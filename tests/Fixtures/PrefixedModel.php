<?php

declare(strict_types=1);

namespace TheDoctor0\LaravelFactoryGenerator\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class PrefixedModel extends Model
{
    protected $table = 'other.customers';
}
