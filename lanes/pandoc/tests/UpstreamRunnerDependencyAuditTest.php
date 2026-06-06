<?php

declare(strict_types=1);

use PortLibs\Pandoc\UpstreamRunnerDependencyAudit;

$makeTree = static function (array $files): string {
    $root = sys_get_temp_dir() . '/pandoc-runner-audit-' . bin2hex(random_bytes(6));
    if (!mkdir($root, 0777, true) && !is_dir($root)) {
        throw new RuntimeException('Unable to create runner audit fixture directory');
    }

    foreach ($files as $relativePath => $contents) {
        $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $relativePath);
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create runner audit fixture subdirectory');
        }
        file_put_contents($path, (string) $contents);
    }

    return $root;
};

$removeTree = static function (string $root): void {
    if (!is_dir($root)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $fileInfo) {
        if ($fileInfo->isDir()) {
            rmdir($fileInfo->getPathname());
        } else {
            unlink($fileInfo->getPathname());
        }
    }
    rmdir($root);
};

$pinnedProject = static function (array $overrides = []): string {
    $pins = array_merge(UpstreamRunnerDependencyAudit::expectedProjectPins(), $overrides);
    $lines = [
        'packages: . pandoc-lua-engine pandoc-server pandoc-cli',
        'constraints: skylighting-format-blaze-html >= 0.1.2, skylighting-format-context >= 0.1.0.2, auto-update >= 0.2.6, crypton >= 1.1.1',
        '',
        'package pandoc',
        '  flags: +embed_data_files +http',
        '',
    ];

    foreach ($pins as $name => $tag) {
        $lines[] = 'source-repository-package';
        $lines[] = '  type: git';
        $lines[] = '  location: https://github.com/jgm/' . $name . '.git';
        $lines[] = '  tag: ' . $tag;
        $lines[] = '';
    }

    return implode("\n", $lines);
};

$formatRunnerDependencies = static function (string $target, array $dependencies): array {
    $constraints = UpstreamRunnerDependencyAudit::expectedRunnerDependencyConstraints()[$target] ?? [];
    $formatted = [];
    foreach ($dependencies as $dependency) {
        $constraint = $constraints[$dependency] ?? '';
        $formatted[] = $constraint === '' ? $dependency : $dependency . ' ' . $constraint;
    }

    return $formatted;
};

$formatBenchmarkDependencies = static function (string $target, array $dependencies): array {
    $constraints = UpstreamRunnerDependencyAudit::expectedBenchmarkDependencyConstraints()[$target] ?? [];
    $formatted = [];
    foreach ($dependencies as $dependency) {
        $constraint = $constraints[$dependency] ?? '';
        $formatted[] = $constraint === '' ? $dependency : $dependency . ' ' . $constraint;
    }

    return $formatted;
};

$pandocBenchmark = static function (array $without = [], ?string $mainIs = null, ?string $sourceDirectory = null, ?string $ghcOptions = null, string $type = 'exitcode-stdio-1.0', ?string $buildable = null, ?string $defaultLanguage = null) use ($formatBenchmarkDependencies): string {
    $dependencies = array_values(array_diff(
        UpstreamRunnerDependencyAudit::expectedBenchmarkDependencies()['benchmark:benchmark-pandoc'],
        $without
    ));

    $benchmark = [
        '',
        'benchmark benchmark-pandoc',
        '  import: common-executable',
        '  type: ' . $type,
    ];
    if ($buildable !== null) {
        $benchmark[] = '  buildable: ' . $buildable;
    }
    if ($defaultLanguage !== null && $defaultLanguage !== '') {
        $benchmark[] = '  default-language: ' . $defaultLanguage;
    }

    return implode("\n", array_merge($benchmark, [
        '  main-is: ' . ($mainIs ?? 'benchmark-pandoc.hs'),
        '  hs-source-dirs: ' . ($sourceDirectory ?? 'benchmark'),
        '  build-depends:',
        '    ' . implode(",\n    ", $formatBenchmarkDependencies('benchmark:benchmark-pandoc', $dependencies)),
        '  ghc-options: ' . ($ghcOptions ?? '-rtsopts -with-rtsopts=-A8m -threaded'),
    ]));
};

$pandocCabal = static function (array $without = [], ?string $mainIs = null, ?string $sourceDirectory = null, ?string $ghcOptions = null, string $type = 'exitcode-stdio-1.0', ?string $buildable = null, ?string $defaultLanguage = 'Haskell2010', ?string $benchmarkStanza = null) use ($formatRunnerDependencies, $pandocBenchmark): string {
    $dependencies = array_values(array_diff(
        UpstreamRunnerDependencyAudit::expectedRunnerDependencies()['test:test-pandoc'],
        $without
    ));
    $commonDependencies = array_values(array_intersect($dependencies, ['base', 'pandoc']));
    $suiteDependencies = array_values(array_diff($dependencies, $commonDependencies));
    $commonExecutable = [
        'common common-executable',
        '  import: common-options',
    ];
    if ($ghcOptions !== '') {
        $commonExecutable[] = '  ghc-options: ' . ($ghcOptions ?? '-rtsopts -with-rtsopts=-A8m -threaded');
    }

    $testSuite = [
        '',
        'test-suite test-pandoc',
        '  import: common-executable',
        '  type: ' . $type,
    ];
    if ($buildable !== null) {
        $testSuite[] = '  buildable: ' . $buildable;
    }
    $testSuite = array_merge($testSuite, [
        '  main-is: ' . ($mainIs ?? 'test-pandoc.hs'),
        '  hs-source-dirs: ' . ($sourceDirectory ?? 'test'),
        '  build-depends:',
        '    ' . implode(",\n    ", $formatRunnerDependencies('test:test-pandoc', $suiteDependencies)),
        '  other-modules:',
        '    ' . implode(",\n    ", UpstreamRunnerDependencyAudit::expectedRunnerOtherModules()['test:test-pandoc']),
    ]);

    $body = implode("\n", array_merge([
        'tested-with: GHC == 9.6.7, GHC == 9.8.4, GHC == 9.10.3, GHC == 9.12.2',
        '',
        'common common-options',
        '  build-depends: ' . implode(', ', $formatRunnerDependencies('test:test-pandoc', $commonDependencies)),
        $defaultLanguage === null || $defaultLanguage === '' ? '' : '  default-language: ' . $defaultLanguage,
        '',
    ], $commonExecutable, $testSuite));

    return $body . "\n" . ($benchmarkStanza ?? $pandocBenchmark());
};

$luaCabal = static function (array $without = [], ?string $mainIs = null, ?string $sourceDirectory = null, string $type = 'exitcode-stdio-1.0', ?string $buildable = null, ?string $defaultLanguage = 'Haskell2010', array $libraryWithout = []) use ($formatRunnerDependencies): string {
    $dependencies = array_values(array_diff(
        UpstreamRunnerDependencyAudit::expectedRunnerDependencies()['test:test-pandoc-lua-engine'],
        $without
    ));
    $commonDependencies = array_values(array_intersect($dependencies, ['base']));
    $suiteDependencies = array_values(array_diff($dependencies, $commonDependencies));
    $libraryDependencies = array_values(array_diff(
        UpstreamRunnerDependencyAudit::expectedLuaEngineLibraryDependencies(),
        $libraryWithout
    ));

    $stanzas = [
        'common test-options',
        '  build-depends: ' . implode(', ', $formatRunnerDependencies('test:test-pandoc-lua-engine', $commonDependencies)),
        $defaultLanguage === null || $defaultLanguage === '' ? '' : '  default-language: ' . $defaultLanguage,
        '',
        'library',
        '  import: test-options',
        '  build-depends:',
        '    ' . implode(",\n    ", $libraryDependencies),
        '',
        'test-suite test-pandoc-lua-engine',
        '  import: test-options',
        '  type: ' . $type,
    ];
    if ($buildable !== null) {
        $stanzas[] = '  buildable: ' . $buildable;
    }
    return implode("\n", array_merge($stanzas, [
        '  main-is: ' . ($mainIs ?? 'test-pandoc-lua-engine.hs'),
        '  hs-source-dirs: ' . ($sourceDirectory ?? 'test'),
        '  build-depends:',
        '    ' . implode(",\n    ", $formatRunnerDependencies('test:test-pandoc-lua-engine', $suiteDependencies)),
        '  other-modules:',
        '    ' . implode(",\n    ", UpstreamRunnerDependencyAudit::expectedRunnerOtherModules()['test:test-pandoc-lua-engine']),
    ]));
};

$testPandocEntryPoint = static function (): string {
    return implode("\n", [
        'module Main (main) where',
        '',
        'import System.Environment (getArgs, getExecutablePath)',
        'import qualified Control.Exception as E',
        'import Text.Pandoc.App (convertWithOpts, handleOptInfo, defaultOpts, options,',
        '                        parseOptionsFromArgs)',
        'import Text.Pandoc.Error (handleError)',
        'import Text.Pandoc.Scripting (noEngine)',
        'import GHC.IO.Encoding',
        'import Test.Tasty',
        'import qualified Tests.Command',
        'import qualified Tests.Old',
        'import qualified Tests.Readers.Creole',
        'import qualified Tests.Readers.Docx',
        'import qualified Tests.Readers.Pptx',
        'import qualified Tests.Readers.Xlsx',
        'import qualified Tests.Readers.DokuWiki',
        'import qualified Tests.Readers.EPUB',
        'import qualified Tests.Readers.FB2',
        'import qualified Tests.Readers.HTML',
        'import qualified Tests.Readers.JATS',
        'import qualified Tests.Readers.Jira',
        'import qualified Tests.Readers.LaTeX',
        'import qualified Tests.Readers.Markdown',
        'import qualified Tests.Readers.Muse',
        'import qualified Tests.Readers.ODT',
        'import qualified Tests.Readers.Org',
        'import qualified Tests.Readers.RST',
        'import qualified Tests.Readers.RTF',
        'import qualified Tests.Readers.Txt2Tags',
        'import qualified Tests.Readers.Man',
        'import qualified Tests.Readers.Mdoc',
        'import qualified Tests.Readers.Pod',
        'import qualified Tests.Shared',
        'import qualified Tests.Writers.AsciiDoc',
        'import qualified Tests.Writers.ConTeXt',
        'import qualified Tests.Writers.DocBook',
        'import qualified Tests.Writers.Docx',
        'import qualified Tests.Writers.FB2',
        'import qualified Tests.Writers.HTML',
        'import qualified Tests.Writers.JATS',
        'import qualified Tests.Writers.Jira',
        'import qualified Tests.Writers.LaTeX',
        'import qualified Tests.Writers.Markdown',
        'import qualified Tests.Writers.Ms',
        'import qualified Tests.Writers.Muse',
        'import qualified Tests.Writers.Native',
        'import qualified Tests.Writers.Org',
        'import qualified Tests.Writers.Plain',
        'import qualified Tests.Writers.Powerpoint',
        'import qualified Tests.Writers.RST',
        'import qualified Tests.Writers.AnnotatedTable',
        'import qualified Tests.Writers.TEI',
        'import qualified Tests.Writers.Markua',
        'import qualified Tests.Writers.BBCode',
        'import qualified Tests.XML',
        'import qualified Tests.MediaBag',
        'import Text.Pandoc.Shared (inDirectory)',
        '',
        'tests :: FilePath -> TestTree',
        'tests pandocPath = testGroup "pandoc tests"',
        '        [ Tests.Command.tests',
        '        , testGroup "Old" (Tests.Old.tests pandocPath)',
        '        , testGroup "Shared" Tests.Shared.tests',
        '        , testGroup "MediaBag" Tests.MediaBag.tests',
        '        , testGroup "XML" Tests.XML.tests',
        '        , testGroup "Writers"',
        '          [ testGroup "Native" Tests.Writers.Native.tests',
        '          , testGroup "ConTeXt" Tests.Writers.ConTeXt.tests',
        '          , testGroup "LaTeX" Tests.Writers.LaTeX.tests',
        '          , testGroup "HTML" Tests.Writers.HTML.tests',
        '          , testGroup "JATS" Tests.Writers.JATS.tests',
        '          , testGroup "Jira" Tests.Writers.Jira.tests',
        '          , testGroup "Docbook" Tests.Writers.DocBook.tests',
        '          , testGroup "Markdown" Tests.Writers.Markdown.tests',
        '          , testGroup "Org" Tests.Writers.Org.tests',
        '          , testGroup "Plain" Tests.Writers.Plain.tests',
        '          , testGroup "AsciiDoc" Tests.Writers.AsciiDoc.tests',
        '          , testGroup "Docx" Tests.Writers.Docx.tests',
        '          , testGroup "RST" Tests.Writers.RST.tests',
        '          , testGroup "TEI" Tests.Writers.TEI.tests',
        '          , testGroup "markua" Tests.Writers.Markua.tests',
        '          , testGroup "Muse" Tests.Writers.Muse.tests',
        '          , testGroup "FB2" Tests.Writers.FB2.tests',
        '          , testGroup "PowerPoint" Tests.Writers.Powerpoint.tests',
        '          , testGroup "Ms" Tests.Writers.Ms.tests',
        '          , testGroup "AnnotatedTable" Tests.Writers.AnnotatedTable.tests',
        '          , testGroup "BBCode" Tests.Writers.BBCode.tests',
        '          ]',
        '        , testGroup "Readers"',
        '          [ testGroup "LaTeX" Tests.Readers.LaTeX.tests',
        '          , testGroup "Markdown" Tests.Readers.Markdown.tests',
        '          , testGroup "HTML" Tests.Readers.HTML.tests',
        '          , testGroup "JATS" Tests.Readers.JATS.tests',
        '          , testGroup "Jira" Tests.Readers.Jira.tests',
        '          , testGroup "Org" Tests.Readers.Org.tests',
        '          , testGroup "RST" Tests.Readers.RST.tests',
        '          , testGroup "RTF" Tests.Readers.RTF.tests',
        '          , testGroup "Docx" Tests.Readers.Docx.tests',
        '          , testGroup "Pptx" Tests.Readers.Pptx.tests',
        '          , testGroup "Xlsx" Tests.Readers.Xlsx.tests',
        '          , testGroup "ODT" Tests.Readers.ODT.tests',
        '          , testGroup "Txt2Tags" Tests.Readers.Txt2Tags.tests',
        '          , testGroup "EPUB" Tests.Readers.EPUB.tests',
        '          , testGroup "Muse" Tests.Readers.Muse.tests',
        '          , testGroup "Creole" Tests.Readers.Creole.tests',
        '          , testGroup "Man" Tests.Readers.Man.tests',
        '          , testGroup "Mdoc" Tests.Readers.Mdoc.tests',
        '          , testGroup "FB2" Tests.Readers.FB2.tests',
        '          , testGroup "DokuWiki" Tests.Readers.DokuWiki.tests',
        '          , testGroup "Pod" Tests.Readers.Pod.tests',
        '          ]',
        '        ]',
        '',
        'main :: IO ()',
        'main = do',
        '  setLocaleEncoding utf8',
        '  args <- getArgs',
        '  case args of',
        '    "--emulate":args\' -> -- emulate pandoc executable',
        '          E.catch',
        '            (do',
        '              res <- parseOptionsFromArgs options defaultOpts "pandoc" args\'',
        '              case res of',
        '                Left e -> handleOptInfo noEngine e',
        '                Right opts -> convertWithOpts noEngine opts)',
        '            (handleError . Left)',
        '    _ -> inDirectory "test" $ do',
        '           fp <- getExecutablePath',
        '           defaultMain $ tests fp',
    ]);
};

