<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $menus = [
            // Main Menus (parent_id = 0)
            [
                'parent_id' => 0,
                'menu_name' => 'Dashboard',
                'menu_order' => 1,
                'status' => 1,
            ],
            [
                'parent_id' => 0,
                'menu_name' => 'Master Data Entry',
                'menu_order' => 2,
                'status' => 1,
            ],
            [
                'parent_id' => 0,
                'menu_name' => 'Entries',
                'menu_order' => 3,
                'status' => 1,
            ],
            [
                'parent_id' => 0,
                'menu_name' => 'Account Opening',
                'menu_order' => 4,
                'status' => 1,
            ],
            [
                'parent_id' => 0,
                'menu_name' => 'Modify',
                'menu_order' => 5,
                'status' => 1,
            ],
            [
                'parent_id' => 0,
                'menu_name' => 'Utility',
                'menu_order' => 6,
                'status' => 1,
            ],
            [
                'parent_id' => 0,
                'menu_name' => 'Account',
                'menu_order' => 7,
                'status' => 1,
            ],
            [
                'parent_id' => 0,
                'menu_name' => 'Report',
                'menu_order' => 8,
                'status' => 1,
            ],

            // Submenus for "Master Data Entry"
            [
                'parent_id' => 2, // Master Data Entry
                'menu_name' => 'Master Data Entry',
                'menu_order' => 1,
                'status' => 1,
            ],
            [
                'parent_id' => 2, // Master Data Entry
                'menu_name' => 'Member Entry',
                'menu_order' => 2,
                'status' => 1,
            ],
            [
                'parent_id' => 2,
                'menu_name' => 'New Meter Issue',
                'menu_order' => 3,
                'status' => 1,
            ],
            [
                'parent_id' => 2,
                'menu_name' => 'Other Income Setup',
                'menu_order' => 4,
                'status' => 1,
            ],
            [
                'parent_id' => 2,
                'menu_name' => 'Rate and Capacity Setup',
                'menu_order' => 5,
                'status' => 1,
            ],
            [
                'parent_id' => 2,
                'menu_name' => 'Discount Fine Setup',
                'menu_order' => 6,
                'status' => 1,
            ],
            [
                'parent_id' => 2,
                'menu_name' => 'Name Transfer Entry',
                'menu_order' => 7,
                'status' => 1,
            ],
            [
                'parent_id' => 2,
                'menu_name' => 'Electrician Setup',
                'menu_order' => 8,
                'status' => 1,
            ],

            // Submenus for "Entries"
            [
                'parent_id' => 3, // Entries
                'menu_name' => 'Fine Post',
                'menu_order' => 1,
                'status' => 1,
            ],
            [
                'parent_id' => 3,
                'menu_name' => 'Meter Deposit',
                'menu_order' => 2,
                'status' => 1,
            ],
            [
                'parent_id' => 3,
                'menu_name' => 'Opening Meter Deposit',
                'menu_order' => 3,
                'status' => 1,
            ],
            [
                'parent_id' => 3,
                'menu_name' => 'Meter Reading',
                'menu_order' => 4,
                'status' => 1,
            ],
            [
                'parent_id' => 3,
                'menu_name' => 'NEA Purchase',
                'menu_order' => 5,
                'status' => 1,
            ],
            [
                'parent_id' => 3,
                'menu_name' => 'NEA Payment',
                'menu_order' => 6,
                'status' => 1,
            ],
            [
                'parent_id' => 3,
                'menu_name' => 'Other Income Receipt',
                'menu_order' => 7,
                'status' => 1,
            ],
            [
                'parent_id' => 3,
                'menu_name' => 'Opening Mahasul Entry',
                'menu_order' => 8,
                'status' => 1,
            ],
            [
                'parent_id' => 3,
                'menu_name' => 'Mahasul Receipt',
                'menu_order' => 9,
                'status' => 1,
            ],
            [
                'parent_id' => 3,
                'menu_name' => 'Meter Deposit Return',
                'menu_order' => 10,
                'status' => 1,
            ],
            [
                'parent_id' => 3,
                'menu_name' => 'Meter Insurance',
                'menu_order' => 11,
                'status' => 1,
            ],
            [
                'parent_id' => 3,
                'menu_name' => 'Advance Payment',
                'menu_order' => 12,
                'status' => 1,
            ],
            [
                'parent_id' => 3,
                'menu_name' => 'Non Member Payment',
                'menu_order' => 13,
                'status' => 1,
            ],
            [
                'parent_id' => 3,
                'menu_name' => 'Blacklist Customer',
                'menu_order' => 14,
                'status' => 1,
            ],

            // Submenus for "Account Opening"
            [
                'parent_id' => 4, // Account Opening
                'menu_name' => 'Opening Share',
                'menu_order' => 1,
                'status' => 1,
            ],
            [
                'parent_id' => 4,
                'menu_name' => 'Share Purchase',
                'menu_order' => 2,
                'status' => 1,
            ],
            [
                'parent_id' => 4,
                'menu_name' => 'Share Return',
                'menu_order' => 3,
                'status' => 1,
            ],
            [
                'parent_id' => 4,
                'menu_name' => 'Deposit',
                'menu_order' => 4,
                'status' => 1,
            ],
            [
                'parent_id' => 4,
                'menu_name' => 'Deposit Withdraw',
                'menu_order' => 5,
                'status' => 1,
            ],

            // Submenus for "Modify"
            [
                'parent_id' => 5, // Modify
                'menu_name' => 'Change Meter',
                'menu_order' => 1,
                'status' => 1,
            ],
            [
                'parent_id' => 5,
                'menu_name' => 'Upgrade Meter',
                'menu_order' => 2,
                'status' => 1,
            ],

            // Submenus for "Utility"
            [
                'parent_id' => 6, // Utility
                'menu_name' => 'Rule Setup',
                'menu_order' => 1,
                'status' => 1,
            ],
            [
                'parent_id' => 6,
                'menu_name' => 'Blacklist Setup',
                'menu_order' => 2,
                'status' => 1,
            ],
            [
                'parent_id' => 6,
                'menu_name' => 'Handicap Setup',
                'menu_order' => 3,
                'status' => 1,
            ],

            // Submenus for "Account"
            [
                'parent_id' => 7, // Account
                'menu_name' => 'Main Group',
                'menu_order' => 1,
                'status' => 1,
            ],
            [
                'parent_id' => 7,
                'menu_name' => 'Sub Group',
                'menu_order' => 2,
                'status' => 1,
            ],
            [
                'parent_id' => 7,
                'menu_name' => 'Account Group',
                'menu_order' => 3,
                'status' => 1,
            ],
            [
                'parent_id' => 7,
                'menu_name' => 'Account Head',
                'menu_order' => 4,
                'status' => 1,
            ],
            [
                'parent_id' => 7,
                'menu_name' => 'Expenses Tracker',
                'menu_order' => 5,
                'status' => 1,
            ],
            [
                'parent_id' => 7,
                'menu_name' => 'Bank Voucher',
                'menu_order' => 6,
                'status' => 1,
            ],
            [
                'parent_id' => 7,
                'menu_name' => 'Journal Voucher',
                'menu_order' => 7,
                'status' => 1,
            ],

            // Submenus for "Report"
            [
                'parent_id' => 8, // Report
                'menu_name' => 'Customer Report',
                'menu_order' => 1,
                'status' => 1,
            ],
            [
                'parent_id' => 8,
                'menu_name' => 'Meter Issue Report',
                'menu_order' => 2,
                'status' => 1,
            ],
            [
                'parent_id' => 8,
                'menu_name' => 'Name Transfer Report',
                'menu_order' => 3,
                'status' => 1,
            ],
            [
                'parent_id' => 8,
                'menu_name' => 'Meter Deposit Report',
                'menu_order' => 4,
                'status' => 1,
            ],
            [
                'parent_id' => 8,
                'menu_name' => 'NEA Report',
                'menu_order' => 5,
                'status' => 1,
            ],
            [
                'parent_id' => 8,
                'menu_name' => 'Voucher Summary',
                'menu_order' => 6,
                'status' => 1,
            ],
            [
                'parent_id' => 8,
                'menu_name' => 'Member Info',
                'menu_order' => 7,
                'status' => 1,
            ],
            [
                'parent_id' => 8,
                'menu_name' => 'Income Head',
                'menu_order' => 8,
                'status' => 1,
            ],
            [
                'parent_id' => 8,
                'menu_name' => 'Bill Wise Report',
                'menu_order' => 9,
                'status' => 1,
            ],
            [
                'parent_id' => 8,
                'menu_name' => 'NEA Leakage',
                'menu_order' => 10,
                'status' => 1,
            ],
            [
                'parent_id' => 8,
                'menu_name' => 'Absent Ledger',
                'menu_order' => 11,
                'status' => 1,
            ],
            [
                'parent_id' => 8,
                'menu_name' => 'Customer Ledger',
                'menu_order' => 12,
                'status' => 1,
            ],
            [
                'parent_id' => 8,
                'menu_name' => 'All Voucher',
                'menu_order' => 13,
                'status' => 1,
            ],
        ];

        // Insert menus
        DB::table('menus')->insert($menus);
    }
}