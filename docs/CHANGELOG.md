# Changelog — Royal Nest Restaurant ERP

All notable changes to this project will be documented in this file.

## [2026-11-13] — Production Refinements: Bangladesh Timezone, Voided P&L Exclusion & Outflow Report Void Flags

**What:**
1. **Bangladesh Standard Timezone Configuration** (`config/app.php`, `.env`, `.env.production.example`):
   - Configured `'timezone' => env('APP_TIMEZONE', 'Asia/Dhaka')` ensuring runtime timestamps and business dates match local restaurant hours in Bangladesh (UTC+6).
   - Verified that late-evening and midnight restaurant transactions (`23:45` to `00:15`) are cleanly recognized and properly partitioned across business dates without shifting or distorting historical records.
2. **P&L Service Voided Transaction Exclusion** (`app/Services/ProfitLossService.php`):
   - Added explicit filtering `->where(fn ($q) => $q->whereNull('reversal_status')->orWhere('reversal_status', '!=', 'voided'))` across `DailySale` and `OperatingExpense` queries in both single-day (`getDailySummary`) and date-range (`getRangeSummary`) calculators.
   - Guaranteed that cancelled/voided mistaken entries never artificially inflate Gross Sales, Operating Costs, or Net Operating Profit.
   - Preserved backward compatibility for pre-enhancement legacy records where `reversal_status` is `NULL`.
3. **Daily Transactions Report Void Flagging & Real Money Totals** (`app/Http/Controllers/ReportController.php`, `resources/js/Pages/Reports/Transactions.vue`):
   - Passed `reversal_status` across all transaction lines: Purchases, Operating Expenses, Supplier Payments, Staff Salary Payouts, and Staff Advances.
   - Filtered active summary metrics (`total_purchases`, `total_raw_materials`, `total_packaging`, `total_operating_expenses`, `total_supplier_payments`, `total_salary_payments`, `total_advances_issued`, and `total_cash_outflows`) to calculate ONLY active non-voided transactions so report totals strictly reflect genuine money movements.
   - Preserved voided vouchers in all 3 tabs (Purchases, Expenses & Outflows, and Combined Stream) rendered with a prominent `[বাতিল / Voided]` badge, strikethrough styling, and dimmed opacity for complete audit visibility.
4. **Automated Verification Suite** (`tests/Feature/FinalProductionRefinementsTest.php`):
   - Created 11 targeted regression tests covering active vs voided sales in P&L, legacy NULL compatibility, active vs voided expenses, voided purchase exclusion from spend totals, voided supplier/salary payouts, audit visibility of voided vouchers, physical refunds vs voids, Bangladesh midnight timezone partitioning, and unchanged FIFO inventory costing.
   - Full test suite: **92/92 tests (482 assertions) passing 100% green**.
   - Frontend compiled cleanly with Vite 8 (5.70s, 0 errors).
   - Packaged updated production deployment zip: `dist/royal_nest_production_deployment.zip` (240 files, 0.59 MB).

**Why:** Ensure 100% accounting fidelity so that cancelled mistaken transactions never distort financial profit or expense reports, while aligning operational dates with Bangladesh restaurant business hours.
**How:** Configured `config/app.php`, updated `ProfitLossService` and `ReportController` Eloquent queries, updated `Transactions.vue` badges, and verified with 11 comprehensive automated tests.
**Where:** `royal-nest/config/app.php`, `royal-nest/app/Services/ProfitLossService.php`, `royal-nest/app/Http/Controllers/ReportController.php`, `royal-nest/resources/js/Pages/Reports/Transactions.vue`, `royal-nest/tests/Feature/FinalProductionRefinementsTest.php`.
**Impact:** Zero regressions across all 92 tests, complete audit trail preservation, accurate P&L margins, and correct local business-day reconciliation.

---

## [2026-11-13] — Universal Voice Typing, Immutable Audit Trail, Recycle Bin Recovery, Reversal vs Refund Accounting, Super Admin Shield & Visitor Mode

**What:**
1. **Universal Voice Typing System** (`resources/js/Components/Voice/UniversalVoiceModal.vue`, `resources/js/Components/Voice/VoiceInputButton.vue`, `app/Http/Controllers/VoiceLookupController.php`):
   - Added native Web Speech API voice typing modal with Bengali (`bn-BD`) as primary default language and 1-tap English (`en-US`) toggle.
   - Built live fuzzy database lookup endpoint `GET /api/voice/lookup` for matching spoken product names, suppliers, employees, expense categories, and accounts with minimum 2-character queries.
   - Integrated touch-friendly (44px) microphone buttons across all 19 transaction entry forms (Daily Costs, Purchases, Consumables, Operating Expenses, Daily Sales, Supplier Management, Staff Directory).
   - Enforced strict user safety: spoken speech is parsed into an editable Review & Confirm Modal before any field is populated, with **zero automatic form submission**. Included manual fallback text input for noisy environments or unsupported browsers.
