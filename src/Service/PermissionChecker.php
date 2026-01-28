<?php

namespace App\Service;

use App\Entity\Comment;
use App\Entity\Milestone;
use App\Entity\Project;
use App\Entity\ProjectMember;
use App\Entity\Role;
use App\Entity\Task;
use App\Entity\TaskChecklist;
use App\Entity\User;
use App\Enum\Permission;
use App\Repository\ProjectMemberRepository;

/**
 * Central service for checking user permissions.
 * Handles both portal-level and project-level authorization.
 */
class PermissionChecker
{
    public function __construct(
        private ProjectMemberRepository $memberRepository,
    ) {
    }

    /**
     * Check if a user has a specific permission, optionally within a resource context.
     */
    public function hasPermission(User $user, string $permission, ?object $subject = null): bool
    {
        // 1. Portal SuperAdmin has all permissions
        if ($user->isPortalSuperAdmin()) {
            return true;
        }

        // 2. Check portal-level permission
        if ($this->isPortalPermission($permission)) {
            return $user->hasPortalPermission($permission);
        }

        // 3. Portal Admin has access to all projects for project-level permissions
        if ($user->isPortalAdmin()) {
            return true;
        }

        // 4. Get project context from subject
        $project = $this->getProjectFromSubject($subject);
        if (!$project) {
            return false;
        }

        // 5. PUBLIC PROJECT: Grant view-only permissions to any authenticated user
        if ($project->isPublic() && $this->isViewOnlyPermission($permission)) {
            return true;
        }

        // 6. Project owner has full access
        if ($project->getOwner()->getId()->equals($user->getId())) {
            return true;
        }

        // 7. Check project membership and role permissions
        $membership = $this->getMembership($user, $project);
        if (!$membership) {
            return false;
        }

        return $membership->hasPermission($permission);
    }

    /**
     * Check if user can perform action on their own resource (e.g., own comment).
     */
    public function hasOwnResourcePermission(
        User $user,
        string $ownPermission,
        string $anyPermission,
        object $subject,
        User $resourceOwner
    ): bool {
        // Check if user owns the resource
        if ($user->getId()->equals($resourceOwner->getId())) {
            return $this->hasPermission($user, $ownPermission, $subject);
        }

        // Otherwise need "any" permission
        return $this->hasPermission($user, $anyPermission, $subject);
    }

    /**
     * Get the project context from various subject types.
     */
    public function getProjectFromSubject(?object $subject): ?Project
    {
        if ($subject === null) {
            return null;
        }

        return match (true) {
            $subject instanceof Project => $subject,
            $subject instanceof Milestone => $subject->getProject(),
            $subject instanceof Task => $subject->getMilestone()->getProject(),
            $subject instanceof TaskChecklist => $subject->getTask()->getMilestone()->getProject(),
            $subject instanceof Comment => $subject->getTask()->getMilestone()->getProject(),
            default => null,
        };
    }

    /**
     * Get user's membership in a project.
     */
    public function getMembership(User $user, Project $project): ?ProjectMember
    {
        return $this->memberRepository->findOneBy([
            'user' => $user,
            'project' => $project,
        ]);
    }

    /**
     * Check if user is a member of the project (any role).
     * For public projects, any authenticated user has view access.
     */
    public function isMember(User $user, Project $project): bool
    {
        if ($user->isPortalAdmin()) {
            return true;
        }

        if ($project->getOwner()->getId()->equals($user->getId())) {
            return true;
        }

        // Public projects: any authenticated user has view access
        if ($project->isPublic()) {
            return true;
        }

        return $this->getMembership($user, $project) !== null;
    }

    /**
     * Check if a permission is portal-level (not project-specific).
     */
    private function isPortalPermission(string $permission): bool
    {
        return in_array($permission, Permission::getPortalPermissions(), true);
    }

    /**
     * Check if a permission is view-only (read access).
     */
    private function isViewOnlyPermission(string $permission): bool
    {
        return in_array($permission, [
            Permission::PROJECT_VIEW,
            Permission::MILESTONE_VIEW,
            Permission::TASK_VIEW,
            Permission::CHECKLIST_VIEW,
            Permission::COMMENT_VIEW,
            Permission::TAG_VIEW,
        ], true);
    }

    /**
     * Get user's role in a project.
     */
    public function getProjectRole(User $user, Project $project): ?Role
    {
        // Portal admins get implicit full access
        if ($user->isPortalAdmin()) {
            return null; // They bypass role checks
        }

        // Project owner gets implicit full access
        if ($project->getOwner()->getId()->equals($user->getId())) {
            return null; // They bypass role checks
        }

        $membership = $this->getMembership($user, $project);
        return $membership?->getRole();
    }

    /**
     * Get all permissions a user has for a specific project.
     */
    public function getProjectPermissions(User $user, Project $project): array
    {
        // SuperAdmin/Admin have all permissions
        if ($user->isPortalAdmin()) {
            return Permission::getProjectPermissions();
        }

        // Owner has all permissions
        if ($project->getOwner()->getId()->equals($user->getId())) {
            return Permission::getProjectPermissions();
        }

        $membership = $this->getMembership($user, $project);
        if (!$membership) {
            return [];
        }

        return $membership->getRole()->getPermissions();
    }
}
