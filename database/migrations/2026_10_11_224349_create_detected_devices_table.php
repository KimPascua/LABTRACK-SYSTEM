```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detected_devices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_id')
                ->nullable()
                ->constrained('students')
                ->nullOnDelete();

            $table->uuid('device_token')->unique();
            $table->string('device_type', 30);
            $table->string('browser', 100)->nullable();
            $table->string('operating_system', 100)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('last_seen')->nullable();

            $table->timestamps();

            $table->index(['student_id', 'status']);
            $table->index('last_seen');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detected_devices');
    }
};