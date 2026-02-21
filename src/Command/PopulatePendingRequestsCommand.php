<?php

namespace App\Command;

use App\Entity\PendingRegistrationRequest;
use App\Entity\ProjectInvitation;
use App\Enum\ProjectInvitationStatus;
use App\Enum\RegistrationRequestStatus;
use App\Enum\RegistrationType;
use App\Repository\ProjectRepository;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use App\Service\ProjectInvitationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:populate-pending-requests',
    description: 'Populate test pending requests and invitations',
)]
class PopulatePendingRequestsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly ProjectRepository $projectRepository,
        private readonly RoleRepository $roleRepository,
        private readonly ProjectInvitationService $invitationService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Populating Pending Requests and Invitations');

        // Get admin user and project for invitations
        $admin = $this->userRepository->findOneBy(['email' => 'admin@example.com']);
        if (!$admin) {
            $io->error('Admin user not found');
            return Command::FAILURE;
        }

        $project = $this->projectRepository->findOneBy(['owner' => $admin]);
        if (!$project) {
            $io->error('Admin project not found');
            return Command::FAILURE;
        }

        $memberRole = $this->roleRepository->findBySlug('project-member');
        if (!$memberRole) {
            $io->error('Project member role not found');
            return Command::FAILURE;
        }

        // Create registration requests
        $io->section('Creating Registration Requests');

        $registrationRequests = [
            [
                'firstName' => 'Alice',
                'lastName' => 'Johnson',
                'email' => 'alice.johnson@restricted-domain.com',
                'type' => RegistrationType::MANUAL,
            ],
            [
                'firstName' => 'Bob',
                'lastName' => 'Smith',
                'email' => 'bob.smith@gmail.com',
                'type' => RegistrationType::GOOGLE,
            ],
            [
                'firstName' => 'Carol',
                'lastName' => 'Williams',
                'email' => 'carol.williams@company.org',
                'type' => RegistrationType::MANUAL,
            ],
        ];

        foreach ($registrationRequests as $data) {
            $request = new PendingRegistrationRequest();
            $request->setFirstName($data['firstName']);
            $request->setLastName($data['lastName']);
            $request->setEmail($data['email']);
            $request->setRegistrationType($data['type']);
            $request->setStatus(RegistrationRequestStatus::PENDING);

            $this->entityManager->persist($request);
            $io->text('✓ Created registration request for ' . $data['firstName'] . ' ' . $data['lastName']);
        }

        $this->entityManager->flush();
        $io->success('Created ' . count($registrationRequests) . ' registration requests');

        // Create project invitations
        $io->section('Creating Project Invitations');

        $invitations = [
            [
                'email' => 'david.brown@external-company.com',
                'role' => $memberRole,
            ],
            [
                'email' => 'emma.davis@partner-org.net',
                'role' => $memberRole,
            ],
            [
                'email' => 'frank.miller@restricted-domain.com',
                'role' => $memberRole,
            ],
        ];

        foreach ($invitations as $data) {
            try {
                $invitation = $this->invitationService->createInvitation(
                    $project,
                    $admin,
                    $data['email'],
                    $data['role']
                );

                // Update status to pending_admin_approval to simulate admin approval workflow
                $invitation->setStatus(ProjectInvitationStatus::PENDING_ADMIN_APPROVAL);

                $io->text('✓ Created invitation for ' . $data['email']);
            } catch (\Exception $e) {
                $io->warning('Skipped ' . $data['email'] . ': ' . $e->getMessage());
            }
        }

        $this->entityManager->flush();
        $io->success('Created ' . count($invitations) . ' project invitations');

        // Summary
        $io->newLine();
        $io->section('Summary');
        $io->table(
            ['Type', 'Count'],
            [
                ['Registration Requests', count($registrationRequests)],
                ['Project Invitations', count($invitations)],
                ['Total Pending', count($registrationRequests) + count($invitations)],
            ]
        );

        $io->newLine();
        $io->info('Visit Settings → User Permissions → Manage Users → Pending Requests tab to review');

        return Command::SUCCESS;
    }
}
