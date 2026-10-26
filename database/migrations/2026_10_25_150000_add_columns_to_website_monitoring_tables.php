<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('website_rules')) {
            Schema::table('website_rules', function (Blueprint $table) {
                if (!Schema::hasColumn('website_rules', 'website_domain')) {
                    $table->string('website_domain', 253)->nullable();
                }
                if (!Schema::hasColumn('website_rules', 'scope')) {
                    $table->string('scope', 30)->default('All');
                }
                if (!Schema::hasColumn('website_rules', 'lab')) {
                    $table->string('lab', 50)->nullable();
                }
                if (!Schema::hasColumn('website_rules', 'restriction')) {
                    $table->string('restriction', 30)->default('Blocked');
                }
                if (!Schema::hasColumn('website_rules', 'status')) {
                    $table->string('status', 30)->default('Active');
                }
                if (!Schema::hasColumn('website_rules', 'reason')) {
                    $table->text('reason')->nullable();
                }
            });
        }

        if (Schema::hasTable('website_activities')) {
            Schema::table('website_activities', function (Blueprint $table) {
                if (!Schema::hasColumn('website_activities', 'pc_name')) {
                    $table->string('pc_name', 50)->nullable();
                }
                if (!Schema::hasColumn('website_activities', 'student_id')) {
                    $table->string('student_id', 50)->nullable();
                }
                if (!Schema::hasColumn('website_activities', 'student_name')) {
                    $table->string('student_name', 100)->nullable();
                }
                if (!Schema::hasColumn('website_activities', 'lab')) {
                    $table->string('lab', 50)->nullable();
                }
                if (!Schema::hasColumn('website_activities', 'website_domain')) {
                    $table->string('website_domain', 253)->nullable();
                }
                if (!Schema::hasColumn('website_activities', 'action')) {
                    $table->string('action', 30)->default('Visit');
                }
                if (!Schema::hasColumn('website_activities', 'status')) {
                    $table->string('status', 30)->default('Allowed');
                }
                if (!Schema::hasColumn('website_activities', 'reason')) {
                    $table->text('reason')->nullable();
                }
                if (!Schema::hasColumn('website_activities', 'visited_at')) {
                    $table->dateTime('visited_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        // Intentionally retain these columns on rollback to avoid deleting monitoring data.
    }
};
