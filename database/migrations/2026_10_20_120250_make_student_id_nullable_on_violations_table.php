
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('violations', function (Blueprint $table) {
            $table->string('student_id', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Keep student_id nullable so unassigned-computer
        // violations do not become invalid.
    }
};
