<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaConnectionBooleanState
{
    private const PRAGMAS = [
        'automatic_index' => true,
        'cell_size_check' => false,
        'checkpoint_fullfsync' => false,
        'count_changes' => false,
        'defer_foreign_keys' => false,
        'foreign_keys' => false,
        'full_column_names' => false,
        'fullfsync' => false,
        'ignore_check_constraints' => false,
        'query_only' => false,
        'read_uncommitted' => false,
        'recursive_triggers' => false,
        'reverse_unordered_selects' => false,
        'short_column_names' => true,
        'writable_schema' => false,
    ];

    /** @var array<string,bool> */
    private array $values;

    private bool $transactionActive = false;

    /**
     * @param array<string,bool|int|string> $values
     */
    public function __construct(array $values = [])
    {
        $this->values = self::PRAGMAS;
        foreach ($values as $pragma => $value) {
            $name = strtolower(trim((string) $pragma));
            if (!array_key_exists($name, self::PRAGMAS)) {
                throw new InvalidArgumentException("Unsupported SQLite boolean PRAGMA {$pragma}");
            }

            $this->values[$name] = self::boolValue($value, "SQLite {$name}");
        }
    }

    /**
     * @return array{
     *     status:string,
     *     pragma:string,
     *     schema:string,
     *     requested:bool|null,
     *     value:int,
     *     changed:bool,
     *     rows:list<array<string,int>>,
     *     reason:string|null,
     *     assignment_returns_rows:false,
     *     dependencies:list<string>
     * }
     */
    public function execute(string $sql): array
    {
        $parsed = self::parse($sql);
        $name = $parsed['pragma'];
        $before = $this->values[$name];
        $after = $before;
        $reason = null;

        if ($parsed['value'] !== null) {
            $requested = $parsed['value'];
            if ($this->transactionActive && $name === 'foreign_keys') {
                $reason = 'foreign_keys_change_ignored_inside_transaction';
            } else {
                $after = $requested;
                $this->values[$name] = $after;
            }
        }

        return [
            'status' => 'ok',
            'pragma' => $name,
            'schema' => $parsed['schema'],
            'requested' => $parsed['value'],
            'value' => $after ? 1 : 0,
            'changed' => $before !== $after,
            'rows' => [[$name => $after ? 1 : 0]],
            'reason' => $reason,
            'assignment_returns_rows' => false,
            'dependencies' => ['sqlite-pragma-connection-boolean-state'],
        ];
    }

    /**
     * @return array{status:string,transaction_active:true}
     */
    public function begin(): array
    {
        if ($this->transactionActive) {
            throw new InvalidArgumentException('SQLite boolean PRAGMA transaction is already active');
        }

        $this->transactionActive = true;

        return ['status' => 'ok', 'transaction_active' => true];
    }

    /**
     * @return array{status:string,transaction_active:false}
     */
    public function commit(): array
    {
        if (!$this->transactionActive) {
            throw new InvalidArgumentException('SQLite boolean PRAGMA transaction is not active');
        }

        $this->transactionActive = false;

        return ['status' => 'ok', 'transaction_active' => false];
    }

    /**
     * @return array{status:string,transaction_active:false}
     */
    public function rollback(): array
    {
        if (!$this->transactionActive) {
            throw new InvalidArgumentException('SQLite boolean PRAGMA transaction is not active');
        }

        $this->transactionActive = false;

        return ['status' => 'ok', 'transaction_active' => false];
    }

    /**
     * @return array<string,bool>
     */
    public function values(): array
    {
        return $this->values;
    }

    /**
     * @return array{schema:string,pragma:string,value:bool|null,has_rhs:bool}
     */
    public static function parse(string $sql): array
    {
        $trimmed = rtrim(trim($sql), " \t\r\n;");
        $identifier = '[A-Za-z_][A-Za-z0-9_]*';
        $value = 'ON|OFF|YES|NO|TRUE|FALSE|[+-]?\d+';
        if (preg_match('/^pragma\s+(?:(?<schema>' . $identifier . ')\s*\.\s*)?(?<pragma>' . implode('|', array_keys(self::PRAGMAS)) . ')(?:\s*(?:=\s*(?<equals>' . $value . ')|\(\s*(?<paren>' . $value . ')\s*\)))?$/i', $trimmed, $matches) !== 1) {
            throw new InvalidArgumentException('Unsupported SQLite boolean PRAGMA SQL');
        }

        $raw = null;
        if (($matches['equals'] ?? '') !== '') {
            $raw = $matches['equals'];
        } elseif (($matches['paren'] ?? '') !== '') {
            $raw = $matches['paren'];
        }

        return [
            'schema' => strtolower(($matches['schema'] ?? '') !== '' ? $matches['schema'] : 'main'),
            'pragma' => strtolower($matches['pragma']),
            'value' => $raw === null ? null : self::boolValue($raw, 'SQLite boolean PRAGMA value'),
            'has_rhs' => $raw !== null,
        ];
    }

    private static function boolValue(bool|int|string $value, string $label): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value !== 0;
        }

        $upper = strtoupper(trim($value));
        return match ($upper) {
            'ON', 'YES', 'TRUE' => true,
            'OFF', 'NO', 'FALSE' => false,
            default => self::integerBool($upper, $label),
        };
    }

    private static function integerBool(string $value, string $label): bool
    {
        if (!preg_match('/^[+-]?\d+$/', $value)) {
            throw new InvalidArgumentException($label . ' must be a boolean keyword or integer');
        }

        return (int) $value !== 0;
    }
}
