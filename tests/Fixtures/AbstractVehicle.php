<?php

declare(strict_types=1);

namespace TheDoctor0\LaravelFactoryGenerator\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

abstract class AbstractVehicle extends Model
{
    protected $table = 'vehicles';
}
