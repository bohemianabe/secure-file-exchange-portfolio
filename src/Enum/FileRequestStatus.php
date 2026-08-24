<?php

namespace App\Enum;

enum FileRequestStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Canceled = 'cancelled';
    case InvitationExpired = 'invitation_expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Completed => 'Completed',
            self::Canceled => 'Canceled',
            self::InvitationExpired => 'Invitation Expired',
        };
    }
}
