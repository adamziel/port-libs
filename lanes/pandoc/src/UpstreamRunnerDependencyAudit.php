<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class UpstreamRunnerDependencyAudit
{
    public const UPSTREAM_COMMIT = '0640c4c9859aa5a3ede082c190fcd5883c24ac83';

    private const REQUIRED_FILES = [
        'cabal.project',
        'pandoc.cabal',
        'pandoc-lua-engine/pandoc-lua-engine.cabal',
        'test/test-pandoc.hs',
        'pandoc-lua-engine/test/test-pandoc-lua-engine.hs',
    ];

    private const REQUIRED_TOOLS = [
        'ghc',
        'cabal',
    ];

    private const PROJECT_SOURCE_REPOSITORY_PINS = [
        'doclayout' => 'ef7f18308a61787244a80885d907fcd2c16604d4',
        'typst-symbols' => '6e97668c9f2ffea09f3187c34b7641038370fd21',
        'typst-hs' => '19e835d40663a92df5bed4e8a0fca5465cacdd6b',
        'texmath' => '0a3fbebc5d0e21769f01b048eb63e1451ccf0e1a',
        'citeproc' => '1b684f1e06fc1093d20c1a2d474f4c3fdf2f65bd',
    ];

    private const PROJECT_PACKAGES = [
        '.',
        'pandoc-lua-engine',
        'pandoc-server',
        'pandoc-cli',
    ];

    private const PROJECT_FLAGS = [
        'pandoc' => [
            'embed_data_files' => true,
            'http' => true,
        ],
    ];

    private const RUNNER_ENTRY_POINTS = [
        'test:test-pandoc' => [
            'packageFile' => 'pandoc.cabal',
            'mainIs' => 'test-pandoc.hs',
            'sourceDirectory' => 'test',
        ],
        'test:test-pandoc-lua-engine' => [
            'packageFile' => 'pandoc-lua-engine/pandoc-lua-engine.cabal',
            'mainIs' => 'test-pandoc-lua-engine.hs',
            'sourceDirectory' => 'pandoc-lua-engine/test',
        ],
    ];

    private const RUNNER_DIRECT_DEPENDENCIES = [
        'test:test-pandoc' => [
            'base',
            'pandoc',
            'Diff',
            'Glob',
            'bytestring',
            'containers',
            'directory',
            'doctemplates',
            'filepath',
            'mtl',
            'pandoc-types',
            'process',
            'tasty',
            'tasty-golden',
            'tasty-hunit',
            'tasty-quickcheck',
            'text',
            'temporary',
            'time',
            'xml',
            'zip-archive',
        ],
        'test:test-pandoc-lua-engine' => [
            'base',
            'pandoc-lua-engine',
            'bytestring',
            'directory',
            'data-default',
            'exceptions',
            'filepath',
            'hslua',
            'pandoc',
            'pandoc-types',
            'tasty',
            'tasty-golden',
            'tasty-hunit',
            'tasty-lua',
            'text',
        ],
    ];

    /**
     * @param array<string, string|array{available?: bool, version?: string|null}> $tools
     * @return array{
     *   upstreamCommit:string,
     *   checkoutPath:string,
     *   requiredFiles:list<string>,
     *   presentFiles:list<string>,
     *   missingFiles:list<string>,
     *   tools:array<string, array{available:bool, version:string|null}>,
     *   missingTools:list<string>,
     *   runnerTargets:list<string>,
     *   runnerEntryPoints:array<string, array{packageFile:string, mainIs:string, sourceDirectory:string}>,
     *   projectSourceRepositoryPins:array{expected:array<string, string>, present:array<string, string>, missing:list<string>, mismatched:array<string, array{expected:string, actual:string}>},
     *   projectPackageClosure:array{expectedPackages:list<string>, presentPackages:list<string>, missingPackages:list<string>, expectedFlags:array<string, array<string, bool>>, presentFlags:array<string, array<string, bool>>, missingFlags:array<string, list<string>>, mismatchedFlags:array<string, array<string, array{expected:bool, actual:bool|null}>>},
     *   runnerDependencyClosure:array{expectedDependencies:array<string, list<string>>, present:array<string, array{packageFile:string, mainIs:string|null, sourceDirectories:list<string>, buildDepends:list<string>}>, missingTargets:list<string>, mismatchedEntryPoints:array<string, list<string>>, missingDependencies:array<string, list<string>>},
     *   readyForNonMutatingCabalPlan:bool,
     *   blockedReasons:list<string>,
     *   nonMutatingPlan:list<string>,
     *   activationGate:string
     * }
     */
    public static function auditCheckout(string $checkoutPath, array $tools = []): array
    {
        $root = rtrim($checkoutPath, DIRECTORY_SEPARATOR);
        if ($root === '') {
            $root = '.';
        }

        $presentFiles = [];
        $missingFiles = [];
        foreach (self::REQUIRED_FILES as $relativePath) {
            if (is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath))) {
                $presentFiles[] = $relativePath;
            } else {
                $missingFiles[] = $relativePath;
            }
        }

        $normalizedTools = self::normalizeTools($tools);
        $missingTools = [];
        foreach (self::REQUIRED_TOOLS as $tool) {
            if (($normalizedTools[$tool]['available'] ?? false) !== true) {
                $missingTools[] = $tool;
            }
        }

        $projectFile = $root . DIRECTORY_SEPARATOR . 'cabal.project';
        $projectContents = is_file($projectFile) ? (string) file_get_contents($projectFile) : null;
        $projectPins = self::auditProjectPins($projectContents);
        $projectPackageClosure = self::auditProjectPackageClosure($projectContents);
        $runnerDependencyClosure = self::auditRunnerDependencyClosure($root);

        $blockedReasons = [];
        if ($missingFiles !== []) {
            $blockedReasons[] = 'missing required upstream runner files: ' . implode(', ', $missingFiles);
        }
        if ($missingTools !== []) {
            $blockedReasons[] = 'missing required Cabal toolchain commands: ' . implode(', ', $missingTools);
        }
        if ($projectPins['missing'] !== []) {
            $blockedReasons[] = 'missing cabal.project source-repository pins: ' . implode(', ', $projectPins['missing']);
        }
        if ($projectPins['mismatched'] !== []) {
            $blockedReasons[] = 'mismatched cabal.project source-repository pins: ' . implode(', ', array_keys($projectPins['mismatched']));
        }
        if ($projectPackageClosure['missingPackages'] !== []) {
            $blockedReasons[] = 'missing cabal.project package entries: ' . implode(', ', $projectPackageClosure['missingPackages']);
        }
        if ($projectPackageClosure['missingFlags'] !== []) {
            $blockedReasons[] = 'missing cabal.project package flags: ' . self::formatProjectFlagFailures($projectPackageClosure['missingFlags']);
        }
        if ($projectPackageClosure['mismatchedFlags'] !== []) {
            $blockedReasons[] = 'mismatched cabal.project package flags: ' . self::formatProjectFlagMismatches($projectPackageClosure['mismatchedFlags']);
        }
        if ($runnerDependencyClosure['missingTargets'] !== []) {
            $blockedReasons[] = 'missing Cabal runner test-suite stanzas: ' . implode(', ', $runnerDependencyClosure['missingTargets']);
        }
        if ($runnerDependencyClosure['mismatchedEntryPoints'] !== []) {
            $blockedReasons[] = 'mismatched Cabal runner entry points: ' . self::formatTargetFailures($runnerDependencyClosure['mismatchedEntryPoints']);
        }
        if ($runnerDependencyClosure['missingDependencies'] !== []) {
            $blockedReasons[] = 'missing Cabal runner direct build-depends: ' . self::formatTargetFailures($runnerDependencyClosure['missingDependencies']);
        }

        $ready = $blockedReasons === [];

        return [
            'upstreamCommit' => self::UPSTREAM_COMMIT,
            'checkoutPath' => $root,
            'requiredFiles' => self::REQUIRED_FILES,
            'presentFiles' => $presentFiles,
            'missingFiles' => $missingFiles,
            'tools' => $normalizedTools,
            'missingTools' => $missingTools,
            'runnerTargets' => array_keys(self::RUNNER_ENTRY_POINTS),
            'runnerEntryPoints' => self::RUNNER_ENTRY_POINTS,
            'projectSourceRepositoryPins' => $projectPins,
            'projectPackageClosure' => $projectPackageClosure,
            'runnerDependencyClosure' => $runnerDependencyClosure,
            'readyForNonMutatingCabalPlan' => $ready,
            'blockedReasons' => $blockedReasons,
            'nonMutatingPlan' => $ready ? [
                'record cabal.project package/flag closure and package-file hashes before any solver/build command',
                'record test-suite entry point and direct build-depends closure for test:test-pandoc and test:test-pandoc-lua-engine',
                'prepare a bounded Cabal solver plan for test:test-pandoc and test:test-pandoc-lua-engine',
                'only after the plan is reviewed, run a separate bounded runner slice with explicit artifact output paths',
            ] : [],
            'activationGate' => self::activationGate($missingFiles, $missingTools, $projectPins, $projectPackageClosure, $runnerDependencyClosure),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function expectedProjectPins(): array
    {
        return self::PROJECT_SOURCE_REPOSITORY_PINS;
    }

    /**
     * @return list<string>
     */
    public static function expectedProjectPackages(): array
    {
        return self::PROJECT_PACKAGES;
    }

    /**
     * @return array<string, array<string, bool>>
     */
    public static function expectedProjectFlags(): array
    {
        return self::PROJECT_FLAGS;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedRunnerDependencies(): array
    {
        return self::RUNNER_DIRECT_DEPENDENCIES;
    }

    /**
     * @return list<string>
     */
    public static function parseCabalProjectPackages(string $contents): array
    {
        $rawPackages = '';
        $capturing = false;

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            if (preg_match('/^\s*packages\s*:\s*(.*?)\s*$/', $line, $match) === 1) {
                $rawPackages .= ' ' . $match[1];
                $capturing = true;
                continue;
            }

            if (!$capturing) {
                continue;
            }

            if (trim($line) === '') {
                continue;
            }

            if (preg_match('/^\s+[^\s:]+(?:\s+[^\s:]+)*\s*$/', $line) === 1) {
                $rawPackages .= ' ' . trim($line);
                continue;
            }

            $capturing = false;
        }

        $packages = [];
        foreach (preg_split('/\s+/', trim($rawPackages)) ?: [] as $package) {
            if ($package !== '' && !in_array($package, $packages, true)) {
                $packages[] = $package;
            }
        }

        return $packages;
    }

    /**
     * @return array<string, array<string, bool>>
     */
    public static function parseCabalProjectFlags(string $contents): array
    {
        $flags = [];
        $currentPackage = null;

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            if (preg_match('/^\s*package\s+([A-Za-z0-9_.-]+)\s*$/', $line, $match) === 1) {
                $currentPackage = $match[1];
                $flags[$currentPackage] ??= [];
                continue;
            }

            if ($currentPackage === null) {
                continue;
            }

            if (preg_match('/^\s*flags\s*:\s*(.*?)\s*$/', $line, $match) !== 1) {
                continue;
            }

            foreach (preg_split('/\s+/', trim($match[1])) ?: [] as $token) {
                if (preg_match('/^([+-])([A-Za-z0-9_-]+)$/', $token, $flagMatch) === 1) {
                    $flags[$currentPackage][$flagMatch[2]] = $flagMatch[1] === '+';
                }
            }
        }

        ksort($flags);
        foreach ($flags as &$packageFlags) {
            ksort($packageFlags);
        }
        unset($packageFlags);

        return $flags;
    }

    /**
     * @return array<string, string>
     */
    public static function parseCabalProjectPins(string $contents): array
    {
        $pins = [];
        $current = [];
        $finish = static function (array $block) use (&$pins): void {
            $location = trim((string) ($block['location'] ?? ''));
            $tag = trim((string) ($block['tag'] ?? ''));
            if ($location === '' || $tag === '') {
                return;
            }

            $path = parse_url($location, PHP_URL_PATH);
            if (!is_string($path) || $path === '') {
                $path = $location;
            }

            $repo = strtolower((string) preg_replace('/\.git$/', '', basename($path)));
            if ($repo !== '') {
                $pins[$repo] = $tag;
            }
        };

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            if (preg_match('/^\s*source-repository-package\s*$/', $line) === 1) {
                if ($current !== []) {
                    $finish($current);
                    $current = [];
                }
                $current['source-repository-package'] = 'true';
                continue;
            }

            if ($current === []) {
                continue;
            }

            if (preg_match('/^\s*([A-Za-z0-9_-]+)\s*:\s*(.*?)\s*$/', $line, $match) === 1) {
                $current[strtolower($match[1])] = $match[2];
            }
        }

        if ($current !== []) {
            $finish($current);
        }

        ksort($pins);
        return $pins;
    }

    /**
     * @return array<string, array{mainIs:string|null, sourceDirectories:list<string>, buildDepends:list<string>}>
     */
    public static function parseCabalTestSuites(string $contents): array
    {
        $stanzas = self::parseCabalStanzas($contents);
        $suites = [];

        foreach ($stanzas as $key => $stanza) {
            if ($stanza['type'] !== 'test-suite') {
                continue;
            }

            $fields = self::resolveCabalStanzaFields($key, $stanzas);
            $sourceDirectories = self::splitWords($fields['hs-source-dirs'] ?? '');
            $buildDepends = self::extractCabalDependencyNames($fields['build-depends'] ?? '');

            $suites[$stanza['name']] = [
                'mainIs' => self::firstFieldValue($fields['main-is'] ?? null),
                'sourceDirectories' => $sourceDirectories,
                'buildDepends' => $buildDepends,
            ];
        }

        ksort($suites);
        return $suites;
    }

    /**
     * @param array<string, string|array{available?: bool, version?: string|null}> $tools
     * @return array<string, array{available:bool, version:string|null}>
     */
    private static function normalizeTools(array $tools): array
    {
        $normalized = [];
        foreach (array_unique(array_merge(self::REQUIRED_TOOLS, array_keys($tools))) as $tool) {
            $value = $tools[$tool] ?? ['available' => false, 'version' => null];
            if (is_array($value)) {
                $normalized[$tool] = [
                    'available' => (bool) ($value['available'] ?? false),
                    'version' => isset($value['version']) && is_string($value['version']) ? $value['version'] : null,
                ];
            } else {
                $normalized[$tool] = [
                    'available' => $value !== '',
                    'version' => $value === '' ? null : $value,
                ];
            }
        }

        ksort($normalized);
        return $normalized;
    }

    /**
     * @return array{expected:array<string, string>, present:array<string, string>, missing:list<string>, mismatched:array<string, array{expected:string, actual:string}>}
     */
    private static function auditProjectPins(?string $contents): array
    {
        $present = $contents === null ? [] : self::parseCabalProjectPins($contents);
        $missing = [];
        $mismatched = [];

        foreach (self::PROJECT_SOURCE_REPOSITORY_PINS as $name => $expectedTag) {
            if (!array_key_exists($name, $present)) {
                $missing[] = $name;
                continue;
            }

            if ($present[$name] !== $expectedTag) {
                $mismatched[$name] = [
                    'expected' => $expectedTag,
                    'actual' => $present[$name],
                ];
            }
        }

        return [
            'expected' => self::PROJECT_SOURCE_REPOSITORY_PINS,
            'present' => $present,
            'missing' => $missing,
            'mismatched' => $mismatched,
        ];
    }

    /**
     * @return array{expectedPackages:list<string>, presentPackages:list<string>, missingPackages:list<string>, expectedFlags:array<string, array<string, bool>>, presentFlags:array<string, array<string, bool>>, missingFlags:array<string, list<string>>, mismatchedFlags:array<string, array<string, array{expected:bool, actual:bool|null}>>}
     */
    private static function auditProjectPackageClosure(?string $contents): array
    {
        $presentPackages = $contents === null ? [] : self::parseCabalProjectPackages($contents);
        $presentFlags = $contents === null ? [] : self::parseCabalProjectFlags($contents);
        $missingPackages = [];
        $missingFlags = [];
        $mismatchedFlags = [];

        foreach (self::PROJECT_PACKAGES as $package) {
            if (!in_array($package, $presentPackages, true)) {
                $missingPackages[] = $package;
            }
        }

        foreach (self::PROJECT_FLAGS as $package => $expectedFlags) {
            foreach ($expectedFlags as $flag => $expectedValue) {
                if (!array_key_exists($package, $presentFlags) || !array_key_exists($flag, $presentFlags[$package])) {
                    $missingFlags[$package][] = $flag;
                    continue;
                }

                if ($presentFlags[$package][$flag] !== $expectedValue) {
                    $mismatchedFlags[$package][$flag] = [
                        'expected' => $expectedValue,
                        'actual' => $presentFlags[$package][$flag],
                    ];
                }
            }
        }

        return [
            'expectedPackages' => self::PROJECT_PACKAGES,
            'presentPackages' => $presentPackages,
            'missingPackages' => $missingPackages,
            'expectedFlags' => self::PROJECT_FLAGS,
            'presentFlags' => $presentFlags,
            'missingFlags' => $missingFlags,
            'mismatchedFlags' => $mismatchedFlags,
        ];
    }

    /**
     * @return array{expectedDependencies:array<string, list<string>>, present:array<string, array{packageFile:string, mainIs:string|null, sourceDirectories:list<string>, buildDepends:list<string>}>, missingTargets:list<string>, mismatchedEntryPoints:array<string, list<string>>, missingDependencies:array<string, list<string>>}
     */
    private static function auditRunnerDependencyClosure(string $root): array
    {
        $present = [];
        foreach (self::RUNNER_ENTRY_POINTS as $target => $entryPoint) {
            $packageFile = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $entryPoint['packageFile']);
            if (!is_file($packageFile)) {
                continue;
            }

            $suiteName = substr($target, strlen('test:'));
            $suites = self::parseCabalTestSuites((string) file_get_contents($packageFile));
            if (!array_key_exists($suiteName, $suites)) {
                continue;
            }

            $present[$target] = [
                'packageFile' => $entryPoint['packageFile'],
                'mainIs' => $suites[$suiteName]['mainIs'],
                'sourceDirectories' => $suites[$suiteName]['sourceDirectories'],
                'buildDepends' => $suites[$suiteName]['buildDepends'],
            ];
        }

        $missingTargets = [];
        $mismatchedEntryPoints = [];
        $missingDependencies = [];

        foreach (self::RUNNER_ENTRY_POINTS as $target => $entryPoint) {
            if (!array_key_exists($target, $present)) {
                $missingTargets[] = $target;
                continue;
            }

            if ($present[$target]['mainIs'] !== $entryPoint['mainIs']) {
                $mismatchedEntryPoints[$target][] = 'main-is expected ' . $entryPoint['mainIs'] . ', found ' . ($present[$target]['mainIs'] ?? 'none');
            }

            if (!in_array($entryPoint['sourceDirectory'], $present[$target]['sourceDirectories'], true)) {
                $mismatchedEntryPoints[$target][] = 'hs-source-dirs missing ' . $entryPoint['sourceDirectory'];
            }

            foreach (self::RUNNER_DIRECT_DEPENDENCIES[$target] as $dependency) {
                if (!in_array($dependency, $present[$target]['buildDepends'], true)) {
                    $missingDependencies[$target][] = $dependency;
                }
            }
        }

        return [
            'expectedDependencies' => self::RUNNER_DIRECT_DEPENDENCIES,
            'present' => $present,
            'missingTargets' => $missingTargets,
            'mismatchedEntryPoints' => $mismatchedEntryPoints,
            'missingDependencies' => $missingDependencies,
        ];
    }

    /**
     * @return array<string, array{type:string, name:string, fields:array<string, string>}>
     */
    private static function parseCabalStanzas(string $contents): array
    {
        $stanzas = [];
        $currentKey = null;
        $lastField = null;

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            if (preg_match('/^([A-Za-z][A-Za-z0-9-]*)\s+([A-Za-z0-9_.-]+)\s*$/', $line, $match) === 1) {
                $type = strtolower($match[1]);
                if (in_array($type, ['test-suite', 'common'], true)) {
                    $currentKey = $type . ':' . $match[2];
                    $stanzas[$currentKey] = [
                        'type' => $type,
                        'name' => $match[2],
                        'fields' => [],
                    ];
                    $lastField = null;
                    continue;
                }
            }

            if ($currentKey === null) {
                continue;
            }

            if (preg_match('/^\S/', $line) === 1) {
                $currentKey = null;
                $lastField = null;
                continue;
            }

            if (preg_match('/^\s*([A-Za-z0-9_-]+)\s*:\s*(.*?)\s*$/', $line, $match) === 1) {
                $lastField = strtolower($match[1]);
                $stanzas[$currentKey]['fields'][$lastField] = trim($match[2]);
                continue;
            }

            if ($lastField !== null && preg_match('/^\s+(.*?)\s*$/', $line, $match) === 1) {
                $continuation = trim($match[1]);
                if ($continuation !== '') {
                    $stanzas[$currentKey]['fields'][$lastField] .= "\n" . $continuation;
                }
            }
        }

        return $stanzas;
    }

    /**
     * @param array<string, array{type:string, name:string, fields:array<string, string>}> $stanzas
     * @param array<string, bool> $seen
     * @return array<string, string>
     */
    private static function resolveCabalStanzaFields(string $key, array $stanzas, array $seen = []): array
    {
        if (!array_key_exists($key, $stanzas) || array_key_exists($key, $seen)) {
            return [];
        }

        $seen[$key] = true;
        $fields = [];
        foreach (self::parseCabalImportNames($stanzas[$key]['fields']['import'] ?? '') as $importName) {
            $importFields = self::resolveCabalStanzaFields('common:' . $importName, $stanzas, $seen);
            $fields = self::mergeCabalFields($fields, $importFields);
        }

        return self::mergeCabalFields($fields, $stanzas[$key]['fields']);
    }

    /**
     * @return list<string>
     */
    private static function parseCabalImportNames(string $raw): array
    {
        $names = [];
        foreach (preg_split('/[\s,]+/', trim($raw)) ?: [] as $name) {
            if ($name !== '' && !in_array($name, $names, true)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * @param array<string, string> $base
     * @param array<string, string> $next
     * @return array<string, string>
     */
    private static function mergeCabalFields(array $base, array $next): array
    {
        foreach ($next as $field => $value) {
            if ($field === 'build-depends' && array_key_exists($field, $base) && $base[$field] !== '') {
                $base[$field] .= ",\n" . $value;
                continue;
            }

            if ($field === 'hs-source-dirs' && array_key_exists($field, $base) && $base[$field] !== '') {
                $base[$field] .= "\n" . $value;
                continue;
            }

            $base[$field] = $value;
        }

        return $base;
    }

    /**
     * @return list<string>
     */
    private static function extractCabalDependencyNames(string $raw): array
    {
        $dependencies = [];
        foreach (explode(',', str_replace("\n", ' ', $raw)) as $part) {
            $part = trim(preg_replace('/--.*$/', '', $part) ?? $part);
            if ($part === '') {
                continue;
            }

            if (preg_match('/^([A-Za-z][A-Za-z0-9_-]*)\b/', $part, $match) === 1 && !in_array($match[1], $dependencies, true)) {
                $dependencies[] = $match[1];
            }
        }

        sort($dependencies);
        return $dependencies;
    }

    /**
     * @return list<string>
     */
    private static function splitWords(string $raw): array
    {
        $words = [];
        foreach (preg_split('/\s+/', trim(str_replace("\n", ' ', $raw))) ?: [] as $word) {
            if ($word !== '' && !in_array($word, $words, true)) {
                $words[] = $word;
            }
        }

        return $words;
    }

    private static function firstFieldValue(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $values = self::splitWords($raw);
        return $values[0] ?? null;
    }

    /**
     * @param array<string, list<string>> $failures
     */
    private static function formatTargetFailures(array $failures): string
    {
        $parts = [];
        foreach ($failures as $target => $items) {
            $parts[] = $target . ' (' . implode(', ', $items) . ')';
        }

        return implode('; ', $parts);
    }

    /**
     * @param array<string, list<string>> $missingFlags
     */
    private static function formatProjectFlagFailures(array $missingFlags): string
    {
        $parts = [];
        foreach ($missingFlags as $package => $flags) {
            $parts[] = $package . ' (' . implode(', ', $flags) . ')';
        }

        return implode('; ', $parts);
    }

    /**
     * @param array<string, array<string, array{expected:bool, actual:bool|null}>> $mismatchedFlags
     */
    private static function formatProjectFlagMismatches(array $mismatchedFlags): string
    {
        $parts = [];
        foreach ($mismatchedFlags as $package => $flags) {
            foreach ($flags as $flag => $state) {
                $parts[] = $package . ':' . $flag . ' expected ' . ($state['expected'] ? '+' : '-') . ', found ' . ($state['actual'] === true ? '+' : '-');
            }
        }

        return implode('; ', $parts);
    }

    /**
     * @param list<string> $missingFiles
     * @param list<string> $missingTools
     * @param array{missing:list<string>, mismatched:array<string, array{expected:string, actual:string}>} $projectPins
     * @param array{missingPackages:list<string>, missingFlags:array<string, list<string>>, mismatchedFlags:array<string, array<string, array{expected:bool, actual:bool|null}>>} $projectPackageClosure
     * @param array{missingTargets:list<string>, mismatchedEntryPoints:array<string, list<string>>, missingDependencies:array<string, list<string>>} $runnerDependencyClosure
     */
    private static function activationGate(array $missingFiles, array $missingTools, array $projectPins, array $projectPackageClosure, array $runnerDependencyClosure): string
    {
        if (
            $missingFiles === []
            && $missingTools === []
            && $projectPins['missing'] === []
            && $projectPins['mismatched'] === []
            && $projectPackageClosure['missingPackages'] === []
            && $projectPackageClosure['missingFlags'] === []
            && $projectPackageClosure['mismatchedFlags'] === []
            && $runnerDependencyClosure['missingTargets'] === []
            && $runnerDependencyClosure['mismatchedEntryPoints'] === []
            && $runnerDependencyClosure['missingDependencies'] === []
        ) {
            return 'Hydrated Pandoc checkout, required Cabal toolchain, cabal.project package/flag closure, runner test-suite stanzas, direct build-depends, and Git pins are present; record a non-mutating solver/build plan before any Haskell runner execution.';
        }

        return 'Hydrate Pandoc upstream commit ' . self::UPSTREAM_COMMIT
            . ' with cabal.project package entries/flags, pandoc.cabal, pandoc-lua-engine/pandoc-lua-engine.cabal, test entry points, direct runner build-depends, ghc, cabal, and exact cabal.project Git source-repository pins before attempting a runner plan.';
    }
}
