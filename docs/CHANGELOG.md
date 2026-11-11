# Changelog — Royal Nest Restaurant ERP

All notable changes to this project will be documented in this file.

## [2026-11-11] — Phase 0: Complete Foundation, Double-Entry GL, RBAC & Subfolder Isolation

**What:**
1. **Subfolder & Git Isolation**: Created `royal-nest/` subfolder, added `/royal-nest/` to Saba POS `.gitignore` to guarantee 0% git mixing, and initialized an independent git repository with initial commit.
2. **Laravel 12 + Inertia.js + Vue 3 Scaffold**: Installed and configured Laravel 12 framework, Inertia Vue 3 adapter, Tailwind CSS, Lucide icons, and Vite asset compiler (`npm run build` passing with 0 errors).
3. **Dedicated MySQL 8.4 Database**: Connected to dedicated local MySQL 8.4 database `royalnest_erp` and automated test database `royalnest_erp_test`.
4. **Foundation Restaurant Schema**:
   - `branches` & `business_settings` (multi-branch ready, BDT currency, Asia/Dhaka timezone, business-day boundary).
   - `roles`, `permissions`, `user_roles`, and extended `users` table with branch assignment, 4-digit security PIN, and 2FA TOTP support.
   - `audit_logs` table with actor, before/after JSON payloads, IP, and reason tracking.
5. **Double-Entry General Ledger Core**:
   - `chart_of_accounts`: 34 standard restaurant accounts across Assets, Liabilities, Equity, Revenue, Contra-revenue, COGS, and Expenses.
   - `payment_accounts`: 6 linked payment accounts (Main Cash Drawer, Kitchen Petty Cash, BRAC Bank, bKash Merchant, Nagad Merchant, Card Clearing).
   - `fiscal_periods`, `journals`, and `journal_lines` supporting balanced debit/credit transactional postings.
6. **Harvested Core Services**: Ported and adapted `AuditLogger` from Saba POS for branch and restaurant entity auditing.
7. **Automated Test Suite**: Authored and passed 7 automated tests (35 assertions) in `tests/Feature/FoundationDatabaseTest.php` and `WelcomeTest.php` against native MySQL 8.4 engine.

**Why:** Establish a rock-solid, production-grade foundation specifically designed for Royal Nest restaurant operations and double-entry accounting without carrying any SaaS retail technical debt.
**How:** Implemented migrations, models, seeders, Vite build pipeline, and comprehensive PHPUnit test suites.
**Where:** `royal-nest/`, `royal-nest/database/*`, `royal-nest/app/*`, `royal-nest/tests/*`, `royal-nest/docs/*`.
**Impact:** Zero interference with Saba POS; clean restaurant ERP foundation ready for Phase 1 (Menu, Units & Recipe BOM).

