<?php

declare(strict_types=1);

namespace TheDoctor0\LaravelFactoryGenerator\Database;

use Illuminate\Support\Facades\DB;

class EnumMysql extends EnumDriver
{
    /**
     * Get enum values for model field in MySQL database.
     *
     * @return array|null
     */
    public function values(): ?array
    {
        $type = DB::connection($this->connection)
            ->select(
                DB::raw("
                    SHOW COLUMNS FROM `{$this->table}`
                    WHERE Field = '{$this->field}
                ")
            );

        preg_match_all("/'([^']+)'/", $type[0]->Type, $matches);

        return $matches[1] ?? null;
    }
}
