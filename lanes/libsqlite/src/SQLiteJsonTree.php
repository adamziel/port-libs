<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteJsonTree
{
    /**
     * @return list<array{key:int|string|null,value:mixed,type:string,atom:mixed,id:int,parent:int|null,fullkey:string,path:string,json:string|SQLiteBlobValue|SQLiteJsonSubtypeValue,root:string}>
     */
    public static function jsonTreeSqlFunction(string $function, string|SQLiteBlobValue|SQLiteJsonSubtypeValue|null $value, string $path = '$'): array
    {
        if (strcasecmp($function, 'json_tree') !== 0) {
            throw new \InvalidArgumentException('SQLite JSON table-valued function must be json_tree');
        }

        return self::jsonTree($value, $path);
    }

    /**
     * @param list<mixed> $arguments
     * @return list<array{key:int|string|null,value:mixed,type:string,atom:mixed,id:int,parent:int|null,fullkey:string,path:string,json:string|SQLiteBlobValue|SQLiteJsonSubtypeValue,root:string}>
     */
    public static function jsonTreeSqlFunctionArguments(string $function, array $arguments): array
    {
        if (count($arguments) < 1 || count($arguments) > 2) {
            throw new \InvalidArgumentException('SQLite json_tree() expects one or two arguments');
        }

        $value = $arguments[0];
        if (!$value instanceof SQLiteBlobValue && !$value instanceof SQLiteJsonSubtypeValue && $value !== null && !is_string($value)) {
            throw new \InvalidArgumentException('SQLite json_tree() JSON argument must be text, BLOB, JSON subtype, or NULL');
        }

        $path = array_key_exists(1, $arguments) ? $arguments[1] : '$';
        if ($path === null) {
            return [];
        }
        if (!is_string($path)) {
            throw new \InvalidArgumentException('SQLite json_tree() path argument must be text');
        }

        return self::jsonTreeSqlFunction($function, $value, $path);
    }

    /**
     * @return list<array{key:int|string|null,value:mixed,type:string,atom:mixed,id:int,parent:int|null,fullkey:string,path:string,json:string|SQLiteBlobValue|SQLiteJsonSubtypeValue,root:string}>
     */
    public static function jsonTree(string|SQLiteBlobValue|SQLiteJsonSubtypeValue|null $value, string $path = '$'): array
    {
        if ($value === null) {
            return [];
        }

        $located = SQLiteJsonInspection::locatePathForJsonEncoding($value, $path);
        if (!$located['found']) {
            return [];
        }

        $rows = [];
        $nextId = 0;
        $rootLocation = self::rootLocation($value, $path);
        self::appendRows($rows, $nextId, $rootLocation['key'], $located['value'], $path, $rootLocation['path'], $value, $path);

        return $rows;
    }

    /**
     * @param list<array{key:int|string|null,value:mixed,type:string,atom:mixed,id:int,parent:int|null,fullkey:string,path:string,json:string|SQLiteBlobValue|SQLiteJsonSubtypeValue,root:string}> $rows
     */
    private static function appendRows(array &$rows, int &$nextId, int|string|null $key, mixed $value, string $fullkey, string $path, string|SQLiteBlobValue|SQLiteJsonSubtypeValue $json, string $root, ?int $parent = null): void
    {
        $id = $nextId++;
        $rows[] = self::row($key, $value, $id, $parent, $fullkey, $path, $json, $root);

        if ($value instanceof \stdClass || (is_array($value) && !array_is_list($value))) {
            foreach (self::objectMembers($value) as $childKey => $child) {
                self::appendRows($rows, $nextId, $childKey, $child, self::appendObjectPath($fullkey, $childKey), $fullkey, $json, $root, $id);
            }

            return;
        }

        if (is_array($value) && array_is_list($value)) {
            foreach ($value as $childKey => $child) {
                self::appendRows($rows, $nextId, $childKey, $child, $fullkey . '[' . $childKey . ']', $fullkey, $json, $root, $id);
            }
        }
    }

    /**
     * @return array{key:int|string|null,value:mixed,type:string,atom:mixed,id:int,parent:int|null,fullkey:string,path:string,json:string|SQLiteBlobValue|SQLiteJsonSubtypeValue,root:string}
     */
    private static function row(int|string|null $key, mixed $value, int $id, ?int $parent, string $fullkey, string $path, string|SQLiteBlobValue|SQLiteJsonSubtypeValue $json, string $root): array
    {
        return [
            'key' => $key,
            'value' => self::sqlValue($value),
            'type' => self::typeName($value),
            'atom' => self::atomValue($value),
            'id' => $id,
            'parent' => $parent,
            'fullkey' => $fullkey,
            'path' => $path,
            'json' => $json,
            'root' => $root,
        ];
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
        if ($value instanceof \stdClass) {
            return 'object';
        }
        if (is_array($value)) {
            return array_is_list($value) ? 'array' : 'object';
        }

        throw new \InvalidArgumentException('SQLite JSON value cannot be classified');
    }

    private static function sqlValue(mixed $value): mixed
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

    private static function atomValue(mixed $value): mixed
    {
        if ($value instanceof \stdClass || is_array($value)) {
            return null;
        }

        return self::sqlValue($value);
    }

    /**
     * @return array<string, mixed>
     */
    private static function objectMembers(\stdClass|array $value): array
    {
        return $value instanceof \stdClass ? get_object_vars($value) : $value;
    }

    private static function appendObjectPath(string $path, string $key): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key) === 1) {
            return $path . '.' . $key;
        }

        return $path . '.' . SQLiteJsonQuote::jsonQuote($key);
    }

    /**
     * @return array{key:int|string|null,path:string}
     */
    private static function rootLocation(string|SQLiteBlobValue|SQLiteJsonSubtypeValue $json, string $path): array
    {
        if ($path === '$') {
            return ['key' => null, 'path' => '$'];
        }

        $segments = self::pathSegments($path);
        if ($segments === []) {
            return ['key' => null, 'path' => '$'];
        }

        $last = $segments[count($segments) - 1];
        $parentPath = count($segments) === 1 ? '$' : substr($path, 0, $last['offset']);

        if ($last['type'] === 'index') {
            if (ctype_digit($last['token'])) {
                return ['key' => (int) $last['token'], 'path' => $parentPath];
            }

            if (preg_match('/^#-(\d+)$/', $last['token'], $matches) === 1) {
                $parent = SQLiteJsonInspection::locatePath($json, $parentPath);
                if (!$parent['found'] || !is_array($parent['value']) || !array_is_list($parent['value'])) {
                    return ['key' => null, 'path' => $parentPath];
                }

                return ['key' => count($parent['value']) - (int) $matches[1], 'path' => $parentPath];
            }

            return ['key' => null, 'path' => $parentPath];
        }

        if ($last['type'] !== 'member') {
            return ['key' => null, 'path' => $path];
        }

        return ['key' => $last['label'], 'path' => $parentPath];
    }

    /**
     * @return list<array{type:string,offset:int,label?:string,token?:string}>
     */
    private static function pathSegments(string $path): array
    {
        $segments = [];
        $offset = 1;
        $length = strlen($path);
        while ($offset < $length) {
            if ($path[$offset] === '.') {
                $segmentOffset = $offset;
                $offset++;
                if ($offset >= $length) {
                    return [];
                }

                if ($path[$offset] === '"') {
                    $end = self::quotedMemberEnd($path, $offset);
                    if ($end === null) {
                        return [];
                    }
                    $label = SQLiteJson5Parser::decode(substr($path, $offset, $end - $offset + 1));
                    if (!is_string($label)) {
                        return [];
                    }
                    $segments[] = ['type' => 'member', 'offset' => $segmentOffset, 'label' => $label];
                    $offset = $end + 1;
                    continue;
                }

                $end = $offset;
                while ($end < $length && $path[$end] !== '.' && $path[$end] !== '[') {
                    $end++;
                }
                if ($end === $offset) {
                    return [];
                }
                $label = SQLiteJsonPath::decodeBareMember(substr($path, $offset, $end - $offset));
                if ($label === null) {
                    return [];
                }
                $segments[] = ['type' => 'member', 'offset' => $segmentOffset, 'label' => $label];
                $offset = $end;
                continue;
            }

            if ($path[$offset] === '[') {
                $segmentOffset = $offset;
                $end = strpos($path, ']', $offset + 1);
                if ($end === false) {
                    return [];
                }
                $segments[] = ['type' => 'index', 'offset' => $segmentOffset, 'token' => substr($path, $offset + 1, $end - $offset - 1)];
                $offset = $end + 1;
                continue;
            }

            return [];
        }

        return $segments;
    }

    private static function quotedMemberEnd(string $path, int $offset): ?int
    {
        $length = strlen($path);
        for ($cursor = $offset + 1; $cursor < $length; $cursor++) {
            if ($path[$cursor] === '\\') {
                if ($cursor + 1 >= $length) {
                    return null;
                }
                $cursor++;
                continue;
            }
            if ($path[$cursor] === '"') {
                return $cursor;
            }
        }

        return null;
    }
}
