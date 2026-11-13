# Royal Nest Café & Restaurant — Architecture & System Specification
## Cost, Inventory, Income & Profit Management System (PRD v1.1 + Supplier & Staff Extensions)

*This file describes the current state of the Royal Nest system. Update it in place whenever something changes — this is not a history, see CHANGELOG.md for history.*

## 1. Overview & Core Objective
Royal Nest Café & Restaurant is a dedicated financial and inventory management system designed to track:
- Daily sales and payment method reconciliation (Cash, bKash, Nagad, Card/Bank).
- Raw materials and consumable inventory under a **Perpetual Inventory System**.
- Goods receiving with configurable product-specific unit conversions and **FIFO (First-In, First-Out) Batch Costing** (previous stock retains its exact historical purchase price, future stock retains its own price, and consumption automatically deducts from the oldest purchase batch first).
- Actual end-of-day kitchen ingredient consumption with **Negative Stock Prevention**.
- Direct **Purchase & Consume** atomic workflow (stock receipt $+Q$ and consumption $-Q$, 0 ending stock, 0 duplicate expense).
- Multi-day **Consumables Cost Allocation** (amortizing items like ৳120 tissue box over 4 days = ৳30/day without upfront P&L expense spikes).
- Daily operating expenses (rent, gas, electricity, maintenance, etc.) kept strictly separate from inventory purchases.
- **Two-Sided Supplier Management**:
  - **Supplier Directory (Master Rolodex)**: Catalog of potential, active, inactive, and archived suppliers with market location, contacts, and multi-product agreed supply rates.
  - **Active Supplier Management**: Live accounts tracking purchase bills, payment vouchers, advance vouchers, and exact outstanding due payable (`Confirmed Bills - Payments - Advances`).
  - **Dedicated Supplier Profile & Statements**: Chronological bill history with item lists, payment vouchers, running due balance, and print-ready statement vouchers.
- **Staff Management, Security Deposit (Held Salary) & Shift Operations**:
  - Auto-generated unique employee IDs (`EMP-001`, `EMP-002`).
  - One-tap **Live Camera Photo Snap** and **NID Card Capture** with client-side canvas compression and fallback to file upload.
  - Department categorization (`kitchen`, `service`, `cleaning`, `cashier`, `management`) and tags.
  - **Staff Shift System & Shift Attendance**: Staff assigned to `morning`, `evening`, or `both` shifts. Attendance tracked by date + shift (`morning`, `evening`, or legacy `null`). Missing shift alerts on Dashboard and Staff Directory identify exact employee and missing shift, disappearing automatically once recorded.
  - **Preserved Salary Calculation Rules**: Zero arbitrary `0.5x` cuts for `both`-shift workers. Monthly salary accrued as agreed salary, and daily workers evaluated once per calendar date attended.
  - **Held Salary (Security Deposit / জামানত)**: Configurable per employee (`held_salary_days` e.g. 3 or 5 days, or custom `held_salary_amount`) held by the owner as a security buffer and subtracted from net disbursable salary.
  - Daily & Bulk Attendance grids with shift selectors and duplicate shift prevention.
  - Salary accrual, partial payouts, staff advances (including initial job joining advance), and advance recovery deductions with **zero double-counting of expenses**.
- **Interactive Daily Profit & Loss Calculator (`/calculator`)**:
  - Mobile-first daily worksheet where the user enters today's sales breakdown and actual raw materials used today (e.g. 4 kg Beef, 2 L Oil).
  - Automatically computes live FIFO batch cost per ingredient, adds today's accrued staff salary and operating expenses, and previews live Gross Profit, True Net Profit, and Net Margin %.
  - One-click **Save & Sync** atomically deducts FIFO inventory batches (`ConsumptionService`) and reconciles `DailySale`.
- **Installable Mobile Progressive Web App (PWA) & Secure Service Worker**:
  - Standalone `manifest.json` with separate `any` and `maskable` home screen app icons (`192x192`, `512x512`), iOS/Android web-app meta tags (`viewport-fit=cover`), sticky bottom navigation bar (`Home`, `Costs`, `Calculator`, `Staff`, `Menu`), and slide-up mobile drawer sheet.
  - Service Worker (`public/sw.js`) caches static hashed assets and icons, with a **strict zero-cache policy for all authenticated ERP routes, dynamic documents, API calls, and financial reports**.
