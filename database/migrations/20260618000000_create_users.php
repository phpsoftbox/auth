<?php

declare(strict_types=1);

use PhpSoftBox\Database\Migrations\AbstractMigration;
use PhpSoftBox\Database\SchemaBuilder\TableBlueprint;

return new class () extends AbstractMigration {
    public function up(): void
    {
        $this->schema()->create('users', static function (TableBlueprint $table): void {
            $table->comment('Пользователи приложения');

            $table->id()->comment('Внутренний идентификатор пользователя');
            $table->string('email', 255)->comment('Адрес электронной почты пользователя');
            $table->string('name', 255)->nullable()->comment('Отображаемое имя');
            $table->string('password_hash', 255)->nullable()->comment('Хеш пароля');
            $table->boolean('is_active')->default(true)->comment('Признак активного пользователя');
            $table->datetime('created_datetime')->nullable()->comment('Дата и время создания');
            $table->datetime('updated_datetime')->nullable()->comment('Дата и время обновления');

            $table->unique(['email'], 'users_email_unique');
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('users');
    }
};
