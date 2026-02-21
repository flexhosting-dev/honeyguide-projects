<?php

namespace App\Command;

use App\Repository\ProjectRepository;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use App\Service\ProjectInvitationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-invitation',
    description: 'Test the project invitation flow',
)]
class TestInvitationCommand extends Command
{
    public function __construct(
        private readonly ProjectInvitationService $invitationService,
        private readonly ProjectRepository $projectRepository,
        private readonly UserRepository $userRepository,
        private readonly RoleRepository $roleRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email to invite')
            ->addArgument('projectId', InputArgument::OPTIONAL, 'Project ID (optional)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');
        $projectId = $input->getArgument('projectId');

        // Get admin user
        $admin = $this->userRepository->findOneBy(['email' => 'admin@example.com']);
        if (!$admin) {
            $io->error('Admin user not found');
            return Command::FAILURE;
        }

        // Get project
        if ($projectId) {
            $project = $this->projectRepository->find($projectId);
        } else {
            $project = $this->projectRepository->findOneBy(['owner' => $admin]);
        }

        if (!$project) {
            $io->error('No project found');
            return Command::FAILURE;
        }

        // Get project-member role
        $role = $this->roleRepository->findBySlug('project-member');
        if (!$role) {
            $io->error('Project member role not found');
            return Command::FAILURE;
        }

        $io->section('Test Configuration');
        $io->table(
            ['Setting', 'Value'],
            [
                ['Email', $email],
                ['Domain', substr(strrchr($email, '@'), 1)],
                ['Invited by', $admin->getFullName() . ' (' . $admin->getEmail() . ')'],
                ['Project', $project->getName()],
                ['Role', $role->getName()],
                ['Is Admin', $admin->isPortalAdmin() ? 'YES' : 'NO'],
            ]
        );

        try {
            $io->info('Creating invitation...');
            $invitation = $this->invitationService->createInvitation(
                $project,
                $admin,
                $email,
                $role
            );

            $io->success('Invitation created successfully!');

            $io->section('Invitation Details');
            $io->table(
                ['Field', 'Value'],
                [
                    ['ID', $invitation->getId()],
                    ['Email', $invitation->getEmail()],
                    ['Status', $invitation->getStatus()->value],
                    ['Token', $invitation->getToken()],
                    ['Expires', $invitation->getExpiresAt()->format('Y-m-d H:i:s')],
                    ['Created', $invitation->getCreatedAt()->format('Y-m-d H:i:s')],
                ]
            );

            if ($invitation->getStatus()->value === 'pending_admin_approval') {
                $io->warning('Status: PENDING_ADMIN_APPROVAL');
                $io->text('Admins have been notified to approve this invitation.');
                $io->text('The invitation email will NOT be sent until an admin approves it.');
            } else {
                $io->success('Status: PENDING');
                $io->text('Invitation email has been sent to: ' . $invitation->getEmail());
                $io->newLine();
                $io->text('Acceptance URL:');
                $io->text('https://dev.flexhosting.co/zohoclone/invitations/' . $invitation->getToken() . '/accept');
            }

            // Check notifications
            $notifications = $this->entityManager->getRepository('App\Entity\Notification')
                ->findBy(['entityId' => $project->getId()], ['createdAt' => 'DESC'], 5);

            if (!empty($notifications)) {
                $io->section('Recent Notifications');
                foreach ($notifications as $notification) {
                    $io->text('- ' . $notification->getType()->value . ' to ' . $notification->getRecipient()->getEmail());
                }
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Error: ' . $e->getMessage());
            $io->text($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}
