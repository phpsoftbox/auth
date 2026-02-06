<?php

declare(strict_types=1);

use PhpSoftBox\Database\Migrations\AbstractMigration;
use PhpSoftBox\Database\SchemaBuilder\TableBlueprint;

return new class () extends AbstractMigration {
    public function up(): void
    {
        $this->schema()->addColumn('user_tokens', static function (TableBlueprint $table): void {
            $table->string('audience', 64)
                ->nullable()
                ->comment('API или другой получатель, для которого выдан credential');
        });

        $this->schema()->alterTable('user_tokens', static function (TableBlueprint $table): void {
            $table->dropIndex('user_tokens_user_id_token_type_index');
        });

        $this->schema()->alterTable('user_tokens', static function (TableBlueprint $table): void {
            $table->index(
                ['user_id', 'token_type', 'audience'],
                'user_tokens_user_id_token_type_audience_index',
            );
        });
    }

    public function down(): void
    {
        $this->schema()->alterTable('user_tokens', static function (TableBlueprint $table): void {
            $table->dropIndex('user_tokens_user_id_token_type_audience_index');
        });

        $this->schema()->alterTable('user_tokens', static function (TableBlueprint $table): void {
            $table->index(['user_id', 'token_type'], 'user_tokens_user_id_token_type_index');
        });

        $this->schema()->alterTable('user_tokens', static function (TableBlueprint $table): void {
            $table->dropColumn('audience');
        });
    }
};
