# Royal Nest Café & Restaurant — Architecture & System Specification
## Cost, Inventory, Income & Profit Management System (PRD v1.1)

*This file describes the current state of the Royal Nest system. Update it in place whenever something changes — this is not a history, see CHANGELOG.md for history.*

## 1. Overview & Core Objective
Royal Nest Café & Restaurant is a dedicated financial and inventory management system designed to track:
- Daily sales and payment method reconciliation (Cash, bKash, Nagad, Card/Bank).
- Raw materials and consumable inventory under a **Perpetual Inventory System**.
- Goods receiving with configurable product-specific unit conversions and **Moving Weighted-Average Costing**.
- Actual end-of-day kitchen ingredient consumption with **Negative Stock Prevention**.
- Direct **Purchase & Consume** atomic workflow (stock receipt $+Q$ and consumption $-Q$, 0 ending stock, 0 duplicate expense).
- Multi-day **Consumables Cost Allocation** (amortizing items like ৳120 tissue box over 4 days = ৳30/day without upfront P&L expense spikes).
- Daily operating expenses (rent, gas, electricity, maintenance, etc.) kept strictly separate from inventory purchases.
- Employee attendance, accrued salary costs vs actual cash payments (tracking unpaid payroll liabilities without double counting).
- Financial accounts (Cash Drawer, bKash, Nagad, Bank) with neutral internal funds transfers.
- Accurate **Daily, Monthly, and Date Range Profit & Loss Reports**.

*Strict Boundaries*: As defined by PRD v1.1 Section 24, Kitchen Display System (KDS), dining table maps, delivery fleets, CRM, AI forecasting, and automatic recipe-based deductions are explicitly out of scope. Stock deduction is based strictly on manually entered actual usage.

---

## 2. Tech Stack & Environment
- **Backend**: Laravel 12 (PHP 8.3.33)
- **Frontend**: Vue 3 (Composition API), Inertia.js, Tailwind CSS v4, Lucide Icons
- **Database Engine**: MySQL 8.4 (InnoDB transactions with row-level locks, `DECIMAL(12, 4)` inventory precision, `DECIMAL(12, 2)` monetary values)
- **Timezone & Locale**: BDT (`৳`), `Asia/Dhaka` timezone
- **Repository Isolation**: `d:\Projects\Saba POS\royal-nest` (completely isolated git repository, ignored by parent `.gitignore`)

---

## 3. Core Database Tables & Data Model
1. **Foundation & RBAC**:
   - `branches`: Multi-branch ready with branch currency, phone, and timezone.
   - `roles`, `permissions`, `user_roles`, `users`: Role-based access control (`owner`, `manager`, `cashier`, `staff`).
   - `audit_logs`: Immutable tracking of user actions, before/after payloads, and IP addresses.
2. **Products & Unit Conversions**:
   - `categories`: Classified into `raw_material`, `consumable`, `other_stock`.
   - `suppliers`: Supplier directory with current payable balances.
   - `products`: Product master storing `current_stock` and `cost_price` (moving weighted-average) always in `base_unit` (`g`, `kg`, `ml`, `L`, `pcs`).
   - `product_unit_conversions`: Product-specific conversion matrix (e.g. 1 sack = 25 kg, 1 kg = 1000 g).
3. **Perpetual Stock Ledger & Movements**:
   - `purchases` & `purchase_items`: Confirmed stock receipts and direct Purchase & Consume transactions.
   - `stock_ledger`: Immutable chronological movement log (`opening_stock`, `purchase_receipt`, `consumption`, `wastage`, `adjustment_in`, `adjustment_out`, `purchase_return`). Never overwrites previous rows.
   - `consumptions` & `consumption_items`: Daily ingredient actual usage records.
   - `consumable_allocations`: Multi-day consumable amortized allocation schedules.
   - `wastages`: Spoilage, burnt, or expired item records with reasons.
   - `stock_counts` & `stock_count_items`: Physical count vs system balance with discrepancy tracking.
4. **Daily Sales & Financial Accounts**:
   - `daily_sales`: Total gross sales with breakdown into Cash, bKash, Nagad, Card/Bank, Other, and discrepancy amount.
   - `operating_expenses`: Daily operating costs (electricity, gas, water, rent, transport, repairs, cleaning, marketing, other).
   - `financial_accounts`: Accounts tracking Cash Drawer, Bank, bKash, Nagad with opening and current balances.
   - `account_transactions`: Deposits, withdrawals, and internal transfers (`transfer_in`, `transfer_out`).
