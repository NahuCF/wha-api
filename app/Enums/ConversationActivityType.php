<?php

namespace App\Enums;

enum ConversationActivityType: string
{
    case ASSIGNED = 'assigned';
    case UNASSIGNED = 'unassigned';
    case RESOLVED = 'resolved';
    case REOPENED = 'reopened';
    case CONVERSATION_STARTED = 'conversation_started';
    case CONVERSATION_EXPIRED = 'conversation_expired';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
