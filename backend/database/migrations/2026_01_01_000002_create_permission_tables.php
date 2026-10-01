<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// spatie/laravel-permission v6 tables (teams disabled)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('name');
            $t->string('guard_name');
            $t->string('module')->nullable()->index();   // grouping for the admin matrix
            $t->string('label')->nullable();
            $t->timestamps();
            $t->unique(['name', 'guard_name']);
        });

        Schema::create('roles', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('name');
            $t->string('guard_name');
            $t->string('label')->nullable();
            $t->boolean('is_system')->default(false);     // super_admin/admin/staff/teacher/student cannot be deleted
            $t->timestamps();
            $t->unique(['name', 'guard_name']);
        });

        Schema::create('model_has_permissions', function (Blueprint $t) {
            $t->unsignedBigInteger('permission_id');
            $t->string('model_type');
            $t->unsignedBigInteger('model_id');
            $t->index(['model_id', 'model_type']);
            $t->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
            $t->primary(['permission_id', 'model_id', 'model_type']);
        });

        Schema::create('model_has_roles', function (Blueprint $t) {
            $t->unsignedBigInteger('role_id');
            $t->string('model_type');
            $t->unsignedBigInteger('model_id');
            $t->index(['model_id', 'model_type']);
            $t->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $t->primary(['role_id', 'model_id', 'model_type']);
        });

        Schema::create('role_has_permissions', function (Blueprint $t) {
            $t->unsignedBigInteger('permission_id');
            $t->unsignedBigInteger('role_id');
            $t->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
            $t->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $t->primary(['permission_id', 'role_id']);
        });
    }

    public function down(): void
    {
        foreach (['role_has_permissions', 'model_has_roles', 'model_has_permissions', 'roles', 'permissions'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
