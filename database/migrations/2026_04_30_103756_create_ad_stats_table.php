<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_campaign_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->integer('impressions')->default(0);
            $table->integer('clicks')->default(0);
            $table->timestamps();

            $table->unique(['ad_campaign_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_stats');
    }
};