2. **Comprehensive Immutable Activity Log & Audit Trail** (`app/Services/AuditLogger.php`, `app/Models/AuditLog.php`, `app/Http/Controllers/AuditLogController.php`, `resources/js/Pages/AuditLogs/Index.vue`):
   - Enhanced `audit_logs` schema with `event_uuid`, `module`, `transaction_reference`, `status`, and `error_message`.
   - Prevented tampering by intercepting `updating` and `deleting` Eloquent events on `AuditLog` model with an immediate `AccessDeniedHttpException`.
   - Added dual-layer audit trail logging: automated capture for authentication, financial vouchers, inventory movements, staff management, and system administration, plus explicit `AuditLogger::logFailure()` for failed validation and unauthorized access attempts.
   - Sanitized all sensitive credentials (passwords, pin numbers, session tokens) before saving JSON diffs.
   - Built a mobile-first timeline dashboard (`/audit-logs`) with module filters, date ranges, user search, before/after JSON diff viewer modal, and streaming CSV export (`/audit-logs/export`).
3. **Deleted Record Recovery & Recycle Bin** (`app/Http/Controllers/RecycleBinController.php`, `resources/js/Pages/RecycleBin/Index.vue`, `app/Models/Product.php`, `Supplier.php`, `Employee.php`, `Category.php`):
   - Enabled soft deletes (`SoftDeletes`) on `Product`, `Supplier`, `Employee`, and `Category` models (`deleted_at` timestamp).
   - Created dedicated Recycle Bin interface (`/recycle-bin`) with responsive tabbed views for each entity type, showing deleted date and original details with 1-tap **Restore** action.
   - Enforced Super-Admin-only permanent purge safety guard: permanently destroying a record is strictly blocked whenever historical transaction dependencies exist (e.g. past purchases, stock ledger entries, sales, or attendance).
4. **Separation of Accounting Void from Actual Money Refund** (`app/Services/ReversalService.php`, `app/Http/Controllers/TransactionReversalController.php`, `resources/js/Pages/Purchases/*`, `Costs/*`, `Expenses/*`, `Sales/*`):
   - Eliminated erroneous assumptions regarding payment reversals by providing two distinct workflows:
     - **Void Mistaken Entry**: For bookkeeping/duplicate typos where no money actually moved; unwinds fictitious ledger entries, restores supplier payables/due amounts, and reverses intact inventory batches without physical cash disbursement.
     - **Actual Refund / Recovery**: For real-world cash or digital money returned; records a distinct linked financial inflow into the designated active financial account with receipt date and reference voucher.
   - **FIFO Purchase Reversal Matrix**: Reversing a purchase with 100% intact shelf stock cleanly unwinds FIFO batches and records a `purchase_return` stock ledger movement; partially consumed purchases block full invoice voiding and mandate partial shelf-stock return; fully consumed purchases strictly block voiding.
5. **Verified Super Admin Identity Shield & Visitor Read-Only Mode** (`app/Http/Controllers/UserController.php`, `app/Http/Middleware/EnsureNotVisitor.php`, `resources/js/Pages/Users/Index.vue`, `app/Models/User.php`):
   - Added `is_super_admin` flag to `users` table, securely locking User ID 1 (`admin@royalnest.com`) against deletion, deactivation, or self-demotion even across email changes.
   - Built User Management dashboard (`/users`) allowing Super Admin to invite users and create Visitor accounts.
   - Built `EnsureNotVisitor` security middleware intercepting all state-changing HTTP methods (`POST`, `PUT`, `PATCH`, `DELETE`) with HTTP 403 Forbidden, while explicitly whitelisting `POST /logout`.
   - Masked sensitive data on visitor sessions (masked employee phone numbers, hidden NID card captures, hidden action buttons).
6. **Instant Daily Cost Breakdown Summary Card** (`resources/js/Pages/Costs/Index.vue`, `app/Http/Controllers/SmartCostController.php`):
   - Added real-time financial KPI breakdown card atop Daily Cost Entry with 6 distinct metric tiles: Raw Materials, Packaging, Operating Expenses, Liability Settlements (Supplier & Salary), Gross Sales, and FIFO COGS.
   - Included interactive date selector and print-ready stylesheet for on-demand cost sheet printing.
7. **Custom Financial Account CRUD** (`app/Http/Controllers/FinancialAccountController.php`, `resources/js/Pages/Accounts/Index.vue`):
   - Added "Add Account" modal and backend `store` / `update` endpoints for creating custom financial accounts with initial balances and active/inactive status toggles.
