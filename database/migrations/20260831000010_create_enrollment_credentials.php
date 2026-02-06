<?php

declare(strict_types=1);

use PhpSoftBox\Database\Migrations\AbstractMigration;
use PhpSoftBox\Database\SchemaBuilder\TableBlueprint;

return new class () extends AbstractMigration {
    public function up(): void
    {
        $this->schema()->create('enrollment_credentials', static function (TableBlueprint $table): void {
            $table->comment('Одноразовые credentials для начального подключения субъектов');

            $table->id()->comment('Внутренний идентификатор записи');
            $table->string('subject_id', 64)->comment('Идентификатор подключаемого субъекта');
            $table->string('audience', 64)->comment('API, принимающий enrollment credential');
            $table->json('allowed_audiences')->comment('API, для которых exchange может выпустить credentials');
            $table->string('selector', 64)->comment('Публичный селектор credential');
            $table->string('token_hash', 128)->comment('Хеш секретной части credential');
            $table->datetime('expires_datetime')->nullable()->comment('Дата и время истечения credential');
            $table->datetime('revoked_datetime')->nullable()->comment('Дата и время отзыва credential');
            $table->datetime('used_datetime')->nullable()->comment('Дата и время атомарного погашения credential');
            $table->datetime('created_datetime')->comment('Дата и время создания credential');
            $table->string('created_ip', 45)->nullable()->comment('Сетевой адрес при создании credential');
            $table->string('created_user_agent', 512)->nullable()->comment('Клиент при создании credential');
            $table->string('used_ip', 45)->nullable()->comment('Сетевой адрес при погашении credential');
            $table->string('used_user_agent', 512)->nullable()->comment('Клиент при погашении credential');
            $table->json('metadata')->nullable()->comment('Дополнительные данные enrollment flow');

            $table->unique(['selector'], 'enrollment_credentials_selector_unique');
            $table->index(
                ['subject_id', 'audience'],
                'enrollment_credentials_subject_id_audience_index',
            );
            $table->index(['expires_datetime'], 'enrollment_credentials_expires_datetime_index');
            $table->index(['used_datetime'], 'enrollment_credentials_used_datetime_index');
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('enrollment_credentials');
    }
};
