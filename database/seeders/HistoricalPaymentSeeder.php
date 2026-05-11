<?php

namespace Database\Seeders;

use App\Models\CustomerLedger;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HistoricalPaymentSeeder extends Seeder
{
    private const ADMIN_ID    = 1;
    private const RECEIPT     = 'receipts/dummy-receipt.png';
    private const DATE        = '2026-05-11';

    // bank_accounts.id values confirmed from DB
    private const BANKS = [
        'Saleem'   => 1,
        'Ehsan SB' => 2,
        'Farhan'   => 3,
        'Meezan'   => 4,
        'HBL'      => 5,
        'Adnan'    => 6,
    ];

    /**
     * Payment rows keyed by order ID.
     * bank = null means Misc-Previous Balance (advance, no receipt, no bank account).
     * bank = string key means bank_transfer to that account.
     */
    private function payments(): array
    {
        return [
            // Order 1 — Asma Sadiq
            1  => [['bank' => 'Farhan',   'amount' => 100000]],

            // Order 2 — Simran Kaur
            2  => [['bank' => 'HBL',      'amount' => 100000]],

            // Order 3 — EEK Collections / Sukaina Khoja
            3  => [['bank' => 'Meezan',   'amount' => 100000]],

            // Order 4 — Kohinoor
            4  => [['bank' => 'HBL',      'amount' => 50000]],

            // Order 5 — Arshad Mehmood
            5  => [['bank' => 'HBL',      'amount' => 150182]],

            // Order 6 — Libas by Kiran and Asad
            6  => [['bank' => 'Adnan',    'amount' => 184250]],

            // Order 7 — Shagufta
            7  => [['bank' => 'HBL',      'amount' => 50160]],

            // Order 8 — Assad - Designer Dhaage
            8  => [['bank' => 'Ehsan SB', 'amount' => 350002]],

            // Order 9 — Umer (fully paid)
            9  => [['bank' => 'Ehsan SB', 'amount' => 1297170]],

            // Order 12 — Mohammad Saeed
            12 => [['bank' => 'HBL',      'amount' => 200000]],

            // Order 13 — Be Smart
            13 => [['bank' => 'Adnan',    'amount' => 20000]],

            // Order 15 — Gohar (bank transfer + misc)
            15 => [
                ['bank' => 'Meezan', 'amount' => 100000],
                ['bank' => null,     'amount' => 37740],
            ],

            // Order 16 — Sobia (fully paid)
            16 => [['bank' => 'Ehsan SB', 'amount' => 322910]],

            // Order 17 — Usama Qadeer Rania Boutique
            17 => [['bank' => 'Adnan',    'amount' => 100000]],

            // Order 18 — Bilal Mirza (misc only)
            18 => [['bank' => null,       'amount' => 30000]],

            // Order 19 — Bano Collection
            19 => [['bank' => 'Ehsan SB', 'amount' => 50000]],

            // Order 21 — Zain
            21 => [['bank' => 'Adnan',    'amount' => 450000]],

            // Order 22 — Khaani (fully paid)
            22 => [['bank' => 'Ehsan SB', 'amount' => 415170]],

            // Order 23 — Anas (Ayanas in spreadsheet — misc only)
            23 => [['bank' => null,       'amount' => 26860]],

            // Order 24 — Splash
            24 => [['bank' => 'Saleem',   'amount' => 500000]],

            // Order 25 — Saad Bhai Wijdan (misc only)
            25 => [['bank' => null,       'amount' => 12210]],

            // Order 26 — Iqra Kashif (fully paid)
            26 => [['bank' => 'Meezan',   'amount' => 230650]],

            // Order 27 — AK Fashion Dubai (two separate bank transfers)
            27 => [
                ['bank' => 'HBL',    'amount' => 200000],
                ['bank' => 'Meezan', 'amount' => 29100],
            ],

            // Order 28 — Aasma
            28 => [['bank' => 'Ehsan SB', 'amount' => 100000]],

            // Order 29 — Nawaban
            29 => [['bank' => 'Ehsan SB', 'amount' => 40000]],

            // Order 30 — Saeed Royal Fashion
            30 => [['bank' => 'Adnan',    'amount' => 33700]],

            // Order 31 — Rameesa Fabrics Shamila
            31 => [['bank' => 'Meezan',   'amount' => 1000000]],

            // Order 32 — Baji Jabeen (fully paid)
            32 => [['bank' => 'HBL',      'amount' => 645820]],

            // Order 33 — Qaisara Matloob
            33 => [['bank' => 'Adnan',    'amount' => 150000]],

            // Order 35 — Mohsin Saeed (bank transfer + misc)
            35 => [
                ['bank' => 'HBL', 'amount' => 1000000],
                ['bank' => null,  'amount' => 55380],
            ],

            // Order 36 — Husna Hossain (fully paid)
            36 => [['bank' => 'Meezan',   'amount' => 184520]],

            // Order 37 — Libas e Khaas
            37 => [['bank' => 'Adnan',    'amount' => 100000]],

            // Order 38 — Sana Ullah
            38 => [['bank' => 'HBL',      'amount' => 16060]],

            // Order 39 — Mona Mukaty (misc only)
            39 => [['bank' => null,       'amount' => 136292]],

            // Skipped (no payment received):
            // Order 10 — Talha
            // Order 11 — Ali Traders
            // Order 14 — Al Imran Boutique
            // Order 20 — Adnan Alfaizan Garments
            // Order 34 — Alkarim
        ];
    }

    public function run(): void
    {
        DB::transaction(function () {
            foreach ($this->payments() as $orderId => $rows) {
                $order = Order::findOrFail($orderId);

                // Idempotency — skip if this order already has payments recorded
                if ($order->payments()->exists()) {
                    $this->command->warn("Order #{$order->order_number} ({$order->customer->name}) already has payments — skipping.");
                    continue;
                }

                $totalSeeded = 0;

                foreach ($rows as $row) {
                    $isBankTransfer = $row['bank'] !== null;

                    $payment = Payment::create([
                        'order_id'        => $order->id,
                        'customer_id'     => $order->customer_id,
                        'amount'          => $row['amount'],
                        'payment_type'    => $isBankTransfer ? 'bank_transfer' : 'advance',
                        'bank_account_id' => $isBankTransfer ? self::BANKS[$row['bank']] : null,
                        'payment_date'    => self::DATE,
                        'notes'           => $isBankTransfer ? null : 'Misc-Previous Balance (historical)',
                        'receipt_image'   => $isBankTransfer ? self::RECEIPT : null,
                        'logged_by'       => self::ADMIN_ID,
                    ]);

                    CustomerLedger::create([
                        'customer_id'             => $order->customer_id,
                        'transaction_type'        => 'payment_received',
                        'amount'                  => -$row['amount'],
                        'running_advance_balance' => 0,
                        'reference_type'          => Payment::class,
                        'reference_id'            => $payment->id,
                        'notes'                   => 'Historical payment for Order #' . $order->order_number
                            . ($isBankTransfer ? ' via ' . $row['bank'] : ' (Misc-Previous Balance)'),
                        'created_by'              => self::ADMIN_ID,
                    ]);

                    $totalSeeded += $row['amount'];
                }

                // Update order financials in one shot after all payments for this order
                $newTotalPaid = $order->total_paid + $totalSeeded;
                $order->update([
                    'total_paid'          => $newTotalPaid,
                    'outstanding_balance' => max(0, $order->total_amount - $newTotalPaid),
                    'status'              => 'confirmed',
                ]);

                $this->command->info(
                    "Order #{$order->order_number} ({$order->customer->name})"
                    . " — seeded PKR " . number_format($totalSeeded)
                    . " | balance: PKR " . number_format(max(0, $order->total_amount - $newTotalPaid))
                );
            }
        });

        $this->command->info('');
        $this->command->info('Historical payments seeded successfully.');
    }
}
