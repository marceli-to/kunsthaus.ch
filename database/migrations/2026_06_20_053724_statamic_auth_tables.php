<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class StatamicAuthTables extends Migration
{
	/**
	 * Run the migrations.
	 *
	 * Made idempotent: this app's Statamic install already published a
	 * two-factor migration, so the two_factor_* columns and webauthn table
	 * exist before this runs. Guard every change so re-running is safe.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('users', function (Blueprint $table) {
			if (!Schema::hasColumn('users', 'super')) {
				$table->boolean('super')->default(false);
			}
			if (!Schema::hasColumn('users', 'avatar')) {
				$table->string('avatar')->nullable();
			}
			if (!Schema::hasColumn('users', 'preferences')) {
				$table->json('preferences')->nullable();
			}
			if (!Schema::hasColumn('users', 'last_login')) {
				$table->timestamp('last_login')->nullable();
			}
			$table->string('password')->nullable()->change();
		});

		if (!Schema::hasTable('role_user')) {
			Schema::create('role_user', function (Blueprint $table) {
				$table->id('id');
				$table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
				$table->string('role_id');
			});
		}

		if (!Schema::hasTable('group_user')) {
			Schema::create('group_user', function (Blueprint $table) {
				$table->id('id');
				$table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
				$table->string('group_id');
			});
		}

		if (!Schema::hasTable('password_activation_tokens')) {
			Schema::create('password_activation_tokens', function (Blueprint $table) {
				$table->string('email')->index();
				$table->string('token');
				$table->timestamp('created_at')->nullable();
			});
		}
	}

	/**
	 * Reverse the migrations.
	 */
	public function down()
	{
		Schema::table('users', function (Blueprint $table) {
			$table->dropColumn(['super', 'avatar', 'preferences', 'last_login']);
			$table->string('password')->nullable(false)->change();
		});

		Schema::dropIfExists('role_user');
		Schema::dropIfExists('group_user');
		Schema::dropIfExists('password_activation_tokens');
	}
}
