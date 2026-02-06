<?php

declare(strict_types=1);

use PhpSoftBox\Database\Migrations\AbstractMigration;
use PhpSoftBox\Database\SchemaBuilder\TableBlueprint;

return new class () extends AbstractMigration {
    public function up(): void
    {
        $this->schema()->create('roles', static function (TableBlueprint $table): void {
            $table->comment('Роли авторизации');

            $table->id()->comment('Внутренний идентификатор роли');
            $table->string('name', 100)->comment('Системное имя роли');
            $table->string('label', 255)->nullable()->comment('Название роли');
            $table->boolean('admin_access')->default(false)->comment('Доступ в административную область');
            $table->datetime('created_datetime')->nullable()->comment('Дата и время создания');
            $table->datetime('updated_datetime')->nullable()->comment('Дата и время обновления');

            $table->unique(['name'], 'roles_name_unique');
        });

        $this->schema()->create('permissions', static function (TableBlueprint $table): void {
            $table->comment('Права доступа');

            $table->id()->comment('Внутренний идентификатор права');
            $table->string('name', 150)->comment('Системное имя права');
            $table->string('label', 255)->nullable()->comment('Название права');
            $table->datetime('created_datetime')->nullable()->comment('Дата и время создания');
            $table->datetime('updated_datetime')->nullable()->comment('Дата и время обновления');

            $table->unique(['name'], 'permissions_name_unique');
        });

        $this->schema()->create('user_roles', static function (TableBlueprint $table): void {
            $table->comment('Связь пользователей и ролей');

            $table->string('user_id', 64)->comment('Идентификатор пользователя');
            $table->integer('role_id')->comment('Идентификатор роли');

            $table->unique(['user_id', 'role_id'], 'user_roles_user_id_role_id_unique');
            $table->index(['role_id'], 'user_roles_role_id_index');
        });

        $this->schema()->create('role_permissions', static function (TableBlueprint $table): void {
            $table->comment('Связь ролей и прав доступа');

            $table->integer('role_id')->comment('Идентификатор роли');
            $table->integer('permission_id')->comment('Идентификатор права');

            $table->unique(['role_id', 'permission_id'], 'role_permissions_role_id_permission_id_unique');
            $table->index(['permission_id'], 'role_permissions_permission_id_index');
        });

        $this->schema()->create('user_permissions', static function (TableBlueprint $table): void {
            $table->comment('Связь пользователей и прав доступа');

            $table->string('user_id', 64)->comment('Идентификатор пользователя');
            $table->integer('permission_id')->comment('Идентификатор права');

            $table->unique(['user_id', 'permission_id'], 'user_permissions_user_id_permission_id_unique');
            $table->index(['permission_id'], 'user_permissions_permission_id_index');
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('user_permissions');
        $this->schema()->dropIfExists('role_permissions');
        $this->schema()->dropIfExists('user_roles');
        $this->schema()->dropIfExists('permissions');
        $this->schema()->dropIfExists('roles');
    }
};
