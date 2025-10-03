<?php

namespace App\Enums;

enum BotNodeType: string
{
    case MESSAGE = 'message';  
    case TEMPLATE = 'template';  
    case IMAGE = 'image';
    case VIDEO = 'video';
    case AUDIO = 'audio';
    case DOCUMENT = 'document';
    case QUESTION_BUTTON = 'question_button';
    case CONDITION = 'condition';
    case START_AGAIN = 'start_again';
    case MARK_AS_SOLVED = 'mark_as_solved';
    case ASSIGN_CHAT = 'assign_chat';
    case LOCATION = 'location';
    case WORKING_HOURS = 'working_hours';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
