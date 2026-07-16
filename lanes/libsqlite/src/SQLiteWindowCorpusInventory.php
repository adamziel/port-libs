<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWindowCorpusInventory
{
    /**
     * @return list<string>
     */
    public static function defaultScriptNames(): array
    {
        return [
            'window1.test',
            'window2.test',
            'window3.test',
            'window4.test',
            'window5.test',
            'window6.test',
            'window7.test',
            'window8.test',
            'window9.test',
            'windowA.test',
            'windowB.test',
            'windowC.test',
            'windowD.test',
            'windowE.test',
            'windowerr.test',
            'windowfault.test',
            'windowpushd.test',
            'filter1.test',
            'filter2.test',
            'filterfault.test',
        ];
    }

    /**
     * @return list<array{script:string,id:string,command:string,line:int,dynamic:bool}>
     */
    public static function parseScript(string $scriptName, string $source): array
    {
        if ($scriptName === '' || !str_ends_with($scriptName, '.test')) {
            throw new \InvalidArgumentException('SQLite window corpus inventory requires a .test script name');
        }

        $tests = [];
        $lines = preg_split('/\R/', $source) ?: [];
        foreach ($lines as $index => $line) {
            if (preg_match('/^\s*(do_(?:execsql|catchsql)_test)\s+(\{[^}\r\n]+\}|"[^"\r\n]+"|[^\s\{]+)/', $line, $matches) !== 1) {
                continue;
            }

            $id = trim($matches[2]);
            if ((str_starts_with($id, '{') && str_ends_with($id, '}')) || (str_starts_with($id, '"') && str_ends_with($id, '"'))) {
                $id = substr($id, 1, -1);
            }

            $tests[] = [
                'script' => $scriptName,
                'id' => $id,
                'command' => $matches[1],
                'line' => $index + 1,
                'dynamic' => self::isDynamicTclId($id),
            ];
        }

        return $tests;
    }

    /**
     * @param list<string>|null $scriptNames
     * @return array{status:string,script_count:int,test_count:int,dynamic_id_count:int,missing_scripts:list<string>,by_script:array<string,int>,tests:list<array{script:string,id:string,command:string,line:int,dynamic:bool}>}
     */
    public static function inventory(string $testDirectory, ?array $scriptNames = null): array
    {
        $scripts = $scriptNames ?? self::defaultScriptNames();
        $tests = [];
        $missing = [];
        $byScript = [];

        foreach ($scripts as $script) {
            $path = rtrim($testDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $script;
            if (!is_file($path)) {
                $missing[] = $script;
                $byScript[$script] = 0;
                continue;
            }

            $rows = self::parseScript($script, (string) file_get_contents($path));
            $byScript[$script] = count($rows);
            array_push($tests, ...$rows);
        }

        return [
            'status' => $missing === [] ? 'ready' : 'blocked-missing-scripts',
            'script_count' => count($scripts) - count($missing),
            'test_count' => count($tests),
            'dynamic_id_count' => count(array_filter($tests, static fn (array $row): bool => $row['dynamic'])),
            'missing_scripts' => $missing,
            'by_script' => $byScript,
            'tests' => $tests,
        ];
    }

    /**
     * @param list<string> $paths
     * @return array{status:string,total:int,covered:int,uncovered:int,coverage_by_script:array<string,array{total:int,covered:int,uncovered:int}>,uncovered_tests:list<array{script:string,id:string,command:string,line:int,dynamic:bool}>,ownership_file_count:int}
     */
    public static function coverageReport(string $testDirectory, array $paths, ?array $scriptNames = null): array
    {
        $inventory = self::inventory($testDirectory, $scriptNames);
        $citations = self::ownershipCitations($paths, $scriptNames ?? self::defaultScriptNames());

        $coverageByScript = [];
        $uncovered = [];
        $covered = 0;
        foreach ($inventory['tests'] as $test) {
            $script = $test['script'];
            $coverageByScript[$script] ??= ['total' => 0, 'covered' => 0, 'uncovered' => 0];
            $coverageByScript[$script]['total']++;

            if (self::isCovered($script, $test['id'], $citations['by_script'][$script] ?? [])) {
                $coverageByScript[$script]['covered']++;
                $covered++;
                continue;
            }

            $coverageByScript[$script]['uncovered']++;
            $uncovered[] = $test;
        }

        return [
            'status' => $inventory['status'] === 'ready' ? 'ready' : 'blocked-missing-scripts',
            'total' => $inventory['test_count'],
            'covered' => $covered,
            'uncovered' => count($uncovered),
            'coverage_by_script' => $coverageByScript,
            'uncovered_tests' => $uncovered,
            'ownership_file_count' => $citations['file_count'],
        ];
    }

    /**
     * @param list<string> $paths
     * @param list<string> $scriptNames
     * @return array{file_count:int,by_script:array<string,list<array{kind:string,id?:string,start?:string,end?:string,source:string}>>}
     */
    public static function ownershipCitations(array $paths, array $scriptNames): array
    {
        $byScript = [];
        $fileCount = 0;
        foreach (self::files($paths) as $file) {
            $text = (string) file_get_contents($file);
            $matchedFile = false;
            foreach ($scriptNames as $script) {
                if (!str_contains($text, $script)) {
                    continue;
                }

                $entries = self::citationsForScript($script, $text, $file);
                if ($entries === []) {
                    continue;
                }
                $matchedFile = true;
                $byScript[$script] ??= [];
                array_push($byScript[$script], ...$entries);
            }
            if ($matchedFile) {
                $fileCount++;
            }
        }

        return ['file_count' => $fileCount, 'by_script' => $byScript];
    }

    private static function isDynamicTclId(string $id): bool
    {
        return str_contains($id, '$') || str_contains($id, '[') || str_contains($id, ']');
    }

    /**
     * @return list<string>
     */
    private static function files(array $paths): array
    {
        $files = [];
        foreach ($paths as $path) {
            if (is_file($path)) {
                $files[] = $path;
                continue;
            }
            if (!is_dir($path)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $fileInfo) {
                if (!$fileInfo instanceof \SplFileInfo || !$fileInfo->isFile()) {
                    continue;
                }
                $extension = strtolower($fileInfo->getExtension());
                if (in_array($extension, ['php', 'md'], true)) {
                    $files[] = $fileInfo->getPathname();
                }
            }
        }

        sort($files, SORT_STRING);

        return $files;
    }

    /**
     * @return list<array{kind:string,id?:string,start?:string,end?:string,source:string}>
     */
    private static function citationsForScript(string $script, string $text, string $file): array
    {
        $entries = [];
        $quoted = preg_quote($script, '/');

        if (preg_match_all('/' . $quoted . '(?::|\s+)([A-Za-z0-9_.$-]+)\s*(?:-|through|to|\.\.)\s*([A-Za-z0-9_.$-]+)/i', $text, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                $entries[] = [
                    'kind' => 'range',
                    'start' => self::normalizeCitationId($match[1]),
                    'end' => self::normalizeCitationId($match[2]),
                    'source' => $file,
                ];
            }
        }

        if (preg_match_all('/' . $quoted . '(?::|\s+)([A-Za-z0-9_.$-]+)/', $text, $matches, PREG_SET_ORDER) !== false) {
            foreach ($matches as $match) {
                $entries[] = [
                    'kind' => 'exact',
                    'id' => self::normalizeCitationId($match[1]),
                    'source' => $file,
                ];
            }
        }

        return $entries;
    }

    /**
     * @param list<array{kind:string,id?:string,start?:string,end?:string,source:string}> $citations
     */
    private static function isCovered(string $script, string $id, array $citations): bool
    {
        foreach ($citations as $citation) {
            if (($citation['kind'] ?? '') === 'exact' && ($citation['id'] ?? null) === $id) {
                return true;
            }
            if (($citation['kind'] ?? '') === 'range' && self::idInRange($id, (string) ($citation['start'] ?? ''), (string) ($citation['end'] ?? ''))) {
                return true;
            }
        }

        return false;
    }

    private static function idInRange(string $id, string $start, string $end): bool
    {
        $left = self::idSortKey($start);
        $current = self::idSortKey($id);
        $right = self::idSortKey($end);
        if ($left === null || $current === null || $right === null) {
            return false;
        }

        return $current >= $left && $current <= $right;
    }

    /**
     * @return list<int>|null
     */
    private static function idSortKey(string $id): ?array
    {
        if (self::isDynamicTclId($id)) {
            return null;
        }
        if (preg_match('/^(?:[A-Za-z0-9_]+-)?([0-9]+(?:\.[0-9]+)*)([A-Za-z]?)$/', $id, $match) !== 1) {
            return null;
        }

        $parts = array_map('intval', explode('.', $match[1]));
        while (count($parts) < 5) {
            $parts[] = -1;
        }
        $suffix = $match[2] === '' ? -1 : ord(strtolower($match[2])) - 96;
        $parts[] = $suffix;

        return $parts;
    }

    private static function normalizeCitationId(string $id): string
    {
        return rtrim($id, '.,;:)');
    }
}
