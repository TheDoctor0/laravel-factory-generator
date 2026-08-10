<?php

declare(strict_types=1);

namespace TheDoctor0\LaravelFactoryGenerator\Database;

use Illuminate\Support\Facades\DB;

class EnumSqlite extends EnumDriver
{
    public function values(): ?array
    {
        $type = DB::connection($this->connection)
            ->select('SELECT sql FROM sqlite_master WHERE tbl_name = ?', [$this->table]);

        if (empty($type)) {
            return null;
        }

        $field = preg_quote($this->field, '/');

        preg_match_all("/check \(\"$field\" in \((.+?)\)\)/", $type[0]->sql, $matches);

        if (isset($matches[1][0])) {
            return collect(explode(',', $matches[1][0]))->map(function ($value) {
                return trim(trim($value), "'");
            })->toArray();
        }

        return null;
    }
}
