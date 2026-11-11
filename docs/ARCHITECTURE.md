# Royal Nest Café & Restaurant — Architecture & System Specification

*This file describes the current state of the Royal Nest Restaurant ERP system. Update it in place whenever something changes — this is not a log, see CHANGELOG.md for history.*

## Overview
**Royal Nest Restaurant ERP Master** is an integrated, web-based restaurant operations, inventory, recipe costing, purchasing, and double-entry accounting management system built to PRD Version 2.0 specifications.

The platform links Front-of-House (Dine-In, Takeaway, Delivery, Table Management) with Kitchen Production (KDS, Station Routing), Purchasing (Requisitions, PO, GRN 3-Way Matching), Perpetual Inventory (BOM Recipe Explosions, Unit Conversions, Batch FEFO, Consumables Amortization), Staff Shifts & Payroll, and double-entry financial accounting.

## Tech Stack
- **Backend**: Laravel 11/12 (PHP 8.3+)
- **Frontend**: Vue 3 (Composition API), Inertia.js, Tailwind CSS
- **Database Engine**: MySQL 8.x (InnoDB transactional row locks, `DECIMAL(12, 4)` inventory precision, `DECIMAL(12, 2)` monetary values)
- **Timezone & Locale**: BDT (`৳`), `Asia/Dhaka` timezone with restaurant `business_day` boundary support
- **Compliance & Local Gateways**: NBR Mushak-6.3 tax invoicing, bKash / Nagad MFS counter verification & webhook auto-matching

## Folder Structure
- `app/Http/Controllers/`: Backend controllers handling FOH, KDS, Inventory, Recipes, Procurement, Accounting, HR
- `app/Models/`: Eloquent ORM models
- `app/Services/`: Core business engines (`RecipeExplosionService`, `DoubleEntryLedgerService`, `StockMovementService`, `AuditLogger`)
- `database/migrations/`: Relational schema definitions
- `docs/`: Project documentation (`ARCHITECTURE.md`, `CHANGELOG.md`)
- `resources/js/`: Vue 3 components, layouts, POS terminal, KDS screens, management dashboards
- `routes/web.php`: Application route definitions

## Current Modules Status
- **Foundation & Scaffold**: **Completed (Phase 0)**
  - MySQL 8.4 engine active with dedicated databases: `royalnest_erp` and `royalnest_erp_test`
  - Laravel 12 + Inertia.js + Vue 3 + Tailwind CSS + Lucide Icons compiled and tested
  - RBAC: 9 standard restaurant roles (`owner`, `manager`, `cashier`, `kitchen_staff`, `storekeeper`, `purchase_officer`, `accountant`, `hr`, `auditor`)
  - Double-Entry General Ledger Core: `chart_of_accounts` (34 standard restaurant accounts), `payment_accounts` (6 accounts: cash, petty cash, bank, bKash, Nagad, card clearing), `journals`, and `journal_lines`
  - Universal `AuditLogger` with before/after payloads and user tracking
- **Menu, Units & Recipe BOM**: Next (Phase 1)
- **Restaurant POS, Tables & KDS**: Pending (Phase 2)
- **Procurement & GRN**: Pending (Phase 3)
- **Consumables & Double-Entry GL**: Pending (Phase 4)
- **HR, Payroll & Final Hardening**: Pending (Phase 5)

