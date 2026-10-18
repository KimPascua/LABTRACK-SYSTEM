
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::create('student_qr_login_otps', function (Blueprint $table) {
        $table->id();

        $table->foreignId('student_id')
            ->constrained('students')
            ->cascadeOnDelete();

        $table->string('otp_hash');
        $table->timestamp('otp_expires_at');
        $table->unsignedTinyInteger('attempts')->default(0);
        $table->timestamp('last_sent_at')->nullable();

        $table->timestamps();
    });
}

    public function down(): void
    {
        Schema::dropIfExists('student_qr_login_otps');
    }
};
