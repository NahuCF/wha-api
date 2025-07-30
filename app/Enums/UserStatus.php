<?php

namespace App\Enums;

enum UserStatus: string
{
    case SIGNED_UP = 'SIGNED_UP';
    case ACTIVE = 'ACTIVE';
    case INACTIVE = 'INACTIVE';
    case DELETED = 'DELETED';
    case INVITED = 'INVITATION_SENT';
    case INVITATION_ACCEPTED = 'INVITATION_ACCEPTED';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