- **Multi-Bill Supplier Payment Allocation**:
  - Single payments can settle multiple bills across `supplier_payment_allocations` table while preserving general FIFO fallback.
- **Configurable Financial Accounts**:
  - Admin can toggle accounts active/inactive (`POST /accounts/{account}/toggle-status`). Inactive accounts blocked from new transactions while ledgers remain intact.
- **Daily Purchases & Expenses Transactions Report (`/reports/transactions`)**:
  - Daily Purchases line-by-line item audit, Daily Expenses & Outflows strictly separating true P&L operating expenses from balance-sheet liability settlements, and Combined filterable stream.
- Financial accounts (Cash Drawer, bKash, Nagad, Bank) with neutral internal funds transfers.
- Accurate **Daily, Monthly, and Date Range Profit & Loss Reports**.
- Authentic **Bilingual Localization** (Native Bengali বাংলা & English) with reactive navbar toggle.

*Strict Boundaries*: As defined by PRD v1.1 Section 24, Kitchen Display System (KDS), dining table maps, delivery fleets, CRM, AI forecasting, and automatic recipe-based deductions are explicitly out of scope. Stock deduction is based strictly on manually entered actual usage.

---

## 2. Tech Stack & Environment
- **Backend**: Laravel 12 (PHP 8.3.33, `ext-intl`, `ext-gd` enabled)
- **Frontend**: Vue 3 (Composition API), Inertia.js, Tailwind CSS v4, Lucide Icons, Canvas camera compression
- **Localization**: Dedicated bilingual i18n dictionary (`bn.json`, `en.json`) with `useI18n` composable
- **Database Engine**: MySQL 8.4 (InnoDB transactions with row-level locks, `DECIMAL(12, 4)` inventory precision, `DECIMAL(12, 2)` monetary values)
- **Timezone & Locale**: BDT (`৳`), `Asia/Dhaka` timezone
- **Repository & Remote**: `d:\Projects\Saba POS\royal-nest` (standalone git repository, remote: `https://github.com/XhihabX/royalnest.git`, branch `main`)
- **Packaging**: Self-contained production release script (`package_production.py`)

---

## 3. Core Database Tables & Data Model
1. **Foundation, RBAC & Authentication**:
   - `branches`: Multi-branch ready with branch currency, phone, and timezone.
   - `roles`, `permissions`, `user_roles`, `users`: Role-based access control (`owner`, `manager`, `cashier`, `staff`, `visitor`).
   - `is_super_admin`: Verified single-admin shield locking User ID 1 (`admin@royalnest.com`) against deletion, deactivation, or demotion.
   - `sessions`: Secure database-backed sessions with `/login` authentication wall.
   - `audit_logs`: Immutable tamper-proof tracking of user actions, before/after payloads, event UUID, module, and IP addresses.
2. **Products & Unit Conversions**:
   - `categories`: Classified into `raw_material`, `packaging`, `consumable`, `other_stock` with soft-deletes (`deleted_at`).
   - `products`: Product master storing `current_stock` and latest `cost_price` always in `base_unit` (`g`, `kg`, `ml`, `L`, `pcs`) with soft-deletes (`deleted_at`).
   - `product_unit_conversions`: Product-specific conversion matrix (e.g. 1 sack = 25 kg, 1 kg = 1000 g).
3. **Supplier Directory & Active Dues**:
   - `suppliers`: Directory with `company_name`, `contact_person`, `phone`, `alternative_phone`, `business_location`, `category`, `status` (`potential`, `active`, `inactive`, `archived`), `preferred_payment_terms`, `opening_balance`, `current_balance`, soft-deletes (`deleted_at`).
   - `supplier_products`: Catalog of products supplied by each vendor with agreed rate `unit_price`, `purchase_unit`, `min_order_quantity`.
   - `supplier_payments`: Payment vouchers against supplier due balance (`voucher_no`, `payment_date`, `amount`, `payment_account_id`, `payment_method`), reversal tracking (`reversal_status`, `reversal_type`, `reversed_at`, `reversed_by`, `reversal_reason`).
   - `supplier_advances`: Advance payments given to vendors reducing net payable.
   - `supplier_payment_allocations`: Multi-bill settlement linking individual purchase bills to payment vouchers.
