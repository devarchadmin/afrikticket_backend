<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->dateTime('date');
            $table->string('location');
            $table->integer('max_tickets');
            $table->decimal('price', 10, 2);
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->decimal('duration')->default(2);
            $table->enum('status', ['pending','active','rejected', 'cancelled'])->default('pending');
            $table->enum('category', ['festival', 'concert', 'sport', 'art', 'education', 'technology', 'business', 'other'])->default('other');
            $table->text('rejection_reason')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
