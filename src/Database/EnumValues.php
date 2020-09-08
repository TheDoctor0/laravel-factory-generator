<?php

declare(strict_types=1);

namespace TheDoctor0\LaravelFactoryGenerator\Database;

use Illuminate\Database\Eloquent\Model;

class EnumValues
{
    /**
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param string                              $field
     *
     * @return array|null
     */
    public static function get(Model $model, string $field): ?array
    {
        return (new self)->values($model, $field);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param string                              $field
     *
     * @return array|null
     */
    protected function values(Model $model, string $field): ?array
    {
        $driver = $model->getConnection()->getDriverName();

        if ($driver === 'mysql') {
            return (new EnumMysql($model, $field))->values();
        }

        if ($driver === 'pgsql') {
            return (new EnumPgsql($model, $field))->values();
        }

        return null;
    }
}
