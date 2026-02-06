<?php

declare(strict_types=1);

use PhpSoftBox\Database\Migrations\AbstractMigration;
use PhpSoftBox\Database\SchemaBuilder\TableBlueprint;

return new class () extends AbstractMigration {
    public function up(): void
    {
        $this->schema()->create('user_tokens', static function (TableBlueprint $table): void {
            $table->comment('Токены пользователей');

            $table->id()->comment('Внутренний идентификатор записи');
            $table->string('user_id', 64)->comment('Идентификатор пользователя');
            $table->string('token_type', 32)->comment('Тип токена');
            $table->string('selector', 64)->comment('Публичный селектор токена');
            $table->string('token_hash', 128)->comment('Хеш секретной части токена');
            $table->datetime('expires_datetime')->nullable()->comment('Дата и время истечения токена');
            $table->datetime('revoked_datetime')->nullable()->comment('Дата и время отзыва токена');
            $table->datetime('last_used_datetime')->nullable()->comment('Дата и время последнего использования');
            $table->string('created_ip', 45)->nullable()->comment('Сетевой адрес при создании');
            $table->string('created_user_agent', 512)->nullable()->comment('Пользовательский агент при создании');
            $table->string('last_used_ip', 45)->nullable()->comment('Сетевой адрес последнего использования');
            $table->string('last_used_user_agent', 512)->nullable()->comment('Пользовательский агент последнего использования');
            $table->json('metadata')->nullable()->comment('Дополнительные данные токена');
            $table->datetime('created_datetime')->comment('Дата и время создания');

            $table->unique(['selector'], 'user_tokens_selector_unique');
            $table->index(['user_id', 'token_type'], 'user_tokens_user_id_token_type_index');
            $table->index(['expires_datetime'], 'user_tokens_expires_datetime_index');
            $table->index(['revoked_datetime'], 'user_tokens_revoked_datetime_index');
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('user_tokens');
    }
};