$luaEntryPoint = static function (): string {
    return implode("\n", [
        'module Main (main) where',
        'import Test.Tasty (TestTree, defaultMain, testGroup)',
        'import System.Directory (withCurrentDirectory)',
        'import qualified Tests.Lua',
        'import qualified Tests.Lua.Module',
        'import qualified Tests.Lua.Reader',
        'import qualified Tests.Lua.Writer',
        'main = withCurrentDirectory "test" $ defaultMain tests',
        'tests :: TestTree',
        'tests = testGroup "pandoc Lua engine" [ testGroup "Lua filters" Tests.Lua.tests, testGroup "Lua modules" Tests.Lua.Module.tests, testGroup "Custom writers" Tests.Lua.Writer.tests, testGroup "Custom readers" Tests.Lua.Reader.tests ]',
    ]);
};

$benchmarkEntryPoint = static function (): string {
    return implode("\n", [
        '{-# LANGUAGE OverloadedStrings #-}',
        'import Text.Pandoc',
        'import Text.Pandoc.MIME',
        'import Control.DeepSeq (force)',
        'import Control.Monad.Except (throwError)',
        'import qualified Text.Pandoc.UTF8 as UTF8',
        'import qualified Data.ByteString as B',
        'import qualified Data.Text as T',
        'import Test.Tasty.Bench',
        'import qualified Data.ByteString.Lazy as BL',
        'import Data.Maybe (mapMaybe)',
        'import Data.List (sortOn)',
        'import Text.Pandoc.Format (FlavoredFormat(..))',
        'readerBench :: Pandoc -> T.Text -> Maybe Benchmark',
        'readerBench _ name',
        '  | name `elem` ["bibtex", "biblatex", "csljson"] = Nothing',
        'readerBench doc name = either (const Nothing) Just $',
        '  runPure $ do',
        '    (rdr, rexts) <- getReader $ FlavoredFormat name mempty',
        '    (wtr, wexts) <- getWriter $ FlavoredFormat name mempty',
        '    tmpl <- Just <$> compileDefaultTemplate name',
        '    case (rdr, wtr) of',
        '      (TextReader r, TextWriter w) -> return $ bench (T.unpack name) $ nf (either (error . show) id . runPure . r def) mempty',
        '      (ByteStringReader r, ByteStringWriter w) -> return $ bench (T.unpack name) $ nf (either (error . show) id . runPure . r def{readerExtensions = rexts}) mempty',
        '      _ -> throwError $ PandocSomeError $ "text/bytestring format mismatch: " <> name',
        'getImages :: IO [(FilePath, MimeType, BL.ByteString)]',
        'getImages = do',
        '  ll <- B.readFile "test/lalune.jpg"',
        '  mv <- B.readFile "test/movie.jpg"',
        '  return [("lalune.jpg", "image/jpg", BL.fromStrict ll), ("movie.jpg", "image/jpg", BL.fromStrict mv)]',
        'writerBench :: [(FilePath, MimeType, BL.ByteString)] -> Pandoc -> T.Text -> Maybe Benchmark',
        'writerBench imgs doc name = either (const Nothing) Just $',
        '  runPure $ do',
        '    (wtr, wexts) <- getWriter $ FlavoredFormat name mempty',
        '    case wtr of',
        '      TextWriter writerFun -> return $ bench (T.unpack name) $ nf (\\d -> either (error . show) id $ runPure $ do mapM_ (\\(fp,mt,bs) -> insertMedia fp (Just mt) bs) imgs; writerFun def{ writerExtensions = wexts} d) doc',
        '      ByteStringWriter writerFun -> return $ bench (T.unpack name) $ nf (\\d -> either (error . show) id $ runPure $ do mapM_ (\\(fp,mt,bs) -> insertMedia fp (Just mt) bs) imgs; writerFun def{ writerExtensions = wexts} d) doc',
        'main :: IO ()',
        'main = do',
        '  inp <- UTF8.toText <$> B.readFile "test/testsuite.txt"',
        '  let opts = def',
        '  let doc = either (error . show) force $ runPure $ readMarkdown opts inp',
        '  defaultMain',
        '    [ env getImages $ \\imgs ->',
        '      bgroup "writers" $ mapMaybe (writerBench imgs doc . fst) (sortOn fst writers :: [(T.Text, Writer PandocPure)])',
        '    , bgroup "readers" $ mapMaybe (readerBench doc . fst) (sortOn fst readers :: [(T.Text, Reader PandocPure)])',
        '    ]',
    ]);
};

$runnerArtifacts = static function (): array {
    $files = [];
    foreach (UpstreamRunnerDependencyAudit::expectedRunnerArtifacts() as $relativePath => $kind) {
        if ($kind === 'directory') {
            $files[$relativePath . '/.audit-keep'] = 'fixture root present';
        } else {
            $files[$relativePath] = 'fixture artifact present';
        }
    }

    return $files;
};

$benchmarkArtifacts = static function () use ($benchmarkEntryPoint): array {
    $files = [];
    foreach (UpstreamRunnerDependencyAudit::expectedBenchmarkArtifacts() as $relativePath => $kind) {
        if ($kind === 'directory') {
            $files[$relativePath . '/.audit-keep'] = 'fixture root present';
        } elseif ($relativePath === 'benchmark/benchmark-pandoc.hs') {
            $files[$relativePath] = $benchmarkEntryPoint();
        } else {
            $files[$relativePath] = 'benchmark fixture artifact present';
        }
    }

    return $files;
};

$requiredFiles = static function (string $project, ?string $pandocPackage = null, ?string $luaPackage = null, bool $includeRunnerArtifacts = true) use ($pandocCabal, $luaCabal, $runnerArtifacts, $benchmarkArtifacts, $testPandocEntryPoint, $luaEntryPoint): array {
    $files = [
        'cabal.project' => $project,
        'pandoc.cabal' => $pandocPackage ?? $pandocCabal(),
        'pandoc-lua-engine/pandoc-lua-engine.cabal' => $luaPackage ?? $luaCabal(),
        'test/test-pandoc.hs' => $testPandocEntryPoint(),
        'pandoc-lua-engine/test/test-pandoc-lua-engine.hs' => $luaEntryPoint(),
    ];

    if ($includeRunnerArtifacts) {
        $files = array_merge($files, $runnerArtifacts(), $benchmarkArtifacts());
    }

    return $files;
};

