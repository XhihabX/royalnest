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
- **Staff Management & Camera Verification**:
  - Auto-generated unique employee IDs (`EMP-001`, `EMP-002`).
  - One-tap **Live Camera Photo Snap** and **NID Card Capture** with client-side canvas compression and fallback to file upload.
  - Daily & Bulk Attendance grids (`Present`, `Half-day`, `Leave`, `Absent`) with duplicate date prevention.
  - Salary accrual, partial payouts, staff advances, and advance recovery deductions with **zero double-counting of expenses**.
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
- **Repository Isolation**: `d:\Projects\Saba POS\royal-nest` (completely isolated git repository, ignored by parent `.gitignore`)
- **Packaging**: Self-contained production release script (`package_production.py`)

---

## 3. Core Database Tables & Data Model
1. **Foundation, RBAC & Authentication**:
   - `branches`: Multi-branch ready with branch currency, phone, and timezone.
   - `roles`, `permissions`, `user_roles`, `users`: Role-based access control (`owner`, `manager`, `cashier`, `staff`).
   - `sessions`: Secure database-backed sessions with `/login` authentication wall.
   - `audit_logs`: Immutable tracking of user actions, before/after payloads, and IP addresses.
2. **Products & Unit Conversions**:
   - `categories`: Classified into `raw_material`, `consumable`, `other_stock`.
   - `products`: Product master storing `current_stock` and latest `cost_price` always in `base_unit` (`g`, `kg`, `ml`, `L`, `pcs`).
   - `product_unit_conversions`: Product-specific conversion matrix (e.g. 1 sack = 25 kg, 1 kg = 1000 g).
3. **Supplier Directory & Active Dues**:
   - `suppliers`: Directory with `company_name`, `contact_person`, `phone`, `alternative_phone`, `business_location`, `category`, `status` (`potential`, `active`, `inactive`, `archived`), `preferred_payment_terms`, `opening_balance`, `current_balance`.
   - `supplier_products`: Catalog of products supplied by each vendor with agreed rate `unit_price`, `purchase_unit`, `min_order_quantity`.
   - `supplier_payments`: Payment vouchers against supplier due balance (`voucher_no`, `payment_date`, `amount`, `payment_account_id`, `payment_method`).
   - `supplier_advances`: Advance payments given to vendors reducing net payable.
4. **Perpetual Stock Ledger & FIFO Batches**:
   - `stock_batches`: Discrete purchase lots (`batch_no`, `received_date`, `initial_quantity`, `remaining_quantity`, `unit_cost`, `is_exhausted`).
   - `purchases` & `purchase_items`: Confirmed stock receipts and direct Purchase & Consume transactions.
   - `stock_ledger`: Immutable chronological movement log (`opening_stock`, `purchase_receipt`, `consumption`, `wastage`, `adjustment_in`, `adjustment_out`, `purchase_return`) linked to specific `stock_batch_id`.
   - `consumptions` & `consumption_items`: Daily ingredient actual usage records linked to specific `stock_batch_id`.
   - `consumable_allocations`: Multi-day consumable amortized allocation schedules.
   - `wastages`: Spoilage, burnt, or expired item records with reasons.
   - `stock_counts` & `stock_count_items`: Physical count vs system balance with discrepancy tracking.
5. **Daily Sales & Financial Accounts**:
   - `daily_sales`: Total gross sales with breakdown into Cash, bKash, Nagad, Card/Bank, Other, and discrepancy amount.
   - `operating_expenses`: Daily operating costs (electricity, gas, water, rent, transport, repairs, cleaning, marketing, other).
   - `financial_accounts`: Accounts tracking Cash Drawer, Bank, bKash, Nagad with opening and current balances.
   - `account_transactions`: Deposits, withdrawals, and internal transfers (`transfer_in`, `transfer_out`).
6. **Staff, Attendance & Payroll**:
   - `employees`: Staff records with auto-generated `employee_id`, `name`, `phone`, `alternative_phone`, `nid_number`, `photo_path`, `nid_photo_path`, `role`, `salary_type` (`monthly`, `daily`, `hourly`), `agreed_salary`, `status` (`active`, `inactive`, `on_leave`, `archived`).
   - `employee_attendances`: Daily attendance records (`present`, `absent`, `half_day`, `leave`) with unique constraint on `(employee_id, attendance_date)`.
   - `employee_advances`: Staff advances issued with balance tracking (`voucher_no`, `amount`, `remaining_balance`, `status`).
   - `salary_payments`: Salary disbursals from financial accounts.
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

---

## 5. Web Interface & Route Structure
All routes render Vue 3 components via Inertia.js with Tailwind CSS v4:
- `/login`: Production Authentication Wall (`Auth/Login.vue`)
- `/`: Executive Operations Dashboard with Cross-Module Cards (`Dashboard.vue`)
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
- `/accounts`: Financial Accounts & Transfers (`Accounts/Index.vue`)
- `/employees`: Staff Directory with Live Camera Snapshot Modal (`Employees/Index.vue`)
- `/employees/{id}/profile`: Detailed Employee Profile, Attendance & Salary (`Employees/Profile.vue`)
- `/employees/attendance/bulk`: 1-Click Team Attendance Grid (`Employees/BulkAttendance.vue`)
- `/reports/profit-loss`: True Profit & Loss Statement (`Reports/ProfitLoss.vue`)

---

## 6. Automated Verification Status
- **Test Coverage**: 29 automated PHPUnit tests with 158 assertions running against native MySQL 8.4 engine (`royalnest_erp_test`), passing 100% green.
- **Supplier & Staff Verification**: Full verification of Directory vs Active separation, due payment vouchers, cash drawer deductions, Employee ID auto-generation, and advance recovery deductions.
- **FIFO Verification**: Scenario 3 in `tests/Feature/PrdMandatoryScenariosTest.php` and `SystemLogicPerfectionTest.php` prove previous stock retains historical price, future purchases retain incoming price, and wastage/stock counts maintain strict batch parity.
- **Frontend Compiler**: Vite 8 compiling all production bundles with zero errors in 6.13 seconds.
- **Production Packager**: `package_production.py` generates self-contained deployment ZIP in `dist/` (0.40 MB).
