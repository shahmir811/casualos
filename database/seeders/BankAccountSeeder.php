<?php

namespace Database\Seeders;

use App\Models\BankAccount;
use Illuminate\Database\Seeder;

class BankAccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = ['Saleem', 'Ehsan SB', 'Farhan', 'Meezan', 'HBL', 'Adnan', 'Osama', 'Akram'];

        foreach ($accounts as $title) {
            BankAccount::firstOrCreate(['title' => $title], ['is_active' => true]);
        }
    }
}
