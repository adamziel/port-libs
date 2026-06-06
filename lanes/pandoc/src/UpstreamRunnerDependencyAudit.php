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
        'pandoc-lua-engine/test/Tests/Lua.hs' => 'file',
        'pandoc-lua-engine/test/Tests/Lua/Module.hs' => 'file',
        'pandoc-lua-engine/test/Tests/Lua/Reader.hs' => 'file',
        'pandoc-lua-engine/test/Tests/Lua/Writer.hs' => 'file',
        'pandoc-lua-engine/test' => 'directory',
        'data' => 'directory',
    ];

    private const REQUIRED_TOOLS = [
        'ghc',
        'cabal',
    ];

    private const TESTED_GHC_VERSIONS = [
        '9.6.7',
        '9.8.4',
        '9.10.3',
        '9.12.2',
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
            'sourceDirectory' => 'test',
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

    private const RUNNER_EXPECTED_MIXINS = [
        'test:test-pandoc' => [],
        'test:test-pandoc-lua-engine' => [],
    ];

    private const RUNNER_EXPECTED_BUILD_TOOLS = [
        'test:test-pandoc' => [],
        'test:test-pandoc-lua-engine' => [],
    ];

    private const BENCHMARK_ENTRY_POINTS = [
        'benchmark:benchmark-pandoc' => [
            'packageFile' => 'pandoc.cabal',
            'type' => 'exitcode-stdio-1.0',
            'mainIs' => 'benchmark-pandoc.hs',
            'sourceDirectory' => 'benchmark',
        ],
    ];

    private const BENCHMARK_DIRECT_DEPENDENCIES = [
        'benchmark:benchmark-pandoc' => [
            'base',
            'pandoc',
            'bytestring',
            'deepseq',
            'mtl',
            'tasty-bench',
            'text',
        ],
    ];

    private const BENCHMARK_DEPENDENCY_CONSTRAINTS = [
        'benchmark:benchmark-pandoc' => [
            'base' => '>= 4.18 && < 5',
            'mtl' => '>= 2.2 && < 2.4',
            'tasty-bench' => '>= 0.4 && <= 0.5',
            'text' => '>= 1.1.1.0 && < 2.2',
        ],
    ];

    private const BENCHMARK_EXECUTABLE_OPTIONS = [
        'benchmark:benchmark-pandoc' => [
            '-rtsopts',
            '-with-rtsopts=-A8m',
            '-threaded',
        ],
    ];

    private const BENCHMARK_DEFAULT_LANGUAGES = [
        'benchmark:benchmark-pandoc' => 'Haskell2010',
    ];

    private const BENCHMARK_EXPECTED_MIXINS = [
        'benchmark:benchmark-pandoc' => [],
    ];

    private const BENCHMARK_EXPECTED_BUILD_TOOLS = [
        'benchmark:benchmark-pandoc' => [],
    ];

    private const BENCHMARK_ARTIFACTS = [
        'benchmark/benchmark-pandoc.hs' => 'file',
        'test/testsuite.txt' => 'file',
        'test/lalune.jpg' => 'file',
        'test/movie.jpg' => 'file',
    ];

    private const BENCHMARK_ENTRY_SOURCE_SEMANTICS = [
        'benchmark:benchmark-pandoc' => [
            'entryFile' => 'benchmark/benchmark-pandoc.hs',
            'requiredSnippets' => [
                'imports pandoc conversion registry' => 'import Text.Pandoc',
                'imports pandoc media MIME support' => 'import Text.Pandoc.MIME',
                'imports tasty benchmark harness' => 'import Test.Tasty.Bench',
                'skips bibliography-only formats' => 'name `elem` ["bibtex", "biblatex", "csljson"]',
                'resolves readers by flavored format' => 'getReader $ FlavoredFormat name mempty',
                'resolves writers by flavored format' => 'getWriter $ FlavoredFormat name mempty',
                'compiles default writer templates' => 'compileDefaultTemplate name',
                'benchmarks text readers' => 'TextReader r',
                'benchmarks bytestring readers' => 'ByteStringReader r',
                'loads image media fixture lalune' => 'B.readFile "test/lalune.jpg"',
                'loads image media fixture movie' => 'B.readFile "test/movie.jpg"',
                'inserts media before writer benchmark' => 'insertMedia fp (Just mt) bs',
                'reads benchmark testsuite fixture' => 'B.readFile "test/testsuite.txt"',
                'parses markdown fixture into Pandoc AST' => 'readMarkdown opts inp',
                'forces parsed AST before benchmarking' => 'force $ runPure $ readMarkdown opts inp',
                'runs tasty benchmark main' => 'defaultMain',
                'groups writer benchmarks' => 'bgroup "writers"',
                'groups reader benchmarks' => 'bgroup "readers"',
            ],
        ],
    ];

    private const RUNNER_OTHER_MODULES = [
        'test:test-pandoc' => [
            'Tests.Old',
            'Tests.Command',
            'Tests.Helpers',
            'Tests.Shared',
            'Tests.MediaBag',
            'Tests.XML',
            'Tests.Readers.LaTeX',
            'Tests.Readers.HTML',
            'Tests.Readers.JATS',
            'Tests.Readers.Jira',
            'Tests.Readers.Markdown',
            'Tests.Readers.Org',
            'Tests.Readers.Org.Block',
            'Tests.Readers.Org.Block.CodeBlock',
            'Tests.Readers.Org.Block.Figure',
            'Tests.Readers.Org.Block.Header',
            'Tests.Readers.Org.Block.List',
            'Tests.Readers.Org.Block.Table',
            'Tests.Readers.Org.Directive',
            'Tests.Readers.Org.Inline',
            'Tests.Readers.Org.Inline.Citation',
            'Tests.Readers.Org.Inline.Note',
            'Tests.Readers.Org.Inline.Smart',
            'Tests.Readers.Org.Meta',
            'Tests.Readers.Org.Shared',
            'Tests.Readers.RST',
            'Tests.Readers.RTF',
            'Tests.Readers.Docx',
            'Tests.Readers.Pptx',
            'Tests.Readers.Xlsx',
            'Tests.Readers.ODT',
            'Tests.Readers.Txt2Tags',
            'Tests.Readers.EPUB',
            'Tests.Readers.Muse',
            'Tests.Readers.Creole',
            'Tests.Readers.Man',
            'Tests.Readers.Mdoc',
            'Tests.Readers.FB2',
            'Tests.Readers.Pod',
            'Tests.Readers.DokuWiki',
            'Tests.Writers.Markdown',
            'Tests.Writers.Native',
            'Tests.Writers.ConTeXt',
            'Tests.Writers.DocBook',
            'Tests.Writers.HTML',
            'Tests.Writers.JATS',
            'Tests.Writers.Jira',
            'Tests.Writers.Org',
            'Tests.Writers.Plain',
            'Tests.Writers.AsciiDoc',
            'Tests.Writers.LaTeX',
            'Tests.Writers.Docx',
            'Tests.Writers.RST',
            'Tests.Writers.TEI',
            'Tests.Writers.Markua',
            'Tests.Writers.Muse',
            'Tests.Writers.FB2',
            'Tests.Writers.Powerpoint',
            'Tests.Writers.OOXML',
            'Tests.Writers.Ms',
            'Tests.Writers.AnnotatedTable',
            'Tests.Writers.BBCode',
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
                'catches command emulation exceptions' => 'E.catch',
                'parses --emulate args with default pandoc options' => "parseOptionsFromArgs options defaultOpts \"pandoc\" args'",
                'handles command option info with noEngine' => 'Left e -> handleOptInfo noEngine e',
                'converts parsed command options with noEngine' => 'Right opts -> convertWithOpts noEngine opts',
                'handles emulation errors through pandoc error handler' => '(handleError . Left)',
                'runs from upstream test directory' => 'inDirectory "test"',
                'passes executable path into old command tests' => 'getExecutablePath',
                'runs tasty defaultMain' => 'defaultMain $ tests fp',
                'loads command golden tests' => 'Tests.Command.tests',
                'loads old command tests' => 'Tests.Old.tests',
                'loads shared helper tests' => 'Tests.Shared.tests',
                'loads media bag tests' => 'Tests.MediaBag.tests',
                'loads xml tests' => 'Tests.XML.tests',
                'loads markdown reader tests' => 'Tests.Readers.Markdown.tests',
                'loads html reader tests' => 'Tests.Readers.HTML.tests',
                'loads jats reader tests' => 'Tests.Readers.JATS.tests',
                'loads jira reader tests' => 'Tests.Readers.Jira.tests',
                'loads org reader tests' => 'Tests.Readers.Org.tests',
                'loads latex reader tests' => 'Tests.Readers.LaTeX.tests',
                'loads rst reader tests' => 'Tests.Readers.RST.tests',
                'loads rtf reader tests' => 'Tests.Readers.RTF.tests',
                'loads docx reader tests' => 'Tests.Readers.Docx.tests',
                'loads pptx reader tests' => 'Tests.Readers.Pptx.tests',
                'loads xlsx reader tests' => 'Tests.Readers.Xlsx.tests',
                'loads odt reader tests' => 'Tests.Readers.ODT.tests',
                'loads txt2tags reader tests' => 'Tests.Readers.Txt2Tags.tests',
                'loads epub reader tests' => 'Tests.Readers.EPUB.tests',
                'loads muse reader tests' => 'Tests.Readers.Muse.tests',
                'loads creole reader tests' => 'Tests.Readers.Creole.tests',
                'loads man reader tests' => 'Tests.Readers.Man.tests',
                'loads mdoc reader tests' => 'Tests.Readers.Mdoc.tests',
                'loads fb2 reader tests' => 'Tests.Readers.FB2.tests',
                'loads dokuwiki reader tests' => 'Tests.Readers.DokuWiki.tests',
                'loads pod reader tests' => 'Tests.Readers.Pod.tests',
                'loads native writer tests' => 'Tests.Writers.Native.tests',
                'loads context writer tests' => 'Tests.Writers.ConTeXt.tests',
                'loads html writer tests' => 'Tests.Writers.HTML.tests',
                'loads jats writer tests' => 'Tests.Writers.JATS.tests',
                'loads jira writer tests' => 'Tests.Writers.Jira.tests',
                'loads latex writer tests' => 'Tests.Writers.LaTeX.tests',
                'loads markdown writer tests' => 'Tests.Writers.Markdown.tests',
                'loads org writer tests' => 'Tests.Writers.Org.tests',
                'loads plain writer tests' => 'Tests.Writers.Plain.tests',
                'loads docx writer tests' => 'Tests.Writers.Docx.tests',
                'loads rst writer tests' => 'Tests.Writers.RST.tests',
                'loads asciidoc writer tests' => 'Tests.Writers.AsciiDoc.tests',
                'loads docbook writer tests' => 'Tests.Writers.DocBook.tests',
                'loads tei writer tests' => 'Tests.Writers.TEI.tests',
                'loads markua writer tests' => 'Tests.Writers.Markua.tests',
                'loads muse writer tests' => 'Tests.Writers.Muse.tests',
                'loads fb2 writer tests' => 'Tests.Writers.FB2.tests',
                'loads powerpoint writer tests' => 'Tests.Writers.Powerpoint.tests',
                'loads ms writer tests' => 'Tests.Writers.Ms.tests',
                'loads annotated table writer tests' => 'Tests.Writers.AnnotatedTable.tests',
                'loads bbcode writer tests' => 'Tests.Writers.BBCode.tests',
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

    private const RUNNER_DEPENDENCY_CONSTRAINTS = [
        'test:test-pandoc' => [
            'base' => '>= 4.18 && < 5',
            'Diff' => '>= 0.2 && < 1.1',
            'Glob' => '>= 0.7 && < 0.11',
            'bytestring' => '>= 0.9 && < 0.13',
            'containers' => '>= 0.4.2.1 && < 0.9',
            'directory' => '>= 1.2.3 && < 1.4',
            'doctemplates' => '>= 0.11 && < 0.12',
            'filepath' => '>= 1.1 && < 1.6',
            'mtl' => '>= 2.2 && < 2.4',
            'pandoc-types' => '>= 1.23.1 && < 1.24',
            'process' => '>= 1.2.3 && < 1.7',
            'tasty' => '>= 0.11 && < 1.6',
            'tasty-golden' => '>= 2.3 && < 2.4',
            'tasty-hunit' => '>= 0.9 && < 0.11',
            'tasty-quickcheck' => '>= 0.8 && < 0.12',
            'text' => '>= 1.1.1.0 && < 2.2',
            'temporary' => '>= 1.1 && < 1.4',
            'time' => '>= 1.5 && < 1.16',
            'xml' => '>= 1.3.12 && < 1.4',
            'zip-archive' => '>= 0.4.3 && < 0.5',
        ],
        'test:test-pandoc-lua-engine' => [
            'base' => '>= 4.12 && < 5',
            'exceptions' => '>= 0.8 && < 0.11',
            'hslua' => '>= 2.5 && < 2.6',
            'pandoc-types' => '>= 1.22 && < 1.24',
            'tasty-lua' => '>= 1.1 && < 1.2',
            'text' => '>= 1.1.1 && < 2.2',
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
     *   compilerTestedWithClosure:array{packageFile:string, expectedGhcVersions:list<string>, presentGhcVersions:list<string>, missingGhcVersions:list<string>, toolGhcVersion:string|null, toolGhcVersionSupported:bool},
     *   runnerTargets:list<string>,
     *   runnerEntryPoints:array<string, array{packageFile:string, type:string, mainIs:string, sourceDirectory:string}>,
     *   benchmarkTargets:list<string>,
     *   benchmarkEntryPoints:array<string, array{packageFile:string, type:string, mainIs:string, sourceDirectory:string}>,
     *   projectSourceRepositoryPins:array{expected:array<string, string>, present:array<string, string>, missing:list<string>, mismatched:array<string, array{expected:string, actual:string}>},
     *   projectSourceRepositoryClosure:array{expected:array<string, array{type:string, location:string}>, present:array<string, array{type:string|null, location:string, tag:string|null}>, missing:list<string>, mismatched:array<string, array{expected:array{type:string, location:string}, actual:array{type:string|null, location:string}>>},
     *   projectPackageClosure:array{expectedPackages:list<string>, presentPackages:list<string>, missingPackages:list<string>, expectedFlags:array<string, array<string, bool>>, presentFlags:array<string, array<string, bool>>, missingFlags:array<string, list<string>>, mismatchedFlags:array<string, array<string, array{expected:bool, actual:bool|null}>>},
     *   projectConstraintClosure:array{expectedConstraints:array<string, string>, presentConstraints:array<string, string>, missingConstraints:list<string>, mismatchedConstraints:array<string, array{expected:string, actual:string}>},
     *   runnerDependencyClosure:array{expectedDependencies:array<string, list<string>>, expectedDependencyConstraints:array<string, array<string, string>>, expectedExecutableOptions:array<string, list<string>>, expectedDefaultLanguages:array<string, string>, expectedMixins:array<string, list<string>>, expectedBuildTools:array<string, list<string>>, expectedOtherModules:array<string, list<string>>, present:array<string, array{packageFile:string, type:string|null, buildable:bool|null, mainIs:string|null, sourceDirectories:list<string>, buildDepends:list<string>, dependencyConstraints:array<string, string>, ghcOptions:list<string>, defaultLanguage:string|null, mixins:list<string>, buildToolDepends:list<string>, buildTools:list<string>, otherModules:list<string>}>, missingTargets:list<string>, mismatchedEntryPoints:array<string, list<string>>, missingDependencies:array<string, list<string>>, mismatchedDependencyConstraints:array<string, array<string, array{expected:string, actual:string}>>, missingExecutableOptions:array<string, list<string>>, mismatchedDefaultLanguages:array<string, array{expected:string, actual:string|null}>, unexpectedMixins:array<string, list<string>>, unexpectedBuildTools:array<string, list<string>>, missingOtherModules:array<string, list<string>>},
     *   benchmarkDependencyClosure:array{expectedDependencies:array<string, list<string>>, expectedDependencyConstraints:array<string, array<string, string>>, expectedExecutableOptions:array<string, list<string>>, expectedDefaultLanguages:array<string, string>, expectedMixins:array<string, list<string>>, expectedBuildTools:array<string, list<string>>, present:array<string, array{packageFile:string, type:string|null, buildable:bool|null, mainIs:string|null, sourceDirectories:list<string>, buildDepends:list<string>, dependencyConstraints:array<string, string>, ghcOptions:list<string>, defaultLanguage:string|null, mixins:list<string>, buildToolDepends:list<string>, buildTools:list<string>}>, missingTargets:list<string>, mismatchedEntryPoints:array<string, list<string>>, missingDependencies:array<string, list<string>>, mismatchedDependencyConstraints:array<string, array<string, array{expected:string, actual:string}>>, missingExecutableOptions:array<string, list<string>>, mismatchedDefaultLanguages:array<string, array{expected:string, actual:string|null}>, unexpectedMixins:array<string, list<string>>, unexpectedBuildTools:array<string, list<string>>},
     *   luaEngineLibraryClosure:array{packageFile:string, expectedDependencies:list<string>, presentDependencies:list<string>, missingDependencies:list<string>},
     *   runnerEntrySourceClosure:array{expected:array<string, array{entryFile:string, requiredSnippets:array<string, string>}>, present:array<string, array{entryFile:string, matchedSnippets:list<string>}>, missingTargets:list<string>, missingSemantics:array<string, list<string>>},
     *   runnerArtifactClosure:array{expected:array<string, string>, present:list<string>, missing:list<string>, wrongType:array<string, array{expected:string, actual:string}>, emptyFiles:list<string>},
     *   benchmarkArtifactClosure:array{expected:array<string, string>, present:list<string>, missing:list<string>, wrongType:array<string, array{expected:string, actual:string}>, emptyFiles:list<string>},
     *   benchmarkEntrySourceClosure:array{expected:array<string, array{entryFile:string, requiredSnippets:array<string, string>}>, present:array<string, array{entryFile:string, matchedSnippets:list<string>}>, missingTargets:list<string>, missingSemantics:array<string, list<string>>},
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
        $compilerTestedWithClosure = self::auditCompilerTestedWithClosure($root, $normalizedTools);
        $runnerDependencyClosure = self::auditRunnerDependencyClosure($root);
        $benchmarkDependencyClosure = self::auditBenchmarkDependencyClosure($root);
        $luaEngineLibraryClosure = self::auditLuaEngineLibraryClosure($root);
        $runnerEntrySourceClosure = self::auditRunnerEntrySourceClosure($root);
        $runnerArtifactClosure = self::auditRunnerArtifactClosure($root);
        $benchmarkArtifactClosure = self::auditBenchmarkArtifactClosure($root);
        $benchmarkEntrySourceClosure = self::auditBenchmarkEntrySourceClosure($root);

        $blockedReasons = [];
        if ($missingFiles !== []) {
            $blockedReasons[] = 'missing required upstream runner files: ' . implode(', ', $missingFiles);
        }
        if ($missingTools !== []) {
            $blockedReasons[] = 'missing required Cabal toolchain commands: ' . implode(', ', $missingTools);
        }
        if ($compilerTestedWithClosure['missingGhcVersions'] !== []) {
            $blockedReasons[] = 'missing pandoc.cabal tested-with GHC versions: ' . implode(', ', $compilerTestedWithClosure['missingGhcVersions']);
        }
        if (($normalizedTools['ghc']['available'] ?? false) === true && $compilerTestedWithClosure['toolGhcVersionSupported'] !== true) {
            $blockedReasons[] = 'unsupported or unrecorded ghc version for Pandoc tested-with matrix: ' . ($compilerTestedWithClosure['toolGhcVersion'] ?? 'none');
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
        if ($runnerDependencyClosure['mismatchedDependencyConstraints'] !== []) {
            $blockedReasons[] = 'mismatched Cabal runner direct build-depends constraints: ' . self::formatTargetConstraintMismatches($runnerDependencyClosure['mismatchedDependencyConstraints']);
        }
        if ($runnerDependencyClosure['missingExecutableOptions'] !== []) {
            $blockedReasons[] = 'missing Cabal runner executable options: ' . self::formatTargetFailures($runnerDependencyClosure['missingExecutableOptions']);
        }
        if ($runnerDependencyClosure['mismatchedDefaultLanguages'] !== []) {
            $blockedReasons[] = 'mismatched Cabal runner default-language: ' . self::formatDefaultLanguageMismatches($runnerDependencyClosure['mismatchedDefaultLanguages']);
        }
        if ($runnerDependencyClosure['unexpectedMixins'] !== []) {
            $blockedReasons[] = 'unexpected Cabal runner mixins: ' . self::formatTargetFailures($runnerDependencyClosure['unexpectedMixins']);
        }
        if ($runnerDependencyClosure['unexpectedBuildTools'] !== []) {
            $blockedReasons[] = 'unexpected Cabal runner build-tool dependencies: ' . self::formatTargetFailures($runnerDependencyClosure['unexpectedBuildTools']);
        }
        if ($runnerDependencyClosure['missingOtherModules'] !== []) {
            $blockedReasons[] = 'missing Cabal runner other-modules: ' . self::formatTargetFailures($runnerDependencyClosure['missingOtherModules']);
        }
        if ($benchmarkDependencyClosure['missingTargets'] !== []) {
            $blockedReasons[] = 'missing Cabal benchmark stanzas: ' . implode(', ', $benchmarkDependencyClosure['missingTargets']);
        }
        if ($benchmarkDependencyClosure['mismatchedEntryPoints'] !== []) {
            $blockedReasons[] = 'mismatched Cabal benchmark entry points: ' . self::formatTargetFailures($benchmarkDependencyClosure['mismatchedEntryPoints']);
        }
        if ($benchmarkDependencyClosure['missingDependencies'] !== []) {
            $blockedReasons[] = 'missing Cabal benchmark direct build-depends: ' . self::formatTargetFailures($benchmarkDependencyClosure['missingDependencies']);
        }
        if ($benchmarkDependencyClosure['mismatchedDependencyConstraints'] !== []) {
            $blockedReasons[] = 'mismatched Cabal benchmark direct build-depends constraints: ' . self::formatTargetConstraintMismatches($benchmarkDependencyClosure['mismatchedDependencyConstraints']);
        }
        if ($benchmarkDependencyClosure['missingExecutableOptions'] !== []) {
            $blockedReasons[] = 'missing Cabal benchmark executable options: ' . self::formatTargetFailures($benchmarkDependencyClosure['missingExecutableOptions']);
        }
        if ($benchmarkDependencyClosure['mismatchedDefaultLanguages'] !== []) {
            $blockedReasons[] = 'mismatched Cabal benchmark default-language: ' . self::formatDefaultLanguageMismatches($benchmarkDependencyClosure['mismatchedDefaultLanguages']);
        }
        if ($benchmarkDependencyClosure['unexpectedMixins'] !== []) {
            $blockedReasons[] = 'unexpected Cabal benchmark mixins: ' . self::formatTargetFailures($benchmarkDependencyClosure['unexpectedMixins']);
        }
        if ($benchmarkDependencyClosure['unexpectedBuildTools'] !== []) {
            $blockedReasons[] = 'unexpected Cabal benchmark build-tool dependencies: ' . self::formatTargetFailures($benchmarkDependencyClosure['unexpectedBuildTools']);
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
        if ($runnerArtifactClosure['emptyFiles'] !== []) {
            $blockedReasons[] = 'empty upstream runner source/golden fixture artifacts: ' . implode(', ', $runnerArtifactClosure['emptyFiles']);
        }
        if ($benchmarkArtifactClosure['missing'] !== []) {
            $blockedReasons[] = 'missing upstream benchmark source/data artifacts: ' . implode(', ', $benchmarkArtifactClosure['missing']);
        }
        if ($benchmarkArtifactClosure['wrongType'] !== []) {
            $blockedReasons[] = 'mismatched upstream benchmark source/data artifact types: ' . self::formatArtifactTypeMismatches($benchmarkArtifactClosure['wrongType']);
        }
        if ($benchmarkArtifactClosure['emptyFiles'] !== []) {
            $blockedReasons[] = 'empty upstream benchmark source/data artifacts: ' . implode(', ', $benchmarkArtifactClosure['emptyFiles']);
        }
        if ($benchmarkEntrySourceClosure['missingTargets'] !== []) {
            $blockedReasons[] = 'missing benchmark entry point source files: ' . implode(', ', $benchmarkEntrySourceClosure['missingTargets']);
        }
        if ($benchmarkEntrySourceClosure['missingSemantics'] !== []) {
            $blockedReasons[] = 'missing benchmark entry point source semantics: ' . self::formatTargetFailures($benchmarkEntrySourceClosure['missingSemantics']);
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
            'compilerTestedWithClosure' => $compilerTestedWithClosure,
            'runnerTargets' => array_keys(self::RUNNER_ENTRY_POINTS),
            'runnerEntryPoints' => self::RUNNER_ENTRY_POINTS,
            'benchmarkTargets' => array_keys(self::BENCHMARK_ENTRY_POINTS),
            'benchmarkEntryPoints' => self::BENCHMARK_ENTRY_POINTS,
            'projectSourceRepositoryPins' => $projectPins,
            'projectSourceRepositoryClosure' => $projectSourceRepositoryClosure,
            'projectPackageClosure' => $projectPackageClosure,
            'projectConstraintClosure' => $projectConstraintClosure,
            'runnerDependencyClosure' => $runnerDependencyClosure,
            'benchmarkDependencyClosure' => $benchmarkDependencyClosure,
            'luaEngineLibraryClosure' => $luaEngineLibraryClosure,
            'runnerEntrySourceClosure' => $runnerEntrySourceClosure,
            'runnerArtifactClosure' => $runnerArtifactClosure,
            'benchmarkArtifactClosure' => $benchmarkArtifactClosure,
            'benchmarkEntrySourceClosure' => $benchmarkEntrySourceClosure,
            'readyForNonMutatingCabalPlan' => $ready,
            'blockedReasons' => $blockedReasons,
            'nonMutatingPlan' => $ready ? [
                'record pandoc.cabal tested-with GHC matrix, cabal.project package/flag closure plus source-repository type/location/tag closure, non-empty runner source/golden fixture artifacts, runner entry-point semantics including command-emulation parser/error handling plus full Tasty group dispatch, and package-file hashes before any solver/build command',
                'record cabal.project solver constraints and runner executable options before any solver/build command',
                'record test-suite type, buildable state, default-language, entry point, direct build-depends with pinned version constraints, no unexpected Cabal mixins or build-tool dependencies, and other-modules closure for test:test-pandoc and test:test-pandoc-lua-engine, plus pandoc-lua-engine library HsLua module dependency closure',
                'record benchmark:benchmark-pandoc type, buildable state, default-language, entry point, direct build-depends with pinned version constraints, no unexpected Cabal mixins or build-tool dependencies, executable options, non-empty source/data artifact closure, and entry-source semantics before any benchmark execution',
                'prepare a bounded Cabal solver plan for test:test-pandoc and test:test-pandoc-lua-engine',
                'only after the plan is reviewed, run a separate bounded runner slice with explicit artifact output paths',
            ] : [],
            'activationGate' => self::activationGate($missingFiles, $missingTools, $compilerTestedWithClosure, $projectPins, $projectSourceRepositoryClosure, $projectPackageClosure, $projectConstraintClosure, $runnerDependencyClosure, $benchmarkDependencyClosure, $luaEngineLibraryClosure, $runnerEntrySourceClosure, $runnerArtifactClosure, $benchmarkArtifactClosure, $benchmarkEntrySourceClosure),
        ];
    }

    /**
     * @return list<string>
     */
    public static function expectedCompilerGhcVersions(): array
    {
        return self::TESTED_GHC_VERSIONS;
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
     * @return array<string, array<string, string>>
     */
    public static function expectedRunnerDependencyConstraints(): array
    {
        return self::RUNNER_DEPENDENCY_CONSTRAINTS;
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
    public static function expectedRunnerMixins(): array
    {
        return self::RUNNER_EXPECTED_MIXINS;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedRunnerBuildTools(): array
    {
        return self::RUNNER_EXPECTED_BUILD_TOOLS;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedBenchmarkDependencies(): array
    {
        return self::BENCHMARK_DIRECT_DEPENDENCIES;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function expectedBenchmarkDependencyConstraints(): array
    {
        return self::BENCHMARK_DEPENDENCY_CONSTRAINTS;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedBenchmarkExecutableOptions(): array
    {
        return self::BENCHMARK_EXECUTABLE_OPTIONS;
    }

    /**
     * @return array<string, string>
     */
    public static function expectedBenchmarkDefaultLanguages(): array
    {
        return self::BENCHMARK_DEFAULT_LANGUAGES;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedBenchmarkMixins(): array
    {
        return self::BENCHMARK_EXPECTED_MIXINS;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function expectedBenchmarkBuildTools(): array
    {
        return self::BENCHMARK_EXPECTED_BUILD_TOOLS;
    }

    /**
     * @return array<string, string>
     */
    public static function expectedBenchmarkArtifacts(): array
    {
        return self::BENCHMARK_ARTIFACTS;
    }

    /**
     * @return array<string, array{entryFile:string, requiredSnippets:array<string, string>}>
     */
    public static function expectedBenchmarkEntrySourceSemantics(): array
    {
        return self::BENCHMARK_ENTRY_SOURCE_SEMANTICS;
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
        return self::requiredRunnerArtifacts();
    }

    /**
     * @return list<string>
     */
    public static function parseCabalProjectPackages(string $contents): array
    {
        $contents = self::normalizeCabalProjectForUnconditionalAudit($contents);
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
        $contents = self::normalizeCabalProjectForUnconditionalAudit($contents);
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
        $contents = self::normalizeCabalProjectForUnconditionalAudit($contents);
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
     * @return list<string>
     */
    public static function parseCabalTestedWithGhcVersions(string $contents): array
    {
        $contents = self::stripCabalLineComments($contents);
        $raw = '';
        $capturing = false;
        $fieldIndent = null;

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $indent = strlen($line) - strlen(ltrim($line));

            if (preg_match('/^\s*tested-with\s*:\s*(.*?)\s*$/i', $line, $match) === 1) {
                $raw .= ' ' . trim($match[1]);
                $capturing = true;
                $fieldIndent = $indent;
                continue;
            }

            if (!$capturing) {
                continue;
            }

            if (trim($line) === '') {
                continue;
            }

            if ($fieldIndent !== null && $indent > $fieldIndent && preg_match('/^\s+(.*?)\s*$/', $line, $match) === 1) {
                $raw .= ' ' . trim($match[1]);
                continue;
            }

            $capturing = false;
            $fieldIndent = null;
        }

        $versions = [];
        if (preg_match_all('/\bGHC\s*==\s*([0-9]+(?:\.[0-9]+){1,3})\b/i', $raw, $matches) === false) {
            return [];
        }

        foreach ($matches[1] ?? [] as $version) {
            if (!in_array($version, $versions, true)) {
                $versions[] = $version;
            }
        }

        return $versions;
    }

    /**
     * @return array<string, string>
     */
    public static function parseCabalProjectPins(string $contents): array
    {
        $contents = self::normalizeCabalProjectForUnconditionalAudit($contents);
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
        $contents = self::normalizeCabalProjectForUnconditionalAudit($contents);
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
     * @return array<string, array{type:string|null, buildable:bool|null, mainIs:string|null, sourceDirectories:list<string>, buildDepends:list<string>, dependencyConstraints:array<string, string>, ghcOptions:list<string>, defaultLanguage:string|null, mixins:list<string>, buildToolDepends:list<string>, buildTools:list<string>, otherModules:list<string>}>
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
            $dependencyConstraints = self::extractCabalDependencyConstraints($fields['build-depends'] ?? '');
            $ghcOptions = self::splitWords($fields['ghc-options'] ?? '');
            $defaultLanguage = self::firstFieldValue($fields['default-language'] ?? null);
            $mixins = self::extractCabalMixinSpecs($fields['mixins'] ?? '');
            $buildToolDepends = self::extractCabalBuildToolDepends($fields['build-tool-depends'] ?? '');
            $buildTools = self::extractCabalBuildTools($fields['build-tools'] ?? '');
            $otherModules = self::extractCabalModuleNames($fields['other-modules'] ?? '');

            $suites[$stanza['name']] = [
                'type' => self::firstFieldValue($fields['type'] ?? null),
                'buildable' => self::cabalBuildableState($fields['buildable'] ?? null),
                'mainIs' => self::firstFieldValue($fields['main-is'] ?? null),
                'sourceDirectories' => $sourceDirectories,
                'buildDepends' => $buildDepends,
                'dependencyConstraints' => $dependencyConstraints,
                'ghcOptions' => $ghcOptions,
                'defaultLanguage' => $defaultLanguage,
                'mixins' => $mixins,
                'buildToolDepends' => $buildToolDepends,
                'buildTools' => $buildTools,
                'otherModules' => $otherModules,
            ];
        }

        ksort($suites);
        return $suites;
    }

    /**
     * @return array<string, array{type:string|null, buildable:bool|null, mainIs:string|null, sourceDirectories:list<string>, buildDepends:list<string>, dependencyConstraints:array<string, string>, ghcOptions:list<string>, defaultLanguage:string|null, mixins:list<string>, buildToolDepends:list<string>, buildTools:list<string>}>
     */
    public static function parseCabalBenchmarks(string $contents): array
    {
        $stanzas = self::parseCabalStanzas($contents);
        $benchmarks = [];

        foreach ($stanzas as $key => $stanza) {
            if ($stanza['type'] !== 'benchmark') {
                continue;
            }

            $fields = self::resolveCabalStanzaFields($key, $stanzas);
            $benchmarks[$stanza['name']] = [
                'type' => self::firstFieldValue($fields['type'] ?? null),
                'buildable' => self::cabalBuildableState($fields['buildable'] ?? null),
                'mainIs' => self::firstFieldValue($fields['main-is'] ?? null),
                'sourceDirectories' => self::splitWords($fields['hs-source-dirs'] ?? ''),
                'buildDepends' => self::extractCabalDependencyNames($fields['build-depends'] ?? ''),
                'dependencyConstraints' => self::extractCabalDependencyConstraints($fields['build-depends'] ?? ''),
                'ghcOptions' => self::splitWords($fields['ghc-options'] ?? ''),
                'defaultLanguage' => self::firstFieldValue($fields['default-language'] ?? null),
                'mixins' => self::extractCabalMixinSpecs($fields['mixins'] ?? ''),
                'buildToolDepends' => self::extractCabalBuildToolDepends($fields['build-tool-depends'] ?? ''),
                'buildTools' => self::extractCabalBuildTools($fields['build-tools'] ?? ''),
            ];
        }

        ksort($benchmarks);
        return $benchmarks;
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
     * @param array<string, array{available:bool, version:string|null}> $tools
     * @return array{packageFile:string, expectedGhcVersions:list<string>, presentGhcVersions:list<string>, missingGhcVersions:list<string>, toolGhcVersion:string|null, toolGhcVersionSupported:bool}
     */
    private static function auditCompilerTestedWithClosure(string $root, array $tools): array
    {
        $packageFile = 'pandoc.cabal';
        $path = $root . DIRECTORY_SEPARATOR . $packageFile;
        $present = is_file($path) ? self::parseCabalTestedWithGhcVersions((string) file_get_contents($path)) : [];
        $missing = [];

        foreach (self::TESTED_GHC_VERSIONS as $version) {
            if (!in_array($version, $present, true)) {
                $missing[] = $version;
            }
        }

        $toolVersion = self::normalizeGhcToolVersion($tools['ghc']['version'] ?? null);

        return [
            'packageFile' => $packageFile,
            'expectedGhcVersions' => self::TESTED_GHC_VERSIONS,
            'presentGhcVersions' => $present,
            'missingGhcVersions' => $missing,
            'toolGhcVersion' => $toolVersion,
            'toolGhcVersionSupported' => $toolVersion !== null && in_array($toolVersion, self::TESTED_GHC_VERSIONS, true),
        ];
    }

    /**
     * @return array{expectedDependencies:array<string, list<string>>, expectedDependencyConstraints:array<string, array<string, string>>, expectedExecutableOptions:array<string, list<string>>, expectedDefaultLanguages:array<string, string>, expectedMixins:array<string, list<string>>, expectedBuildTools:array<string, list<string>>, expectedOtherModules:array<string, list<string>>, present:array<string, array{packageFile:string, type:string|null, buildable:bool|null, mainIs:string|null, sourceDirectories:list<string>, buildDepends:list<string>, dependencyConstraints:array<string, string>, ghcOptions:list<string>, defaultLanguage:string|null, mixins:list<string>, buildToolDepends:list<string>, buildTools:list<string>, otherModules:list<string>}>, missingTargets:list<string>, mismatchedEntryPoints:array<string, list<string>>, missingDependencies:array<string, list<string>>, mismatchedDependencyConstraints:array<string, array<string, array{expected:string, actual:string}>>, missingExecutableOptions:array<string, list<string>>, mismatchedDefaultLanguages:array<string, array{expected:string, actual:string|null}>, unexpectedMixins:array<string, list<string>>, unexpectedBuildTools:array<string, list<string>>, missingOtherModules:array<string, list<string>>}
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
                'dependencyConstraints' => $suites[$suiteName]['dependencyConstraints'],
                'ghcOptions' => $suites[$suiteName]['ghcOptions'],
                'defaultLanguage' => $suites[$suiteName]['defaultLanguage'],
                'mixins' => $suites[$suiteName]['mixins'],
                'buildToolDepends' => $suites[$suiteName]['buildToolDepends'],
                'buildTools' => $suites[$suiteName]['buildTools'],
                'otherModules' => $suites[$suiteName]['otherModules'],
            ];
        }

        $missingTargets = [];
        $mismatchedEntryPoints = [];
        $missingDependencies = [];
        $mismatchedDependencyConstraints = [];
        $missingExecutableOptions = [];
        $mismatchedDefaultLanguages = [];
        $unexpectedMixins = [];
        $unexpectedBuildTools = [];
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

            foreach (self::RUNNER_DEPENDENCY_CONSTRAINTS[$target] ?? [] as $dependency => $expectedConstraint) {
                if (!in_array($dependency, $present[$target]['buildDepends'], true)) {
                    continue;
                }

                $actualConstraint = $present[$target]['dependencyConstraints'][$dependency] ?? '';
                if ($actualConstraint !== $expectedConstraint) {
                    $mismatchedDependencyConstraints[$target][$dependency] = [
                        'expected' => $expectedConstraint,
                        'actual' => $actualConstraint,
                    ];
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

            $expectedMixins = self::RUNNER_EXPECTED_MIXINS[$target] ?? [];
            foreach ($present[$target]['mixins'] as $mixin) {
                if (!in_array($mixin, $expectedMixins, true)) {
                    $unexpectedMixins[$target][] = $mixin;
                }
            }

            $unexpectedBuildTools[$target] = self::unexpectedCabalBuildTools($present[$target]['buildToolDepends'], $present[$target]['buildTools']);
            if ($unexpectedBuildTools[$target] === []) {
                unset($unexpectedBuildTools[$target]);
            }

            foreach (self::RUNNER_OTHER_MODULES[$target] as $module) {
                if (!in_array($module, $present[$target]['otherModules'], true)) {
                    $missingOtherModules[$target][] = $module;
                }
            }
        }

        return [
            'expectedDependencies' => self::RUNNER_DIRECT_DEPENDENCIES,
            'expectedDependencyConstraints' => self::RUNNER_DEPENDENCY_CONSTRAINTS,
            'expectedExecutableOptions' => self::RUNNER_EXECUTABLE_OPTIONS,
            'expectedDefaultLanguages' => self::RUNNER_DEFAULT_LANGUAGES,
            'expectedMixins' => self::RUNNER_EXPECTED_MIXINS,
            'expectedBuildTools' => self::RUNNER_EXPECTED_BUILD_TOOLS,
            'expectedOtherModules' => self::RUNNER_OTHER_MODULES,
            'present' => $present,
            'missingTargets' => $missingTargets,
            'mismatchedEntryPoints' => $mismatchedEntryPoints,
            'missingDependencies' => $missingDependencies,
            'mismatchedDependencyConstraints' => $mismatchedDependencyConstraints,
            'missingExecutableOptions' => $missingExecutableOptions,
            'mismatchedDefaultLanguages' => $mismatchedDefaultLanguages,
            'unexpectedMixins' => $unexpectedMixins,
            'unexpectedBuildTools' => $unexpectedBuildTools,
            'missingOtherModules' => $missingOtherModules,
        ];
    }

    /**
     * @return array{expectedDependencies:array<string, list<string>>, expectedDependencyConstraints:array<string, array<string, string>>, expectedExecutableOptions:array<string, list<string>>, expectedDefaultLanguages:array<string, string>, expectedMixins:array<string, list<string>>, expectedBuildTools:array<string, list<string>>, present:array<string, array{packageFile:string, type:string|null, buildable:bool|null, mainIs:string|null, sourceDirectories:list<string>, buildDepends:list<string>, dependencyConstraints:array<string, string>, ghcOptions:list<string>, defaultLanguage:string|null, mixins:list<string>, buildToolDepends:list<string>, buildTools:list<string>}>, missingTargets:list<string>, mismatchedEntryPoints:array<string, list<string>>, missingDependencies:array<string, list<string>>, mismatchedDependencyConstraints:array<string, array<string, array{expected:string, actual:string}>>, missingExecutableOptions:array<string, list<string>>, mismatchedDefaultLanguages:array<string, array{expected:string, actual:string|null}>, unexpectedMixins:array<string, list<string>>, unexpectedBuildTools:array<string, list<string>>}
     */
    private static function auditBenchmarkDependencyClosure(string $root): array
    {
        $present = [];
        foreach (self::BENCHMARK_ENTRY_POINTS as $target => $entryPoint) {
            $packageFile = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $entryPoint['packageFile']);
            if (!is_file($packageFile)) {
                continue;
            }

            $benchmarkName = substr($target, strlen('benchmark:'));
            $benchmarks = self::parseCabalBenchmarks((string) file_get_contents($packageFile));
            if (!array_key_exists($benchmarkName, $benchmarks)) {
                continue;
            }

            $present[$target] = [
                'packageFile' => $entryPoint['packageFile'],
                'type' => $benchmarks[$benchmarkName]['type'],
                'buildable' => $benchmarks[$benchmarkName]['buildable'],
                'mainIs' => $benchmarks[$benchmarkName]['mainIs'],
                'sourceDirectories' => $benchmarks[$benchmarkName]['sourceDirectories'],
                'buildDepends' => $benchmarks[$benchmarkName]['buildDepends'],
                'dependencyConstraints' => $benchmarks[$benchmarkName]['dependencyConstraints'],
                'ghcOptions' => $benchmarks[$benchmarkName]['ghcOptions'],
                'defaultLanguage' => $benchmarks[$benchmarkName]['defaultLanguage'],
                'mixins' => $benchmarks[$benchmarkName]['mixins'],
                'buildToolDepends' => $benchmarks[$benchmarkName]['buildToolDepends'],
                'buildTools' => $benchmarks[$benchmarkName]['buildTools'],
            ];
        }

        $missingTargets = [];
        $mismatchedEntryPoints = [];
        $missingDependencies = [];
        $mismatchedDependencyConstraints = [];
        $missingExecutableOptions = [];
        $mismatchedDefaultLanguages = [];
        $unexpectedMixins = [];
        $unexpectedBuildTools = [];

        foreach (self::BENCHMARK_ENTRY_POINTS as $target => $entryPoint) {
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

            foreach (self::BENCHMARK_DIRECT_DEPENDENCIES[$target] as $dependency) {
                if (!in_array($dependency, $present[$target]['buildDepends'], true)) {
                    $missingDependencies[$target][] = $dependency;
                }
            }

            foreach (self::BENCHMARK_DEPENDENCY_CONSTRAINTS[$target] ?? [] as $dependency => $expectedConstraint) {
                if (!in_array($dependency, $present[$target]['buildDepends'], true)) {
                    continue;
                }

                $actualConstraint = $present[$target]['dependencyConstraints'][$dependency] ?? '';
                if ($actualConstraint !== $expectedConstraint) {
                    $mismatchedDependencyConstraints[$target][$dependency] = [
                        'expected' => $expectedConstraint,
                        'actual' => $actualConstraint,
                    ];
                }
            }

            foreach (self::BENCHMARK_EXECUTABLE_OPTIONS[$target] as $option) {
                if (!in_array($option, $present[$target]['ghcOptions'], true)) {
                    $missingExecutableOptions[$target][] = $option;
                }
            }

            $expectedLanguage = self::BENCHMARK_DEFAULT_LANGUAGES[$target];
            if ($present[$target]['defaultLanguage'] !== $expectedLanguage) {
                $mismatchedDefaultLanguages[$target] = [
                    'expected' => $expectedLanguage,
                    'actual' => $present[$target]['defaultLanguage'],
                ];
            }

            $expectedMixins = self::BENCHMARK_EXPECTED_MIXINS[$target] ?? [];
            foreach ($present[$target]['mixins'] as $mixin) {
                if (!in_array($mixin, $expectedMixins, true)) {
                    $unexpectedMixins[$target][] = $mixin;
                }
            }

            $unexpectedBuildTools[$target] = self::unexpectedCabalBuildTools($present[$target]['buildToolDepends'], $present[$target]['buildTools']);
            if ($unexpectedBuildTools[$target] === []) {
                unset($unexpectedBuildTools[$target]);
            }
        }

        return [
            'expectedDependencies' => self::BENCHMARK_DIRECT_DEPENDENCIES,
            'expectedDependencyConstraints' => self::BENCHMARK_DEPENDENCY_CONSTRAINTS,
            'expectedExecutableOptions' => self::BENCHMARK_EXECUTABLE_OPTIONS,
            'expectedDefaultLanguages' => self::BENCHMARK_DEFAULT_LANGUAGES,
            'expectedMixins' => self::BENCHMARK_EXPECTED_MIXINS,
            'expectedBuildTools' => self::BENCHMARK_EXPECTED_BUILD_TOOLS,
            'present' => $present,
            'missingTargets' => $missingTargets,
            'mismatchedEntryPoints' => $mismatchedEntryPoints,
            'missingDependencies' => $missingDependencies,
            'mismatchedDependencyConstraints' => $mismatchedDependencyConstraints,
            'missingExecutableOptions' => $missingExecutableOptions,
            'mismatchedDefaultLanguages' => $mismatchedDefaultLanguages,
            'unexpectedMixins' => $unexpectedMixins,
            'unexpectedBuildTools' => $unexpectedBuildTools,
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
     * @return array{expected:array<string, array{entryFile:string, requiredSnippets:array<string, string>}>, present:array<string, array{entryFile:string, matchedSnippets:list<string>}>, missingTargets:list<string>, missingSemantics:array<string, list<string>>}
     */
    private static function auditBenchmarkEntrySourceClosure(string $root): array
    {
        $present = [];
        $missingTargets = [];
        $missingSemantics = [];

        foreach (self::BENCHMARK_ENTRY_SOURCE_SEMANTICS as $target => $expected) {
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
            'expected' => self::BENCHMARK_ENTRY_SOURCE_SEMANTICS,
            'present' => $present,
            'missingTargets' => $missingTargets,
            'missingSemantics' => $missingSemantics,
        ];
    }

    /**
     * @return array{expected:array<string, string>, present:list<string>, missing:list<string>, wrongType:array<string, array{expected:string, actual:string}>, emptyFiles:list<string>}
     */
    private static function auditRunnerArtifactClosure(string $root): array
    {
        $present = [];
        $missing = [];
        $wrongType = [];
        $emptyFiles = [];

        foreach (self::requiredRunnerArtifacts() as $relativePath => $expectedKind) {
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
            if ($expectedKind === 'file' && filesize($path) === 0) {
                $emptyFiles[] = $relativePath;
            }
        }

        return [
            'expected' => self::requiredRunnerArtifacts(),
            'present' => $present,
            'missing' => $missing,
            'wrongType' => $wrongType,
            'emptyFiles' => $emptyFiles,
        ];
    }

    /**
     * @return array{expected:array<string, string>, present:list<string>, missing:list<string>, wrongType:array<string, array{expected:string, actual:string}>, emptyFiles:list<string>}
     */
    private static function auditBenchmarkArtifactClosure(string $root): array
    {
        $present = [];
        $missing = [];
        $wrongType = [];
        $emptyFiles = [];

        foreach (self::BENCHMARK_ARTIFACTS as $relativePath => $expectedKind) {
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
            if ($expectedKind === 'file' && filesize($path) === 0) {
                $emptyFiles[] = $relativePath;
            }
        }

        return [
            'expected' => self::BENCHMARK_ARTIFACTS,
            'present' => $present,
            'missing' => $missing,
            'wrongType' => $wrongType,
            'emptyFiles' => $emptyFiles,
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function requiredRunnerArtifacts(): array
    {
        $artifacts = self::REQUIRED_RUNNER_ARTIFACTS;

        foreach (self::RUNNER_OTHER_MODULES as $target => $modules) {
            $entryPoint = self::RUNNER_ENTRY_POINTS[$target] ?? null;
            if ($entryPoint === null) {
                continue;
            }

            $packageRoot = dirname($entryPoint['packageFile']);
            $packagePrefix = $packageRoot === '.' ? '' : str_replace('\\', '/', $packageRoot) . '/';
            $sourceDirectory = trim($entryPoint['sourceDirectory'], '/');
            $sourcePrefix = $sourceDirectory === '' ? '' : $sourceDirectory . '/';

            foreach ($modules as $module) {
                $relativePath = $packagePrefix . $sourcePrefix . str_replace('.', '/', $module) . '.hs';
                $artifacts[$relativePath] = 'file';
            }
        }

        ksort($artifacts);
        return $artifacts;
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
        $lastFieldIndent = null;
        $conditionalIndent = null;

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            if (preg_match('/^library\s*$/i', $line) === 1) {
                $currentKey = 'library:default';
                $stanzas[$currentKey] = [
                    'type' => 'library',
                    'name' => 'default',
                    'fields' => [],
                ];
                $lastField = null;
                $lastFieldIndent = null;
                $conditionalIndent = null;
                continue;
            }

            if (preg_match('/^([A-Za-z][A-Za-z0-9-]*)\s+([A-Za-z0-9_.-]+)\s*$/', $line, $match) === 1) {
                $type = strtolower($match[1]);
                if (in_array($type, ['test-suite', 'benchmark', 'common', 'library'], true)) {
                    $currentKey = $type . ':' . $match[2];
                    $stanzas[$currentKey] = [
                        'type' => $type,
                        'name' => $match[2],
                        'fields' => [],
                    ];
                    $lastField = null;
                    $lastFieldIndent = null;
                    $conditionalIndent = null;
                    continue;
                }
            }

            if ($currentKey === null) {
                continue;
            }

            if (preg_match('/^\S/', $line) === 1) {
                $currentKey = null;
                $lastField = null;
                $lastFieldIndent = null;
                $conditionalIndent = null;
                continue;
            }

            $trimmed = trim($line);
            $indent = strlen($line) - strlen(ltrim($line));

            if ($conditionalIndent !== null) {
                if ($trimmed === '') {
                    continue;
                }

                if ($indent > $conditionalIndent) {
                    continue;
                }

                if ($indent === $conditionalIndent && preg_match('/^(?:elif|else)\b/i', $trimmed) === 1) {
                    $lastField = null;
                    $lastFieldIndent = null;
                    continue;
                }

                $conditionalIndent = null;
            }

            if (preg_match('/^(?:if|elif|else)\b/i', $trimmed) === 1) {
                $conditionalIndent = $indent;
                $lastField = null;
                $lastFieldIndent = null;
                continue;
            }

            if ($lastField !== null && $lastFieldIndent !== null && $indent > $lastFieldIndent && preg_match('/^\s+(.*?)\s*$/', $line, $match) === 1) {
                $continuation = trim($match[1]);
                if ($continuation !== '') {
                    $stanzas[$currentKey]['fields'][$lastField] .= "\n" . $continuation;
                }
                continue;
            }

            if (preg_match('/^\s*([A-Za-z0-9_-]+)\s*:\s*(.*?)\s*$/', $line, $match) === 1) {
                $lastField = strtolower($match[1]);
                $lastFieldIndent = $indent;
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
            if (in_array($field, ['build-depends', 'build-tool-depends', 'build-tools', 'other-modules', 'mixins'], true) && array_key_exists($field, $base) && $base[$field] !== '') {
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

    private static function normalizeCabalProjectForUnconditionalAudit(string $raw): string
    {
        return self::stripCabalConditionalBlocks(self::stripCabalLineComments($raw));
    }

    private static function stripCabalConditionalBlocks(string $raw): string
    {
        $lines = [];
        $conditionalIndent = null;

        foreach (preg_split('/\R/', $raw) ?: [] as $line) {
            $trimmed = trim($line);
            $indent = strlen($line) - strlen(ltrim($line));

            if ($conditionalIndent !== null) {
                if ($trimmed === '') {
                    continue;
                }

                if ($indent > $conditionalIndent) {
                    continue;
                }

                if ($indent === $conditionalIndent && preg_match('/^(?:elif|else)\b/i', $trimmed) === 1) {
                    continue;
                }

                $conditionalIndent = null;
            }

            if (preg_match('/^(?:if|elif|else)\b/i', $trimmed) === 1) {
                $conditionalIndent = $indent;
                continue;
            }

            $lines[] = $line;
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
     * @return array<string, string>
     */
    private static function extractCabalDependencyConstraints(string $raw): array
    {
        $raw = self::stripCabalLineComments($raw);
        $constraints = [];
        foreach (explode(',', str_replace("\n", ' ', $raw)) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            if (preg_match('/^([A-Za-z][A-Za-z0-9_-]*)\b(.*?)$/', $part, $match) === 1) {
                $constraints[$match[1]] = self::normalizeCabalConstraint($match[2]);
            }
        }

        ksort($constraints);
        return $constraints;
    }

    private static function normalizeCabalConstraint(string $raw): string
    {
        return preg_replace('/\s+/', ' ', trim($raw)) ?? trim($raw);
    }

    private static function normalizeGhcToolVersion(?string $version): ?string
    {
        if ($version === null) {
            return null;
        }

        if (preg_match('/\b([0-9]+(?:\.[0-9]+){1,3})\b/', $version, $match) === 1) {
            return $match[1];
        }

        $version = trim($version);
        return $version === '' ? null : $version;
    }

    /**
     * @return list<string>
     */
    private static function extractCabalMixinSpecs(string $raw): array
    {
        $raw = self::stripCabalLineComments($raw);
        $mixins = [];

        foreach (self::splitCabalCommaList($raw) as $part) {
            if ($part !== '' && !in_array($part, $mixins, true)) {
                $mixins[] = $part;
            }
        }

        sort($mixins);
        return $mixins;
    }

    /**
     * @return list<string>
     */
    private static function extractCabalBuildToolDepends(string $raw): array
    {
        $raw = self::stripCabalLineComments($raw);
        $tools = [];

        foreach (self::splitCabalCommaList($raw) as $part) {
            if ($part !== '' && !in_array($part, $tools, true)) {
                $tools[] = $part;
            }
        }

        return $tools;
    }

    /**
     * @return list<string>
     */
    private static function extractCabalBuildTools(string $raw): array
    {
        $raw = self::stripCabalLineComments($raw);
        $tools = [];

        foreach (self::splitCabalCommaList($raw) as $part) {
            if ($part === '') {
                continue;
            }

            $items = preg_match('/[<>=:]/', $part) === 1 ? [$part] : self::splitWords($part);
            foreach ($items as $item) {
                if ($item !== '' && !in_array($item, $tools, true)) {
                    $tools[] = $item;
                }
            }
        }

        return $tools;
    }

    /**
     * @param list<string> $buildToolDepends
     * @param list<string> $buildTools
     * @return list<string>
     */
    private static function unexpectedCabalBuildTools(array $buildToolDepends, array $buildTools): array
    {
        $unexpected = [];
        foreach ($buildToolDepends as $tool) {
            $unexpected[] = 'build-tool-depends: ' . $tool;
        }
        foreach ($buildTools as $tool) {
            $unexpected[] = 'build-tools: ' . $tool;
        }

        return $unexpected;
    }

    /**
     * @return list<string>
     */
    private static function splitCabalCommaList(string $raw): array
    {
        $raw = str_replace(["\r\n", "\r", "\n"], ' ', $raw);
        $parts = [];
        $current = '';
        $depth = 0;
        $length = strlen($raw);

        for ($offset = 0; $offset < $length; $offset++) {
            $char = $raw[$offset];
            if ($char === '(') {
                $depth++;
                $current .= $char;
                continue;
            }

            if ($char === ')') {
                $depth = max(0, $depth - 1);
                $current .= $char;
                continue;
            }

            if ($char === ',' && $depth === 0) {
                $parts[] = self::normalizeCabalListItem($current);
                $current = '';
                continue;
            }

            $current .= $char;
        }

        $parts[] = self::normalizeCabalListItem($current);

        return array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));
    }

    private static function normalizeCabalListItem(string $raw): string
    {
        return preg_replace('/\s+/', ' ', trim($raw)) ?? trim($raw);
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
     * @param array<string, array<string, array{expected:string, actual:string}>> $failures
     */
    private static function formatTargetConstraintMismatches(array $failures): string
    {
        $parts = [];
        foreach ($failures as $target => $items) {
            $itemParts = [];
            foreach ($items as $dependency => $state) {
                $itemParts[] = $dependency . ' expected ' . $state['expected'] . ', found ' . ($state['actual'] === '' ? 'none' : $state['actual']);
            }
            $parts[] = $target . ' (' . implode(', ', $itemParts) . ')';
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
     * @param array{missingGhcVersions:list<string>, toolGhcVersionSupported:bool} $compilerTestedWithClosure
     * @param array{missing:list<string>, mismatched:array<string, array{expected:string, actual:string}>} $projectPins
     * @param array{missing:list<string>, mismatched:array<string, array{expected:array{type:string, location:string}, actual:array{type:string|null, location:string}>>} $projectSourceRepositoryClosure
     * @param array{missingPackages:list<string>, missingFlags:array<string, list<string>>, mismatchedFlags:array<string, array<string, array{expected:bool, actual:bool|null}>>} $projectPackageClosure
     * @param array{missingConstraints:list<string>, mismatchedConstraints:array<string, array{expected:string, actual:string}>} $projectConstraintClosure
     * @param array{missingTargets:list<string>, mismatchedEntryPoints:array<string, list<string>>, missingDependencies:array<string, list<string>>, mismatchedDependencyConstraints:array<string, array<string, array{expected:string, actual:string}>>, missingExecutableOptions:array<string, list<string>>, mismatchedDefaultLanguages:array<string, array{expected:string, actual:string|null}>, unexpectedMixins:array<string, list<string>>, unexpectedBuildTools:array<string, list<string>>, missingOtherModules:array<string, list<string>>} $runnerDependencyClosure
     * @param array{missingTargets:list<string>, mismatchedEntryPoints:array<string, list<string>>, missingDependencies:array<string, list<string>>, mismatchedDependencyConstraints:array<string, array<string, array{expected:string, actual:string}>>, missingExecutableOptions:array<string, list<string>>, mismatchedDefaultLanguages:array<string, array{expected:string, actual:string|null}>, unexpectedMixins:array<string, list<string>>, unexpectedBuildTools:array<string, list<string>>} $benchmarkDependencyClosure
     * @param array{missingDependencies:list<string>} $luaEngineLibraryClosure
     * @param array{missingTargets:list<string>, missingSemantics:array<string, list<string>>} $runnerEntrySourceClosure
     * @param array{missing:list<string>, wrongType:array<string, array{expected:string, actual:string}>, emptyFiles:list<string>} $runnerArtifactClosure
     * @param array{missing:list<string>, wrongType:array<string, array{expected:string, actual:string}>, emptyFiles:list<string>} $benchmarkArtifactClosure
     * @param array{missingTargets:list<string>, missingSemantics:array<string, list<string>>} $benchmarkEntrySourceClosure
     */
    private static function activationGate(array $missingFiles, array $missingTools, array $compilerTestedWithClosure, array $projectPins, array $projectSourceRepositoryClosure, array $projectPackageClosure, array $projectConstraintClosure, array $runnerDependencyClosure, array $benchmarkDependencyClosure, array $luaEngineLibraryClosure, array $runnerEntrySourceClosure, array $runnerArtifactClosure, array $benchmarkArtifactClosure, array $benchmarkEntrySourceClosure): string
    {
        if (
            $missingFiles === []
            && $missingTools === []
            && $compilerTestedWithClosure['missingGhcVersions'] === []
            && $compilerTestedWithClosure['toolGhcVersionSupported'] === true
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
            && $runnerDependencyClosure['mismatchedDependencyConstraints'] === []
            && $runnerDependencyClosure['missingExecutableOptions'] === []
            && $runnerDependencyClosure['mismatchedDefaultLanguages'] === []
            && $runnerDependencyClosure['unexpectedMixins'] === []
            && $runnerDependencyClosure['unexpectedBuildTools'] === []
            && $runnerDependencyClosure['missingOtherModules'] === []
            && $benchmarkDependencyClosure['missingTargets'] === []
            && $benchmarkDependencyClosure['mismatchedEntryPoints'] === []
            && $benchmarkDependencyClosure['missingDependencies'] === []
            && $benchmarkDependencyClosure['mismatchedDependencyConstraints'] === []
            && $benchmarkDependencyClosure['missingExecutableOptions'] === []
            && $benchmarkDependencyClosure['mismatchedDefaultLanguages'] === []
            && $benchmarkDependencyClosure['unexpectedMixins'] === []
            && $benchmarkDependencyClosure['unexpectedBuildTools'] === []
            && $luaEngineLibraryClosure['missingDependencies'] === []
            && $runnerEntrySourceClosure['missingTargets'] === []
            && $runnerEntrySourceClosure['missingSemantics'] === []
            && $runnerArtifactClosure['missing'] === []
            && $runnerArtifactClosure['wrongType'] === []
            && $runnerArtifactClosure['emptyFiles'] === []
            && $benchmarkArtifactClosure['missing'] === []
            && $benchmarkArtifactClosure['wrongType'] === []
            && $benchmarkArtifactClosure['emptyFiles'] === []
            && $benchmarkEntrySourceClosure['missingTargets'] === []
            && $benchmarkEntrySourceClosure['missingSemantics'] === []
        ) {
            return 'Hydrated Pandoc checkout, required Cabal toolchain, pandoc.cabal tested-with GHC matrix, cabal.project package/flag/constraint closure, exact cabal.project source-repository Git types and locations, non-empty runner source/golden fixtures, runner entry-point source semantics including command-emulation parser/error handling and full Tasty group dispatch, buildable runner test-suite stanzas, exitcode-stdio runner types, direct build-depends with pinned version constraints, Haskell2010 default-language closure, no unexpected runner or benchmark mixins, no runner or benchmark build-tool dependencies, runner other-modules closure, pandoc-lua-engine library HsLua module dependency closure, non-empty benchmark component dependency/artifact closure, benchmark entry-point source semantics, executable options, and Git pins are present; record a non-mutating solver/build plan before any Haskell runner or benchmark execution.';
        }

        return 'Hydrate Pandoc upstream commit ' . self::UPSTREAM_COMMIT
            . ' with pandoc.cabal tested-with GHC matrix, cabal.project package entries/flags/constraints, exact cabal.project source-repository Git types and locations, pandoc.cabal, pandoc-lua-engine/pandoc-lua-engine.cabal, non-empty runner source/golden fixtures, non-empty benchmark source/data artifacts, runner entry-point source semantics including command-emulation parser/error handling and full Tasty group dispatch, benchmark entry-point source semantics, buildable exitcode-stdio test-suite types and buildable benchmark components, Haskell2010 default-language closure, test entry points and benchmark entry points, direct runner build-depends and benchmark build-depends with pinned version constraints, no unexpected runner or benchmark mixins, no runner or benchmark build-tool dependencies, runner other-modules closure, pandoc-lua-engine library HsLua module dependency closure, runner and benchmark executable options, ghc, cabal, and exact cabal.project Git source-repository pins before attempting a runner plan.';
    }
}