4. **Perpetual Stock Ledger & FIFO Batches**:
   - `stock_batches`: Discrete purchase lots (`batch_no`, `received_date`, `initial_quantity`, `remaining_quantity`, `unit_cost`, `is_exhausted`).
   - `purchases` & `purchase_items`: Confirmed stock receipts and direct Purchase & Consume transactions with reversal tracking (`reversal_status`, `reversal_type`, `reversed_at`, `reversed_by`, `reversal_reason`).
   - `stock_ledger`: Immutable chronological movement log (`opening_stock`, `purchase_receipt`, `consumption`, `wastage`, `adjustment_in`, `adjustment_out`, `purchase_return`) linked to specific `stock_batch_id`.
   - `consumptions` & `consumption_items`: Daily ingredient actual usage records linked to specific `stock_batch_id`.
   - `consumable_allocations`: Multi-day consumable amortized allocation schedules.
   - `wastages`: Spoilage, burnt, or expired item records with reasons.
   - `stock_counts` & `stock_count_items`: Physical count vs system balance with discrepancy tracking.
5. **Daily Sales & Financial Accounts**:
   - `daily_sales`: Total gross sales with breakdown into Cash, bKash, Nagad, Card/Bank, Other, and discrepancy amount, with reversal tracking.
   - `operating_expenses`: Daily operating costs (electricity, gas, water, rent, transport, repairs, cleaning, marketing, other), with reversal tracking.
   - `financial_accounts`: Accounts tracking Cash Drawer, Bank, bKash, Nagad, and custom accounts with active/inactive status toggles.
   - `account_transactions`: Deposits, withdrawals, and internal transfers (`transfer_in`, `transfer_out`).
6. **Staff, Attendance & Payroll**:
   - `employees`: Staff records with auto-generated `employee_id`, `name`, `phone`, `alternative_phone`, `nid_number`, `photo_path`, `nid_photo_path`, `role`, `department`, `tags`, `assigned_shift` (`morning`, `evening`, `both`), `salary_type` (`monthly`, `daily`, `hourly`), `agreed_salary`, `held_salary_days`, `held_salary_amount`, `status`, soft-deletes (`deleted_at`).
   - `employee_attendances`: Shift-aware attendance records (`shift`: `morning`, `evening`, `null`) with unique constraint on `(employee_id, attendance_date, shift)`.
   - `employee_advances`: Staff advances issued with balance tracking (`voucher_no`, `amount`, `remaining_balance`, `status`), with reversal tracking.
   - `salary_payments`: Salary disbursals from financial accounts, with reversal tracking.
   - `salary_deductions`: Advance recoveries deducted from monthly salary payouts.

---

## 4. Business Services Layer (`app/Services/`)
- `SupplierService`:
  - Enforces two distinct views: Master Directory vs Active Vendors.
  - Manages activation/deactivation lifecycle without deleting historical data.
  - Maintains multi-product agreed rate catalogs.
  - Records payments against supplier dues and advance vouchers.
  - Computes exact live outstanding payables and generates printable statements.
- `EmployeeService`:
  - Generates auto-incrementing Employee IDs (`EMP-001`, `EMP-002`).
  - Processes camera photo snapshots and NID uploads.
  - Records single and bulk team attendance.
  - Manages staff advances and recovers advances via payroll deductions.
  - Computes accrued salary vs paid salary vs remaining payable.
- `StockLedgerService`:
  - Enforces atomic database locks (`Product::lockForUpdate()`).
  - Guards against negative stock by default (`ValidationException`).
  - Creates discrete `StockBatch` on purchase receipts.
  - Appends immutable `StockLedger` movements with batch traceability.
- `PurchaseService`:
  - Converts purchased units to base units via product-specific multipliers.
  - Generates discrete `StockBatch` for every purchase line.
  - Posts stock receipt movement to ledger.
  - Handles direct **Purchase & Consume** workflow.
  - Updates supplier payable liability and deducts payments from financial accounts.
