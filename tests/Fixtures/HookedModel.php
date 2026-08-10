<?php

declare(strict_types=1);

namespace TheDoctor0\LaravelFactoryGenerator\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class HookedModel extends Model
{
    protected $table = 'countries';

    public bool $initialized = false;

    public bool $finished = false;

    public function factoryGeneratorInit(): void
    {
        $this->initialized = true;
    }

    public function factoryGeneratorEnd(): void
    {
        $this->finished = true;
    }
}
