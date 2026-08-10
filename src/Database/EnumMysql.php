<?php

declare(strict_types=1);

namespace TheDoctor0\LaravelFactoryGenerator\Database;

use Illuminate\Support\Facades\DB;

class EnumMysql extends EnumDriver
{
    public function values(): ?array
    {
        $table = str_replace('`', '``', $this->table);

        $type = DB::connection($this->connection)
            ->select("SHOW COLUMNS FROM `$table` WHERE Field = ?", [$this->field]);

        if (empty($type)) {
            return null;
        }

        preg_match_all("/'([^']+)'/", $type[0]->Type, $matches);

        return $matches[1] ?? null;
    }
}
