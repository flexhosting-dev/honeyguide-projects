<?php

namespace App\Enum;

enum ProjectInvitationStatus: string
{
    case PENDING = 'pending';
    case ACCEPTED = 'accepted';
    case DECLINED = 'declined';
    case EXPIRED = 'expired';
    case PENDING_ADMIN_APPROVAL = 'pending_admin_approval';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::ACCEPTED => 'Accepted',
            self::DECLINED => 'Declined',
            self::EXPIRED => 'Expired',
            self::PENDING_ADMIN_APPROVAL => 'Pending Admin Approval',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'yellow',
            self::ACCEPTED => 'green',
            self::DECLINED => 'red',
            self::EXPIRED => 'gray',
            self::PENDING_ADMIN_APPROVAL => 'blue',
        };
    }

    public function isPending(): bool
    {
        return $this === self::PENDING || $this === self::PENDING_ADMIN_APPROVAL;
    }
}
