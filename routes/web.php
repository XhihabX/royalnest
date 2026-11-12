<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConsumableAllocationController;
use App\Http\Controllers\ConsumptionController;
use App\Http\Controllers\DailySalesController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FinancialAccountController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReportController;
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

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Daily Sales
    Route::get('/sales', [DailySalesController::class, 'index'])->name('sales.index');
    Route::post('/sales', [DailySalesController::class, 'store'])->name('sales.store');

    // Products & Inventory Management
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');

    // Suppliers: Two Distinct Sections
    Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
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

    // Daily Ingredient Consumption
    Route::get('/consumption', [ConsumptionController::class, 'index'])->name('consumption.index');
    Route::post('/consumption', [ConsumptionController::class, 'store'])->name('consumption.store');

    // Consumables Multi-Day Allocation
    Route::get('/consumables', [ConsumableAllocationController::class, 'index'])->name('consumables.index');
    Route::post('/consumables', [ConsumableAllocationController::class, 'store'])->name('consumables.store');

    // Perpetual Stock Movement Ledger
    Route::get('/ledger', [StockLedgerController::class, 'index'])->name('ledger.index');

    // Wastage Management
    Route::get('/wastage', [WastageController::class, 'index'])->name('wastage.index');
    Route::post('/wastage', [WastageController::class, 'store'])->name('wastage.store');

    // Physical Stock Count & Reconciliation
    Route::get('/stock-counts', [StockCountController::class, 'index'])->name('stock-counts.index');
    Route::post('/stock-counts', [StockCountController::class, 'store'])->name('stock-counts.store');

    // Daily Operating Expenses
    Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');

    // Financial Accounts, Liquidity & Transfers
    Route::get('/accounts', [FinancialAccountController::class, 'index'])->name('accounts.index');
    Route::post('/accounts/transfer', [FinancialAccountController::class, 'transfer'])->name('accounts.transfer');
    Route::post('/accounts/adjust', [FinancialAccountController::class, 'adjust'])->name('accounts.adjust');

    // Employees, Staff Profile, Attendance & Payroll
    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
    Route::get('/employees/{employee}/profile', [EmployeeController::class, 'profile'])->name('employees.profile');
    Route::get('/employees/attendance/bulk', [EmployeeController::class, 'bulkAttendanceView'])->name('employees.attendance.bulk');
    Route::post('/employees/attendance/bulk', [EmployeeController::class, 'storeBulkAttendance'])->name('employees.attendance.bulk.store');
    Route::post('/employees/attendance', [EmployeeController::class, 'markAttendance'])->name('employees.attendance');
    Route::post('/employees/advance', [EmployeeController::class, 'issueAdvance'])->name('employees.advance');
    Route::post('/employees/pay-salary', [EmployeeController::class, 'paySalary'])->name('employees.pay-salary');

    // True Profit & Loss Reporting
    Route::get('/reports/profit-loss', [ReportController::class, 'profitLoss'])->name('reports.profit-loss');
});
