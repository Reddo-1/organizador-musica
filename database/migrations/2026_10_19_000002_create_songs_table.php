<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('songs', function (Blueprint $table) {
            $table->id();
            $table->text('path');
            $table->text('path_key')->unique();
            $table->string('title');
            $table->string('artist')->nullable();
            $table->foreignId('genre_id')->nullable()->constrained()->nullOnDelete();
            $table->smallInteger('song_year')->nullable();
            $table->smallInteger('discovered_year')->nullable();
            $table->unsignedTinyInteger('energy')->nullable();
            $table->string('set_role')->nullable();
            $table->decimal('bpm', 5, 2)->nullable();
            $table->string('musical_key')->nullable();
            $table->decimal('duration', 8, 2)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('missing')->default(false)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('songs');
    }
};
