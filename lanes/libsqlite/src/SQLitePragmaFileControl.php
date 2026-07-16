<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaFileControl
{
    /** @var array<int, string> */
    private array $codeMessages;

    /**
     * @param array<int, string> $codeMessages
     */
    public function __construct(
        private string $filename,
        array $codeMessages = [],
    ) {
        $filename = trim($filename);
        if ($filename === '') {
            throw new InvalidArgumentException('SQLite PRAGMA file-control filename cannot be empty');
        }

        $this->filename = $filename;
        $this->codeMessages = $codeMessages + [
            1 => 'SQL logic error',
            5 => 'database is locked',
            7 => 'out of memory',
            8 => 'attempt to write a readonly database',
            14 => 'unable to open database file',
        ];
    }

    /**
     * @return array{
     *     status:'ok'|'error',
     *     pragma:'error'|'filename',
     *     schema:string,
     *     value:string|null,
     *     code:int|null,
     *     message:string|null,
     *     filename:string|null,
     *     tail:string|null,
     *     rows:list<array<string,string>>,
     *     catchsql:array{0:int,1:mixed},
     *     dependencies:list<string>
     * }
     */
    public function execute(string $sql): array
    {
        $parsed = self::parse($sql);

        if ($parsed['pragma'] === 'filename') {
            return [
                'status' => 'ok',
                'pragma' => 'filename',
                'schema' => $parsed['schema'],
                'value' => null,
                'code' => null,
                'message' => null,
                'filename' => $this->filename,
                'tail' => basename($this->filename),
                'rows' => [['filename' => $this->filename]],
                'catchsql' => [0, [$this->filename]],
                'dependencies' => ['sqlite-vfs-file-control-pragma'],
            ];
        }

        $error = $this->errorFromValue($parsed['value']);

        return [
            'status' => 'error',
            'pragma' => 'error',
            'schema' => $parsed['schema'],
            'value' => $parsed['value'],
            'code' => $error['code'],
            'message' => $error['message'],
            'filename' => null,
            'tail' => null,
            'rows' => [],
            'catchsql' => [1, $error['message']],
            'dependencies' => ['sqlite-vfs-file-control-pragma'],
        ];
    }

    /**
     * @return array{schema:string, pragma:'error'|'filename', value:string|null}
     */
    public static function parse(string $sql): array
    {
        $trimmed = rtrim(trim($sql), " \t\r\n;");
        $identifier = '[A-Za-z_][A-Za-z0-9_]*';
        if (!preg_match('/^pragma\s+(?:(?<schema>' . $identifier . ')\s*\.\s*)?(?<pragma>error|filename)(?:\s*(?:=\s*(?<equals>.+)|\(\s*(?<paren>.*?)\s*\)))?$/i', $trimmed, $matches)) {
            throw new InvalidArgumentException('Unsupported SQLite VFS PRAGMA file-control SQL');
        }

        $value = null;
        if (($matches['equals'] ?? '') !== '') {
            $value = self::unquoteValue(trim($matches['equals']));
        } elseif (($matches['paren'] ?? '') !== '') {
            $value = self::unquoteValue(trim($matches['paren']));
        }

        return [
            'schema' => strtolower(($matches['schema'] ?? '') !== '' ? $matches['schema'] : 'main'),
            'pragma' => strtolower($matches['pragma']),
            'value' => $value,
        ];
    }

    /**
     * @return array{code:int,message:string}
     */
    private function errorFromValue(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return ['code' => 1, 'message' => $this->codeMessages[1]];
        }

        $trimmed = trim($value);
        if (preg_match('/^(?<code>[0-9]+)(?:\s+(?<message>.*))?$/', $trimmed, $matches) === 1) {
            $code = (int) $matches['code'];
            $message = trim($matches['message'] ?? '');
            if ($message === '') {
                $message = $this->codeMessages[$code] ?? $this->codeMessages[1];
            }

            return ['code' => $code, 'message' => $message];
        }

        return ['code' => 1, 'message' => $trimmed];
    }

    private static function unquoteValue(string $value): string
    {
        $length = strlen($value);
        if ($length >= 2) {
            $first = $value[0];
            $last = $value[$length - 1];
            if (($first === "'" && $last === "'") || ($first === '"' && $last === '"')) {
                $inner = substr($value, 1, -1);
                return str_replace($first . $first, $first, $inner);
            }
        }

        return $value;
    }
}
