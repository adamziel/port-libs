<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaSchemaDataVersion
{
    /** @var array<string, array{schema_version:int, data_version:int, change_counter:int, user_version:int, schema_dirty:bool, data_dirty:bool, user_dirty:bool}> */
    private array $schemas = [];

    /** @var array<string, array{schema_version:int, data_version:int, change_counter:int, user_version:int, schema_dirty:bool, data_dirty:bool, user_dirty:bool}>|null */
    private ?array $transactionSnapshot = null;

    private bool $defensive = false;

    /**
     * @param array<string, array<string, int|bool>> $schemas
     */
    public function __construct(array $schemas = [])
    {
        if ($schemas === []) {
            $schemas = ['main' => []];
        }

        foreach ($schemas as $name => $state) {
            $this->schemas[self::normalizeSchemaName((string) $name)] = [
                'schema_version' => self::intOption($state, 'schema_version', 0),
                'data_version' => self::intOption($state, 'data_version', self::intOption($state, 'change_counter', 1)),
                'change_counter' => self::intOption($state, 'change_counter', self::intOption($state, 'data_version', 1)),
                'user_version' => self::signedIntOption($state, 'user_version', 0),
                'schema_dirty' => (bool) ($state['schema_dirty'] ?? false),
                'data_dirty' => (bool) ($state['data_dirty'] ?? false),
                'user_dirty' => (bool) ($state['user_dirty'] ?? false),
            ];
        }
    }

    public static function fromSnapshot(SQLitePragmaSnapshot $snapshot, string $schema = 'main'): self
    {
        return new self([
            $schema => [
                'schema_version' => $snapshot->value('schema_version') ?? 0,
                'data_version' => $snapshot->value('data_version') ?? 1,
                'change_counter' => $snapshot->value('data_version') ?? 1,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(string $sql): array
    {
        $parsed = self::parse($sql);
        $schema = $parsed['schema'];
        $name = $parsed['pragma'];
        $value = $parsed['value'];
        $this->ensureSchema($schema);

        if ($name === 'data_version') {
            if ($value !== null) {
                return $this->row($schema, $name, false, 'read_only_pragma_ignored');
            }

            return $this->row($schema, $name, false, 'current');
        }

        if ($name === 'user_version') {
            if ($value === null) {
                return $this->row($schema, $name, false, 'current');
            }

            $old = $this->schemas[$schema]['user_version'];
            $this->schemas[$schema]['user_version'] = $value;
            $this->schemas[$schema]['user_dirty'] = $old !== $value;

            return $this->row($schema, $name, $old !== $value, 'assigned');
        }

        if ($value === null) {
            return $this->row($schema, $name, false, 'current');
        }

        if ($this->defensive) {
            return $this->row($schema, $name, false, 'defensive_schema_version_ignored');
        }

        $old = $this->schemas[$schema]['schema_version'];
        $this->schemas[$schema]['schema_version'] = $value;
        $this->schemas[$schema]['schema_dirty'] = $old !== $value;

        return $this->row($schema, $name, $old !== $value, 'assigned');
    }

    /**
     * @return array<string, mixed>
     */
    public function bumpDataVersion(string $schema = 'main', int $by = 1, string $reason = 'external_commit'): array
    {
        return $this->recordExternalCommit($schema, $by, $reason);
    }

    /**
     * @return array<string, mixed>
     */
    public function recordExternalCommit(string $schema = 'main', int $by = 1, string $reason = 'external_commit'): array
    {
        if ($by < 1) {
            throw new InvalidArgumentException('data_version bump must be positive');
        }

        $schema = self::normalizeSchemaName($schema);
        $this->ensureSchema($schema);
        $this->schemas[$schema]['data_version'] += $by;
        $this->schemas[$schema]['change_counter'] += $by;
        $this->schemas[$schema]['data_dirty'] = true;

        return $this->row($schema, 'data_version', true, $reason);
    }

    /**
     * @return array<string, mixed>
     */
    public function recordLocalCommit(string $schema = 'main', int $by = 1, string $reason = 'local_commit'): array
    {
        if ($by < 1) {
            throw new InvalidArgumentException('local commit bump must be positive');
        }

        $schema = self::normalizeSchemaName($schema);
        $this->ensureSchema($schema);
        $this->schemas[$schema]['change_counter'] += $by;
        $this->schemas[$schema]['data_dirty'] = true;

        return $this->row($schema, 'data_version', false, $reason);
    }

    /**
     * @return array<string, mixed>
     */
    public function recordSchemaChange(string $schema = 'main', int $by = 1, string $reason = 'schema_change'): array
    {
        if ($by < 1) {
            throw new InvalidArgumentException('schema change bump must be positive');
        }

        $schema = self::normalizeSchemaName($schema);
        $this->ensureSchema($schema);
        $this->schemas[$schema]['schema_version'] = self::normalizeVersion($this->schemas[$schema]['schema_version'] + $by);
        $this->schemas[$schema]['change_counter'] += $by;
        $this->schemas[$schema]['schema_dirty'] = true;

        return $this->row($schema, 'schema_version', true, $reason);
    }

    /**
     * @return array{status:string, operation:string, schema_count:int}
     */
    public function beginTransaction(): array
    {
        if ($this->transactionSnapshot !== null) {
            throw new InvalidArgumentException('SQLite PRAGMA version transaction is already active');
        }

        $this->transactionSnapshot = $this->schemas;

        return [
            'status' => 'ok',
            'operation' => 'begin',
            'schema_count' => count($this->schemas),
        ];
    }

    /**
     * @return array{status:string, operation:string, restored:bool, schema_count:int}
     */
    public function rollbackTransaction(): array
    {
        if ($this->transactionSnapshot === null) {
            throw new InvalidArgumentException('SQLite PRAGMA version transaction is not active');
        }

        $this->schemas = $this->transactionSnapshot;
        $this->transactionSnapshot = null;

        return [
            'status' => 'ok',
            'operation' => 'rollback',
            'restored' => true,
            'schema_count' => count($this->schemas),
        ];
    }

    /**
     * @return array{status:string, operation:string, committed:bool, schema_count:int}
     */
    public function commitTransaction(): array
    {
        if ($this->transactionSnapshot === null) {
            throw new InvalidArgumentException('SQLite PRAGMA version transaction is not active');
        }

        $this->transactionSnapshot = null;

        return [
            'status' => 'ok',
            'operation' => 'commit',
            'committed' => true,
            'schema_count' => count($this->schemas),
        ];
    }

    public function setDefensive(bool $defensive): void
    {
        $this->defensive = $defensive;
    }

    /**
     * @return array<string, mixed>
     */
    public function observeHeader(string $schema, int $schemaVersion, int $changeCounter, string $reason = 'header_observed'): array
    {
        $schema = self::normalizeSchemaName($schema);
        $schemaVersion = self::normalizeVersion($schemaVersion);
        $changeCounter = self::normalizeVersion($changeCounter);
        $this->ensureSchema($schema);

        $oldChangeCounter = $this->schemas[$schema]['change_counter'];
        $this->schemas[$schema]['schema_version'] = $schemaVersion;
        $this->schemas[$schema]['change_counter'] = $changeCounter;
        if ($changeCounter !== $oldChangeCounter) {
            $this->schemas[$schema]['data_version'] = $changeCounter;
            $this->schemas[$schema]['data_dirty'] = true;
        }

        return $this->row($schema, 'data_version', $changeCounter !== $oldChangeCounter, $reason);
    }

    /**
     * @return array<string, int>
     */
    public function headerUpdate(string $schema = 'main'): array
    {
        $schema = self::normalizeSchemaName($schema);
        $this->ensureSchema($schema);

        return [
            'schema_cookie' => $this->schemas[$schema]['schema_version'],
            'file_change_counter' => $this->schemas[$schema]['change_counter'],
        ];
    }

    /**
     * @param list<array{id:string,schema:string,schema_cookie:int,prepared_during_rolled_back_schema?:bool,sql?:string}> $preparedStatements
     * @return array{
     *     status:string,
     *     operation:string,
     *     schema:string,
     *     current_schema_cookie:int,
     *     expired:list<string>,
     *     preserved:list<string>,
     *     reasons:array<string,string>,
     *     dependencies:list<string>
     * }
     */
    public function expirePreparedAfterSchemaRollback(array $preparedStatements, string $schema = 'main', ?int $currentSchemaCookie = null): array
    {
        $schema = self::normalizeSchemaName($schema);
        $this->ensureSchema($schema);
        $currentSchemaCookie ??= $this->schemas[$schema]['schema_version'];

        $expired = [];
        $preserved = [];
        $reasons = [];

        foreach ($preparedStatements as $statement) {
            $id = (string) $statement['id'];
            $statementSchema = self::normalizeSchemaName((string) ($statement['schema'] ?? $schema));
            $statementCookie = self::normalizeVersion((int) $statement['schema_cookie']);

            if ($statementSchema !== $schema) {
                $preserved[] = $id;
                $reasons[$id] = 'different_schema';
                continue;
            }

            if (($statement['prepared_during_rolled_back_schema'] ?? false) === true) {
                $expired[] = $id;
                $reasons[$id] = 'prepared_during_rolled_back_schema';
                continue;
            }

            if ($statementCookie !== $currentSchemaCookie) {
                $expired[] = $id;
                $reasons[$id] = 'schema_cookie_mismatch';
                continue;
            }

            $preserved[] = $id;
            $reasons[$id] = 'schema_cookie_current';
        }

        return [
            'status' => 'ok',
            'operation' => 'expire-prepared-after-schema-rollback',
            'schema' => $schema,
            'current_schema_cookie' => $currentSchemaCookie,
            'expired' => $expired,
            'preserved' => $preserved,
            'reasons' => $reasons,
            'dependencies' => [
                'sqlite-schema-rollback-expires-prepared-statements',
                'sqlite-schema-cookie-equality-is-not-sufficient-after-rollback',
            ],
        ];
    }

    /**
     * Model the schema-cookie reload gate exercised by PRAGMA schema_version=N.
     *
     * @param list<array{id:string,schema:string,schema_cookie:int,sql?:string}> $preparedStatements
     * @return array{
     *     status:string,
     *     operation:string,
     *     schema:string,
     *     before_schema_version:int,
     *     after_schema_version:int,
     *     changed:bool,
     *     reason:string,
     *     expired:list<string>,
     *     preserved:list<string>,
     *     reasons:array<string,string>,
     *     header:array<string,int>,
     *     dependencies:list<string>
     * }
     */
    public function schemaVersionReloadPlan(string $sql, array $preparedStatements): array
    {
        $parsed = self::parse($sql);
        if ($parsed['pragma'] !== 'schema_version') {
            throw new InvalidArgumentException('SQLite schema-version reload plan requires PRAGMA schema_version');
        }
        if ($parsed['value'] === null) {
            throw new InvalidArgumentException('SQLite schema-version reload plan requires an assigned value');
        }

        $schema = $parsed['schema'];
        $this->ensureSchema($schema);
        $before = $this->schemas[$schema]['schema_version'];
        $result = $this->execute($sql);
        $after = $this->schemas[$schema]['schema_version'];
        $changed = (bool) $result['changed'];

        $expired = [];
        $preserved = [];
        $reasons = [];
        foreach ($preparedStatements as $statement) {
            $id = (string) $statement['id'];
            $statementSchema = self::normalizeSchemaName((string) ($statement['schema'] ?? $schema));
            $statementCookie = self::normalizeVersion((int) $statement['schema_cookie']);

            if ($statementSchema !== $schema) {
                $preserved[] = $id;
                $reasons[$id] = 'different_schema';
                continue;
            }

            if (!$changed) {
                $preserved[] = $id;
                $reasons[$id] = 'schema_cookie_unchanged';
                continue;
            }

            if ($statementCookie !== $after) {
                $expired[] = $id;
                $reasons[$id] = 'schema_cookie_mismatch_after_pragma_assignment';
                continue;
            }

            $preserved[] = $id;
            $reasons[$id] = 'schema_cookie_matches_assigned_value';
        }

        return [
            'status' => 'ok',
            'operation' => 'schema-version-reload-plan',
            'schema' => $schema,
            'before_schema_version' => $before,
            'after_schema_version' => $after,
            'changed' => $changed,
            'reason' => (string) $result['reason'],
            'expired' => $expired,
            'preserved' => $preserved,
            'reasons' => $reasons,
            'header' => $this->headerUpdate($schema),
            'dependencies' => [
                'sqlite-pragma-schema-version-reload',
                'sqlite-schema-cookie-prepared-statement-expiry',
                'sqlite-attached-schema-version-isolation',
            ],
        ];
    }

    /**
     * @return array<string, array<string, int|bool>>
     */
    public function state(): array
    {
        return $this->schemas;
    }

    /**
     * @return array{pragma:string, schema:string, value:int|null}
     */
    public static function parse(string $sql): array
    {
        $trimmed = trim($sql);
        $trimmed = rtrim($trimmed, ';');
        $identifier = '[A-Za-z_][A-Za-z0-9_]*';
        $number = '[-+]?\d+';

        if (!preg_match('/^PRAGMA\s+(?:(?<schema>' . $identifier . ')\.)?(?<pragma>schema_version|data_version|user_version)\s*(?:(?:=|\()\s*(?<value>' . $number . ')\s*\)?)?$/i', $trimmed, $matches)) {
            throw new InvalidArgumentException("Unsupported PRAGMA schema/data version SQL: {$sql}");
        }

        $pragma = strtolower($matches['pragma']);
        $value = null;
        if (array_key_exists('value', $matches) && $matches['value'] !== '') {
            $value = $pragma === 'user_version'
                ? self::normalizeSignedVersion((int) $matches['value'])
                : self::normalizeVersion((int) $matches['value']);
        }

        return [
            'pragma' => $pragma,
            'schema' => self::normalizeSchemaName($matches['schema'] !== '' ? $matches['schema'] : 'main'),
            'value' => $value,
        ];
    }

    private function ensureSchema(string $schema): void
    {
        if (isset($this->schemas[$schema])) {
            return;
        }

        $this->schemas[$schema] = [
            'schema_version' => 0,
            'data_version' => 1,
            'change_counter' => 1,
            'user_version' => 0,
            'schema_dirty' => false,
            'data_dirty' => false,
            'user_dirty' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(string $schema, string $pragma, bool $changed, string $reason): array
    {
        $value = $this->schemas[$schema][$pragma];

        return [
            'status' => 'ok',
            'pragma' => $pragma,
            'schema' => $schema,
            'value' => $value,
            'changed' => $changed,
            'reason' => $reason,
            'rows' => [[$pragma => $value]],
            'header' => $this->headerUpdate($schema),
        ];
    }

    /**
     * @param array<string, int|bool> $state
     */
    private static function intOption(array $state, string $name, int $default): int
    {
        return self::normalizeVersion((int) ($state[$name] ?? $default));
    }

    /**
     * @param array<string, int|bool> $state
     */
    private static function signedIntOption(array $state, string $name, int $default): int
    {
        return self::normalizeSignedVersion((int) ($state[$name] ?? $default));
    }

    private static function normalizeVersion(int $value): int
    {
        if ($value < 0 || $value > 0x7fffffff) {
            throw new InvalidArgumentException('SQLite PRAGMA version values must be signed 32-bit non-negative integers');
        }

        return $value;
    }

    private static function normalizeSignedVersion(int $value): int
    {
        if ($value < -0x80000000 || $value > 0x7fffffff) {
            throw new InvalidArgumentException('SQLite PRAGMA user_version values must be signed 32-bit integers');
        }

        return $value;
    }

    private static function normalizeSchemaName(string $schema): string
    {
        $schema = strtolower(trim($schema));
        if ($schema === '' || !preg_match('/^[a-z_][a-z0-9_]*$/', $schema)) {
            throw new InvalidArgumentException("Invalid schema name: {$schema}");
        }

        return $schema;
    }
}