8. **Automated Verification & Release Packaging**:
   - Created `tests/Feature/SystemWideEnhancementAndReadinessTest.php` with 9 comprehensive tests covering all new features.
   - Entire test suite: **81/81 tests (449 assertions) passing 100% green**.
   - Frontend compiled cleanly with Vite 8 (5.51s, 0 errors).
   - Generated production release package: `dist/royal_nest_production_deployment.zip` (0.58 MB, 240 files).

**Why:** Complete the production-readiness roadmap, empowering the restaurant owner with frictionless voice entry in Bengali, bulletproof audit accountability, safe deleted-item recovery, compliant reversal vs refund accounting, and secure multi-user role segregation.
**How:** Built Web Speech API components, implemented domain services (`ReversalService`, `AuditLogger`), added soft-delete recovery controller, configured `EnsureNotVisitor` middleware, and verified with end-to-end tests and automated release packaging.
**Where:** `royal-nest/app/Services/*`, `royal-nest/app/Http/Controllers/*`, `royal-nest/app/Http/Middleware/*`, `royal-nest/resources/js/*`, `royal-nest/tests/Feature/*`, `royal-nest/database/migrations/*`.
**Impact:** Zero regressions across all 81 automated tests, guaranteed ledger and FIFO integrity, full audit compliance, and an effortless mobile user experience.

---

## [2026-11-13] — Staff Shift System, Daily Purchases & Outflows Report, Multi-Bill Supplier Allocation, Configurable Financial Accounts & Secure PWA Service Worker

**What:**
1. **Restaurant Staff Shift Architecture & Shift-Aware Attendance Alerts** (`database/migrations/2026_11_13_000002_add_shifts_packaging_and_payment_allocations.php`, `app/Models/Employee.php`, `app/Models/EmployeeAttendance.php`, `app/Services/EmployeeService.php`, `app/Http/Controllers/EmployeeController.php`, `resources/js/Pages/Employees/*`, `Dashboard.vue`):
   - Added `assigned_shift` (`morning`, `evening`, `both`) to `employees` and nullable `shift` (`morning`, `evening`, `null` for legacy) to `employee_attendances` with composite unique index `(employee_id, attendance_date, shift)`.
   - Built shift-aware attendance alert engine (`EmployeeService::getMissingAttendanceAlerts`) that detects exact employee and unrecorded shift (`Morning Shift` vs `Evening / Night Shift`), surfacing actionable cards on Dashboard and Staff Directory with 1-tap `Present` / `Half-Day` / `Absent` quick buttons. Alerts clear in real-time as soon as the shift is recorded.
   - Preserved salary calculation rules without arbitrary cuts: Daily wage calculation counts distinct calendar dates attended (`$datesGrouped = $monthAttendances->groupBy(...)`), guaranteeing workers assigned to both shifts receive full agreed daily pay without duplicate charges or arbitrary 0.5 cuts.
2. **Dedicated Salary Payout Shortcut in Cost Entry with Designation Display** (`resources/js/Pages/Costs/Index.vue`, `app/Http/Controllers/SmartCostController.php`):
   - Selecting an employee in Cost Entry instantly displays their Designation (`role`), department, and real-time unpaid salary payable.
   - Payout transactions deduct from the selected active Financial Account and record into `SalaryPayment` with **zero duplicate operating expenses in P&L**.
