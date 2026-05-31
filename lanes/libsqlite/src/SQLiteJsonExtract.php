<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteJsonExtract
{
    public static function extractSqlFunction(string $function, string|SQLiteBlobValue|SQLiteJsonSubtypeValue|null $value, string ...$paths): mixed
    {
        if ($function === 'json_extract') {
            return self::extract($value, ...$paths);
        }
        if ($function !== 'jsonb_extract') {
            throw new \InvalidArgumentException('SQLite JSON extract function must be json_extract or jsonb_extract');
        }
        if ($value === null) {
            return null;
        }
        if ($paths === []) {
            throw new \InvalidArgumentException('SQLite json_extract() requires at least one path');
        }

        $located = array_map(
            static fn (string $path): array => SQLiteJsonInspection::locatePath($value, $path),
            $paths,
        );

        if (count($paths) === 1) {
            return self::sqliteSinglePathJsonbValue($located[0]);
        }

        return new SQLiteBlobValue(SQLiteJsonB::encode(array_map(
            static fn (array $result): mixed => $result['found'] ? $result['value'] : null,
            $located,
        )));
    }

    public static function extract(string|SQLiteBlobValue|SQLiteJsonSubtypeValue|null $value, string ...$paths): mixed
    {
        if ($value === null) {
            return null;
        }
        if ($paths === []) {
            throw new \InvalidArgumentException('SQLite json_extract() requires at least one path');
        }

        $located = array_map(
            static fn (string $path): array => SQLiteJsonInspection::locatePath($value, $path),
            $paths,
        );

        if (count($paths) === 1) {
            return self::sqliteSinglePathValue($located[0]);
        }

        return SQLiteJsonCanonical::encodeDecodedJson(array_map(
            static fn (array $result): mixed => $result['found'] ? $result['value'] : null,
            $located,
        ));
    }

    public static function extractJsonArgument(string|SQLiteBlobValue|SQLiteJsonSubtypeValue|null $value, string ...$paths): mixed
    {
        if ($value === null) {
            return null;
        }
        if ($paths === []) {
            throw new \InvalidArgumentException('SQLite json_extract() requires at least one path');
        }

        $located = array_map(
            static fn (string $path): array => SQLiteJsonInspection::locatePath($value, $path),
            $paths,
        );

        if (count($paths) === 1) {
            return self::sqliteSinglePathJsonArgument($located[0]);
        }

        return new SQLiteJsonSubtypeValue(SQLiteJsonCanonical::encodeDecodedJson(array_map(
            static fn (array $result): mixed => $result['found'] ? $result['value'] : null,
            $located,
        )));
    }

    public static function extractJsonArgumentSqlFunction(string $function, string|SQLiteBlobValue|SQLiteJsonSubtypeValue|null $value, string ...$paths): mixed
    {
        if ($function === 'json_extract') {
            return self::extractJsonArgument($value, ...$paths);
        }
        if ($function !== 'jsonb_extract') {
            throw new \InvalidArgumentException('SQLite JSON extract function must be json_extract or jsonb_extract');
        }
        if ($value === null) {
            return null;
        }
        if ($paths === []) {
            throw new \InvalidArgumentException('SQLite json_extract() requires at least one path');
        }

        $located = array_map(
            static fn (string $path): array => SQLiteJsonInspection::locatePath($value, $path),
            $paths,
        );

        if (count($paths) === 1) {
            return self::sqliteSinglePathJsonbArgument($located[0]);
        }

        return new SQLiteBlobValue(SQLiteJsonB::encode(array_map(
            static fn (array $result): mixed => $result['found'] ? $result['value'] : null,
            $located,
        )));
    }

    /**
     * @param array{found:bool,value:mixed} $located
     */
    private static function sqliteSinglePathValue(array $located): mixed
    {
        if (!$located['found']) {
            return null;
        }

        $value = $located['value'];
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

    /**
     * @param array{found:bool,value:mixed} $located
     */
    private static function sqliteSinglePathJsonArgument(array $located): mixed
    {
        if (!$located['found']) {
            return null;
        }

        $value = $located['value'];
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

    /**
     * @param array{found:bool,value:mixed} $located
     */
    private static function sqliteSinglePathJsonbValue(array $located): mixed
    {
        if (!$located['found']) {
            return null;
        }

        $value = $located['value'];
        if ($value === true) {
            return 1;
        }
        if ($value === false) {
            return 0;
        }
        if ($value === null || is_int($value) || is_float($value) || is_string($value)) {
            return $value;
        }

        return new SQLiteBlobValue(SQLiteJsonB::encode($value));
    }

    /**
     * @param array{found:bool,value:mixed} $located
     */
    private static function sqliteSinglePathJsonbArgument(array $located): mixed
    {
        if (!$located['found']) {
            return null;
        }

        $value = $located['value'];
        if ($value === true) {
            return 1;
        }
        if ($value === false) {
            return 0;
        }
        if ($value === null || is_int($value) || is_float($value) || is_string($value)) {
            return $value;
        }

        return new SQLiteBlobValue(SQLiteJsonB::encode($value));
    }
}
