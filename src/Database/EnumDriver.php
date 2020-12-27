<?php

declare(strict_types=1);

namespace TheDoctor0\LaravelFactoryGenerator\Database;

use Illuminate\Database\Eloquent\Model;

abstract class EnumDriver
{
    /**
     * @var string
     */
    protected $connection;

    /**
     * @var string
     */
    protected $table;

    /**
     * @var string
     */
    protected $field;

    public function __construct(Model $model, string $field)
    {
        $this->connection = $model->getConnectionName();
        $this->table = $model->getTable();
        $this->field = $field;
    }

    abstract public function values(): ?array;
}
