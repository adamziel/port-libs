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

    private const REQUIRED_RUNNER_ARTIFACTS = [
        'test/Tests/Command.hs' => 'file',
        'test/Tests/Readers/Markdown.hs' => 'file',
        'test/Tests/Writers/Markdown.hs' => 'file',
        'test/Tests/Writers/Native.hs' => 'file',
        'test/command' => 'directory',
        'test/tables' => 'directory',
        'test/testsuite.txt' => 'file',
        'test/testsuite.native' => 'file',
        'test/markdown-reader-more.txt' => 'file',
        'test/markdown-reader-more.native' => 'file',
        'test/html-reader.html' => 'file',
        'test/html-reader.native' => 'file',
        'pandoc-lua-engine/test' => 'directory',
        'data' => 'directory',
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

    private const PROJECT_SOURCE_REPOSITORIES = [
        'doclayout' => [
            'type' => 'git',
            'location' => 'https://github.com/jgm/doclayout.git',
        ],
        'typst-symbols' => [
            'type' => 'git',
            'location' => 'https://github.com/jgm/typst-symbols.git',
        ],
        'typst-hs' => [
            'type' => 'git',
            'location' => 'https://github.com/jgm/typst-hs.git',
        ],
        'texmath' => [
            'type' => 'git',
            'location' => 'https://github.com/jgm/texmath.git',
        ],
        'citeproc' => [
            'type' => 'git',
            'location' => 'https://github.com/jgm/citeproc.git',
        ],
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

    private const PROJECT_CONSTRAINTS = [
        'auto-update' => '>= 0.2.6',
        'crypton' => '>= 1.1.1',
        'skylighting-format-blaze-html' => '>= 0.1.2',
        'skylighting-format-context' => '>= 0.1.0.2',
    ];

    private const RUNNER_ENTRY_POINTS = [
        'test:test-pandoc' => [
            'packageFile' => 'pandoc.cabal',
            'type' => 'exitcode-stdio-1.0',
            'mainIs' => 'test-pandoc.hs',
            'sourceDirectory' => 'test',
        ],
        'test:test-pandoc-lua-engine' => [
            'packageFile' => 'pandoc-lua-engine/pandoc-lua-engine.cabal',
            'type' => 'exitcode-stdio-1.0',
            'mainIs' => 'test-pandoc-lua-engine.hs',
            'sourceDirectory' => 'pandoc-lua-engine/test',
        ],
    ];

    private const RUNNER_EXECUTABLE_OPTIONS = [
        'test:test-pandoc' => [
            '-rtsopts',
            '-with-rtsopts=-A8m',
            '-threaded',
        ],
        'test:test-pandoc-lua-engine' => [],
    ];

    private const RUNNER_DEFAULT_LANGUAGES = [
        'test:test-pandoc' => 'Haskell2010',
        'test:test-pandoc-lua-engine' => 'Haskell2010',
    ];

    private const RUNNER_OTHER_MODULES = [
        'test:test-pandoc' => [
            'Tests.Command',
            'Tests.Readers.Markdown',
            'Tests.Writers.Markdown',
            'Tests.Writers.Native',
        ],
        'test:test-pandoc-lua-engine' => [
            'Tests.Lua',
            'Tests.Lua.Module',
            'Tests.Lua.Reader',
            'Tests.Lua.Writer',
        ],
    ];

    private const RUNNER_ENTRY_SOURCE_SEMANTICS = [
        'test:test-pandoc' => [
            'entryFile' => 'test/test-pandoc.hs',
            'requiredSnippets' => [
                'sets locale encoding to utf8' => 'setLocaleEncoding utf8',
                'offers --emulate command runner path' => '"--emulate"',
                'uses noEngine for command emulation' => 'convertWithOpts noEngine',
                'runs from upstream test directory' => 'inDirectory "test"',
                'passes executable path into old command tests' => 'getExecutablePath',
                'runs tasty defaultMain' => 'defaultMain $ tests fp',
                'loads command golden tests' => 'Tests.Command.tests',
                'loads markdown reader tests' => 'Tests.Readers.Markdown.tests',
                'loads native writer tests' => 'Tests.Writers.Native.tests',
                'loads markdown writer tests' => 'Tests.Writers.Markdown.tests',
            ],
        ],
        'test:test-pandoc-lua-engine' => [
            'entryFile' => 'pandoc-lua-engine/test/test-pandoc-lua-engine.hs',
            'requiredSnippets' => [
                'runs from lua engine test directory' => 'withCurrentDirectory "test"',
                'runs tasty defaultMain' => 'defaultMain tests',
                'names lua engine tasty group' => 'testGroup "pandoc Lua engine"',
                'loads lua filter tests' => 'Tests.Lua.tests',
                'loads lua module tests' => 'Tests.Lua.Module.tests',
                'loads custom writer tests' => 'Tests.Lua.Writer.tests',
                'loads custom reader tests' => 'Tests.Lua.Reader.tests',
            ],
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

    private const LUA_ENGINE_LIBRARY_DEPENDENCIES = [
        'hslua-module-doclayout',
        'hslua-module-path',
        'hslua-module-system',
        'hslua-module-text',
        'hslua-module-version',
        'hslua-module-zip',
        'lpeg',
        'pandoc-lua-marshal',
    ];

    /**
     * @param array<string, string|array{available?: bool, version?: string|null}> $tools
     * @return array{
     *   upstreamCommit:string,
     *   checkoutPath:string,
     *   requiredFiles:list<string>,
     *   presentFiles:list<string>,
     *   missingFiles:list<string>,
     *   requiredFileProvenance:array{expected:list<string>, present:array<string, array{sha256:string, bytes:int}>, missing:list<string>},
     *   tools:array<string, array{available:bool, version:string|null}>,
     *   missingTools:list<string>,
     *   runnerTargets:list<string>,
     *   runnerEntryPoints:array<string, array{packageFile:string, type:string, mainIs:string, sourceDirectory:string}>,
     *   projectSourceRepositoryPins:array{expected:array<string, string>, present:array<string, string>, missing:list<string>, mismatched:array<string, array{expected:string, actual:string}>},
     *   projectSourceRepositoryClosure:array{expected:array<string, array{type:string, location:string}>, present:array<string, array{type:string|null, location:string, tag:string|null}>, missing:list<string>, mismatched:array<string, array{expected:array{type:string, location:string}, actual:array{type:string|null, location:string}>>},
     *   projectPackageClosure:array{expectedPackages:list<string>, presentPackages:list<string>, missingPackages:list<string>, expectedFlags:array<string, array<string, bool>>, presentFlags:array<string, array<string, bool>>, missingFlags:array<string, list<string>>, mismatchedFlags:array<string, array<string, array{expected:bool, actual:bool|null}>>},
     *   projectConstraintClosure:array{expectedConstraints:array<string, string>, presentConstraints:array<string, string>, missingConstraints:list<string>, mismatchedConstraints:array<string, array{expected:string, actual:string}>},
     *   runnerDependencyClosure:array{expectedDependencies:array<string, list<string>>, expectedExecutableOptions:array<string, list<string>>, expectedDefaultLanguages:array<string, string>, expectedOtherModules:array<string, list<string>>, present:array<string, array{packageFile:string, type:string|null, buildable:bool|null, mainIs:string|null, sourceDirectories:list<string>, buildDepends:list<string>, ghcOptions:list<string>, defaultLanguage:string|null, otherModules:list<string>}>, missingTargets:list<string>, mismatchedEntryPoints:array<string, list<string>>, missingDependencies:array<string, list<string>>, missingExecutableOptions:array<string, list<string>>, mismatchedDefaultLanguages:array<string, array{expected:string, actual:string|null}>, missingOtherModules:array<string, list<string>>},
     *   luaEngineLibraryClosure:array{packageFile:string, expectedDependencies:list<string>, presentDependencies:list<string>, missingDependencies:list<string>},
     *   runnerEntrySourceClosure:array{expected:array<string, array{entryFile:string, requiredSnippets:array<string, string>}>, present:array<string, array{entryFile:string, matchedSnippets:list<string>}>, missingTargets:list<string>, missingSemantics:array<string, list<string>>},
     *   runnerArtifactClosure:array{expected:array<string, string>, present:list<string>, missing:list<string>, wrongType:array<string, array{expected:string, actual:string}>},
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

        $requiredFileProvenance = self::auditRequiredFileProvenance($root);
        $presentFiles = array_keys($requiredFileProvenance['present']);
        $missingFiles = $requiredFileProvenance['missing'];

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
        $projectSourceRepositoryClosure = self::auditProjectSourceRepositoryClosure($projectContents);
        $projectPackageClosure = self::auditProjectPackageClosure($projectContents);
        $projectConstraintClosure = self::auditProjectConstraintClosure($projectContents);
        $runnerDependencyClosure = self::auditRunnerDependencyClosure($root);
        $luaEngineLibraryClosure = self::auditLuaEngineLibraryClosure($root);
        $runnerEntrySourceClosure = self::auditRunnerEntrySourceClosure($root);
        $runnerArtifactClosure = self::auditRunnerArtifactClosure($root);

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
        if ($projectSourceRepositoryClosure['missing'] !== []) {
            $blockedReasons[] = 'missing cabal.project source-repository package locations/types: ' . implode(', ', $projectSourceRepositoryClosure['missing']);
        }
        if ($projectSourceRepositoryClosure['mismatched'] !== []) {
            $blockedReasons[] = 'mismatched cabal.project source-repository package locations/types: ' . implode(', ', array_keys($projectSourceRepositoryClosure['mismatched']));
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
        if ($projectConstraintClosure['missingConstraints'] !== []) {
            $blockedReasons[] = 'missing cabal.project solver constraints: ' . implode(', ', $projectConstraintClosure['missingConstraints']);
        }
        if ($projectConstraintClosure['mismatchedConstraints'] !== []) {
            $blockedReasons[] = 'mismatched cabal.project solver constraints: ' . self::formatProjectConstraintMismatches($projectConstraintClosure['mismatchedConstraints']);
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
        if ($runnerDependencyClosure['missingExecutableOptions'] !== []) {
            $blockedReasons[] = 'missing Cabal runner executable options: ' . self::formatTargetFailures($runnerDependencyClosure['missingExecutableOptions']);
        }
        if ($runnerDependencyClosure['mismatchedDefaultLanguages'] !== []) {
            $blockedReasons[] = 'mismatched Cabal runner default-language: ' . self::formatDefaultLanguageMismatches($runnerDependencyClosure['mismatchedDefaultLanguages']);
        }
        if ($runnerDependencyClosure['missingOtherModules'] !== []) {
            $blockedReasons[] = 'missing Cabal runner other-modules: ' . self::formatTargetFailures($runnerDependencyClosure['missingOtherModules']);
        }
        if ($luaEngineLibraryClosure['missingDependencies'] !== []) {
            $blockedReasons[] = 'missing pandoc-lua-engine library build-depends: ' . implode(', ', $luaEngineLibraryClosure['missingDependencies']);
        }
        if ($runnerEntrySourceClosure['missingTargets'] !== []) {
            $blockedReasons[] = 'missing runner entry point source files: ' . implode(', ', $runnerEntrySourceClosure['missingTargets']);
        }
        if ($runnerEntrySourceClosure['missingSemantics'] !== []) {
            $blockedReasons[] = 'missing runner entry point source semantics: ' . self::formatTargetFailures($runnerEntrySourceClosure['missingSemantics']);
        }
        if ($runnerArtifactClosure['missing'] !== []) {
            $blockedReasons[] = 'missing upstream runner source/golden fixture artifacts: ' . implode(', ', $runnerArtifactClosure['missing']);
        }
        if ($runnerArtifactClosure['wrongType'] !== []) {
            $blockedReasons[] = 'mismatched upstream runner source/golden fixture artifact types: ' . self::formatArtifactTypeMismatches($runnerArtifactClosure['wrongType']);
        }

        $ready = $blockedReasons === [];

        return [
            'upstreamCommit' => self::UPSTREAM_COMMIT,
            'checkoutPath' => $root,
            'requiredFiles' => self::REQUIRED_FILES,
            'presentFiles' => $presentFiles,
            'missingFiles' => $missingFiles,
            'requiredFileProvenance' => $requiredFileProvenance,
            'tools' => $normalizedTools,
            'missingTools' => $missingTools,
            'runnerTargets' => array_keys(self::RUNNER_ENTRY_POINTS),
            'runnerEntryPoints' => self::RUNNER_ENTRY_POINTS,
            'projectSourceRepositoryPins' => $projectPins,
            'projectSourceRepositoryClosure' => $projectSourceRepositoryClosure,
            'projectPackageClosure' => $projectPackageClosure,
            'projectConstraintClosure' => $projectConstraintClosure,
            'runnerDependencyClosure' => $runnerDependencyClosure,
            'luaEngineLibraryClosure' => $luaEngineLibraryClosure,
            'runnerEntrySourceClosure' => $runnerEntrySourceClosure,
            'runnerArtifactClosure' => $runnerArtifactClosure,
            'readyForNonMutatingCabalPlan' => $ready,
            'blockedReasons' => $blockedReasons,
            'nonMutatingPlan' => $ready ? [
                'record cabal.project package/flag closure plus source-repository type/location/tag closure, runner source/golden fixture artifacts, runner entry-point semantics, and package-file hashes before any solver/build command',
                'record cabal.project solver constraints and runner executable options before any solver/build command',
                'record test-suite type, buildable state, default-language, entry point, direct build-depends, and other-modules closure for test:test-pandoc and test:test-pandoc-lua-engine, plus pandoc-lua-engine library HsLua module dependency closure',
                'prepare a bounded Cabal solver plan for test:test-pandoc and test:test-pandoc-lua-engine',
                'only after the plan is reviewed, run a separate bounded runner slice with explicit artifact output paths',
            ] : [],
            'activationGate' => self::activationGate($missingFiles, $missingTools, $projectPins, $projectSourceRepositoryClosure, $projectPackageClosure, $projectConstraintClosure, $runnerDependencyClosure, $luaEngineLibraryClosure, $runnerEntrySourceClosure, $runnerArtifactClosure),
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
     * @return array<string, array{type:string, location:string}>
     */
    public static function expectedProjectSourceRepositories(): array
    {
        return self::PROJECT_SOURCE_REPOSITORIES;
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
     * @return array<string, string>
     */
    public static function expectedProjectConstraints(): array
    {
        return self::PROJECT_CONSTRAINTS;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedRunnerDependencies(): array
    {
        return self::RUNNER_DIRECT_DEPENDENCIES;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedRunnerExecutableOptions(): array
    {
        return self::RUNNER_EXECUTABLE_OPTIONS;
    }

    /**
     * @return array<string, string>
     */
    public static function expectedRunnerDefaultLanguages(): array
    {
        return self::RUNNER_DEFAULT_LANGUAGES;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedRunnerOtherModules(): array
    {
        return self::RUNNER_OTHER_MODULES;
    }

    /**
     * @return list<string>
     */
    public static function expectedLuaEngineLibraryDependencies(): array
    {
        return self::LUA_ENGINE_LIBRARY_DEPENDENCIES;
    }

    /**
     * @return array<string, array{entryFile:string, requiredSnippets:array<string, string>}>
     */
    public static function expectedRunnerEntrySourceSemantics(): array
    {
        return self::RUNNER_ENTRY_SOURCE_SEMANTICS;
    }

    /**
     * @return array<string, string>
     */
    public static function expectedRunnerArtifacts(): array
    {
        return self::REQUIRED_RUNNER_ARTIFACTS;
    }

    /**
     * @return list<string>
     */
    public static function parseCabalProjectPackages(string $contents): array
    {
        $contents = self::stripCabalLineComments($contents);
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
        $contents = self::stripCabalLineComments($contents);
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
    public static function parseCabalProjectConstraints(string $contents): array
    {
        $contents = self::stripCabalLineComments($contents);
        $rawConstraints = '';
        $capturing = false;

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            if (preg_match('/^\s*constraints\s*:\s*(.*?)\s*$/', $line, $match) === 1) {
                $rawConstraints .= ' ' . $match[1];
                $capturing = true;
                continue;
            }

            if (!$capturing) {
                continue;
            }

            if (trim($line) === '') {
                continue;
            }

            if (preg_match('/^\s+(.+?)\s*$/', $line, $match) === 1) {
                $rawConstraints .= ' ' . trim($match[1]);
                continue;
            }

            $capturing = false;
        }

        $constraints = [];
        foreach (explode(',', str_replace("\n", ' ', $rawConstraints)) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            if (preg_match('/^([A-Za-z][A-Za-z0-9_-]*)\s+(.+?)\s*$/', $part, $match) === 1) {
                $constraints[$match[1]] = preg_replace('/\s+/', ' ', trim($match[2])) ?? trim($match[2]);
            }
        }

        ksort($constraints);
        return $constraints;
    }

    /**
     * @return array<string, string>
     */
    public static function parseCabalProjectPins(string $contents): array
    {
        $contents = self::stripCabalLineComments($contents);
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
     * @return array<string, array{type:string|null, location:string, tag:string|null}>
     */
    public static function parseCabalProjectSourceRepositories(string $contents): array
    {
        $contents = self::stripCabalLineComments($contents);
        $repositories = [];
        $current = [];
        $finish = static function (array $block) use (&$repositories): void {
            $location = trim((string) ($block['location'] ?? ''));
            if ($location === '') {
                return;
            }

            $path = parse_url($location, PHP_URL_PATH);
            if (!is_string($path) || $path === '') {
                $path = $location;
            }

            $repo = strtolower((string) preg_replace('/\.git$/', '', basename($path)));
            if ($repo === '') {
                return;
            }

            $type = trim((string) ($block['type'] ?? ''));
            $tag = trim((string) ($block['tag'] ?? ''));
            $repositories[$repo] = [
                'type' => $type === '' ? null : strtolower($type),
                'location' => $location,
                'tag' => $tag === '' ? null : $tag,
            ];
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

        ksort($repositories);
        return $repositories;
    }

    /**
     * @return array<string, array{type:string|null, buildable:bool|null, mainIs:string|null, sourceDirectories:list<string>, buildDepends:list<string>, ghcOptions:list<string>, defaultLanguage:string|null, otherModules:list<string>}>
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
            $ghcOptions = self::splitWords($fields['ghc-options'] ?? '');
            $defaultLanguage = self::firstFieldValue($fields['default-language'] ?? null);
            $otherModules = self::extractCabalModuleNames($fields['other-modules'] ?? '');

            $suites[$stanza['name']] = [
                'type' => self::firstFieldValue($fields['type'] ?? null),
                'buildable' => self::cabalBuildableState($fields['buildable'] ?? null),
                'mainIs' => self::firstFieldValue($fields['main-is'] ?? null),
                'sourceDirectories' => $sourceDirectories,
                'buildDepends' => $buildDepends,
                'ghcOptions' => $ghcOptions,
                'defaultLanguage' => $defaultLanguage,
                'otherModules' => $otherModules,
            ];
        }

        ksort($suites);
        return $suites;
    }

    /**
     * @return array<string, array{buildDepends:list<string>}>
     */
    public static function parseCabalLibraries(string $contents): array
    {
        $stanzas = self::parseCabalStanzas($contents);
        $libraries = [];

        foreach ($stanzas as $key => $stanza) {
            if ($stanza['type'] !== 'library') {
                continue;
            }

            $fields = self::resolveCabalStanzaFields($key, $stanzas);
            $libraries[$stanza['name']] = [
                'buildDepends' => self::extractCabalDependencyNames($fields['build-depends'] ?? ''),
            ];
        }

        ksort($libraries);
        return $libraries;
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
     * @return array{expected:array<string, array{type:string, location:string}>, present:array<string, array{type:string|null, location:string, tag:string|null}>, missing:list<string>, mismatched:array<string, array{expected:array{type:string, location:string}, actual:array{type:string|null, location:string}>>}
     */
    private static function auditProjectSourceRepositoryClosure(?string $contents): array
    {
        $present = $contents === null ? [] : self::parseCabalProjectSourceRepositories($contents);
        $missing = [];
        $mismatched = [];

        foreach (self::PROJECT_SOURCE_REPOSITORIES as $name => $expected) {
            if (!array_key_exists($name, $present)) {
                $missing[] = $name;
                continue;
            }

            $actual = [
                'type' => $present[$name]['type'],
                'location' => $present[$name]['location'],
            ];
            if ($actual['type'] !== $expected['type'] || $actual['location'] !== $expected['location']) {
                $mismatched[$name] = [
                    'expected' => $expected,
                    'actual' => $actual,
                ];
            }
        }

        return [
            'expected' => self::PROJECT_SOURCE_REPOSITORIES,
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
     * @return array{expectedConstraints:array<string, string>, presentConstraints:array<string, string>, missingConstraints:list<string>, mismatchedConstraints:array<string, array{expected:string, actual:string}>}
     */
    private static function auditProjectConstraintClosure(?string $contents): array
    {
        $present = $contents === null ? [] : self::parseCabalProjectConstraints($contents);
        $missing = [];
        $mismatched = [];

        foreach (self::PROJECT_CONSTRAINTS as $name => $expectedConstraint) {
            if (!array_key_exists($name, $present)) {
                $missing[] = $name;
                continue;
            }

            if ($present[$name] !== $expectedConstraint) {
                $mismatched[$name] = [
                    'expected' => $expectedConstraint,
                    'actual' => $present[$name],
                ];
            }
        }

        return [
            'expectedConstraints' => self::PROJECT_CONSTRAINTS,
            'presentConstraints' => $present,
            'missingConstraints' => $missing,
            'mismatchedConstraints' => $mismatched,
        ];
    }

    /**
     * @return array{expectedDependencies:array<string, list<string>>, expectedExecutableOptions:array<string, list<string>>, expectedDefaultLanguages:array<string, string>, expectedOtherModules:array<string, list<string>>, present:array<string, array{packageFile:string, type:string|null, buildable:bool|null, mainIs:string|null, sourceDirectories:list<string>, buildDepends:list<string>, ghcOptions:list<string>, defaultLanguage:string|null, otherModules:list<string>}>, missingTargets:list<string>, mismatchedEntryPoints:array<string, list<string>>, missingDependencies:array<string, list<string>>, missingExecutableOptions:array<string, list<string>>, mismatchedDefaultLanguages:array<string, array{expected:string, actual:string|null}>, missingOtherModules:array<string, list<string>>}
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
                'type' => $suites[$suiteName]['type'],
                'buildable' => $suites[$suiteName]['buildable'],
                'mainIs' => $suites[$suiteName]['mainIs'],
                'sourceDirectories' => $suites[$suiteName]['sourceDirectories'],
                'buildDepends' => $suites[$suiteName]['buildDepends'],
                'ghcOptions' => $suites[$suiteName]['ghcOptions'],
                'defaultLanguage' => $suites[$suiteName]['defaultLanguage'],
                'otherModules' => $suites[$suiteName]['otherModules'],
            ];
        }

        $missingTargets = [];
        $mismatchedEntryPoints = [];
        $missingDependencies = [];
        $missingExecutableOptions = [];
        $mismatchedDefaultLanguages = [];
        $missingOtherModules = [];

        foreach (self::RUNNER_ENTRY_POINTS as $target => $entryPoint) {
            if (!array_key_exists($target, $present)) {
                $missingTargets[] = $target;
                continue;
            }

            if ($present[$target]['type'] !== $entryPoint['type']) {
                $mismatchedEntryPoints[$target][] = 'type expected ' . $entryPoint['type'] . ', found ' . ($present[$target]['type'] ?? 'none');
            }

            if ($present[$target]['buildable'] !== true) {
                $mismatchedEntryPoints[$target][] = 'buildable expected true, found ' . self::formatCabalBuildableState($present[$target]['buildable']);
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

            foreach (self::RUNNER_EXECUTABLE_OPTIONS[$target] as $option) {
                if (!in_array($option, $present[$target]['ghcOptions'], true)) {
                    $missingExecutableOptions[$target][] = $option;
                }
            }

            $expectedLanguage = self::RUNNER_DEFAULT_LANGUAGES[$target];
            if ($present[$target]['defaultLanguage'] !== $expectedLanguage) {
                $mismatchedDefaultLanguages[$target] = [
                    'expected' => $expectedLanguage,
                    'actual' => $present[$target]['defaultLanguage'],
                ];
            }

            foreach (self::RUNNER_OTHER_MODULES[$target] as $module) {
                if (!in_array($module, $present[$target]['otherModules'], true)) {
                    $missingOtherModules[$target][] = $module;
                }
            }
        }

        return [
            'expectedDependencies' => self::RUNNER_DIRECT_DEPENDENCIES,
            'expectedExecutableOptions' => self::RUNNER_EXECUTABLE_OPTIONS,
            'expectedDefaultLanguages' => self::RUNNER_DEFAULT_LANGUAGES,
            'expectedOtherModules' => self::RUNNER_OTHER_MODULES,
            'present' => $present,
            'missingTargets' => $missingTargets,
            'mismatchedEntryPoints' => $mismatchedEntryPoints,
            'missingDependencies' => $missingDependencies,
            'missingExecutableOptions' => $missingExecutableOptions,
            'mismatchedDefaultLanguages' => $mismatchedDefaultLanguages,
            'missingOtherModules' => $missingOtherModules,
        ];
    }

    /**
     * @return array{packageFile:string, expectedDependencies:list<string>, presentDependencies:list<string>, missingDependencies:list<string>}
     */
    private static function auditLuaEngineLibraryClosure(string $root): array
    {
        $packageFile = 'pandoc-lua-engine/pandoc-lua-engine.cabal';
        $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $packageFile);
        $presentDependencies = [];

        if (is_file($path)) {
            $libraries = self::parseCabalLibraries((string) file_get_contents($path));
            $presentDependencies = $libraries['default']['buildDepends'] ?? [];
        }

        $missingDependencies = [];
        foreach (self::LUA_ENGINE_LIBRARY_DEPENDENCIES as $dependency) {
            if (!in_array($dependency, $presentDependencies, true)) {
                $missingDependencies[] = $dependency;
            }
        }

        return [
            'packageFile' => $packageFile,
            'expectedDependencies' => self::LUA_ENGINE_LIBRARY_DEPENDENCIES,
            'presentDependencies' => $presentDependencies,
            'missingDependencies' => $missingDependencies,
        ];
    }

    /**
     * @return array{expected:array<string, array{entryFile:string, requiredSnippets:array<string, string>}>, present:array<string, array{entryFile:string, matchedSnippets:list<string>}>, missingTargets:list<string>, missingSemantics:array<string, list<string>>}
     */
    private static function auditRunnerEntrySourceClosure(string $root): array
    {
        $present = [];
        $missingTargets = [];
        $missingSemantics = [];

        foreach (self::RUNNER_ENTRY_SOURCE_SEMANTICS as $target => $expected) {
            $relativePath = $expected['entryFile'];
            $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            if (!is_file($path)) {
                $missingTargets[] = $target;
                continue;
            }

            $contents = file_get_contents($path);
            if ($contents === false) {
                $missingTargets[] = $target;
                continue;
            }

            $matched = [];
            foreach ($expected['requiredSnippets'] as $label => $snippet) {
                if (str_contains($contents, $snippet)) {
                    $matched[] = $label;
                    continue;
                }

                $missingSemantics[$target][] = $label;
            }

            $present[$target] = [
                'entryFile' => $relativePath,
                'matchedSnippets' => $matched,
            ];
        }

        return [
            'expected' => self::RUNNER_ENTRY_SOURCE_SEMANTICS,
            'present' => $present,
            'missingTargets' => $missingTargets,
            'missingSemantics' => $missingSemantics,
        ];
    }

    /**
     * @return array{expected:array<string, string>, present:list<string>, missing:list<string>, wrongType:array<string, array{expected:string, actual:string}>}
     */
    private static function auditRunnerArtifactClosure(string $root): array
    {
        $present = [];
        $missing = [];
        $wrongType = [];

        foreach (self::REQUIRED_RUNNER_ARTIFACTS as $relativePath => $expectedKind) {
            $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            $actualKind = self::filesystemArtifactKind($path);
            if ($actualKind === null) {
                $missing[] = $relativePath;
                continue;
            }

            if ($actualKind !== $expectedKind) {
                $wrongType[$relativePath] = [
                    'expected' => $expectedKind,
                    'actual' => $actualKind,
                ];
                continue;
            }

            $present[] = $relativePath;
        }

        return [
            'expected' => self::REQUIRED_RUNNER_ARTIFACTS,
            'present' => $present,
            'missing' => $missing,
            'wrongType' => $wrongType,
        ];
    }

    /**
     * @return array{expected:list<string>, present:array<string, array{sha256:string, bytes:int}>, missing:list<string>}
     */
    private static function auditRequiredFileProvenance(string $root): array
    {
        $present = [];
        $missing = [];

        foreach (self::REQUIRED_FILES as $relativePath) {
            $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            if (!is_file($path)) {
                $missing[] = $relativePath;
                continue;
            }

            $contents = file_get_contents($path);
            if ($contents === false) {
                $missing[] = $relativePath;
                continue;
            }

            $present[$relativePath] = [
                'sha256' => hash('sha256', $contents),
                'bytes' => strlen($contents),
            ];
        }

        return [
            'expected' => self::REQUIRED_FILES,
            'present' => $present,
            'missing' => $missing,
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
            if (preg_match('/^library\s*$/i', $line) === 1) {
                $currentKey = 'library:default';
                $stanzas[$currentKey] = [
                    'type' => 'library',
                    'name' => 'default',
                    'fields' => [],
                ];
                $lastField = null;
                continue;
            }

            if (preg_match('/^([A-Za-z][A-Za-z0-9-]*)\s+([A-Za-z0-9_.-]+)\s*$/', $line, $match) === 1) {
                $type = strtolower($match[1]);
                if (in_array($type, ['test-suite', 'common', 'library'], true)) {
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
        $raw = self::stripCabalLineComments($raw);
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
            if (in_array($field, ['build-depends', 'other-modules'], true) && array_key_exists($field, $base) && $base[$field] !== '') {
                $base[$field] .= ",\n" . $value;
                continue;
            }

            if (in_array($field, ['ghc-options', 'hs-source-dirs'], true) && array_key_exists($field, $base) && $base[$field] !== '') {
                $base[$field] .= "\n" . $value;
                continue;
            }

            $base[$field] = $value;
        }

        return $base;
    }

    private static function stripCabalLineComments(string $raw): string
    {
        $lines = [];
        foreach (preg_split('/\R/', $raw) ?: [] as $line) {
            $lines[] = preg_replace('/--.*$/', '', $line) ?? $line;
        }

        return implode("\n", $lines);
    }

    /**
     * @return list<string>
     */
    private static function extractCabalDependencyNames(string $raw): array
    {
        $raw = self::stripCabalLineComments($raw);
        $dependencies = [];
        foreach (explode(',', str_replace("\n", ' ', $raw)) as $part) {
            $part = trim($part);
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
    private static function extractCabalModuleNames(string $raw): array
    {
        $raw = self::stripCabalLineComments($raw);
        $modules = [];
        foreach (preg_split('/[\s,]+/', str_replace("\n", ' ', trim($raw))) ?: [] as $module) {
            $module = trim($module);
            if ($module !== '' && preg_match('/^[A-Z][A-Za-z0-9_]*(?:\.[A-Z][A-Za-z0-9_]*)*$/', $module) === 1 && !in_array($module, $modules, true)) {
                $modules[] = $module;
            }
        }

        sort($modules);
        return $modules;
    }

    /**
     * @return list<string>
     */
    private static function splitWords(string $raw): array
    {
        $raw = self::stripCabalLineComments($raw);
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

    private static function cabalBuildableState(?string $raw): ?bool
    {
        if ($raw === null) {
            return true;
        }

        $value = strtolower((string) self::firstFieldValue($raw));
        if (in_array($value, ['true', 'yes'], true)) {
            return true;
        }

        if (in_array($value, ['false', 'no'], true)) {
            return false;
        }

        return null;
    }

    private static function formatCabalBuildableState(?bool $state): string
    {
        if ($state === true) {
            return 'true';
        }

        if ($state === false) {
            return 'false';
        }

        return 'none';
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
     * @param array<string, array{expected:string, actual:string}> $mismatchedConstraints
     */
    private static function formatProjectConstraintMismatches(array $mismatchedConstraints): string
    {
        $parts = [];
        foreach ($mismatchedConstraints as $package => $state) {
            $parts[] = $package . ' expected ' . $state['expected'] . ', found ' . $state['actual'];
        }

        return implode('; ', $parts);
    }

    /**
     * @param array<string, array{expected:string, actual:string|null}> $mismatches
     */
    private static function formatDefaultLanguageMismatches(array $mismatches): string
    {
        $parts = [];
        foreach ($mismatches as $target => $state) {
            $parts[] = $target . ' expected ' . $state['expected'] . ', found ' . ($state['actual'] ?? 'none');
        }

        return implode('; ', $parts);
    }

    private static function filesystemArtifactKind(string $path): ?string
    {
        if (is_file($path)) {
            return 'file';
        }

        if (is_dir($path)) {
            return 'directory';
        }

        if (file_exists($path)) {
            return 'other';
        }

        return null;
    }

    /**
     * @param array<string, array{expected:string, actual:string}> $mismatches
     */
    private static function formatArtifactTypeMismatches(array $mismatches): string
    {
        $parts = [];
        foreach ($mismatches as $path => $state) {
            $parts[] = $path . ' expected ' . $state['expected'] . ', found ' . $state['actual'];
        }

        return implode('; ', $parts);
    }

    /**
     * @param list<string> $missingFiles
     * @param list<string> $missingTools
     * @param array{missing:list<string>, mismatched:array<string, array{expected:string, actual:string}>} $projectPins
     * @param array{missing:list<string>, mismatched:array<string, array{expected:array{type:string, location:string}, actual:array{type:string|null, location:string}>>} $projectSourceRepositoryClosure
     * @param array{missingPackages:list<string>, missingFlags:array<string, list<string>>, mismatchedFlags:array<string, array<string, array{expected:bool, actual:bool|null}>>} $projectPackageClosure
     * @param array{missingConstraints:list<string>, mismatchedConstraints:array<string, array{expected:string, actual:string}>} $projectConstraintClosure
     * @param array{missingTargets:list<string>, mismatchedEntryPoints:array<string, list<string>>, missingDependencies:array<string, list<string>>, missingExecutableOptions:array<string, list<string>>, mismatchedDefaultLanguages:array<string, array{expected:string, actual:string|null}>, missingOtherModules:array<string, list<string>>} $runnerDependencyClosure
     * @param array{missingDependencies:list<string>} $luaEngineLibraryClosure
     * @param array{missingTargets:list<string>, missingSemantics:array<string, list<string>>} $runnerEntrySourceClosure
     * @param array{missing:list<string>, wrongType:array<string, array{expected:string, actual:string}>} $runnerArtifactClosure
     */
    private static function activationGate(array $missingFiles, array $missingTools, array $projectPins, array $projectSourceRepositoryClosure, array $projectPackageClosure, array $projectConstraintClosure, array $runnerDependencyClosure, array $luaEngineLibraryClosure, array $runnerEntrySourceClosure, array $runnerArtifactClosure): string
    {
        if (
            $missingFiles === []
            && $missingTools === []
            && $projectPins['missing'] === []
            && $projectPins['mismatched'] === []
            && $projectSourceRepositoryClosure['missing'] === []
            && $projectSourceRepositoryClosure['mismatched'] === []
            && $projectPackageClosure['missingPackages'] === []
            && $projectPackageClosure['missingFlags'] === []
            && $projectPackageClosure['mismatchedFlags'] === []
            && $projectConstraintClosure['missingConstraints'] === []
            && $projectConstraintClosure['mismatchedConstraints'] === []
            && $runnerDependencyClosure['missingTargets'] === []
            && $runnerDependencyClosure['mismatchedEntryPoints'] === []
            && $runnerDependencyClosure['missingDependencies'] === []
            && $runnerDependencyClosure['missingExecutableOptions'] === []
            && $runnerDependencyClosure['mismatchedDefaultLanguages'] === []
            && $runnerDependencyClosure['missingOtherModules'] === []
            && $luaEngineLibraryClosure['missingDependencies'] === []
            && $runnerEntrySourceClosure['missingTargets'] === []
            && $runnerEntrySourceClosure['missingSemantics'] === []
            && $runnerArtifactClosure['missing'] === []
            && $runnerArtifactClosure['wrongType'] === []
        ) {
            return 'Hydrated Pandoc checkout, required Cabal toolchain, cabal.project package/flag/constraint closure, exact cabal.project source-repository Git types and locations, runner source/golden fixtures, runner entry-point source semantics, buildable runner test-suite stanzas, exitcode-stdio runner types, direct build-depends, Haskell2010 default-language closure, runner other-modules closure, pandoc-lua-engine library HsLua module dependency closure, executable options, and Git pins are present; record a non-mutating solver/build plan before any Haskell runner execution.';
        }

        return 'Hydrate Pandoc upstream commit ' . self::UPSTREAM_COMMIT
            . ' with cabal.project package entries/flags/constraints, exact cabal.project source-repository Git types and locations, pandoc.cabal, pandoc-lua-engine/pandoc-lua-engine.cabal, runner source/golden fixtures, runner entry-point source semantics, buildable exitcode-stdio test-suite types, Haskell2010 default-language closure, test entry points, direct runner build-depends, runner other-modules closure, pandoc-lua-engine library HsLua module dependency closure, runner executable options, ghc, cabal, and exact cabal.project Git source-repository pins before attempting a runner plan.';
    }
}
