<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_registration_otps', function (Blueprint $table) {
            $table->id();

            $table->string('student_id')->unique();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');

            // Store only the hashed OTP, never the plain code.
            $table->string('otp_hash');
            $table->timestamp('otp_expires_at');

            // Limit repeated incorrect verification attempts.
            $table->unsignedTinyInteger('attempts')->default(0);

            $table->timestamps();

            $table->index('otp_expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_registration_otps');
    }
};
