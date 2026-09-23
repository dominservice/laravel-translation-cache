<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create($this->table(), function (Blueprint $table): void {
            $table->id();
            $table->string('locale', 20);
            $table->string('namespace')->default('*');
            $table->string('group');
            $table->text('translation_key');
            $table->longText('value');
            $table->char('identity_hash', 64)->unique('translation_overrides_identity_unique');
            $table->timestamps();

            $table->index('locale', 'translation_overrides_locale_index');
            $table->index(
                ['locale', 'namespace', 'group'],
                'translation_overrides_group_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table());
    }

    private function table(): string
    {
        return (string) config('translation-cache.table', 'translation_overrides');
    }
};
