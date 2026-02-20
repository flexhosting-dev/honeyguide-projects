<?php

namespace App\Service;

use App\Entity\Milestone;
use App\Entity\Project;
use App\Entity\ProjectMember;
use App\Entity\User;
use App\Repository\ProjectRepository;
use App\Repository\RoleRepository;
use Doctrine\ORM\EntityManagerInterface;

class PersonalProjectService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RoleRepository $roleRepository,
        private readonly ProjectRepository $projectRepository,
        private readonly ActivityService $activityService,
    ) {
    }

    /**
     * Create a personal project for a user.
     */
    public function createPersonalProject(User $user): Project
    {
        // Check if user already has a personal project
        $existing = $this->projectRepository->findPersonalProjectForUser($user);
        if ($existing) {
            return $existing;
        }

        // Create the project
        $project = new Project();
        $project->setName($this->generateProjectName($user));
        $project->setOwner($user);
        $project->setIsPublic(false);
        $project->setIsPersonal(true);
        $project->setDescription('Your personal workspace for tasks and projects.');

        // Add owner as project manager
        $managerRole = $this->roleRepository->findBySlug('project-manager');
        if (!$managerRole) {
            throw new \RuntimeException('Project Manager role not found. Please run fixtures.');
        }

        $member = new ProjectMember();
        $member->setUser($user);
        $member->setRole($managerRole);
        $project->addMember($member);

        // Create a default milestone for tasks
        $milestone = new Milestone();
        $milestone->setName('General');
        $milestone->setProject($project);
        $milestone->setPosition(0);
        $milestone->setIsDefault(true);

        $this->entityManager->persist($project);
        $this->entityManager->persist($milestone);

        // Log activity
        $this->activityService->logProjectCreated($project, $user);

        return $project;
    }

    /**
     * Generate the personal project name for a user.
     */
    public function generateProjectName(User $user): string
    {
        $firstName = trim($user->getFirstName());

        if (empty($firstName)) {
            // Fall back to email prefix if no first name
            $email = $user->getEmail();
            $firstName = explode('@', $email)[0];
        }

        return $firstName . "'s Personal Project";
    }

    /**
     * Update personal project name if user's name changes.
     */
    public function updateProjectNameIfNeeded(User $user): void
    {
        $project = $this->projectRepository->findPersonalProjectForUser($user);
        if (!$project) {
            return;
        }

        $expectedName = $this->generateProjectName($user);
        if ($project->getName() !== $expectedName) {
            $project->setName($expectedName);
        }
    }
}
