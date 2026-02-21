<?php

namespace App\Enum;

enum ActivityAction: string
{
    case CREATED = 'created';
    case UPDATED = 'updated';
    case DELETED = 'deleted';
    case ASSIGNED = 'assigned';
    case UNASSIGNED = 'unassigned';
    case COMMENTED = 'commented';
    case STATUS_CHANGED = 'status_changed';
    case PRIORITY_CHANGED = 'priority_changed';
    case MILESTONE_CHANGED = 'milestone_changed';
    case PROJECT_CHANGED = 'project_changed';
    case MEMBER_ADDED = 'member_added';
    case MEMBER_REMOVED = 'member_removed';
    case MEMBER_ROLE_CHANGED = 'member_role_changed';

    public function label(): string
    {
        return match($this) {
            self::CREATED => 'created',
            self::UPDATED => 'updated',
            self::DELETED => 'deleted',
            self::ASSIGNED => 'assigned',
            self::UNASSIGNED => 'unassigned',
            self::COMMENTED => 'commented',
            self::STATUS_CHANGED => 'changed status of',
            self::PRIORITY_CHANGED => 'changed priority of',
            self::MILESTONE_CHANGED => 'moved to milestone',
            self::PROJECT_CHANGED => 'moved task from project',
            self::MEMBER_ADDED => 'added member to',
            self::MEMBER_REMOVED => 'removed member from',
            self::MEMBER_ROLE_CHANGED => 'changed role of member in',
        };
    }
}
