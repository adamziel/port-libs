<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePragmaOptimizePlan
{
    /** @var array<string,int> */
    private array $analysisLimits = [];

    /**
     * @param array<string,int> $analysisLimits
     */
    public function __construct(array $analysisLimits = [])
    {
        foreach ($analysisLimits as $schema => $limit) {
            $this->analysisLimits[$this->normalizeSchema((string) $schema)] = $this->normalizeLimit($limit);
        }
        $this->analysisLimits += ['main' => 0];
    }

    /**
     * @return array<string,mixed>
     */
    public function execute(string $sql, array $tables = []): array
    {
        $parsed = self::parse($sql);
        $schema = $this->normalizeSchema($parsed['schema']);

        if ($parsed['pragma'] === 'analysis_limit') {
            return $this->executeAnalysisLimit($schema, $parsed['argument']);
        }

        return $this->executeOptimize($schema, $parsed['argument'], $tables);
    }

    /**
     * @return array{pragma:string,schema:string,argument:mixed}
     */
    public static function parse(string $sql): array
    {
        $sql = trim($sql);
        $sql = rtrim($sql, " \t\r\n;");
        $identifier = '(?:"[^"]+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)';
        if (!preg_match('/^PRAGMA\s+(?:(?<schema>' . $identifier . ')\.)?(?<pragma>analysis_limit|optimize)\s*(?:(?:=\s*(?<eq>.+))|(?:\((?<paren>.*)\)))?$/is', $sql, $match)) {
            throw new \InvalidArgumentException('SQLite PRAGMA optimize plan supports PRAGMA analysis_limit and PRAGMA optimize');
        }

        $argument = null;
        if (array_key_exists('eq', $match) && $match['eq'] !== '') {
            $argument = self::parseArgument($match['eq']);
        } elseif (array_key_exists('paren', $match) && $match['paren'] !== '') {
            $argument = self::parseArgument($match['paren']);
        }

        return [
            'pragma' => strtolower($match['pragma']),
            'schema' => isset($match['schema']) && $match['schema'] !== '' ? self::unquoteIdentifier($match['schema']) : 'main',
            'argument' => $argument,
        ];
    }

    /**
     * @param list<array<string,mixed>> $tables
     * @return array<string,mixed>
     */
    private function executeOptimize(string $schema, mixed $argument, array $tables): array
    {
        $mask = $argument === null ? 0xfffe : $this->normalizeMask($argument);
        $previousLimit = $this->analysisLimits[$schema] ?? 0;
        $temporaryLimit = $previousLimit === 0 ? 2000 : $previousLimit;
        $this->analysisLimits[$schema] = $temporaryLimit;

        $analyze = [];
        $skipped = [];
        foreach ($tables as $table) {
            $tableSchema = $this->normalizeSchema((string) ($table['schema'] ?? 'main'));
            if ($tableSchema !== $schema) {
                continue;
            }

            $name = self::requiredIdentifier($table, 'name', 'SQLite PRAGMA optimize table');
            $rowCount = max(0, (int) ($table['rowCount'] ?? 0));
            $statRowCount = array_key_exists('statRowCount', $table) ? max(0, (int) $table['statRowCount']) : null;
            $touched = (bool) ($table['touched'] ?? false);
            $hasStat = (bool) ($table['hasStat'] ?? $statRowCount !== null);
            $reason = $this->optimizeReason($mask, $rowCount, $statRowCount, $touched, $hasStat);

            if ($reason === null) {
                $skipped[] = ['table' => $name, 'reason' => 'up-to-date'];
                continue;
            }

            $analyze[] = [
                'schema' => $schema,
                'table' => $name,
                'sql' => 'ANALYZE ' . self::quoteIdentifier($schema) . '.' . self::quoteIdentifier($name),
                'reason' => $reason,
                'analysisLimit' => $temporaryLimit,
                'estimatedRows' => $rowCount,
                'statRows' => $statRowCount,
            ];
        }

        $this->analysisLimits[$schema] = $previousLimit;

        return [
            'pragma' => 'optimize',
            'schema' => $schema,
            'mask' => $mask,
            'previousAnalysisLimit' => $previousLimit,
            'temporaryAnalysisLimit' => $temporaryLimit,
            'restoredAnalysisLimit' => $this->analysisLimits[$schema],
            'analyze' => $analyze,
            'skipped' => $skipped,
            'rows' => [],
            'dependencies' => ['analysis_limit', 'sqlite_stat1', 'schema-table-scan'],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function executeAnalysisLimit(string $schema, mixed $argument): array
    {
        $previous = $this->analysisLimits[$schema] ?? 0;
        if ($argument !== null) {
            $this->analysisLimits[$schema] = $this->normalizeLimit($argument);
        }

        $current = $this->analysisLimits[$schema] ?? 0;

        return [
            'pragma' => 'analysis_limit',
            'schema' => $schema,
            'previous' => $previous,
            'effective' => $current,
            'changed' => $argument !== null && $previous !== $current,
            'rows' => [['analysis_limit' => $current]],
            'dependencies' => ['pragma-state'],
        ];
    }

    private function optimizeReason(int $mask, int $rowCount, ?int $statRowCount, bool $touched, bool $hasStat): ?string
    {
        if (($mask & 0x0001) !== 0) {
            return 'debug-mask';
        }
        if (!$hasStat || $statRowCount === null) {
            return 'missing-stat1';
        }
        if ($touched && ($mask & 0x0002) !== 0) {
            return 'touched-table';
        }
        if (($mask & 0x10000) !== 0) {
            return 'force-all';
        }
        if ($rowCount > 0 && abs($rowCount - $statRowCount) >= max(10, (int) ceil($rowCount * 0.25))) {
            return 'row-count-drift';
        }

        return null;
    }

    private function normalizeLimit(mixed $limit): int
    {
        if (!is_int($limit) && !(is_string($limit) && preg_match('/^-?\d+$/', trim($limit)))) {
            throw new \InvalidArgumentException('SQLite PRAGMA analysis_limit needs an integer value');
        }

        return max(0, (int) $limit);
    }

    private function normalizeMask(mixed $mask): int
    {
        if (!is_int($mask) && !(is_string($mask) && preg_match('/^(?:0x[0-9A-Fa-f]+|\d+)$/', trim($mask)))) {
            throw new \InvalidArgumentException('SQLite PRAGMA optimize mask needs an unsigned integer value');
        }

        $value = is_string($mask) && str_starts_with(strtolower(trim($mask)), '0x')
            ? hexdec(substr(trim($mask), 2))
            : (int) $mask;
        if ($value < 0) {
            throw new \InvalidArgumentException('SQLite PRAGMA optimize mask needs an unsigned integer value');
        }

        return $value;
    }

    private function normalizeSchema(string $schema): string
    {
        $schema = strtolower(trim($schema));

        return $schema === '' ? 'main' : $schema;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function requiredIdentifier(array $row, string $key, string $context): string
    {
        $value = $row[$key] ?? null;
        if (!is_string($value) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value)) {
            throw new \InvalidArgumentException($context . ' needs a safe identifier ' . $key);
        }

        return $value;
    }

    private static function parseArgument(string $argument): mixed
    {
        $argument = trim($argument);
        if ($argument === '') {
            return null;
        }
        if (
            (str_starts_with($argument, "'") && str_ends_with($argument, "'"))
            || (str_starts_with($argument, '"') && str_ends_with($argument, '"'))
        ) {
            return substr($argument, 1, -1);
        }

        return $argument;
    }

    private static function unquoteIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);
        if (
            (str_starts_with($identifier, '"') && str_ends_with($identifier, '"'))
            || (str_starts_with($identifier, '`') && str_ends_with($identifier, '`'))
            || (str_starts_with($identifier, '[') && str_ends_with($identifier, ']'))
        ) {
            return substr($identifier, 1, -1);
        }

        return $identifier;
    }

    private static function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
}
