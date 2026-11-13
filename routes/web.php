<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CashReconciliationController;
use App\Http\Controllers\ConsumableAllocationController;
use App\Http\Controllers\ConsumptionController;
use App\Http\Controllers\DailySalesController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FinancialAccountController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfitCalculatorController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SmartCostController;
use App\Http\Controllers\StockCountController;
use App\Http\Controllers\StockLedgerController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\WastageController;
use Illuminate\Support\Facades\Route;

// Public Authentication Routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);

// Authenticated Operational Application
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Dashboard - Accessible to all authenticated users
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Attendance marking - staff and up
    Route::post('/employees/attendance', [EmployeeController::class, 'markAttendance'])->name('employees.attendance');

    // Daily Sales, Smart Cost Entry & Cash Reconciliation - Cashiers, Managers, Admins, Owners
    Route::middleware('role:cashier,manager')->group(function () {
        Route::get('/sales', [DailySalesController::class, 'index'])->name('sales.index');
        Route::post('/sales', [DailySalesController::class, 'store'])->name('sales.store');

        Route::get('/consumption', [ConsumptionController::class, 'index'])->name('consumption.index');
        Route::post('/consumption', [ConsumptionController::class, 'store'])->name('consumption.store');

        // Smart Daily Cost Entry & Quick Shortcuts
        Route::get('/daily-costs', [SmartCostController::class, 'index'])->name('daily-costs.index');
        Route::post('/daily-costs', [SmartCostController::class, 'store'])->name('daily-costs.store');
        Route::post('/daily-costs/templates/{template}/favorite', [SmartCostController::class, 'toggleFavorite'])->name('daily-costs.template.favorite');
        Route::delete('/daily-costs/templates/{template}', [SmartCostController::class, 'destroyTemplate'])->name('daily-costs.template.destroy');

        // Daily Cash Reconciliation & Auto Sales Estimation
        Route::get('/cash-reconciliation', [CashReconciliationController::class, 'index'])->name('cash-reconciliation.index');
        Route::post('/cash-reconciliation', [CashReconciliationController::class, 'store'])->name('cash-reconciliation.store');
        Route::post('/cash-reconciliation/sync-sales', [CashReconciliationController::class, 'syncToSales'])->name('cash-reconciliation.sync-sales');

        // Daily Profit & Loss Calculator (Raw Material Usage & Real-Time P&L)
        Route::get('/calculator', [ProfitCalculatorController::class, 'index'])->name('calculator.index');
        Route::post('/calculator', [ProfitCalculatorController::class, 'store'])->name('calculator.store');
    });

    // Inventory, Operations, Staff & Purchasing - Managers, Admins, Owners
    Route::middleware('role:manager')->group(function () {
        // Products
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::post('/products', [ProductController::class, 'store'])->name('products.store');

        // Suppliers
        Route::get('/suppliers', fn () => redirect()->route('suppliers.directory'))->name('suppliers.index');
        Route::get('/suppliers/directory', [SupplierController::class, 'directory'])->name('suppliers.directory');
        Route::get('/suppliers/active', [SupplierController::class, 'active'])->name('suppliers.active');
        Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
        Route::post('/suppliers/{supplier}/status', [SupplierController::class, 'setStatus'])->name('suppliers.set-status');
        Route::get('/suppliers/{supplier}/profile', [SupplierController::class, 'profile'])->name('suppliers.profile');
        Route::post('/suppliers/{supplier}/pay-due', [SupplierController::class, 'payDue'])->name('suppliers.pay-due');
        Route::post('/suppliers/{supplier}/advance', [SupplierController::class, 'advance'])->name('suppliers.advance');
        Route::post('/suppliers/{supplier}/products', [SupplierController::class, 'addProduct'])->name('suppliers.add-product');
        Route::delete('/supplier-products/{supplierProduct}', [SupplierController::class, 'deleteProduct'])->name('suppliers.delete-product');

        // Purchases & Receiving
        Route::get('/purchases', [PurchaseController::class, 'index'])->name('purchases.index');
        Route::post('/purchases', [PurchaseController::class, 'store'])->name('purchases.store');

        // Consumables Multi-Day Allocation
        Route::get('/consumables', [ConsumableAllocationController::class, 'index'])->name('consumables.index');
        Route::post('/consumables', [ConsumableAllocationController::class, 'store'])->name('consumables.store');

        // Stock Ledger & Wastage & Counts
        Route::get('/ledger', [StockLedgerController::class, 'index'])->name('ledger.index');
        Route::get('/wastage', [WastageController::class, 'index'])->name('wastage.index');
        Route::post('/wastage', [WastageController::class, 'store'])->name('wastage.store');
        Route::get('/stock-counts', [StockCountController::class, 'index'])->name('stock-counts.index');
        Route::post('/stock-counts', [StockCountController::class, 'store'])->name('stock-counts.store');

        // Daily Operating Expenses
        Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');

        // Employees Management & Attendance
        Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
        Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
        Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
        Route::get('/employees/{employee}/profile', [EmployeeController::class, 'profile'])->name('employees.profile');
        Route::get('/employees/attendance/bulk', [EmployeeController::class, 'bulkAttendanceView'])->name('employees.attendance.bulk');
        Route::post('/employees/attendance/bulk', [EmployeeController::class, 'storeBulkAttendance'])->name('employees.attendance.bulk.store');
        Route::post('/employees/advance', [EmployeeController::class, 'issueAdvance'])->name('employees.advance');
    });

    // Sensitive Finance, Liquidity, Payroll Disbursal & Executive Reporting - Owners & Admins
    Route::middleware('role:admin,owner')->group(function () {
        // Financial Accounts
        Route::get('/accounts', [FinancialAccountController::class, 'index'])->name('accounts.index');
        Route::post('/accounts/transfer', [FinancialAccountController::class, 'transfer'])->name('accounts.transfer');
        Route::post('/accounts/adjust', [FinancialAccountController::class, 'adjust'])->name('accounts.adjust');

        // Salary Disbursal
        Route::post('/employees/pay-salary', [EmployeeController::class, 'paySalary'])->name('employees.pay-salary');

        // True Profit & Loss Reporting
        Route::get('/reports/profit-loss', [ReportController::class, 'profitLoss'])->name('reports.profit-loss');
    });
});
