<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteJsonEach
{
    /**
     * @return list<array{key:int|string|null,value:mixed,type:string,atom:mixed,id:int,parent:null,fullkey:string,path:string,json:string|SQLiteBlobValue|SQLiteJsonSubtypeValue,root:string}>
     */
    public static function jsonEachSqlFunction(string $function, string|SQLiteBlobValue|SQLiteJsonSubtypeValue|null $value, string $path = '$'): array
    {
        if (strcasecmp($function, 'json_each') !== 0) {
            throw new \InvalidArgumentException('SQLite JSON table-valued function must be json_each');
        }

        return self::jsonEach($value, $path);
    }

    /**
     * @param list<mixed> $arguments
     * @return list<array{key:int|string|null,value:mixed,type:string,atom:mixed,id:int,parent:null,fullkey:string,path:string,json:string|SQLiteBlobValue|SQLiteJsonSubtypeValue,root:string}>
     */
    public static function jsonEachSqlFunctionArguments(string $function, array $arguments): array
    {
        if (count($arguments) < 1 || count($arguments) > 2) {
            throw new \InvalidArgumentException('SQLite json_each() expects one or two arguments');
        }

        $value = $arguments[0];
        if (!$value instanceof SQLiteBlobValue && !$value instanceof SQLiteJsonSubtypeValue && $value !== null && !is_string($value)) {
            throw new \InvalidArgumentException('SQLite json_each() JSON argument must be text, BLOB, JSON subtype, or NULL');
        }

        $path = array_key_exists(1, $arguments) ? $arguments[1] : '$';
        if ($path === null) {
            return [];
        }
        if (!is_string($path)) {
            throw new \InvalidArgumentException('SQLite json_each() path argument must be text');
        }

        return self::jsonEachSqlFunction($function, $value, $path);
    }

    /**
     * @return list<array{key:int|string|null,value:mixed,type:string,atom:mixed,id:int,parent:null,fullkey:string,path:string,json:string|SQLiteBlobValue|SQLiteJsonSubtypeValue,root:string}>
     */
    public static function jsonEach(string|SQLiteBlobValue|SQLiteJsonSubtypeValue|null $value, string $path = '$'): array
    {
        if ($value === null) {
            return [];
        }

        $located = SQLiteJsonInspection::locatePath($value, $path);
        if (!$located['found']) {
            return [];
        }

        $root = $located['value'];
        if ($root instanceof \stdClass || (is_array($root) && !array_is_list($root))) {
            $rows = [];
            $id = 1;
            foreach (self::objectMembers($root) as $key => $child) {
                $rows[] = self::row($key, $child, $id++, null, self::appendObjectPath($path, $key), $path, $value, $path);
            }

            return $rows;
        }

        if (is_array($root) && array_is_list($root)) {
            $rows = [];
            foreach ($root as $key => $child) {
                $rows[] = self::row($key, $child, $key + 1, null, $path . '[' . $key . ']', $path, $value, $path);
            }

            return $rows;
        }

        return [self::row(null, $root, 1, null, $path, $path, $value, $path)];
    }

    /**
     * @return array{key:int|string|null,value:mixed,type:string,atom:mixed,id:int,parent:null,fullkey:string,path:string,json:string|SQLiteBlobValue|SQLiteJsonSubtypeValue,root:string}
     */
    private static function row(int|string|null $key, mixed $value, int $id, null $parent, string $fullkey, string $path, string|SQLiteBlobValue|SQLiteJsonSubtypeValue $json, string $root): array
    {
        $type = self::typeName($value);

        return [
            'key' => $key,
            'value' => self::sqlValue($value),
            'type' => $type,
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

        return new SQLiteJsonSubtypeValue(SQLiteJsonCanonical::encodeDecodedJson($value));
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
}