- `ConsumptionService` (Automatic FIFO Dispatch):
  - Validates total available stock across all active batches.
  - Sequentially consumes from the oldest available `StockBatch` (`received_date ASC, id ASC`).
  - Slices multi-batch consumption entries accurately in `stock_ledger` and `consumption_items`.
- `Wastage & Stock Count Synchronization`:
  - `WastageController` sequentially traverses and depletes active FIFO batches, computing true weighted average wastage cost across multiple purchase lots.
  - `StockCountController` synchronizes physical counts with FIFO batches: surplus creates an `ADJUSTMENT-COUNT-` batch, and deficit chronologically depletes oldest active batches.
- `DailySalesController` (Multi-Account Cash Intake):
  - Automatically deposits reconciled daily revenue into active financial accounts (`cash` $\rightarrow$ Cash Drawer, `bkash` $\rightarrow$ bKash, `nagad` $\rightarrow$ Nagad, `bank` $\rightarrow$ Bank).
  - Uses idempotent transaction reconciliation to allow safe edits/re-submissions without double-counting.
- `FinancialAccountService`:
  - Records deposits, withdrawals, and profit-neutral account-to-account transfers.
- `ProfitLossService`:
  - Computes True Profit & Loss:
    - $\text{Gross Profit} = \text{Total Sales} - (\text{Ingredient Consumption Cost} + \text{Consumables Allocated Cost})$
    - $\text{Total Operating Cost} = \text{Operating Expenses} + \text{Staff Salary Accrual} + \text{Wastage Cost}$
    - $\text{True Net Profit} = \text{Gross Profit} - \text{Total Operating Cost}$
    - Zero duplicate expenses from supplier due payments or salary cash disbursals.
- `AuditLogger`:
  - Dual-layer immutable activity logger with UUID deduplication and sensitive payload sanitization.
  - Automatically captures authentication, financial vouchers, inventory movements, staff management, and system administration events.
  - Prevents updates or deletes at the model level via `AccessDeniedHttpException`.
- `ReversalService`:
  - Segregates bookkeeping voids from real-world money refunds.
  - Implements FIFO purchase batch reversal matrix: 100% intact shelf stock unwound with compensating `purchase_return` ledger movement. Partially consumed stock safely blocked from full voiding; when partial return is approved (`$allowPartialReturn = true`), remaining physical shelf stock is returned via compensating `purchase_return` ledger entries, while supplier due liability is credited up to the returned shelf stock value (`$totalReturnedValue`) without erasing legitimate kitchen consumption cost. Fully consumed stock strictly blocked from voiding.
  - Voids mistaken supplier payments, salary payouts, advances, operating expenses, and daily sales by neutralizing fictitious ledger effects and restoring linked balances.
  - Records real-world supplier and employee cash/bank refunds into specified active accounts.
- `ReportController` & Outflow Classification:
  - Explicitly separates **Total Account Outflows** into:
    - **Actual Physical Cash Outflows** (`actual_cash_outflows`): Outflows strictly tied to accounts of type `cash` (Cash Drawer).
    - **Digital Bank & MFS Outflows** (`digital_bank_outflows`): Outflows tied to accounts of type `bank`, `bkash`, or `nagad`.
    - **Unlinked / Unresolved Outflows** (`unresolved_account_outflows`): Any expense, payment, or advance missing a valid account relationship is flagged as `unknown` / `Unlinked` rather than being silently assumed as cash.
    - **Total Account Outflows** (`total_account_outflows`): Sum of all active transaction outflows.
    - Legacy alias `total_cash_outflows` retained as an alias to `total_account_outflows` with deprecation notices.

---

