<?php

namespace App\Enum;

enum NotificationType: string
{
    case TASK_ASSIGNED = 'task_assigned';
    case TASK_UNASSIGNED = 'task_unassigned';
    case TASK_COMPLETED = 'task_completed';
    case TASK_DUE_SOON = 'task_due_soon';
    case TASK_OVERDUE = 'task_overdue';
    case COMMENT_ADDED = 'comment_added';
    case MENTIONED = 'mentioned';
    case COMMENT_REPLY = 'comment_reply';
    case PROJECT_INVITED = 'project_invited';
    case PROJECT_REMOVED = 'project_removed';
    case MILESTONE_DUE = 'milestone_due';
    case TASK_STATUS_CHANGED = 'task_status_changed';
    case ATTACHMENT_ADDED = 'attachment_added';

    public function label(): string
    {
        return match ($this) {
            self::TASK_ASSIGNED => 'Task Assigned',
            self::TASK_UNASSIGNED => 'Task Unassigned',
            self::TASK_COMPLETED => 'Task Completed',
            self::TASK_DUE_SOON => 'Task Due Soon',
            self::TASK_OVERDUE => 'Task Overdue',
            self::COMMENT_ADDED => 'New Comment',
            self::MENTIONED => 'Mentioned',
            self::COMMENT_REPLY => 'Comment Reply',
            self::PROJECT_INVITED => 'Project Invitation',
            self::PROJECT_REMOVED => 'Removed from Project',
            self::MILESTONE_DUE => 'Milestone Due',
            self::TASK_STATUS_CHANGED => 'Task Status Changed',
            self::ATTACHMENT_ADDED => 'Attachment Added',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::TASK_ASSIGNED, self::TASK_UNASSIGNED => 'user-plus',
            self::TASK_COMPLETED => 'check-circle',
            self::TASK_DUE_SOON, self::TASK_OVERDUE => 'clock',
            self::COMMENT_ADDED, self::COMMENT_REPLY => 'chat-bubble',
            self::MENTIONED => 'at-symbol',
            self::PROJECT_INVITED, self::PROJECT_REMOVED => 'folder',
            self::MILESTONE_DUE => 'flag',
            self::TASK_STATUS_CHANGED => 'arrow-path',
            self::ATTACHMENT_ADDED => 'paper-clip',
        };
    }

    public function defaultInApp(): bool
    {
        return true;
    }

    public function defaultEmail(): bool
    {
        return match ($this) {
            self::TASK_ASSIGNED, self::MENTIONED, self::PROJECT_INVITED => true,
            default => false,
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::TASK_ASSIGNED, self::TASK_UNASSIGNED, self::TASK_COMPLETED,
            self::TASK_DUE_SOON, self::TASK_OVERDUE, self::TASK_STATUS_CHANGED => 'Tasks',
            self::COMMENT_ADDED, self::MENTIONED, self::COMMENT_REPLY => 'Comments',
            self::PROJECT_INVITED, self::PROJECT_REMOVED, self::MILESTONE_DUE => 'Projects',
            self::ATTACHMENT_ADDED => 'Tasks',
        };
    }
}