5. **Staff & Payroll**:
   - `employees`: Staff records with monthly/daily agreed salary.
   - `employee_attendances`: Daily attendance records (`present`, `absent`, `half_day`, `leave`).
   - `salary_payments`: Cash disbursals reducing accrued payroll liabilities.

---

## 4. Business Services Layer (`app/Services/`)
- `StockLedgerService`:
  - Enforces atomic database locks (`Product::lockForUpdate()`).
  - Guards against negative stock by default (`ValidationException`).
  - Recalculates moving weighted-average cost on stock additions:
    $$\text{New Cost} = \frac{(\text{Current Qty} \times \text{Current Cost}) + (\text{Incoming Qty} \times \text{Incoming Cost})}{\text{Current Qty} + \text{Incoming Qty}}$$
  - Appends immutable `StockLedger` movements.
- `PurchaseService`:
  - Converts purchased units to base units via product-specific multipliers.
  - Posts stock receipt movement to ledger.
  - Handles the direct **Purchase & Consume** workflow: immediately executes a linked consumption movement of the same quantity, resulting in 0 remaining stock and 1 single consumption cost in P&L.
  - Updates supplier payable liability and deducts payments from financial accounts.
- `ConsumptionService`:
  - Validates stock availability for every ingredient.
  - Deducts quantity from on-hand stock and writes consumption records to `stock_ledger` and `consumption_items`.
- `ConsumableAllocationService`:
  - Amortizes multi-day items (e.g. ৳120 tissue over 4 days = ৳30/day) and provides daily recognized amortization.
- `FinancialAccountService`:
  - Records deposits and withdrawals.
  - Executes internal account-to-account transfers with the immutable rule: **Internal transfers have 0 effect on Profit & Loss**.
- `ProfitLossService`:
  - Computes True Profit & Loss:
    - $\text{Gross Profit} = \text{Total Sales} - (\text{Ingredient Consumption Cost} + \text{Consumables Allocated Cost})$
    - $\text{Total Operating Cost} = \text{Operating Expenses} + \text{Staff Salary Accrual} + \text{Wastage Cost}$
    - $\text{True Net Profit} = \text{Gross Profit} - \text{Total Operating Cost}$
    - $\text{Net Profit Margin \%} = (\text{Net Profit} / \text{Total Sales}) \times 100$

---

## 5. Web Interface & Route Structure
All routes render Vue 3 components via Inertia.js with Tailwind CSS v4:
- `/`: Executive Dashboard (`Dashboard.vue`)
- `/sales`: Daily Sales Entry & Breakdown (`Sales/Daily.vue`)
- `/products`: Inventory Products & Conversions (`Products/Index.vue`)
- `/purchases`: Stock Receiving & Purchase & Consume (`Purchases/Index.vue`)
- `/consumption`: End-of-Day Usage Entry (`Consumption/Index.vue`)
- `/consumables`: Amortization Scheduler (`Consumables/Index.vue`)
- `/ledger`: Perpetual Stock Movement Ledger (`Ledger/Index.vue`)
- `/expenses`: Operating Expenses Categorization (`Expenses/Index.vue`)
- `/wastage`: Spoilage & Wastage Entry (`Wastage/Index.vue`)
- `/stock-counts`: Physical Stock Count Audit (`StockCounts/Index.vue`)
- `/accounts`: Financial Accounts & Transfers (`Accounts/Index.vue`)
- `/employees`: Attendance & Payroll (`Employees/Index.vue`)
- `/reports/profit-loss`: True Profit & Loss Statement (`Reports/ProfitLoss.vue`)

---

## 6. Automated Verification Status
- **Test Coverage**: 20 automated PHPUnit tests with 85 assertions running against native MySQL 8.4 engine (`royalnest_erp_test`), passing 100% green.
- **PRD Scenarios Verified**: All 12 mandatory scenarios from PRD v1.1 Section 23 tested and verified in `tests/Feature/PrdMandatoryScenariosTest.php`.
- **Frontend Compiler**: Vite 8 compiling 26 production bundles with zero errors.
