<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePragmaEncodingPageTempStoreState
{
    /** @var array<string, array{encoding:string,page_size:int,page_count:int,max_page_count:int,application_id:int,temp_store:int,auto_vacuum:int,pending_auto_vacuum:int|null,database_empty:bool,temporary:bool}> */
    private array $schemas = [];

    /** @var array<string, list<array<string, int|string|null>>> */
    private array $tempTables = [];

    private bool $tempTransactionActive = false;

    private bool $tempScanActive = false;

    /**
     * @param array<string, array{encoding?:string|int,page_size?:int|string,page_count?:int|string,max_page_count?:int|string,application_id?:int|string,temp_store?:int|string,auto_vacuum?:int|string,pending_auto_vacuum?:int|string|null,database_empty?:bool,temporary?:bool}> $schemas
     */
    public function __construct(array $schemas = [])
    {
        $this->schemas['main'] = $this->normalizeSchemaState($schemas['main'] ?? []);
        $this->schemas['temp'] = $this->normalizeSchemaState(($schemas['temp'] ?? []) + [
            'temporary' => true,
            'encoding' => $this->schemas['main']['encoding'],
            'page_size' => $this->schemas['main']['page_size'],
            'database_empty' => false,
        ]);

        foreach ($schemas as $schema => $state) {
            $name = strtolower(trim((string) $schema));
            if ($name === '' || isset($this->schemas[$name])) {
                continue;
            }
            $this->schemas[$name] = $this->normalizeSchemaState($state);
        }
    }

    /**
     * @return array{
     *     status:string,
     *     pragma:'encoding'|'page_size'|'page_count'|'max_page_count'|'application_id'|'temp_store'|'auto_vacuum',
     *     schema:string,
     *     requested:int|string|null,
     *     effective:int|string,
     *     changed:bool,
     *     rows:list<array<string,int|string>>,
     *     reason:string|null,
     *     dependencies:list<string>
     * }
     */
    public function execute(string $sql): array
    {
        $parsed = self::parse($sql);
        $schema = $parsed['schema'];
        $this->ensureSchema($schema);

        return match ($parsed['pragma']) {
            'encoding' => $this->executeEncoding($schema, $parsed['value']),
            'page_size' => $this->executePageSize($schema, $parsed['value']),
            'page_count' => $this->executePageCount($schema, $parsed['value']),
            'max_page_count' => $this->executeMaxPageCount($schema, $parsed['value']),
            'application_id' => $this->executeApplicationId($schema, $parsed['value']),
            'temp_store' => $this->executeTempStore($schema, $parsed['value']),
            'auto_vacuum' => $this->executeAutoVacuum($schema, $parsed['value']),
        };
    }

    /**
     * @return array{schema:string,pragma:'encoding'|'page_size'|'page_count'|'max_page_count'|'application_id'|'temp_store'|'auto_vacuum',value:string|null}
     */
    public static function parse(string $sql): array
    {
        $trimmed = rtrim(trim($sql), " \t\r\n;");
        if (!preg_match('/^pragma\s+(?:(?<schema>[A-Za-z_][A-Za-z0-9_]*)\s*\.\s*)?(?<pragma>encoding|page_size|page_count|max_page_count|application_id|temp_store|auto_vacuum)(?:\s*(?:=\s*(?<equals>\'UTF-8\'|\'UTF-16\'|\'UTF-16le\'|\'UTF-16be\'|\'FULL\'|\'INCREMENTAL\'|\'NONE\'|\'OFF\'|"UTF-8"|"UTF-16"|"UTF-16le"|"UTF-16be"|"FULL"|"INCREMENTAL"|"NONE"|"OFF"|[A-Za-z_][A-Za-z0-9_]*|[+-]?\d+)|\(\s*(?<paren>\'UTF-8\'|\'UTF-16\'|\'UTF-16le\'|\'UTF-16be\'|\'FULL\'|\'INCREMENTAL\'|\'NONE\'|\'OFF\'|"UTF-8"|"UTF-16"|"UTF-16le"|"UTF-16be"|"FULL"|"INCREMENTAL"|"NONE"|"OFF"|[A-Za-z_][A-Za-z0-9_]*|[+-]?\d+)\s*\)))?$/i', $trimmed, $matches)) {
            throw new \InvalidArgumentException('Unsupported SQLite PRAGMA encoding/page/temp_store SQL');
        }

        $value = null;
        if (($matches['equals'] ?? '') !== '') {
            $value = $matches['equals'];
        } elseif (($matches['paren'] ?? '') !== '') {
            $value = $matches['paren'];
        }

        return [
            'schema' => strtolower(($matches['schema'] ?? '') !== '' ? $matches['schema'] : 'main'),
            'pragma' => strtolower($matches['pragma']),
            'value' => $value,
        ];
    }

    /**
     * @return array<string, array{encoding:string,page_size:int,page_count:int,max_page_count:int,application_id:int,temp_store:int,auto_vacuum:int,pending_auto_vacuum:int|null,database_empty:bool,temporary:bool}>
     */
    public function schemas(): array
    {
        return $this->schemas;
    }

    /**
     * @param list<array<string, int|string|null>> $rows
     * @return array{status:string,table:string,rows:int,temp_store:int}
     */
    public function beginTempTransaction(string $tableName, array $rows = []): array
    {
        if ($this->tempTransactionActive) {
            throw new \InvalidArgumentException('SQLite temp_store transaction is already active');
        }

        $table = self::normalizeIdentifier($tableName, 'SQLite temp table name');
        $this->tempTransactionActive = true;
        $this->tempTables[$table] = array_values($rows);

        return [
            'status' => 'temp_transaction_active',
            'table' => $table,
            'rows' => count($this->tempTables[$table]),
            'temp_store' => $this->schemas['main']['temp_store'],
        ];
    }

    /**
     * @param array<string, int|string|null> $row
     * @return array{status:string,table:string,rows:int}
     */
    public function insertTempRow(string $tableName, array $row): array
    {
        if (!$this->tempTransactionActive) {
            throw new \InvalidArgumentException('SQLite temp_store insert requires an active transaction');
        }

        $table = self::normalizeIdentifier($tableName, 'SQLite temp table name');
        $this->tempTables[$table] ??= [];
        $this->tempTables[$table][] = $row;

        return [
            'status' => 'temp_row_inserted',
            'table' => $table,
            'rows' => count($this->tempTables[$table]),
        ];
    }

    /**
     * @return array{status:string,tables:list<string>,temp_store:int}
     */
    public function commitTempTransaction(): array
    {
        if (!$this->tempTransactionActive) {
            throw new \InvalidArgumentException('SQLite temp_store transaction is not active');
        }

        $this->tempTransactionActive = false;

        return [
            'status' => 'temp_transaction_committed',
            'tables' => array_keys($this->tempTables),
            'temp_store' => $this->schemas['main']['temp_store'],
        ];
    }

    /**
     * @return array{status:string,table:string,rows:list<array<string, int|string|null>>,temp_store:int}
     */
    public function beginTempScan(string $tableName): array
    {
        if ($this->tempScanActive) {
            throw new \InvalidArgumentException('SQLite temp_store scan is already active');
        }

        $table = self::normalizeIdentifier($tableName, 'SQLite temp table name');
        $this->tempScanActive = true;

        return [
            'status' => 'temp_scan_active',
            'table' => $table,
            'rows' => $this->tempTables[$table] ?? [],
            'temp_store' => $this->schemas['main']['temp_store'],
        ];
    }

    /**
     * @return array{status:string}
     */
    public function endTempScan(): array
    {
        if (!$this->tempScanActive) {
            throw new \InvalidArgumentException('SQLite temp_store scan is not active');
        }

        $this->tempScanActive = false;

        return ['status' => 'temp_scan_finished'];
    }

    /**
     * @param array{encoding?:string|int,page_size?:int|string,page_count?:int|string,max_page_count?:int|string,application_id?:int|string,temp_store?:int|string,auto_vacuum?:int|string,pending_auto_vacuum?:int|string|null,database_empty?:bool,temporary?:bool} $state
     * @return array{encoding:string,page_size:int,page_count:int,max_page_count:int,application_id:int,temp_store:int,auto_vacuum:int,pending_auto_vacuum:int|null,database_empty:bool,temporary:bool}
     */
    private function normalizeSchemaState(array $state): array
    {
        $pendingAutoVacuum = $state['pending_auto_vacuum'] ?? null;
        $pageCount = self::normalizeNonNegativeInt($state['page_count'] ?? 0, 'SQLite page_count must be non-negative');
        $maxPageCount = self::normalizeNonNegativeInt($state['max_page_count'] ?? max($pageCount, 1073741823), 'SQLite max_page_count must be non-negative');

        return [
            'encoding' => self::normalizeEncoding($state['encoding'] ?? 'UTF-8'),
            'page_size' => self::normalizePageSize($state['page_size'] ?? 4096),
            'page_count' => $pageCount,
            'max_page_count' => max($pageCount, $maxPageCount),
            'application_id' => self::normalizeSignedInt($state['application_id'] ?? 0, 'SQLite application_id must be an integer'),
            'temp_store' => self::normalizeTempStore($state['temp_store'] ?? 0),
            'auto_vacuum' => self::normalizeAutoVacuum($state['auto_vacuum'] ?? 0),
            'pending_auto_vacuum' => $pendingAutoVacuum === null ? null : self::normalizeAutoVacuum($pendingAutoVacuum),
            'database_empty' => (bool) ($state['database_empty'] ?? true),
            'temporary' => (bool) ($state['temporary'] ?? false),
        ];
    }

    private function ensureSchema(string $schema): void
    {
        if (!isset($this->schemas[$schema])) {
            $this->schemas[$schema] = $this->normalizeSchemaState([
                'encoding' => $this->schemas['main']['encoding'],
                'page_size' => $this->schemas['main']['page_size'],
            ]);
        }
    }

    /**
     * @return array{status:string,pragma:'encoding',schema:string,requested:string|null,effective:string,changed:bool,rows:list<array{encoding:string}>,reason:string|null,dependencies:list<string>}
     */
    private function executeEncoding(string $schema, ?string $requested): array
    {
        $before = $this->schemas[$schema]['encoding'];
        $effective = $before;
        $reason = null;

        if ($requested !== null) {
            $candidate = self::normalizeEncoding($requested);
            if ($schema !== 'main' || $this->schemas[$schema]['temporary']) {
                $reason = 'encoding_is_database_connection_wide';
            } elseif (!$this->schemas[$schema]['database_empty']) {
                $reason = 'encoding_change_ignored_after_schema_created';
            } else {
                $effective = $candidate;
                $this->schemas[$schema]['encoding'] = $effective;
                foreach ($this->schemas as $name => $state) {
                    if ($state['temporary']) {
                        $this->schemas[$name]['encoding'] = $effective;
                    }
                }
            }
        }

        return [
            'status' => 'ok',
            'pragma' => 'encoding',
            'schema' => $schema,
            'requested' => $requested === null ? null : self::stripQuotes($requested),
            'effective' => $effective,
            'changed' => $before !== $effective,
            'rows' => [['encoding' => $effective]],
            'reason' => $reason,
            'dependencies' => ['sqlite-pragma-encoding-state'],
        ];
    }

    /**
     * @return array{status:string,pragma:'page_size',schema:string,requested:int|null,effective:int,changed:bool,rows:list<array{page_size:int}>,reason:string|null,dependencies:list<string>}
     */
    private function executePageSize(string $schema, ?string $requested): array
    {
        $before = $this->schemas[$schema]['page_size'];
        $effective = $before;
        $reason = null;
        $requestedValue = $requested === null ? null : self::normalizePageSize($requested);

        if ($requestedValue !== null) {
            if ($this->schemas[$schema]['temporary']) {
                $reason = 'temporary_schema_uses_connection_page_size';
            } elseif (!$this->schemas[$schema]['database_empty']) {
                $reason = 'page_size_change_requires_vacuum';
            } else {
                $effective = $requestedValue;
                $this->schemas[$schema]['page_size'] = $effective;
            }
        }

        return [
            'status' => 'ok',
            'pragma' => 'page_size',
            'schema' => $schema,
            'requested' => $requestedValue,
            'effective' => $effective,
            'changed' => $before !== $effective,
            'rows' => [['page_size' => $effective]],
            'reason' => $reason,
            'dependencies' => ['sqlite-pragma-page-size-state'],
        ];
    }

    /**
     * @return array{status:string,pragma:'page_count',schema:string,requested:null,effective:int,changed:false,rows:list<array{page_count:int}>,reason:string|null,dependencies:list<string>}
     */
    private function executePageCount(string $schema, ?string $requested): array
    {
        if ($requested !== null) {
            throw new \InvalidArgumentException('SQLite page_count is read-only');
        }

        $effective = $this->schemas[$schema]['page_count'];

        return [
            'status' => 'ok',
            'pragma' => 'page_count',
            'schema' => $schema,
            'requested' => null,
            'effective' => $effective,
            'changed' => false,
            'rows' => [['page_count' => $effective]],
            'reason' => null,
            'dependencies' => ['sqlite-pragma-page-count-state'],
        ];
    }

    /**
     * @return array{status:string,pragma:'max_page_count',schema:string,requested:int|null,effective:int,changed:bool,rows:list<array{max_page_count:int}>,reason:string|null,page_count:int,dependencies:list<string>}
     */
    private function executeMaxPageCount(string $schema, ?string $requested): array
    {
        $before = $this->schemas[$schema]['max_page_count'];
        $requestedValue = $requested === null ? null : self::normalizeNonNegativeInt($requested, 'SQLite max_page_count must be non-negative');
        $effective = $before;
        $reason = null;

        if ($requestedValue !== null) {
            $pageCount = $this->schemas[$schema]['page_count'];
            $effective = max($pageCount, $requestedValue);
            $this->schemas[$schema]['max_page_count'] = $effective;
            $reason = $effective === $requestedValue ? 'assigned' : 'clamped_to_page_count';
        }

        return [
            'status' => 'ok',
            'pragma' => 'max_page_count',
            'schema' => $schema,
            'requested' => $requestedValue,
            'effective' => $effective,
            'changed' => $before !== $effective,
            'rows' => [['max_page_count' => $effective]],
            'reason' => $reason,
            'page_count' => $this->schemas[$schema]['page_count'],
            'dependencies' => ['sqlite-pragma-max-page-count-state'],
        ];
    }

    /**
     * @return array{status:string,pragma:'application_id',schema:string,requested:int|null,effective:int,changed:bool,rows:list<array{application_id:int}>,reason:string|null,dependencies:list<string>}
     */
    private function executeApplicationId(string $schema, ?string $requested): array
    {
        $before = $this->schemas[$schema]['application_id'];
        $effective = $before;
        $requestedValue = $requested === null ? null : self::normalizeSignedInt($requested, 'SQLite application_id must be an integer');

        if ($requestedValue !== null) {
            $effective = $requestedValue;
            $this->schemas[$schema]['application_id'] = $effective;
        }

        return [
            'status' => 'ok',
            'pragma' => 'application_id',
            'schema' => $schema,
            'requested' => $requestedValue,
            'effective' => $effective,
            'changed' => $before !== $effective,
            'rows' => [['application_id' => $effective]],
            'reason' => null,
            'dependencies' => ['sqlite-pragma-application-id-state'],
        ];
    }

    /**
     * @return array{status:string,pragma:'temp_store',schema:string,requested:int|string|null,effective:int,changed:bool,rows:list<array{temp_store:int}>,reason:string|null,dependencies:list<string>}
     */
    private function executeTempStore(string $schema, ?string $requested): array
    {
        $before = $this->schemas[$schema]['temp_store'];
        $effective = $before;

        if ($requested !== null) {
            if ($this->tempTransactionActive || $this->tempScanActive) {
                throw new \RuntimeException('temporary storage cannot be changed from within a transaction');
            }

            $effective = self::normalizeTempStore($requested);
            $this->schemas[$schema]['temp_store'] = $effective;
        }

        return [
            'status' => 'ok',
            'pragma' => 'temp_store',
            'schema' => $schema,
            'requested' => $requested === null ? null : self::stripQuotes($requested),
            'effective' => $effective,
            'changed' => $before !== $effective,
            'rows' => [['temp_store' => $effective]],
            'reason' => null,
            'dependencies' => ['sqlite-pragma-temp-store-state'],
        ];
    }

    /**
     * @return array{status:string,pragma:'auto_vacuum',schema:string,requested:int|null,effective:int,changed:bool,rows:list<array{auto_vacuum:int}>,reason:string|null,requires_vacuum:bool,pending:int|null,page_count:int,dependencies:list<string>}
     */
    private function executeAutoVacuum(string $schema, ?string $requested): array
    {
        $before = $this->schemas[$schema]['auto_vacuum'];
        $effective = $before;
        $pending = $this->schemas[$schema]['pending_auto_vacuum'];
        $reason = null;
        $requiresVacuum = false;
        $requestedValue = $requested === null ? null : self::normalizeAutoVacuum($requested);

        if ($requestedValue !== null) {
            if ($this->schemas[$schema]['temporary']) {
                $reason = 'temporary_schema_auto_vacuum_is_connection_local';
            } elseif ($requestedValue === 0 && $before !== 0) {
                $pending = 0;
                $requiresVacuum = true;
                $reason = 'auto_vacuum_disable_requires_vacuum';
            } elseif (!$this->schemas[$schema]['database_empty'] && $before === 0 && $requestedValue !== 0) {
                $pending = $requestedValue;
                $requiresVacuum = true;
                $reason = 'auto_vacuum_enable_requires_vacuum';
            } else {
                $effective = $requestedValue;
                $pending = null;
                $this->schemas[$schema]['auto_vacuum'] = $effective;
            }

            $this->schemas[$schema]['pending_auto_vacuum'] = $pending;
        }

        return [
            'status' => 'ok',
            'pragma' => 'auto_vacuum',
            'schema' => $schema,
            'requested' => $requestedValue,
            'effective' => $effective,
            'changed' => $before !== $effective,
            'rows' => [['auto_vacuum' => $effective]],
            'reason' => $reason,
            'requires_vacuum' => $requiresVacuum,
            'pending' => $pending,
            'page_count' => $this->schemas[$schema]['page_count'],
            'dependencies' => ['sqlite-pragma-auto-vacuum-state'],
        ];
    }

    private static function normalizeEncoding(int|string $value): string
    {
        $text = strtoupper(str_replace(['_', ' '], ['-', ''], self::stripQuotes((string) $value)));

        return match ($text) {
            '1', 'UTF-8', 'UTF8' => 'UTF-8',
            '2', 'UTF-16LE', 'UTF16LE' => 'UTF-16le',
            '3', 'UTF-16BE', 'UTF16BE' => 'UTF-16be',
            'UTF-16', 'UTF16' => 'UTF-16le',
            default => throw new \InvalidArgumentException('Unsupported SQLite encoding'),
        };
    }

    private static function normalizePageSize(int|string $value): int
    {
        if (!is_int($value) && !ctype_digit((string) $value)) {
            throw new \InvalidArgumentException('SQLite page_size must be numeric');
        }
        $pageSize = (int) $value;
        if ($pageSize < 512 || $pageSize > 65536 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite page_size must be a power of two between 512 and 65536');
        }

        return $pageSize;
    }

    private static function normalizeTempStore(int|string $value): int
    {
        $normalized = is_int($value) || ctype_digit((string) $value)
            ? (int) $value
            : match (strtolower(self::stripQuotes((string) $value))) {
                'default' => 0,
                'file' => 1,
                'memory' => 2,
                default => -1,
            };

        if (!in_array($normalized, [0, 1, 2], true)) {
            throw new \InvalidArgumentException('Unsupported SQLite temp_store mode');
        }

        return $normalized;
    }

    private static function normalizeIdentifier(string $value, string $message): string
    {
        $identifier = strtolower(trim($value));
        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $identifier)) {
            throw new \InvalidArgumentException($message);
        }

        return $identifier;
    }

    private static function normalizeAutoVacuum(int|string $value): int
    {
        $normalized = is_int($value) || ctype_digit((string) $value)
            ? (int) $value
            : match (strtolower(str_replace(['-', '_'], '', self::stripQuotes((string) $value)))) {
                'none', 'off' => 0,
                'full' => 1,
                'incremental' => 2,
                default => -1,
            };

        if (!in_array($normalized, [0, 1, 2], true)) {
            throw new \InvalidArgumentException('Unsupported SQLite auto_vacuum mode');
        }

        return $normalized;
    }

    private static function normalizeNonNegativeInt(int|string $value, string $message): int
    {
        if (!is_int($value) && !preg_match('/^[+]?\d+$/', (string) $value)) {
            throw new \InvalidArgumentException($message);
        }
        $int = (int) $value;
        if ($int < 0) {
            throw new \InvalidArgumentException($message);
        }

        return $int;
    }

    private static function normalizeSignedInt(int|string $value, string $message): int
    {
        if (!is_int($value) && !preg_match('/^[+-]?\d+$/', (string) $value)) {
            throw new \InvalidArgumentException($message);
        }

        return (int) $value;
    }

    private static function stripQuotes(string $value): string
    {
        $trimmed = trim($value);
        if (strlen($trimmed) >= 2) {
            $first = $trimmed[0];
            $last = $trimmed[strlen($trimmed) - 1];
            if (($first === "'" && $last === "'") || ($first === '"' && $last === '"')) {
                return substr($trimmed, 1, -1);
            }
        }

        return $trimmed;
    }
}