return [
    'reports missing checkout files and cabal tools without invoking runners' => static function (TestRunner $t) use ($makeTree, $removeTree): void {
        $root = $makeTree([]);
        try {
            $audit = UpstreamRunnerDependencyAudit::auditCheckout($root, [
                'ghc' => ['available' => true, 'version' => '9.10.3'],
                'cabal' => ['available' => false, 'version' => null],
            ]);
        } finally {
            $removeTree($root);
        }

        $t->same(false, $audit['readyForNonMutatingCabalPlan']);
        $t->same([
            'cabal.project',
            'pandoc.cabal',
            'pandoc-lua-engine/pandoc-lua-engine.cabal',
            'test/test-pandoc.hs',
            'pandoc-lua-engine/test/test-pandoc-lua-engine.hs',
        ], $audit['missingFiles']);
        $t->same([], $audit['requiredFileProvenance']['present']);
        $t->same($audit['missingFiles'], $audit['requiredFileProvenance']['missing']);
        $t->same(['cabal'], $audit['missingTools']);
        $t->same([], $audit['projectSourceRepositoryPins']['present']);
        $t->same([
            'doclayout',
            'typst-symbols',
            'typst-hs',
            'texmath',
            'citeproc',
        ], $audit['projectSourceRepositoryPins']['missing']);
        $t->same(UpstreamRunnerDependencyAudit::expectedProjectPackages(), $audit['projectPackageClosure']['missingPackages']);
        $t->same(['embed_data_files', 'http'], $audit['projectPackageClosure']['missingFlags']['pandoc']);
        $t->same([
            'auto-update',
            'crypton',
            'skylighting-format-blaze-html',
            'skylighting-format-context',
        ], $audit['projectConstraintClosure']['missingConstraints']);
        $t->same(['test:test-pandoc', 'test:test-pandoc-lua-engine'], $audit['runnerDependencyClosure']['missingTargets']);
        $t->same(array_keys(UpstreamRunnerDependencyAudit::expectedRunnerArtifacts()), $audit['runnerArtifactClosure']['missing']);
        $t->same([], $audit['runnerArtifactClosure']['present']);
        $t->same([], $audit['runnerArtifactClosure']['wrongType']);
        $t->same([], $audit['nonMutatingPlan']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('missing required upstream runner files', $blocked);
        $t->contains('missing required Cabal toolchain commands: cabal', $blocked);
        $t->contains('missing cabal.project package entries', $blocked);
        $t->contains('missing cabal.project solver constraints', $blocked);
        $t->contains('missing Cabal runner test-suite stanzas', $blocked);
        $t->contains('missing upstream runner source/golden fixture artifacts', $blocked);
        $t->contains(UpstreamRunnerDependencyAudit::UPSTREAM_COMMIT, $audit['activationGate']);
    },
    'accepts hydrated cabal runner closure with exact project source pins' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $root = $makeTree($requiredFiles($pinnedProject()));
        try {
            $audit = UpstreamRunnerDependencyAudit::auditCheckout($root, [
                'ghc' => ['available' => true, 'version' => '9.10.3'],
                'cabal' => ['available' => true, 'version' => '3.12.1.0'],
            ]);
        } finally {
            $removeTree($root);
        }

        $t->same(true, $audit['readyForNonMutatingCabalPlan']);
        $t->same([], $audit['missingFiles']);
        $t->same([], $audit['missingTools']);
        $t->same([], $audit['projectSourceRepositoryPins']['missing']);
        $t->same([], $audit['projectSourceRepositoryPins']['mismatched']);
        $expectedPins = UpstreamRunnerDependencyAudit::expectedProjectPins();
        ksort($expectedPins);
        $t->same($expectedPins, $audit['projectSourceRepositoryPins']['present']);
        $t->same(UpstreamRunnerDependencyAudit::expectedProjectPackages(), $audit['projectPackageClosure']['presentPackages']);
        $t->same([], $audit['projectPackageClosure']['missingPackages']);
        $t->same([], $audit['projectPackageClosure']['missingFlags']);
        $t->same([], $audit['projectPackageClosure']['mismatchedFlags']);
        $t->same(UpstreamRunnerDependencyAudit::expectedProjectConstraints(), $audit['projectConstraintClosure']['presentConstraints']);
        $t->same([], $audit['projectConstraintClosure']['missingConstraints']);
        $t->same([], $audit['projectConstraintClosure']['mismatchedConstraints']);
        $t->same(UpstreamRunnerDependencyAudit::expectedCompilerGhcVersions(), $audit['compilerTestedWithClosure']['presentGhcVersions']);
        $t->same([], $audit['compilerTestedWithClosure']['missingGhcVersions']);
        $t->same('9.10.3', $audit['compilerTestedWithClosure']['toolGhcVersion']);
        $t->same(true, $audit['compilerTestedWithClosure']['toolGhcVersionSupported']);
        $t->same(['test:test-pandoc', 'test:test-pandoc-lua-engine'], $audit['runnerTargets']);
        $t->same('pandoc.cabal', $audit['runnerEntryPoints']['test:test-pandoc']['packageFile']);
        $t->same('exitcode-stdio-1.0', $audit['runnerEntryPoints']['test:test-pandoc']['type']);
        $t->same('pandoc-lua-engine/pandoc-lua-engine.cabal', $audit['runnerEntryPoints']['test:test-pandoc-lua-engine']['packageFile']);
        $t->same('exitcode-stdio-1.0', $audit['runnerEntryPoints']['test:test-pandoc-lua-engine']['type']);
        $t->same([], $audit['runnerDependencyClosure']['missingTargets']);
        $t->same([], $audit['runnerDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['runnerDependencyClosure']['missingDependencies']);
        $t->same([], $audit['runnerDependencyClosure']['mismatchedDependencyConstraints']);
        $t->same([], $audit['runnerDependencyClosure']['missingExecutableOptions']);
        $t->same([], $audit['runnerDependencyClosure']['mismatchedDefaultLanguages']);
        $t->same([], $audit['runnerDependencyClosure']['missingOtherModules']);
        $t->same(UpstreamRunnerDependencyAudit::expectedLuaEngineLibraryDependencies(), $audit['luaEngineLibraryClosure']['expectedDependencies']);
        $t->same([], $audit['luaEngineLibraryClosure']['missingDependencies']);
        $t->same(true, in_array('hslua-module-zip', $audit['luaEngineLibraryClosure']['presentDependencies'], true));
        $t->same(true, in_array('pandoc-lua-marshal', $audit['luaEngineLibraryClosure']['presentDependencies'], true));
        $t->same('exitcode-stdio-1.0', $audit['runnerDependencyClosure']['present']['test:test-pandoc']['type']);
        $t->same('exitcode-stdio-1.0', $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['type']);
        $t->same(['test'], $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['sourceDirectories']);
        $t->same('Haskell2010', $audit['runnerDependencyClosure']['present']['test:test-pandoc']['defaultLanguage']);
        $t->same('Haskell2010', $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['defaultLanguage']);
        $t->same(true, in_array('base', $audit['runnerDependencyClosure']['present']['test:test-pandoc']['buildDepends'], true));
        $t->same(true, in_array('zip-archive', $audit['runnerDependencyClosure']['present']['test:test-pandoc']['buildDepends'], true));
        $t->same(true, in_array('tasty-lua', $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['buildDepends'], true));
        $t->same('>= 4.18 && < 5', $audit['runnerDependencyClosure']['present']['test:test-pandoc']['dependencyConstraints']['base']);
        $t->same('>= 1.23.1 && < 1.24', $audit['runnerDependencyClosure']['present']['test:test-pandoc']['dependencyConstraints']['pandoc-types']);
        $t->same('>= 0.4.3 && < 0.5', $audit['runnerDependencyClosure']['present']['test:test-pandoc']['dependencyConstraints']['zip-archive']);
        $t->same('>= 2.5 && < 2.6', $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['dependencyConstraints']['hslua']);
        $t->same('>= 1.1 && < 1.2', $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['dependencyConstraints']['tasty-lua']);
        $t->same(true, in_array('Tests.Command', $audit['runnerDependencyClosure']['present']['test:test-pandoc']['otherModules'], true));
        $t->same(true, in_array('Tests.Writers.Native', $audit['runnerDependencyClosure']['present']['test:test-pandoc']['otherModules'], true));
        $t->same(true, in_array('Tests.Lua.Reader', $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['otherModules'], true));
        $t->same(UpstreamRunnerDependencyAudit::expectedRunnerExecutableOptions()['test:test-pandoc'], $audit['runnerDependencyClosure']['present']['test:test-pandoc']['ghcOptions']);
        $t->same([], $audit['runnerEntrySourceClosure']['missingTargets']);
        $t->same([], $audit['runnerEntrySourceClosure']['missingSemantics']);
        $t->same(
            array_keys(UpstreamRunnerDependencyAudit::expectedRunnerEntrySourceSemantics()['test:test-pandoc']['requiredSnippets']),
            $audit['runnerEntrySourceClosure']['present']['test:test-pandoc']['matchedSnippets']
        );
        $t->same(
            array_keys(UpstreamRunnerDependencyAudit::expectedRunnerEntrySourceSemantics()['test:test-pandoc-lua-engine']['requiredSnippets']),
            $audit['runnerEntrySourceClosure']['present']['test:test-pandoc-lua-engine']['matchedSnippets']
        );
        $t->same(array_keys(UpstreamRunnerDependencyAudit::expectedRunnerArtifacts()), $audit['runnerArtifactClosure']['present']);
        $t->same([], $audit['runnerArtifactClosure']['missing']);
        $t->same([], $audit['runnerArtifactClosure']['wrongType']);
        $t->same([], $audit['benchmarkEntrySourceClosure']['missingTargets']);
        $t->same([], $audit['benchmarkEntrySourceClosure']['missingSemantics']);
        $t->same(
            array_keys(UpstreamRunnerDependencyAudit::expectedBenchmarkEntrySourceSemantics()['benchmark:benchmark-pandoc']['requiredSnippets']),
            $audit['benchmarkEntrySourceClosure']['present']['benchmark:benchmark-pandoc']['matchedSnippets']
        );
        $t->contains('non-mutating solver/build plan', $audit['activationGate']);
        $t->contains('record pandoc.cabal tested-with GHC matrix', $audit['nonMutatingPlan'][0]);
        $t->contains('cabal.project package/flag closure', $audit['nonMutatingPlan'][0]);
        $t->contains('runner entry-point semantics', $audit['nonMutatingPlan'][0]);
        $t->contains('solver constraints and runner executable options', $audit['nonMutatingPlan'][1]);
        $t->contains('test-suite type, buildable state, default-language, entry point, direct build-depends with pinned version constraints, no unexpected Cabal mixins or build-tool dependencies, and other-modules closure', $audit['nonMutatingPlan'][2]);
        $t->contains('pandoc-lua-engine library HsLua module dependency closure', $audit['nonMutatingPlan'][2]);
        $t->contains('entry-source semantics before any benchmark execution', $audit['nonMutatingPlan'][3]);
    },
    'records required runner file provenance before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $root = $makeTree($files);
        try {
            $audit = UpstreamRunnerDependencyAudit::auditCheckout($root, [
                'ghc' => ['available' => true, 'version' => '9.10.3'],
                'cabal' => ['available' => true, 'version' => '3.12.1.0'],
            ]);
        } finally {
            $removeTree($root);
        }

        $t->same(true, $audit['readyForNonMutatingCabalPlan']);
        $t->same([
            'cabal.project',
            'pandoc.cabal',
            'pandoc-lua-engine/pandoc-lua-engine.cabal',
            'test/test-pandoc.hs',
            'pandoc-lua-engine/test/test-pandoc-lua-engine.hs',
        ], $audit['requiredFileProvenance']['expected']);
        $t->same([], $audit['requiredFileProvenance']['missing']);
        foreach ($audit['requiredFileProvenance']['expected'] as $relativePath) {
            $t->same(hash('sha256', $files[$relativePath]), $audit['requiredFileProvenance']['present'][$relativePath]['sha256']);
            $t->same(strlen($files[$relativePath]), $audit['requiredFileProvenance']['present'][$relativePath]['bytes']);
        }
        $t->contains('package-file hashes', $audit['nonMutatingPlan'][0]);
    },
    'flags missing and mismatched cabal project git pins' => static function (TestRunner $t) use ($makeTree, $removeTree, $requiredFiles): void {
        $project = implode("\n", [
            'packages: . pandoc-lua-engine',
            '',
            'package pandoc',
            '  flags: +embed_data_files -http',
            '',
            'source-repository-package',
            '  type: git',
            '  location: https://github.com/jgm/doclayout.git',
            '  tag: wrong-doclayout-tag',
            '',
            'source-repository-package',
            '  type: git',
            '  location: https://github.com/jgm/texmath.git',
            '  tag: 0a3fbebc5d0e21769f01b048eb63e1451ccf0e1a',
        ]);
        $root = $makeTree($requiredFiles($project));
        try {
            $audit = UpstreamRunnerDependencyAudit::auditCheckout($root, [
                'ghc' => '9.10.3',
                'cabal' => '3.12.1.0',
            ]);
        } finally {
            $removeTree($root);
        }

        $t->same(false, $audit['readyForNonMutatingCabalPlan']);
        $t->same([], $audit['missingFiles']);
        $t->same([], $audit['missingTools']);
        $t->same([
            'typst-symbols',
            'typst-hs',
            'citeproc',
        ], $audit['projectSourceRepositoryPins']['missing']);
        $t->same([
            'expected' => 'ef7f18308a61787244a80885d907fcd2c16604d4',
            'actual' => 'wrong-doclayout-tag',
        ], $audit['projectSourceRepositoryPins']['mismatched']['doclayout']);
        $t->same(['pandoc-server', 'pandoc-cli'], $audit['projectPackageClosure']['missingPackages']);
        $t->same([
            'expected' => true,
            'actual' => false,
        ], $audit['projectPackageClosure']['mismatchedFlags']['pandoc']['http']);
        $t->same([
            'auto-update',
            'crypton',
            'skylighting-format-blaze-html',
            'skylighting-format-context',
        ], $audit['projectConstraintClosure']['missingConstraints']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('missing cabal.project source-repository pins', $blocked);
        $t->contains('mismatched cabal.project source-repository pins: doclayout', $blocked);
        $t->contains('missing cabal.project package entries: pandoc-server, pandoc-cli', $blocked);
        $t->contains('mismatched cabal.project package flags: pandoc:http expected +, found -', $blocked);
        $t->contains('missing cabal.project solver constraints', $blocked);
    },
    'blocks source repository package type or location drift with matching tags' => static function (TestRunner $t) use ($makeTree, $removeTree, $requiredFiles, $pandocCabal, $luaCabal): void {
        $project = implode("\n", [
            'packages: . pandoc-lua-engine pandoc-server pandoc-cli',
            'constraints: skylighting-format-blaze-html >= 0.1.2, skylighting-format-context >= 0.1.0.2, auto-update >= 0.2.6, crypton >= 1.1.1',
            '',
            'package pandoc',
            '  flags: +embed_data_files +http',
            '',
            'source-repository-package',
            '  type: svn',
            '  location: https://github.com/jgm/doclayout.git',
            '  tag: ef7f18308a61787244a80885d907fcd2c16604d4',
            '',
            'source-repository-package',
            '  type: git',
            '  location: https://mirror.example.invalid/jgm/typst-symbols.git',
            '  tag: 6e97668c9f2ffea09f3187c34b7641038370fd21',
            '',
            'source-repository-package',
            '  type: git',
            '  location: https://github.com/jgm/typst-hs.git',
            '  tag: 19e835d40663a92df5bed4e8a0fca5465cacdd6b',
            '',
            'source-repository-package',
            '  type: git',
            '  location: https://github.com/jgm/texmath.git',
            '  tag: 0a3fbebc5d0e21769f01b048eb63e1451ccf0e1a',
            '',
            'source-repository-package',
            '  type: git',
            '  location: https://github.com/jgm/citeproc.git',
            '  tag: 1b684f1e06fc1093d20c1a2d474f4c3fdf2f65bd',
        ]);
        $root = $makeTree($requiredFiles(
            $project,
            $pandocCabal(),
            $luaCabal()
        ));
        try {
            $audit = UpstreamRunnerDependencyAudit::auditCheckout($root, [
                'ghc' => '9.10.3',
                'cabal' => '3.12.1.0',
            ]);
        } finally {
            $removeTree($root);
        }

        $t->same(false, $audit['readyForNonMutatingCabalPlan']);
        $t->same([], $audit['projectSourceRepositoryPins']['missing']);
        $t->same([], $audit['projectSourceRepositoryPins']['mismatched']);
        $t->same([], $audit['projectPackageClosure']['missingPackages']);
        $t->same([], $audit['projectConstraintClosure']['missingConstraints']);
        $t->same([], $audit['runnerDependencyClosure']['missingTargets']);
        $t->same([], $audit['runnerDependencyClosure']['missingDependencies']);
        $t->same([], $audit['runnerDependencyClosure']['missingExecutableOptions']);
        $t->same([
            'expected' => [
                'type' => 'git',
                'location' => 'https://github.com/jgm/doclayout.git',
            ],
            'actual' => [
                'type' => 'svn',
                'location' => 'https://github.com/jgm/doclayout.git',
            ],
        ], $audit['projectSourceRepositoryClosure']['mismatched']['doclayout']);
        $t->same([
            'expected' => [
                'type' => 'git',
                'location' => 'https://github.com/jgm/typst-symbols.git',
            ],
            'actual' => [
                'type' => 'git',
                'location' => 'https://mirror.example.invalid/jgm/typst-symbols.git',
            ],
        ], $audit['projectSourceRepositoryClosure']['mismatched']['typst-symbols']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('mismatched cabal.project source-repository package locations/types: doclayout, typst-symbols', $blocked);
        $t->contains('exact cabal.project source-repository Git types and locations', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks stale tested-with ghc matrix and unsupported local ghc before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['pandoc.cabal'] = str_replace(
            'tested-with: GHC == 9.6.7, GHC == 9.8.4, GHC == 9.10.3, GHC == 9.12.2',
            "tested-with: GHC == 9.6.7,\n  GHC == 9.8.4,\n  GHC == 9.12.2",
            $files['pandoc.cabal']
        );

        $root = $makeTree($files);
        try {
            $audit = UpstreamRunnerDependencyAudit::auditCheckout($root, [
                'ghc' => ['available' => true, 'version' => 'The Glorious Glasgow Haskell Compilation System, version 9.14.1'],
                'cabal' => ['available' => true, 'version' => '3.12.1.0'],
            ]);
        } finally {
            $removeTree($root);
        }

        $t->same(false, $audit['readyForNonMutatingCabalPlan']);
        $t->same([], $audit['missingFiles']);
        $t->same([], $audit['missingTools']);
        $t->same([
            '9.6.7',
            '9.8.4',
            '9.12.2',
        ], $audit['compilerTestedWithClosure']['presentGhcVersions']);
        $t->same(['9.10.3'], $audit['compilerTestedWithClosure']['missingGhcVersions']);
        $t->same('9.14.1', $audit['compilerTestedWithClosure']['toolGhcVersion']);
        $t->same(false, $audit['compilerTestedWithClosure']['toolGhcVersionSupported']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('missing pandoc.cabal tested-with GHC versions: 9.10.3', $blocked);
        $t->contains('unsupported or unrecorded ghc version for Pandoc tested-with matrix: 9.14.1', $blocked);
        $t->contains('pandoc.cabal tested-with GHC matrix', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'rejects hydrated checkout with incomplete runner package closure' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles, $pandocCabal, $luaCabal): void {
        $root = $makeTree($requiredFiles(
            $pinnedProject(),
            $pandocCabal(['zip-archive', 'tasty-quickcheck'], 'wrong-main.hs', 'other-test'),
            $luaCabal(['tasty-lua', 'hslua'])
        ));
        try {
            $audit = UpstreamRunnerDependencyAudit::auditCheckout($root, [
                'ghc' => '9.10.3',
                'cabal' => '3.12.1.0',
            ]);
        } finally {
            $removeTree($root);
        }

        $t->same(false, $audit['readyForNonMutatingCabalPlan']);
        $t->same([], $audit['missingFiles']);
        $t->same([], $audit['missingTools']);
        $t->same([], $audit['projectSourceRepositoryPins']['missing']);
        $t->same([], $audit['projectPackageClosure']['missingPackages']);
        $t->same([], $audit['projectPackageClosure']['missingFlags']);
        $t->same([], $audit['runnerDependencyClosure']['missingTargets']);
        $t->contains('main-is expected test-pandoc.hs, found wrong-main.hs', $audit['runnerDependencyClosure']['mismatchedEntryPoints']['test:test-pandoc'][0]);
        $t->contains('hs-source-dirs missing test', $audit['runnerDependencyClosure']['mismatchedEntryPoints']['test:test-pandoc'][1]);
        $t->same(['tasty-quickcheck', 'zip-archive'], $audit['runnerDependencyClosure']['missingDependencies']['test:test-pandoc']);
        $t->same(['hslua', 'tasty-lua'], $audit['runnerDependencyClosure']['missingDependencies']['test:test-pandoc-lua-engine']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('mismatched Cabal runner entry points', $blocked);
        $t->contains('missing Cabal runner direct build-depends', $blocked);
    },
    'blocks stale runner direct dependency constraints before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['pandoc.cabal'] = str_replace(
            'pandoc-types >= 1.23.1 && < 1.24',
            'pandoc-types >= 1.22 && < 1.24',
            $files['pandoc.cabal']
        );
        $files['pandoc.cabal'] = str_replace(
            'zip-archive >= 0.4.3 && < 0.5',
            'zip-archive',
            $files['pandoc.cabal']
        );
        $files['pandoc-lua-engine/pandoc-lua-engine.cabal'] = str_replace(
            'hslua >= 2.5 && < 2.6',
            'hslua >= 2.4 && < 2.6',
            $files['pandoc-lua-engine/pandoc-lua-engine.cabal']
        );
        $files['pandoc-lua-engine/pandoc-lua-engine.cabal'] = str_replace(
            'tasty-lua >= 1.1 && < 1.2',
            'tasty-lua',
            $files['pandoc-lua-engine/pandoc-lua-engine.cabal']
        );

        $root = $makeTree($files);
        try {
            $audit = UpstreamRunnerDependencyAudit::auditCheckout($root, [
                'ghc' => '9.10.3',
                'cabal' => '3.12.1.0',
            ]);
        } finally {
            $removeTree($root);
        }

        $t->same(false, $audit['readyForNonMutatingCabalPlan']);
        $t->same([], $audit['missingFiles']);
        $t->same([], $audit['missingTools']);
        $t->same([], $audit['projectSourceRepositoryPins']['missing']);
        $t->same([], $audit['projectPackageClosure']['missingPackages']);
        $t->same([], $audit['projectConstraintClosure']['missingConstraints']);
        $t->same([], $audit['runnerDependencyClosure']['missingTargets']);
        $t->same([], $audit['runnerDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['runnerDependencyClosure']['missingDependencies']);
        $t->same([
            'expected' => '>= 1.23.1 && < 1.24',
            'actual' => '>= 1.22 && < 1.24',
        ], $audit['runnerDependencyClosure']['mismatchedDependencyConstraints']['test:test-pandoc']['pandoc-types']);
        $t->same([
            'expected' => '>= 0.4.3 && < 0.5',
            'actual' => '',
        ], $audit['runnerDependencyClosure']['mismatchedDependencyConstraints']['test:test-pandoc']['zip-archive']);
        $t->same([
            'expected' => '>= 2.5 && < 2.6',
            'actual' => '>= 2.4 && < 2.6',
        ], $audit['runnerDependencyClosure']['mismatchedDependencyConstraints']['test:test-pandoc-lua-engine']['hslua']);
        $t->same([
            'expected' => '>= 1.1 && < 1.2',
            'actual' => '',
        ], $audit['runnerDependencyClosure']['mismatchedDependencyConstraints']['test:test-pandoc-lua-engine']['tasty-lua']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('mismatched Cabal runner direct build-depends constraints', $blocked);
        $t->contains('test:test-pandoc (pandoc-types expected >= 1.23.1 && < 1.24, found >= 1.22 && < 1.24, zip-archive expected >= 0.4.3 && < 0.5, found none)', $blocked);
        $t->contains('test:test-pandoc-lua-engine (hslua expected >= 2.5 && < 2.6, found >= 2.4 && < 2.6, tasty-lua expected >= 1.1 && < 1.2, found none)', $blocked);
        $t->contains('direct runner build-depends', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks stale repo relative lua runner source directory before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles, $luaCabal): void {
        $root = $makeTree($requiredFiles(
            $pinnedProject(),
            null,
            $luaCabal([], null, 'pandoc-lua-engine/test')
        ));
        try {
            $audit = UpstreamRunnerDependencyAudit::auditCheckout($root, [
                'ghc' => '9.10.3',
                'cabal' => '3.12.1.0',
            ]);
        } finally {
            $removeTree($root);
        }

        $t->same(false, $audit['readyForNonMutatingCabalPlan']);
        $t->same([], $audit['missingFiles']);
        $t->same([], $audit['missingTools']);
        $t->same([], $audit['projectSourceRepositoryPins']['missing']);
        $t->same([], $audit['projectSourceRepositoryPins']['mismatched']);
        $t->same([], $audit['projectPackageClosure']['missingPackages']);
        $t->same([], $audit['projectConstraintClosure']['missingConstraints']);
        $t->same([], $audit['runnerDependencyClosure']['missingTargets']);
        $t->same([], $audit['runnerDependencyClosure']['missingDependencies']);
        $t->same([], $audit['runnerDependencyClosure']['missingExecutableOptions']);
        $t->same(['pandoc-lua-engine/test'], $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['sourceDirectories']);
        $t->contains('hs-source-dirs missing test', $audit['runnerDependencyClosure']['mismatchedEntryPoints']['test:test-pandoc-lua-engine'][0]);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('mismatched Cabal runner entry points: test:test-pandoc-lua-engine (hs-source-dirs missing test)', $blocked);
        $t->contains('test entry points', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks stale solver constraints and stripped runner executable options' => static function (TestRunner $t) use ($makeTree, $removeTree, $requiredFiles, $pandocCabal, $luaCabal): void {
        $project = implode("\n", [
            'packages: .',
            '  pandoc-lua-engine pandoc-server pandoc-cli',
            'constraints:',
            '  skylighting-format-blaze-html >= 0.1.1,',
            '  auto-update >= 0.2.6,',
            '',
            'package pandoc',
            '  flags: +embed_data_files +http',
            '',
            'source-repository-package',
            '  type: git',
            '  location: https://github.com/jgm/doclayout.git',
            '  tag: ef7f18308a61787244a80885d907fcd2c16604d4',
            '',
            'source-repository-package',
            '  type: git',
            '  location: https://github.com/jgm/typst-symbols.git',
            '  tag: 6e97668c9f2ffea09f3187c34b7641038370fd21',
            '',
            'source-repository-package',
            '  type: git',
            '  location: https://github.com/jgm/typst-hs.git',
            '  tag: 19e835d40663a92df5bed4e8a0fca5465cacdd6b',
            '',
            'source-repository-package',
            '  type: git',
            '  location: https://github.com/jgm/texmath.git',
            '  tag: 0a3fbebc5d0e21769f01b048eb63e1451ccf0e1a',
            '',
            'source-repository-package',
            '  type: git',
            '  location: https://github.com/jgm/citeproc.git',
            '  tag: 1b684f1e06fc1093d20c1a2d474f4c3fdf2f65bd',
        ]);
        $root = $makeTree($requiredFiles(
            $project,
            $pandocCabal([], null, null, '-rtsopts'),
            $luaCabal()
        ));
        try {
            $audit = UpstreamRunnerDependencyAudit::auditCheckout($root, [
                'ghc' => '9.10.3',
                'cabal' => '3.12.1.0',
            ]);
        } finally {
            $removeTree($root);
        }

        $t->same(false, $audit['readyForNonMutatingCabalPlan']);
        $t->same([], $audit['missingFiles']);
        $t->same([], $audit['missingTools']);
        $t->same([], $audit['projectSourceRepositoryPins']['missing']);
        $t->same([], $audit['projectPackageClosure']['missingPackages']);
        $t->same([], $audit['projectPackageClosure']['missingFlags']);
        $t->same([
            'crypton',
            'skylighting-format-context',
        ], $audit['projectConstraintClosure']['missingConstraints']);
        $t->same([
            'expected' => '>= 0.1.2',
            'actual' => '>= 0.1.1',
        ], $audit['projectConstraintClosure']['mismatchedConstraints']['skylighting-format-blaze-html']);
        $t->same([
            '-with-rtsopts=-A8m',
            '-threaded',
        ], $audit['runnerDependencyClosure']['missingExecutableOptions']['test:test-pandoc']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('missing cabal.project solver constraints: crypton, skylighting-format-context', $blocked);
        $t->contains('mismatched cabal.project solver constraints: skylighting-format-blaze-html expected >= 0.1.2, found >= 0.1.1', $blocked);
        $t->contains('missing Cabal runner executable options: test:test-pandoc (-with-rtsopts=-A8m, -threaded)', $blocked);
        $t->contains('package entries/flags/constraints', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'rejects non executable cabal runner test-suite types' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles, $pandocCabal, $luaCabal): void {
        $root = $makeTree($requiredFiles(
            $pinnedProject(),
            $pandocCabal([], null, null, null, 'detailed-0.9'),
            $luaCabal([], null, null, 'detailed-0.9')
        ));
        try {
            $audit = UpstreamRunnerDependencyAudit::auditCheckout($root, [
                'ghc' => '9.10.3',
                'cabal' => '3.12.1.0',
            ]);
        } finally {
            $removeTree($root);
        }

        $t->same(false, $audit['readyForNonMutatingCabalPlan']);
        $t->same([], $audit['missingFiles']);
        $t->same([], $audit['missingTools']);
        $t->same([], $audit['projectSourceRepositoryPins']['missing']);
        $t->same([], $audit['projectPackageClosure']['missingPackages']);
        $t->same([], $audit['projectConstraintClosure']['missingConstraints']);
        $t->same([], $audit['runnerDependencyClosure']['missingTargets']);
        $t->contains('type expected exitcode-stdio-1.0, found detailed-0.9', $audit['runnerDependencyClosure']['mismatchedEntryPoints']['test:test-pandoc'][0]);
        $t->contains('type expected exitcode-stdio-1.0, found detailed-0.9', $audit['runnerDependencyClosure']['mismatchedEntryPoints']['test:test-pandoc-lua-engine'][0]);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('mismatched Cabal runner entry points: test:test-pandoc (type expected exitcode-stdio-1.0, found detailed-0.9)', $blocked);
        $t->contains('exitcode-stdio test-suite types', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'rejects non buildable cabal runner test-suites before solver planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles, $pandocCabal, $luaCabal): void {
        $root = $makeTree($requiredFiles(
            $pinnedProject(),
            $pandocCabal([], null, null, null, 'exitcode-stdio-1.0', 'False'),
            $luaCabal([], null, null, 'exitcode-stdio-1.0', 'False')
        ));
        try {
            $audit = UpstreamRunnerDependencyAudit::auditCheckout($root, [
                'ghc' => '9.10.3',
                'cabal' => '3.12.1.0',
            ]);
        } finally {
            $removeTree($root);
        }

        $t->same(false, $audit['readyForNonMutatingCabalPlan']);
        $t->same([], $audit['missingFiles']);
        $t->same([], $audit['missingTools']);
        $t->same([], $audit['projectSourceRepositoryPins']['missing']);
        $t->same([], $audit['projectSourceRepositoryPins']['mismatched']);
        $t->same([], $audit['projectPackageClosure']['missingPackages']);
        $t->same([], $audit['projectConstraintClosure']['missingConstraints']);
        $t->same([], $audit['runnerDependencyClosure']['missingTargets']);
        $t->same(false, $audit['runnerDependencyClosure']['present']['test:test-pandoc']['buildable']);
        $t->same(false, $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['buildable']);
        $t->contains('buildable expected true, found false', $audit['runnerDependencyClosure']['mismatchedEntryPoints']['test:test-pandoc'][0]);
        $t->contains('buildable expected true, found false', $audit['runnerDependencyClosure']['mismatchedEntryPoints']['test:test-pandoc-lua-engine'][0]);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('mismatched Cabal runner entry points: test:test-pandoc (buildable expected true, found false)', $blocked);
        $t->contains('buildable exitcode-stdio test-suite types', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'rejects hydrated runner package closure without source and golden fixture artifacts' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $root = $makeTree($requiredFiles($pinnedProject(), null, null, false));
        try {
            $audit = UpstreamRunnerDependencyAudit::auditCheckout($root, [
                'ghc' => '9.10.3',
                'cabal' => '3.12.1.0',
            ]);
        } finally {
            $removeTree($root);
        }

        $t->same(false, $audit['readyForNonMutatingCabalPlan']);
        $t->same([], $audit['missingFiles']);
        $t->same([], $audit['missingTools']);
        $t->same([], $audit['projectSourceRepositoryPins']['missing']);
        $t->same([], $audit['projectSourceRepositoryPins']['mismatched']);
        $t->same([], $audit['projectPackageClosure']['missingPackages']);
        $t->same([], $audit['projectConstraintClosure']['missingConstraints']);
        $t->same([], $audit['runnerDependencyClosure']['missingTargets']);
        $t->same([], $audit['runnerDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['runnerDependencyClosure']['missingDependencies']);
        $t->same([], $audit['runnerDependencyClosure']['missingExecutableOptions']);
        $expectedMissing = array_values(array_diff(
            array_keys(UpstreamRunnerDependencyAudit::expectedRunnerArtifacts()),
            ['pandoc-lua-engine/test']
        ));
        $t->same($expectedMissing, $audit['runnerArtifactClosure']['missing']);
        $t->same(['pandoc-lua-engine/test'], $audit['runnerArtifactClosure']['present']);
        $t->same([], $audit['runnerArtifactClosure']['wrongType']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('missing upstream runner source/golden fixture artifacts', $blocked);
        $t->contains('runner source/golden fixtures', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks lua runner other-module source artifact drift before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        unset($files['pandoc-lua-engine/test/Tests/Lua/Reader.hs']);

        $root = $makeTree($files);
        try {
            $audit = UpstreamRunnerDependencyAudit::auditCheckout($root, [
                'ghc' => '9.10.3',
                'cabal' => '3.12.1.0',
            ]);
        } finally {
            $removeTree($root);
        }

        $t->same(false, $audit['readyForNonMutatingCabalPlan']);
        $t->same([], $audit['missingFiles']);
        $t->same([], $audit['missingTools']);
        $t->same([], $audit['runnerDependencyClosure']['missingTargets']);
        $t->same([], $audit['runnerDependencyClosure']['missingOtherModules']);
        $t->same(['pandoc-lua-engine/test/Tests/Lua/Reader.hs'], $audit['runnerArtifactClosure']['missing']);
        $t->same([], $audit['runnerArtifactClosure']['wrongType']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('missing upstream runner source/golden fixture artifacts: pandoc-lua-engine/test/Tests/Lua/Reader.hs', $blocked);
        $t->contains('runner source/golden fixtures', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks main runner other-module source artifact drift before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        unset(
            $files['test/Tests/Readers/Docx.hs'],
            $files['test/Tests/Readers/Org/Inline/Citation.hs'],
            $files['test/Tests/Writers/BBCode.hs']
        );

        $root = $makeTree($files);
        try {
            $audit = UpstreamRunnerDependencyAudit::auditCheckout($root, [
                'ghc' => '9.10.3',
                'cabal' => '3.12.1.0',
            ]);
        } finally {
            $removeTree($root);
        }

        $t->same(false, $audit['readyForNonMutatingCabalPlan']);
        $t->same([], $audit['missingFiles']);
        $t->same([], $audit['missingTools']);
        $t->same([], $audit['runnerDependencyClosure']['missingTargets']);
        $t->same([], $audit['runnerDependencyClosure']['missingOtherModules']);
        $t->same([
            'test/Tests/Readers/Docx.hs',
            'test/Tests/Readers/Org/Inline/Citation.hs',
            'test/Tests/Writers/BBCode.hs',
        ], $audit['runnerArtifactClosure']['missing']);
        $t->same([], $audit['runnerArtifactClosure']['wrongType']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('missing upstream runner source/golden fixture artifacts: test/Tests/Readers/Docx.hs, test/Tests/Readers/Org/Inline/Citation.hs, test/Tests/Writers/BBCode.hs', $blocked);
        $t->contains('runner source/golden fixtures', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks runner entry point source semantic drift before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['test/test-pandoc.hs'] = implode("\n", [
            'module Main (main) where',
            'import Test.Tasty (defaultMain)',
            'main = defaultMain tests',
        ]);
        $files['pandoc-lua-engine/test/test-pandoc-lua-engine.hs'] = implode("\n", [
            'module Main (main) where',
            'import Test.Tasty (defaultMain)',
            'main = defaultMain tests',
        ]);

        $root = $makeTree($files);
        try {
            $audit = UpstreamRunnerDependencyAudit::auditCheckout($root, [
                'ghc' => '9.10.3',
                'cabal' => '3.12.1.0',
            ]);
        } finally {
            $removeTree($root);
        }

        $t->same(false, $audit['readyForNonMutatingCabalPlan']);
        $t->same([], $audit['missingFiles']);
        $t->same([], $audit['missingTools']);
        $t->same([], $audit['projectSourceRepositoryPins']['missing']);
        $t->same([], $audit['projectSourceRepositoryPins']['mismatched']);
        $t->same([], $audit['projectPackageClosure']['missingPackages']);
        $t->same([], $audit['projectConstraintClosure']['missingConstraints']);
        $t->same([], $audit['runnerDependencyClosure']['missingTargets']);
        $t->same([], $audit['runnerDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['runnerDependencyClosure']['missingDependencies']);
        $t->same([], $audit['runnerDependencyClosure']['missingExecutableOptions']);
        $t->same([], $audit['runnerEntrySourceClosure']['missingTargets']);
        $t->contains('sets locale encoding to utf8', implode("\n", $audit['runnerEntrySourceClosure']['missingSemantics']['test:test-pandoc']));
        $t->contains('offers --emulate command runner path', implode("\n", $audit['runnerEntrySourceClosure']['missingSemantics']['test:test-pandoc']));
        $t->contains('loads markdown writer tests', implode("\n", $audit['runnerEntrySourceClosure']['missingSemantics']['test:test-pandoc']));
        $t->contains('runs from lua engine test directory', implode("\n", $audit['runnerEntrySourceClosure']['missingSemantics']['test:test-pandoc-lua-engine']));
        $t->contains('names lua engine tasty group', implode("\n", $audit['runnerEntrySourceClosure']['missingSemantics']['test:test-pandoc-lua-engine']));
        $t->contains('loads custom reader tests', implode("\n", $audit['runnerEntrySourceClosure']['missingSemantics']['test:test-pandoc-lua-engine']));
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('missing runner entry point source semantics', $blocked);
        $t->contains('runner entry-point source semantics', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks omitted main runner tasty groups before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles, $testPandocEntryPoint): void {
        $files = $requiredFiles($pinnedProject());
        $entryPoint = $testPandocEntryPoint();
        foreach ([
            'Tests.Shared.tests',
            'Tests.MediaBag.tests',
            'Tests.XML.tests',
            'Tests.Readers.Docx.tests',
            'Tests.Readers.ODT.tests',
            'Tests.Readers.EPUB.tests',
            'Tests.Writers.Docx.tests',
            'Tests.Writers.RST.tests',
            'Tests.Writers.BBCode.tests',
        ] as $snippet) {
            $entryPoint = str_replace($snippet, 'omitted_' . str_replace(['.', ':'], '_', $snippet), $entryPoint);
        }
        $files['test/test-pandoc.hs'] = $entryPoint;

        $root = $makeTree($files);
        try {
            $audit = UpstreamRunnerDependencyAudit::auditCheckout($root, [
                'ghc' => '9.10.3',
                'cabal' => '3.12.1.0',
            ]);
        } finally {
            $removeTree($root);
        }

        $t->same(false, $audit['readyForNonMutatingCabalPlan']);
        $t->same([], $audit['missingFiles']);
        $t->same([], $audit['missingTools']);
        $t->same([], $audit['projectSourceRepositoryPins']['missing']);
        $t->same([], $audit['projectSourceRepositoryPins']['mismatched']);
        $t->same([], $audit['projectPackageClosure']['missingPackages']);
        $t->same([], $audit['projectConstraintClosure']['missingConstraints']);
        $t->same([], $audit['runnerDependencyClosure']['missingTargets']);
        $t->same([], $audit['runnerDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['runnerDependencyClosure']['missingDependencies']);
        $t->same([], $audit['runnerDependencyClosure']['missingOtherModules']);
        $t->same([], $audit['runnerEntrySourceClosure']['missingTargets']);
        $missing = implode("\n", $audit['runnerEntrySourceClosure']['missingSemantics']['test:test-pandoc']);
        $t->contains('loads shared helper tests', $missing);
        $t->contains('loads media bag tests', $missing);
        $t->contains('loads xml tests', $missing);
        $t->contains('loads docx reader tests', $missing);
        $t->contains('loads odt reader tests', $missing);
        $t->contains('loads epub reader tests', $missing);
        $t->contains('loads docx writer tests', $missing);
        $t->contains('loads rst writer tests', $missing);
        $t->contains('loads bbcode writer tests', $missing);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('missing runner entry point source semantics', $blocked);
        $t->contains('runner entry-point source semantics', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks stripped command emulation parser and extended tasty groups before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles, $testPandocEntryPoint): void {
        $files = $requiredFiles($pinnedProject());
        $entryPoint = $testPandocEntryPoint();
        foreach ([
            'E.catch',
            'parseOptionsFromArgs options defaultOpts "pandoc" args\'',
            'Left e -> handleOptInfo noEngine e',
            'Right opts -> convertWithOpts noEngine opts',
            '(handleError . Left)',
            'Tests.Readers.JATS.tests',
            'Tests.Readers.Jira.tests',
            'Tests.Readers.Org.tests',
            'Tests.Readers.RTF.tests',
            'Tests.Readers.Txt2Tags.tests',
            'Tests.Readers.Muse.tests',
            'Tests.Readers.Creole.tests',
            'Tests.Readers.Man.tests',
            'Tests.Readers.Mdoc.tests',
            'Tests.Readers.DokuWiki.tests',
            'Tests.Writers.ConTeXt.tests',
            'Tests.Writers.JATS.tests',
            'Tests.Writers.Jira.tests',
            'Tests.Writers.Org.tests',
            'Tests.Writers.Plain.tests',
            'Tests.Writers.Markua.tests',
            'Tests.Writers.Muse.tests',
            'Tests.Writers.FB2.tests',
            'Tests.Writers.Ms.tests',
        ] as $snippet) {
            $entryPoint = str_replace($snippet, 'omitted_' . preg_replace('/[^A-Za-z0-9_]+/', '_', $snippet), $entryPoint);
        }
        $files['test/test-pandoc.hs'] = $entryPoint;

        $root = $makeTree($files);
        try {
            $audit = UpstreamRunnerDependencyAudit::auditCheckout($root, [
                'ghc' => '9.10.3',
                'cabal' => '3.12.1.0',
            ]);
        } finally {
            $removeTree($root);
        }

        $t->same(false, $audit['readyForNonMutatingCabalPlan']);
        $t->same([], $audit['missingFiles']);
        $t->same([], $audit['missingTools']);
        $t->same([], $audit['runnerDependencyClosure']['missingTargets']);
        $t->same([], $audit['runnerDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['runnerDependencyClosure']['missingDependencies']);
        $t->same([], $audit['runnerDependencyClosure']['missingExecutableOptions']);
        $t->same([], $audit['runnerDependencyClosure']['missingOtherModules']);
        $t->same([], $audit['runnerEntrySourceClosure']['missingTargets']);
        $missing = implode("\n", $audit['runnerEntrySourceClosure']['missingSemantics']['test:test-pandoc']);
        $t->contains('catches command emulation exceptions', $missing);
        $t->contains('parses --emulate args with default pandoc options', $missing);
        $t->contains('handles command option info with noEngine', $missing);
        $t->contains('converts parsed command options with noEngine', $missing);
        $t->contains('handles emulation errors through pandoc error handler', $missing);
        $t->contains('loads jats reader tests', $missing);
        $t->contains('loads org reader tests', $missing);
        $t->contains('loads dokuwiki reader tests', $missing);
        $t->contains('loads context writer tests', $missing);
        $t->contains('loads plain writer tests', $missing);
        $t->contains('loads ms writer tests', $missing);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('missing runner entry point source semantics', $blocked);
        $t->contains('runner entry-point source semantics', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks runner other-modules drift before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['pandoc.cabal'] = str_replace(
            "    Tests.Command,\n",
            '',
            $files['pandoc.cabal']
        );
        $files['pandoc.cabal'] = str_replace(
            "    Tests.Writers.Native",
            '    Tests.Writers.HTML',
            $files['pandoc.cabal']
        );
        $files['pandoc-lua-engine/pandoc-lua-engine.cabal'] = str_replace(
            "    Tests.Lua.Reader,\n",
            '',
            $files['pandoc-lua-engine/pandoc-lua-engine.cabal']
        );

        $root = $makeTree($files);
        try {
            $audit = UpstreamRunnerDependencyAudit::auditCheckout($root, [
                'ghc' => '9.10.3',
                'cabal' => '3.12.1.0',
            ]);
        } finally {
            $removeTree($root);
        }

        $t->same(false, $audit['readyForNonMutatingCabalPlan']);
        $t->same([], $audit['missingFiles']);
        $t->same([], $audit['missingTools']);
        $t->same([], $audit['projectSourceRepositoryPins']['missing']);
        $t->same([], $audit['projectSourceRepositoryPins']['mismatched']);
        $t->same([], $audit['projectPackageClosure']['missingPackages']);
        $t->same([], $audit['projectConstraintClosure']['missingConstraints']);
        $t->same([], $audit['runnerDependencyClosure']['missingTargets']);
        $t->same([], $audit['runnerDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['runnerDependencyClosure']['missingDependencies']);
        $t->same([], $audit['runnerDependencyClosure']['missingExecutableOptions']);
        $t->same(['Tests.Command', 'Tests.Writers.Native'], $audit['runnerDependencyClosure']['missingOtherModules']['test:test-pandoc']);
        $t->same(['Tests.Lua.Reader'], $audit['runnerDependencyClosure']['missingOtherModules']['test:test-pandoc-lua-engine']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('missing Cabal runner other-modules', $blocked);
        $t->contains('runner other-modules closure', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks full pandoc runner reader and writer module closure drift before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['pandoc.cabal'] = str_replace(
            "    Tests.Readers.Docx,\n",
            '',
            $files['pandoc.cabal']
        );
        $files['pandoc.cabal'] = str_replace(
            "    Tests.Writers.BBCode",
            '    Tests.Writers.Native',
            $files['pandoc.cabal']
        );

        $root = $makeTree($files);
        try {
            $audit = UpstreamRunnerDependencyAudit::auditCheckout($root, [
                'ghc' => '9.10.3',
                'cabal' => '3.12.1.0',
            ]);
        } finally {
            $removeTree($root);
        }

        $t->same(false, $audit['readyForNonMutatingCabalPlan']);
        $t->same([], $audit['missingFiles']);
        $t->same([], $audit['missingTools']);
        $t->same([], $audit['projectSourceRepositoryPins']['missing']);
        $t->same([], $audit['projectSourceRepositoryPins']['mismatched']);
        $t->same([], $audit['projectPackageClosure']['missingPackages']);
        $t->same([], $audit['projectConstraintClosure']['missingConstraints']);
        $t->same([], $audit['runnerDependencyClosure']['missingTargets']);
        $t->same([], $audit['runnerDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['runnerDependencyClosure']['missingDependencies']);
        $t->same([], $audit['runnerDependencyClosure']['missingExecutableOptions']);
        $t->same([
            'Tests.Readers.Docx',
            'Tests.Writers.BBCode',
        ], $audit['runnerDependencyClosure']['missingOtherModules']['test:test-pandoc']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('missing Cabal runner other-modules: test:test-pandoc (Tests.Readers.Docx, Tests.Writers.BBCode)', $blocked);
        $t->contains('runner other-modules closure', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks lua engine library dependency drift before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles, $luaCabal): void {
        $root = $makeTree($requiredFiles(
            $pinnedProject(),
            null,
            $luaCabal([], null, null, 'exitcode-stdio-1.0', null, 'Haskell2010', [
                'hslua-module-doclayout',
                'hslua-module-zip',
                'pandoc-lua-marshal',
            ])
        ));
        try {
            $audit = UpstreamRunnerDependencyAudit::auditCheckout($root, [
                'ghc' => '9.10.3',
                'cabal' => '3.12.1.0',
            ]);
        } finally {
            $removeTree($root);
        }

        $t->same(false, $audit['readyForNonMutatingCabalPlan']);
        $t->same([], $audit['missingFiles']);
        $t->same([], $audit['missingTools']);
        $t->same([], $audit['projectSourceRepositoryPins']['missing']);
        $t->same([], $audit['projectSourceRepositoryPins']['mismatched']);
        $t->same([], $audit['projectPackageClosure']['missingPackages']);
        $t->same([], $audit['projectConstraintClosure']['missingConstraints']);
        $t->same([], $audit['runnerDependencyClosure']['missingTargets']);
        $t->same([], $audit['runnerDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['runnerDependencyClosure']['missingDependencies']);
        $t->same([], $audit['runnerDependencyClosure']['missingExecutableOptions']);
        $t->same([], $audit['runnerDependencyClosure']['mismatchedDefaultLanguages']);
        $t->same([], $audit['runnerDependencyClosure']['missingOtherModules']);
        $t->same([
            'hslua-module-doclayout',
            'hslua-module-zip',
            'pandoc-lua-marshal',
        ], $audit['luaEngineLibraryClosure']['missingDependencies']);
        $t->same(true, in_array('hslua-module-path', $audit['luaEngineLibraryClosure']['presentDependencies'], true));
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('missing pandoc-lua-engine library build-depends: hslua-module-doclayout, hslua-module-zip, pandoc-lua-marshal', $blocked);
        $t->contains('pandoc-lua-engine library HsLua module dependency closure', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks runner default-language drift before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles, $pandocCabal, $luaCabal): void {
        $root = $makeTree($requiredFiles(
            $pinnedProject(),
            $pandocCabal([], null, null, null, 'exitcode-stdio-1.0', null, 'Haskell98'),
            $luaCabal([], null, null, 'exitcode-stdio-1.0', null, '')
        ));
        try {
            $audit = UpstreamRunnerDependencyAudit::auditCheckout($root, [
                'ghc' => '9.10.3',
                'cabal' => '3.12.1.0',
            ]);
        } finally {
            $removeTree($root);
        }

        $t->same(false, $audit['readyForNonMutatingCabalPlan']);
        $t->same([], $audit['missingFiles']);
        $t->same([], $audit['missingTools']);
        $t->same([], $audit['projectSourceRepositoryPins']['missing']);
        $t->same([], $audit['projectSourceRepositoryPins']['mismatched']);
        $t->same([], $audit['projectPackageClosure']['missingPackages']);
        $t->same([], $audit['projectConstraintClosure']['missingConstraints']);
        $t->same([], $audit['runnerDependencyClosure']['missingTargets']);
        $t->same([], $audit['runnerDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['runnerDependencyClosure']['missingDependencies']);
        $t->same([], $audit['runnerDependencyClosure']['missingExecutableOptions']);
        $t->same([
            'expected' => 'Haskell2010',
            'actual' => 'Haskell98',
        ], $audit['runnerDependencyClosure']['mismatchedDefaultLanguages']['test:test-pandoc']);
        $t->same([
            'expected' => 'Haskell2010',
            'actual' => null,
        ], $audit['runnerDependencyClosure']['mismatchedDefaultLanguages']['test:test-pandoc-lua-engine']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('mismatched Cabal runner default-language', $blocked);
        $t->contains('test:test-pandoc expected Haskell2010, found Haskell98', $blocked);
        $t->contains('test:test-pandoc-lua-engine expected Haskell2010, found none', $blocked);
        $t->contains('Haskell2010 default-language closure', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks benchmark component dependency and artifact drift before planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles, $pandocCabal, $pandocBenchmark): void {
        $benchmark = $pandocBenchmark(
            ['deepseq', 'tasty-bench'],
            'wrong-benchmark.hs',
            'other-benchmark',
            '-rtsopts',
            'exitcode-stdio-1.0',
            null,
            'Haskell98'
        );
        $benchmark = str_replace(
            'text >= 1.1.1.0 && < 2.2',
            'text >= 1.0 && < 2.2',
            $benchmark
        );

        $files = $requiredFiles(
            $pinnedProject(),
            $pandocCabal([], null, null, null, 'exitcode-stdio-1.0', null, 'Haskell2010', $benchmark)
        );
        unset($files['benchmark/benchmark-pandoc.hs'], $files['test/lalune.jpg']);

        $root = $makeTree($files);
        try {
            $audit = UpstreamRunnerDependencyAudit::auditCheckout($root, [
                'ghc' => '9.10.3',
                'cabal' => '3.12.1.0',
            ]);
        } finally {
            $removeTree($root);
        }

        $target = 'benchmark:benchmark-pandoc';
        $t->same(false, $audit['readyForNonMutatingCabalPlan']);
        $t->same([$target], $audit['benchmarkTargets']);
        $t->same('pandoc.cabal', $audit['benchmarkEntryPoints'][$target]['packageFile']);
        $t->same('benchmark-pandoc.hs', $audit['benchmarkEntryPoints'][$target]['mainIs']);
        $t->same([], $audit['missingFiles']);
        $t->same([], $audit['missingTools']);
        $t->same([], $audit['runnerDependencyClosure']['missingTargets']);
        $t->same([], $audit['runnerDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingTargets']);
        $t->same(['other-benchmark'], $audit['benchmarkDependencyClosure']['present'][$target]['sourceDirectories']);
        $t->contains('main-is expected benchmark-pandoc.hs, found wrong-benchmark.hs', $audit['benchmarkDependencyClosure']['mismatchedEntryPoints'][$target][0]);
        $t->contains('hs-source-dirs missing benchmark', $audit['benchmarkDependencyClosure']['mismatchedEntryPoints'][$target][1]);
        $t->same(['deepseq', 'tasty-bench'], $audit['benchmarkDependencyClosure']['missingDependencies'][$target]);
        $t->same([
            'expected' => '>= 1.1.1.0 && < 2.2',
            'actual' => '>= 1.0 && < 2.2',
        ], $audit['benchmarkDependencyClosure']['mismatchedDependencyConstraints'][$target]['text']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingExecutableOptions']);
        $t->same([
            'expected' => 'Haskell2010',
            'actual' => 'Haskell98',
        ], $audit['benchmarkDependencyClosure']['mismatchedDefaultLanguages'][$target]);
        $t->same([
            'benchmark/benchmark-pandoc.hs',
            'test/lalune.jpg',
        ], $audit['benchmarkArtifactClosure']['missing']);
        $t->same(['benchmark:benchmark-pandoc'], $audit['benchmarkEntrySourceClosure']['missingTargets']);
        $t->same([], $audit['benchmarkArtifactClosure']['wrongType']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('mismatched Cabal benchmark entry points', $blocked);
        $t->contains('missing Cabal benchmark direct build-depends: benchmark:benchmark-pandoc (deepseq, tasty-bench)', $blocked);
        $t->contains('mismatched Cabal benchmark direct build-depends constraints: benchmark:benchmark-pandoc (text expected >= 1.1.1.0 && < 2.2, found >= 1.0 && < 2.2)', $blocked);
        $t->contains('mismatched Cabal benchmark default-language: benchmark:benchmark-pandoc expected Haskell2010, found Haskell98', $blocked);
        $t->contains('missing upstream benchmark source/data artifacts: benchmark/benchmark-pandoc.hs, test/lalune.jpg', $blocked);
        $t->contains('missing benchmark entry point source files: benchmark:benchmark-pandoc', $blocked);
        $t->contains('benchmark source/data artifacts', $audit['activationGate']);
        $t->contains('benchmark build-depends', $audit['activationGate']);
        $t->contains('benchmark executable options', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks benchmark entry point source semantic drift before planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['benchmark/benchmark-pandoc.hs'] = implode("\n", [
            'module Main (main) where',
            'import Test.Tasty.Bench',
            'main = defaultMain []',
        ]);

        $root = $makeTree($files);
        try {
            $audit = UpstreamRunnerDependencyAudit::auditCheckout($root, [
                'ghc' => '9.10.3',
                'cabal' => '3.12.1.0',
            ]);
        } finally {
            $removeTree($root);
        }

        $target = 'benchmark:benchmark-pandoc';
        $t->same(false, $audit['readyForNonMutatingCabalPlan']);
        $t->same([], $audit['missingFiles']);
        $t->same([], $audit['missingTools']);
        $t->same([], $audit['runnerDependencyClosure']['missingTargets']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingTargets']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['benchmarkArtifactClosure']['missing']);
        $t->same([], $audit['benchmarkEntrySourceClosure']['missingTargets']);
        $missing = implode("\n", $audit['benchmarkEntrySourceClosure']['missingSemantics'][$target]);
        $t->contains('imports pandoc conversion registry', $missing);
        $t->contains('skips bibliography-only formats', $missing);
        $t->contains('resolves readers by flavored format', $missing);
        $t->contains('loads image media fixture lalune', $missing);
        $t->contains('reads benchmark testsuite fixture', $missing);
        $t->contains('groups writer benchmarks', $missing);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('missing benchmark entry point source semantics', $blocked);
        $t->contains('benchmark:benchmark-pandoc', $blocked);
        $t->contains('benchmark entry-point source semantics', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks runner and benchmark mixin drift before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['pandoc.cabal'] = str_replace(
            "test-suite test-pandoc\n  import: common-executable",
            implode("\n", [
                'test-suite test-pandoc',
                '  import: common-executable',
                '  mixins:',
                '    base hiding (Prelude, Data.List),',
                '    pandoc-types (Text.Pandoc.Definition as Text.Pandoc.Definition.Audit)',
            ]),
            $files['pandoc.cabal']
        );
        $files['pandoc.cabal'] = str_replace(
            "benchmark benchmark-pandoc\n  import: common-executable",
            implode("\n", [
                'benchmark benchmark-pandoc',
                '  import: common-executable',
                '  mixins: base (Prelude as BenchPrelude)',
            ]),
            $files['pandoc.cabal']
        );
        $files['pandoc-lua-engine/pandoc-lua-engine.cabal'] = str_replace(
            "test-suite test-pandoc-lua-engine\n  import: test-options",
            implode("\n", [
                'test-suite test-pandoc-lua-engine',
                '  import: test-options',
                '  mixins: hslua (HsLua.Core as HsLua.Core.Audit)',
            ]),
            $files['pandoc-lua-engine/pandoc-lua-engine.cabal']
        );

        $root = $makeTree($files);
        try {
            $audit = UpstreamRunnerDependencyAudit::auditCheckout($root, [
                'ghc' => '9.10.3',
                'cabal' => '3.12.1.0',
            ]);
        } finally {
            $removeTree($root);
        }

        $target = 'benchmark:benchmark-pandoc';
        $t->same(false, $audit['readyForNonMutatingCabalPlan']);
        $t->same([], $audit['missingFiles']);
        $t->same([], $audit['missingTools']);
        $t->same([], $audit['runnerDependencyClosure']['missingTargets']);
        $t->same([], $audit['runnerDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['runnerDependencyClosure']['missingDependencies']);
        $t->same([], $audit['runnerDependencyClosure']['mismatchedDependencyConstraints']);
        $t->same([], $audit['runnerDependencyClosure']['missingExecutableOptions']);
        $t->same([], $audit['runnerDependencyClosure']['missingOtherModules']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingTargets']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingDependencies']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedDependencyConstraints']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingExecutableOptions']);
        $t->same([
            'base hiding (Prelude, Data.List)',
            'pandoc-types (Text.Pandoc.Definition as Text.Pandoc.Definition.Audit)',
        ], $audit['runnerDependencyClosure']['present']['test:test-pandoc']['mixins']);
        $t->same([
            'hslua (HsLua.Core as HsLua.Core.Audit)',
        ], $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['mixins']);
        $t->same([
            'base (Prelude as BenchPrelude)',
        ], $audit['benchmarkDependencyClosure']['present'][$target]['mixins']);
        $t->same($audit['runnerDependencyClosure']['present']['test:test-pandoc']['mixins'], $audit['runnerDependencyClosure']['unexpectedMixins']['test:test-pandoc']);
        $t->same($audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['mixins'], $audit['runnerDependencyClosure']['unexpectedMixins']['test:test-pandoc-lua-engine']);
        $t->same($audit['benchmarkDependencyClosure']['present'][$target]['mixins'], $audit['benchmarkDependencyClosure']['unexpectedMixins'][$target]);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('unexpected Cabal runner mixins: test:test-pandoc (base hiding (Prelude, Data.List), pandoc-types (Text.Pandoc.Definition as Text.Pandoc.Definition.Audit)); test:test-pandoc-lua-engine (hslua (HsLua.Core as HsLua.Core.Audit))', $blocked);
        $t->contains('unexpected Cabal benchmark mixins: benchmark:benchmark-pandoc (base (Prelude as BenchPrelude))', $blocked);
        $t->contains('no unexpected runner or benchmark mixins', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks runner and benchmark build tool dependencies before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['pandoc.cabal'] = str_replace(
            "test-suite test-pandoc\n  import: common-executable",
            implode("\n", [
                'test-suite test-pandoc',
                '  import: common-executable',
                '  build-tool-depends:',
                '    doctest:doctest >= 0.20,',
                '    hspec-discover:hspec-discover',
                '  build-tools: cpphs happy',
            ]),
            $files['pandoc.cabal']
        );
        $files['pandoc.cabal'] = str_replace(
            "benchmark benchmark-pandoc\n  import: common-executable",
            implode("\n", [
                'benchmark benchmark-pandoc',
                '  import: common-executable',
                '  build-tool-depends: tasty-discover:tasty-discover',
                '  build-tools: alex',
            ]),
            $files['pandoc.cabal']
        );
        $files['pandoc-lua-engine/pandoc-lua-engine.cabal'] = str_replace(
            "test-suite test-pandoc-lua-engine\n  import: test-options",
            implode("\n", [
                'test-suite test-pandoc-lua-engine',
                '  import: test-options',
                '  build-tool-depends: hslua-cli:hslua-cli',
            ]),
            $files['pandoc-lua-engine/pandoc-lua-engine.cabal']
        );

        $root = $makeTree($files);
        try {
            $audit = UpstreamRunnerDependencyAudit::auditCheckout($root, [
                'ghc' => '9.10.3',
                'cabal' => '3.12.1.0',
            ]);
        } finally {
            $removeTree($root);
        }

        $target = 'benchmark:benchmark-pandoc';
        $t->same(false, $audit['readyForNonMutatingCabalPlan']);
        $t->same([], $audit['missingFiles']);
        $t->same([], $audit['missingTools']);
        $t->same([], $audit['runnerDependencyClosure']['missingTargets']);
        $t->same([], $audit['runnerDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['runnerDependencyClosure']['missingDependencies']);
        $t->same([], $audit['runnerDependencyClosure']['mismatchedDependencyConstraints']);
        $t->same([], $audit['runnerDependencyClosure']['missingExecutableOptions']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedMixins']);
        $t->same([], $audit['runnerDependencyClosure']['missingOtherModules']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingTargets']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingDependencies']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedDependencyConstraints']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingExecutableOptions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedMixins']);
        $t->same([
            'doctest:doctest >= 0.20',
            'hspec-discover:hspec-discover',
        ], $audit['runnerDependencyClosure']['present']['test:test-pandoc']['buildToolDepends']);
        $t->same([
            'cpphs',
            'happy',
        ], $audit['runnerDependencyClosure']['present']['test:test-pandoc']['buildTools']);
        $t->same([
            'hslua-cli:hslua-cli',
        ], $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['buildToolDepends']);
        $t->same([
            'tasty-discover:tasty-discover',
        ], $audit['benchmarkDependencyClosure']['present'][$target]['buildToolDepends']);
        $t->same(['alex'], $audit['benchmarkDependencyClosure']['present'][$target]['buildTools']);
        $t->same([
            'build-tool-depends: doctest:doctest >= 0.20',
            'build-tool-depends: hspec-discover:hspec-discover',
            'build-tools: cpphs',
            'build-tools: happy',
        ], $audit['runnerDependencyClosure']['unexpectedBuildTools']['test:test-pandoc']);
        $t->same([
            'build-tool-depends: hslua-cli:hslua-cli',
        ], $audit['runnerDependencyClosure']['unexpectedBuildTools']['test:test-pandoc-lua-engine']);
        $t->same([
            'build-tool-depends: tasty-discover:tasty-discover',
            'build-tools: alex',
        ], $audit['benchmarkDependencyClosure']['unexpectedBuildTools'][$target]);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('unexpected Cabal runner build-tool dependencies: test:test-pandoc (build-tool-depends: doctest:doctest >= 0.20, build-tool-depends: hspec-discover:hspec-discover, build-tools: cpphs, build-tools: happy); test:test-pandoc-lua-engine (build-tool-depends: hslua-cli:hslua-cli)', $blocked);
        $t->contains('unexpected Cabal benchmark build-tool dependencies: benchmark:benchmark-pandoc (build-tool-depends: tasty-discover:tasty-discover, build-tools: alex)', $blocked);
        $t->contains('no runner or benchmark build-tool dependencies', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks empty runner and benchmark artifacts before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['test/Tests/Command.hs'] = '';
        $files['test/testsuite.txt'] = '';
        $files['pandoc-lua-engine/test/Tests/Lua/Writer.hs'] = '';
        $files['benchmark/benchmark-pandoc.hs'] = '';
        $files['test/movie.jpg'] = '';

        $root = $makeTree($files);
        try {
            $audit = UpstreamRunnerDependencyAudit::auditCheckout($root, [
                'ghc' => '9.10.3',
                'cabal' => '3.12.1.0',
            ]);
        } finally {
            $removeTree($root);
        }

        $t->same(false, $audit['readyForNonMutatingCabalPlan']);
        $t->same([], $audit['missingFiles']);
        $t->same([], $audit['missingTools']);
        $t->same([], $audit['runnerDependencyClosure']['missingTargets']);
        $t->same([], $audit['runnerDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['runnerDependencyClosure']['missingDependencies']);
        $t->same([], $audit['runnerDependencyClosure']['mismatchedDependencyConstraints']);
        $t->same([], $audit['runnerDependencyClosure']['missingExecutableOptions']);
        $t->same([], $audit['runnerDependencyClosure']['missingOtherModules']);
        $t->same([], $audit['runnerArtifactClosure']['missing']);
        $t->same([], $audit['runnerArtifactClosure']['wrongType']);
        $t->same([
            'pandoc-lua-engine/test/Tests/Lua/Writer.hs',
            'test/Tests/Command.hs',
            'test/testsuite.txt',
        ], $audit['runnerArtifactClosure']['emptyFiles']);
        $t->same([], $audit['benchmarkArtifactClosure']['missing']);
        $t->same([], $audit['benchmarkArtifactClosure']['wrongType']);
        $t->same([
            'benchmark/benchmark-pandoc.hs',
            'test/testsuite.txt',
            'test/movie.jpg',
        ], $audit['benchmarkArtifactClosure']['emptyFiles']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('empty upstream runner source/golden fixture artifacts: pandoc-lua-engine/test/Tests/Lua/Writer.hs, test/Tests/Command.hs, test/testsuite.txt', $blocked);
        $t->contains('empty upstream benchmark source/data artifacts: benchmark/benchmark-pandoc.hs, test/testsuite.txt, test/movie.jpg', $blocked);
        $t->contains('non-empty runner source/golden fixtures', $audit['activationGate']);
        $t->contains('non-empty benchmark source/data artifacts', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'normalizes cabal line comments before resolving runner fields' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['pandoc.cabal'] = str_replace(
            "    Diff,\n    Glob,",
            "    Diff,\n    -- comment must not swallow the following dependency\n    Glob,",
            $files['pandoc.cabal']
        );
        $files['pandoc-lua-engine/pandoc-lua-engine.cabal'] = str_replace(
            "    bytestring,\n    directory,",
            "    bytestring,\n    -- comment must not hide directory from the parser\n    directory,",
            $files['pandoc-lua-engine/pandoc-lua-engine.cabal']
        );

        $root = $makeTree($files);
        try {
            $audit = UpstreamRunnerDependencyAudit::auditCheckout($root, [
                'ghc' => '9.10.3',
                'cabal' => '3.12.1.0',
            ]);
        } finally {
            $removeTree($root);
        }

        $t->same(true, $audit['readyForNonMutatingCabalPlan']);
        $t->same([], $audit['runnerDependencyClosure']['missingDependencies']);
        $t->same([], $audit['runnerDependencyClosure']['missingExecutableOptions']);
        $t->same([], $audit['runnerDependencyClosure']['missingOtherModules']);
        $t->same([], $audit['blockedReasons']);
    },
    'ignores conditional cabal runner fields when auditing unconditional closure' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['pandoc.cabal'] .= implode("\n", [
            '',
            '  if flag(optional-runner-fixtures)',
            '    build-depends: optional-runner-helper',
            '    ghc-options: -eventlog',
            '    other-modules: Tests.Optional.Runner',
            '  else',
            '    build-depends: optional-runner-fallback',
        ]);
        $files['pandoc-lua-engine/pandoc-lua-engine.cabal'] .= implode("\n", [
            '',
            '  if os(windows)',
            '    build-depends: Win32',
            '    other-modules: Tests.Lua.WindowsOnly',
        ]);

        $root = $makeTree($files);
        try {
            $audit = UpstreamRunnerDependencyAudit::auditCheckout($root, [
                'ghc' => '9.10.3',
                'cabal' => '3.12.1.0',
            ]);
        } finally {
            $removeTree($root);
        }

        $t->same(true, $audit['readyForNonMutatingCabalPlan']);
        $t->same([], $audit['runnerDependencyClosure']['missingDependencies']);
        $t->same([], $audit['runnerDependencyClosure']['missingExecutableOptions']);
        $t->same([], $audit['runnerDependencyClosure']['missingOtherModules']);
        $t->same(false, in_array('optional-runner-helper', $audit['runnerDependencyClosure']['present']['test:test-pandoc']['buildDepends'], true));
        $t->same(false, in_array('-eventlog', $audit['runnerDependencyClosure']['present']['test:test-pandoc']['ghcOptions'], true));
        $t->same(false, in_array('Tests.Optional.Runner', $audit['runnerDependencyClosure']['present']['test:test-pandoc']['otherModules'], true));
        $t->same(false, in_array('Win32', $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['buildDepends'], true));
        $t->same(false, in_array('Tests.Lua.WindowsOnly', $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['otherModules'], true));
        $t->same([], $audit['blockedReasons']);
    },
    'does not count commented runner fields as present before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['pandoc.cabal'] = str_replace(
            '  ghc-options: -rtsopts -with-rtsopts=-A8m -threaded',
            "  ghc-options: -rtsopts -with-rtsopts=-A8m\n  -- -threaded is intentionally commented out",
            $files['pandoc.cabal']
        );
        $files['pandoc.cabal'] = str_replace(
            "    Tests.Command,\n",
            "    -- Tests.Command is intentionally commented out\n",
            $files['pandoc.cabal']
        );

        $root = $makeTree($files);
        try {
            $audit = UpstreamRunnerDependencyAudit::auditCheckout($root, [
                'ghc' => '9.10.3',
                'cabal' => '3.12.1.0',
            ]);
        } finally {
            $removeTree($root);
        }

        $t->same(false, $audit['readyForNonMutatingCabalPlan']);
        $t->same(['-threaded'], $audit['runnerDependencyClosure']['missingExecutableOptions']['test:test-pandoc']);
        $t->same(['Tests.Command'], $audit['runnerDependencyClosure']['missingOtherModules']['test:test-pandoc']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('missing Cabal runner executable options: test:test-pandoc (-threaded)', $blocked);
        $t->contains('missing Cabal runner other-modules: test:test-pandoc (Tests.Command)', $blocked);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'normalizes cabal project line comments before dependency planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $project = $pinnedProject();
        $project = str_replace(
            'packages: . pandoc-lua-engine pandoc-server pandoc-cli',
            "packages: -- runner packages\n  . -- main pandoc package\n  pandoc-lua-engine\n  pandoc-server\n  pandoc-cli -- command package",
            $project
        );
        $project = str_replace(
            'constraints: skylighting-format-blaze-html >= 0.1.2, skylighting-format-context >= 0.1.0.2, auto-update >= 0.2.6, crypton >= 1.1.1',
            "constraints: -- solver floors\n  skylighting-format-blaze-html >= 0.1.2, -- HTML format floor\n  skylighting-format-context >= 0.1.0.2,\n  auto-update >= 0.2.6,\n  crypton >= 1.1.1 -- crypton floor",
            $project
        );
        $project = str_replace(
            '  flags: +embed_data_files +http',
            "  flags: +embed_data_files -- package data files\n  flags: +http -- HTTP reader support",
            $project
        );
        $project = str_replace('  type: git', '  type: git -- pinned Git dependency', $project);
        $project = preg_replace('/(  location: https:\/\/github\.com\/jgm\/[A-Za-z0-9_-]+\.git)/', '$1 -- upstream location', $project) ?? $project;
        $project = preg_replace('/(  tag: [a-f0-9]+)/', '$1 -- pinned commit', $project) ?? $project;

        $root = $makeTree($requiredFiles($project));
        try {
            $audit = UpstreamRunnerDependencyAudit::auditCheckout($root, [
                'ghc' => '9.10.3',
                'cabal' => '3.12.1.0',
            ]);
        } finally {
            $removeTree($root);
        }

        $t->same(true, $audit['readyForNonMutatingCabalPlan']);
        $t->same(UpstreamRunnerDependencyAudit::expectedProjectPackages(), $audit['projectPackageClosure']['presentPackages']);
        $t->same([], $audit['projectPackageClosure']['missingPackages']);
        $t->same([], $audit['projectPackageClosure']['missingFlags']);
        $t->same([], $audit['projectPackageClosure']['mismatchedFlags']);
        $t->same(UpstreamRunnerDependencyAudit::expectedProjectConstraints(), $audit['projectConstraintClosure']['presentConstraints']);
        $t->same([], $audit['projectConstraintClosure']['missingConstraints']);
        $t->same([], $audit['projectConstraintClosure']['mismatchedConstraints']);
        $expectedPins = UpstreamRunnerDependencyAudit::expectedProjectPins();
        ksort($expectedPins);
        $t->same($expectedPins, $audit['projectSourceRepositoryPins']['present']);
        $t->same([], $audit['projectSourceRepositoryPins']['missing']);
        $t->same([], $audit['projectSourceRepositoryPins']['mismatched']);
        $t->same([], $audit['projectSourceRepositoryClosure']['missing']);
        $t->same([], $audit['projectSourceRepositoryClosure']['mismatched']);
        $t->same([], $audit['blockedReasons']);
    },
    'does not count commented cabal project packages or flags as present' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $project = $pinnedProject();
        $project = str_replace(
            'packages: . pandoc-lua-engine pandoc-server pandoc-cli',
            "packages: . pandoc-lua-engine pandoc-server\n  -- pandoc-cli is intentionally commented out",
            $project
        );
        $project = str_replace(
            '  flags: +embed_data_files +http',
            '  flags: +embed_data_files -- +http is intentionally commented out',
            $project
        );

        $root = $makeTree($requiredFiles($project));
        try {
            $audit = UpstreamRunnerDependencyAudit::auditCheckout($root, [
                'ghc' => '9.10.3',
                'cabal' => '3.12.1.0',
            ]);
        } finally {
            $removeTree($root);
        }

        $t->same(false, $audit['readyForNonMutatingCabalPlan']);
        $t->same(['pandoc-cli'], $audit['projectPackageClosure']['missingPackages']);
        $t->same(['http'], $audit['projectPackageClosure']['missingFlags']['pandoc']);
        $t->same([], $audit['projectPackageClosure']['mismatchedFlags']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('missing cabal.project package entries: pandoc-cli', $blocked);
        $t->contains('missing cabal.project package flags: pandoc (http)', $blocked);
        $t->contains('cabal.project package entries/flags/constraints', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'ignores conditional cabal project fields when auditing unconditional closure' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $project = $pinnedProject() . "\n" . implode("\n", [
            '',
            'if arch(wasm32)',
            '  tests: False',
            '',
            '  package pandoc',
            '    flags: +embed_data_files -http',
            '',
            '  package pandoc-lua-engine',
            '    flags: -repl',
            '',
            '  source-repository-package',
            '    type: git',
            '    location: https://github.com/jappeace/ram.git',
            '    tag: 6e49475ae7b4b3545923407388690234d838dc45',
            '    post-checkout-command: sh -c "patch -N -p1 < ../../../wasm/patches/memory.patch"',
            '',
            '  allow-newer:',
            '    all:base,',
            '    all:text',
        ]);

        $root = $makeTree($requiredFiles($project));
        try {
            $audit = UpstreamRunnerDependencyAudit::auditCheckout($root, [
                'ghc' => '9.10.3',
                'cabal' => '3.12.1.0',
            ]);
        } finally {
            $removeTree($root);
        }

        $t->same(true, $audit['readyForNonMutatingCabalPlan']);
        $t->same([
            'embed_data_files' => true,
            'http' => true,
        ], $audit['projectPackageClosure']['presentFlags']['pandoc']);
        $t->same(false, array_key_exists('pandoc-lua-engine', $audit['projectPackageClosure']['presentFlags']));
        $t->same(false, array_key_exists('ram', $audit['projectSourceRepositoryPins']['present']));
        $t->same(false, array_key_exists('ram', $audit['projectSourceRepositoryClosure']['present']));
        $t->same([], $audit['projectPackageClosure']['mismatchedFlags']);
        $t->same([], $audit['projectSourceRepositoryPins']['mismatched']);
        $t->same([], $audit['projectSourceRepositoryClosure']['mismatched']);
        $t->same([], $audit['blockedReasons']);
    },
];
