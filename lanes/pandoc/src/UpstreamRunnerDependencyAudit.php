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
        $projectPins = self::auditProjectPins(is_file($projectFile) ? (string) file_get_contents($projectFile) : null);

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

        $ready = $blockedReasons === [];

        return [
            'upstreamCommit' => self::UPSTREAM_COMMIT,
            'checkoutPath' => $root,
            'requiredFiles' => self::REQUIRED_FILES,
            'presentFiles' => $presentFiles,
            'missingFiles' => $missingFiles,
            'tools' => $normalizedTools,
            'missingTools' => $missingTools,
            'runnerTargets' => [
                'test:test-pandoc',
                'test:test-pandoc-lua-engine',
            ],
            'runnerEntryPoints' => [
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
            ],
            'projectSourceRepositoryPins' => $projectPins,
            'readyForNonMutatingCabalPlan' => $ready,
            'blockedReasons' => $blockedReasons,
            'nonMutatingPlan' => $ready ? [
                'record cabal.project and package-file hashes before any solver/build command',
                'prepare a bounded Cabal solver plan for test:test-pandoc and test:test-pandoc-lua-engine',
                'only after the plan is reviewed, run a separate bounded runner slice with explicit artifact output paths',
            ] : [],
            'activationGate' => self::activationGate($missingFiles, $missingTools, $projectPins),
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
     * @param list<string> $missingFiles
     * @param list<string> $missingTools
     * @param array{missing:list<string>, mismatched:array<string, array{expected:string, actual:string}>} $projectPins
     */
    private static function activationGate(array $missingFiles, array $missingTools, array $projectPins): string
    {
        if ($missingFiles === [] && $missingTools === [] && $projectPins['missing'] === [] && $projectPins['mismatched'] === []) {
            return 'Hydrated Pandoc checkout and required Cabal toolchain are present; record a non-mutating solver/build plan before any Haskell runner execution.';
        }

        return 'Hydrate Pandoc upstream commit ' . self::UPSTREAM_COMMIT
            . ' with cabal.project, pandoc.cabal, pandoc-lua-engine/pandoc-lua-engine.cabal, test entry points, ghc, cabal, and exact cabal.project Git source-repository pins before attempting a runner plan.';
    }
}
