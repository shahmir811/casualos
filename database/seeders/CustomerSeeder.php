<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@casualite.com')->firstOrFail();

        $customers = [
            // ── Pakistan ──────────────────────────────────────────────────────
            [
                'name'           => 'Asma Sadiq',
                'email'          => 'ms_asmasadiq@hotmail.com',
                'city'           => 'Gujranwala',
                'contact_number' => '+92 301 8742563',
            ],
            [
                'name'           => 'Libas by Kiran and Asad',
                'email'          => 'kiranf2694@gmail.com',
                'city'           => 'Melbourne / Karachi',
                'contact_number' => '+92 312 9473618',
            ],
            [
                'name'           => 'Talha',
                'email'          => 'talha.acca2015@gmail.com',
                'city'           => 'Jhelum',
                'contact_number' => '+92 345 6291847',
            ],
            [
                'name'           => 'Ali Traders',
                'email'          => 'alitradersansarroad@gmail.com',
                'city'           => 'Sahiwal',
                'contact_number' => '+92 333 4817263',
            ],
            [
                'name'           => 'Be Smart',
                'email'          => 'akmallatifmuqqadam@gmail.com',
                'city'           => 'Bhimber',
                'contact_number' => '+92 300 7364921',
            ],
            [
                'name'           => 'Al Imran Boutique',
                'email'          => 'ma664383@gmail.com',
                'city'           => 'Kharrian',
                'contact_number' => '+92 321 5839274',
            ],
            [
                'name'           => 'Gohar',
                'email'          => 'sh.moazam203@gmail.com',
                'city'           => 'Jhelum',
                'contact_number' => '+92 346 2917584',
            ],
            [
                'name'           => 'Usama Qadeer Rania Boutique',
                'email'          => '6885608@gmail.com',
                'city'           => 'Gujrat',
                'contact_number' => '+92 314 6283749',
            ],
            [
                'name'           => 'Bilal Mirza',
                'email'          => 'bm75094@gmail.com',
                'city'           => 'Karachi',
                'contact_number' => '+92 322 8471635',
            ],
            [
                'name'           => 'Bano Collection',
                'email'          => 'younaskhan5343@gmail.com',
                'city'           => 'Swat',
                'contact_number' => '+92 311 7392846',
            ],
            [
                'name'           => 'Adnan Alfaizan Garments',
                'email'          => 'alfaizanoffcial1990@gmail.com',
                'city'           => 'Karachi',
                'contact_number' => '+92 303 5847291',
            ],
            [
                'name'           => 'Zain',
                'email'          => 'zainarif532@gmail.com',
                'city'           => 'Karachi',
                'contact_number' => '+92 319 4728361',
            ],
            [
                'name'           => 'Splash',
                'email'          => 'splashfashionofficial@gmail.com',
                'city'           => 'Jhelum',
                'contact_number' => '+92 333 1827465',
            ],
            [
                'name'           => 'Saad Bhai Wijdan',
                'email'          => 'saadnaskani@gmail.com',
                'city'           => 'Hyderabad',
                'contact_number' => '+92 300 4917382',
            ],
            [
                'name'           => 'Nawaban',
                'email'          => 'jawad.nawab117@gmail.com',
                'city'           => 'Gujrat',
                'contact_number' => '+92 344 8263917',
            ],
            [
                'name'           => 'Saeed Royal Fashion',
                'email'          => 'saeedjameel21@gmail.com',
                'city'           => 'Karachi',
                'contact_number' => '+92 321 7493618',
            ],
            [
                'name'           => 'Alkarim',
                'email'          => 'bilalalkarim@gmail.com',
                'city'           => 'Karachi',
                'contact_number' => '+92 315 2847193',
            ],
            [
                'name'           => 'Mohsin Saeed',
                'email'          => 'ali.msstudio@gmail.com',
                'city'           => 'Gujranwala',
                'contact_number' => '+92 302 6183749',
            ],
            [
                'name'           => 'Libas e Khass',
                'email'          => 'arslanarsa822@gmail.com',
                'city'           => 'Dinga',
                'contact_number' => '+92 348 4719263',
            ],
            [
                'name'           => 'Sanaulla',
                'email'          => 'zakir@znsg.pk',
                'city'           => 'Karachi',
                'contact_number' => '+92 300 8372916',
            ],

            // ── United Kingdom ────────────────────────────────────────────────
            [
                'name'           => 'Arshad Mehmood',
                'email'          => 'aayan.com@me.com',
                'city'           => 'UK',
                'contact_number' => '+44 7712 384917',
            ],
            [
                'name'           => 'Shagufta',
                'email'          => 's_nagi@hotmail.de',
                'city'           => 'Surrey',
                'contact_number' => '+44 7834 291638',
            ],
            [
                'name'           => 'Assad - Designer Dhaage',
                'email'          => 'humhain@gmail.com',
                'city'           => 'London',
                'contact_number' => '+44 7928 473619',
            ],
            [
                'name'           => 'Umer',
                'email'          => 'support@umnaa.co.uk',
                'city'           => 'Derby',
                'contact_number' => '+44 7654 193847',
            ],
            [
                'name'           => 'Mohammad Saeed',
                'email'          => 's4eed7172@gmail.com',
                'city'           => 'Bradford',
                'contact_number' => '+44 7483 916274',
            ],
            [
                'name'           => 'Sobia',
                'email'          => 'kiranmirza238@gmail.com',
                'city'           => 'Birmingham',
                'contact_number' => '+44 7391 827463',
            ],
            [
                'name'           => 'Khaani',
                'email'          => 'ictdirect@hotmail.co.uk',
                'city'           => 'Oldham',
                'contact_number' => '+44 7826 394817',
            ],
            [
                'name'           => 'Iqra Kashif',
                'email'          => 'kiqra5131@gmail.com',
                'city'           => 'Walsall, Birmingham',
                'contact_number' => '+44 7917 284639',
            ],
            [
                'name'           => 'Aasma',
                'email'          => 'aasma.ramzan@hotmail.com',
                'city'           => 'Nottingham',
                'contact_number' => '+44 7648 391728',
            ],
            [
                'name'           => 'Rameesa Fabrics Shamila',
                'email'          => 'sham-a1@hotmail.co.uk',
                'city'           => 'Burton on Trent',
                'contact_number' => '+44 7723 849163',
            ],
            [
                'name'           => 'Baji Jabeen',
                'email'          => 'tass@alkarim.net',
                'city'           => 'Liversedge',
                'contact_number' => '+44 7591 384726',
            ],

            // ── United States ─────────────────────────────────────────────────
            [
                'name'           => 'Simran Kaur',
                'email'          => 'simranssuits@gmail.com',
                'city'           => 'Rochester Hills',
                'contact_number' => '+1 248 739 4821',
            ],
            [
                'name'           => 'EEK Collections / Sukaina Khoja',
                'email'          => 'husainiautos@gmail.com',
                'city'           => 'Houston',
                'contact_number' => '+1 713 582 9347',
            ],
            [
                'name'           => 'Husna Hossain',
                'email'          => 'husnah49@gmail.com',
                'city'           => 'Edison, NJ',
                'contact_number' => '+1 732 849 3716',
            ],
            [
                'name'           => 'Mona Mukaty',
                'email'          => 'kmukaty@yahoo.com',
                'city'           => 'Houston',
                'contact_number' => '+1 281 473 9182',
            ],

            // ── Canada ────────────────────────────────────────────────────────
            [
                'name'           => 'Kohinoor',
                'email'          => 'shahbazgl@hotmail.com',
                'city'           => 'Toronto',
                'contact_number' => '+1 416 839 2741',
            ],
            [
                'name'           => 'Qaisara Matloob',
                'email'          => 'mcheemaca@gmail.com',
                'city'           => 'Calgary',
                'contact_number' => '+1 403 729 4836',
            ],

            // ── Saudi Arabia ──────────────────────────────────────────────────
            [
                'name'           => 'Anas',
                'email'          => 'leanasmehmood@gmail.com',
                'city'           => 'Riyadh',
                'contact_number' => '+966 54 382 9174',
            ],

            // ── UAE ───────────────────────────────────────────────────────────
            [
                'name'           => 'AK Fashion Dubai',
                'email'          => 'khurramparekh69@gmail.com',
                'city'           => 'Dubai',
                'contact_number' => '+971 55 847 3629',
            ],
        ];

        foreach ($customers as $data) {
            Customer::firstOrCreate(
                ['email' => strtolower($data['email'])],
                [
                    'name'                  => $data['name'],
                    'city'                  => $data['city'],
                    'contact_number'        => $data['contact_number'],
                    'advance_credit_balance' => 0.00,
                    'created_by'            => $admin->id,
                ]
            );
        }

        $this->command->info('CustomerSeeder: ' . count($customers) . ' customers seeded.');
    }
}
