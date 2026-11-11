<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Models\JournalLine;
use App\Models\PaymentAccount;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FoundationDatabaseTest extends TestCase
{
    public function test_branch_and_roles_exist(): void
    {
        $branch = Branch::where('code', 'MAIN-01')->first();
        $this->assertNotNull($branch);
        $this->assertEquals('Royal Nest Café & Restaurant', $branch->name);
        $this->assertEquals('BDT', $branch->currency);
        $this->assertEquals('৳', $branch->currency_symbol);

        $rolesCount = Role::count();
        $this->assertGreaterThanOrEqual(9, $rolesCount);
        $this->assertTrue(Role::where('name', 'owner')->exists());
        $this->assertTrue(Role::where('name', 'kitchen_staff')->exists());
        $this->assertTrue(Role::where('name', 'cashier')->exists());
    }

    public function test_chart_of_accounts_and_payment_accounts_are_linked(): void
    {
        $cashCoa = ChartOfAccount::where('code', '1010')->first();
        $this->assertNotNull($cashCoa);
        $this->assertEquals('asset', $cashCoa->type);
        $this->assertEquals('debit', $cashCoa->normal_balance);

        $bkash = PaymentAccount::where('type', 'bkash')->first();
        $this->assertNotNull($bkash);
        $this->assertEquals('1040', $bkash->chartOfAccount->code);
    }

    public function test_audit_logger_creates_immutable_log(): void
    {
        $admin = User::where('email', 'admin@royalnest.com')->first();
        $this->actingAs($admin);

        $log = AuditLogger::log(
            action: 'test_action',
            description: 'Phase 0 verification audit',
            beforePayload: ['status' => 'pending'],
            afterPayload: ['status' => 'verified']
        );

        $this->assertDatabaseHas('audit_logs', [
            'id' => $log->id,
            'action' => 'test_action',
            'user_name' => 'Royal Nest Owner',
        ]);
    }

    public function test_double_entry_balanced_journal_posting(): void
    {
        $admin = User::where('email', 'admin@royalnest.com')->first();
        $branch = Branch::where('code', 'MAIN-01')->first();
        $cashAcc = ChartOfAccount::where('code', '1010')->first(); // Cash Asset
        $capitalAcc = ChartOfAccount::where('code', '3010')->first(); // Owner Capital

        $journal = DB::transaction(function () use ($admin, $branch, $cashAcc, $capitalAcc) {
            $jrn = Journal::create([
                'journal_no' => 'JRN-TEST-' . uniqid(),
                'branch_id' => $branch->id,
                'business_date' => now()->toDateString(),
                'transaction_date' => now()->toDateString(),
                'posting_date' => now(),
                'source_module' => 'manual',
                'description' => 'Initial owner capital cash injection',
                'total_debit' => 50000.00,
                'total_credit' => 50000.00,
                'is_posted' => true,
                'created_by' => $admin->id,
            ]);

            // Debit Cash Asset ৳50,000
            JournalLine::create([
                'journal_id' => $jrn->id,
                'chart_account_id' => $cashAcc->id,
                'branch_id' => $branch->id,
                'debit' => 50000.00,
                'credit' => 0.00,
                'description' => 'Owner capital cash deposit',
            ]);

            // Credit Owner Capital ৳50,000
            JournalLine::create([
                'journal_id' => $jrn->id,
                'chart_account_id' => $capitalAcc->id,
                'branch_id' => $branch->id,
                'debit' => 0.00,
                'credit' => 50000.00,
                'description' => 'Owner equity recognized',
            ]);

            return $jrn;
        });

        $this->assertEquals(50000.00, $journal->total_debit);
        $this->assertEquals(50000.00, $journal->total_credit);
        $this->assertCount(2, $journal->lines);
        $this->assertEquals($journal->lines->sum('debit'), $journal->lines->sum('credit'));
    }
}

