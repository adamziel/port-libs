<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaTraceState
{
    private const MODES = [
        'vdbe_trace',
        'vdbe_listing',
        'sql_trace',
    ];

    /** @var array<string,bool> */
    private array $enabled = [];

    /** @var list<array{mode:string,operation:string,sql:string,result_rows:int}> */
    private array $events = [];

    /**
     * @param array<string,bool|int|string> $enabled
     */
    public function __construct(array $enabled = [])
    {
        foreach (self::MODES as $mode) {
            $this->enabled[$mode] = false;
        }

        foreach ($enabled as $mode => $value) {
            $mode = $this->normalizeMode((string) $mode);
            $this->enabled[$mode] = $this->booleanValue($value);
        }
    }

    /**
     * @return array{
     *     status:string,
     *     pragma:'vdbe_trace'|'vdbe_listing'|'sql_trace',
     *     requested:bool|null,
     *     enabled:bool,
     *     rows:list<array<string,int>>,
     *     dependencies:list<string>
     * }
     */
    public function pragma(string $sql): array
    {
        $parsed = $this->parsePragma($sql);
        if ($parsed['requested'] !== null) {
            $this->enabled[$parsed['mode']] = $parsed['requested'];
        }

        $rows = $parsed['requested'] === null ? [[$parsed['mode'] => $this->enabled[$parsed['mode']] ? 1 : 0]] : [];

        return [
            'status' => 'ok',
            'pragma' => $parsed['mode'],
            'requested' => $parsed['requested'],
            'enabled' => $this->enabled[$parsed['mode']],
            'rows' => $rows,
            'dependencies' => ['sqlite-pragma-trace-state'],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{
     *     status:string,
     *     operation:string,
     *     sql:string,
     *     rows:list<array<string,mixed>>,
     *     result_count:int,
     *     trace_enabled:list<string>,
     *     trace_events:list<array{mode:string,operation:string,sql:string,result_rows:int}>,
     *     dependencies:list<string>
     * }
     */
    public function execute(string $sql, array $rows = []): array
    {
        $trimmed = $this->normalizeSql($sql);
        $operation = $this->operation($trimmed);
        $events = [];

        foreach ($this->enabledModes() as $mode) {
            $events[] = [
                'mode' => $mode,
                'operation' => $operation,
                'sql' => $trimmed,
                'result_rows' => count($rows),
            ];
        }

        if ($events !== []) {
            array_push($this->events, ...$events);
        }

        return [
            'status' => 'ok',
            'operation' => $operation,
            'sql' => $trimmed,
            'rows' => $rows,
            'result_count' => count($rows),
            'trace_enabled' => $this->enabledModes(),
            'trace_events' => $events,
            'dependencies' => ['sqlite-pragma-trace-state'],
        ];
    }

    /**
     * @return array<string,bool>
     */
    public function state(): array
    {
        return $this->enabled;
    }

    /**
     * @return list<array{mode:string,operation:string,sql:string,result_rows:int}>
     */
    public function traceLog(): array
    {
        return $this->events;
    }

    public function clearTraceLog(): void
    {
        $this->events = [];
    }

    /**
     * @return list<string>
     */
    private function enabledModes(): array
    {
        $modes = [];
        foreach (self::MODES as $mode) {
            if ($this->enabled[$mode]) {
                $modes[] = $mode;
            }
        }

        return $modes;
    }

    /**
     * @return array{mode:'vdbe_trace'|'vdbe_listing'|'sql_trace',requested:bool|null}
     */
    private function parsePragma(string $sql): array
    {
        $trimmed = $this->normalizeSql($sql);
        if (preg_match('/^pragma\s+(?<mode>vdbe_trace|vdbe_listing|sql_trace)(?:\s*=\s*(?<value>[^;]+))?$/i', $trimmed, $matches) !== 1) {
            throw new InvalidArgumentException('SQLite trace PRAGMA SQL is not supported');
        }

        $requested = null;
        if (($matches['value'] ?? '') !== '') {
            $requested = $this->booleanValue($matches['value']);
        }

        return [
            'mode' => $this->normalizeMode($matches['mode']),
            'requested' => $requested,
        ];
    }

    private function normalizeMode(string $mode): string
    {
        $normalized = strtolower(trim($mode));
        if (!in_array($normalized, self::MODES, true)) {
            throw new InvalidArgumentException('SQLite trace PRAGMA mode is not supported');
        }

        return $normalized;
    }

    private function booleanValue(bool|int|string $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value !== 0;
        }

        $normalized = strtolower(trim($value, " \t\r\n'\""));
        if (preg_match('/^[+-]?\d+$/', $normalized) === 1) {
            return (int) $normalized !== 0;
        }

        return match ($normalized) {
            'on', 'yes', 'true' => true,
            'off', 'no', 'false' => false,
            default => throw new InvalidArgumentException('SQLite trace PRAGMA boolean value is not supported'),
        };
    }

    private function normalizeSql(string $sql): string
    {
        $trimmed = rtrim(trim($sql), " \t\r\n;");
        if ($trimmed === '') {
            throw new InvalidArgumentException('SQLite trace SQL must not be empty');
        }

        return $trimmed;
    }

    private function operation(string $sql): string
    {
        if (preg_match('/^([A-Za-z_]+)/', ltrim($sql), $matches) !== 1) {
            throw new InvalidArgumentException('SQLite trace SQL operation is not supported');
        }

        return strtolower($matches[1]);
    }
}
