<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\ChartOfAccount;
use App\Models\PaymentAccount;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RoyalNestFoundationSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Default Branch
        $branch = Branch::firstOrCreate(
            ['code' => 'MAIN-01'],
            [
                'name' => 'Royal Nest Café & Restaurant',
                'phone' => '+8801700000000',
                'email' => 'contact@royalnest.com',
                'address' => 'Dhaka, Bangladesh',
                'bin_number' => '001234567-0101',
                'currency' => 'BDT',
                'currency_symbol' => '৳',
                'timezone' => 'Asia/Dhaka',
                'business_day_start_time' => '06:00:00',
                'is_active' => true,
            ]
        );

        // 2. Business Settings
        BusinessSetting::set('business_name', 'Royal Nest Café & Restaurant', 'general', 'Official Trading Name');
        BusinessSetting::set('vat_rate', 15.00, 'tax', 'Standard Bangladesh VAT Rate');
        BusinessSetting::set('vat_mode', 'inclusive', 'tax', 'inclusive or exclusive');
        BusinessSetting::set('max_cashier_discount_percent', 5.00, 'pos', 'Maximum discount % allowed without manager PIN');
        BusinessSetting::set('business_day_cutoff', '06:00:00', 'pos', 'Time when late night operations roll to next business date');

        // 3. Roles
        $roles = [
            ['name' => 'owner', 'display_name' => 'Owner / Super Admin', 'description' => 'Complete system governance, chart of accounts, financials', 'is_system' => true],
            ['name' => 'manager', 'display_name' => 'Restaurant Manager', 'description' => 'Daily operations, stock adjustments, shift approvals, discounts', 'is_system' => true],
            ['name' => 'cashier', 'display_name' => 'Cashier / POS Operator', 'description' => 'Table orders, billing, payments, shift drawer balancing', 'is_system' => true],
            ['name' => 'kitchen_staff', 'display_name' => 'Kitchen Staff / Chef', 'description' => 'Kitchen Display System tickets, preparation times, prep batches', 'is_system' => true],
            ['name' => 'storekeeper', 'display_name' => 'Storekeeper / Inventory', 'description' => 'Goods receipt, physical stock counts, wastage entries', 'is_system' => true],
            ['name' => 'purchase_officer', 'display_name' => 'Purchase Officer', 'description' => 'Requisitions, supplier POs, supplier catalog', 'is_system' => true],
            ['name' => 'accountant', 'display_name' => 'Accountant', 'description' => 'General ledger journals, AP/AR reconciliation, financial statements', 'is_system' => true],
            ['name' => 'hr', 'display_name' => 'HR / Supervisor', 'description' => 'Staff profiles, shifts, attendance, payroll calculations', 'is_system' => true],
            ['name' => 'auditor', 'display_name' => 'Auditor (Read-Only)', 'description' => 'Read-only access to audit trail and financial reports', 'is_system' => true],
        ];

        foreach ($roles as $roleData) {
            Role::firstOrCreate(['name' => $roleData['name']], $roleData);
        }

        // 4. Default Admin User
        $admin = User::firstOrCreate(
            ['email' => 'admin@royalnest.com'],
            [
                'branch_id' => $branch->id,
                'name' => 'Royal Nest Owner',
                'phone' => '+8801711111111',
                'primary_role' => 'owner',
                'password' => Hash::make('password'),
                'security_pin' => Hash::make('1234'),
                'is_active' => true,
            ]
        );

        $ownerRole = Role::where('name', 'owner')->first();
        if ($ownerRole) {
            $admin->roles()->syncWithoutDetaching([$ownerRole->id]);
        }

        // 5. Chart of Accounts (COA) PRD Section 7.2
        $coaAccounts = [
            // Assets (1000s)
            ['code' => '1010', 'name' => 'Cash in Drawer / Float', 'type' => 'asset', 'normal_balance' => 'debit', 'is_control' => true, 'is_system' => true],
            ['code' => '1020', 'name' => 'Petty Cash', 'type' => 'asset', 'normal_balance' => 'debit', 'is_control' => false, 'is_system' => true],
            ['code' => '1030', 'name' => 'BRAC Bank Current Account', 'type' => 'asset', 'normal_balance' => 'debit', 'is_control' => true, 'is_system' => true],
            ['code' => '1040', 'name' => 'bKash Merchant Account', 'type' => 'asset', 'normal_balance' => 'debit', 'is_control' => true, 'is_system' => true],
            ['code' => '1050', 'name' => 'Nagad Merchant Account', 'type' => 'asset', 'normal_balance' => 'debit', 'is_control' => true, 'is_system' => true],
            ['code' => '1060', 'name' => 'Card Settlement Clearing', 'type' => 'asset', 'normal_balance' => 'debit', 'is_control' => true, 'is_system' => true],
            ['code' => '1100', 'name' => 'Raw Materials & Food Inventory', 'type' => 'asset', 'normal_balance' => 'debit', 'is_control' => true, 'is_system' => true],
            ['code' => '1200', 'name' => 'Accounts Receivable (Customer Due)', 'type' => 'asset', 'normal_balance' => 'debit', 'is_control' => true, 'is_system' => true],
            ['code' => '1500', 'name' => 'Kitchen Equipment & Fixtures', 'type' => 'asset', 'normal_balance' => 'debit', 'is_control' => false, 'is_system' => true],

            // Liabilities (2000s)
            ['code' => '2010', 'name' => 'Accounts Payable (Supplier Bills)', 'type' => 'liability', 'normal_balance' => 'credit', 'is_control' => true, 'is_system' => true],
            ['code' => '2020', 'name' => 'Accrued Operating Expenses', 'type' => 'liability', 'normal_balance' => 'credit', 'is_control' => false, 'is_system' => true],
            ['code' => '2030', 'name' => 'Accrued Staff Payroll', 'type' => 'liability', 'normal_balance' => 'credit', 'is_control' => false, 'is_system' => true],
            ['code' => '2040', 'name' => 'NBR VAT Payable (15%)', 'type' => 'liability', 'normal_balance' => 'credit', 'is_control' => true, 'is_system' => true],
            ['code' => '2050', 'name' => 'Customer Advance Deposits', 'type' => 'liability', 'normal_balance' => 'credit', 'is_control' => false, 'is_system' => true],

            // Equity (3000s)
            ['code' => '3010', 'name' => 'Owner Capital', 'type' => 'equity', 'normal_balance' => 'credit', 'is_control' => false, 'is_system' => true],
            ['code' => '3020', 'name' => 'Owner Drawings', 'type' => 'equity', 'normal_balance' => 'debit', 'is_control' => false, 'is_system' => true],
            ['code' => '3030', 'name' => 'Retained Earnings', 'type' => 'equity', 'normal_balance' => 'credit', 'is_control' => false, 'is_system' => true],

            // Revenue (4000s)
            ['code' => '4010', 'name' => 'Food Sales Revenue', 'type' => 'revenue', 'normal_balance' => 'credit', 'is_control' => false, 'is_system' => true],
            ['code' => '4020', 'name' => 'Beverage Sales Revenue', 'type' => 'revenue', 'normal_balance' => 'credit', 'is_control' => false, 'is_system' => true],
            ['code' => '4030', 'name' => 'Delivery & Service Charge Revenue', 'type' => 'revenue', 'normal_balance' => 'credit', 'is_control' => false, 'is_system' => true],

            // Contra Revenue (4100s)
            ['code' => '4110', 'name' => 'Sales Discounts Allowed', 'type' => 'contra_revenue', 'normal_balance' => 'debit', 'is_control' => false, 'is_system' => true],
            ['code' => '4120', 'name' => 'Customer Refunds & Returns', 'type' => 'contra_revenue', 'normal_balance' => 'debit', 'is_control' => false, 'is_system' => true],

            // Cost of Goods Sold (5000s)
            ['code' => '5010', 'name' => 'Food Ingredients Consumed (BOM COGS)', 'type' => 'cogs', 'normal_balance' => 'debit', 'is_control' => false, 'is_system' => true],
            ['code' => '5020', 'name' => 'Beverage Ingredients Consumed', 'type' => 'cogs', 'normal_balance' => 'debit', 'is_control' => false, 'is_system' => true],
            ['code' => '5030', 'name' => 'Packaging & Disposable Consumables', 'type' => 'cogs', 'normal_balance' => 'debit', 'is_control' => false, 'is_system' => true],
            ['code' => '5040', 'name' => 'Kitchen Wastage & Spoilage', 'type' => 'cogs', 'normal_balance' => 'debit', 'is_control' => false, 'is_system' => true],

            // Operating Expenses (6000s)
            ['code' => '6010', 'name' => 'Staff Salaries & Labour Wages', 'type' => 'expense', 'normal_balance' => 'debit', 'is_control' => false, 'is_system' => true],
            ['code' => '6020', 'name' => 'Restaurant Rent', 'type' => 'expense', 'normal_balance' => 'debit', 'is_control' => false, 'is_system' => true],
            ['code' => '6030', 'name' => 'Utilities (Gas, Electric, Water)', 'type' => 'expense', 'normal_balance' => 'debit', 'is_control' => false, 'is_system' => true],
            ['code' => '6040', 'name' => 'Internet & POS Systems', 'type' => 'expense', 'normal_balance' => 'debit', 'is_control' => false, 'is_system' => true],
            ['code' => '6050', 'name' => 'Repairs & Kitchen Maintenance', 'type' => 'expense', 'normal_balance' => 'debit', 'is_control' => false, 'is_system' => true],
            ['code' => '6060', 'name' => 'Cleaning & Sanity Supplies', 'type' => 'expense', 'normal_balance' => 'debit', 'is_control' => false, 'is_system' => true],
            ['code' => '6070', 'name' => 'Marketing & Promotions', 'type' => 'expense', 'normal_balance' => 'debit', 'is_control' => false, 'is_system' => true],

            // Other Income/Expense (7000s)
            ['code' => '7010', 'name' => 'Bank & MFS Gateway Fees', 'type' => 'other_income_expense', 'normal_balance' => 'debit', 'is_control' => false, 'is_system' => true],
        ];

        foreach ($coaAccounts as $acc) {
            ChartOfAccount::firstOrCreate(['code' => $acc['code']], $acc);
        }

        // 6. Connect Payment Accounts to COA
        $cashAcc = ChartOfAccount::where('code', '1010')->first();
        $pettyAcc = ChartOfAccount::where('code', '1020')->first();
        $bankAcc = ChartOfAccount::where('code', '1030')->first();
        $bkashAcc = ChartOfAccount::where('code', '1040')->first();
        $nagadAcc = ChartOfAccount::where('code', '1050')->first();
        $cardAcc = ChartOfAccount::where('code', '1060')->first();

        $paymentAccounts = [
            ['name' => 'Main Cash Drawer', 'type' => 'cash_drawer', 'chart_account_id' => $cashAcc->id, 'account_number' => null],
            ['name' => 'Kitchen Petty Cash', 'type' => 'petty_cash', 'chart_account_id' => $pettyAcc->id, 'account_number' => null],
            ['name' => 'BRAC Bank Current', 'type' => 'bank', 'chart_account_id' => $bankAcc->id, 'account_number' => '1501204890001'],
            ['name' => 'bKash Merchant (01700-000000)', 'type' => 'bkash', 'chart_account_id' => $bkashAcc->id, 'account_number' => '01700000000'],
            ['name' => 'Nagad Merchant (01800-000000)', 'type' => 'nagad', 'chart_account_id' => $nagadAcc->id, 'account_number' => '01800000000'],
            ['name' => 'Visa/MasterCard Settlement', 'type' => 'card_clearing', 'chart_account_id' => $cardAcc->id, 'account_number' => null],
        ];

        foreach ($paymentAccounts as $pAcc) {
            PaymentAccount::firstOrCreate(
                ['branch_id' => $branch->id, 'name' => $pAcc['name']],
                [
                    'chart_account_id' => $pAcc['chart_account_id'],
                    'type' => $pAcc['type'],
                    'account_number' => $pAcc['account_number'],
                    'opening_balance' => 0.00,
                    'current_balance' => 0.00,
                    'is_active' => true,
                ]
            );
        }
    }
}
