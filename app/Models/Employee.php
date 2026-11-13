<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_id',
        'name',
        'phone',
        'alternative_phone',
        'nid_number',
        'photo_path',
        'nid_photo_path',
        'emergency_contact',
        'address',
        'role',
        'department',
        'tags',
        'joining_date',
        'salary_type',
        'agreed_salary',
        'held_salary_days',
        'held_salary_amount',
        'hourly_rate',
        'status',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'joining_date' => 'date:Y-m-d',
        'agreed_salary' => 'decimal:2',
        'held_salary_days' => 'integer',
        'held_salary_amount' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'photo_url',
        'nid_photo_url',
    ];

    protected function photoUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->photo_path ? Storage::url($this->photo_path) : null,
        );
    }

    protected function nidPhotoUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->nid_photo_path ? Storage::url($this->nid_photo_path) : null,
        );
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(EmployeeAttendance::class);
    }

    public function salaryPayments(): HasMany
    {
        return $this->hasMany(SalaryPayment::class)->orderBy('payment_date', 'desc');
    }

    public function advances(): HasMany
    {
        return $this->hasMany(EmployeeAdvance::class)->orderBy('advance_date', 'desc');
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(SalaryDeduction::class)->orderBy('deduction_date', 'desc');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
