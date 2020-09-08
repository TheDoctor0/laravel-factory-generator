<?php

declare(strict_types=1);

namespace TheDoctor0\LaravelFactoryGenerator\Database;

use Illuminate\Support\Facades\DB;

class EnumPgsql extends EnumDriver
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
                    SELECT matches[1]
                    FROM pg_constraint, regexp_matches(pg_get_constraintdef(\"oid\"), '''(.+?)''', 'g') matches
                    WHERE contype = 'c'
                        AND conname = '{$this->table}_{$this->field}_check'
                        AND conrelid = 'public.{$this->table}'::regclass;
                ")
            );

        if (! count($type)) {
            return null;
        }

        return collect($type)->map->matches->toArray();
    }
}
