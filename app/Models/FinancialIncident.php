<?php

namespace App\Models;

use Database\Factories\FinancialIncidentFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Guarded(['*'])]
#[Hidden(['destination', 'claim_token'])]
class FinancialIncident extends Model
{
    /** @use HasFactory<FinancialIncidentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['resolved_at' => 'immutable_datetime'];
    }
}
