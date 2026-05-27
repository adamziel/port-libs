<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaLockingMode
{
    /** @var array<string, string> */
    private array $modes = ['main' => 'normal'];

    public function current(?string $schema = null): string
    {
        $schema = self::normalizeSchema($schema);
        if ($schema === 'temp') {
            return 'exclusive';
        }

        return $this->modes[$schema ?? 'main'] ?? 'normal';
    }

    public function set(string $mode, ?string $schema = null): string
    {
        $schema = self::normalizeSchema($schema);
        $mode = strtolower(trim($mode));

        if (!in_array($mode, ['normal', 'exclusive'], true)) {
            return $this->current($schema);
        }

        if ($schema !== 'temp') {
            $this->modes[$schema ?? 'main'] = $mode;
        }

        return $this->current($schema);
    }

    /**
     * @return array{status: string, pragma: string, schema: string, requested_mode: string|null, locking_mode: string, changed: bool, rows: list<array{locking_mode: string}>}
     */
    public function execute(string $sql): array
    {
        $parsed = self::parse($sql);
        $before = $this->current($parsed['schema']);
        $requested = $parsed['mode'];
        $current = $requested === null ? $before : $this->set($requested, $parsed['schema']);

        return [
            'status' => 'ok',
            'pragma' => 'locking_mode',
            'schema' => $parsed['schema'] ?? 'main',
            'requested_mode' => $requested,
            'locking_mode' => $current,
            'changed' => $current !== $before,
            'rows' => [
                ['locking_mode' => $current],
            ],
        ];
    }

    /**
     * @return array{schema: string|null, mode: string|null}
     */
    private static function parse(string $sql): array
    {
        $trimmed = trim($sql);
        $trimmed = rtrim($trimmed, " \t\n\r\0\x0B;");

        if (!preg_match('/^pragma\s+(?:(?<schema>[A-Za-z_][A-Za-z0-9_]*)\s*\.\s*)?locking_mode(?:\s*(?:=\s*(?<equals>[A-Za-z_][A-Za-z0-9_]*)|\(\s*(?<paren>[A-Za-z_][A-Za-z0-9_]*)\s*\)))?$/i', $trimmed, $matches)) {
            throw new InvalidArgumentException('Only PRAGMA locking_mode queries and assignments are supported');
        }

        $schema = isset($matches['schema']) && $matches['schema'] !== '' ? strtolower($matches['schema']) : null;
        $mode = null;
        if (isset($matches['equals']) && $matches['equals'] !== '') {
            $mode = strtolower($matches['equals']);
        } elseif (isset($matches['paren']) && $matches['paren'] !== '') {
            $mode = strtolower($matches['paren']);
        }

        return [
            'schema' => $schema,
            'mode' => $mode,
        ];
    }

    private static function normalizeSchema(?string $schema): ?string
    {
        if ($schema === null || trim($schema) === '') {
            return null;
        }

        return strtolower(trim($schema));
    }
}
