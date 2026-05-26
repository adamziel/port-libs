<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteFileUri
{
    private const VALID_MODES = ['ro' => true, 'rw' => true, 'rwc' => true, 'memory' => true];
    private const VALID_CACHES = ['shared' => true, 'private' => true];

    /**
     * @return array{is_uri:bool,input:string,path:string,authority:string|null,mode:string|null,cache:string|null,immutable:bool|null,nolock:bool|null,psow:bool|null,vfs:string|null,known_parameters:array<string, string>,unknown_parameters:array<string, string>,all_query_parameters:array<string, list<string>>}
     */
    public static function parse(string $filename): array
    {
        if (!str_starts_with($filename, 'file:')) {
            return self::plainFilename($filename);
        }

        $tail = substr($filename, 5);
        [$target, $query] = self::splitTargetAndQuery($tail);
        [$authority, $pathPart] = self::splitAuthorityAndPath($target);
        if ($authority !== null && $authority !== '' && strcasecmp($authority, 'localhost') !== 0) {
            throw new \InvalidArgumentException("Unsupported SQLite file URI authority: {$authority}");
        }

        $path = self::decodePercent($pathPart === '' ? '' : $pathPart);
        $parameters = self::parseQuery($query);
        $known = [];
        $unknown = [];
        foreach ($parameters as $name => $values) {
            $value = $values[array_key_last($values)];
            if (in_array($name, ['mode', 'cache', 'immutable', 'nolock', 'psow', 'vfs'], true)) {
                $known[$name] = $value;
            } else {
                $unknown[$name] = $value;
            }
        }

        $mode = $known['mode'] ?? null;
        if ($mode !== null && !isset(self::VALID_MODES[$mode])) {
            throw new \InvalidArgumentException("Unsupported SQLite file URI mode: {$mode}");
        }

        $cache = $known['cache'] ?? null;
        if ($cache !== null && !isset(self::VALID_CACHES[$cache])) {
            throw new \InvalidArgumentException("Unsupported SQLite file URI cache mode: {$cache}");
        }

        return [
            'is_uri' => true,
            'input' => $filename,
            'path' => $path,
            'authority' => $authority,
            'mode' => $mode,
            'cache' => $cache,
            'immutable' => array_key_exists('immutable', $known) ? self::booleanParameter('immutable', $known['immutable']) : null,
            'nolock' => array_key_exists('nolock', $known) ? self::booleanParameter('nolock', $known['nolock']) : null,
            'psow' => array_key_exists('psow', $known) ? self::booleanParameter('psow', $known['psow']) : null,
            'vfs' => $known['vfs'] ?? null,
            'known_parameters' => $known,
            'unknown_parameters' => $unknown,
            'all_query_parameters' => $parameters,
        ];
    }

    /**
     * @return array{is_uri:bool,input:string,path:string,authority:null,mode:null,cache:null,immutable:null,nolock:null,psow:null,vfs:null,known_parameters:array<string, string>,unknown_parameters:array<string, string>,all_query_parameters:array<string, list<string>>}
     */
    private static function plainFilename(string $filename): array
    {
        return [
            'is_uri' => false,
            'input' => $filename,
            'path' => $filename,
            'authority' => null,
            'mode' => null,
            'cache' => null,
            'immutable' => null,
            'nolock' => null,
            'psow' => null,
            'vfs' => null,
            'known_parameters' => [],
            'unknown_parameters' => [],
            'all_query_parameters' => [],
        ];
    }

    /**
     * @return array{0:string, 1:string}
     */
    private static function splitTargetAndQuery(string $tail): array
    {
        $queryOffset = strpos($tail, '?');
        if ($queryOffset === false) {
            return [$tail, ''];
        }

        return [substr($tail, 0, $queryOffset), substr($tail, $queryOffset + 1)];
    }

    /**
     * @return array{0:string|null, 1:string}
     */
    private static function splitAuthorityAndPath(string $target): array
    {
        if (!str_starts_with($target, '//')) {
            return [null, $target];
        }

        $withoutSlashes = substr($target, 2);
        $slashOffset = strpos($withoutSlashes, '/');
        if ($slashOffset === false) {
            return [$withoutSlashes, ''];
        }

        return [substr($withoutSlashes, 0, $slashOffset), substr($withoutSlashes, $slashOffset)];
    }

    /**
     * @return array<string, list<string>>
     */
    private static function parseQuery(string $query): array
    {
        if ($query === '') {
            return [];
        }

        $parameters = [];
        foreach (explode('&', $query) as $part) {
            [$name, $value] = array_pad(explode('=', $part, 2), 2, '');
            if ($name === '') {
                throw new \InvalidArgumentException('SQLite file URI query parameter name cannot be empty');
            }

            $decodedName = self::decodePercent($name);
            $decodedValue = self::decodePercent($value);
            $parameters[$decodedName] ??= [];
            $parameters[$decodedName][] = $decodedValue;
        }

        return $parameters;
    }

    private static function decodePercent(string $value): string
    {
        if (preg_match('/%(?![0-9A-Fa-f]{2})/', $value) === 1) {
            throw new \InvalidArgumentException("Malformed percent escape in SQLite file URI component: {$value}");
        }

        return rawurldecode($value);
    }

    private static function booleanParameter(string $name, string $value): bool
    {
        return match ($value) {
            '0' => false,
            '1' => true,
            default => throw new \InvalidArgumentException("SQLite file URI {$name} expects 0 or 1"),
        };
    }
}
