<?php

use App\Enums\SystemPermission;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    private $permissions = [
        'contact' => [
            [
                'name' => SystemPermission::VIEW_AND_MANAGE_CONTACT,
                'label' => 'Can view and manage Contact List',
                'description' => 'Allows the user to create, update, and delete contact list entries and fields.',
            ],
        ],
        'campaigns' => [
            [
                'name' => SystemPermission::VIEW_AND_MANAGE_CAMPAIGNS,
                'label' => 'Can view and manage Campaigns',
                'description' => 'Allows the user to create, edit, and launch marketing campaigns.',
            ],
        ],
        'conversations' => [
            [
                'name' => SystemPermission::VIEW_AND_MANAGE_CONVERSATIONS,
                'label' => 'Can view and manage conversations',
                'description' => 'Allows full access to chat conversations.',
            ],
        ],
        'bots' => [
            [
                'name' => SystemPermission::BUILD_AND_DEPLOY_BOTS,
                'label' => 'Can build and deploy Bots',
                'description' => 'Grants access to bot builder and deployment tools.',
            ],
        ],
        'settings' => [
            [
                'name' => SystemPermission::VIEW_AND_MANAGE_CONTACT_FIELDS,
                'label' => 'Can view and manage Contact Fields',
                'description' => 'Allows customization of contact field structure and labels.',
            ],
            [
                'name' => SystemPermission::VIEW_ANALYTICS,
                'label' => 'Can view Analytics',
                'description' => 'Grants read-only access to dashboard analytics and reports.',
            ],
            [
                'name' => SystemPermission::VIEW_USER_ROLES_AND_TEAMS,
                'label' => 'Can view Users, Roles & Teams',
                'description' => 'Allows viewing of users, role assignments, and teams.',
            ],
            [
                'name' => SystemPermission::MANAGE_USER_ROLES_AND_TEAMS,
                'label' => 'Can manage Users, Roles & Teams',
                'description' => 'Allows creation and management of users, roles, and teams.',
            ],
            [
                'name' => SystemPermission::VIEW_AND_MANAGE_TEMPLATES,
                'label' => 'Can view and manage Templates',
                'description' => 'Grants access to create, edit, and delete message templates.',
            ],
        ],
        'development' => [
            [
                'name' => SystemPermission::VIEW_AND_MANAGE_WEBHOOKS_AND_API_KEYS,
                'label' => 'Can view and manage webhooks, API keys',
                'description' => 'Grants access to integration settings such as webhooks and API tokens.',
            ],
        ],
        'account' => [
            [
                'name' => SystemPermission::VIEW_AND_MANAGE_ACCOUNT_SETTINGS,
                'label' => 'Can view and manage account settings',
                'description' => 'Grants access to account settings.',
            ],
        ],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {

        foreach ($this->permissions as $group => $items) {
            foreach ($items as $item) {
                Permission::create([
                    'name' => $group.'.'.$item['name']->value,
                    'label' => $item['label'],
                    'description' => $item['description'],
                    'guard_name' => 'api',
                ]);
            }
        }

        $owner = Role::create(['name' => 'Owner', 'is_internal' => true]);
        $admin = Role::create(['name' => 'Admin', 'is_internal' => true]);
        $member = Role::create(['name' => 'Member', 'is_internal' => true]);

        $all = Permission::all();

        $owner->syncPermissions($all);

        $admin->syncPermissions(
            $all->filter(fn ($perm) => $perm->name !== 'account.view_and_manage_account_settings')
        );

        $member->syncPermissions(
            $all->filter(fn ($perm) => in_array($perm->name, [
                'contact.view_and_manage_contact_list',
                'conversations.view_and_manage_conversations',
                'settings.view_and_manage_contact_fields',
            ]))
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Role::whereIn('name', ['Owner', 'Admin', 'Member'])->delete();
    }
};
