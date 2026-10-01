<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Existing users need an explicit ownership mapping, never a guessed tenant.
        if (DB::table('users')->exists()) {
            throw new RuntimeException('Existing users require an explicit organization/clinic backfill before this migration. No data was changed.');
        }

        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('clinics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();
            $table->unique(['organization_id', 'slug']);
            $table->unique(['organization_id', 'id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('clinic_id');
            // A clinic from a different organization must be rejected even by SQL.
            $table->foreign(['organization_id', 'clinic_id'])
                ->references(['organization_id', 'id'])->on('clinics')->restrictOnDelete();
            $table->index(['organization_id', 'clinic_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['organization_id', 'clinic_id']);
            $table->dropForeign(['organization_id']);
            $table->dropIndex(['organization_id', 'clinic_id']);
            $table->dropColumn(['organization_id', 'clinic_id']);
        });
        Schema::dropIfExists('clinics');
        Schema::dropIfExists('organizations');
    }
};
