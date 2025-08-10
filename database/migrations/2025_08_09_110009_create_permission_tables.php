<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableNames = config('permission.table_names', [
            'permissions' => 'permissions',
            'roles' => 'roles',
            'model_has_permissions' => 'model_has_permissions',
            'model_has_roles' => 'model_has_roles',
            'role_has_permissions' => 'role_has_permissions',
        ]);

        Schema::create($tableNames['permissions'], function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignUlid('tenant_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('label')->nullable();
            $table->text('description')->nullable();
            $table->string('guard_name');
            $table->string('group')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique(['tenant_id', 'name', 'guard_name']);
        });

        Schema::create($tableNames['roles'], function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignUlid('tenant_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('guard_name');
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_internal')->default(false);
            $table->foreignUlid('user_id')->nullable()->constrained();
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique(['tenant_id', 'name', 'guard_name']);
        });

        Schema::create($tableNames['model_has_permissions'], function (Blueprint $table) use ($tableNames) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->ulid('model_id');
            $table->foreignUlid('tenant_id')->nullable()->constrained()->onDelete('cascade');

            $table->foreign('permission_id')->references('id')->on($tableNames['permissions'])->onDelete('cascade');

            $table->index(['model_id', 'model_type']);
            $table->index('tenant_id');
            $table->primary(['permission_id', 'model_id', 'model_type']);
        });

        Schema::create($tableNames['model_has_roles'], function (Blueprint $table) use ($tableNames) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->ulid('model_id');
            $table->foreignUlid('tenant_id')->nullable()->constrained()->onDelete('cascade');

            $table->foreign('role_id')->references('id')->on($tableNames['roles'])->onDelete('cascade');

            $table->index(['model_id', 'model_type']);
            $table->index('tenant_id');
            $table->primary(['role_id', 'model_id', 'model_type']);
        });

        Schema::create($tableNames['role_has_permissions'], function (Blueprint $table) use ($tableNames) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->foreignUlid('tenant_id')->nullable()->constrained()->onDelete('cascade');

            $table->foreign('permission_id')->references('id')->on($tableNames['permissions'])->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on($tableNames['roles'])->onDelete('cascade');

            $table->index('tenant_id');
            $table->primary(['permission_id', 'role_id']);
        });
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names', [
            'permissions' => 'permissions',
            'roles' => 'roles',
            'model_has_permissions' => 'model_has_permissions',
            'model_has_roles' => 'model_has_roles',
            'role_has_permissions' => 'role_has_permissions',
        ]);

        Schema::dropIfExists($tableNames['role_has_permissions']);
        Schema::dropIfExists($tableNames['model_has_roles']);
        Schema::dropIfExists($tableNames['model_has_permissions']);
        Schema::dropIfExists($tableNames['roles']);
        Schema::dropIfExists($tableNames['permissions']);
    }
};
