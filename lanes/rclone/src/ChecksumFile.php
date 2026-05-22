<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

final class ChecksumFile
{
    /**
     * @return array<string, string>
     */
    public static function parse(string $contents, bool $ignoreCase = false): array
    {
        $hashes = [];
        $lines = preg_split('/\R/', $contents) ?: [];

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }
            if ($ignoreCase) {
                $line = strtolower($line);
            }
            if (!preg_match('/^([^ ]+) [ *](.+)$/', $line, $m)) {
                continue;
            }

            $sum = $m[1];
            $path = $m[2];
            if ($sum === '' || isset($hashes[$path])) {
                continue;
            }

            $hashes[$path] = strtolower($sum);
        }

        return $hashes;
    }

    public static function check(
        MemoryProvider $provider,
        string $contents,
        string $hashType,
        bool $oneWay = false,
        ?FilterRuleSet $filter = null,
        bool $ignoreCase = false,
    ): CheckResult {
        return self::verify($provider, self::parse($contents, $ignoreCase), $hashType, $oneWay, $filter, $ignoreCase);
    }

    public static function checkDownload(
        MemoryProvider $provider,
        string $contents,
        string $hashType,
        bool $oneWay = false,
        ?FilterRuleSet $filter = null,
        bool $ignoreCase = false,
    ): CheckResult {
        return self::verify($provider, self::parse($contents, $ignoreCase), $hashType, $oneWay, $filter, $ignoreCase, true);
    }

    /**
     * @param array<string, string> $sums
     */
    public static function verify(
        MemoryProvider $provider,
        array $sums,
        string $hashType,
        bool $oneWay = false,
        ?FilterRuleSet $filter = null,
        bool $ignoreCase = false,
        bool $download = false,
    ): CheckResult {
        $type = HashType::fromString($hashType);
        if ($type === HashType::NONE) {
            throw new \InvalidArgumentException('checksum verification requires a concrete hash type');
        }
        if (!$download && !$provider->supportsHash($type)) {
            throw new \InvalidArgumentException("hash type {$type} is not supported by provider");
        }

        $expected = [];
        $originalPaths = [];
        foreach ($sums as $path => $sum) {
            $lookup = self::lookupPath($path, $ignoreCase);
            if (array_key_exists($lookup, $expected)) {
                continue;
            }

            $expected[$lookup] = strtolower($sum);
            $originalPaths[$lookup] = $path;
        }

        $matches = [];
        $differ = [];
        $missingOnSource = [];
        $consumed = [];

        foreach ($provider->list() as $object) {
            if ($filter !== null && !$filter->includes($object->path)) {
                continue;
            }

            $lookup = self::lookupPath($object->path, $ignoreCase);
            if (!array_key_exists($lookup, $expected)) {
                if (!$oneWay) {
                    $missingOnSource[] = $object->path;
                }
                continue;
            }

            $consumed[$lookup] = true;
            $actual = self::objectHash($provider, $object->path, $type, $download);
            if ($actual === '' || strtolower($actual) !== $expected[$lookup]) {
                $differ[] = $object->path;
            } else {
                $matches[] = $object->path;
            }
        }

        $missingOnTarget = [];
        foreach ($originalPaths as $lookup => $path) {
            if (isset($consumed[$lookup])) {
                continue;
            }
            if ($filter !== null && !$filter->includes($path)) {
                continue;
            }

            $missingOnTarget[] = $path;
        }

        sort($matches, SORT_STRING);
        sort($differ, SORT_STRING);
        sort($missingOnSource, SORT_STRING);
        sort($missingOnTarget, SORT_STRING);

        return new CheckResult($matches, $differ, $missingOnSource, $missingOnTarget);
    }

    private static function lookupPath(string $path, bool $ignoreCase): string
    {
        return $ignoreCase ? strtolower($path) : $path;
    }

    private static function objectHash(MemoryProvider $provider, string $path, string $type, bool $download): string
    {
        if ($download) {
            return MultiHasher::hashBytes($provider->get($path), new HashSet($type))[$type] ?? '';
        }

        return $provider->hashes($path, new HashSet($type))[$type] ?? '';
    }
}
