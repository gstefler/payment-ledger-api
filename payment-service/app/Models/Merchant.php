<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'email', 'webhook_url'])]
class Merchant extends Model
{
    use HasFactory, HasUuids;

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(MerchantBalance::class);
    }
}
