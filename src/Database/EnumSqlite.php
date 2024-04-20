<?php

declare(strict_types=1);

namespace TheDoctor0\LaravelFactoryGenerator\Database;

use Illuminate\Support\Facades\DB;

class EnumSqlite extends EnumDriver
{
    public function values(): ?array
    {
        $query = DB::raw("
            SELECT sql FROM sqlite_schema
            WHERE tbl_name = '{$this->table}'
        ");

        $type = DB::connection($this->connection)
            ->select($query->getValue(DB::connection()->getQueryGrammar()));

        preg_match_all("/check \(\"{$this->field}\" in \((.+?)\)\)/", $type[0]->sql, $matches);

        if (isset($matches[1][0])) {
            return collect(explode(',', $matches[1][0]))->map(function ($value) {
                return trim(trim($value), "'");
            })->toArray();
        }

        return null;
    }
}