3. **Daily Purchases, Expenses & Cash Outflow Transactions Reporting** (`app/Http/Controllers/ReportController.php`, `resources/js/Pages/Reports/Transactions.vue`, `resources/js/Layouts/AppLayout.vue`):
   - Created `/reports/transactions` route with 3 responsive mobile-first tabs:
     - **Daily Purchases**: Line-by-line item audit (Item, Qty, Unit, Unit Price, Line Total, Supplier, Invoice #, Payment Status, and Category Badge).
     - **Daily Expenses & Outflows**: Strict accounting separation distinguishing True P&L Operating Expenses (utilities, rent, maintenance) from Balance Sheet Liability Settlements (supplier due payouts, salary disbursements) and Advances.
     - **Combined Stream**: Unified chronological transaction ledger with 1-tap category pills (`Raw Materials`, `Packaging`, `Consumables`, `Operating Expenses`, `Supplier Due`, `Salary`).
4. **Strict Category Separation for Packaging Materials** (`categories.type = 'packaging'`, `products.product_type = 'packaging'`):
   - Created first-class `Packaging Materials` category and product type, strictly separated from raw food ingredients and general consumables.
5. **Multi-Bill & Partial-Bill Supplier Payment Allocation** (`app/Models/SupplierPaymentAllocation.php`, `app/Services/SupplierService.php`, `Costs/Index.vue`, `SupplierController.php`):
   - Created `supplier_payment_allocations` table linking payments to individual purchase bills.
   - Enforces bill outstanding limits (cannot allocate more than remaining bill due), updates bill payment statuses (`paid`, `partial`, `due`), and automatically falls back to general FIFO settlement when allocations are unspecified.
6. **Configurable Financial Accounts & Active Guard** (`app/Services/FinancialAccountService.php`, `app/Http/Controllers/FinancialAccountController.php`, `resources/js/Pages/Accounts/Index.vue`):
   - Added `POST /accounts/{account}/toggle-status` enabling Admin to activate/deactivate accounts (`Main Cash`, `Bank`, `bKash`, `Nagad`).
   - Inactive accounts are hidden from transaction dropdowns and strictly rejected by backend transaction methods (`deposit`, `withdraw`, `transfer`), while historical transaction ledgers remain intact.
7. **Secure PWA Service Worker & Compliant Manifest** (`public/sw.js`, `public/manifest.json`, `resources/views/app.blade.php`):
   - Created `public/sw.js` with static cache versioning (`royalnest-static-v1`) for hashed assets and icons.
   - Strictly enforces zero-cache policy for dynamic documents, navigation requests, API responses, and all authenticated ERP routes (`/api/*`, `/reports/*`, `/sales*`, `/employees*`, `/costs*`, `/accounts*`, `/calculator*`, etc.), preventing any exposure of private financial or session data.
   - Updated `manifest.json` with separate `any` and `maskable` icon definitions and standalone app display.
8. **Automated Verification & Production Build**:
   - Created `tests/Feature/ShiftAttendanceReportsAndAccountsTest.php` with 8 comprehensive automated tests.
   - Entire test suite: **72/72 tests (399 assertions) passing 100% green**.
   - Vite 8 compiled all 55 frontend bundles in 14.48 seconds with 0 errors.
   - Regenerated release bundle: `dist/royal_nest_production_deployment.zip` (0.51 MB, 221 files).

**Why:** Satisfy all approved critical requirements for mobile-first usability, staff shift scheduling, attendance accountability, accurate financial separation between P&L and balance sheet outflows, multi-bill supplier settlements, and an installable, secure PWA experience.
**How:** Implemented database migrations, updated Eloquent models, enhanced domain services, created `Transactions.vue`, refined `Costs/Index.vue` and `Accounts/Index.vue`, configured `sw.js`, and verified with PHPUnit and Vite.
**Where:** `royal-nest/app/Http/Controllers/*`, `royal-nest/app/Services/*`, `royal-nest/app/Models/*`, `royal-nest/resources/js/Pages/*`, `royal-nest/public/sw.js`, `royal-nest/public/manifest.json`, `royal-nest/tests/Feature/ShiftAttendanceReportsAndAccountsTest.php`.
**Impact:** Total alignment with business requirements, zero regressions across all 72 tests, rock-solid accounting integrity, and an app-like mobile experience.

---

## [2026-11-13] — Mobile-First PWA UI Overhaul, Interactive Daily P&L Calculator, Held Salary Deposit & 1-Tap Worker Payouts

**What:**
1. **Installable Mobile Progressive Web App (PWA) & Bottom Navigation** (`public/manifest.json`, `resources/views/app.blade.php`, `resources/js/Layouts/AppLayout.vue`):
   - Created `public/manifest.json` (`display: standalone`) and generated home screen icons (`192x192` and `512x512`).
   - Added iOS & Android PWA meta tags (`apple-mobile-web-app-capable`, `viewport-fit=cover, user-scalable=no`).
   - Built a fixed mobile bottom navigation bar (`Home`, `Daily Costs`, `P&L Calculator`, `Staff`, `Menu`) and a slide-up mobile drawer sheet for all secondary modules.
2. **Interactive Daily Profit & Loss Calculator** (`app/Http/Controllers/ProfitCalculatorController.php`, `resources/js/Pages/Calculator/Daily.vue`):
   - Built a dedicated mobile-first calculator screen (`/calculator`) where the owner/manager enters today's sales breakdown and raw materials used today (e.g., 4 kg Beef, 2 L Oil).
   - Automatically fetches each product's active FIFO purchase batch price (`StockBatch::where('is_exhausted', false)`), computes live cost of goods sold (COGS), adds today's accrued staff salary and operating expenses, and displays live Gross Profit, True Net Profit, and Net Margin %.
   - One-click **Save & Sync** atomically deducts FIFO inventory batches (`ConsumptionService::recordConsumption`) and reconciles `DailySale`.
3. **Staff Department Categorization, Attendance Missing Reminder & Held Salary (Security Deposit / জামানত)** (`database/migrations/2026_11_13_000001_enhance_employees_with_held_salary_and_departments.php`, `app/Services/EmployeeService.php`, `Employees/Index.vue`, `Employees/Profile.vue`):
   - Added `department` (`kitchen`, `service`, `cleaning`, `cashier`, `management`), `tags`, `held_salary_days`, and `held_salary_amount` to `employees`.
   - Implemented the held security deposit formula: $\text{Held Deposit} = \text{held\_salary\_amount} > 0 \ ? \ \text{held\_salary\_amount} : \text{round}((\text{agreed\_salary} / 30) \times \text{held\_salary\_days}, 2)$, subtracted from net disbursable salary (`remaining_payable`).
   - Added global and per-page attendance missing alert banner when any active employee's attendance has not been recorded for the current business day.
4. **1-Tap Worker Salary & Cash Advance Shortcut in Daily Costs** (`resources/js/Pages/Costs/Index.vue`):
   - Added 1-tap quick action pills in Daily Cost Entry (`দৈনিক মজুরি প্রদান` & `নগদ অগ্রিম প্রদান`) that auto-populate the selected worker's daily wage rate and default to the Cash Drawer account.
5. **Automated Verification**:
   - Created `tests/Feature/MobileCalculatorAndStaffHoldTest.php` covering employee department & held salary creation, initial advance deduction, security deposit formula, unmarked attendance alerts, Daily P&L Calculator atomic FIFO deduction, and fast worker payouts.
   - All 6 tests (26+ assertions) in `MobileCalculatorAndStaffHoldTest` passed 100% green; Vite production build completed with 0 errors.

**Why:** Transform the system into an effortless, thumb-friendly mobile web app that can be added to the phone's home screen, provide a straightforward daily raw material P&L calculator without arbitrary multi-day amortization, enforce staff attendance reminders, and support restaurant security deposits (held salary) and fast worker cash payouts.
**How:** Implemented PWA manifest and layout shell, created `ProfitCalculatorController` and `Calculator/Daily.vue`, extended `Employee` schema and `EmployeeService`, enhanced `Costs/Index.vue`, and verified with PHPUnit and Vite.
**Where:** `royal-nest/app/Http/Controllers/ProfitCalculatorController.php`, `royal-nest/app/Http/Controllers/EmployeeController.php`, `royal-nest/app/Services/EmployeeService.php`, `royal-nest/resources/js/Layouts/AppLayout.vue`, `royal-nest/resources/js/Pages/Calculator/Daily.vue`, `royal-nest/resources/js/Pages/Employees/*`, `royal-nest/resources/js/Pages/Costs/Index.vue`, `royal-nest/tests/Feature/MobileCalculatorAndStaffHoldTest.php`.
**Impact:** Complete mobile-first experience, instant daily profit calculation from actual meat/oil usage, accurate staff security deposit accounting, and zero interference with parent Saba POS repository.

---

## [2026-11-12] — Complete System Re-Analysis & Logic Perfection: Wastage FIFO, Stock Count Batch Sync, Daily Sales Auto-Deposit & Supplier Bill Status

**What:**
1. **Wastage Multi-Batch FIFO Traversal** (`app/Http/Controllers/WastageController.php`):
   - Implemented sequential FIFO batch traversal algorithm inside a database transaction with `Product::lockForUpdate()`.
   - When wastage quantity spans across multiple purchase batches, each batch's remaining quantity is chronologically depleted, updating `remaining_quantity` and `is_exhausted`.
   - Calculates true weighted average cost across all consumed batches and logs separate immutable `StockLedger` movements linked to each `stockBatchId`.
2. **Physical Stock Count FIFO Batch Synchronization** (`app/Http/Controllers/StockCountController.php`):
   - Surplus items (`counted_quantity > system_quantity`): Generates a dedicated `COUNT-ADJ-YYYYMMDD-product_id` `StockBatch` and logs an `adjustment_in` movement, allowing subsequent kitchen usage to consume from this batch in FIFO order.
   - Deficit items (`counted_quantity < system_quantity`): Sequentially depletes oldest active purchase batches in FIFO order, keeping `StockBatch` remaining quantities strictly identical to on-hand shelf stock.
3. **Daily Sales Multi-Account Financial Cash Intake** (`app/Http/Controllers/DailySalesController.php`):
   - Injected `FinancialAccountService` into `DailySalesController`.
   - Automatically deposits reconciled daily sales into their corresponding active financial accounts:
     - `cash_sales` $\rightarrow$ Account of type `cash` ("Cash Drawer")
     - `bkash_sales` $\rightarrow$ Account of type `bkash` ("bKash Merchant")
     - `nagad_sales` $\rightarrow$ Account of type `nagad` ("Nagad Merchant")
     - `card_bank_sales` $\rightarrow$ Account of type `bank` ("BRAC Bank Current")
   - Enforced idempotent transaction cleanup: Editing or re-submitting daily sales on the same date atomically rolls back and re-deposits funds without double-counting.
4. **Supplier Bill Payment Status & Dynamic FIFO Allocation** (`app/Services/SupplierService.php` & `Profile.vue`):
   - Implemented dynamic FIFO payment allocation in `getSupplierStatement()`, calculating each individual bill's cumulative paid amount, remaining due, and status (`Paid`, `Partially Paid`, `Unpaid`).
   - Statement running due balance remains 100% mathematically consistent at every row ($\text{Due} = \text{Opening} + \sum \text{Bills} - \sum \text{Vouchers}$).
   - Added visual status badges on the Supplier Profile transaction ledger to clearly distinguish between paid and unpaid invoices.
5. **Comprehensive Verification**:
   - Created `tests/Feature/SystemLogicPerfectionTest.php` testing multi-batch wastage traversal, physical count surplus/deficit batch sync, daily sales multi-account deposits with update idempotency, and supplier statement per-bill status allocation.
   - Full test suite: **29/29 tests (158 assertions) passing 100% green**.
   - Vite 8 compiled all 47 frontend bundles in 6.13 seconds with 0 errors.
   - Production archive regenerated: `dist/royal_nest_production_deployment.zip` (0.40 MB).

**Why:** Ensure 100% accuracy and mathematical integrity across all inventory lots, cash accounts, and supplier purchase bills, eliminating any potential discrepancies in live restaurant operations.
**How:** Refactored controller logic with row-level locks, integrated financial account deposits with transaction rollback protection, implemented FIFO batch traversal in wastage and stock counts, and added automated feature tests.
**Where:** `royal-nest/app/Http/Controllers/WastageController.php`, `royal-nest/app/Http/Controllers/StockCountController.php`, `royal-nest/app/Http/Controllers/DailySalesController.php`, `royal-nest/app/Services/SupplierService.php`, `royal-nest/resources/js/Pages/Suppliers/Profile.vue`, `royal-nest/tests/Feature/SystemLogicPerfectionTest.php`.
**Impact:** Cash Drawer balance always reflects actual restaurant revenue; stock batches stay in exact parity with physical inventory; supplier invoices display precise payment statuses; site stability remains rock solid.

---

## [2026-11-12] — Full Supplier Management, Staff Camera Capture, Bulk Attendance & Production Hardening

**What:**
1. **Two Distinct Supplier Modules (Never Mixed)**:
   - Built **Supplier Directory** (`/suppliers/directory`): Central catalog of all vendors (meat, spices, dairy, produce, packaging) with business locations, contacts, status (`potential`, `active`, `inactive`, `archived`), and multi-product agreed supply rates (`supplier_products`). Adding a vendor here does not affect financial balances.
   - Built **Active Supplier Management** (`/suppliers/active`): Strictly active vendors currently supplying the restaurant. Tracks live **Outstanding Payable Due** (`Confirmed Bills - Payments - Advances`), last purchase date, and last payment date.
   - Built **Dedicated Supplier Profile & Statements** (`/suppliers/{id}/profile`): Chronological bill history with item lists, payment vouchers, advance vouchers, running due balance at every row, and print-ready statement vouchers.
   - Implemented `SupplierAdvance` table and workflow: Records vendor advance payments, reducing upcoming payables without entering P&L as a double expense.
2. **Staff Management, Live Camera Photo Snap & NID Verification**:
   - Implemented auto-generated unique Employee IDs (`EMP-001`, `EMP-002`).
   - Built `CameraCapture.vue`: Uses browser `getUserMedia` to stream live webcam or mobile camera, snap photos, compress client-side via HTML5 canvas, with automatic fallback to file picker (`capture="environment"` for mobile cameras).
   - Added separate dedicated capture buttons for **Staff Photo** and **NID Card Photo** with instant previews and modal zoom.
   - Built **Dedicated Employee Profile** (`/employees/{id}/profile`): Tabbed view for Personal Info & ID Docs, Monthly Attendance Calendar, Accrued Salary vs Paid Breakdown, Advances Ledger, and Salary Vouchers.
   - Built **Bulk Attendance Entry Grid** (`/employees/attendance/bulk`): 1-click batch selection (`Present`, `Half-day`, `Leave`, `Absent`) for the entire active staff team on any date with duplicate date prevention.
   - Built **Staff Advance & Deduction Recovery**: Advance payouts outflow from financial accounts into an advance liability; monthly salary payouts can deduct active advances with atomic balance reduction and zero double-counting in P&L.
3. **Cross-Module Dashboard Integration**:
   - Enhanced executive dashboard with live summary cards for Active Suppliers, Total Supplier Payables, Recent Dealer Bills, Today's Staff Attendance Breakdown, and Outstanding Staff Advances.
4. **Production Security & Authentication**:
   - Implemented `/login` authentication wall with rate limiting and secure database session management.
   - Wrapped all operational application routes in `auth` middleware with role checks.
5. **Bilingual Localization (বাংলা & English)**:
   - Comprehensive translation dictionaries (`bn.json` & `en.json`) with natural Bangladeshi restaurant terms.
   - Instant reactive language toggle in navbar persisted across sessions via `localStorage`.
6. **Automated Verification**:
   - Created `tests/Feature/SupplierAndStaffManagementTest.php` testing Directory vs Active separation, due vouchers, Cash Drawer deductions, Employee ID generation, single-date attendance protection, and advance recovery deductions.
   - 25 automated PHPUnit tests with 120 assertions running against native MySQL 8.4 engine (`royalnest_erp_test`), passing 100% green.
   - Vite 8 compiling all production bundles in 4.88 seconds with 0 errors.
   - Created `package_production.py` generating live release archive `dist/royal_nest_production_deployment.zip`.

**Why:** Fulfill user requirements for complete, functional Supplier Management (Directory vs Active), Staff Management with live camera snapshot and NID documentation, authentic native Bengali localization, and production readiness.
**How:** Implemented database migrations, Eloquent models, domain services (`SupplierService`, `EmployeeService`), Vue 3 Inertia pages, Vite bundle compilation, and comprehensive PHPUnit test suite.
**Where:** `royal-nest/app/*`, `royal-nest/database/migrations/*`, `royal-nest/resources/js/*`, `royal-nest/tests/*`, `royal-nest/docs/*`, `royal-nest/package_production.py`.
**Impact:** 100% zero interference with parent Saba POS project; Royal Nest is 100% verified and production-ready for live deployment.

---

## [2026-11-11] — Transition to FIFO (First-In, First-Out) Batch Costing Engine

**What:**
1. **FIFO Batch / Lot Database Schema**:
   - Created `stock_batches` table tracking discrete purchase batches (`product_id`, `purchase_item_id`, `batch_no`, `received_date`, `initial_quantity`, `remaining_quantity`, `unit_cost`, `is_exhausted`).
   - Added foreign key `stock_batch_id` to `stock_ledger` and `consumption_items`.
2. **Batch Generation in Purchases & Opening Stock**:
   - Updated `PurchaseService` and `StockLedgerService` to create a dedicated `StockBatch` for every incoming purchase item and initial opening stock, preserving the exact purchase price per base unit without price blending.
3. **Automatic FIFO Dispatch Engine**:
   - Upgraded `ConsumptionService` with an automatic FIFO traversal algorithm: orders active batches chronologically (`received_date ASC, id ASC`) and deducts from the oldest available batch first until exhausted.
   - Slices multi-batch consumption entries cleanly in `stock_ledger` and `consumption_items` with exact batch cost tracking.
4. **FIFO Wastage Deduction**:
   - Updated `WastageController` to draw from oldest active FIFO batches so spoilage costs reflect the actual historical cost of the oldest batch.
5. **Interactive Frontend Updates**:
   - Updated `Products/Index.vue` with an "Active FIFO Batches (Price by Lot)" breakdown and exact batch inventory valuation.
   - Updated `Ledger/Index.vue` displaying the batch tag on every stock movement line.
   - Recompiled Vite production build (`npm run build`).
6. **Automated Test Suite**:
   - Updated Scenario 3 in `PrdMandatoryScenariosTest.php` to prove that previous stock retains its price (10 kg @ ৳100) and future stock retains its price (10 kg @ ৳120), consuming 5 kg @ ৳100 (৳500), and consuming 7 kg across both batches (৳740).
   - All 20 tests (91 assertions) passing 100% green.

**Why:** Replace moving weighted-average cost blending with First-In, First-Out (FIFO) lot costing as requested by user ("the previous ones will have their price and the future ones will have their price").
**How:** Implemented relational migration `create_stock_batches_table`, `StockBatch` Eloquent model, FIFO traversal in `ConsumptionService`, UI enhancements, and updated PHPUnit test suite.
**Where:** `royal-nest/database/migrations/*`, `royal-nest/app/*`, `royal-nest/resources/js/*`, `royal-nest/tests/*`, `royal-nest/docs/*`.
**Impact:** Exact historical costing for Royal Nest; 100% zero interference with parent Saba POS project.

---

## [2026-11-11] — Implementation of Royal Nest Cost, Inventory, Income & Profit Management System (PRD v1.1)

**What:**
1. **Perpetual Inventory Engine & Unit Conversions**:
   - Implemented relational database schema with `products`, `product_unit_conversions`, `stock_ledger`, `purchases`, `purchase_items`, `consumptions`, `consumption_items`, `consumable_allocations`, `wastages`, `stock_counts`, and `stock_count_items`.
   - Created `StockLedgerService` enforcing atomic row locking (`lockForUpdate`), strict negative stock prevention, and perpetual movement recording on all stock receipts.
   - Built configurable product-specific unit conversions (e.g. 1 sack = 25 kg, 1 kg = 1000 g, 1 box = 1 pcs).
2. **Purchase Receiving & Direct "Purchase & Consume" Workflow**:
   - Built `PurchaseService` supporting standard stock receipts as well as the specialized direct "Purchase & Consume" workflow (PRD Section 6), creating two linked movements (+Q receipt and -Q consumption) resulting in 0 ending stock and single consumption cost recognition with 0 duplicate expense.
   - Integrated supplier payable balance tracking and financial account withdrawals.
3. **Manual Daily Ingredient Consumption & Negative Stock Blocker**:
   - Created `ConsumptionService` and interactive Vue modal (`Consumption/Index.vue`) validating available on-hand stock and blocking negative stock deductions by default.
4. **Multi-Day Consumables Cost Allocation**:
   - Built `ConsumableAllocationService` and UI (`Consumables/Index.vue`) to amortize multi-day consumable purchases (e.g. ৳120 tissue box over 4 days = ৳30/day) directly into daily P&L without upfront cost spikes.
5. **Operating Expenses & Financial Accounts**:
   - Built `ExpenseController` categorizing utility bills, rent, maintenance, cleaning, and marketing.
   - Built `FinancialAccountService` and `Accounts/Index.vue` tracking Cash Drawer, bKash Merchant, Nagad Merchant, and BRAC Bank, with internal account transfers having 0 effect on P&L.
6. **Staff Attendance & Payroll Engine**:
   - Built `EmployeeController` and `Employees/Index.vue` tracking daily attendance, accrued monthly/daily salary costs, and cash salary payments without duplicate expense counting.
7. **Daily Sales & Discrepancy Tracking**:
   - Built `DailySalesController` and `Sales/Daily.vue` recording daily sales with Cash, bKash, Nagad, Card/Bank breakdown, with discrepancy flagging.
8. **True Profit & Loss Reporting**:
   - Built `ProfitLossService` and `Reports/ProfitLoss.vue` generating accurate Daily and Date Range Income & Expenditure statements breakdown (Gross Sales, Cost of Materials/COGS, Gross Profit, Operating Costs, True Net Profit, and Net Margin %).
9. **Interactive Vue 3 Frontend & Production Build**:
   - Built modern dark Tailwind UI matching Royal Nest branding with responsive top navigation and 13 full-featured Vue pages.
   - Vite 8 production build compiled cleanly (`npm run build`).
10. **Automated Verification**:
    - Created `tests/Feature/PrdMandatoryScenariosTest.php` testing all 12 mandatory PRD scenarios against native MySQL 8.4 engine.
    - Verified all 20 tests (85 assertions) passing 100% green.

**Why:** Completely fulfill the user's updated PRD Version 1.1 ("Royal Nest Café & Restaurant Cost, Inventory, Income & Profit Management System") with production-grade accounting, perpetual stock control, and financial reporting.
**How:** Implemented Laravel 12 controllers, Eloquent models, domain services, seeders, Inertia Vue 3 pages, and comprehensive PHPUnit test suites.
**Where:** `royal-nest/app/*`, `royal-nest/database/*`, `royal-nest/resources/js/*`, `royal-nest/routes/web.php`, `royal-nest/tests/*`, `royal-nest/docs/*`.
**Impact:** 100% zero git or code interference with parent Saba POS project; Royal Nest system is fully operational and verified.

---

## [2026-11-11] — Phase 0: Complete Foundation, Double-Entry GL, RBAC & Subfolder Isolation

**What:**
1. **Subfolder & Git Isolation**: Created `royal-nest/` subfolder, added `/royal-nest/` to Saba POS `.gitignore` to guarantee 0% git mixing, and initialized an independent git repository with initial commit.
2. **Laravel 12 + Inertia.js + Vue 3 Scaffold**: Installed and configured Laravel 12 framework, Inertia Vue 3 adapter, Tailwind CSS, Lucide icons, and Vite asset compiler (`npm run build` passing with 0 errors).
3. **Dedicated MySQL 8.4 Database**: Connected to dedicated local MySQL 8.4 database `royalnest_erp` and automated test database `royalnest_erp_test`.
4. **Foundation Restaurant Schema**:
   - `branches` & `business_settings` (multi-branch ready, BDT currency, Asia/Dhaka timezone, business-day boundary).
   - `roles`, `permissions`, `user_roles`, and extended `users` table with branch assignment, 4-digit security PIN, and 2FA TOTP support.
   - `audit_logs` table with actor, before/after JSON payloads, IP, and reason tracking.
5. **Harvested Core Services**: Ported and adapted `AuditLogger` from Saba POS for branch and restaurant entity auditing.

**Why:** Establish a rock-solid, production-grade foundation specifically designed for Royal Nest restaurant operations without carrying any SaaS retail technical debt.
**How:** Implemented migrations, models, seeders, Vite build pipeline, and comprehensive PHPUnit test suites.
**Where:** `royal-nest/`, `royal-nest/database/*`, `royal-nest/app/*`, `royal-nest/tests/*`, `royal-nest/docs/*`.
**Impact:** Zero interference with Saba POS; clean restaurant foundation ready.
