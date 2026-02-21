<?php

namespace App\Service;

use App\Entity\Activity;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\ActivityAction;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\UuidInterface;

class ActivityService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function log(
        Project $project,
        User $user,
        string $entityType,
        UuidInterface $entityId,
        ActivityAction $action,
        ?string $entityName = null,
        ?array $metadata = null,
    ): Activity {
        $activity = new Activity();
        $activity->setProject($project);
        $activity->setUser($user);
        $activity->setEntityType($entityType);
        $activity->setEntityId($entityId);
        $activity->setAction($action);
        $activity->setEntityName($entityName);
        $activity->setMetadata($metadata);

        $this->entityManager->persist($activity);

        return $activity;
    }

    public function logProjectCreated(Project $project, User $user): Activity
    {
        return $this->log(
            $project,
            $user,
            'project',
            $project->getId(),
            ActivityAction::CREATED,
            $project->getName(),
        );
    }

    public function logProjectUpdated(Project $project, User $user, array $changes = []): Activity
    {
        return $this->log(
            $project,
            $user,
            'project',
            $project->getId(),
            ActivityAction::UPDATED,
            $project->getName(),
            $changes ? ['changes' => $changes] : null,
        );
    }

    public function logMilestoneCreated(Project $project, User $user, UuidInterface $milestoneId, string $milestoneName): Activity
    {
        return $this->log(
            $project,
            $user,
            'milestone',
            $milestoneId,
            ActivityAction::CREATED,
            $milestoneName,
        );
    }

    public function logTaskCreated(Project $project, User $user, UuidInterface $taskId, string $taskTitle): Activity
    {
        return $this->log(
            $project,
            $user,
            'task',
            $taskId,
            ActivityAction::CREATED,
            $taskTitle,
        );
    }

    public function logTaskUpdated(Project $project, User $user, UuidInterface $taskId, string $taskTitle, array $changes = []): Activity
    {
        return $this->log(
            $project,
            $user,
            'task',
            $taskId,
            ActivityAction::UPDATED,
            $taskTitle,
            $changes ? ['changes' => $changes] : null,
        );
    }

    public function logTaskStatusChanged(Project $project, User $user, UuidInterface $taskId, string $taskTitle, string $oldStatus, string $newStatus): Activity
    {
        return $this->log(
            $project,
            $user,
            'task',
            $taskId,
            ActivityAction::STATUS_CHANGED,
            $taskTitle,
            ['from' => $oldStatus, 'to' => $newStatus],
        );
    }

    public function logTaskPriorityChanged(Project $project, User $user, UuidInterface $taskId, string $taskTitle, string $oldPriority, string $newPriority): Activity
    {
        return $this->log(
            $project,
            $user,
            'task',
            $taskId,
            ActivityAction::PRIORITY_CHANGED,
            $taskTitle,
            ['from' => $oldPriority, 'to' => $newPriority],
        );
    }

    public function logTaskMilestoneChanged(Project $project, User $user, UuidInterface $taskId, string $taskTitle, string $oldMilestone, string $newMilestone): Activity
    {
        return $this->log(
            $project,
            $user,
            'task',
            $taskId,
            ActivityAction::MILESTONE_CHANGED,
            $taskTitle,
            ['from' => $oldMilestone, 'to' => $newMilestone],
        );
    }

    public function logTaskAssigned(Project $project, User $user, UuidInterface $taskId, string $taskTitle, string $assigneeName): Activity
    {
        return $this->log(
            $project,
            $user,
            'task',
            $taskId,
            ActivityAction::ASSIGNED,
            $taskTitle,
            ['assignee' => $assigneeName],
        );
    }

    public function logTaskUnassigned(Project $project, User $user, UuidInterface $taskId, string $taskTitle, string $assigneeName): Activity
    {
        return $this->log(
            $project,
            $user,
            'task',
            $taskId,
            ActivityAction::UNASSIGNED,
            $taskTitle,
            ['assignee' => $assigneeName],
        );
    }

    public function logCommentAdded(Project $project, User $user, UuidInterface $taskId, string $taskTitle): Activity
    {
        return $this->log(
            $project,
            $user,
            'task',
            $taskId,
            ActivityAction::COMMENTED,
            $taskTitle,
        );
    }

    public function logMemberAdded(Project $project, User $user, string $memberName, string $role): Activity
    {
        return $this->log(
            $project,
            $user,
            'project',
            $project->getId(),
            ActivityAction::MEMBER_ADDED,
            $project->getName(),
            ['member' => $memberName, 'role' => $role],
        );
    }

    public function logMemberRemoved(Project $project, User $user, string $memberName): Activity
    {
        return $this->log(
            $project,
            $user,
            'project',
            $project->getId(),
            ActivityAction::MEMBER_REMOVED,
            $project->getName(),
            ['member' => $memberName],
        );
    }

    public function logTaskProjectChanged(
        Project $newProject,
        User $user,
        UuidInterface $taskId,
        string $taskTitle,
        string $oldProjectName
    ): Activity {
        return $this->log(
            $newProject,
            $user,
            'task',
            $taskId,
            ActivityAction::PROJECT_CHANGED,
            $taskTitle,
            ['from' => $oldProjectName],
        );
    }
}
