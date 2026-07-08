<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	/**
	 * A submitted JAtelier campaign image: the visitor's confirmed composite plus
	 * the source portrait (kept for moderation), FADP consent timestamp and the
	 * moderation lifecycle (submitted → published | rejected). Both image paths
	 * are relative to the PRIVATE "local" disk; deleting a record wipes them.
	 */
	public function up(): void
	{
		Schema::create('generated_images', function (Blueprint $table) {
			$table->id();
			$table->uuid('uuid')->unique();
			$table->string('first_name');
			$table->string('last_name');
			$table->string('ja_style');
			$table->boolean('background_removed')->default(false);
			$table->string('source_image_path');
			$table->string('final_path');
			$table->string('status')->default('submitted')->index();
			$table->string('user_email');
			$table->timestamp('consent_at');
			$table->timestamp('published_at')->nullable();
			$table->timestamp('notified_at')->nullable();
			$table->timestamps();
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('generated_images');
	}
};
