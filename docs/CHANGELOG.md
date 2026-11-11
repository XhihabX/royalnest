# Changelog — Royal Nest Restaurant ERP

All notable changes to this project will be documented in this file.

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
