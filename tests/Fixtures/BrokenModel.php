<?php

declare(strict_types=1);

namespace TheDoctor0\LaravelFactoryGenerator\Tests\Fixtures;

use Exception;
use Illuminate\Database\Eloquent\Model;

class BrokenModel extends Model
{
    protected $table = 'countries';

    public function factoryGeneratorInit(): void
    {
        throw new Exception('Cannot prepare model.');
    }
}
