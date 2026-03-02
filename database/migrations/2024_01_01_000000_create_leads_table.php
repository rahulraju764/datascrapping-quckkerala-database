<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('view_number')->unique();
            $table->string('address_id')->nullable();
            $table->string('title');
            $table->string('business_name')->nullable();
            $table->string('mobile')->nullable();
            $table->string('mobile_formatted')->nullable();
            $table->boolean('whatsapp_enabled')->default(false);
            $table->string('status')->default('pending'); // pending, fetched, failed
            $table->text('raw_response')->nullable();
            $table->timestamps();
        });

        Schema::create('scrape_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('url');
            $table->integer('total_found')->default(0);
            $table->integer('total_processed')->default(0);
            $table->integer('total_failed')->default(0);
            $table->string('status')->default('running'); // running, completed, failed
            $table->text('log')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
        Schema::dropIfExists('scrape_sessions');
    }
};
