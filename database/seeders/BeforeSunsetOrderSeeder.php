<?php

namespace Database\Seeders;

use App\Models\Order;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BeforeSunsetOrderSeeder extends Seeder
{
    public function run(): void
    {
        $catalogueId  = 1;
        $designIds    = [1, 2, 3, 4, 5, 6, 7];
        $sellingPrice = 6590.00;
        $discountPrice = 6390.00;

        // Designs mapped by id → [selling_price, discount_price]
        $designs = collect($designIds)->mapWithKeys(fn($id) => [
            $id => ['selling' => $sellingPrice, 'discount' => $discountPrice],
        ]);

        // Customers already exist in DB — map email (lowercase) → customer_id
        $customerMap = DB::table('customers')
            ->get(['id', 'email'])
            ->keyBy(fn($c) => strtolower($c->email));

        // Raw order data from Google Form responses (Before Sunset catalogue)
        // [submitted_name, submitted_city, submitted_email, xs, s, m, l, xl, submitted_at]
        $rows = [
            ['Asma sadiq',                    'Gujranwala',            'ms_asmasadiq@hotmail.com',         0,  0,  1,  1,  1, '2026-05-03 05:38:22'],
            ['Simran Kaur',                   'Rochester Hills',       'simranssuits@gmail.com',           1,  1,  1,  1,  1, '2026-05-03 05:40:25'],
            ['EEK COLLECTIONS/SUKAINA KHOJA', 'Houston',               'Husainiautos@gmail.com',           0,  1,  2,  2,  1, '2026-05-03 08:02:17'],
            ['Kohinoor',                      'Toronto',               'Shahbazgl@hotmail.com',            0,  6,  8,  8,  4, '2026-05-03 15:15:41'],
            ['Arshad Mehmood',                'UK',                    'aayan.com@me.com',                 2,  2,  2,  2,  2, '2026-05-03 15:34:19'],
            ['libas by kiran and asad',       'Melbourne / Karachi',   'kiranf2694@gmail.com',             0,  1,  1,  1,  1, '2026-05-03 15:35:23'],
            ['Shagufta',                      'UK Surrey',             's_nagi@hotmail.de',                0,  0,  1,  1,  1, '2026-05-03 15:37:30'],
            ['Assad - Designer dhaage',       'London',                'humhain@gmail.com',                0,  2,  6,  6,  2, '2026-05-03 15:41:05'],
            ['Umer',                          'Derby',                 'support@umnaa.co.uk',              1,  4, 10,  8,  6, '2026-05-03 15:42:59'],
            ['Talha',                         'Jhelum',                'talha.acca2015@gmail.com',         0,  4,  8,  8,  4, '2026-05-03 16:12:37'],
            ['Ali traders',                   'Sahiwal',               'alitradersansarroad@gmail.com',    0,  3,  4,  3,  1, '2026-05-03 16:12:44'],
            ['MOHAMMAD SAEED',                'BRADFORD',              's4eed7172@gmail.com',              0,  4,  4,  4,  4, '2026-05-03 16:13:51'],
            ['Be smart',                      'Bhimber',               'akmallatifmuqqadam@gmail.com',     1,  1,  3,  2,  1, '2026-05-03 16:14:00'],
            ['Al imran boutique',             'Kharrian',              'ma664383@gmail.com',               0,  6, 10,  6,  3, '2026-05-03 16:14:42'],
            ['Gohar',                         'Jhelum',                'sh.moazam203@gmail.com',           0,  3,  6,  5,  4, '2026-05-03 16:15:44'],
            ['Sobia',                         'Birmingham',            'kiranmirza238@gmail.com',          0,  1,  2,  2,  2, '2026-05-03 16:18:14'],
            ['Usama qadeer Rania boutique',   'Gujrat',                '6885608@gmail.com',                0,  2,  4,  4,  2, '2026-05-03 16:23:41'],
            ['Bilal Mirza',                   'Karachi',               'bm75094@gmail.com',                0,  1,  1,  1,  1, '2026-05-03 16:27:24'],
            ['Bano collection',               'Swat',                  'younaskhan5343@gmail.com',         0,  2,  5,  5,  2, '2026-05-03 16:31:49'],
            ['Adnan Alfaizan garments',       'Karachi',               'alfaizanoffcial1990@gmail.com',    0,  0,  7,  7,  7, '2026-05-03 16:42:54'],
            ['Zain',                          'Karachi',               'zainarif532@gmail.com',            0,  4,  8,  8,  4, '2026-05-03 17:10:01'],
            ['Khaani',                        'Oldham',                'ictdirect@hotmail.co.uk',          0,  3,  3,  3,  0, '2026-05-03 17:16:05'],
            ['Anas',                          'Riyadh',                'leanasmehmood@gmail.com',          0,  0,  0, 12, 12, '2026-05-03 17:50:06'],
            ['Splash',                        'Jhelum',                'splashfashionofficial@gmail.com',  4,  8,  8,  8,  4, '2026-05-03 18:18:39'],
            ['Saad bhai Wijdan',              'Hyderabad',             'saadnaskani@gmail.com',            0,  3,  4,  4,  4, '2026-05-03 18:31:10'],
            ['Iqra kashif',                   'Walsall Birmingham UK', 'kiqra5131@gmail.com',              1,  1,  1,  1,  1, '2026-05-03 18:31:10'],
            ['Ak fashion dubai',              'Dubai',                 'khurramparekh69@gmail.com',        0,  2, 15, 15,  8, '2026-05-03 19:47:38'],
            ['Aasma',                         'Nottingham UK',         'aasma.ramzan@hotmail.com',         0,  2,  2,  2,  2, '2026-05-03 21:06:46'],
            ['Nawaban',                       'Gujrat',                'jawad.nawab117@gmail.com',         0,  3,  3,  2,  2, '2026-05-03 21:30:47'],
            ['Saeed Royal fashion',           'Karachi',               'saeedjameel21@gmail.com',          2,  3,  4,  3,  2, '2026-05-03 21:36:34'],
            ['Rameesa fabrics Shamila UK',    'Burton on Trent',       'sham-a1@hotmail.co.uk',            3,  3,  6,  6,  6, '2026-05-03 21:53:03'],
            ['Baji JABEEN',                   'Liversedge',            'Tass@alkarim.net',                 0,  3,  4,  4,  3, '2026-05-03 22:04:56'],
            ['Qaisara Matloob',               'Calgary Canada',        'mcheemaca@gmail.com',              0,  1,  2,  2,  2, '2026-05-03 22:34:49'],
            ['Alkarim',                       'Karachi',               'bilalalkarim@gmail.com',           0, 30, 30, 30, 15, '2026-05-03 22:35:45'],
            ['Mohsin saeed',                  'Gujranwala',            'Ali.msstudio@gmail.com',           0,  3,  8,  8,  5, '2026-05-03 22:40:06'],
            ['Husna Hossain',                 'Edison New Jersey USA', 'husnah49@gmail.com',               0,  1,  1,  1,  1, '2026-05-04 09:23:34'],
            ['Libas e khass',                 'Dinga',                 'arslanarsa822@gmail.com',          0,  3,  3,  2,  2, '2026-05-04 12:44:04'],
            ['Sanaulla',                      'Karachi',               'zakir@znsg.pk',                    0,  2,  4,  3,  1, '2026-05-04 16:36:06'],
            ['Mona Mukaty',                   'Houston',               'kmukaty@yahoo.com',                0,  0,  1,  1,  1, '2026-05-07 18:53:53'],
        ];

        DB::transaction(function () use ($rows, $catalogueId, $designs, $customerMap) {
            foreach ($rows as $row) {
                [$name, $city, $email, $xs, $s, $m, $l, $xl, $submittedAt] = $row;

                $totalQty   = $xs + $s + $m + $l + $xl;
                $unitPrice  = $totalQty > 24
                    ? $designs->first()['discount']
                    : $designs->first()['selling'];

                $orderTotal = $totalQty * $unitPrice * count($designs);

                $customer    = $customerMap->get(strtolower($email));
                $customerId  = $customer?->id;

                $order = Order::create([
                    'customer_id'       => $customerId,
                    'catalogue_id'      => $catalogueId,
                    'status'            => 'received',
                    'submitted_name'    => $name,
                    'submitted_city'    => $city,
                    'submitted_email'   => $email,
                    'total_amount'      => $orderTotal,
                    'total_paid'        => 0,
                    'outstanding_balance' => $orderTotal,
                    'is_flagged'        => $customerId === null,
                    'submitted_at'      => $submittedAt,
                ]);

                $items = [];
                $now   = now();
                foreach ($designs as $designId => $prices) {
                    $price       = $totalQty > 24 ? $prices['discount'] : $prices['selling'];
                    $designTotal = $totalQty * $price;
                    $items[] = [
                        'order_id'     => $order->id,
                        'design_id'    => $designId,
                        'qty_xs'       => $xs,
                        'qty_s'        => $s,
                        'qty_m'        => $m,
                        'qty_l'        => $l,
                        'qty_xl'       => $xl,
                        'unit_price'   => $price,
                        'total_qty'    => $totalQty,
                        'total_amount' => $designTotal,
                        'created_at'   => $now,
                        'updated_at'   => $now,
                    ];
                }

                DB::table('order_items')->insert($items);
            }
        });
    }
}
