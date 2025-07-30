<?php

namespace App\Enums;

enum SystemRole: string
{
    case OWNER = 'Owner';
    case ADMIN = 'Admin';
    case MEMBER = 'Member';
}
