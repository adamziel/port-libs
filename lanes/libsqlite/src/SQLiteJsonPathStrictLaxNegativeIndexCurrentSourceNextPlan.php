<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteJsonPathStrictLaxNegativeIndexCurrentSourceNextPlan
{
    /**
     * @param list<array<string, mixed>> $currentRows
     * @param list<array<string, mixed>> $nextRows
     * @param list<string> $paths
     * @return array<string, mixed>
     */
    public static function compare(array $currentRows, array $nextRows, array $paths): array
    {
        if ($paths === []) {
            throw new \InvalidArgumentException('SQLite JSON path strict/lax comparison requires at least one path');
        }

        $pathDiagnostics = [];
        $validPaths = [];
        $invalidPaths = [];
        foreach ($paths as $path) {
            if (!is_string($path)) {
                throw new \InvalidArgumentException('SQLite JSON path must be text');
            }

            $diagnostic = self::pathDiagnostic($path);
            $pathDiagnostics[$path] = $diagnostic;
            if ($diagnostic['wellFormed']) {
                $validPaths[] = $path;
            } else {
                $invalidPaths[] = $path;
            }
        }

        $current = self::sourceDiagnostics($currentRows, $validPaths);
        $next = self::sourceDiagnostics($nextRows, $validPaths);

        $changed = self::signature($current['rows']) !== self::signature($next['rows']);
        $invalidInputChanged = $invalidPaths !== [];

        return [
            'surface' => 'json-path-strict-lax-negative-index-current-source-next110',
            'pathCount' => count($paths),
            'validPathCount' => count($validPaths),
            'invalidPathCount' => count($invalidPaths),
            'paths' => $pathDiagnostics,
            'validPaths' => $validPaths,
            'invalidPaths' => $invalidPaths,
            'current' => $current,
            'next' => $next,
            'currentRowCount' => count($currentRows),
            'nextRowCount' => count($nextRows),
            'changed' => $changed,
            'reprepareRequired' => $changed || $invalidInputChanged,
            'reprepareReason' => $invalidInputChanged
                ? 'json-path-prefix-or-negative-index-malformed'
                : ($changed ? 'json-path-current-source-result-changed' : 'stable-json-path-current-source'),
            'currentReaderPolicy' => 'keep-current-json-path-source-until-statement-reset',
            'nextReaderPolicy' => $invalidInputChanged
                ? 'next-json-path-source-errors-before-row-yield'
                : 'next-json-path-source-is-runnable',
            'dependencies' => ['SQLiteJsonInspection', 'SQLiteJsonPath'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function pathDiagnostic(string $path): array
    {
        $classification = self::classifyPath($path);
        $wellFormed = SQLiteJsonPath::isWellFormed($path);
        $locatable = false;
        $error = null;

        if ($wellFormed) {
            try {
                SQLiteJsonInspection::locatePath('[]', $path);
                $locatable = true;
            } catch (\Throwable $exception) {
                $error = $exception->getMessage();
            }
        } else {
            $error = match ($classification) {
                'strict-prefix' => 'SQLite JSON path does not accept SQL/JSON strict prefix',
                'lax-prefix' => 'SQLite JSON path does not accept SQL/JSON lax prefix',
                'negative-array-index' => 'SQLite JSON path negative array index must use #-N form',
                default => 'SQLite JSON path is malformed',
            };
        }

        return [
            'path' => $path,
            'classification' => $classification,
            'wellFormed' => $wellFormed,
            'locatable' => $locatable,
            'error' => $error,
        ];
    }

    private static function classifyPath(string $path): string
    {
        $trimmed = ltrim($path);
        if (preg_match('/^strict\s+\$/i', $trimmed) === 1) {
            return 'strict-prefix';
        }
        if (preg_match('/^lax\s+\$/i', $trimmed) === 1) {
            return 'lax-prefix';
        }
        if (preg_match('/\[-[0-9]+\]/', $path) === 1) {
            return 'negative-array-index';
        }
        if (preg_match('/\[#-[0-9]+\]/', $path) === 1) {
            return 'sqlite-reverse-index';
        }
        if (str_contains($path, '[#-')) {
            return 'malformed';
        }
        if ($path === '$' || str_starts_with($path, '$.')) {
            return 'sqlite-path';
        }

        return 'malformed';
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<string> $paths
     * @return array<string, mixed>
     */
    private static function sourceDiagnostics(array $rows, array $paths): array
    {
        $diagnostics = [];
        $foundRowids = [];
        $missingRowids = [];
        $jsonErrorRowids = [];

        foreach ($rows as $row) {
            if (!array_key_exists('setting_id', $row) || !is_int($row['setting_id'])) {
                throw new \InvalidArgumentException('SQLite JSON path current-source row requires integer setting_id');
            }
            if (!array_key_exists('key_value', $row)) {
                throw new \InvalidArgumentException('SQLite JSON path current-source row requires key_value');
            }

            $rowid = $row['setting_id'];
            $pathResults = [];
            foreach ($paths as $path) {
                try {
                    $located = SQLiteJsonInspection::locatePath(self::jsonInput($row['key_value']), $path);
                    $pathResults[$path] = [
                        'found' => $located['found'],
                        'value' => $located['found'] ? self::resultValue($located['value']) : null,
                        'type' => $located['found'] ? self::typeName($located['value']) : null,
                        'error' => null,
                    ];
                    if ($located['found']) {
                        $foundRowids[$rowid] = true;
                    }
                } catch (\Throwable $exception) {
                    $jsonErrorRowids[$rowid] = true;
                    $pathResults[$path] = [
                        'found' => false,
                        'value' => null,
                        'type' => null,
                        'error' => $exception->getMessage(),
                    ];
                }
            }

            if (!isset($foundRowids[$rowid]) && !isset($jsonErrorRowids[$rowid])) {
                $missingRowids[$rowid] = true;
            }

            $diagnostics[$rowid] = [
                'rowid' => $rowid,
                'keyName' => is_string($row['key_name'] ?? null) ? $row['key_name'] : null,
                'paths' => $pathResults,
            ];
        }

        return [
            'rows' => $diagnostics,
            'foundRowids' => array_map('intval', array_keys($foundRowids)),
            'missingRowids' => array_map('intval', array_keys($missingRowids)),
            'jsonErrorRowids' => array_map('intval', array_keys($jsonErrorRowids)),
        ];
    }

    private static function jsonInput(mixed $value): string|SQLiteBlobValue|SQLiteJsonSubtypeValue|null
    {
        if ($value === null || is_string($value) || $value instanceof SQLiteBlobValue || $value instanceof SQLiteJsonSubtypeValue) {
            return $value;
        }

        throw new \InvalidArgumentException('SQLite JSON path current-source key_value must be JSON text, JSON subtype, JSONB blob, or NULL');
    }

    private static function resultValue(mixed $value): mixed
    {
        if ($value === true) {
            return 1;
        }
        if ($value === false) {
            return 0;
        }
        if ($value === null || is_int($value) || is_float($value) || is_string($value)) {
            return $value;
        }

        return SQLiteJsonCanonical::encodeDecodedJson($value);
    }

    private static function typeName(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }
        if ($value === true) {
            return 'true';
        }
        if ($value === false) {
            return 'false';
        }
        if (is_int($value)) {
            return 'integer';
        }
        if (is_float($value)) {
            return 'real';
        }
        if (is_string($value)) {
            return 'text';
        }
        if (is_array($value)) {
            return array_is_list($value) ? 'array' : 'object';
        }
        if ($value instanceof \stdClass) {
            return 'object';
        }

        throw new \InvalidArgumentException('SQLite JSON path result cannot be classified');
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private static function signature(array $rows): string
    {
        return json_encode($rows, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
