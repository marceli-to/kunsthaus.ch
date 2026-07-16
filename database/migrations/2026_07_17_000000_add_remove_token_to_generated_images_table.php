<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Opaque per-image token for the remove/unsubscribe link. Replaces the signed
 * `?expires=&signature=` query URL in the publish mail — that pattern trips
 * Google Safe Browsing's phishing heuristic. Backfills existing rows so links
 * for already-published images can be generated.
 */
return new class extends Migration
{
	public function up(): void
	{
		Schema::table('generated_images', function (Blueprint $table) {
			$table->string('remove_token', 64)->nullable()->unique()->after('uuid');
		});

		DB::table('generated_images')->whereNull('remove_token')->pluck('id')
			->each(fn ($id) => DB::table('generated_images')
				->where('id', $id)
				->update(['remove_token' => Str::random(48)]));
	}

	public function down(): void
	{
		Schema::table('generated_images', function (Blueprint $table) {
			$table->dropColumn('remove_token');
		});
	}
};
