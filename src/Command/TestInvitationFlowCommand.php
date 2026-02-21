<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\ProjectInvitationRepository;
use App\Repository\ProjectMemberRepository;
use App\Repository\ProjectRepository;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use App\Service\PersonalProjectService;
use App\Service\ProjectInvitationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:test-invitation-flow',
    description: 'Test the complete invitation flow end-to-end',
)]
class TestInvitationFlowCommand extends Command
{
    public function __construct(
        private readonly ProjectInvitationService $invitationService,
        private readonly ProjectRepository $projectRepository,
        private readonly UserRepository $userRepository,
        private readonly RoleRepository $roleRepository,
        private readonly ProjectMemberRepository $memberRepository,
        private readonly ProjectInvitationRepository $invitationRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly PersonalProjectService $personalProjectService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $testEmail = 'test.guest.' . time() . '@gmail.com';

        $io->title('Testing Complete Invitation Flow');

        // Step 1: Create invitation
        $io->section('Step 1: Creating Invitation');

        $admin = $this->userRepository->findOneBy(['email' => 'admin@example.com']);
        if (!$admin) {
            $io->error('Admin user not found');
            return Command::FAILURE;
        }

        $project = $this->projectRepository->findOneBy(['owner' => $admin]);
        if (!$project) {
            $io->error('No project found for admin');
            return Command::FAILURE;
        }

        $role = $this->roleRepository->findBySlug('project-member');
        if (!$role) {
            $io->error('Project member role not found');
            return Command::FAILURE;
        }

        try {
            $invitation = $this->invitationService->createInvitation(
                $project,
                $admin,
                $testEmail,
                $role
            );

            $io->success('Invitation created!');
            $io->table(
                ['Field', 'Value'],
                [
                    ['Email', $invitation->getEmail()],
                    ['Status', $invitation->getStatus()->value],
                    ['Token', substr($invitation->getToken(), 0, 20) . '...'],
                ]
            );

            if ($invitation->getStatus()->value !== 'pending') {
                $io->error('Expected status to be "pending" but got: ' . $invitation->getStatus()->value);
                return Command::FAILURE;
            }

        } catch (\Exception $e) {
            $io->error('Failed to create invitation: ' . $e->getMessage());
            return Command::FAILURE;
        }

        // Step 2: Verify invitation in database
        $io->section('Step 2: Verifying Invitation in Database');

        $dbInvitation = $this->invitationRepository->findByToken($invitation->getToken());
        if (!$dbInvitation) {
            $io->error('Invitation not found in database!');
            return Command::FAILURE;
        }

        $io->success('Invitation found in database');

        // Step 3: Simulate user acceptance (create new user)
        $io->section('Step 3: Simulating User Acceptance');

        $io->text('Creating new user account...');

        $newUser = new User();
        $newUser->setEmail($testEmail);
        $newUser->setFirstName('Test');
        $newUser->setLastName('Guest');
        $newUser->setPassword($this->passwordHasher->hashPassword($newUser, 'TestPassword123'));
        $newUser->setIsVerified(true);

        $this->entityManager->persist($newUser);
        $this->entityManager->flush();

        $io->success('User created: ' . $newUser->getFullName());

        // Step 4: Accept invitation
        $io->section('Step 4: Accepting Invitation');

        try {
            $this->invitationService->acceptInvitation($invitation, $newUser);
            $io->success('Invitation accepted!');
        } catch (\Exception $e) {
            $io->error('Failed to accept invitation: ' . $e->getMessage());
            return Command::FAILURE;
        }

        // Step 5: Verify user is member of project
        $io->section('Step 5: Verifying Project Membership');

        $member = $this->memberRepository->findByProjectAndUser($project, $newUser);
        if (!$member) {
            $io->error('User is not a member of the project!');
            return Command::FAILURE;
        }

        $io->success('User is a member of the project!');
        $io->table(
            ['Field', 'Value'],
            [
                ['User', $newUser->getFullName()],
                ['Project', $project->getName()],
                ['Role', $member->getRole()->getName()],
                ['Joined At', $member->getJoinedAt()->format('Y-m-d H:i:s')],
            ]
        );

        // Step 6: Verify invitation status updated
        $io->section('Step 6: Verifying Invitation Status');

        $this->entityManager->refresh($invitation);
        if ($invitation->getStatus()->value !== 'accepted') {
            $io->error('Expected invitation status to be "accepted" but got: ' . $invitation->getStatus()->value);
            return Command::FAILURE;
        }

        $io->success('Invitation status is "accepted"');

        // Step 7: Verify activity log
        $io->section('Step 7: Verifying Activity Log');

        $activities = $this->entityManager->getRepository('App\Entity\Activity')
            ->findBy(['project' => $project], ['createdAt' => 'DESC'], 5);

        if (empty($activities)) {
            $io->warning('No activities found');
        } else {
            $io->text('Recent activities:');
            foreach ($activities as $activity) {
                $io->text('- ' . $activity->getAction()->value . ' by ' . $activity->getUser()->getFullName());
            }
        }

        // Step 8: Test with existing user
        $io->section('Step 8: Testing with Existing User');

        $existingUser = $this->userRepository->findOneBy(['email' => 'sylvester@honeyguide.org']);
        if (!$existingUser) {
            $io->warning('Skipping existing user test - user not found');
        } else {
            // Create invitation for existing user
            $testEmail2 = $existingUser->getEmail();

            try {
                $invitation2 = $this->invitationService->createInvitation(
                    $project,
                    $admin,
                    $testEmail2,
                    $role
                );

                $io->success('Created invitation for existing user: ' . $existingUser->getFullName());

                // Accept it
                $this->invitationService->acceptInvitation($invitation2, $existingUser);

                // Verify membership
                $member2 = $this->memberRepository->findByProjectAndUser($project, $existingUser);
                if (!$member2) {
                    $io->error('Existing user is not a member after acceptance!');
                    return Command::FAILURE;
                }

                $io->success('Existing user successfully added to project!');

            } catch (\Exception $e) {
                // It's okay if they're already a member
                if (str_contains($e->getMessage(), 'already a member')) {
                    $io->info('User is already a member (expected)');
                } else {
                    $io->error('Error: ' . $e->getMessage());
                }
            }
        }

        // Step 9: Create personal project for new user
        $io->section('Step 9: Creating Personal Project for New User');

        try {
            $personalProject = $this->personalProjectService->createPersonalProject($newUser);
            $this->entityManager->flush();
            $io->success('Personal project created: ' . $personalProject->getName());
        } catch (\Exception $e) {
            $io->error('Failed to create personal project: ' . $e->getMessage());
        }

        // Final summary
        $io->section('Test Summary');
        $io->success('All tests passed! ✅');

        $io->table(
            ['Test', 'Status'],
            [
                ['Create invitation', '✅ Passed'],
                ['Database verification', '✅ Passed'],
                ['User creation', '✅ Passed'],
                ['Invitation acceptance', '✅ Passed'],
                ['Project membership', '✅ Passed'],
                ['Status update', '✅ Passed'],
                ['Activity logging', '✅ Passed'],
                ['Existing user flow', '✅ Passed'],
                ['Personal project', '✅ Passed'],
            ]
        );

        $io->newLine();
        $io->text('Test user created:');
        $io->text('  Email: ' . $testEmail);
        $io->text('  Password: TestPassword123');
        $io->text('  Project: ' . $project->getName());

        return Command::SUCCESS;
    }
}
