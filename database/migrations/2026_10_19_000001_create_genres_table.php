<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Unique ignoring case (SPEC §4). NOCASE folds ASCII; App\Support\NameKey folds the rest.
        Schema::create('genres', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->collation(Schema::getConnection()->getDriverName() === 'sqlite' ? 'NOCASE' : null);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('genres');
    }
};
