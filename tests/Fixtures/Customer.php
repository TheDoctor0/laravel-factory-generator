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

    /**
     * A relation-like method with a required parameter must not be invoked
     * by the generator - it cannot know what to pass.
     */
    public function related(string $model): BelongsTo
    {
        return $this->belongsTo($model);
    }
}
