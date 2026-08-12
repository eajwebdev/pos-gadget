<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')
            ->where('key', 'pos.default_payment')
            ->where('value', 'others')
            ->update(['value' => 'cash']);

        DB::table('system_settings')
            ->where('key', 'pos.default_payment')
            ->update([
                'options' => json_encode(['cash', 'gcash', 'card', 'credit', 'mixed', 'installment']),
            ]);
    }

    public function down(): void
    {
        DB::table('system_settings')
            ->where('key', 'pos.default_payment')
            ->update([
                'options' => json_encode(['cash', 'gcash', 'card', 'others', 'credit', 'mixed', 'installment']),
            ]);
    }
};
