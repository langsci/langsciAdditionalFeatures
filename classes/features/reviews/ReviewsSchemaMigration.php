<?php

namespace APP\plugins\generic\langsciAdditionalFeatures\classes\features\reviews;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\install\DowngradeNotSupportedException;
use RuntimeException;

class ReviewsSchemaMigration extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('langsci_review_links')) {
            Schema::create('langsci_review_links', function (Blueprint $table) {
                $table->bigIncrements('review_id');
                $table->bigInteger('submission_id');
                $table->bigInteger('publication_id');
                $table->string('reviewer', 255)->nullable();
                $table->text('money_quote')->nullable();
                $table->string('date', 255)->nullable();
                $table->string('link', 255);
                $table->string('link_name', 255);
            });
        }

        $columns = collect(Schema::getColumns('langsci_review_links'))->keyBy('name');
        $required = ['review_id', 'submission_id', 'publication_id', 'reviewer', 'money_quote', 'date', 'link', 'link_name'];
        $missing = array_values(array_filter($required, fn ($name) => !$columns->has($name)));
        if ($missing) {
            throw new RuntimeException('Incompatible langsci_review_links table: missing columns ' . implode(', ', $missing));
        }

        foreach (['review_id', 'submission_id', 'publication_id'] as $name) {
            if (!preg_match('/int/i', $columns[$name]['type_name'])) {
                throw new RuntimeException("Incompatible langsci_review_links table: {$name} must be an integer");
            }
            if (Schema::getConnection()->getDriverName() !== 'sqlite' && !preg_match('/bigint|int8/i', $columns[$name]['type_name'])) {
                throw new RuntimeException("Incompatible langsci_review_links table: {$name} must support 64-bit IDs");
            }
        }
        foreach (['reviewer', 'money_quote', 'date', 'link', 'link_name'] as $name) {
            if (!preg_match('/char|text|clob/i', $columns[$name]['type_name'])) {
                throw new RuntimeException("Incompatible langsci_review_links table: {$name} must be a text column");
            }
            if ($name === 'money_quote' && !preg_match('/text|clob/i', $columns[$name]['type_name'])) {
                throw new RuntimeException('Incompatible langsci_review_links table: money_quote must support long text');
            }
            if ($name !== 'money_quote' && preg_match('/char(?:acter)?(?: varying)?\((\d+)\)/i', $columns[$name]['type'], $matches) && (int) $matches[1] < 255) {
                throw new RuntimeException("Incompatible langsci_review_links table: {$name} must allow at least 255 characters");
            }
        }
        foreach ($columns as $column) {
            if (!in_array($column['name'], $required, true) && !$column['nullable'] && $column['default'] === null && !$column['auto_increment'] && empty($column['generation'])) {
                throw new RuntimeException("Incompatible langsci_review_links table: extra required column {$column['name']}");
            }
        }

        $primary = collect(Schema::getIndexes('langsci_review_links'))->first(fn ($index) => $index['primary']);
        if (!$primary || $primary['columns'] !== ['review_id'] || !$columns['review_id']['auto_increment']) {
            throw new RuntimeException('Incompatible langsci_review_links table: review_id must be an auto-incrementing primary key');
        }
    }

    public function down(): void
    {
        throw new DowngradeNotSupportedException('Reviews storage is retained and cannot be rolled back automatically.');
    }
}
