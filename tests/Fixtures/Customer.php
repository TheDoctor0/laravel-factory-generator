<?php

declare(strict_types=1);

namespace TheDoctor0\LaravelFactoryGenerator\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Customer extends Model
{
    protected $table = 'customers';

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function related(string $model): BelongsTo
    {
        return $this->belongsTo($model);
    }
}
