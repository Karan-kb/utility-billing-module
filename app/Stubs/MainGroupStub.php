<?php

namespace App\Stubs;

use App\Models\MainGroup;
use App\Models\SubGroup;
use App\Models\AccountGroup;
use App\Models\AccountHead;

class MainGroupStub
{
    public static function createMainGroups()
    {
        $chart = [

            // ---------------------------------------------------
            // ASSETS
            // ---------------------------------------------------
            'Assets' => [

                'Fixed Asset' => [
                    ['group' => 'Land', 'heads' => []],
                    ['group' => 'Building', 'heads' => []],
                    ['group' => 'Plant and Machinery', 'heads' => []],
                    ['group' => 'Furniture and Fixtures', 'heads' => []],
                    ['group' => 'Vehicles', 'heads' => []],
                ],

                'Investment' => [
                    ['group' => 'Long-Term Investments', 'heads' => []],
                ],

                'Stock/Inventory' => [
                    ['group' => 'Inventory / Stock', 'heads' => []],
                ],

                'Current Asset' => [
                    ['group' => 'Accounts Receivable (Debtors)', 'heads' => []],

                    [
                        'group' => 'Cash Accounts',
                        'heads' => [
                            ['name' => 'Cash in Hand', 'name_np' => 'नगद'],
                        ]
                    ],

                    [
                        'group' => 'Bank Accounts',
                        'heads' => [
                            ['name' => 'Global Bank', 'name_np' => 'ग्लोबल बैंक'],
                        ]
                    ],

                    ['group' => 'Prepaid Expenses', 'heads' => []],
                    ['group' => 'Short-term Investments', 'heads' => []],
                ]
            ],

            // ---------------------------------------------------
            // LIABILITIES
            // ---------------------------------------------------
            'Liabilities' => [

                'Equity' => [
                    ['group' => 'Capital / Share Capital', 'heads' => []],
                    ['group' => 'Retained Earnings', 'heads' => []],
                    ['group' => 'Reserves and Surplus', 'heads' => []],
                ],

                'Non-Current Liability' => [
                    ['group' => 'Long-Term Loans', 'heads' => []],
                    ['group' => 'Lease Liabilities', 'heads' => []],
                ],

                'Current Liability' => [
                    ['group' => 'Accounts Payable (Creditors)', 'heads' => []],
                    ['group' => 'Short-Term Loans', 'heads' => []],
                    ['group' => 'Accrued Expenses', 'heads' => []],
                    ['group' => 'Provisions', 'heads' => []],
                    ['group' => 'Unearned Revenue', 'heads' => []],

                    [
                        'group' => 'Payable',
                        'heads' => [
                            ['name' => 'TDS- Salary', 'name_np' => 'टिडिएस- तलब'],
                            ['name' => 'TDS-Audit Fee', 'name_np' => 'टिडिएस- अडिट शुल्क'],
                            ['name' => 'TDS-House Rent', 'name_np' => 'टिडिएस- घर भाडा'],
                        ]
                    ],

                    [
                        'group' => 'VAT Account',
                        'heads' => [
                            ['name' => 'VAT', 'name_np' => 'भ्याट'],
                        ]
                    ],

                    [
                        'group' => 'Share Account',
                        'heads' => [
                            ['name' => 'Share Account', 'name_np' => 'सेयर खाता'],
                        ]

                    ],
                    [
                        'group' => 'Meter Deposit',
                        'heads' => [
                            ['name' => 'Meter Deposit', 'name_np' => 'मिटर धरौटी'],
                        ]

                    ],
                    [
                        'group' => 'Advance payment Recevied',
                        'heads' => [
                            ['name' => 'Advance payment Recevied', 'name_np' => 'अग्रिम भुक्तानी प्राप्त'],
                        ]
                    ],

                    [
                        'group' => 'Duties & Taxes',
                        'heads' => [
                            ['name' => 'Income Tax Payable', 'name_np' => 'आयकर देय'],
                        ]
                    ],
                ],
            ],

            // ---------------------------------------------------
            // INCOME
            // ---------------------------------------------------
            'Income' => [

                'Sales Income' => [
                    ['group' => 'Sales', 'heads' => []],
                    ['group' => 'Sales Return', 'heads' => []],
                    ['group' => 'Service Revenue', 'heads' => []],
                    ['group' => 'Commission Income', 'heads' => []],
                    ['group' => 'Interest Income', 'heads' => []],
                    ['group' => 'Rental Income', 'heads' => []],

                    [
                        'group' => 'Income From Electricity Business',
                        'heads' => [
                            ['name' => 'Income From Mahasul Sales', 'name_np' => 'महसुल बिक्रीबाट आय'],
                            ['name' => 'Income From Non-member Mahasul Sales', 'name_np' => 'गैर–सदस्य महसुल बिक्रीबाट आय'],
                            ['name' => 'Income Fine Charge', 'name_np' => 'जरिवाना शुल्कबाट आय'],
                            ['name' => 'Income From Demand Charge', 'name_np' => 'डिमान्ड शुल्कबाट आय'],
                            ['name' => 'Income From Other Charge', 'name_np' => 'अन्य शुल्कबाट आय'],
                            ['name' => 'Income From Subsidy Charge', 'name_np' => 'अनुदान शुल्कबाट आय'],
                            ['name' => 'Income Services Charge', 'name_np' => 'सेवा शुल्कबाट आय'],
                            ['name' => 'Sewa Sulka', 'name_np' => 'सेवा शुल्क रकम'],
                            ['name' => 'Meter insurance', 'name_np' => 'मिटर बीमा'],
                            ['name' => 'Income From Blacklist', 'name_np' => 'कालोसूचीबाट प्राप्त आम्दानी'],

                        ]
                    ],

                    ['group' => 'Other Operating Income', 'heads' => []],
                ],

                'other income' => [
                    ['group' => 'discount income', 'heads' => []],
                    ['group' => 'Scheme Discount Income', 'heads' => []],
                    ['group' => 'Deposit Return Charge', 'heads' => []],
                    ['group' => 'Share Return Charge', 'heads' => []],
                ],

                'Other Income' => [
                    ['group' => 'Miscellaneous Income', 'heads' => []],
                ],
            ],

            // ---------------------------------------------------
            // EXPENSES
            // ---------------------------------------------------
            'Expenses' => [

                'direct expenses' => [
                    ['group' => 'Purchase', 'heads' => []],
                    ['group' => 'Purchase Return', 'heads' => []],
                    [
                        'group' => 'Electricity Purchase-NEA',
                        'heads' => [
                            ['name' => 'Electricity Purchase-NEA', 'name_np' => 'विद्युत खरिद – ने.वि.प्रा.'],
                        ]
                    ],
                    ['group' => 'Freight Inward', 'heads' => []],
                    ['group' => 'Carriage Inward', 'heads' => []],
                    ['group' => 'wages', 'heads' => []],
                    ['group' => 'Excise Duty Expenses', 'heads' => []],
                    ['group' => 'health insurance Expenses', 'heads' => []],
                    ['group' => 'fright charge', 'heads' => []],
                    [
                        'group' => 'Discount Expenses',
                        'heads' => [
                            ['name' => 'Discount Expenses', 'name_np' => 'छुट खर्च'],
                        ]
                    ],
                    [
                        'group' => 'Rebate Discount',
                        'heads' => [
                            ['name' => 'Rebate Discount', 'name_np' => 'रिबेट छुट'],
                        ]
                    ],
                    [
                        'group' => 'Scheme Discount',
                        'heads' => [
                            // ['name' => 'Disabble Discount', 'name_np' => 'छुट निष्क्रिय गर्नुहोस्']

                        ]
                    ],
                ],

                'Indirect Expense' => [
                    [
                        'group' => 'Direct Expenses',
                        'heads' => [
                            ['name' => 'Fuel for Production', 'name_np' => 'उत्पादनको इन्धन'],
                            ['name' => 'Packing Charges (Direct)', 'name_np' => 'प्याकिंग शुल्क (प्रत्यक्ष)'],
                            ['name' => 'Factory Rent', 'name_np' => 'कारखाना भाडा'],
                        ]
                    ],
                    [
                        'group' => 'Administrative Expenses',
                        'heads' => [
                            ['name' => 'Office Rent', 'name_np' => 'कार्यालय भाडा'],
                            ['name' => 'Office Salaries', 'name_np' => 'कार्यालय तलब'],
                            ['name' => 'Stationery & Printing', 'name_np' => 'स्टेसनरी र मुद्रण'],
                            ['name' => 'Telephone & Internet', 'name_np' => 'टेलिफोन र इन्टरनेट'],
                            ['name' => 'Electricity (Office)', 'name_np' => 'विद्युत (कार्यालय)'],
                            ['name' => 'Legal & Professional Fees', 'name_np' => 'कानुनी र व्यावसायिक शुल्क'],
                            ['name' => 'Software Subscription', 'name_np' => 'सफ्टवेयर सदस्यता'],
                            ['name' => 'Audit Fees', 'name_np' => 'अडिट शुल्क'],
                            ['name' => 'Postage & Courier', 'name_np' => 'हुलाक र कुरियर'],
                        ]
                    ],
                    [
                        'group' => 'Selling & Distribution Expenses',
                        'heads' => [
                            ['name' => 'Advertisement & Promotion', 'name_np' => 'विज्ञापन र प्रवर्द्धन'],
                            ['name' => 'Sales Commission', 'name_np' => 'बिक्री कमिशन'],
                            ['name' => 'Packing Charges (Selling)', 'name_np' => 'प्याकिंग शुल्क (बिक्री)'],
                            ['name' => 'Carriage Outward', 'name_np' => 'सामान ढुवानी (बाहिर)'],
                            ['name' => 'Delivery Charges', 'name_np' => 'डेलिभरी शुल्क'],
                            ['name' => 'Trade Fair Expenses', 'name_np' => 'व्यापार मेला खर्च'],
                            ['name' => 'Discount Allowed', 'name_np' => 'छूट अनुमत'],
                        ]
                    ],
                    [
                        'group' => 'Financial Expenses',
                        'heads' => [
                            ['name' => 'Bank Charges', 'name_np' => 'बैंक शुल्क'],
                            ['name' => 'Interest on Loan', 'name_np' => 'ऋणमा ब्याज'],
                            ['name' => 'Loan Processing Fees', 'name_np' => 'ऋण प्रशोधन शुल्क'],
                            ['name' => 'Interest on Overdraft', 'name_np' => 'ओभरड्राफ्टमा ब्याज'],
                            ['name' => 'Cheque Bounce Charges', 'name_np' => 'चेक बाउन्स शुल्क'],
                        ]
                    ],
                    [
                        'group' => 'Depreciation & Amortization',
                        'heads' => [
                            ['name' => 'Depreciation on Machinery', 'name_np' => 'मेसिनरीमा मूल्यह्रास'],
                            ['name' => 'Depreciation on Furniture', 'name_np' => 'फर्निचरमा मूल्यह्रास'],
                            ['name' => 'Amortization of Intangible Assets', 'name_np' => 'अमूर्त सम्पत्तिको अमोर्टाइजेशन'],
                        ]
                    ],
                    [
                        'group' => 'Miscellaneous Expenses',
                        'heads' => [
                            ['name' => 'General Expenses', 'name_np' => 'सामान्य खर्च'],
                            ['name' => 'Entertainment Expenses', 'name_np' => 'मनोरञ्जन खर्च'],
                            ['name' => 'Subscription Fees', 'name_np' => 'सदस्यता शुल्क'],
                            ['name' => 'Membership Fees', 'name_np' => 'सदस्य शुल्क'],
                            ['name' => 'Gifts & Donations', 'name_np' => 'उपहार र दान'],
                        ]
                    ],
                    [
                        'group' => 'Employee Benefit Expenses',
                        'heads' => [
                            ['name' => 'Staff Welfare', 'name_np' => 'कर्मचारी कल्याण'],
                        ]
                    ],

                ]
            ],
        ];

        // ---------------------------------------------------
        // GENERATE DATABASE ENTRIES
        // ---------------------------------------------------
        foreach ($chart as $mainName => $subGroups) {

            $main = MainGroup::firstOrCreate([
                'name' => $mainName,
                'is_active' => 1
            ]);

            foreach ($subGroups as $subName => $accountGroups) {

                $sub = SubGroup::firstOrCreate([
                    'name' => $subName,
                    'main_group_id' => $main->id,
                    'is_active' => 1
                ]);

                foreach ($accountGroups as $ag) {

                    $accountGroup = AccountGroup::firstOrCreate([
                        'name' => $ag['group'],
                        'sub_group_id' => $sub->id,
                        'is_active' => 1,
                    ]);

                    foreach ($ag['heads'] as $head) {

                        AccountHead::firstOrCreate([
                            'name' => $head['name'],
                            'name_np' => $head['name_np'] ?? null,
                            'account_group_id' => $accountGroup->id,
                            'is_active' => 1
                        ]);
                    }
                }
            }
        }
    }
}
