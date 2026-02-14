<?php

namespace App\Models;

use App\Http\Traits\Observable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory, Observable;

    protected $fillable = [
        'name',
        'last_name',
        'salary',
        'ssn',
        'address',
        'goal',
        'tax_id',
        'country',
    ];
}
