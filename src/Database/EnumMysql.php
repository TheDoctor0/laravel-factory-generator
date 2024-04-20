<?php

declare(strict_types=1);

namespace TheDoctor0\LaravelFactoryGenerator\Database;

use Illuminate\Support\Facades\DB;

class EnumMysql extends EnumDriver
{
    public function values(): ?array
    {
        $query = DB::raw("
            SHOW COLUMNS FROM `{$this->table}`
            WHERE Field = '{$this->field}'
        ");

        $type = DB::connection($this->connection)
            ->select($query->getValue(DB::connection()->getQueryGrammar()));

        preg_match_all("/'([^']+)'/", $type[0]->Type, $matches);

        return $matches[1] ?? null;
    }
}
