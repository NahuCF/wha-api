<?php

namespace App\Enums;

enum SystemPermission: string
{
    case VIEW_AND_MANAGE_CONTACT = 'view_and_manage_contact';
    case VIEW_AND_MANAGE_CAMPAIGNS = 'view_and_manage_campaigns';
    case VIEW_AND_MANAGE_CONVERSATIONS = 'view_and_manage_conversations';
    case BUILD_AND_DEPLOY_BOTS = 'build_and_deploy_bots';
    case VIEW_AND_MANAGE_CONTACT_FIELDS = 'view_and_manage_contact_fields';
    case VIEW_ANALYTICS = 'view_analytics';
    case VIEW_USER_ROLES_AND_TEAMS = 'view_user_roles_and_teams';
    case MANAGE_USER_ROLES_AND_TEAMS = 'manage_user_roles_and_teams';
    case VIEW_AND_MANAGE_TEMPLATES = 'view_and_manage_templates';
    case VIEW_AND_MANAGE_WEBHOOKS_AND_API_KEYS = 'view_and_manage_webhooks_and_api_keys';
    case VIEW_AND_MANAGE_ACCOUNT_SETTINGS = 'view_and_manage_account_settings';
}