## 5. Web Interface & Route Structure
All routes render Vue 3 components via Inertia.js with Tailwind CSS v4:
- `/login`: Production Authentication Wall (`Auth/Login.vue`)
- `/`: Executive Operations Dashboard with Cross-Module Cards (`Dashboard.vue`)
- `/calculator`: Interactive Daily Profit & Loss Calculator (`Calculator/Daily.vue`)
- `/daily-costs`: Smart Daily Cost Entry with Instant Daily Breakdown KPI Card & Voice Typing (`Costs/Index.vue`)
- `/cash-reconciliation`: Daily Cash Reconciliation & Auto Sales Estimation (`Cash/Reconciliation.vue`)
- `/sales`: Daily Sales Entry & Reconciliation (`Sales/Daily.vue`)
- `/suppliers/directory`: Master Supplier Directory & Product Catalogs (`Suppliers/Directory.vue`)
- `/suppliers/active`: Active Supplier Dues & Payment Settlement (`Suppliers/Active.vue`)
- `/suppliers/{id}/profile`: Detailed Supplier Profile & Printable Statement (`Suppliers/Profile.vue`)
- `/purchases`: Stock Receiving & Purchase & Consume (`Purchases/Index.vue`)
- `/consumption`: End-of-Day Kitchen Usage Entry (`Consumption/Index.vue`)
- `/consumables`: Amortization Scheduler (`Consumables/Index.vue`)
- `/ledger`: Perpetual Stock Movement Ledger with Batch Tags (`Ledger/Index.vue`)
- `/expenses`: Operating Expenses Categorization (`Expenses/Index.vue`)
- `/wastage`: Spoilage & Wastage Entry with FIFO Traversal (`Wastage/Index.vue`)
- `/stock-counts`: Physical Stock Count Audit & FIFO Batch Sync (`StockCounts/Index.vue`)
- `/accounts`: Financial Accounts, Status Toggles & Internal Transfers (`Accounts/Index.vue`)
- `/employees`: Staff Directory with Department Tabs, Held Salary Settings & Camera Snapshot (`Employees/Index.vue`)
- `/employees/{id}/profile`: Detailed Employee Profile, Held Security Deposit, Attendance & Salary (`Employees/Profile.vue`)
- `/employees/attendance/bulk`: 1-Click Team Attendance Grid (`Employees/BulkAttendance.vue`)
- `/reports/transactions`: Daily Purchases, Expenses & Cash Outflow Report (`Reports/Transactions.vue`)
- `/reports/profit-loss`: True Profit & Loss Statement (`Reports/ProfitLoss.vue`)
- `/audit-logs`: Immutable Activity Log Timeline, Diff Viewer & CSV Export (`AuditLogs/Index.vue`)
- `/recycle-bin`: Soft-Delete Item Recovery for Products, Suppliers, Employees & Categories (`RecycleBin/Index.vue`)
- `/users`: Super Admin Protected User Management & Visitor Read-Only Role (`Users/Index.vue`)
- `GET /api/voice/lookup`: Live fuzzy database lookup endpoint powering Universal Voice Dictation across 19 forms

---

## 6. Automated Verification Status
- **Test Coverage**: 92 automated PHPUnit tests across 14 test suites passing 100% green, including `FinalProductionRefinementsTest.php`, `SystemWideEnhancementAndReadinessTest.php`, `ShiftAttendanceReportsAndAccountsTest.php`, `MobileCalculatorAndStaffHoldTest.php`, `SmartCostAndCashReconciliationTest.php`, `OneTimeEntryGlobalSynchronizationTest.php`, `SystemLogicPerfectionTest.php`, and `PrdMandatoryScenariosTest.php`.
- **Supplier & Staff Verification**: Full verification of Directory vs Active separation, multi-bill allocation, due payment vouchers, cash drawer deductions, Employee ID auto-generation, shift alerts, held salary security deposit calculation, and advance recovery deductions.
- **FIFO & Daily Calculator Verification**: Proves previous stock retains historical price, future purchases retain incoming price, and the Daily P&L Calculator atomically deducts FIFO batches while syncing daily sales.
- **Audit, Recovery & Reversal Verification**: Verified tamper-proof immutable audit records, soft-delete restoration without dependency violations, separate accounting voids vs actual refunds, Super Admin lock, and Visitor read-only 403 enforcement.
- **Production Refinement Verification**: Verified Bangladesh Standard Timezone (`Asia/Dhaka`), zero contamination of P&L reports by voided transactions while preserving legacy records with `reversal_status = NULL`, and audit visibility of cancelled transactions in `/reports/transactions` with clean exclusion from active cash outflow totals.
- **Frontend Compiler**: Vite 8 compiling all production bundles with zero errors in 5.70 seconds.
- **Production Packager**: `package_production.py` generates self-contained deployment ZIP in `dist/`.
