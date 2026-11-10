<?php

use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Pengujian otomatis membuat datanya sendiri lewat factory.
        if (app()->runningUnitTests()) {
            return;
        }

        (new DatabaseSeeder())->run();
    }

    public function down(): void
    {
        // Akun contoh tidak dihapus saat rollback.
    }
};