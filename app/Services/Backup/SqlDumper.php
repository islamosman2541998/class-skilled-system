<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * A database dump built through PDO queries (spec §13: no system commands on the shared hosting).
 * One gzip SQL file: for each table DROP, CREATE and INSERTs of 500 rows, with values quoted by PDO.
 * Works on MySQL (the hosting) and SQLite (the tests).
 */
class SqlDumper
{
    private const CHUNK = 500;

    /**
     * @param  list<string>|null  $tables  only these tables (default: every table)
     * @return int the file size in bytes
     */
    public function dump(string $path, ?array $tables = null): int
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $grammar = $connection->getQueryGrammar();
        $pdo = $connection->getPdo();
        $tables ??= $this->tables($driver);

        $file = gzopen($path, 'wb6');

        if ($file === false) {
            throw new RuntimeException('Cannot write '.$path);
        }

        try {
            gzwrite($file, '-- Class Skilled System backup '.now()->toDateTimeString()."\n");
            gzwrite($file, $driver === 'mysql' ? "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n" : "PRAGMA foreign_keys=OFF;\n\n");

            foreach ($tables as $table) {
                $wrapped = $grammar->wrapTable($table);
                gzwrite($file, "DROP TABLE IF EXISTS {$wrapped};\n".$this->createStatement($driver, $table).";\n");

                $columns = Schema::getColumnListing($table);
                $list = implode(', ', array_map(fn (string $column) => $grammar->wrap($column), $columns));

                for ($offset = 0; ; $offset += self::CHUNK) {
                    $rows = DB::table($table)->orderBy($columns[0])->offset($offset)->limit(self::CHUNK)->get();

                    if ($rows->isEmpty()) {
                        break;
                    }

                    $values = $rows->map(fn (object $row) => '('.implode(', ', array_map(
                        fn (string $column) => $this->literal($row->{$column}, $pdo),
                        $columns,
                    )).')')->implode(",\n");

                    gzwrite($file, "INSERT INTO {$wrapped} ({$list}) VALUES\n{$values};\n");
                }

                foreach ($driver === 'sqlite' ? $this->sqliteIndexes($table) : [] as $index) {
                    gzwrite($file, $index.";\n");
                }

                gzwrite($file, "\n");
            }

            gzwrite($file, $driver === 'mysql' ? "SET FOREIGN_KEY_CHECKS=1;\n" : "PRAGMA foreign_keys=ON;\n");
        } finally {
            gzclose($file);
        }

        clearstatcache(true, $path);

        return (int) filesize($path);
    }

    /**
     * @return list<string>
     */
    private function tables(string $driver): array
    {
        return collect(Schema::getTables())
            ->pluck('name')
            ->reject(fn (string $name) => $driver === 'sqlite' && str_starts_with($name, 'sqlite_'))
            ->values()
            ->all();
    }

    private function createStatement(string $driver, string $table): string
    {
        if ($driver === 'mysql') {
            $row = (array) DB::selectOne('SHOW CREATE TABLE '.DB::getQueryGrammar()->wrapTable($table));

            return (string) ($row['Create Table'] ?? array_values($row)[1]);
        }

        return (string) DB::table('sqlite_master')->where('type', 'table')->where('name', $table)->value('sql');
    }

    /**
     * @return list<string>
     */
    private function sqliteIndexes(string $table): array
    {
        return DB::table('sqlite_master')->where('type', 'index')->where('tbl_name', $table)->whereNotNull('sql')->pluck('sql')->map(fn ($sql) => (string) $sql)->all();
    }

    private function literal(mixed $value, \PDO $pdo): string
    {
        return match (true) {
            $value === null => 'NULL',
            is_bool($value) => $value ? '1' : '0',
            is_int($value), is_float($value) => (string) $value,
            default => (string) $pdo->quote((string) $value),
        };
    }
}
