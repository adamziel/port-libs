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
        'cabal-version: 2.4',
        'name: pandoc',
        'version: 3.9.0.2',
        'build-type: Simple',
        'tested-with: GHC == 9.6.7, GHC == 9.8.4, GHC == 9.10.3, GHC == 9.12.2',
        'data-files:',
        '  ' . implode(",\n  ", UpstreamRunnerDependencyAudit::expectedPackageDataFiles()['pandoc.cabal']),
        'extra-doc-files:',
        '  ' . implode(",\n  ", UpstreamRunnerDependencyAudit::expectedPackageExtraDocFiles()['pandoc.cabal']),
        'extra-source-files:',
        '  ' . implode(",\n  ", UpstreamRunnerDependencyAudit::expectedPackageExtraSourceFiles()['pandoc.cabal']),
        '',
        'source-repository head',
        '  type: git',
        '  location: https://github.com/jgm/pandoc.git',
        '',
        'flag embed_data_files',
        '  description: Embed data files in the built executable',
        '  default: False',
        '  manual: True',
        '',
        'flag http',
        '  description: Enable HTTP support for the runner closure',
        '  default: True',
        '  manual: True',
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
        'cabal-version: 2.4',
        'name: pandoc-lua-engine',
        'version: 0.5.2',
        'build-type: Simple',
        'extra-source-files:',
        '  ' . implode(",\n  ", UpstreamRunnerDependencyAudit::expectedPackageExtraSourceFiles()['pandoc-lua-engine/pandoc-lua-engine.cabal']),
        '',
        'source-repository head',
        '  type: git',
        '  location: https://github.com/jgm/pandoc.git',
        '',
        'common test-options',
        '  build-depends: ' . implode(', ', $formatRunnerDependencies('test:test-pandoc-lua-engine', $commonDependencies)),
        $defaultLanguage === null || $defaultLanguage === '' ? '' : '  default-language: ' . $defaultLanguage,
        '',
        'library',
        '  import: test-options',
        '  hs-source-dirs: src',
        '  exposed-modules: Text.Pandoc.Lua',
        '  other-modules:',
        '    ' . implode(",\n    ", UpstreamRunnerDependencyAudit::expectedLuaEngineLibraryOtherModules()),
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

$serverCabal = static function (): string {
    return implode("\n", [
        'cabal-version: 2.4',
        'name: pandoc-server',
        'version: 0.1.2',
        'build-type: Simple',
        '',
        'source-repository head',
        '  type: git',
        '  location: https://github.com/jgm/pandoc.git',
        '',
        'common common-options',
        '  default-language: Haskell2010',
        '  build-depends: base >= 4.12 && < 5',
        '',
        'library',
        '  import: common-options',
        '  build-depends:',
        '    pandoc >= 3.9 && < 3.10,',
        '    pandoc-types >= 1.22 && < 1.24,',
        '    containers >= 0.6.0.1 && < 0.9,',
        '    aeson >= 2.0 && < 2.3,',
        '    bytestring >= 0.9 && < 0.13,',
        '    base64-bytestring >= 0.1 && < 1.3,',
        '    doctemplates >= 0.11 && < 0.12,',
        '    data-default >= 0.4 && < 0.9,',
        '    text >= 1.1.1.0 && < 2.2,',
        '    unicode-collation >= 0.1.1 && < 0.2,',
        '    servant-server >= 0.19 && < 0.21,',
        '    skylighting >= 0.13 && < 0.15,',
        '    wai >= 3.2 && < 3.3,',
        '    wai-cors >= 0.2.7 && < 0.3',
        '  hs-source-dirs: src',
        '  exposed-modules: Text.Pandoc.Server',
        '  buildable: True',
    ]);
};

$cliCabal = static function (): string {
    return implode("\n", [
        'cabal-version: 2.4',
        'name: pandoc-cli',
        'version: 3.9.0.2',
        'build-type: Simple',
        'extra-source-files:',
        '  ' . implode(",\n  ", UpstreamRunnerDependencyAudit::expectedPackageExtraSourceFiles()['pandoc-cli/pandoc-cli.cabal']),
        '',
        'source-repository head',
        '  type: git',
        '  location: https://github.com/jgm/pandoc.git',
        '',
        'flag lua',
        '  description: Support custom modifications and conversions with the pandoc Lua scripting engine.',
        '  default: True',
        '',
        'flag server',
        '  description: Include support for running pandoc as an HTTP server.',
        '  default: True',
        '',
        'flag repl',
        '  description: Include support for running a pandoc Lua repl.',
        '  default: True',
        '',
        'flag nightly',
        '  description: Add nightly suffix to version output.',
        '  default: False',
        '',
        'common common-options',
        '  default-language: Haskell2010',
        '  other-extensions: OverloadedStrings',
        '  build-depends: base >= 4.18 && < 5',
        '  ghc-options: -Wall',
        '               -fno-warn-unused-do-bind',
        '               -Wincomplete-record-updates',
        '               -Wnoncanonical-monad-instances',
        '               -Wcpp-undef',
        '               -Wincomplete-uni-patterns',
        '               -Widentities',
        '               -Wpartial-fields',
        '               -Wmissing-signatures',
        '               -fhide-source-paths',
        '               -Wunused-packages',
        '               -Winvalid-haddock',
        '  if os(windows)',
        '    cpp-options: -D_WINDOWS',
        '',
        'common common-executable',
        '  import: common-options',
        '  ghc-options: -rtsopts -with-rtsopts=-A8m',
        '',
        'executable pandoc',
        '  import: common-executable',
        '  main-is: pandoc.hs',
        '  hs-source-dirs: src',
        '  buildable: True',
        '  build-depends: pandoc == 3.9.0.2, text',
        '  other-modules: PandocCLI.Lua, PandocCLI.Server',
        '  if arch(wasm32)',
        '    hs-source-dirs: wasm',
        '    other-modules: PandocWasm',
        '    cpp-options: -DINCLUDE_WASM',
        '    build-depends: aeson, containers, bytestring, skylighting, filepath, pandoc-lua-engine',
        '    ghc-options: -optl-Wl,--export=__wasm_call_ctors,--export=hs_init_with_rtsopts,--export=malloc,--export=convert,--export=query',
        '  else',
        '    ghc-options: -threaded',
        '  if flag(nightly)',
        '    cpp-options: -DNIGHTLY',
        '    build-depends: template-haskell, time',
        '  if flag(server)',
        '    build-depends: pandoc-server >= 0.1.1 && < 0.2, wai-extra >= 3.0.24, warp, safe',
        '    hs-source-dirs: server',
        '  else',
        '    hs-source-dirs: no-server',
        '  if flag(lua)',
        '    build-depends: pandoc-lua-engine >= 0.5.1 && < 0.6',
        '    hs-source-dirs: lua',
        '  else',
        '    hs-source-dirs: no-lua',
        '  if flag(repl)',
        '    build-depends: hslua-cli >= 1.4.1 && < 1.5, temporary >= 1.1 && < 1.4',
        '    cpp-options: -DREPL',
    ]);
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

$luaLibraryArtifacts = static function (): array {
    $files = [];
    foreach (UpstreamRunnerDependencyAudit::expectedLuaEngineLibrarySourceArtifacts() as $relativePath) {
        $files[$relativePath] = 'module fixture present for ' . $relativePath;
    }

    return $files;
};

$benchmarkArtifacts = static function () use ($benchmarkEntryPoint): array {
    $files = [];
    $benchmarkTestsuiteFixture = implode("\n\n", [
        '% Pandoc Test Suite',
        '# Headers',
        '# Code Blocks',
        '# Block Quotes',
        '# Lists',
        '# Definition Lists',
        '# HTML Blocks',
        '# Inline Markup',
        '# Smart quotes, ellipses, dashes',
        '# LaTeX',
        '# Special Characters',
        '# Links',
        '# Images',
        '![lalune][]',
        '   [lalune]: lalune.jpg "Voyage dans la Lune"',
        'Here is a movie ![movie](movie.jpg) icon.',
        '# Footnotes',
    ]) . "\n";
    $jpegFixture = "\xff\xd8" . 'benchmark-jpeg-fixture' . "\xff\xd9";
    foreach (UpstreamRunnerDependencyAudit::expectedBenchmarkArtifacts() as $relativePath => $kind) {
        if ($kind === 'directory') {
            $files[$relativePath . '/.audit-keep'] = 'fixture root present';
        } elseif ($relativePath === 'benchmark/benchmark-pandoc.hs') {
            $files[$relativePath] = $benchmarkEntryPoint();
        } elseif ($relativePath === 'test/testsuite.txt') {
            $files[$relativePath] = $benchmarkTestsuiteFixture;
        } elseif ($relativePath === 'test/lalune.jpg' || $relativePath === 'test/movie.jpg') {
            $files[$relativePath] = $jpegFixture;
        } else {
            $files[$relativePath] = 'benchmark fixture artifact present';
        }
    }

    return $files;
};

$cliSourceArtifacts = static function (): array {
    $files = [];
    foreach (UpstreamRunnerDependencyAudit::expectedCliExecutableSourceArtifacts() as $relativePath => $kind) {
        if ($kind === 'directory') {
            $files[$relativePath . '/.audit-keep'] = 'cli executable source fixture present';
        } else {
            $semantics = UpstreamRunnerDependencyAudit::expectedCliExecutableSourceSemantics()[$relativePath] ?? [];
            $files[$relativePath] = implode("\n", array_merge(
                ['cli executable source fixture present for ' . $relativePath],
                array_values($semantics)
            ));
        }
    }

    return $files;
};

$formatRegistrySourceArtifacts = static function (): array {
    $files = [];
    foreach (UpstreamRunnerDependencyAudit::expectedFormatRegistrySourceArtifacts() as $relativePath => $kind) {
        if ($kind === 'directory') {
            $files[$relativePath . '/.audit-keep'] = 'format registry source fixture present';
            continue;
        }

        $files[$relativePath] = implode("\n", array_values(
            UpstreamRunnerDependencyAudit::expectedFormatRegistrySourceSemantics()[$relativePath] ?? []
        ));
    }

    return $files;
};

$requiredFiles = static function (string $project, ?string $pandocPackage = null, ?string $luaPackage = null, bool $includeRunnerArtifacts = true, ?string $serverPackage = null, ?string $cliPackage = null) use ($pandocCabal, $luaCabal, $serverCabal, $cliCabal, $runnerArtifacts, $luaLibraryArtifacts, $benchmarkArtifacts, $cliSourceArtifacts, $formatRegistrySourceArtifacts, $testPandocEntryPoint, $luaEntryPoint): array {
    $files = [
        'cabal.project' => $project,
        'pandoc.cabal' => $pandocPackage ?? $pandocCabal(),
        'pandoc-lua-engine/pandoc-lua-engine.cabal' => $luaPackage ?? $luaCabal(),
        'pandoc-server/pandoc-server.cabal' => $serverPackage ?? $serverCabal(),
        'pandoc-cli/pandoc-cli.cabal' => $cliPackage ?? $cliCabal(),
        'test/test-pandoc.hs' => $testPandocEntryPoint(),
        'pandoc-lua-engine/test/test-pandoc-lua-engine.hs' => $luaEntryPoint(),
    ];

    $files = array_merge($files, $luaLibraryArtifacts());
    $files = array_merge($files, $cliSourceArtifacts());
    $files = array_merge($files, $formatRegistrySourceArtifacts());

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
            'pandoc-server/pandoc-server.cabal',
            'pandoc-cli/pandoc-cli.cabal',
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
        $t->same(['embed_data_files', 'http'], $audit['packageFlagDefinitionClosure']['missingFlags']['pandoc.cabal']);
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
        $t->contains('missing Cabal package flag definitions: pandoc.cabal (embed_data_files, http)', $blocked);
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
        $t->same([], $audit['projectSourceRepositoryClosure']['unexpectedFields']);
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
        $t->same(UpstreamRunnerDependencyAudit::expectedProjectUnconditionalFields(), $audit['projectUnconditionalFieldClosure']['expectedFields']);
        $t->same([
            'constraints',
            'packages',
        ], $audit['projectUnconditionalFieldClosure']['presentFields']);
        $t->same([], $audit['projectUnconditionalFieldClosure']['unexpectedFields']);
        $t->same(UpstreamRunnerDependencyAudit::expectedProjectConditionalBranches(), $audit['projectConditionalBranchClosure']['expectedBranches']);
        $t->same([], $audit['projectConditionalBranchClosure']['presentBranches']);
        $t->same([], $audit['projectConditionalBranchClosure']['unexpectedBranches']);
        $t->same(UpstreamRunnerDependencyAudit::expectedCompilerGhcVersions(), $audit['compilerTestedWithClosure']['presentGhcVersions']);
        $t->same([], $audit['compilerTestedWithClosure']['missingGhcVersions']);
        $t->same('9.10.3', $audit['compilerTestedWithClosure']['toolGhcVersion']);
        $t->same(true, $audit['compilerTestedWithClosure']['toolGhcVersionSupported']);
        $t->same(UpstreamRunnerDependencyAudit::expectedPackageIdentities(), $audit['packageIdentityClosure']['expected']);
        $t->same(UpstreamRunnerDependencyAudit::expectedPackageIdentities(), $audit['packageIdentityClosure']['present']);
        $t->same([], $audit['packageIdentityClosure']['missingHeaders']);
        $t->same([], $audit['packageIdentityClosure']['mismatchedHeaders']);
        $t->same(UpstreamRunnerDependencyAudit::expectedPackageSetupDependencies(), $audit['packageSetupClosure']['expectedSetupDependencies']);
        $t->same(false, $audit['packageSetupClosure']['present']['pandoc.cabal']['customSetup']);
        $t->same(false, $audit['packageSetupClosure']['present']['pandoc-lua-engine/pandoc-lua-engine.cabal']['customSetup']);
        $t->same([], $audit['packageSetupClosure']['unexpectedCustomSetupStanzas']);
        $t->same([], $audit['packageSetupClosure']['unexpectedSetupDependencies']);
        $t->same(UpstreamRunnerDependencyAudit::expectedPackageFlagDefinitions(), $audit['packageFlagDefinitionClosure']['expectedFlags']);
        $t->same(['embed_data_files', 'http'], $audit['packageFlagDefinitionClosure']['presentFlags']['pandoc.cabal']);
        $t->same([], $audit['packageFlagDefinitionClosure']['presentFlags']['pandoc-lua-engine/pandoc-lua-engine.cabal']);
        $t->same([], $audit['packageFlagDefinitionClosure']['missingFlags']);
        $t->same(UpstreamRunnerDependencyAudit::expectedPackageFlagFields(), $audit['packageFlagDefinitionClosure']['expectedFlagFields']);
        $t->same([
            'embed_data_files' => [
                'default' => 'False',
                'manual' => 'True',
            ],
            'http' => [
                'default' => 'True',
                'manual' => 'True',
            ],
        ], $audit['packageFlagDefinitionClosure']['presentFlagFields']['pandoc.cabal']);
        $t->same([
            'lua' => [
                'default' => 'True',
                'manual' => null,
            ],
            'nightly' => [
                'default' => 'False',
                'manual' => null,
            ],
            'repl' => [
                'default' => 'True',
                'manual' => null,
            ],
            'server' => [
                'default' => 'True',
                'manual' => null,
            ],
        ], $audit['packageFlagDefinitionClosure']['presentFlagFields']['pandoc-cli/pandoc-cli.cabal']);
        $t->same([], $audit['packageFlagDefinitionClosure']['mismatchedFlagFields']);
        $t->same(UpstreamRunnerDependencyAudit::expectedPackageDataFiles(), $audit['packageDataFileClosure']['expectedDataFiles']);
        $t->same(UpstreamRunnerDependencyAudit::expectedPackageDataFiles()['pandoc.cabal'], $audit['packageDataFileClosure']['presentDataFiles']['pandoc.cabal']);
        $t->same([], $audit['packageDataFileClosure']['presentDataFiles']['pandoc-lua-engine/pandoc-lua-engine.cabal']);
        $t->same([], $audit['packageDataFileClosure']['presentDataFiles']['pandoc-server/pandoc-server.cabal']);
        $t->same([], $audit['packageDataFileClosure']['presentDataFiles']['pandoc-cli/pandoc-cli.cabal']);
        $t->same([], $audit['packageDataFileClosure']['missingDataFiles']);
        $t->same([], $audit['packageDataFileClosure']['unexpectedDataFiles']);
        $t->same(UpstreamRunnerDependencyAudit::expectedPackageExtraDocFiles(), $audit['packageExtraFileClosure']['expectedExtraDocFiles']);
        $t->same(UpstreamRunnerDependencyAudit::expectedPackageExtraDocFiles()['pandoc.cabal'], $audit['packageExtraFileClosure']['presentExtraDocFiles']['pandoc.cabal']);
        $t->same([], $audit['packageExtraFileClosure']['presentExtraDocFiles']['pandoc-lua-engine/pandoc-lua-engine.cabal']);
        $t->same([], $audit['packageExtraFileClosure']['presentExtraDocFiles']['pandoc-server/pandoc-server.cabal']);
        $t->same([], $audit['packageExtraFileClosure']['presentExtraDocFiles']['pandoc-cli/pandoc-cli.cabal']);
        $t->same([], $audit['packageExtraFileClosure']['missingExtraDocFiles']);
        $t->same([], $audit['packageExtraFileClosure']['unexpectedExtraDocFiles']);
        $t->same(UpstreamRunnerDependencyAudit::expectedPackageExtraSourceFiles(), $audit['packageExtraFileClosure']['expectedExtraSourceFiles']);
        $t->same(UpstreamRunnerDependencyAudit::expectedPackageExtraSourceFiles()['pandoc.cabal'], $audit['packageExtraFileClosure']['presentExtraSourceFiles']['pandoc.cabal']);
        $t->same(UpstreamRunnerDependencyAudit::expectedPackageExtraSourceFiles()['pandoc-lua-engine/pandoc-lua-engine.cabal'], $audit['packageExtraFileClosure']['presentExtraSourceFiles']['pandoc-lua-engine/pandoc-lua-engine.cabal']);
        $t->same([], $audit['packageExtraFileClosure']['presentExtraSourceFiles']['pandoc-server/pandoc-server.cabal']);
        $t->same(UpstreamRunnerDependencyAudit::expectedPackageExtraSourceFiles()['pandoc-cli/pandoc-cli.cabal'], $audit['packageExtraFileClosure']['presentExtraSourceFiles']['pandoc-cli/pandoc-cli.cabal']);
        $t->same([], $audit['packageExtraFileClosure']['missingExtraSourceFiles']);
        $t->same([], $audit['packageExtraFileClosure']['unexpectedExtraSourceFiles']);
        $t->same(UpstreamRunnerDependencyAudit::expectedPackageExtraTmpFiles(), $audit['packageExtraFileClosure']['expectedExtraTmpFiles']);
        $t->same([], $audit['packageExtraFileClosure']['presentExtraTmpFiles']['pandoc.cabal']);
        $t->same([], $audit['packageExtraFileClosure']['presentExtraTmpFiles']['pandoc-lua-engine/pandoc-lua-engine.cabal']);
        $t->same([], $audit['packageExtraFileClosure']['presentExtraTmpFiles']['pandoc-server/pandoc-server.cabal']);
        $t->same([], $audit['packageExtraFileClosure']['presentExtraTmpFiles']['pandoc-cli/pandoc-cli.cabal']);
        $t->same([], $audit['packageExtraFileClosure']['unexpectedExtraTmpFiles']);
        $t->same(UpstreamRunnerDependencyAudit::expectedPackageNativeSystemFields(), $audit['packageNativeSystemFieldClosure']['expectedNativeSystemFields']);
        $t->same([], $audit['packageNativeSystemFieldClosure']['presentNativeSystemFields']['pandoc.cabal']);
        $t->same([], $audit['packageNativeSystemFieldClosure']['presentNativeSystemFields']['pandoc-lua-engine/pandoc-lua-engine.cabal']);
        $t->same([], $audit['packageNativeSystemFieldClosure']['presentNativeSystemFields']['pandoc-server/pandoc-server.cabal']);
        $t->same([], $audit['packageNativeSystemFieldClosure']['presentNativeSystemFields']['pandoc-cli/pandoc-cli.cabal']);
        $t->same([], $audit['packageNativeSystemFieldClosure']['unexpectedNativeSystemFields']);
        $t->same(UpstreamRunnerDependencyAudit::expectedPackageSourceRepositories(), $audit['packageSourceRepositoryClosure']['expected']);
        $t->same('git', $audit['packageSourceRepositoryClosure']['present']['pandoc.cabal']['head']['type']);
        $t->same('https://github.com/jgm/pandoc.git', $audit['packageSourceRepositoryClosure']['present']['pandoc-cli/pandoc-cli.cabal']['head']['location']);
        $t->same([], $audit['packageSourceRepositoryClosure']['missing']);
        $t->same([], $audit['packageSourceRepositoryClosure']['mismatched']);
        $t->same([], $audit['packageSourceRepositoryClosure']['unexpected']);
        $t->same([], $audit['packageSourceRepositoryClosure']['unexpectedFields']);
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
        $t->same([], $audit['runnerDependencyClosure']['unexpectedExecutableOptions']);
        $t->same([], $audit['runnerDependencyClosure']['mismatchedDefaultLanguages']);
        $t->same(UpstreamRunnerDependencyAudit::expectedRunnerManualFields(), $audit['runnerDependencyClosure']['expectedManualFields']);
        $t->same([], $audit['runnerDependencyClosure']['mismatchedManualFields']);
        $t->same([], $audit['runnerDependencyClosure']['missingOtherModules']);
        $t->same(UpstreamRunnerDependencyAudit::expectedRunnerDefaultExtensions(), $audit['runnerDependencyClosure']['expectedDefaultExtensions']);
        $t->same(UpstreamRunnerDependencyAudit::expectedRunnerOtherExtensions(), $audit['runnerDependencyClosure']['expectedOtherExtensions']);
        $t->same(UpstreamRunnerDependencyAudit::expectedRunnerTestOptions(), $audit['runnerDependencyClosure']['expectedTestOptions']);
        $t->same(UpstreamRunnerDependencyAudit::expectedRunnerAutogenModules(), $audit['runnerDependencyClosure']['expectedAutogenModules']);
        $t->same(UpstreamRunnerDependencyAudit::expectedRunnerReexportedModules(), $audit['runnerDependencyClosure']['expectedReexportedModules']);
        $t->same(UpstreamRunnerDependencyAudit::expectedRunnerExtraSourceFiles(), $audit['runnerDependencyClosure']['expectedExtraSourceFiles']);
        $t->same(UpstreamRunnerDependencyAudit::expectedRunnerExtraDocFiles(), $audit['runnerDependencyClosure']['expectedExtraDocFiles']);
        $t->same(UpstreamRunnerDependencyAudit::expectedRunnerExtraTmpFiles(), $audit['runnerDependencyClosure']['expectedExtraTmpFiles']);
        $t->same(UpstreamRunnerDependencyAudit::expectedRunnerDataFiles(), $audit['runnerDependencyClosure']['expectedDataFiles']);
        $t->same(UpstreamRunnerDependencyAudit::expectedRunnerConditionalBranches(), $audit['runnerDependencyClosure']['expectedConditionalBranches']);
        $t->same(UpstreamRunnerDependencyAudit::expectedRunnerNativeSystemFields(), $audit['runnerDependencyClosure']['expectedNativeSystemFields']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedDefaultExtensions']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedOtherExtensions']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedTestOptions']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedAutogenModules']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedReexportedModules']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedExtraSourceFiles']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedExtraDocFiles']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedExtraTmpFiles']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedDataFiles']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedConditionalBranches']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedNativeSystemFields']);
        $t->same(UpstreamRunnerDependencyAudit::expectedBenchmarkOptions(), $audit['benchmarkDependencyClosure']['expectedBenchmarkOptions']);
        $t->same(UpstreamRunnerDependencyAudit::expectedBenchmarkManualFields(), $audit['benchmarkDependencyClosure']['expectedManualFields']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedManualFields']);
        $t->same(UpstreamRunnerDependencyAudit::expectedBenchmarkExtraSourceFiles(), $audit['benchmarkDependencyClosure']['expectedExtraSourceFiles']);
        $t->same(UpstreamRunnerDependencyAudit::expectedBenchmarkExtraDocFiles(), $audit['benchmarkDependencyClosure']['expectedExtraDocFiles']);
        $t->same(UpstreamRunnerDependencyAudit::expectedBenchmarkExtraTmpFiles(), $audit['benchmarkDependencyClosure']['expectedExtraTmpFiles']);
        $t->same(UpstreamRunnerDependencyAudit::expectedBenchmarkDataFiles(), $audit['benchmarkDependencyClosure']['expectedDataFiles']);
        $t->same(UpstreamRunnerDependencyAudit::expectedBenchmarkConditionalBranches(), $audit['benchmarkDependencyClosure']['expectedConditionalBranches']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedBenchmarkOptions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedExecutableOptions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedExtraSourceFiles']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedExtraDocFiles']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedExtraTmpFiles']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedDataFiles']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedConditionalBranches']);
        $t->same(UpstreamRunnerDependencyAudit::expectedLuaEngineLibraryDependencies(), $audit['luaEngineLibraryClosure']['expectedDependencies']);
        $t->same(UpstreamRunnerDependencyAudit::expectedLuaEngineLibraryExposedModules(), $audit['luaEngineLibraryClosure']['expectedExposedModules']);
        $t->same(['Text.Pandoc.Lua'], $audit['luaEngineLibraryClosure']['presentExposedModules']);
        $t->same([], $audit['luaEngineLibraryClosure']['missingExposedModules']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedExposedModules']);
        $t->same(UpstreamRunnerDependencyAudit::expectedLuaEngineLibrarySourceDirectories(), $audit['luaEngineLibraryClosure']['expectedSourceDirectories']);
        $t->same(['src'], $audit['luaEngineLibraryClosure']['presentSourceDirectories']);
        $t->same([], $audit['luaEngineLibraryClosure']['missingSourceDirectories']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedSourceDirectories']);
        $t->same(UpstreamRunnerDependencyAudit::expectedLuaEngineLibraryOtherModules(), $audit['luaEngineLibraryClosure']['expectedOtherModules']);
        $t->same([], $audit['luaEngineLibraryClosure']['missingOtherModules']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedOtherModules']);
        $t->same(UpstreamRunnerDependencyAudit::expectedLuaEngineLibrarySourceArtifacts(), $audit['luaEngineLibraryClosure']['expectedSourceArtifacts']);
        $t->same([], $audit['luaEngineLibraryClosure']['missingSourceArtifacts']);
        $t->same([], $audit['luaEngineLibraryClosure']['wrongTypeSourceArtifacts']);
        $t->same([], $audit['luaEngineLibraryClosure']['emptySourceArtifacts']);
        foreach (UpstreamRunnerDependencyAudit::expectedLuaEngineLibrarySourceArtifacts() as $relativePath) {
            $t->same(true, isset($audit['luaEngineLibraryClosure']['sourceArtifactProvenance'][$relativePath]));
            $t->same(true, $audit['luaEngineLibraryClosure']['sourceArtifactProvenance'][$relativePath]['bytes'] > 0);
        }
        $t->same(UpstreamRunnerDependencyAudit::expectedLuaEngineLibraryDefaultLanguage(), $audit['luaEngineLibraryClosure']['expectedDefaultLanguage']);
        $t->same('Haskell2010', $audit['luaEngineLibraryClosure']['presentDefaultLanguage']);
        $t->same(null, $audit['luaEngineLibraryClosure']['mismatchedDefaultLanguage']);
        $t->same(UpstreamRunnerDependencyAudit::expectedLuaEngineLibraryDefaultExtensions(), $audit['luaEngineLibraryClosure']['expectedDefaultExtensions']);
        $t->same(UpstreamRunnerDependencyAudit::expectedLuaEngineLibraryOtherExtensions(), $audit['luaEngineLibraryClosure']['expectedOtherExtensions']);
        $t->same([], $audit['luaEngineLibraryClosure']['missingDependencies']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedDependencies']);
        $t->same([], $audit['luaEngineLibraryClosure']['presentDefaultExtensions']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedDefaultExtensions']);
        $t->same([], $audit['luaEngineLibraryClosure']['presentOtherExtensions']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedOtherExtensions']);
        $t->same(UpstreamRunnerDependencyAudit::expectedLuaEngineLibraryAutogenModules(), $audit['luaEngineLibraryClosure']['expectedAutogenModules']);
        $t->same([], $audit['luaEngineLibraryClosure']['presentAutogenModules']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedAutogenModules']);
        $t->same(UpstreamRunnerDependencyAudit::expectedLuaEngineLibraryReexportedModules(), $audit['luaEngineLibraryClosure']['expectedReexportedModules']);
        $t->same([], $audit['luaEngineLibraryClosure']['presentReexportedModules']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedReexportedModules']);
        $t->same(UpstreamRunnerDependencyAudit::expectedLuaEngineLibraryModuleInterfaceFields(), $audit['luaEngineLibraryClosure']['expectedModuleInterfaceFields']);
        $t->same([], $audit['luaEngineLibraryClosure']['presentModuleInterfaceFields']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedModuleInterfaceFields']);
        $t->same(true, in_array('hslua-module-zip', $audit['luaEngineLibraryClosure']['presentDependencies'], true));
        $t->same(true, in_array('pandoc-lua-marshal', $audit['luaEngineLibraryClosure']['presentDependencies'], true));
        $t->same(UpstreamRunnerDependencyAudit::expectedServerLibraryDependencies(), $audit['serverLibraryClosure']['expectedDependencies']);
        $t->same(UpstreamRunnerDependencyAudit::expectedServerLibraryDependencies(), $audit['serverLibraryClosure']['presentDependencies']);
        $t->same(UpstreamRunnerDependencyAudit::expectedServerLibraryDependencyConstraints(), $audit['serverLibraryClosure']['dependencyConstraints']);
        $t->same([], $audit['serverLibraryClosure']['missingDependencies']);
        $t->same([], $audit['serverLibraryClosure']['unexpectedDependencies']);
        $t->same([], $audit['serverLibraryClosure']['mismatchedDependencyConstraints']);
        $t->same(UpstreamRunnerDependencyAudit::expectedServerLibraryExposedModules(), $audit['serverLibraryClosure']['expectedExposedModules']);
        $t->same(['Text.Pandoc.Server'], $audit['serverLibraryClosure']['presentExposedModules']);
        $t->same([], $audit['serverLibraryClosure']['missingExposedModules']);
        $t->same([], $audit['serverLibraryClosure']['unexpectedExposedModules']);
        $t->same(UpstreamRunnerDependencyAudit::expectedServerLibrarySourceDirectories(), $audit['serverLibraryClosure']['presentSourceDirectories']);
        $t->same(UpstreamRunnerDependencyAudit::expectedServerLibraryDefaultLanguage(), $audit['serverLibraryClosure']['presentDefaultLanguage']);
        $t->same(null, $audit['serverLibraryClosure']['mismatchedDefaultLanguage']);
        $t->same(false, $audit['cliExecutableClosure']['missingExecutable']);
        $t->same('pandoc.hs', $audit['cliExecutableClosure']['presentMainIs']);
        $t->same(true, $audit['cliExecutableClosure']['presentBuildable']);
        $t->same([], $audit['cliExecutableClosure']['mismatchedEntryPoint']);
        $t->same(UpstreamRunnerDependencyAudit::expectedCliExecutableDependencies(), $audit['cliExecutableClosure']['expectedDependencies']);
        $t->same(UpstreamRunnerDependencyAudit::expectedCliExecutableDependencies(), $audit['cliExecutableClosure']['presentDependencies']);
        $t->same(UpstreamRunnerDependencyAudit::expectedCliExecutableDependencyConstraints(), $audit['cliExecutableClosure']['expectedDependencyConstraints']);
        $t->same('>= 4.18 && < 5', $audit['cliExecutableClosure']['dependencyConstraints']['base']);
        $t->same('== 3.9.0.2', $audit['cliExecutableClosure']['dependencyConstraints']['pandoc']);
        $t->same([], $audit['cliExecutableClosure']['missingDependencies']);
        $t->same([], $audit['cliExecutableClosure']['unexpectedDependencies']);
        $t->same([], $audit['cliExecutableClosure']['mismatchedDependencyConstraints']);
        $t->same(UpstreamRunnerDependencyAudit::expectedCliExecutableOptions(), $audit['cliExecutableClosure']['presentExecutableOptions']);
        $t->same([], $audit['cliExecutableClosure']['missingExecutableOptions']);
        $t->same([], $audit['cliExecutableClosure']['unexpectedExecutableOptions']);
        $t->same(UpstreamRunnerDependencyAudit::expectedCliExecutableDefaultLanguage(), $audit['cliExecutableClosure']['presentDefaultLanguage']);
        $t->same(null, $audit['cliExecutableClosure']['mismatchedDefaultLanguage']);
        $t->same(UpstreamRunnerDependencyAudit::expectedCliExecutableCommonImports(), $audit['cliExecutableClosure']['presentCommonImports']);
        $t->same([], $audit['cliExecutableClosure']['missingCommonImports']);
        $t->same([], $audit['cliExecutableClosure']['unexpectedCommonImports']);
        $t->same([], $audit['cliExecutableClosure']['unresolvedCommonImports']);
        $t->same(UpstreamRunnerDependencyAudit::expectedCliExecutableSourceDirectories(), $audit['cliExecutableClosure']['presentSourceDirectories']);
        $t->same([], $audit['cliExecutableClosure']['missingSourceDirectories']);
        $t->same([], $audit['cliExecutableClosure']['unexpectedSourceDirectories']);
        $t->same(UpstreamRunnerDependencyAudit::expectedCliExecutableOtherExtensions(), $audit['cliExecutableClosure']['presentOtherExtensions']);
        $t->same([], $audit['cliExecutableClosure']['missingOtherExtensions']);
        $t->same([], $audit['cliExecutableClosure']['unexpectedOtherExtensions']);
        $t->same(UpstreamRunnerDependencyAudit::expectedCliExecutableOtherModules(), $audit['cliExecutableClosure']['presentOtherModules']);
        $t->same([], $audit['cliExecutableClosure']['missingOtherModules']);
        $t->same([], $audit['cliExecutableClosure']['unexpectedOtherModules']);
        $t->same(UpstreamRunnerDependencyAudit::expectedCliExecutableConditionalBranches(), $audit['cliExecutableClosure']['presentConditionalBranches']);
        $t->same([], $audit['cliExecutableClosure']['missingConditionalBranches']);
        $t->same([], $audit['cliExecutableClosure']['unexpectedConditionalBranches']);
        $t->same(UpstreamRunnerDependencyAudit::expectedCliExecutableConditionalFieldClosure(), $audit['cliExecutableClosure']['expectedConditionalFieldClosure']);
        $t->same(UpstreamRunnerDependencyAudit::expectedCliExecutableConditionalFieldClosure(), $audit['cliExecutableClosure']['presentConditionalFieldClosure']);
        $t->same([], $audit['cliExecutableClosure']['missingConditionalFieldEntries']);
        $t->same([], $audit['cliExecutableClosure']['unexpectedConditionalFieldEntries']);
        $t->same([], $audit['cliExecutableClosure']['unexpectedDefaultExtensions']);
        $t->same([], $audit['cliExecutableClosure']['unexpectedCppOptions']);
        $t->same([], $audit['cliExecutableClosure']['unexpectedBuildTools']);
        $t->same([], $audit['cliExecutableClosure']['unexpectedNativeSystemFields']);
        $t->same('exitcode-stdio-1.0', $audit['runnerDependencyClosure']['present']['test:test-pandoc']['type']);
        $t->same('exitcode-stdio-1.0', $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['type']);
        $t->same(['test'], $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['sourceDirectories']);
        $t->same('Haskell2010', $audit['runnerDependencyClosure']['present']['test:test-pandoc']['defaultLanguage']);
        $t->same('Haskell2010', $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['defaultLanguage']);
        $t->same(null, $audit['runnerDependencyClosure']['present']['test:test-pandoc']['manual']);
        $t->same(null, $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['manual']);
        $t->same([], $audit['runnerDependencyClosure']['present']['test:test-pandoc']['defaultExtensions']);
        $t->same([], $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['defaultExtensions']);
        $t->same([], $audit['runnerDependencyClosure']['present']['test:test-pandoc']['otherExtensions']);
        $t->same([], $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['otherExtensions']);
        $t->same([], $audit['runnerDependencyClosure']['present']['test:test-pandoc']['autogenModules']);
        $t->same([], $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['autogenModules']);
        $t->same([], $audit['runnerDependencyClosure']['present']['test:test-pandoc']['extraSourceFiles']);
        $t->same([], $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['extraSourceFiles']);
        $t->same([], $audit['runnerDependencyClosure']['present']['test:test-pandoc']['extraTmpFiles']);
        $t->same([], $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['extraTmpFiles']);
        $t->same([], $audit['runnerDependencyClosure']['present']['test:test-pandoc']['dataFiles']);
        $t->same([], $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['dataFiles']);
        $t->same([], $audit['runnerDependencyClosure']['present']['test:test-pandoc']['testOptions']);
        $t->same([], $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['testOptions']);
        $t->same([], $audit['runnerDependencyClosure']['present']['test:test-pandoc']['conditionalBranches']);
        $t->same([], $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['conditionalBranches']);
        $t->same(UpstreamRunnerDependencyAudit::expectedRunnerCommonImports()['test:test-pandoc'], $audit['runnerDependencyClosure']['present']['test:test-pandoc']['commonImports']);
        $t->same(UpstreamRunnerDependencyAudit::expectedRunnerCommonImports()['test:test-pandoc-lua-engine'], $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['commonImports']);
        $t->same([], $audit['runnerDependencyClosure']['present']['test:test-pandoc']['unresolvedCommonImports']);
        $t->same([], $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['unresolvedCommonImports']);
        $t->same([], $audit['runnerDependencyClosure']['present']['test:test-pandoc']['nativeSystemFields']);
        $t->same([], $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['nativeSystemFields']);
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
        $t->same(array_keys(UpstreamRunnerDependencyAudit::expectedFormatRegistrySourceArtifacts()), $audit['formatRegistrySourceClosure']['present']);
        $t->same([], $audit['formatRegistrySourceClosure']['missing']);
        $t->same([], $audit['formatRegistrySourceClosure']['wrongType']);
        $t->same([], $audit['formatRegistrySourceClosure']['emptyFiles']);
        $t->same([], $audit['formatRegistrySourceClosure']['missingSemantics']);
        $t->same(
            array_keys(UpstreamRunnerDependencyAudit::expectedFormatRegistrySourceSemantics()['src/Text/Pandoc/Readers.hs']),
            $audit['formatRegistrySourceClosure']['presentSemantics']['src/Text/Pandoc/Readers.hs']
        );
        $t->same(
            array_keys(UpstreamRunnerDependencyAudit::expectedFormatRegistrySourceSemantics()['src/Text/Pandoc/Writers.hs']),
            $audit['formatRegistrySourceClosure']['presentSemantics']['src/Text/Pandoc/Writers.hs']
        );
        $t->same(
            array_keys(UpstreamRunnerDependencyAudit::expectedFormatRegistrySourceSemantics()['src/Text/Pandoc/Format.hs']),
            $audit['formatRegistrySourceClosure']['presentSemantics']['src/Text/Pandoc/Format.hs']
        );
        $t->same(UpstreamRunnerDependencyAudit::expectedBenchmarkCommonImports()['benchmark:benchmark-pandoc'], $audit['benchmarkDependencyClosure']['present']['benchmark:benchmark-pandoc']['commonImports']);
        $t->same([], $audit['benchmarkDependencyClosure']['present']['benchmark:benchmark-pandoc']['unresolvedCommonImports']);
        $t->same(null, $audit['benchmarkDependencyClosure']['present']['benchmark:benchmark-pandoc']['manual']);
        $t->same([], $audit['benchmarkDependencyClosure']['present']['benchmark:benchmark-pandoc']['benchmarkOptions']);
        $t->same([], $audit['benchmarkDependencyClosure']['present']['benchmark:benchmark-pandoc']['conditionalBranches']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedDependencies']);
        $t->contains('non-mutating solver/build plan', $audit['activationGate']);
        $t->contains('record Cabal package identity/version headers', $audit['nonMutatingPlan'][0]);
        $t->contains('package flag definitions plus default/manual values for cabal.project flags', $audit['nonMutatingPlan'][0]);
        $t->contains('exact package-level source-repository head closure', $audit['nonMutatingPlan'][0]);
        $t->contains('exact package-level extra-doc-files and extra-source-files closure', $audit['nonMutatingPlan'][0]);
        $t->contains('no unexpected package-level extra-tmp-files or native/system dependency fields', $audit['nonMutatingPlan'][0]);
        $t->contains('pandoc.cabal tested-with GHC matrix', $audit['nonMutatingPlan'][0]);
        $t->contains('cabal.project package/flag closure', $audit['nonMutatingPlan'][0]);
        $t->contains('runner entry-point semantics', $audit['nonMutatingPlan'][0]);
        $t->contains('solver constraints and runner executable options', $audit['nonMutatingPlan'][1]);
        $t->contains('test-suite type, buildable state, default-language, absent manual field, common import closure, entry point, direct build-depends with pinned version constraints, exact executable options, no unexpected Cabal custom-setup/setup-depends, no unexpected common imports, unresolved common imports, direct build-depends, hs-source-dirs, mixins, build-tool dependencies, default-extensions, other-extensions, cpp-options, autogen-modules, reexported-modules, module interface fields, extra-source-files, extra-doc-files, extra-tmp-files, data-files, or conditional branches, and exact other-modules closure', $audit['nonMutatingPlan'][2]);
        $t->contains('pandoc-lua-engine library HsLua module dependency closure', $audit['nonMutatingPlan'][2]);
        $t->contains('exact library exposed-modules closure', $audit['nonMutatingPlan'][2]);
        $t->contains('exact library source directory and other-modules closure', $audit['nonMutatingPlan'][2]);
        $t->contains('library source artifact hashes', $audit['nonMutatingPlan'][2]);
        $t->contains('Haskell2010 library default-language', $audit['nonMutatingPlan'][2]);
        $t->contains('pandoc-server library direct dependency', $audit['nonMutatingPlan'][2]);
        $t->contains('pandoc-cli executable entry point', $audit['nonMutatingPlan'][2]);
        $t->contains('benchmark:benchmark-pandoc type, buildable state, default-language, absent manual field, common import closure, entry point, direct build-depends with pinned version constraints, exact executable options, no unexpected Cabal benchmark common imports, unresolved common imports, direct build-depends, hs-source-dirs, mixins, build-tool dependencies, default-extensions, other-extensions, cpp-options, autogen-modules, reexported-modules, module interface fields, other-modules, extra-source-files, extra-doc-files, extra-tmp-files, data-files, or conditional branches', $audit['nonMutatingPlan'][3]);
        $t->contains('entry-source semantics before any benchmark execution', $audit['nonMutatingPlan'][3]);
    },

    'records descriptor only cabal dry-run command envelope before planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $root = $makeTree($requiredFiles($pinnedProject()));
        try {
            $audit = UpstreamRunnerDependencyAudit::auditCheckout($root, [
                'ghc' => ['available' => true, 'version' => '9.10.3'],
                'cabal' => ['available' => true, 'version' => '3.12.1.0'],
            ]);
        } finally {
            $removeTree($root);
        }

        $commands = UpstreamRunnerDependencyAudit::expectedCabalPlanCommands();
        $t->same($commands, $audit['cabalPlanCommands']);
        $t->same([
            'runner-test-dependencies',
            'benchmark-dependencies',
        ], array_keys($commands));
        $t->same('descriptor-only; do not execute from this isolated PHP lane', $commands['runner-test-dependencies']['executionPolicy']);
        $t->same('.port-libs/pandoc-runner/cabal-build/runner-test-dependencies', $commands['runner-test-dependencies']['buildDirectory']);
        $t->same('.port-libs/pandoc-runner/cabal-build/benchmark-dependencies', $commands['benchmark-dependencies']['buildDirectory']);
        $t->same([
            'v2-build',
            '--offline',
            '--project-dir=.',
            '--builddir=.port-libs/pandoc-runner/cabal-build/runner-test-dependencies',
            '--dry-run',
            '--only-dependencies',
            '--enable-tests',
            '--disable-benchmarks',
            'test:test-pandoc',
            'test:test-pandoc-lua-engine',
        ], $commands['runner-test-dependencies']['arguments']);
        $t->same([
            'v2-build',
            '--offline',
            '--project-dir=.',
            '--builddir=.port-libs/pandoc-runner/cabal-build/benchmark-dependencies',
            '--dry-run',
            '--only-dependencies',
            '--disable-tests',
            '--enable-benchmarks',
            'benchmark:benchmark-pandoc',
        ], $commands['benchmark-dependencies']['arguments']);
        $t->same(['test:test-pandoc', 'test:test-pandoc-lua-engine'], $commands['runner-test-dependencies']['targets']);
        $t->same(['benchmark:benchmark-pandoc'], $commands['benchmark-dependencies']['targets']);
        $t->contains('exact Cabal dry-run command descriptors', $audit['nonMutatingPlan'][5]);
        $t->contains('runner-test-dependencies', $audit['activationGate']);
        $t->contains('benchmark-dependencies', $audit['activationGate']);
    },

    'records local cabal dry-run workspace before any environment can be used' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $root = $makeTree($requiredFiles($pinnedProject()));
        try {
            $audit = UpstreamRunnerDependencyAudit::auditCheckout($root, [
                'ghc' => ['available' => true, 'version' => '9.10.3'],
                'cabal' => ['available' => true, 'version' => '3.12.1.0'],
            ]);
        } finally {
            $removeTree($root);
        }

        $workspace = UpstreamRunnerDependencyAudit::expectedCabalPlanWorkspace();
        $t->same($workspace, $audit['cabalPlanWorkspace']);
        $t->same('descriptor-only; do not read or print process environment values from this PHP lane', $workspace['environmentPolicy']);
        $t->same([
            'CABAL_DIR',
            'CABAL_CONFIG',
            'XDG_CACHE_HOME',
            'XDG_STATE_HOME',
            'TMPDIR',
        ], array_keys($workspace['environmentVariables']));
        $t->same('.port-libs/pandoc-runner/cabal', $workspace['environmentVariables']['CABAL_DIR']);
        $t->same('.port-libs/pandoc-runner/cabal/config', $workspace['environmentVariables']['CABAL_CONFIG']);
        $t->same('.port-libs/pandoc-runner/cache', $workspace['environmentVariables']['XDG_CACHE_HOME']);
        $t->same('.port-libs/pandoc-runner/state', $workspace['environmentVariables']['XDG_STATE_HOME']);
        $t->same('.port-libs/pandoc-runner/tmp', $workspace['environmentVariables']['TMPDIR']);
        $t->same([
            'runner-test-dependencies' => '.port-libs/pandoc-runner/cabal-build/runner-test-dependencies',
            'benchmark-dependencies' => '.port-libs/pandoc-runner/cabal-build/benchmark-dependencies',
        ], $workspace['buildDirectories']);
        $t->same([
            'runner-test-dependencies' => '.port-libs/pandoc-runner/logs/runner-test-dependencies.txt',
            'benchmark-dependencies' => '.port-libs/pandoc-runner/logs/benchmark-dependencies.txt',
        ], $workspace['transcriptFiles']);
        $t->contains('no HOME-scoped Cabal store', implode("\n", $workspace['preflight']));
        $t->contains('do not print process environment', implode("\n", $workspace['preflight']));
        $t->contains('local Cabal dry-run workspace', $audit['nonMutatingPlan'][6]);
        $t->contains('repo-local dry-run workspace', $audit['activationGate']);
    },

    'records cabal dry-run descriptor closure before any workspace is created' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $root = $makeTree($requiredFiles($pinnedProject()));
        try {
            $audit = UpstreamRunnerDependencyAudit::auditCheckout($root, [
                'ghc' => ['available' => true, 'version' => '9.10.3'],
                'cabal' => ['available' => true, 'version' => '3.12.1.0'],
            ]);
        } finally {
            $removeTree($root);
        }

        $closure = $audit['cabalPlanDescriptorClosure'];
        $t->same([
            'runner-test-dependencies',
            'benchmark-dependencies',
        ], $closure['expectedCommands']);
        $t->same([
            'benchmark-dependencies',
            'runner-test-dependencies',
        ], $closure['presentCommands']);
        $t->same([], $closure['missingCommands']);
        $t->same([], $closure['unexpectedCommands']);
        $t->same([], $closure['commandPolicyViolations']);
        $t->same([], $closure['workspacePolicyViolations']);
        $t->same([], $closure['commandWorkspaceMismatches']);

        $commands = UpstreamRunnerDependencyAudit::expectedCabalPlanCommands();
        $workspace = UpstreamRunnerDependencyAudit::expectedCabalPlanWorkspace();
        unset($commands['benchmark-dependencies']);
        $commands['runner-test-dependencies']['buildDirectory'] = 'dist-newstyle';
        $commands['runner-test-dependencies']['arguments'][3] = '--builddir=dist-newstyle';
        $commands['home-runner'] = $commands['runner-test-dependencies'];
        $commands['home-runner']['buildDirectory'] = '/home/claude/.cabal/store';
        $commands['home-runner']['arguments'] = [
            'v2-build',
            '--offline',
            '--dry-run',
            '--only-dependencies',
            '--builddir=/home/claude/.cabal/store',
            'test:test-pandoc',
        ];
        $workspace['environmentPolicy'] = 'print process environment';
        $workspace['environmentVariables']['CABAL_DIR'] = '/home/claude/.cabal';
        $workspace['environmentVariables']['TMPDIR'] = '../tmp';
        $workspace['buildDirectories']['runner-test-dependencies'] = '.port-libs/pandoc-runner/cabal-build/other';
        $workspace['transcriptFiles']['benchmark-dependencies'] = '~/pandoc-runner.log';
        $workspace['optionalPlanJsonFiles']['benchmark-dependencies'] = 'dist-newstyle/cache/plan.json';

        $drift = UpstreamRunnerDependencyAudit::auditCabalPlanDescriptorClosure($commands, $workspace);
        $t->same(['benchmark-dependencies'], $drift['missingCommands']);
        $t->same(['home-runner'], $drift['unexpectedCommands']);
        $commandPolicy = implode("\n", $drift['commandPolicyViolations']);
        $workspacePolicy = implode("\n", $drift['workspacePolicyViolations']);
        $workspaceMismatches = implode("\n", $drift['commandWorkspaceMismatches']);
        $t->contains('runner-test-dependencies buildDirectory must not use Cabal default dist-newstyle paths: dist-newstyle', $commandPolicy);
        $t->contains('runner-test-dependencies argument must not use Cabal default dist-newstyle paths: --builddir=dist-newstyle', $commandPolicy);
        $t->contains('environmentPolicy must forbid live process environment output', $workspacePolicy);
        $t->contains('environmentVariables.CABAL_DIR must be relative, not absolute: /home/claude/.cabal', $workspacePolicy);
        $t->contains('environmentVariables.TMPDIR must not contain parent-directory traversal: ../tmp', $workspacePolicy);
        $t->contains('transcriptFiles.benchmark-dependencies must not be home-scoped: ~/pandoc-runner.log', $workspacePolicy);
        $t->contains('optionalPlanJsonFiles.benchmark-dependencies must not use Cabal default dist-newstyle paths: dist-newstyle/cache/plan.json', $workspacePolicy);
        $t->contains('runner-test-dependencies command buildDirectory does not match workspace build directory', $workspaceMismatches);
        $t->contains('runner-test-dependencies --builddir argument does not match workspace build directory', $workspaceMismatches);
        $t->contains('validated repo-local environment variable paths', $audit['nonMutatingPlan'][6]);
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
            'pandoc-server/pandoc-server.cabal',
            'pandoc-cli/pandoc-cli.cabal',
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
    'blocks cabal flag default and manual field drift before solver planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['pandoc.cabal'] = str_replace(
            implode("\n", [
                'flag embed_data_files',
                '  description: Embed data files in the built executable',
                '  default: False',
                '  manual: True',
            ]),
            implode("\n", [
                'flag embed_data_files',
                '  description: Embed data files in the built executable',
                '  default: True',
            ]),
            $files['pandoc.cabal']
        );
        $files['pandoc-cli/pandoc-cli.cabal'] = str_replace(
            implode("\n", [
                'flag lua',
                '  description: Support custom modifications and conversions with the pandoc Lua scripting engine.',
                '  default: True',
            ]),
            implode("\n", [
                'flag lua',
                '  description: Support custom modifications and conversions with the pandoc Lua scripting engine.',
                '  default: True',
                '  manual: True',
            ]),
            $files['pandoc-cli/pandoc-cli.cabal']
        );
        $files['pandoc-cli/pandoc-cli.cabal'] = str_replace(
            implode("\n", [
                'flag nightly',
                '  description: Add nightly suffix to version output.',
                '  default: False',
            ]),
            implode("\n", [
                'flag nightly',
                '  description: Add nightly suffix to version output.',
                '  default: True',
            ]),
            $files['pandoc-cli/pandoc-cli.cabal']
        );

        $root = $makeTree($files);
        try {
            $audit = UpstreamRunnerDependencyAudit::auditCheckout($root, [
                'ghc' => ['available' => true, 'version' => '9.10.3'],
                'cabal' => ['available' => true, 'version' => '3.12.1.0'],
            ]);
        } finally {
            $removeTree($root);
        }

        $t->same(false, $audit['readyForNonMutatingCabalPlan']);
        $t->same([], $audit['packageFlagDefinitionClosure']['missingFlags']);
        $t->same([
            'embed_data_files' => [
                'default' => 'True',
                'manual' => null,
            ],
            'http' => [
                'default' => 'True',
                'manual' => 'True',
            ],
        ], $audit['packageFlagDefinitionClosure']['presentFlagFields']['pandoc.cabal']);
        $t->same([
            'pandoc.cabal' => [
                'embed_data_files' => [
                    'default' => [
                        'expected' => 'False',
                        'actual' => 'True',
                    ],
                    'manual' => [
                        'expected' => 'True',
                        'actual' => null,
                    ],
                ],
            ],
            'pandoc-cli/pandoc-cli.cabal' => [
                'lua' => [
                    'manual' => [
                        'expected' => null,
                        'actual' => 'True',
                    ],
                ],
                'nightly' => [
                    'default' => [
                        'expected' => 'False',
                        'actual' => 'True',
                    ],
                ],
            ],
        ], $audit['packageFlagDefinitionClosure']['mismatchedFlagFields']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('mismatched Cabal package flag fields', $blocked);
        $t->contains('pandoc.cabal (embed_data_files.default expected False, found True, embed_data_files.manual expected True, found absent)', $blocked);
        $t->contains('pandoc-cli/pandoc-cli.cabal (lua.manual expected absent, found True, nightly.default expected False, found True)', $blocked);
        $t->contains('package flag definitions plus default/manual values', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks unexpected cabal package flag definitions before solver planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['pandoc.cabal'] = str_replace(
            "flag http\n",
            implode("\n", [
                'flag runner_audit_network',
                '  description: Enable generated runner network fixtures',
                '  default: False',
                '  manual: True',
                '',
                'flag http',
            ]) . "\n",
            $files['pandoc.cabal']
        );
        $files['pandoc-lua-engine/pandoc-lua-engine.cabal'] .= implode("\n", [
            '',
            'flag generated_lua_runner',
            '  description: Enable generated Lua runner fixtures',
            '  default: False',
        ]);
        $files['pandoc-cli/pandoc-cli.cabal'] = str_replace(
            "flag nightly\n",
            implode("\n", [
                'flag generated_binary_wrapper',
                '  description: Enable generated binary wrapper tests',
                '  default: False',
                '',
                'flag nightly',
            ]) . "\n",
            $files['pandoc-cli/pandoc-cli.cabal']
        );

        $root = $makeTree($files);
        try {
            $audit = UpstreamRunnerDependencyAudit::auditCheckout($root, [
                'ghc' => ['available' => true, 'version' => '9.10.3'],
                'cabal' => ['available' => true, 'version' => '3.12.1.0'],
            ]);
        } finally {
            $removeTree($root);
        }

        $t->same(false, $audit['readyForNonMutatingCabalPlan']);
        $t->same([], $audit['packageFlagDefinitionClosure']['missingFlags']);
        $t->same([], $audit['packageFlagDefinitionClosure']['mismatchedFlagFields']);
        $t->same([
            'embed_data_files',
            'http',
            'runner_audit_network',
        ], $audit['packageFlagDefinitionClosure']['presentFlags']['pandoc.cabal']);
        $t->same([
            'generated_lua_runner',
        ], $audit['packageFlagDefinitionClosure']['presentFlags']['pandoc-lua-engine/pandoc-lua-engine.cabal']);
        $t->same([
            'generated_binary_wrapper',
            'lua',
            'nightly',
            'repl',
            'server',
        ], $audit['packageFlagDefinitionClosure']['presentFlags']['pandoc-cli/pandoc-cli.cabal']);
        $t->same([
            'pandoc.cabal' => ['runner_audit_network'],
            'pandoc-lua-engine/pandoc-lua-engine.cabal' => ['generated_lua_runner'],
            'pandoc-cli/pandoc-cli.cabal' => ['generated_binary_wrapper'],
        ], $audit['packageFlagDefinitionClosure']['unexpectedFlags']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('unexpected Cabal package flag definitions: pandoc.cabal (runner_audit_network)', $blocked);
        $t->contains('pandoc-lua-engine/pandoc-lua-engine.cabal (generated_lua_runner)', $blocked);
        $t->contains('pandoc-cli/pandoc-cli.cabal (generated_binary_wrapper)', $blocked);
        $t->contains('no unexpected Cabal package flag definitions', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'records cabal plan stability provenance before solver planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $root = $makeTree($files);
        try {
            $unpinned = UpstreamRunnerDependencyAudit::auditCheckout($root, [
                'ghc' => ['available' => true, 'version' => '9.10.3'],
                'cabal' => ['available' => true, 'version' => '3.12.1.0'],
            ]);
        } finally {
            $removeTree($root);
        }

        $t->same(true, $unpinned['readyForNonMutatingCabalPlan']);
        $t->same(UpstreamRunnerDependencyAudit::expectedStablePlanFiles(), $unpinned['planStabilityClosure']['expectedStablePlanFiles']);
        $t->same(['cabal.project.freeze'], $unpinned['planStabilityClosure']['missing']);
        $t->same(true, $unpinned['planStabilityClosure']['unpinnedPlanRisk']);
        $t->same(hash('sha256', $files['cabal.project']), $unpinned['planStabilityClosure']['present']['cabal.project']['sha256']);
        $t->same(strlen($files['cabal.project']), $unpinned['planStabilityClosure']['present']['cabal.project']['bytes']);
        $t->contains('cabal.project.freeze absence', $unpinned['activationGate']);
        $t->contains('capture stable Cabal plan file provenance', $unpinned['nonMutatingPlan'][4]);

        $freeze = "constraints: base ==4.20.0.0,\n  pandoc ==3.9.0.2\n";
        $files['cabal.project.freeze'] = $freeze;
        $root = $makeTree($files);
        try {
            $pinned = UpstreamRunnerDependencyAudit::auditCheckout($root, [
                'ghc' => ['available' => true, 'version' => '9.10.3'],
                'cabal' => ['available' => true, 'version' => '3.12.1.0'],
            ]);
        } finally {
            $removeTree($root);
        }

        $t->same(true, $pinned['readyForNonMutatingCabalPlan']);
        $t->same([], $pinned['planStabilityClosure']['missing']);
        $t->same([], $pinned['planStabilityClosure']['wrongType']);
        $t->same([], $pinned['planStabilityClosure']['emptyFiles']);
        $t->same(false, $pinned['planStabilityClosure']['unpinnedPlanRisk']);
        $t->same(hash('sha256', $freeze), $pinned['planStabilityClosure']['present']['cabal.project.freeze']['sha256']);
        $t->same(strlen($freeze), $pinned['planStabilityClosure']['present']['cabal.project.freeze']['bytes']);
        $t->contains('stable cabal.project and cabal.project.freeze provenance', $pinned['activationGate']);
    },
    'records invalid cabal project freeze contents as unpinned plan risk before solver planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $freeze = implode("\n", [
            '-- stale placeholder produced before dependency resolution',
            'packages: . pandoc-cli',
            'constraints:',
            '  -- no pinned package versions',
            '',
        ]);
        $files['cabal.project.freeze'] = $freeze;

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
        $t->same([], $audit['planStabilityClosure']['missing']);
        $t->same([], $audit['planStabilityClosure']['wrongType']);
        $t->same([], $audit['planStabilityClosure']['emptyFiles']);
        $t->same([
            'cabal.project.freeze' => 'missing pinned constraints',
        ], $audit['planStabilityClosure']['invalidFiles']);
        $t->same(true, $audit['planStabilityClosure']['unpinnedPlanRisk']);
        $t->same(hash('sha256', $freeze), $audit['planStabilityClosure']['present']['cabal.project.freeze']['sha256']);
        $t->same(strlen($freeze), $audit['planStabilityClosure']['present']['cabal.project.freeze']['bytes']);
        $t->contains('cabal.project.freeze absence or invalidity', $audit['activationGate']);
        $t->contains('capture stable Cabal plan file provenance', $audit['nonMutatingPlan'][4]);
    },
    'records runner and benchmark artifact provenance before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
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
        $t->same([], $audit['runnerArtifactClosure']['missing']);
        $t->same([], $audit['runnerArtifactClosure']['wrongType']);
        $t->same([], $audit['runnerArtifactClosure']['emptyFiles']);
        $t->same([], $audit['benchmarkArtifactClosure']['missing']);
        $t->same([], $audit['benchmarkArtifactClosure']['wrongType']);
        $t->same([], $audit['benchmarkArtifactClosure']['emptyFiles']);
        $t->same(UpstreamRunnerDependencyAudit::expectedBenchmarkArtifactSemantics(), $audit['benchmarkArtifactClosure']['expectedSemantics']);
        $t->same([], $audit['benchmarkArtifactClosure']['missingSemantics']);

        foreach (UpstreamRunnerDependencyAudit::expectedRunnerArtifacts() as $relativePath => $kind) {
            if ($kind !== 'file') {
                $t->same(false, array_key_exists($relativePath, $audit['runnerArtifactClosure']['fileProvenance']));
                continue;
            }

            $t->same(hash('sha256', $files[$relativePath]), $audit['runnerArtifactClosure']['fileProvenance'][$relativePath]['sha256']);
            $t->same(strlen($files[$relativePath]), $audit['runnerArtifactClosure']['fileProvenance'][$relativePath]['bytes']);
        }

        foreach (UpstreamRunnerDependencyAudit::expectedBenchmarkArtifacts() as $relativePath => $kind) {
            if ($kind !== 'file') {
                $t->same(false, array_key_exists($relativePath, $audit['benchmarkArtifactClosure']['fileProvenance']));
                continue;
            }

            $t->same(hash('sha256', $files[$relativePath]), $audit['benchmarkArtifactClosure']['fileProvenance'][$relativePath]['sha256']);
            $t->same(strlen($files[$relativePath]), $audit['benchmarkArtifactClosure']['fileProvenance'][$relativePath]['bytes']);
        }

        $t->contains('runner source/golden artifact hashes', $audit['nonMutatingPlan'][0]);
        $t->contains('benchmark source/data artifact hashes', $audit['nonMutatingPlan'][3]);
        $t->contains('benchmark fixture semantics', $audit['nonMutatingPlan'][3]);
        $t->contains('non-empty runner source/golden fixtures with artifact hashes', $audit['activationGate']);
        $t->contains('non-empty benchmark component dependency/artifact closure with artifact hashes, benchmark fixture semantics', $audit['activationGate']);
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
    'blocks unexpected cabal project source repository fields before planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $project = str_replace(
            implode("\n", [
                '  location: https://github.com/jgm/texmath.git',
                '  tag: 0a3fbebc5d0e21769f01b048eb63e1451ccf0e1a',
            ]),
            implode("\n", [
                '  location: https://github.com/jgm/texmath.git',
                '  tag: 0a3fbebc5d0e21769f01b048eb63e1451ccf0e1a',
                '  branch: runner-audit',
                '  subdir: texmath-core',
                '  post-checkout-command: sh ./configure-runner-audit',
            ]),
            $pinnedProject()
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
        $t->same([], $audit['projectSourceRepositoryPins']['missing']);
        $t->same([], $audit['projectSourceRepositoryPins']['mismatched']);
        $t->same([], $audit['projectSourceRepositoryClosure']['missing']);
        $t->same([], $audit['projectSourceRepositoryClosure']['mismatched']);
        $t->same([], $audit['projectSourceRepositoryClosure']['unexpected']);
        $t->same([], $audit['projectPackageClosure']['missingPackages']);
        $t->same([], $audit['projectConstraintClosure']['missingConstraints']);
        $t->same([
            'branch: runner-audit',
            'post-checkout-command: sh ./configure-runner-audit',
            'subdir: texmath-core',
        ], $audit['projectSourceRepositoryClosure']['unexpectedFields']['texmath']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('unexpected cabal.project source-repository package fields: texmath (branch: runner-audit, post-checkout-command: sh ./configure-runner-audit, subdir: texmath-core)', $blocked);
        $t->contains('no unexpected cabal.project source-repository package fields', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks unexpected cabal project package repository flag and constraint drift before planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $project = str_replace(
            'packages: . pandoc-lua-engine pandoc-server pandoc-cli',
            'packages: . pandoc-lua-engine pandoc-server pandoc-cli pandoc-runner-extra',
            $pinnedProject()
        );
        $project = str_replace(
            'constraints: skylighting-format-blaze-html >= 0.1.2, skylighting-format-context >= 0.1.0.2, auto-update >= 0.2.6, crypton >= 1.1.1',
            'constraints: skylighting-format-blaze-html >= 0.1.2, skylighting-format-context >= 0.1.0.2, auto-update >= 0.2.6, crypton >= 1.1.1, lens >= 5.2',
            $project
        );
        $project = str_replace(
            '  flags: +embed_data_files +http',
            '  flags: +embed_data_files +http +runner_audit',
            $project
        );
        $project .= implode("\n", [
            '',
            'source-repository-package',
            '  type: git',
            '  location: https://github.com/jgm/pandoc-runner-tools.git',
            '  tag: 1111111111111111111111111111111111111111',
            '',
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
        $t->same([], $audit['projectSourceRepositoryPins']['missing']);
        $t->same([], $audit['projectSourceRepositoryPins']['mismatched']);
        $t->same([], $audit['projectSourceRepositoryClosure']['missing']);
        $t->same([], $audit['projectSourceRepositoryClosure']['mismatched']);
        $t->same([], $audit['projectPackageClosure']['missingPackages']);
        $t->same([], $audit['projectPackageClosure']['missingFlags']);
        $t->same([], $audit['projectPackageClosure']['mismatchedFlags']);
        $t->same([], $audit['projectConstraintClosure']['missingConstraints']);
        $t->same([], $audit['projectConstraintClosure']['mismatchedConstraints']);
        $t->same(['pandoc-runner-tools'], $audit['projectSourceRepositoryClosure']['unexpected'] ?? ['missing-key']);
        $t->same(['pandoc-runner-extra'], $audit['projectPackageClosure']['unexpectedPackages'] ?? ['missing-key']);
        $t->same(['runner_audit'], $audit['projectPackageClosure']['unexpectedFlags']['pandoc'] ?? ['missing-key']);
        $t->same(['lens'], $audit['projectConstraintClosure']['unexpectedConstraints'] ?? ['missing-key']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('unexpected cabal.project source-repository packages: pandoc-runner-tools', $blocked);
        $t->contains('unexpected cabal.project package entries: pandoc-runner-extra', $blocked);
        $t->contains('unexpected cabal.project package flags: pandoc (runner_audit)', $blocked);
        $t->contains('unexpected cabal.project solver constraints: lens', $blocked);
        $t->contains('no unexpected cabal.project source-repository packages', $audit['activationGate']);
        $t->contains('no unexpected cabal.project package entries or flags', $audit['activationGate']);
        $t->contains('no unexpected cabal.project solver constraints', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks unexpected cabal project package stanza fields before planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $project = str_replace(
            '  flags: +embed_data_files +http',
            implode("\n", [
                '  flags: +embed_data_files +http',
                '  constraints: pandoc == 3.9.0.2',
                '  ghc-options: -O0 -fplugin=RunnerAudit',
                '  tests: False',
            ]),
            $pinnedProject()
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
        $t->same([], $audit['missingFiles']);
        $t->same([], $audit['missingTools']);
        $t->same([], $audit['projectPackageClosure']['missingPackages']);
        $t->same([], $audit['projectPackageClosure']['unexpectedPackages']);
        $t->same([], $audit['projectPackageClosure']['missingFlags']);
        $t->same([], $audit['projectPackageClosure']['mismatchedFlags']);
        $t->same([], $audit['projectPackageClosure']['unexpectedFlags']);
        $t->same(['flags'], $audit['projectPackageClosure']['expectedPackageFields']['pandoc']);
        $t->same([
            'constraints' => 'pandoc == 3.9.0.2',
            'flags' => '+embed_data_files +http',
            'ghc-options' => '-O0 -fplugin=RunnerAudit',
            'tests' => 'False',
        ], $audit['projectPackageClosure']['presentPackageFields']['pandoc']);
        $t->same([
            'constraints: pandoc == 3.9.0.2',
            'ghc-options: -O0 -fplugin=RunnerAudit',
            'tests: False',
        ], $audit['projectPackageClosure']['unexpectedPackageFields']['pandoc']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('unexpected cabal.project package stanza fields: pandoc (constraints: pandoc == 3.9.0.2, ghc-options: -O0 -fplugin=RunnerAudit, tests: False)', $blocked);
        $t->contains('no unexpected cabal.project package stanza fields', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks unexpected cabal project unconditional plan fields before planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $project = $pinnedProject() . "\n" . implode("\n", [
            'allow-newer: all:base, all:text',
            'tests: False',
            'benchmarks: True',
            'with-compiler: ghc-9.12.2',
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
        $t->same([], $audit['projectSourceRepositoryPins']['missing']);
        $t->same([], $audit['projectSourceRepositoryPins']['mismatched']);
        $t->same([], $audit['projectSourceRepositoryClosure']['missing']);
        $t->same([], $audit['projectSourceRepositoryClosure']['mismatched']);
        $t->same([], $audit['projectPackageClosure']['missingPackages']);
        $t->same([], $audit['projectPackageClosure']['missingFlags']);
        $t->same([], $audit['projectPackageClosure']['mismatchedFlags']);
        $t->same([], $audit['projectPackageClosure']['unexpectedFlags']);
        $t->same([], $audit['projectConstraintClosure']['missingConstraints']);
        $t->same([], $audit['projectConstraintClosure']['mismatchedConstraints']);
        $t->same([], $audit['projectConstraintClosure']['unexpectedConstraints']);
        $t->same([
            'allow-newer',
            'benchmarks',
            'tests',
            'with-compiler',
        ], $audit['projectUnconditionalFieldClosure']['unexpectedFields']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('unexpected cabal.project unconditional plan fields: allow-newer, benchmarks, tests, with-compiler', $blocked);
        $t->contains('no unexpected cabal.project unconditional plan fields', $audit['activationGate']);
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
    'blocks cabal package identity drift before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['pandoc.cabal'] = str_replace('name: pandoc', 'name: pandoc-core', $files['pandoc.cabal']);
        $files['pandoc.cabal'] = str_replace('version: 3.9.0.2', 'version: 3.9.0.1', $files['pandoc.cabal']);
        $files['pandoc.cabal'] = str_replace('build-type: Simple', 'build-type: Custom', $files['pandoc.cabal']);
        $files['pandoc-lua-engine/pandoc-lua-engine.cabal'] = str_replace("cabal-version: 2.4\n", '', $files['pandoc-lua-engine/pandoc-lua-engine.cabal']);
        $files['pandoc-lua-engine/pandoc-lua-engine.cabal'] = str_replace('version: 0.5.2', 'version: 0.5.1', $files['pandoc-lua-engine/pandoc-lua-engine.cabal']);

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
        $t->same([], $audit['compilerTestedWithClosure']['missingGhcVersions']);
        $t->same(['cabalVersion'], $audit['packageIdentityClosure']['missingHeaders']['pandoc-lua-engine/pandoc-lua-engine.cabal']);
        $t->same([
            'expected' => 'pandoc',
            'actual' => 'pandoc-core',
        ], $audit['packageIdentityClosure']['mismatchedHeaders']['pandoc.cabal']['name']);
        $t->same([
            'expected' => '3.9.0.2',
            'actual' => '3.9.0.1',
        ], $audit['packageIdentityClosure']['mismatchedHeaders']['pandoc.cabal']['version']);
        $t->same([
            'expected' => 'Simple',
            'actual' => 'Custom',
        ], $audit['packageIdentityClosure']['mismatchedHeaders']['pandoc.cabal']['buildType']);
        $t->same([
            'expected' => '0.5.2',
            'actual' => '0.5.1',
        ], $audit['packageIdentityClosure']['mismatchedHeaders']['pandoc-lua-engine/pandoc-lua-engine.cabal']['version']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('missing Cabal package identity headers: pandoc-lua-engine/pandoc-lua-engine.cabal (cabalVersion)', $blocked);
        $t->contains('mismatched Cabal package identity headers: pandoc.cabal (name expected pandoc, found pandoc-core, version expected 3.9.0.2, found 3.9.0.1, buildType expected Simple, found Custom); pandoc-lua-engine/pandoc-lua-engine.cabal (version expected 0.5.2, found 0.5.1)', $blocked);
        $t->contains('Cabal package identity/version headers', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks workspace package identity and cli flag drift before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['pandoc-server/pandoc-server.cabal'] = str_replace('version: 0.1.2', 'version: 0.1.1', $files['pandoc-server/pandoc-server.cabal']);
        $files['pandoc-cli/pandoc-cli.cabal'] = str_replace('name: pandoc-cli', 'name: pandoc-runner-cli', $files['pandoc-cli/pandoc-cli.cabal']);
        $files['pandoc-cli/pandoc-cli.cabal'] = str_replace(implode("\n", [
            'flag server',
            '  description: Include support for running pandoc as an HTTP server.',
            '  default: True',
            '',
        ]), '', $files['pandoc-cli/pandoc-cli.cabal']);
        $files['pandoc-cli/pandoc-cli.cabal'] = str_replace(implode("\n", [
            'flag nightly',
            '  description: Add nightly suffix to version output.',
            '  default: False',
            '',
        ]), '', $files['pandoc-cli/pandoc-cli.cabal']);

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
        $t->same([], $audit['projectPackageClosure']['missingPackages']);
        $t->same([], $audit['runnerDependencyClosure']['missingTargets']);
        $t->same([], $audit['runnerDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['runnerDependencyClosure']['missingDependencies']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingTargets']);
        $t->same([
            'expected' => '0.1.2',
            'actual' => '0.1.1',
        ], $audit['packageIdentityClosure']['mismatchedHeaders']['pandoc-server/pandoc-server.cabal']['version']);
        $t->same([
            'expected' => 'pandoc-cli',
            'actual' => 'pandoc-runner-cli',
        ], $audit['packageIdentityClosure']['mismatchedHeaders']['pandoc-cli/pandoc-cli.cabal']['name']);
        $t->same([
            'lua',
            'repl',
        ], $audit['packageFlagDefinitionClosure']['presentFlags']['pandoc-cli/pandoc-cli.cabal']);
        $t->same([
            'nightly',
            'server',
        ], $audit['packageFlagDefinitionClosure']['missingFlags']['pandoc-cli/pandoc-cli.cabal']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('mismatched Cabal package identity headers: pandoc-server/pandoc-server.cabal (version expected 0.1.2, found 0.1.1); pandoc-cli/pandoc-cli.cabal (name expected pandoc-cli, found pandoc-runner-cli)', $blocked);
        $t->contains('missing Cabal package flag definitions: pandoc-cli/pandoc-cli.cabal (nightly, server)', $blocked);
        $t->contains('Cabal package identity/version headers', $audit['activationGate']);
        $t->contains('package flag definitions plus default/manual values for cabal.project flags', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks package custom setup dependencies before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['pandoc.cabal'] .= implode("\n", [
            '',
            'custom-setup',
            '  setup-depends:',
            '    base >= 4.18 && < 5,',
            '    Cabal >= 3.10 && < 3.13',
        ]);
        $files['pandoc-lua-engine/pandoc-lua-engine.cabal'] .= implode("\n", [
            '',
            'common setup-options',
            '  setup-depends: base >= 4.12 && < 5',
            '',
            'custom-setup',
            '  import: setup-options',
            '  setup-depends: Cabal >= 3.8',
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
        $t->same([], $audit['packageIdentityClosure']['missingHeaders']);
        $t->same([], $audit['packageIdentityClosure']['mismatchedHeaders']);
        $t->same([], $audit['projectSourceRepositoryPins']['missing']);
        $t->same([], $audit['projectSourceRepositoryPins']['mismatched']);
        $t->same([], $audit['projectPackageClosure']['missingPackages']);
        $t->same([], $audit['projectConstraintClosure']['missingConstraints']);
        $t->same(true, $audit['packageSetupClosure']['present']['pandoc.cabal']['customSetup']);
        $t->same(true, $audit['packageSetupClosure']['present']['pandoc-lua-engine/pandoc-lua-engine.cabal']['customSetup']);
        $t->same([
            'Cabal',
            'base',
        ], $audit['packageSetupClosure']['present']['pandoc.cabal']['setupDepends']);
        $t->same([
            'Cabal' => '>= 3.10 && < 3.13',
            'base' => '>= 4.18 && < 5',
        ], $audit['packageSetupClosure']['present']['pandoc.cabal']['dependencyConstraints']);
        $t->same([
            'Cabal',
            'base',
        ], $audit['packageSetupClosure']['present']['pandoc-lua-engine/pandoc-lua-engine.cabal']['setupDepends']);
        $t->same([
            'Cabal' => '>= 3.8',
            'base' => '>= 4.12 && < 5',
        ], $audit['packageSetupClosure']['present']['pandoc-lua-engine/pandoc-lua-engine.cabal']['dependencyConstraints']);
        $t->same([
            'custom-setup',
        ], $audit['packageSetupClosure']['unexpectedCustomSetupStanzas']['pandoc.cabal']);
        $t->same([
            'custom-setup',
        ], $audit['packageSetupClosure']['unexpectedCustomSetupStanzas']['pandoc-lua-engine/pandoc-lua-engine.cabal']);
        $t->same([
            'Cabal >= 3.10 && < 3.13',
            'base >= 4.18 && < 5',
        ], $audit['packageSetupClosure']['unexpectedSetupDependencies']['pandoc.cabal']);
        $t->same([
            'Cabal >= 3.8',
            'base >= 4.12 && < 5',
        ], $audit['packageSetupClosure']['unexpectedSetupDependencies']['pandoc-lua-engine/pandoc-lua-engine.cabal']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('unexpected Cabal custom-setup stanzas: pandoc.cabal (custom-setup); pandoc-lua-engine/pandoc-lua-engine.cabal (custom-setup)', $blocked);
        $t->contains('unexpected Cabal setup-depends: pandoc.cabal (Cabal >= 3.10 && < 3.13, base >= 4.18 && < 5); pandoc-lua-engine/pandoc-lua-engine.cabal (Cabal >= 3.8, base >= 4.12 && < 5)', $blocked);
        $t->contains('no package custom-setup/setup-depends hooks', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks missing package flag definitions before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['pandoc.cabal'] = str_replace(implode("\n", [
            'flag embed_data_files',
            '  description: Embed data files in the built executable',
            '  default: False',
            '  manual: True',
            '',
        ]), '', $files['pandoc.cabal']);
        $files['pandoc.cabal'] = str_replace(implode("\n", [
            'flag http',
            '  description: Enable HTTP support for the runner closure',
            '  default: True',
            '  manual: True',
            '',
        ]), '', $files['pandoc.cabal']);

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
        $t->same([], $audit['packageIdentityClosure']['missingHeaders']);
        $t->same([], $audit['packageIdentityClosure']['mismatchedHeaders']);
        $t->same([], $audit['packageSetupClosure']['unexpectedCustomSetupStanzas']);
        $t->same([], $audit['packageSetupClosure']['unexpectedSetupDependencies']);
        $t->same([], $audit['projectPackageClosure']['missingPackages']);
        $t->same([], $audit['projectPackageClosure']['missingFlags']);
        $t->same([], $audit['projectPackageClosure']['mismatchedFlags']);
        $t->same(UpstreamRunnerDependencyAudit::expectedPackageFlagDefinitions(), $audit['packageFlagDefinitionClosure']['expectedFlags']);
        $t->same([], $audit['packageFlagDefinitionClosure']['presentFlags']['pandoc.cabal']);
        $t->same([
            'embed_data_files',
            'http',
        ], $audit['packageFlagDefinitionClosure']['missingFlags']['pandoc.cabal']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('missing Cabal package flag definitions: pandoc.cabal (embed_data_files, http)', $blocked);
        $t->contains('package flag definitions plus default/manual values for cabal.project flags', $audit['activationGate']);
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
    'blocks unexpected runner and benchmark direct dependencies before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['pandoc.cabal'] = str_replace(
            "    Diff >= 0.2 && < 1.1,\n    Glob >= 0.7 && < 0.11,",
            "    Diff >= 0.2 && < 1.1,\n    aeson >= 2.0 && < 2.3,\n    Glob >= 0.7 && < 0.11,",
            $files['pandoc.cabal']
        );
        $files['pandoc.cabal'] = str_replace(
            "    deepseq,\n    mtl",
            "    deepseq,\n    criterion >= 1.6 && < 1.7,\n    mtl",
            $files['pandoc.cabal']
        );
        $files['pandoc-lua-engine/pandoc-lua-engine.cabal'] = str_replace(
            "    directory,\n    data-default,",
            "    directory,\n    hspec >= 2.10,\n    data-default,",
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
        $t->same([], $audit['projectSourceRepositoryPins']['missing']);
        $t->same([], $audit['projectPackageClosure']['missingPackages']);
        $t->same([], $audit['projectConstraintClosure']['missingConstraints']);
        $t->same([], $audit['runnerDependencyClosure']['missingTargets']);
        $t->same([], $audit['runnerDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['runnerDependencyClosure']['missingDependencies']);
        $t->same([], $audit['runnerDependencyClosure']['mismatchedDependencyConstraints']);
        $t->same([], $audit['runnerDependencyClosure']['missingExecutableOptions']);
        $t->same([], $audit['runnerDependencyClosure']['mismatchedDefaultLanguages']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingTargets']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingDependencies']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedDependencyConstraints']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingExecutableOptions']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedDefaultLanguages']);
        $t->same(['aeson >= 2.0 && < 2.3'], $audit['runnerDependencyClosure']['unexpectedDependencies']['test:test-pandoc']);
        $t->same(['hspec >= 2.10'], $audit['runnerDependencyClosure']['unexpectedDependencies']['test:test-pandoc-lua-engine']);
        $t->same(['criterion >= 1.6 && < 1.7'], $audit['benchmarkDependencyClosure']['unexpectedDependencies'][$target]);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('unexpected Cabal runner direct build-depends: test:test-pandoc (aeson >= 2.0 && < 2.3); test:test-pandoc-lua-engine (hspec >= 2.10)', $blocked);
        $t->contains('unexpected Cabal benchmark direct build-depends: benchmark:benchmark-pandoc (criterion >= 1.6 && < 1.7)', $blocked);
        $t->contains('no unexpected runner or benchmark direct build-depends', $audit['activationGate']);
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
    'blocks unexpected runner and benchmark executable options before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['pandoc.cabal'] = str_replace(
            '  ghc-options: -rtsopts -with-rtsopts=-A8m -threaded',
            '  ghc-options: -rtsopts -with-rtsopts=-A8m -threaded -eventlog',
            $files['pandoc.cabal']
        );
        $files['pandoc-lua-engine/pandoc-lua-engine.cabal'] = str_replace(
            "common test-options\n  build-depends: base >= 4.12 && < 5",
            implode("\n", [
                'common test-options',
                '  build-depends: base >= 4.12 && < 5',
                '  ghc-options: -fno-ignore-asserts',
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
        $t->same([], $audit['projectSourceRepositoryPins']['missing']);
        $t->same([], $audit['projectPackageClosure']['missingPackages']);
        $t->same([], $audit['projectConstraintClosure']['missingConstraints']);
        $t->same([], $audit['runnerDependencyClosure']['missingTargets']);
        $t->same([], $audit['runnerDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['runnerDependencyClosure']['missingDependencies']);
        $t->same([], $audit['runnerDependencyClosure']['mismatchedDependencyConstraints']);
        $t->same([], $audit['runnerDependencyClosure']['missingExecutableOptions']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedDependencies']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedTestOptions']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedCppOptions']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedConditionalBranches']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingTargets']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingDependencies']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedDependencyConstraints']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingExecutableOptions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedDependencies']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedBenchmarkOptions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedCppOptions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedConditionalBranches']);
        $t->same(UpstreamRunnerDependencyAudit::expectedRunnerExecutableOptions(), $audit['runnerDependencyClosure']['expectedExecutableOptions']);
        $t->same(UpstreamRunnerDependencyAudit::expectedBenchmarkExecutableOptions(), $audit['benchmarkDependencyClosure']['expectedExecutableOptions']);
        $t->same([
            '-eventlog',
        ], $audit['runnerDependencyClosure']['unexpectedExecutableOptions']['test:test-pandoc']);
        $t->same([
            '-fno-ignore-asserts',
        ], $audit['runnerDependencyClosure']['unexpectedExecutableOptions']['test:test-pandoc-lua-engine']);
        $t->same([
            '-eventlog',
        ], $audit['benchmarkDependencyClosure']['unexpectedExecutableOptions'][$target]);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('unexpected Cabal runner executable options: test:test-pandoc (-eventlog); test:test-pandoc-lua-engine (-fno-ignore-asserts)', $blocked);
        $t->contains('unexpected Cabal benchmark executable options: benchmark:benchmark-pandoc (-eventlog)', $blocked);
        $t->contains('exact runner and benchmark executable options', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks runner and benchmark manual fields before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['pandoc.cabal'] = str_replace(
            "test-suite test-pandoc\n  import: common-executable\n  type: exitcode-stdio-1.0",
            implode("\n", [
                'test-suite test-pandoc',
                '  import: common-executable',
                '  type: exitcode-stdio-1.0',
                '  manual: True',
            ]),
            $files['pandoc.cabal']
        );
        $files['pandoc.cabal'] = str_replace(
            "benchmark benchmark-pandoc\n  import: common-executable\n  type: exitcode-stdio-1.0",
            implode("\n", [
                'benchmark benchmark-pandoc',
                '  import: common-executable',
                '  type: exitcode-stdio-1.0',
                '  manual: True',
            ]),
            $files['pandoc.cabal']
        );
        $files['pandoc-lua-engine/pandoc-lua-engine.cabal'] = str_replace(
            "test-suite test-pandoc-lua-engine\n  import: test-options\n  type: exitcode-stdio-1.0",
            implode("\n", [
                'test-suite test-pandoc-lua-engine',
                '  import: test-options',
                '  type: exitcode-stdio-1.0',
                '  manual: True',
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
        $t->same([], $audit['runnerDependencyClosure']['unexpectedExecutableOptions']);
        $t->same([], $audit['runnerDependencyClosure']['mismatchedDefaultLanguages']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingTargets']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingDependencies']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedDependencyConstraints']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingExecutableOptions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedExecutableOptions']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedDefaultLanguages']);
        $t->same(UpstreamRunnerDependencyAudit::expectedRunnerManualFields(), $audit['runnerDependencyClosure']['expectedManualFields']);
        $t->same(UpstreamRunnerDependencyAudit::expectedBenchmarkManualFields(), $audit['benchmarkDependencyClosure']['expectedManualFields']);
        $t->same('true', $audit['runnerDependencyClosure']['present']['test:test-pandoc']['manual']);
        $t->same('true', $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['manual']);
        $t->same('true', $audit['benchmarkDependencyClosure']['present'][$target]['manual']);
        $t->same([
            'expected' => null,
            'actual' => 'true',
        ], $audit['runnerDependencyClosure']['mismatchedManualFields']['test:test-pandoc']);
        $t->same([
            'expected' => null,
            'actual' => 'true',
        ], $audit['runnerDependencyClosure']['mismatchedManualFields']['test:test-pandoc-lua-engine']);
        $t->same([
            'expected' => null,
            'actual' => 'true',
        ], $audit['benchmarkDependencyClosure']['mismatchedManualFields'][$target]);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('mismatched Cabal runner manual fields: test:test-pandoc manual expected absent, found true; test:test-pandoc-lua-engine manual expected absent, found true', $blocked);
        $t->contains('mismatched Cabal benchmark manual fields: benchmark:benchmark-pandoc manual expected absent, found true', $blocked);
        $t->contains('exact absent runner and benchmark manual fields', $audit['activationGate']);
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
    'rejects non buildable cabal benchmark before solver planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles, $pandocCabal, $pandocBenchmark): void {
        $benchmark = $pandocBenchmark([], null, null, null, 'exitcode-stdio-1.0', 'False');
        $root = $makeTree($requiredFiles(
            $pinnedProject(),
            $pandocCabal([], null, null, null, 'exitcode-stdio-1.0', null, 'Haskell2010', $benchmark)
        ));
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
        $t->same([], $audit['projectSourceRepositoryPins']['missing']);
        $t->same([], $audit['projectSourceRepositoryPins']['mismatched']);
        $t->same([], $audit['projectPackageClosure']['missingPackages']);
        $t->same([], $audit['projectConstraintClosure']['missingConstraints']);
        $t->same([], $audit['runnerDependencyClosure']['missingTargets']);
        $t->same([], $audit['runnerDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['runnerDependencyClosure']['missingDependencies']);
        $t->same([], $audit['runnerDependencyClosure']['missingExecutableOptions']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingTargets']);
        $t->same(false, $audit['benchmarkDependencyClosure']['present'][$target]['buildable']);
        $t->contains('buildable expected true, found false', $audit['benchmarkDependencyClosure']['mismatchedEntryPoints'][$target][0]);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('mismatched Cabal benchmark entry points: benchmark:benchmark-pandoc (buildable expected true, found false)', $blocked);
        $t->contains('buildable benchmark components', $audit['activationGate']);
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
    'blocks unexpected runner and benchmark other-modules before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['pandoc.cabal'] = str_replace(
            "common common-executable\n  import: common-options",
            "common common-executable\n  import: common-options\n  other-modules:\n    Tests.Generated.Runner,\n    Tests.Shared.Generated",
            $files['pandoc.cabal']
        );
        $files['pandoc.cabal'] = str_replace(
            "  main-is: benchmark-pandoc.hs\n  hs-source-dirs: benchmark",
            "  main-is: benchmark-pandoc.hs\n  other-modules:\n    Benchmark.Generated,\n    Benchmark.LegacyGenerated\n  hs-source-dirs: benchmark",
            $files['pandoc.cabal']
        );
        $files['pandoc-lua-engine/pandoc-lua-engine.cabal'] = str_replace(
            "common test-options\n  build-depends: base >= 4.12 && < 5",
            "common test-options\n  other-modules: Tests.Lua.Generated\n  build-depends: base >= 4.12 && < 5",
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
        $t->same([], $audit['runnerDependencyClosure']['missingExecutableOptions']);
        $t->same([], $audit['runnerDependencyClosure']['missingOtherModules']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingTargets']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingDependencies']);
        $t->same([
            'Tests.Generated.Runner',
            'Tests.Shared.Generated',
        ], $audit['runnerDependencyClosure']['unexpectedOtherModules']['test:test-pandoc']);
        $t->same([
            'Tests.Lua.Generated',
        ], $audit['runnerDependencyClosure']['unexpectedOtherModules']['test:test-pandoc-lua-engine']);
        $t->same([
            'Benchmark.Generated',
            'Benchmark.LegacyGenerated',
            'Tests.Generated.Runner',
            'Tests.Shared.Generated',
        ], $audit['benchmarkDependencyClosure']['unexpectedOtherModules'][$target]);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('unexpected Cabal runner other-modules: test:test-pandoc (Tests.Generated.Runner, Tests.Shared.Generated); test:test-pandoc-lua-engine (Tests.Lua.Generated)', $blocked);
        $t->contains('unexpected Cabal benchmark other-modules: benchmark:benchmark-pandoc (Benchmark.Generated, Benchmark.LegacyGenerated, Tests.Generated.Runner, Tests.Shared.Generated)', $blocked);
        $t->contains('no unexpected runner or benchmark other-modules', $audit['activationGate']);
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
    'blocks unexpected lua engine library dependencies before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles, $luaCabal): void {
        $luaPackage = str_replace(
            "    hslua-module-path,\n",
            "    hslua-module-path,\n    hslua-module-runner-audit >= 0.1 && < 0.2,\n    pandoc-lua-generated,\n",
            $luaCabal()
        );

        $root = $makeTree($requiredFiles(
            $pinnedProject(),
            null,
            $luaPackage
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
        $t->same([], $audit['runnerDependencyClosure']['unexpectedDependencies']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedDependencies']);
        $t->same([], $audit['luaEngineLibraryClosure']['missingDependencies']);
        $t->same([
            'hslua-module-runner-audit >= 0.1 && < 0.2',
            'pandoc-lua-generated',
        ], $audit['luaEngineLibraryClosure']['unexpectedDependencies']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('unexpected pandoc-lua-engine library Lua support build-depends: hslua-module-runner-audit >= 0.1 && < 0.2, pandoc-lua-generated', $blocked);
        $t->contains('no unexpected pandoc-lua-engine library Lua support build-depends', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks pandoc server library dependency drift before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles, $serverCabal): void {
        $serverPackage = str_replace(
            [
                '  default-language: Haskell2010',
                '    pandoc >= 3.9 && < 3.10,',
                '    pandoc-types >= 1.22 && < 1.24,',
                '  hs-source-dirs: src',
                '  exposed-modules: Text.Pandoc.Server',
            ],
            [
                '  default-language: Haskell98',
                '    pandoc >= 3.8 && < 3.9,',
                '    pandoc-server-audit >= 0.1 && < 0.2,',
                '  hs-source-dirs: server-src generated-src',
                '  exposed-modules: Text.Pandoc.Server.Generated',
            ],
            $serverCabal()
        );

        $root = $makeTree($requiredFiles(
            $pinnedProject(),
            null,
            null,
            true,
            $serverPackage
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
        $t->same(UpstreamRunnerDependencyAudit::expectedServerLibraryDependencies(), $audit['serverLibraryClosure']['expectedDependencies']);
        $t->same(UpstreamRunnerDependencyAudit::expectedServerLibraryDependencyConstraints(), $audit['serverLibraryClosure']['expectedDependencyConstraints']);
        $t->same([
            'pandoc-types',
        ], $audit['serverLibraryClosure']['missingDependencies']);
        $t->same([
            'pandoc-server-audit >= 0.1 && < 0.2',
        ], $audit['serverLibraryClosure']['unexpectedDependencies']);
        $t->same([
            'pandoc' => [
                'expected' => '>= 3.9 && < 3.10',
                'actual' => '>= 3.8 && < 3.9',
            ],
        ], $audit['serverLibraryClosure']['mismatchedDependencyConstraints']);
        $t->same([
            'Text.Pandoc.Server',
        ], $audit['serverLibraryClosure']['missingExposedModules']);
        $t->same([
            'Text.Pandoc.Server.Generated',
        ], $audit['serverLibraryClosure']['unexpectedExposedModules']);
        $t->same(['src'], $audit['serverLibraryClosure']['missingSourceDirectories']);
        $t->same(['server-src', 'generated-src'], $audit['serverLibraryClosure']['unexpectedSourceDirectories']);
        $t->same([
            'expected' => 'Haskell2010',
            'actual' => 'Haskell98',
        ], $audit['serverLibraryClosure']['mismatchedDefaultLanguage']);

        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('missing pandoc-server library build-depends: pandoc-types', $blocked);
        $t->contains('unexpected pandoc-server library build-depends: pandoc-server-audit >= 0.1 && < 0.2', $blocked);
        $t->contains('mismatched pandoc-server library build-depends constraints: pandoc expected >= 3.9 && < 3.10, found >= 3.8 && < 3.9', $blocked);
        $t->contains('missing pandoc-server library exposed-modules: Text.Pandoc.Server', $blocked);
        $t->contains('unexpected pandoc-server library exposed-modules: Text.Pandoc.Server.Generated', $blocked);
        $t->contains('missing pandoc-server library hs-source-dirs: src', $blocked);
        $t->contains('unexpected pandoc-server library hs-source-dirs: server-src, generated-src', $blocked);
        $t->contains('mismatched pandoc-server library default-language: expected Haskell2010, found Haskell98', $blocked);
        $t->contains('exact pandoc-server library dependency/exposed-module/source-directory/default-language closure', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks pandoc cli executable drift before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles, $cliCabal): void {
        $cliPackage = str_replace(
            [
                '  default-language: Haskell2010',
                '  other-extensions: OverloadedStrings',
                '  build-depends: base >= 4.18 && < 5',
                '  ghc-options: -rtsopts -with-rtsopts=-A8m',
                '  main-is: pandoc.hs',
                '  hs-source-dirs: src',
                '  build-depends: pandoc == 3.9.0.2, text',
                '  other-modules: PandocCLI.Lua, PandocCLI.Server',
            ],
            [
                '  default-language: Haskell98',
                '  other-extensions: OverloadedLabels',
                '  build-depends: base >= 4.17 && < 5, pandoc-cli-common',
                '  ghc-options: -rtsopts -eventlog',
                '  main-is: pandoc-runner.hs',
                '  hs-source-dirs: generated-cli',
                '  build-depends: pandoc == 3.8.0.0, pandoc-cli-audit >= 0.1 && < 0.2',
                '  other-modules: PandocCLI.Generated',
            ],
            $cliCabal()
        );
        $cliPackage .= "\n  if flag(profile)\n    ghc-options: -prof";

        $root = $makeTree($requiredFiles(
            $pinnedProject(),
            null,
            null,
            true,
            null,
            $cliPackage
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
        $t->same(false, $audit['cliExecutableClosure']['missingExecutable']);
        $t->same(UpstreamRunnerDependencyAudit::expectedCliExecutableDependencies(), $audit['cliExecutableClosure']['expectedDependencies']);
        $t->same(UpstreamRunnerDependencyAudit::expectedCliExecutableDependencyConstraints(), $audit['cliExecutableClosure']['expectedDependencyConstraints']);
        $t->same([
            'main-is expected pandoc.hs, found pandoc-runner.hs',
        ], $audit['cliExecutableClosure']['mismatchedEntryPoint']);
        $t->same([
            'text',
        ], $audit['cliExecutableClosure']['missingDependencies']);
        $t->same([
            'pandoc-cli-audit >= 0.1 && < 0.2',
            'pandoc-cli-common',
        ], $audit['cliExecutableClosure']['unexpectedDependencies']);
        $t->same([
            'base' => [
                'expected' => '>= 4.18 && < 5',
                'actual' => '>= 4.17 && < 5',
            ],
            'pandoc' => [
                'expected' => '== 3.9.0.2',
                'actual' => '== 3.8.0.0',
            ],
        ], $audit['cliExecutableClosure']['mismatchedDependencyConstraints']);
        $t->same([
            '-with-rtsopts=-A8m',
        ], $audit['cliExecutableClosure']['missingExecutableOptions']);
        $t->same([
            '-eventlog',
        ], $audit['cliExecutableClosure']['unexpectedExecutableOptions']);
        $t->same([
            'expected' => 'Haskell2010',
            'actual' => 'Haskell98',
        ], $audit['cliExecutableClosure']['mismatchedDefaultLanguage']);
        $t->same(['src'], $audit['cliExecutableClosure']['missingSourceDirectories']);
        $t->same(['generated-cli'], $audit['cliExecutableClosure']['unexpectedSourceDirectories']);
        $t->same(['OverloadedStrings'], $audit['cliExecutableClosure']['missingOtherExtensions']);
        $t->same(['OverloadedLabels'], $audit['cliExecutableClosure']['unexpectedOtherExtensions']);
        $t->same([
            'PandocCLI.Lua',
            'PandocCLI.Server',
        ], $audit['cliExecutableClosure']['missingOtherModules']);
        $t->same([
            'PandocCLI.Generated',
        ], $audit['cliExecutableClosure']['unexpectedOtherModules']);
        $t->same([], $audit['cliExecutableClosure']['missingConditionalBranches']);
        $t->same([
            'executable pandoc: if flag(profile)',
        ], $audit['cliExecutableClosure']['unexpectedConditionalBranches']);

        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('mismatched pandoc-cli executable entry point: main-is expected pandoc.hs, found pandoc-runner.hs', $blocked);
        $t->contains('missing pandoc-cli executable build-depends: text', $blocked);
        $t->contains('unexpected pandoc-cli executable build-depends: pandoc-cli-audit >= 0.1 && < 0.2, pandoc-cli-common', $blocked);
        $t->contains('mismatched pandoc-cli executable build-depends constraints: base expected >= 4.18 && < 5, found >= 4.17 && < 5, pandoc expected == 3.9.0.2, found == 3.8.0.0', $blocked);
        $t->contains('missing pandoc-cli executable options: -with-rtsopts=-A8m', $blocked);
        $t->contains('unexpected pandoc-cli executable options: -eventlog', $blocked);
        $t->contains('mismatched pandoc-cli executable default-language: expected Haskell2010, found Haskell98', $blocked);
        $t->contains('missing pandoc-cli executable hs-source-dirs: src', $blocked);
        $t->contains('unexpected pandoc-cli executable hs-source-dirs: generated-cli', $blocked);
        $t->contains('missing pandoc-cli executable other-extensions: OverloadedStrings', $blocked);
        $t->contains('unexpected pandoc-cli executable other-extensions: OverloadedLabels', $blocked);
        $t->contains('missing pandoc-cli executable other-modules: PandocCLI.Lua, PandocCLI.Server', $blocked);
        $t->contains('unexpected pandoc-cli executable other-modules: PandocCLI.Generated', $blocked);
        $t->contains('unexpected pandoc-cli executable conditional branches: executable pandoc: if flag(profile)', $blocked);
        $t->contains('exact pandoc-cli executable entry point, common import, direct dependency, option, source-directory, extension, other-module, and known conditional-branch closure', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'records pandoc cli flag-specific conditional dependency closure before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $root = $makeTree($requiredFiles($pinnedProject()));
        try {
            $audit = UpstreamRunnerDependencyAudit::auditCheckout($root, [
                'ghc' => '9.10.3',
                'cabal' => '3.12.1.0',
            ]);
        } finally {
            $removeTree($root);
        }

        $closure = $audit['cliExecutableClosure']['presentConditionalFieldClosure'];
        $t->same(true, $audit['readyForNonMutatingCabalPlan']);
        $t->same([
            'aeson',
            'bytestring',
            'containers',
            'filepath',
            'pandoc-lua-engine',
            'skylighting',
        ], $closure['executable pandoc: if arch(wasm32)']['buildDepends']);
        $t->same(['PandocWasm'], $closure['executable pandoc: if arch(wasm32)']['otherModules']);
        $t->same([
            'template-haskell',
            'time',
        ], $closure['executable pandoc: if flag(nightly)']['buildDepends']);
        $t->same([
            'pandoc-server >= 0.1.1 && < 0.2',
            'safe',
            'wai-extra >= 3.0.24',
            'warp',
        ], $closure['executable pandoc: if flag(server)']['buildDepends']);
        $t->same(['no-server'], $closure['executable pandoc: else after if flag(server)']['sourceDirectories']);
        $t->same(['pandoc-lua-engine >= 0.5.1 && < 0.6'], $closure['executable pandoc: if flag(lua)']['buildDepends']);
        $t->same(['no-lua'], $closure['executable pandoc: else after if flag(lua)']['sourceDirectories']);
        $t->same([
            'hslua-cli >= 1.4.1 && < 1.5',
            'temporary >= 1.1 && < 1.4',
        ], $closure['executable pandoc: if flag(repl)']['buildDepends']);
        $t->same(['-DREPL'], $closure['executable pandoc: if flag(repl)']['cppOptions']);
        $t->same([], $audit['cliExecutableClosure']['missingConditionalFieldEntries']);
        $t->same([], $audit['cliExecutableClosure']['unexpectedConditionalFieldEntries']);
    },
    'records pandoc cli conditional source artifacts before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $root = $makeTree($requiredFiles($pinnedProject()));
        try {
            $audit = UpstreamRunnerDependencyAudit::auditCheckout($root, [
                'ghc' => '9.10.3',
                'cabal' => '3.12.1.0',
            ]);
        } finally {
            $removeTree($root);
        }

        $expected = UpstreamRunnerDependencyAudit::expectedCliExecutableSourceArtifacts();
        $closure = $audit['cliExecutableClosure'];
        $t->same(true, $audit['readyForNonMutatingCabalPlan']);
        $t->same($expected, $closure['expectedSourceArtifacts']);
        $t->same(array_keys($expected), $closure['presentSourceArtifacts']);
        $t->same([], $closure['missingSourceArtifacts']);
        $t->same([], $closure['wrongTypeSourceArtifacts']);
        $t->same([], $closure['emptySourceArtifacts']);
        $t->same(array_keys($expected), array_keys($closure['sourceArtifactProvenance']));
        $t->same(true, $closure['sourceArtifactProvenance']['pandoc-cli/wasm/PandocWasm.hs']['bytes'] > 0);
        $t->same(true, $closure['sourceArtifactProvenance']['pandoc-cli/server/PandocCLI/Server.hs']['bytes'] > 0);
        $t->same(true, $closure['sourceArtifactProvenance']['pandoc-cli/no-server/PandocCLI/Server.hs']['bytes'] > 0);
        $t->same(true, $closure['sourceArtifactProvenance']['pandoc-cli/lua/PandocCLI/Lua.hs']['bytes'] > 0);
        $t->same(true, $closure['sourceArtifactProvenance']['pandoc-cli/no-lua/PandocCLI/Lua.hs']['bytes'] > 0);
        $t->same(
            array_keys(UpstreamRunnerDependencyAudit::expectedCliExecutableSourceSemantics()),
            array_keys($closure['expectedSourceSemantics'])
        );
        $t->same([], $closure['missingSourceSemantics']);
        $t->contains('routes server executable name', implode(',', $closure['presentSourceSemantics']['pandoc-cli/src/pandoc.hs']));
        $t->contains('runs warp on configured port', implode(',', $closure['presentSourceSemantics']['pandoc-cli/server/PandocCLI/Server.hs']));
        $t->contains('returns no engine placeholder', implode(',', $closure['presentSourceSemantics']['pandoc-cli/no-lua/PandocCLI/Lua.hs']));
        $t->contains('non-empty pandoc-cli conditional source artifacts with hashes', $audit['activationGate']);
        $t->contains('conditional source artifact hashes', $audit['nonMutatingPlan'][2]);
        $t->contains('conditional source semantics', $audit['nonMutatingPlan'][2]);
    },
    'blocks pandoc cli conditional source artifact drift before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        unset($files['pandoc-cli/wasm/PandocWasm.hs']);
        unset($files['pandoc-cli/server/PandocCLI/Server.hs']);
        $files['pandoc-cli/server/PandocCLI/Server.hs/.audit-keep'] = 'wrong source artifact type';
        $files['pandoc-cli/no-lua/PandocCLI/Lua.hs'] = '';

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
        $t->same(['pandoc-cli/wasm/PandocWasm.hs'], $audit['cliExecutableClosure']['missingSourceArtifacts']);
        $t->same([
            'pandoc-cli/server/PandocCLI/Server.hs' => [
                'expected' => 'file',
                'actual' => 'directory',
            ],
        ], $audit['cliExecutableClosure']['wrongTypeSourceArtifacts']);
        $t->same(['pandoc-cli/no-lua/PandocCLI/Lua.hs'], $audit['cliExecutableClosure']['emptySourceArtifacts']);

        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('missing pandoc-cli executable source artifacts: pandoc-cli/wasm/PandocWasm.hs', $blocked);
        $t->contains('mismatched pandoc-cli executable source artifact types: pandoc-cli/server/PandocCLI/Server.hs expected file, found directory', $blocked);
        $t->contains('empty pandoc-cli executable source artifacts: pandoc-cli/no-lua/PandocCLI/Lua.hs', $blocked);
        $t->contains('non-empty pandoc-cli conditional source artifacts with hashes', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks pandoc cli version option source semantic drift before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['pandoc-cli/src/pandoc.hs'] = str_replace(
            [
                's == "-v" || s == "--version"',
                'takeWhile (/= "--") rawArgs',
                'let versionOr action = if hasVersion then versionInfoCLI else action',
                '"server": args -> versionOr $ runServer args',
                'parseOptionsFromArgs options defaultOpts prg args',
                'Left e -> handleOptInfo engine e',
                'versionInfo getFeatures (Just $ T.unpack (engineName scriptingEngine)) versionSuffix',
            ],
            [
                's == "--help"',
                'rawArgs',
                'let versionOr action = action',
                '"server": args -> runServer args',
                'parseOptionsFromArgs options defaultOpts "pandoc" args',
                'Left e -> handleOptInfo noEngine e',
                'pure ()',
            ],
            $files['pandoc-cli/src/pandoc.hs']
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
        $missing = $audit['cliExecutableClosure']['missingSourceSemantics']['pandoc-cli/src/pandoc.hs'];
        $t->contains('detects short and long version options', implode(',', $missing));
        $t->contains('stops version detection at option separator', implode(',', $missing));
        $t->contains('guards commands with version handler', implode(',', $missing));
        $t->contains('routes server subcommand through version handler', implode(',', $missing));
        $t->contains('parses options with executable program name', implode(',', $missing));
        $t->contains('handles option info with selected engine', implode(',', $missing));
        $t->contains('reports feature list and scripting engine in version output', implode(',', $missing));

        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('missing pandoc-cli executable source semantics', $blocked);
        $t->contains('pandoc-cli/src/pandoc.hs (detects short and long version options, stops version detection at option separator, guards commands with version handler', $blocked);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks pandoc cli conditional source semantic drift before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['pandoc-cli/src/pandoc.hs'] = implode("\n", [
            'module Main where',
            'import PandocCLI.Lua',
            'main = pure ()',
        ]);
        $files['pandoc-cli/server/PandocCLI/Server.hs'] = implode("\n", [
            'module PandocCLI.Server ( runCGI , runServer ) where',
            'runCGI = pure ()',
            'runServer _ = pure ()',
        ]);
        $files['pandoc-cli/no-server/PandocCLI/Server.hs'] = implode("\n", [
            'module PandocCLI.Server ( runCGI , runServer ) where',
            'runCGI = pure ()',
            'runServer _args = pure ()',
        ]);
        $files['pandoc-cli/lua/PandocCLI/Lua.hs'] = implode("\n", [
            'module PandocCLI.Lua (runLuaInterpreter, getEngine) where',
            'runLuaInterpreter _ _ = pure ()',
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
        $missing = $audit['cliExecutableClosure']['missingSourceSemantics'];
        $t->contains('imports server shim module', implode(',', $missing['pandoc-cli/src/pandoc.hs']));
        $t->contains('routes server executable name', implode(',', $missing['pandoc-cli/src/pandoc.hs']));
        $t->contains('runs cgi with timeout middleware', implode(',', $missing['pandoc-cli/server/PandocCLI/Server.hs']));
        $t->contains('exits with unsupported status', implode(',', $missing['pandoc-cli/no-server/PandocCLI/Server.hs']));
        $t->contains('guards repl-specific code', implode(',', $missing['pandoc-cli/lua/PandocCLI/Lua.hs']));

        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('missing pandoc-cli executable source semantics', $blocked);
        $t->contains('pandoc-cli/src/pandoc.hs (imports server shim module, detects short and long version options', $blocked);
        $t->contains('routes server executable name', $blocked);
        $t->contains('pandoc-cli/no-server/PandocCLI/Server.hs (routes cgi placeholder to unsupported handler, routes server placeholder to unsupported handler, exits with unsupported status)', $blocked);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks pandoc cli conditional branch field drift before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles, $cliCabal): void {
        $cliPackage = str_replace(
            [
                '    hs-source-dirs: wasm',
                '    other-modules: PandocWasm',
                '    cpp-options: -DINCLUDE_WASM',
                '    build-depends: aeson, containers, bytestring, skylighting, filepath, pandoc-lua-engine',
                '    ghc-options: -threaded',
                '    build-depends: template-haskell, time',
                '    build-depends: pandoc-server >= 0.1.1 && < 0.2, wai-extra >= 3.0.24, warp, safe',
                '    hs-source-dirs: server',
                '    build-depends: pandoc-lua-engine >= 0.5.1 && < 0.6',
                '    hs-source-dirs: no-lua',
                '    build-depends: hslua-cli >= 1.4.1 && < 1.5, temporary >= 1.1 && < 1.4',
                '    cpp-options: -DREPL',
            ],
            [
                '    hs-source-dirs: generated-wasm',
                '    other-modules: PandocGeneratedWasm',
                '    cpp-options: -DINCLUDE_GENERATED_WASM',
                '    build-depends: aeson, containers, bytestring, skylighting, pandoc-wasm-audit >= 0.1 && < 0.2',
                '    ghc-options: -eventlog',
                '    build-depends: template-haskell, nightly-audit >= 0.1 && < 0.2',
                '    build-depends: pandoc-server >= 0.1.0 && < 0.2, warp, pandoc-server-audit >= 0.1 && < 0.2',
                '    hs-source-dirs: server generated-server',
                '    build-depends: pandoc-lua-engine >= 0.5.0 && < 0.6',
                '    hs-source-dirs: generated-no-lua',
                '    build-depends: hslua-cli >= 1.4.1 && < 1.5',
                '    cpp-options: -DREPL_GENERATED',
            ],
            $cliCabal()
        );

        $root = $makeTree($requiredFiles(
            $pinnedProject(),
            null,
            null,
            true,
            null,
            $cliPackage
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
        $t->same([], $audit['cliExecutableClosure']['missingConditionalBranches']);
        $t->same([], $audit['cliExecutableClosure']['unexpectedConditionalBranches']);
        $t->same([
            'common common-options: if os(windows)' => [
                'sourceDirectories' => [],
                'ghcOptions' => [],
                'cppOptions' => ['-D_WINDOWS'],
                'buildDepends' => [],
                'otherModules' => [],
                'nativeSystemFields' => [],
            ],
            'executable pandoc: if arch(wasm32)' => [
                'sourceDirectories' => ['generated-wasm'],
                'ghcOptions' => ['-optl-Wl,--export=__wasm_call_ctors,--export=hs_init_with_rtsopts,--export=malloc,--export=convert,--export=query'],
                'cppOptions' => ['-DINCLUDE_GENERATED_WASM'],
                'buildDepends' => [
                    'aeson',
                    'bytestring',
                    'containers',
                    'pandoc-wasm-audit >= 0.1 && < 0.2',
                    'skylighting',
                ],
                'otherModules' => ['PandocGeneratedWasm'],
                'nativeSystemFields' => [],
            ],
            'executable pandoc: else after if arch(wasm32)' => [
                'sourceDirectories' => [],
                'ghcOptions' => ['-eventlog'],
                'cppOptions' => [],
                'buildDepends' => [],
                'otherModules' => [],
                'nativeSystemFields' => [],
            ],
            'executable pandoc: if flag(nightly)' => [
                'sourceDirectories' => [],
                'ghcOptions' => [],
                'cppOptions' => ['-DNIGHTLY'],
                'buildDepends' => [
                    'nightly-audit >= 0.1 && < 0.2',
                    'template-haskell',
                ],
                'otherModules' => [],
                'nativeSystemFields' => [],
            ],
            'executable pandoc: if flag(server)' => [
                'sourceDirectories' => ['server', 'generated-server'],
                'ghcOptions' => [],
                'cppOptions' => [],
                'buildDepends' => [
                    'pandoc-server >= 0.1.0 && < 0.2',
                    'pandoc-server-audit >= 0.1 && < 0.2',
                    'warp',
                ],
                'otherModules' => [],
                'nativeSystemFields' => [],
            ],
            'executable pandoc: else after if flag(server)' => [
                'sourceDirectories' => ['no-server'],
                'ghcOptions' => [],
                'cppOptions' => [],
                'buildDepends' => [],
                'otherModules' => [],
                'nativeSystemFields' => [],
            ],
            'executable pandoc: if flag(lua)' => [
                'sourceDirectories' => ['lua'],
                'ghcOptions' => [],
                'cppOptions' => [],
                'buildDepends' => ['pandoc-lua-engine >= 0.5.0 && < 0.6'],
                'otherModules' => [],
                'nativeSystemFields' => [],
            ],
            'executable pandoc: else after if flag(lua)' => [
                'sourceDirectories' => ['generated-no-lua'],
                'ghcOptions' => [],
                'cppOptions' => [],
                'buildDepends' => [],
                'otherModules' => [],
                'nativeSystemFields' => [],
            ],
            'executable pandoc: if flag(repl)' => [
                'sourceDirectories' => [],
                'ghcOptions' => [],
                'cppOptions' => ['-DREPL_GENERATED'],
                'buildDepends' => ['hslua-cli >= 1.4.1 && < 1.5'],
                'otherModules' => [],
                'nativeSystemFields' => [],
            ],
        ], $audit['cliExecutableClosure']['presentConditionalFieldClosure']);
        $t->same([
            'executable pandoc: if arch(wasm32)' => [
                'hs-source-dirs: wasm',
                'cpp-options: -DINCLUDE_WASM',
                'build-depends: filepath',
                'build-depends: pandoc-lua-engine',
                'other-modules: PandocWasm',
            ],
            'executable pandoc: else after if arch(wasm32)' => ['ghc-options: -threaded'],
            'executable pandoc: if flag(nightly)' => ['build-depends: time'],
            'executable pandoc: if flag(server)' => [
                'build-depends: pandoc-server >= 0.1.1 && < 0.2',
                'build-depends: safe',
                'build-depends: wai-extra >= 3.0.24',
            ],
            'executable pandoc: if flag(lua)' => ['build-depends: pandoc-lua-engine >= 0.5.1 && < 0.6'],
            'executable pandoc: else after if flag(lua)' => ['hs-source-dirs: no-lua'],
            'executable pandoc: if flag(repl)' => [
                'cpp-options: -DREPL',
                'build-depends: temporary >= 1.1 && < 1.4',
            ],
        ], $audit['cliExecutableClosure']['missingConditionalFieldEntries']);
        $t->same([
            'executable pandoc: if arch(wasm32)' => [
                'hs-source-dirs: generated-wasm',
                'cpp-options: -DINCLUDE_GENERATED_WASM',
                'build-depends: pandoc-wasm-audit >= 0.1 && < 0.2',
                'other-modules: PandocGeneratedWasm',
            ],
            'executable pandoc: else after if arch(wasm32)' => ['ghc-options: -eventlog'],
            'executable pandoc: if flag(nightly)' => ['build-depends: nightly-audit >= 0.1 && < 0.2'],
            'executable pandoc: if flag(server)' => [
                'hs-source-dirs: generated-server',
                'build-depends: pandoc-server >= 0.1.0 && < 0.2',
                'build-depends: pandoc-server-audit >= 0.1 && < 0.2',
            ],
            'executable pandoc: if flag(lua)' => ['build-depends: pandoc-lua-engine >= 0.5.0 && < 0.6'],
            'executable pandoc: else after if flag(lua)' => ['hs-source-dirs: generated-no-lua'],
            'executable pandoc: if flag(repl)' => ['cpp-options: -DREPL_GENERATED'],
        ], $audit['cliExecutableClosure']['unexpectedConditionalFieldEntries']);

        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('missing pandoc-cli executable conditional branch fields: executable pandoc: if arch(wasm32) (hs-source-dirs: wasm, cpp-options: -DINCLUDE_WASM, build-depends: filepath, build-depends: pandoc-lua-engine, other-modules: PandocWasm)', $blocked);
        $t->contains('executable pandoc: if flag(server) (build-depends: pandoc-server >= 0.1.1 && < 0.2, build-depends: safe, build-depends: wai-extra >= 3.0.24)', $blocked);
        $t->contains('executable pandoc: if flag(repl) (cpp-options: -DREPL, build-depends: temporary >= 1.1 && < 1.4)', $blocked);
        $t->contains('unexpected pandoc-cli executable conditional branch fields: executable pandoc: if arch(wasm32) (hs-source-dirs: generated-wasm, cpp-options: -DINCLUDE_GENERATED_WASM, build-depends: pandoc-wasm-audit >= 0.1 && < 0.2, other-modules: PandocGeneratedWasm)', $blocked);
        $t->contains('executable pandoc: if flag(server) (hs-source-dirs: generated-server, build-depends: pandoc-server >= 0.1.0 && < 0.2, build-depends: pandoc-server-audit >= 0.1 && < 0.2)', $blocked);
        $t->contains('executable pandoc: if flag(lua) (build-depends: pandoc-lua-engine >= 0.5.0 && < 0.6)', $blocked);
        $t->contains('exact pandoc-cli conditional branch field bodies', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks lua engine library exposed-module drift before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles, $luaCabal): void {
        $luaPackage = str_replace(
            '  exposed-modules: Text.Pandoc.Lua',
            '  exposed-modules: Text.Pandoc.Lua.Generated',
            $luaCabal()
        );

        $root = $makeTree($requiredFiles(
            $pinnedProject(),
            null,
            $luaPackage
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
        $t->same([], $audit['runnerDependencyClosure']['missingTargets']);
        $t->same([], $audit['runnerDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['runnerDependencyClosure']['missingDependencies']);
        $t->same([], $audit['runnerDependencyClosure']['missingExecutableOptions']);
        $t->same([], $audit['runnerDependencyClosure']['mismatchedDefaultLanguages']);
        $t->same([], $audit['runnerDependencyClosure']['missingOtherModules']);
        $t->same([], $audit['luaEngineLibraryClosure']['missingDependencies']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedDependencies']);
        $t->same(UpstreamRunnerDependencyAudit::expectedLuaEngineLibraryExposedModules(), $audit['luaEngineLibraryClosure']['expectedExposedModules']);
        $t->same(['Text.Pandoc.Lua.Generated'], $audit['luaEngineLibraryClosure']['presentExposedModules']);
        $t->same(['Text.Pandoc.Lua'], $audit['luaEngineLibraryClosure']['missingExposedModules']);
        $t->same(['Text.Pandoc.Lua.Generated'], $audit['luaEngineLibraryClosure']['unexpectedExposedModules']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedDefaultExtensions']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedOtherExtensions']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedConditionalBranches']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedNativeSystemFields']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('missing pandoc-lua-engine library exposed-modules: Text.Pandoc.Lua', $blocked);
        $t->contains('unexpected pandoc-lua-engine library exposed-modules: Text.Pandoc.Lua.Generated', $blocked);
        $t->contains('exact pandoc-lua-engine library exposed-modules closure', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks lua engine library other-module drift before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles, $luaCabal): void {
        $luaPackage = str_replace(
            "    Text.Pandoc.Lua.Engine,\n",
            '',
            $luaCabal()
        );
        $luaPackage = str_replace(
            'Text.Pandoc.Lua.Writer.Scaffolding',
            'Text.Pandoc.Lua.Generated.Runner',
            $luaPackage
        );

        $root = $makeTree($requiredFiles(
            $pinnedProject(),
            null,
            $luaPackage
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
        $t->same([], $audit['runnerDependencyClosure']['missingTargets']);
        $t->same([], $audit['runnerDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['runnerDependencyClosure']['missingDependencies']);
        $t->same([], $audit['runnerDependencyClosure']['missingExecutableOptions']);
        $t->same([], $audit['runnerDependencyClosure']['mismatchedDefaultLanguages']);
        $t->same([], $audit['runnerDependencyClosure']['missingOtherModules']);
        $t->same([], $audit['luaEngineLibraryClosure']['missingDependencies']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedDependencies']);
        $t->same([], $audit['luaEngineLibraryClosure']['missingExposedModules']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedExposedModules']);
        $t->same(UpstreamRunnerDependencyAudit::expectedLuaEngineLibraryOtherModules(), $audit['luaEngineLibraryClosure']['expectedOtherModules']);
        $t->same(false, in_array('Text.Pandoc.Lua.Engine', $audit['luaEngineLibraryClosure']['presentOtherModules'], true));
        $t->same(true, in_array('Text.Pandoc.Lua.Generated.Runner', $audit['luaEngineLibraryClosure']['presentOtherModules'], true));
        $t->same([
            'Text.Pandoc.Lua.Engine',
            'Text.Pandoc.Lua.Writer.Scaffolding',
        ], $audit['luaEngineLibraryClosure']['missingOtherModules']);
        $t->same([
            'Text.Pandoc.Lua.Generated.Runner',
        ], $audit['luaEngineLibraryClosure']['unexpectedOtherModules']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('missing pandoc-lua-engine library other-modules: Text.Pandoc.Lua.Engine, Text.Pandoc.Lua.Writer.Scaffolding', $blocked);
        $t->contains('unexpected pandoc-lua-engine library other-modules: Text.Pandoc.Lua.Generated.Runner', $blocked);
        $t->contains('exact pandoc-lua-engine library other-modules closure', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks lua engine library source artifact drift before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        unset($files['pandoc-lua-engine/src/Text/Pandoc/Lua/Engine.hs']);
        unset($files['pandoc-lua-engine/src/Text/Pandoc/Lua/Writer/Classic.hs']);
        $files['pandoc-lua-engine/src/Text/Pandoc/Lua/Marshal/Template.hs'] = '';
        $files['pandoc-lua-engine/src/Text/Pandoc/Lua/Writer/Classic.hs/.audit-keep'] = 'wrong source artifact type';

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
        $t->same([], $audit['runnerDependencyClosure']['missingTargets']);
        $t->same([], $audit['runnerDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['runnerDependencyClosure']['missingDependencies']);
        $t->same([], $audit['runnerDependencyClosure']['missingOtherModules']);
        $t->same([], $audit['luaEngineLibraryClosure']['missingOtherModules']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedOtherModules']);
        $t->same(['pandoc-lua-engine/src/Text/Pandoc/Lua/Engine.hs'], $audit['luaEngineLibraryClosure']['missingSourceArtifacts']);
        $t->same([
            'expected' => 'file',
            'actual' => 'directory',
        ], $audit['luaEngineLibraryClosure']['wrongTypeSourceArtifacts']['pandoc-lua-engine/src/Text/Pandoc/Lua/Writer/Classic.hs']);
        $t->same(['pandoc-lua-engine/src/Text/Pandoc/Lua/Marshal/Template.hs'], $audit['luaEngineLibraryClosure']['emptySourceArtifacts']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('missing pandoc-lua-engine library source artifacts: pandoc-lua-engine/src/Text/Pandoc/Lua/Engine.hs', $blocked);
        $t->contains('mismatched pandoc-lua-engine library source artifact types: pandoc-lua-engine/src/Text/Pandoc/Lua/Writer/Classic.hs expected file, found directory', $blocked);
        $t->contains('empty pandoc-lua-engine library source artifacts: pandoc-lua-engine/src/Text/Pandoc/Lua/Marshal/Template.hs', $blocked);
        $t->contains('non-empty pandoc-lua-engine library source artifacts with artifact hashes', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks unexpected lua engine library extension drift before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles, $luaCabal): void {
        $luaPackage = str_replace(
            "library\n  import: test-options",
            implode("\n", [
                'library',
                '  import: test-options',
                '  default-extensions:',
                '    LambdaCase,',
                '    OverloadedStrings',
                '  extensions: TypeApplications',
                '  other-extensions: DeriveGeneric FlexibleContexts',
            ]),
            $luaCabal()
        );

        $root = $makeTree($requiredFiles(
            $pinnedProject(),
            null,
            $luaPackage
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
        $t->same([], $audit['runnerDependencyClosure']['unexpectedDefaultExtensions']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedOtherExtensions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedDefaultExtensions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedOtherExtensions']);
        $t->same([], $audit['luaEngineLibraryClosure']['missingDependencies']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedDependencies']);
        $t->same(UpstreamRunnerDependencyAudit::expectedLuaEngineLibraryDefaultExtensions(), $audit['luaEngineLibraryClosure']['expectedDefaultExtensions']);
        $t->same(UpstreamRunnerDependencyAudit::expectedLuaEngineLibraryOtherExtensions(), $audit['luaEngineLibraryClosure']['expectedOtherExtensions']);
        $t->same([
            'LambdaCase',
            'OverloadedStrings',
            'TypeApplications',
        ], $audit['luaEngineLibraryClosure']['presentDefaultExtensions']);
        $t->same([
            'DeriveGeneric',
            'FlexibleContexts',
        ], $audit['luaEngineLibraryClosure']['presentOtherExtensions']);
        $t->same($audit['luaEngineLibraryClosure']['presentDefaultExtensions'], $audit['luaEngineLibraryClosure']['unexpectedDefaultExtensions']);
        $t->same($audit['luaEngineLibraryClosure']['presentOtherExtensions'], $audit['luaEngineLibraryClosure']['unexpectedOtherExtensions']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('unexpected pandoc-lua-engine library default-extensions: LambdaCase, OverloadedStrings, TypeApplications', $blocked);
        $t->contains('unexpected pandoc-lua-engine library other-extensions: DeriveGeneric, FlexibleContexts', $blocked);
        $t->contains('no unexpected pandoc-lua-engine library default/other extensions', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks unexpected lua engine library conditional branches before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles, $luaCabal): void {
        $luaPackage = str_replace(
            "library\n  import: test-options",
            implode("\n", [
                'library',
                '  import: test-options',
                '  if flag(repl)',
                '    build-depends: hslua-repl',
                '  if os(windows)',
                '    build-depends: Win32',
                '  else',
                '    build-depends: unix',
            ]),
            $luaCabal()
        );

        $root = $makeTree($requiredFiles(
            $pinnedProject(),
            null,
            $luaPackage
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
        $t->same([], $audit['runnerDependencyClosure']['unexpectedConditionalBranches']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedConditionalBranches']);
        $t->same([], $audit['luaEngineLibraryClosure']['missingDependencies']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedDependencies']);
        $t->same([
            'library default: if flag(repl)',
        ], $audit['luaEngineLibraryClosure']['allowedConditionalBranches']);
        $t->same([
            'library default: if flag(repl)',
            'library default: if os(windows)',
            'library default: else',
        ], $audit['luaEngineLibraryClosure']['presentConditionalBranches']);
        $t->same([
            'library default: if os(windows)',
            'library default: else',
        ], $audit['luaEngineLibraryClosure']['unexpectedConditionalBranches']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('unexpected pandoc-lua-engine library conditional branches: library default: if os(windows), library default: else', $blocked);
        $t->contains('no unexpected pandoc-lua-engine library conditional branches', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks lua engine library default-language drift before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles, $luaCabal): void {
        $luaPackage = str_replace(
            '  default-language: Haskell2010',
            '  default-language: Haskell98',
            $luaCabal()
        );
        $luaPackage = str_replace(
            "  type: exitcode-stdio-1.0\n  main-is: test-pandoc-lua-engine.hs",
            "  type: exitcode-stdio-1.0\n  default-language: Haskell2010\n  main-is: test-pandoc-lua-engine.hs",
            $luaPackage
        );

        $root = $makeTree($requiredFiles(
            $pinnedProject(),
            null,
            $luaPackage
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
        $t->same([], $audit['runnerDependencyClosure']['mismatchedDefaultLanguages']);
        $t->same([], $audit['runnerDependencyClosure']['missingDependencies']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedDependencies']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedDefaultLanguages']);
        $t->same([], $audit['luaEngineLibraryClosure']['missingDependencies']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedDependencies']);
        $t->same(UpstreamRunnerDependencyAudit::expectedLuaEngineLibraryDefaultLanguage(), $audit['luaEngineLibraryClosure']['expectedDefaultLanguage']);
        $t->same('Haskell98', $audit['luaEngineLibraryClosure']['presentDefaultLanguage']);
        $t->same([
            'expected' => 'Haskell2010',
            'actual' => 'Haskell98',
        ], $audit['luaEngineLibraryClosure']['mismatchedDefaultLanguage']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedDefaultExtensions']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedOtherExtensions']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedExtraSourceFiles']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedExtraDocFiles']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedExtraTmpFiles']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedDataFiles']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedConditionalBranches']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedNativeSystemFields']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('mismatched pandoc-lua-engine library default-language: expected Haskell2010, found Haskell98', $blocked);
        $t->contains('Haskell2010 pandoc-lua-engine library default-language', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks unexpected lua engine library native system fields before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles, $luaCabal): void {
        $luaPackage = str_replace(
            "library\n  import: test-options",
            implode("\n", [
                'library',
                '  import: test-options',
                '  c-sources: cbits/lua-runner-audit.c',
                '  extra-libraries: lua5.4 pandoclua',
                '  pkgconfig-depends: lua >= 5.4',
                '  hsc2hs-options:',
                '    --cross-compile',
                '    --template=cbits/lua-template.hsc',
                '  ld-options: -Wl,--as-needed',
            ]),
            $luaCabal()
        );

        $root = $makeTree($requiredFiles(
            $pinnedProject(),
            null,
            $luaPackage
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
        $t->same([], $audit['runnerDependencyClosure']['unexpectedNativeSystemFields']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedNativeSystemFields']);
        $t->same([], $audit['luaEngineLibraryClosure']['missingDependencies']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedDependencies']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedDefaultExtensions']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedOtherExtensions']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedConditionalBranches']);
        $t->same([
            'c-sources' => ['cbits/lua-runner-audit.c'],
            'extra-libraries' => ['lua5.4', 'pandoclua'],
            'hsc2hs-options' => ['--cross-compile', '--template=cbits/lua-template.hsc'],
            'ld-options' => ['-Wl,--as-needed'],
            'pkgconfig-depends' => ['lua >= 5.4'],
        ], $audit['luaEngineLibraryClosure']['presentNativeSystemFields']);
        $t->same([
            'c-sources: cbits/lua-runner-audit.c',
            'extra-libraries: lua5.4',
            'extra-libraries: pandoclua',
            'hsc2hs-options: --cross-compile',
            'hsc2hs-options: --template=cbits/lua-template.hsc',
            'ld-options: -Wl,--as-needed',
            'pkgconfig-depends: lua >= 5.4',
        ], $audit['luaEngineLibraryClosure']['unexpectedNativeSystemFields']);

        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('unexpected pandoc-lua-engine library native/system dependencies: c-sources: cbits/lua-runner-audit.c, extra-libraries: lua5.4, extra-libraries: pandoclua, hsc2hs-options: --cross-compile, hsc2hs-options: --template=cbits/lua-template.hsc, ld-options: -Wl,--as-needed, pkgconfig-depends: lua >= 5.4', $blocked);
        $t->contains('no unexpected pandoc-lua-engine library native/system dependency fields', $audit['activationGate']);
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
    'blocks runner and benchmark common import drift before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $runnerConstraints = UpstreamRunnerDependencyAudit::expectedRunnerDependencyConstraints()['test:test-pandoc'];
        $runnerCommon = implode("\n", [
            'common runner-common',
            '  build-depends: base ' . $runnerConstraints['base'] . ', pandoc',
            '  default-language: Haskell2010',
            '',
        ]);
        $files['pandoc.cabal'] = str_replace(
            "common common-executable\n  import: common-options",
            $runnerCommon . 'common common-executable' . "\n" . '  import: runner-common generated-common',
            $files['pandoc.cabal']
        );
        $files['pandoc.cabal'] = str_replace(
            "test-suite test-pandoc\n  import: common-executable",
            implode("\n", [
                'test-suite test-pandoc',
                '  import: common-executable generated-runner',
            ]),
            $files['pandoc.cabal']
        );
        $files['pandoc.cabal'] = str_replace(
            "benchmark benchmark-pandoc\n  import: common-executable",
            implode("\n", [
                'benchmark benchmark-pandoc',
                '  import: common-executable generated-benchmark',
            ]),
            $files['pandoc.cabal']
        );
        $files['pandoc-lua-engine/pandoc-lua-engine.cabal'] = str_replace(
            "test-suite test-pandoc-lua-engine\n  import: test-options",
            implode("\n", [
                'test-suite test-pandoc-lua-engine',
                '  import: test-options generated-lua',
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
        $t->same(UpstreamRunnerDependencyAudit::expectedRunnerCommonImports(), $audit['runnerDependencyClosure']['expectedCommonImports']);
        $t->same(UpstreamRunnerDependencyAudit::expectedBenchmarkCommonImports(), $audit['benchmarkDependencyClosure']['expectedCommonImports']);
        $t->same([], $audit['runnerDependencyClosure']['missingDependencies']);
        $t->same([], $audit['runnerDependencyClosure']['mismatchedDependencyConstraints']);
        $t->same([], $audit['runnerDependencyClosure']['missingExecutableOptions']);
        $t->same([], $audit['runnerDependencyClosure']['mismatchedDefaultLanguages']);
        $t->same([], $audit['runnerDependencyClosure']['missingOtherModules']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingDependencies']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedDependencyConstraints']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingExecutableOptions']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedDefaultLanguages']);
        $t->same([
            'common-executable',
            'runner-common',
            'generated-common',
            'generated-runner',
        ], $audit['runnerDependencyClosure']['present']['test:test-pandoc']['commonImports']);
        $t->same([
            'test-options',
            'generated-lua',
        ], $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['commonImports']);
        $t->same([
            'common-executable',
            'runner-common',
            'generated-common',
            'generated-benchmark',
        ], $audit['benchmarkDependencyClosure']['present'][$target]['commonImports']);
        $t->same([
            'common-options',
        ], $audit['runnerDependencyClosure']['missingCommonImports']['test:test-pandoc']);
        $t->same([
            'runner-common',
            'generated-common',
            'generated-runner',
        ], $audit['runnerDependencyClosure']['unexpectedCommonImports']['test:test-pandoc']);
        $t->same([
            'generated-common',
            'generated-runner',
        ], $audit['runnerDependencyClosure']['unresolvedCommonImports']['test:test-pandoc']);
        $t->same([
            'generated-lua',
        ], $audit['runnerDependencyClosure']['unexpectedCommonImports']['test:test-pandoc-lua-engine']);
        $t->same([
            'generated-lua',
        ], $audit['runnerDependencyClosure']['unresolvedCommonImports']['test:test-pandoc-lua-engine']);
        $t->same([
            'common-options',
        ], $audit['benchmarkDependencyClosure']['missingCommonImports'][$target]);
        $t->same([
            'runner-common',
            'generated-common',
            'generated-benchmark',
        ], $audit['benchmarkDependencyClosure']['unexpectedCommonImports'][$target]);
        $t->same([
            'generated-common',
            'generated-benchmark',
        ], $audit['benchmarkDependencyClosure']['unresolvedCommonImports'][$target]);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('missing Cabal runner common imports: test:test-pandoc (common-options)', $blocked);
        $t->contains('unexpected Cabal runner common imports: test:test-pandoc (runner-common, generated-common, generated-runner); test:test-pandoc-lua-engine (generated-lua)', $blocked);
        $t->contains('unresolved Cabal runner common imports: test:test-pandoc (generated-common, generated-runner); test:test-pandoc-lua-engine (generated-lua)', $blocked);
        $t->contains('missing Cabal benchmark common imports: benchmark:benchmark-pandoc (common-options)', $blocked);
        $t->contains('unexpected Cabal benchmark common imports: benchmark:benchmark-pandoc (runner-common, generated-common, generated-benchmark)', $blocked);
        $t->contains('unresolved Cabal benchmark common imports: benchmark:benchmark-pandoc (generated-common, generated-benchmark)', $blocked);
        $t->contains('exact runner and benchmark common import closure', $audit['activationGate']);
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
    'blocks benchmark fixture semantic drift before planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['test/testsuite.txt'] = implode("\n", [
            '% Pandoc Test Suite',
            '# Headers',
            '# Images',
            '![lalune][]',
            '# Footnotes',
        ]);
        $files['test/lalune.jpg'] = 'not-a-jpeg';
        $files['test/movie.jpg'] = "\xff\xd8" . 'missing-eoi-marker';

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
        $t->same([], $audit['benchmarkArtifactClosure']['missing']);
        $t->same([], $audit['benchmarkArtifactClosure']['wrongType']);
        $t->same([], $audit['benchmarkArtifactClosure']['emptyFiles']);
        $t->same([
            'test/testsuite.txt' => [
                'contains code blocks section',
                'contains block quotes section',
                'contains lists section',
                'contains definition lists section',
                'contains html blocks section',
                'contains inline markup section',
                'contains smart punctuation section',
                'contains latex section',
                'contains special characters section',
                'contains links section',
                'contains lalune reference definition',
                'contains movie inline image',
            ],
            'test/lalune.jpg' => [
                'has jpeg soi marker',
                'has jpeg eoi marker',
            ],
            'test/movie.jpg' => [
                'has jpeg eoi marker',
            ],
        ], $audit['benchmarkArtifactClosure']['missingSemantics']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('missing upstream benchmark fixture semantics', $blocked);
        $t->contains('test/testsuite.txt (contains code blocks section', $blocked);
        $t->contains('test/lalune.jpg (has jpeg soi marker, has jpeg eoi marker)', $blocked);
        $t->contains('test/movie.jpg (has jpeg eoi marker)', $blocked);
        $t->contains('benchmark source/data artifacts with artifact hashes and fixture semantics', $audit['activationGate']);
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
    'blocks benchmark format registry source semantic drift before planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['benchmark/benchmark-pandoc.hs'] = str_replace(
            [
                '    [ env getImages $ \imgs ->',
                '      bgroup "writers" $ mapMaybe (writerBench imgs doc . fst) (sortOn fst writers :: [(T.Text, Writer PandocPure)])',
                '    , bgroup "readers" $ mapMaybe (readerBench doc . fst) (sortOn fst readers :: [(T.Text, Reader PandocPure)])',
            ],
            [
                '    [',
                '      bgroup "writers" []',
                '    , bgroup "readers" []',
            ],
            $files['benchmark/benchmark-pandoc.hs']
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
        $t->same([], $audit['benchmarkArtifactClosure']['missing']);
        $t->same([], $audit['benchmarkEntrySourceClosure']['missingTargets']);
        $t->same([
            'wraps writer benchmarks in media environment',
            'maps writers from registry into benchmark group',
            'maps readers from registry into benchmark group',
        ], $audit['benchmarkEntrySourceClosure']['missingSemantics'][$target]);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('missing benchmark entry point source semantics', $blocked);
        $t->contains('wraps writer benchmarks in media environment', $blocked);
        $t->contains('maps writers from registry into benchmark group', $blocked);
        $t->contains('maps readers from registry into benchmark group', $blocked);
        $t->contains('benchmark entry-point source semantics', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks roff manual format registry source drift before planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['src/Text/Pandoc/Readers.hs'] = str_replace(
            [
                ', readMan',
                'import Text.Pandoc.Readers.Man',
                '("man" , TextReader readMan)',
            ],
            [
                ', readMdoc',
                'import Text.Pandoc.Readers.Mdoc',
                '("mdoc" , TextReader readMdoc)',
            ],
            $files['src/Text/Pandoc/Readers.hs']
        );
        $files['src/Text/Pandoc/Writers.hs'] = str_replace(
            [
                ', writeMan',
                ', writeMs',
                'import Text.Pandoc.Writers.Man',
                'import Text.Pandoc.Writers.Ms',
                '("man" , TextWriter writeMan)',
                '("ms" , TextWriter writeMs)',
            ],
            [
                ', writeMarkdown',
                ', writeMarkdownStrict',
                'import Text.Pandoc.Writers.Markdown',
                'import Text.Pandoc.Writers.Markdown',
                '("markdown" , TextWriter writeMarkdown)',
                '("markdown_strict" , TextWriter writeMarkdown)',
            ],
            $files['src/Text/Pandoc/Writers.hs']
        );
        $files['src/Text/Pandoc/Format.hs'] = str_replace(
            [
                '".ms" -> defFlavor "ms"',
                '".roff" -> defFlavor "ms"',
                '[\'.\',y] | y `elem` [\'1\'..\'9\'] -> defFlavor "man"',
            ],
            [
                '".me" -> defFlavor "ms"',
                '".tr" -> defFlavor "troff"',
                '[\'.\',y] | y == \'0\' -> defFlavor "man"',
            ],
            $files['src/Text/Pandoc/Format.hs']
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
        $t->same(array_keys(UpstreamRunnerDependencyAudit::expectedFormatRegistrySourceArtifacts()), $audit['formatRegistrySourceClosure']['present']);
        $t->same([], $audit['formatRegistrySourceClosure']['missing']);
        $t->same([], $audit['formatRegistrySourceClosure']['wrongType']);
        $t->same([], $audit['formatRegistrySourceClosure']['emptyFiles']);
        $t->same([
            'exports roff man reader',
            'imports roff man reader module',
            'registers man reader format',
        ], $audit['formatRegistrySourceClosure']['missingSemantics']['src/Text/Pandoc/Readers.hs']);
        $t->same([
            'exports roff man writer',
            'exports roff ms writer',
            'imports roff man writer module',
            'imports roff ms writer module',
            'registers man writer format',
            'registers ms writer format',
        ], $audit['formatRegistrySourceClosure']['missingSemantics']['src/Text/Pandoc/Writers.hs']);
        $t->same([
            'infers ms format from dot-ms files',
            'infers ms format from dot-roff files',
            'infers man format from numeric manual suffixes',
        ], $audit['formatRegistrySourceClosure']['missingSemantics']['src/Text/Pandoc/Format.hs']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('missing Pandoc roff/manual format registry source semantics', $blocked);
        $t->contains('registers man reader format', $blocked);
        $t->contains('registers ms writer format', $blocked);
        $t->contains('infers man format from numeric manual suffixes', $blocked);
        $t->contains('Pandoc format registry source artifacts', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks benchmark utf8 decode and mismatch error source drift before planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['benchmark/benchmark-pandoc.hs'] = str_replace(
            [
                '  inp <- UTF8.toText <$> B.readFile "test/testsuite.txt"',
                '      _ -> throwError $ PandocSomeError $ "text/bytestring format mismatch: " <> name',
                'nf (either (error . show) id . runPure . r def) mempty',
                'nf (either (error . show) id . runPure . r def{readerExtensions = rexts}) mempty',
            ],
            [
                "  raw <- B.readFile \"test/testsuite.txt\"\n  let inp = UTF8.toText raw",
                '      _ -> throwError $ PandocSomeError "benchmark format mismatch"',
                'nf (either (const mempty) id . runPure . r def) mempty',
                'nf (either (const mempty) id . runPure . r def{readerExtensions = rexts}) mempty',
            ],
            $files['benchmark/benchmark-pandoc.hs']
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
        $t->same([], $audit['benchmarkArtifactClosure']['missing']);
        $t->same([], $audit['benchmarkEntrySourceClosure']['missingTargets']);
        $t->same([
            'decodes benchmark markdown fixture as UTF-8',
            'raises reader benchmark failures with shown Pandoc errors',
            'reports text bytestring benchmark mismatches',
        ], $audit['benchmarkEntrySourceClosure']['missingSemantics'][$target]);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('missing benchmark entry point source semantics', $blocked);
        $t->contains('decodes benchmark markdown fixture as UTF-8', $blocked);
        $t->contains('raises reader benchmark failures with shown Pandoc errors', $blocked);
        $t->contains('reports text bytestring benchmark mismatches', $blocked);
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
    'blocks runner test-options and benchmark-options before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['pandoc.cabal'] = str_replace(
            "test-suite test-pandoc\n  import: common-executable",
            implode("\n", [
                'test-suite test-pandoc',
                '  import: common-executable',
                '  test-options: -p markdown -j1',
            ]),
            $files['pandoc.cabal']
        );
        $files['pandoc.cabal'] = str_replace(
            "benchmark benchmark-pandoc\n  import: common-executable",
            implode("\n", [
                'benchmark benchmark-pandoc',
                '  import: common-executable',
                '  benchmark-options: -o benchmark.csv -m mean',
            ]),
            $files['pandoc.cabal']
        );
        $files['pandoc-lua-engine/pandoc-lua-engine.cabal'] = str_replace(
            "test-suite test-pandoc-lua-engine\n  import: test-options",
            implode("\n", [
                'test-suite test-pandoc-lua-engine',
                '  import: test-options',
                '  test-options: -p lua',
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
        $t->same([], $audit['runnerDependencyClosure']['unexpectedBuildTools']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedDefaultExtensions']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedOtherExtensions']);
        $t->same([], $audit['runnerDependencyClosure']['missingOtherModules']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingTargets']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingDependencies']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedDependencyConstraints']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingExecutableOptions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedMixins']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedBuildTools']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedDefaultExtensions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedOtherExtensions']);
        $t->same(UpstreamRunnerDependencyAudit::expectedRunnerTestOptions(), $audit['runnerDependencyClosure']['expectedTestOptions']);
        $t->same(UpstreamRunnerDependencyAudit::expectedBenchmarkOptions(), $audit['benchmarkDependencyClosure']['expectedBenchmarkOptions']);
        $t->same([
            '-p',
            'markdown',
            '-j1',
        ], $audit['runnerDependencyClosure']['present']['test:test-pandoc']['testOptions']);
        $t->same([
            '-p',
            'lua',
        ], $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['testOptions']);
        $t->same([
            '-o',
            'benchmark.csv',
            '-m',
            'mean',
        ], $audit['benchmarkDependencyClosure']['present'][$target]['benchmarkOptions']);
        $t->same($audit['runnerDependencyClosure']['present']['test:test-pandoc']['testOptions'], $audit['runnerDependencyClosure']['unexpectedTestOptions']['test:test-pandoc']);
        $t->same($audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['testOptions'], $audit['runnerDependencyClosure']['unexpectedTestOptions']['test:test-pandoc-lua-engine']);
        $t->same($audit['benchmarkDependencyClosure']['present'][$target]['benchmarkOptions'], $audit['benchmarkDependencyClosure']['unexpectedBenchmarkOptions'][$target]);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('unexpected Cabal runner test-options: test:test-pandoc (-p, markdown, -j1); test:test-pandoc-lua-engine (-p, lua)', $blocked);
        $t->contains('unexpected Cabal benchmark options: benchmark:benchmark-pandoc (-o, benchmark.csv, -m, mean)', $blocked);
        $t->contains('no unexpected runner test-options', $audit['activationGate']);
        $t->contains('no unexpected benchmark-options', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks runner and benchmark default extension drift before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['pandoc.cabal'] = str_replace(
            "test-suite test-pandoc\n  import: common-executable",
            implode("\n", [
                'test-suite test-pandoc',
                '  import: common-executable',
                '  default-extensions:',
                '    CPP,',
                '    OverloadedStrings',
            ]),
            $files['pandoc.cabal']
        );
        $files['pandoc.cabal'] = str_replace(
            "benchmark benchmark-pandoc\n  import: common-executable",
            implode("\n", [
                'benchmark benchmark-pandoc',
                '  import: common-executable',
                '  default-extensions: DataKinds OverloadedStrings',
            ]),
            $files['pandoc.cabal']
        );
        $files['pandoc-lua-engine/pandoc-lua-engine.cabal'] = str_replace(
            "test-suite test-pandoc-lua-engine\n  import: test-options",
            implode("\n", [
                'test-suite test-pandoc-lua-engine',
                '  import: test-options',
                '  default-extensions: LambdaCase, TypeApplications',
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
        $t->same([], $audit['runnerDependencyClosure']['unexpectedBuildTools']);
        $t->same([], $audit['runnerDependencyClosure']['missingOtherModules']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingTargets']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingDependencies']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedDependencyConstraints']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingExecutableOptions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedMixins']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedBuildTools']);
        $t->same([
            'CPP',
            'OverloadedStrings',
        ], $audit['runnerDependencyClosure']['present']['test:test-pandoc']['defaultExtensions']);
        $t->same([
            'LambdaCase',
            'TypeApplications',
        ], $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['defaultExtensions']);
        $t->same([
            'DataKinds',
            'OverloadedStrings',
        ], $audit['benchmarkDependencyClosure']['present'][$target]['defaultExtensions']);
        $t->same($audit['runnerDependencyClosure']['present']['test:test-pandoc']['defaultExtensions'], $audit['runnerDependencyClosure']['unexpectedDefaultExtensions']['test:test-pandoc']);
        $t->same($audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['defaultExtensions'], $audit['runnerDependencyClosure']['unexpectedDefaultExtensions']['test:test-pandoc-lua-engine']);
        $t->same($audit['benchmarkDependencyClosure']['present'][$target]['defaultExtensions'], $audit['benchmarkDependencyClosure']['unexpectedDefaultExtensions'][$target]);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('unexpected Cabal runner default-extensions: test:test-pandoc (CPP, OverloadedStrings); test:test-pandoc-lua-engine (LambdaCase, TypeApplications)', $blocked);
        $t->contains('unexpected Cabal benchmark default-extensions: benchmark:benchmark-pandoc (DataKinds, OverloadedStrings)', $blocked);
        $t->contains('no unexpected runner or benchmark default-extensions', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks legacy cabal extensions field drift before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['pandoc.cabal'] = str_replace(
            "common common-executable\n  import: common-options",
            implode("\n", [
                'common common-executable',
                '  import: common-options',
                '  extensions:',
                '    CPP,',
                '    OverloadedStrings',
            ]),
            $files['pandoc.cabal']
        );
        $files['pandoc.cabal'] = str_replace(
            "benchmark benchmark-pandoc\n  import: common-executable",
            implode("\n", [
                'benchmark benchmark-pandoc',
                '  import: common-executable',
                '  extensions: DataKinds OverloadedStrings',
            ]),
            $files['pandoc.cabal']
        );
        $files['pandoc-lua-engine/pandoc-lua-engine.cabal'] = str_replace(
            "test-suite test-pandoc-lua-engine\n  import: test-options",
            implode("\n", [
                'test-suite test-pandoc-lua-engine',
                '  import: test-options',
                '  extensions: LambdaCase, TypeApplications',
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
        $t->same([], $audit['runnerDependencyClosure']['unexpectedBuildTools']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedOtherExtensions']);
        $t->same([], $audit['runnerDependencyClosure']['missingOtherModules']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingTargets']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingDependencies']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedDependencyConstraints']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingExecutableOptions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedMixins']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedBuildTools']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedOtherExtensions']);
        $t->same([
            'CPP',
            'OverloadedStrings',
        ], $audit['runnerDependencyClosure']['present']['test:test-pandoc']['defaultExtensions']);
        $t->same([
            'LambdaCase',
            'TypeApplications',
        ], $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['defaultExtensions']);
        $t->same([
            'CPP',
            'DataKinds',
            'OverloadedStrings',
        ], $audit['benchmarkDependencyClosure']['present'][$target]['defaultExtensions']);
        $t->same($audit['runnerDependencyClosure']['present']['test:test-pandoc']['defaultExtensions'], $audit['runnerDependencyClosure']['unexpectedDefaultExtensions']['test:test-pandoc']);
        $t->same($audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['defaultExtensions'], $audit['runnerDependencyClosure']['unexpectedDefaultExtensions']['test:test-pandoc-lua-engine']);
        $t->same($audit['benchmarkDependencyClosure']['present'][$target]['defaultExtensions'], $audit['benchmarkDependencyClosure']['unexpectedDefaultExtensions'][$target]);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('unexpected Cabal runner default-extensions: test:test-pandoc (CPP, OverloadedStrings); test:test-pandoc-lua-engine (LambdaCase, TypeApplications)', $blocked);
        $t->contains('unexpected Cabal benchmark default-extensions: benchmark:benchmark-pandoc (CPP, DataKinds, OverloadedStrings)', $blocked);
        $t->contains('no unexpected runner or benchmark default-extensions', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks runner and benchmark other extension drift before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['pandoc.cabal'] = str_replace(
            "test-suite test-pandoc\n  import: common-executable",
            implode("\n", [
                'test-suite test-pandoc',
                '  import: common-executable',
                '  other-extensions:',
                '    CPP,',
                '    OverloadedStrings',
            ]),
            $files['pandoc.cabal']
        );
        $files['pandoc.cabal'] = str_replace(
            "benchmark benchmark-pandoc\n  import: common-executable",
            implode("\n", [
                'benchmark benchmark-pandoc',
                '  import: common-executable',
                '  other-extensions: DeriveGeneric OverloadedStrings',
            ]),
            $files['pandoc.cabal']
        );
        $files['pandoc-lua-engine/pandoc-lua-engine.cabal'] = str_replace(
            "test-suite test-pandoc-lua-engine\n  import: test-options",
            implode("\n", [
                'test-suite test-pandoc-lua-engine',
                '  import: test-options',
                '  other-extensions: FlexibleContexts, TypeApplications',
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
        $t->same([], $audit['runnerDependencyClosure']['unexpectedBuildTools']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedDefaultExtensions']);
        $t->same([], $audit['runnerDependencyClosure']['missingOtherModules']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingTargets']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingDependencies']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedDependencyConstraints']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingExecutableOptions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedMixins']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedBuildTools']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedDefaultExtensions']);
        $t->same([
            'CPP',
            'OverloadedStrings',
        ], $audit['runnerDependencyClosure']['present']['test:test-pandoc']['otherExtensions']);
        $t->same([
            'FlexibleContexts',
            'TypeApplications',
        ], $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['otherExtensions']);
        $t->same([
            'DeriveGeneric',
            'OverloadedStrings',
        ], $audit['benchmarkDependencyClosure']['present'][$target]['otherExtensions']);
        $t->same($audit['runnerDependencyClosure']['present']['test:test-pandoc']['otherExtensions'], $audit['runnerDependencyClosure']['unexpectedOtherExtensions']['test:test-pandoc']);
        $t->same($audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['otherExtensions'], $audit['runnerDependencyClosure']['unexpectedOtherExtensions']['test:test-pandoc-lua-engine']);
        $t->same($audit['benchmarkDependencyClosure']['present'][$target]['otherExtensions'], $audit['benchmarkDependencyClosure']['unexpectedOtherExtensions'][$target]);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('unexpected Cabal runner other-extensions: test:test-pandoc (CPP, OverloadedStrings); test:test-pandoc-lua-engine (FlexibleContexts, TypeApplications)', $blocked);
        $t->contains('unexpected Cabal benchmark other-extensions: benchmark:benchmark-pandoc (DeriveGeneric, OverloadedStrings)', $blocked);
        $t->contains('no unexpected runner or benchmark other-extensions', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks runner and benchmark cpp option drift before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['pandoc.cabal'] = str_replace(
            "common common-executable\n  import: common-options",
            implode("\n", [
                'common common-executable',
                '  import: common-options',
                '  cpp-options:',
                '    -DWRITE_GOLDENS',
                '    -DREGENERATE_NATIVE',
            ]),
            $files['pandoc.cabal']
        );
        $files['pandoc.cabal'] = str_replace(
            "benchmark benchmark-pandoc\n  import: common-executable",
            implode("\n", [
                'benchmark benchmark-pandoc',
                '  import: common-executable',
                '  cpp-options: -DBENCHMARK_AUDIT',
            ]),
            $files['pandoc.cabal']
        );
        $files['pandoc-lua-engine/pandoc-lua-engine.cabal'] = str_replace(
            "common test-options\n  build-depends: base >= 4.12 && < 5",
            implode("\n", [
                'common test-options',
                '  build-depends: base >= 4.12 && < 5',
                '  cpp-options: -DLUA_ENGINE_AUDIT',
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
        $t->same([], $audit['runnerDependencyClosure']['unexpectedBuildTools']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedDefaultExtensions']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedOtherExtensions']);
        $t->same([], $audit['runnerDependencyClosure']['missingOtherModules']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingTargets']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingDependencies']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedDependencyConstraints']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingExecutableOptions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedMixins']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedBuildTools']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedDefaultExtensions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedOtherExtensions']);
        $t->same([
            '-DWRITE_GOLDENS',
            '-DREGENERATE_NATIVE',
        ], $audit['runnerDependencyClosure']['present']['test:test-pandoc']['cppOptions']);
        $t->same([
            '-DLUA_ENGINE_AUDIT',
        ], $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['cppOptions']);
        $t->same([
            '-DWRITE_GOLDENS',
            '-DREGENERATE_NATIVE',
            '-DBENCHMARK_AUDIT',
        ], $audit['benchmarkDependencyClosure']['present'][$target]['cppOptions']);
        $t->same($audit['runnerDependencyClosure']['present']['test:test-pandoc']['cppOptions'], $audit['runnerDependencyClosure']['unexpectedCppOptions']['test:test-pandoc']);
        $t->same($audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['cppOptions'], $audit['runnerDependencyClosure']['unexpectedCppOptions']['test:test-pandoc-lua-engine']);
        $t->same($audit['benchmarkDependencyClosure']['present'][$target]['cppOptions'], $audit['benchmarkDependencyClosure']['unexpectedCppOptions'][$target]);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('unexpected Cabal runner cpp-options: test:test-pandoc (-DWRITE_GOLDENS, -DREGENERATE_NATIVE); test:test-pandoc-lua-engine (-DLUA_ENGINE_AUDIT)', $blocked);
        $t->contains('unexpected Cabal benchmark cpp-options: benchmark:benchmark-pandoc (-DWRITE_GOLDENS, -DREGENERATE_NATIVE, -DBENCHMARK_AUDIT)', $blocked);
        $t->contains('no unexpected runner or benchmark cpp-options', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks runner and benchmark autogen module drift before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['pandoc.cabal'] = str_replace(
            "common common-executable\n  import: common-options",
            implode("\n", [
                'common common-executable',
                '  import: common-options',
                '  autogen-modules:',
                '    Paths_pandoc',
                '    Test.Generated',
            ]),
            $files['pandoc.cabal']
        );
        $files['pandoc.cabal'] = str_replace(
            "benchmark benchmark-pandoc\n  import: common-executable",
            implode("\n", [
                'benchmark benchmark-pandoc',
                '  import: common-executable',
                '  autogen-modules: Bench.Generated Paths_benchmark',
            ]),
            $files['pandoc.cabal']
        );
        $files['pandoc-lua-engine/pandoc-lua-engine.cabal'] = str_replace(
            "common test-options\n  build-depends: base >= 4.12 && < 5",
            implode("\n", [
                'common test-options',
                '  build-depends: base >= 4.12 && < 5',
                '  autogen-modules: Paths_pandoc_lua_engine, Tests.Lua.Generated',
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
        $t->same([], $audit['runnerDependencyClosure']['unexpectedBuildTools']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedDefaultExtensions']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedOtherExtensions']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedCppOptions']);
        $t->same([], $audit['runnerDependencyClosure']['missingOtherModules']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingTargets']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingDependencies']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedDependencyConstraints']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingExecutableOptions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedMixins']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedBuildTools']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedDefaultExtensions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedOtherExtensions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedCppOptions']);
        $t->same(UpstreamRunnerDependencyAudit::expectedRunnerAutogenModules(), $audit['runnerDependencyClosure']['expectedAutogenModules']);
        $t->same(UpstreamRunnerDependencyAudit::expectedBenchmarkAutogenModules(), $audit['benchmarkDependencyClosure']['expectedAutogenModules']);
        $t->same([
            'Paths_pandoc',
            'Test.Generated',
        ], $audit['runnerDependencyClosure']['present']['test:test-pandoc']['autogenModules']);
        $t->same([
            'Paths_pandoc_lua_engine',
            'Tests.Lua.Generated',
        ], $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['autogenModules']);
        $t->same([
            'Bench.Generated',
            'Paths_benchmark',
            'Paths_pandoc',
            'Test.Generated',
        ], $audit['benchmarkDependencyClosure']['present'][$target]['autogenModules']);
        $t->same($audit['runnerDependencyClosure']['present']['test:test-pandoc']['autogenModules'], $audit['runnerDependencyClosure']['unexpectedAutogenModules']['test:test-pandoc']);
        $t->same($audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['autogenModules'], $audit['runnerDependencyClosure']['unexpectedAutogenModules']['test:test-pandoc-lua-engine']);
        $t->same($audit['benchmarkDependencyClosure']['present'][$target]['autogenModules'], $audit['benchmarkDependencyClosure']['unexpectedAutogenModules'][$target]);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('unexpected Cabal runner autogen-modules: test:test-pandoc (Paths_pandoc, Test.Generated); test:test-pandoc-lua-engine (Paths_pandoc_lua_engine, Tests.Lua.Generated)', $blocked);
        $t->contains('unexpected Cabal benchmark autogen-modules: benchmark:benchmark-pandoc (Bench.Generated, Paths_benchmark, Paths_pandoc, Test.Generated)', $blocked);
        $t->contains('no unexpected runner or benchmark autogen-modules', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks runner and benchmark reexported module drift before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['pandoc.cabal'] = str_replace(
            "common common-executable\n  import: common-options",
            implode("\n", [
                'common common-executable',
                '  import: common-options',
                '  reexported-modules:',
                '    Text.Pandoc.Definition',
                '    Text.Pandoc.Builder',
            ]),
            $files['pandoc.cabal']
        );
        $files['pandoc.cabal'] = str_replace(
            "benchmark benchmark-pandoc\n  import: common-executable",
            implode("\n", [
                'benchmark benchmark-pandoc',
                '  import: common-executable',
                '  reexported-modules: Text.Pandoc.Benchmark',
            ]),
            $files['pandoc.cabal']
        );
        $files['pandoc-lua-engine/pandoc-lua-engine.cabal'] = str_replace(
            "common test-options\n  build-depends: base >= 4.12 && < 5",
            implode("\n", [
                'common test-options',
                '  build-depends: base >= 4.12 && < 5',
                '  reexported-modules: Text.Pandoc.Lua.Module, Text.Pandoc.Lua.Writer',
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
        $t->same([], $audit['runnerDependencyClosure']['unexpectedBuildTools']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedDefaultExtensions']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedOtherExtensions']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedCppOptions']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedAutogenModules']);
        $t->same([], $audit['runnerDependencyClosure']['missingOtherModules']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingTargets']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingDependencies']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedDependencyConstraints']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingExecutableOptions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedMixins']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedBuildTools']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedDefaultExtensions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedOtherExtensions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedCppOptions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedAutogenModules']);
        $t->same(UpstreamRunnerDependencyAudit::expectedRunnerReexportedModules(), $audit['runnerDependencyClosure']['expectedReexportedModules']);
        $t->same(UpstreamRunnerDependencyAudit::expectedBenchmarkReexportedModules(), $audit['benchmarkDependencyClosure']['expectedReexportedModules']);
        $t->same([
            'Text.Pandoc.Builder',
            'Text.Pandoc.Definition',
        ], $audit['runnerDependencyClosure']['present']['test:test-pandoc']['reexportedModules']);
        $t->same([
            'Text.Pandoc.Lua.Module',
            'Text.Pandoc.Lua.Writer',
        ], $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['reexportedModules']);
        $t->same([
            'Text.Pandoc.Benchmark',
            'Text.Pandoc.Builder',
            'Text.Pandoc.Definition',
        ], $audit['benchmarkDependencyClosure']['present'][$target]['reexportedModules']);
        $t->same($audit['runnerDependencyClosure']['present']['test:test-pandoc']['reexportedModules'], $audit['runnerDependencyClosure']['unexpectedReexportedModules']['test:test-pandoc']);
        $t->same($audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['reexportedModules'], $audit['runnerDependencyClosure']['unexpectedReexportedModules']['test:test-pandoc-lua-engine']);
        $t->same($audit['benchmarkDependencyClosure']['present'][$target]['reexportedModules'], $audit['benchmarkDependencyClosure']['unexpectedReexportedModules'][$target]);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('unexpected Cabal runner reexported-modules: test:test-pandoc (Text.Pandoc.Builder, Text.Pandoc.Definition); test:test-pandoc-lua-engine (Text.Pandoc.Lua.Module, Text.Pandoc.Lua.Writer)', $blocked);
        $t->contains('unexpected Cabal benchmark reexported-modules: benchmark:benchmark-pandoc (Text.Pandoc.Benchmark, Text.Pandoc.Builder, Text.Pandoc.Definition)', $blocked);
        $t->contains('no unexpected runner or benchmark reexported-modules', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks runner and benchmark module interface drift before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['pandoc.cabal'] = str_replace(
            "common common-executable\n  import: common-options",
            implode("\n", [
                'common common-executable',
                '  import: common-options',
                '  signatures:',
                '    Tests.RunnerSignature',
                '  virtual-modules: Tests.VirtualRunner',
            ]),
            $files['pandoc.cabal']
        );
        $files['pandoc.cabal'] = str_replace(
            "benchmark benchmark-pandoc\n  import: common-executable",
            implode("\n", [
                'benchmark benchmark-pandoc',
                '  import: common-executable',
                '  signatures: Benchmark.Signature',
                '  virtual-modules: Benchmark.Virtual',
            ]),
            $files['pandoc.cabal']
        );
        $files['pandoc-lua-engine/pandoc-lua-engine.cabal'] = str_replace(
            "common test-options\n  build-depends: base >= 4.12 && < 5",
            implode("\n", [
                'common test-options',
                '  build-depends: base >= 4.12 && < 5',
                '  signatures: Lua.RunnerSignature',
                '  virtual-modules: Lua.VirtualRunner',
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
        $t->same([], $audit['runnerDependencyClosure']['unexpectedBuildTools']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedDefaultExtensions']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedOtherExtensions']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedCppOptions']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedAutogenModules']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedReexportedModules']);
        $t->same([], $audit['runnerDependencyClosure']['missingOtherModules']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingTargets']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingDependencies']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedDependencyConstraints']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingExecutableOptions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedMixins']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedBuildTools']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedDefaultExtensions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedOtherExtensions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedCppOptions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedAutogenModules']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedReexportedModules']);
        $t->same(UpstreamRunnerDependencyAudit::expectedRunnerModuleInterfaceFields(), $audit['runnerDependencyClosure']['expectedModuleInterfaceFields']);
        $t->same(UpstreamRunnerDependencyAudit::expectedBenchmarkModuleInterfaceFields(), $audit['benchmarkDependencyClosure']['expectedModuleInterfaceFields']);
        $t->same([
            'signatures' => ['Tests.RunnerSignature'],
            'virtual-modules' => ['Tests.VirtualRunner'],
        ], $audit['runnerDependencyClosure']['present']['test:test-pandoc']['moduleInterfaceFields']);
        $t->same([
            'signatures' => ['Lua.RunnerSignature'],
            'virtual-modules' => ['Lua.VirtualRunner'],
        ], $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['moduleInterfaceFields']);
        $t->same([
            'signatures' => ['Benchmark.Signature', 'Tests.RunnerSignature'],
            'virtual-modules' => ['Benchmark.Virtual', 'Tests.VirtualRunner'],
        ], $audit['benchmarkDependencyClosure']['present'][$target]['moduleInterfaceFields']);
        $t->same([
            'signatures: Tests.RunnerSignature',
            'virtual-modules: Tests.VirtualRunner',
        ], $audit['runnerDependencyClosure']['unexpectedModuleInterfaceFields']['test:test-pandoc']);
        $t->same([
            'signatures: Lua.RunnerSignature',
            'virtual-modules: Lua.VirtualRunner',
        ], $audit['runnerDependencyClosure']['unexpectedModuleInterfaceFields']['test:test-pandoc-lua-engine']);
        $t->same([
            'signatures: Benchmark.Signature',
            'signatures: Tests.RunnerSignature',
            'virtual-modules: Benchmark.Virtual',
            'virtual-modules: Tests.VirtualRunner',
        ], $audit['benchmarkDependencyClosure']['unexpectedModuleInterfaceFields'][$target]);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('unexpected Cabal runner module interface fields: test:test-pandoc (signatures: Tests.RunnerSignature, virtual-modules: Tests.VirtualRunner); test:test-pandoc-lua-engine (signatures: Lua.RunnerSignature, virtual-modules: Lua.VirtualRunner)', $blocked);
        $t->contains('unexpected Cabal benchmark module interface fields: benchmark:benchmark-pandoc (signatures: Benchmark.Signature, signatures: Tests.RunnerSignature, virtual-modules: Benchmark.Virtual, virtual-modules: Tests.VirtualRunner)', $blocked);
        $t->contains('no unexpected runner or benchmark module interface fields', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks cabal project conditional branches before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $project = $pinnedProject() . implode("\n", [
            'if os(windows)',
            '  packages: pandoc-runner-windows',
            '  constraints: Win32 >= 2.13',
            'elif arch(wasm32)',
            '  source-repository-package',
            '    type: git',
            '    location: https://example.invalid/pandoc-wasm-runner.git',
            '    tag: deadbeef',
            'else',
            '  package pandoc',
            '    flags: -http',
            '',
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
        $t->same([], $audit['projectPackageClosure']['unexpectedPackages']);
        $t->same([], $audit['projectPackageClosure']['unexpectedFlags']);
        $t->same([], $audit['projectPackageClosure']['unexpectedPackageFields']);
        $t->same([], $audit['projectConstraintClosure']['unexpectedConstraints']);
        $t->same([], $audit['projectSourceRepositoryClosure']['unexpected']);
        $t->same([], $audit['projectSourceRepositoryClosure']['unexpectedFields']);
        $t->same([], $audit['projectUnconditionalFieldClosure']['unexpectedFields']);
        $t->same([], $audit['projectConditionalBranchClosure']['expectedBranches']);
        $t->same([
            'if os(windows)',
            'elif arch(wasm32)',
            'else after elif arch(wasm32)',
        ], $audit['projectConditionalBranchClosure']['presentBranches']);
        $t->same($audit['projectConditionalBranchClosure']['presentBranches'], $audit['projectConditionalBranchClosure']['unexpectedBranches']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('unexpected cabal.project conditional branches: if os(windows), elif arch(wasm32), else after elif arch(wasm32)', $blocked);
        $t->contains('no unexpected cabal.project conditional branches', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks runner and benchmark native system dependency drift before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['pandoc.cabal'] = str_replace(
            "common common-executable\n  import: common-options",
            implode("\n", [
                'common common-executable',
                '  import: common-options',
                '  c-sources:',
                '    cbits/test-pandoc-audit.c',
                '  js-sources: js/test-pandoc-audit.js',
                '  extra-libraries: z iconv',
                '  pkgconfig-depends: zlib >= 1.2',
            ]),
            $files['pandoc.cabal']
        );
        $files['pandoc.cabal'] = str_replace(
            "benchmark benchmark-pandoc\n  import: common-executable",
            implode("\n", [
                'benchmark benchmark-pandoc',
                '  import: common-executable',
                '  ld-options: -Wl,--export-dynamic',
                '  cc-options: -DBENCHMARK_AUDIT',
            ]),
            $files['pandoc.cabal']
        );
        $files['pandoc-lua-engine/pandoc-lua-engine.cabal'] = str_replace(
            "common test-options\n  build-depends: base >= 4.12 && < 5",
            implode("\n", [
                'common test-options',
                '  build-depends: base >= 4.12 && < 5',
                '  cxx-sources: cbits/lua-audit.cpp',
                '  frameworks: CoreFoundation',
                '  extra-framework-dirs: /System/Library/Frameworks',
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
        $t->same([], $audit['runnerDependencyClosure']['unexpectedBuildTools']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedDefaultExtensions']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedOtherExtensions']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedCppOptions']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedAutogenModules']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedReexportedModules']);
        $t->same([], $audit['runnerDependencyClosure']['missingOtherModules']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingTargets']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingDependencies']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedDependencyConstraints']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingExecutableOptions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedMixins']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedBuildTools']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedDefaultExtensions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedOtherExtensions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedCppOptions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedAutogenModules']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedReexportedModules']);
        $t->same(UpstreamRunnerDependencyAudit::expectedRunnerNativeSystemFields(), $audit['runnerDependencyClosure']['expectedNativeSystemFields']);
        $t->same(UpstreamRunnerDependencyAudit::expectedBenchmarkNativeSystemFields(), $audit['benchmarkDependencyClosure']['expectedNativeSystemFields']);
        $t->same([
            'c-sources' => ['cbits/test-pandoc-audit.c'],
            'extra-libraries' => ['iconv', 'z'],
            'js-sources' => ['js/test-pandoc-audit.js'],
            'pkgconfig-depends' => ['zlib >= 1.2'],
        ], $audit['runnerDependencyClosure']['present']['test:test-pandoc']['nativeSystemFields']);
        $t->same([
            'cxx-sources' => ['cbits/lua-audit.cpp'],
            'extra-framework-dirs' => ['/System/Library/Frameworks'],
            'frameworks' => ['CoreFoundation'],
        ], $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['nativeSystemFields']);
        $t->same([
            'c-sources' => ['cbits/test-pandoc-audit.c'],
            'cc-options' => ['-DBENCHMARK_AUDIT'],
            'extra-libraries' => ['iconv', 'z'],
            'js-sources' => ['js/test-pandoc-audit.js'],
            'ld-options' => ['-Wl,--export-dynamic'],
            'pkgconfig-depends' => ['zlib >= 1.2'],
        ], $audit['benchmarkDependencyClosure']['present'][$target]['nativeSystemFields']);
        $t->same([
            'c-sources: cbits/test-pandoc-audit.c',
            'extra-libraries: iconv',
            'extra-libraries: z',
            'js-sources: js/test-pandoc-audit.js',
            'pkgconfig-depends: zlib >= 1.2',
        ], $audit['runnerDependencyClosure']['unexpectedNativeSystemFields']['test:test-pandoc']);
        $t->same([
            'cxx-sources: cbits/lua-audit.cpp',
            'extra-framework-dirs: /System/Library/Frameworks',
            'frameworks: CoreFoundation',
        ], $audit['runnerDependencyClosure']['unexpectedNativeSystemFields']['test:test-pandoc-lua-engine']);
        $t->same([
            'c-sources: cbits/test-pandoc-audit.c',
            'cc-options: -DBENCHMARK_AUDIT',
            'extra-libraries: iconv',
            'extra-libraries: z',
            'js-sources: js/test-pandoc-audit.js',
            'ld-options: -Wl,--export-dynamic',
            'pkgconfig-depends: zlib >= 1.2',
        ], $audit['benchmarkDependencyClosure']['unexpectedNativeSystemFields'][$target]);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('unexpected Cabal runner native/system dependencies: test:test-pandoc (c-sources: cbits/test-pandoc-audit.c, extra-libraries: iconv, extra-libraries: z, js-sources: js/test-pandoc-audit.js, pkgconfig-depends: zlib >= 1.2); test:test-pandoc-lua-engine (cxx-sources: cbits/lua-audit.cpp, extra-framework-dirs: /System/Library/Frameworks, frameworks: CoreFoundation)', $blocked);
        $t->contains('unexpected Cabal benchmark native/system dependencies: benchmark:benchmark-pandoc (c-sources: cbits/test-pandoc-audit.c, cc-options: -DBENCHMARK_AUDIT, extra-libraries: iconv, extra-libraries: z, js-sources: js/test-pandoc-audit.js, ld-options: -Wl,--export-dynamic, pkgconfig-depends: zlib >= 1.2)', $blocked);
        $t->contains('no unexpected runner or benchmark native/system dependency fields', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks runner and benchmark native header include drift before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['pandoc.cabal'] = str_replace(
            "common common-executable\n  import: common-options",
            implode("\n", [
                'common common-executable',
                '  import: common-options',
                '  includes:',
                '    test-pandoc-audit.h,',
                '    shared-runner-audit.h',
                '  autogen-includes: autogen/test-pandoc-config.h',
            ]),
            $files['pandoc.cabal']
        );
        $files['pandoc.cabal'] = str_replace(
            "benchmark benchmark-pandoc\n  import: common-executable",
            implode("\n", [
                'benchmark benchmark-pandoc',
                '  import: common-executable',
                '  includes: benchmark-audit.h',
                '  autogen-includes: autogen/benchmark-config.h',
            ]),
            $files['pandoc.cabal']
        );
        $files['pandoc-lua-engine/pandoc-lua-engine.cabal'] = str_replace(
            "common test-options\n  build-depends: base >= 4.12 && < 5",
            implode("\n", [
                'common test-options',
                '  build-depends: base >= 4.12 && < 5',
                '  includes: lua-engine-audit.h',
                '  autogen-includes: autogen/lua-engine-config.h',
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
        $t->same([], $audit['runnerDependencyClosure']['unexpectedBuildTools']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedDefaultExtensions']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedOtherExtensions']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedCppOptions']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedAutogenModules']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedReexportedModules']);
        $t->same([], $audit['runnerDependencyClosure']['missingOtherModules']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingTargets']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingDependencies']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedDependencyConstraints']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingExecutableOptions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedMixins']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedBuildTools']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedDefaultExtensions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedOtherExtensions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedCppOptions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedAutogenModules']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedReexportedModules']);
        $t->same(UpstreamRunnerDependencyAudit::expectedRunnerNativeSystemFields(), $audit['runnerDependencyClosure']['expectedNativeSystemFields']);
        $t->same(UpstreamRunnerDependencyAudit::expectedBenchmarkNativeSystemFields(), $audit['benchmarkDependencyClosure']['expectedNativeSystemFields']);
        $t->same([
            'autogen-includes' => ['autogen/test-pandoc-config.h'],
            'includes' => ['shared-runner-audit.h', 'test-pandoc-audit.h'],
        ], $audit['runnerDependencyClosure']['present']['test:test-pandoc']['nativeSystemFields']);
        $t->same([
            'autogen-includes' => ['autogen/lua-engine-config.h'],
            'includes' => ['lua-engine-audit.h'],
        ], $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['nativeSystemFields']);
        $t->same([
            'autogen-includes' => ['autogen/benchmark-config.h', 'autogen/test-pandoc-config.h'],
            'includes' => ['benchmark-audit.h', 'shared-runner-audit.h', 'test-pandoc-audit.h'],
        ], $audit['benchmarkDependencyClosure']['present'][$target]['nativeSystemFields']);
        $t->same([
            'autogen-includes: autogen/test-pandoc-config.h',
            'includes: shared-runner-audit.h',
            'includes: test-pandoc-audit.h',
        ], $audit['runnerDependencyClosure']['unexpectedNativeSystemFields']['test:test-pandoc']);
        $t->same([
            'autogen-includes: autogen/lua-engine-config.h',
            'includes: lua-engine-audit.h',
        ], $audit['runnerDependencyClosure']['unexpectedNativeSystemFields']['test:test-pandoc-lua-engine']);
        $t->same([
            'autogen-includes: autogen/benchmark-config.h',
            'autogen-includes: autogen/test-pandoc-config.h',
            'includes: benchmark-audit.h',
            'includes: shared-runner-audit.h',
            'includes: test-pandoc-audit.h',
        ], $audit['benchmarkDependencyClosure']['unexpectedNativeSystemFields'][$target]);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('unexpected Cabal runner native/system dependencies: test:test-pandoc (autogen-includes: autogen/test-pandoc-config.h, includes: shared-runner-audit.h, includes: test-pandoc-audit.h); test:test-pandoc-lua-engine (autogen-includes: autogen/lua-engine-config.h, includes: lua-engine-audit.h)', $blocked);
        $t->contains('unexpected Cabal benchmark native/system dependencies: benchmark:benchmark-pandoc (autogen-includes: autogen/benchmark-config.h, autogen-includes: autogen/test-pandoc-config.h, includes: benchmark-audit.h, includes: shared-runner-audit.h, includes: test-pandoc-audit.h)', $blocked);
        $t->contains('no unexpected runner or benchmark native/system dependency fields', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks runner and benchmark native preprocessor field drift before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['pandoc.cabal'] = str_replace(
            "common common-executable\n  import: common-options",
            implode("\n", [
                'common common-executable',
                '  import: common-options',
                '  hsc2hs-options: --cross-compile --template=test/template.hsc',
                '  c2hs-options: --cppopts=-DTEST_RUNNER_AUDIT',
                '  asm-options: -Wa,--fatal-warnings',
                '  js-options: --no-minify',
                '  extra-lib-dirs-static: /opt/pandoc-runner/static',
            ]),
            $files['pandoc.cabal']
        );
        $files['pandoc.cabal'] = str_replace(
            "benchmark benchmark-pandoc\n  import: common-executable",
            implode("\n", [
                'benchmark benchmark-pandoc',
                '  import: common-executable',
                '  cmm-options: -DCMM_BENCHMARK_AUDIT',
                '  extra-bundled-libraries: bundled-pandoc-bench',
            ]),
            $files['pandoc.cabal']
        );
        $files['pandoc-lua-engine/pandoc-lua-engine.cabal'] = str_replace(
            "common test-options\n  build-depends: base >= 4.12 && < 5",
            implode("\n", [
                'common test-options',
                '  build-depends: base >= 4.12 && < 5',
                '  hsc2hs-options: --lua-cross',
                '  c2hs-options: --cppopts=-DLUA_RUNNER_AUDIT',
                '  extra-bundled-libraries: bundled-lua-audit',
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
        $t->same([], $audit['runnerDependencyClosure']['unexpectedBuildTools']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedDefaultExtensions']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedOtherExtensions']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedCppOptions']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedAutogenModules']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedReexportedModules']);
        $t->same([], $audit['runnerDependencyClosure']['missingOtherModules']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingTargets']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingDependencies']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedDependencyConstraints']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingExecutableOptions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedMixins']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedBuildTools']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedDefaultExtensions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedOtherExtensions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedCppOptions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedAutogenModules']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedReexportedModules']);
        $t->same(UpstreamRunnerDependencyAudit::expectedRunnerNativeSystemFields(), $audit['runnerDependencyClosure']['expectedNativeSystemFields']);
        $t->same(UpstreamRunnerDependencyAudit::expectedBenchmarkNativeSystemFields(), $audit['benchmarkDependencyClosure']['expectedNativeSystemFields']);
        $t->same([
            'asm-options' => ['-Wa,--fatal-warnings'],
            'c2hs-options' => ['--cppopts=-DTEST_RUNNER_AUDIT'],
            'extra-lib-dirs-static' => ['/opt/pandoc-runner/static'],
            'hsc2hs-options' => ['--cross-compile', '--template=test/template.hsc'],
            'js-options' => ['--no-minify'],
        ], $audit['runnerDependencyClosure']['present']['test:test-pandoc']['nativeSystemFields']);
        $t->same([
            'c2hs-options' => ['--cppopts=-DLUA_RUNNER_AUDIT'],
            'extra-bundled-libraries' => ['bundled-lua-audit'],
            'hsc2hs-options' => ['--lua-cross'],
        ], $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['nativeSystemFields']);
        $t->same([
            'asm-options' => ['-Wa,--fatal-warnings'],
            'c2hs-options' => ['--cppopts=-DTEST_RUNNER_AUDIT'],
            'cmm-options' => ['-DCMM_BENCHMARK_AUDIT'],
            'extra-bundled-libraries' => ['bundled-pandoc-bench'],
            'extra-lib-dirs-static' => ['/opt/pandoc-runner/static'],
            'hsc2hs-options' => ['--cross-compile', '--template=test/template.hsc'],
            'js-options' => ['--no-minify'],
        ], $audit['benchmarkDependencyClosure']['present'][$target]['nativeSystemFields']);
        $t->same([
            'asm-options: -Wa,--fatal-warnings',
            'c2hs-options: --cppopts=-DTEST_RUNNER_AUDIT',
            'extra-lib-dirs-static: /opt/pandoc-runner/static',
            'hsc2hs-options: --cross-compile',
            'hsc2hs-options: --template=test/template.hsc',
            'js-options: --no-minify',
        ], $audit['runnerDependencyClosure']['unexpectedNativeSystemFields']['test:test-pandoc']);
        $t->same([
            'c2hs-options: --cppopts=-DLUA_RUNNER_AUDIT',
            'extra-bundled-libraries: bundled-lua-audit',
            'hsc2hs-options: --lua-cross',
        ], $audit['runnerDependencyClosure']['unexpectedNativeSystemFields']['test:test-pandoc-lua-engine']);
        $t->same([
            'asm-options: -Wa,--fatal-warnings',
            'c2hs-options: --cppopts=-DTEST_RUNNER_AUDIT',
            'cmm-options: -DCMM_BENCHMARK_AUDIT',
            'extra-bundled-libraries: bundled-pandoc-bench',
            'extra-lib-dirs-static: /opt/pandoc-runner/static',
            'hsc2hs-options: --cross-compile',
            'hsc2hs-options: --template=test/template.hsc',
            'js-options: --no-minify',
        ], $audit['benchmarkDependencyClosure']['unexpectedNativeSystemFields'][$target]);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('unexpected Cabal runner native/system dependencies: test:test-pandoc (asm-options: -Wa,--fatal-warnings, c2hs-options: --cppopts=-DTEST_RUNNER_AUDIT, extra-lib-dirs-static: /opt/pandoc-runner/static, hsc2hs-options: --cross-compile, hsc2hs-options: --template=test/template.hsc, js-options: --no-minify); test:test-pandoc-lua-engine (c2hs-options: --cppopts=-DLUA_RUNNER_AUDIT, extra-bundled-libraries: bundled-lua-audit, hsc2hs-options: --lua-cross)', $blocked);
        $t->contains('unexpected Cabal benchmark native/system dependencies: benchmark:benchmark-pandoc (asm-options: -Wa,--fatal-warnings, c2hs-options: --cppopts=-DTEST_RUNNER_AUDIT, cmm-options: -DCMM_BENCHMARK_AUDIT, extra-bundled-libraries: bundled-pandoc-bench, extra-lib-dirs-static: /opt/pandoc-runner/static, hsc2hs-options: --cross-compile, hsc2hs-options: --template=test/template.hsc, js-options: --no-minify)', $blocked);
        $t->contains('no unexpected runner or benchmark native/system dependency fields', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks runner and benchmark alternate compiler option drift before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['pandoc.cabal'] = str_replace(
            "common common-executable\n  import: common-options",
            implode("\n", [
                'common common-executable',
                '  import: common-options',
                '  ghc-prof-options:',
                '    -fprof-auto',
                '    -fprof-cafs',
                '  ghc-shared-options: -dynamic-too',
            ]),
            $files['pandoc.cabal']
        );
        $files['pandoc.cabal'] = str_replace(
            "benchmark benchmark-pandoc\n  import: common-executable",
            implode("\n", [
                'benchmark benchmark-pandoc',
                '  import: common-executable',
                '  ghcjs-options: --closure=1 --debug',
            ]),
            $files['pandoc.cabal']
        );
        $files['pandoc-lua-engine/pandoc-lua-engine.cabal'] = str_replace(
            "common test-options\n  build-depends: base >= 4.12 && < 5",
            implode("\n", [
                'common test-options',
                '  build-depends: base >= 4.12 && < 5',
                '  ghc-prof-options: -fprof-auto-lua',
                '  ghcjs-options: --lua-runner-audit',
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
        $t->same([], $audit['runnerDependencyClosure']['unexpectedBuildTools']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedDefaultExtensions']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedOtherExtensions']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedCppOptions']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedAutogenModules']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedReexportedModules']);
        $t->same([], $audit['runnerDependencyClosure']['missingOtherModules']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingTargets']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingDependencies']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedDependencyConstraints']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingExecutableOptions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedMixins']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedBuildTools']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedDefaultExtensions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedOtherExtensions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedCppOptions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedAutogenModules']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedReexportedModules']);
        $t->same(UpstreamRunnerDependencyAudit::expectedRunnerNativeSystemFields(), $audit['runnerDependencyClosure']['expectedNativeSystemFields']);
        $t->same(UpstreamRunnerDependencyAudit::expectedBenchmarkNativeSystemFields(), $audit['benchmarkDependencyClosure']['expectedNativeSystemFields']);
        $t->same([
            'ghc-prof-options' => ['-fprof-auto', '-fprof-cafs'],
            'ghc-shared-options' => ['-dynamic-too'],
        ], $audit['runnerDependencyClosure']['present']['test:test-pandoc']['nativeSystemFields']);
        $t->same([
            'ghc-prof-options' => ['-fprof-auto-lua'],
            'ghcjs-options' => ['--lua-runner-audit'],
        ], $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['nativeSystemFields']);
        $t->same([
            'ghc-prof-options' => ['-fprof-auto', '-fprof-cafs'],
            'ghc-shared-options' => ['-dynamic-too'],
            'ghcjs-options' => ['--closure=1', '--debug'],
        ], $audit['benchmarkDependencyClosure']['present'][$target]['nativeSystemFields']);
        $t->same([
            'ghc-prof-options: -fprof-auto',
            'ghc-prof-options: -fprof-cafs',
            'ghc-shared-options: -dynamic-too',
        ], $audit['runnerDependencyClosure']['unexpectedNativeSystemFields']['test:test-pandoc']);
        $t->same([
            'ghc-prof-options: -fprof-auto-lua',
            'ghcjs-options: --lua-runner-audit',
        ], $audit['runnerDependencyClosure']['unexpectedNativeSystemFields']['test:test-pandoc-lua-engine']);
        $t->same([
            'ghc-prof-options: -fprof-auto',
            'ghc-prof-options: -fprof-cafs',
            'ghc-shared-options: -dynamic-too',
            'ghcjs-options: --closure=1',
            'ghcjs-options: --debug',
        ], $audit['benchmarkDependencyClosure']['unexpectedNativeSystemFields'][$target]);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('unexpected Cabal runner native/system dependencies: test:test-pandoc (ghc-prof-options: -fprof-auto, ghc-prof-options: -fprof-cafs, ghc-shared-options: -dynamic-too); test:test-pandoc-lua-engine (ghc-prof-options: -fprof-auto-lua, ghcjs-options: --lua-runner-audit)', $blocked);
        $t->contains('unexpected Cabal benchmark native/system dependencies: benchmark:benchmark-pandoc (ghc-prof-options: -fprof-auto, ghc-prof-options: -fprof-cafs, ghc-shared-options: -dynamic-too, ghcjs-options: --closure=1, ghcjs-options: --debug)', $blocked);
        $t->contains('no unexpected runner or benchmark native/system dependency fields', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks runner and benchmark extra source file drift before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['pandoc.cabal'] = str_replace(
            "common common-executable\n  import: common-options",
            implode("\n", [
                'common common-executable',
                '  import: common-options',
                '  extra-source-files:',
                '    test/generated-goldens/*.native,',
                '    test/generated-markdown/*.md',
            ]),
            $files['pandoc.cabal']
        );
        $files['pandoc.cabal'] = str_replace(
            "benchmark benchmark-pandoc\n  import: common-executable",
            implode("\n", [
                'benchmark benchmark-pandoc',
                '  import: common-executable',
                '  extra-source-files: benchmark/generated/*.md',
            ]),
            $files['pandoc.cabal']
        );
        $files['pandoc-lua-engine/pandoc-lua-engine.cabal'] = str_replace(
            "common test-options\n  build-depends: base >= 4.12 && < 5",
            implode("\n", [
                'common test-options',
                '  build-depends: base >= 4.12 && < 5',
                '  extra-source-files: test/generated-lua/*.lua',
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
        $t->same([], $audit['runnerDependencyClosure']['unexpectedBuildTools']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedTestOptions']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedDefaultExtensions']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedOtherExtensions']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedCppOptions']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedAutogenModules']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedReexportedModules']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedNativeSystemFields']);
        $t->same([], $audit['runnerDependencyClosure']['missingOtherModules']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingTargets']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingDependencies']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedDependencyConstraints']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingExecutableOptions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedMixins']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedBuildTools']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedBenchmarkOptions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedDefaultExtensions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedOtherExtensions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedCppOptions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedAutogenModules']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedReexportedModules']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedNativeSystemFields']);
        $t->same(UpstreamRunnerDependencyAudit::expectedRunnerExtraSourceFiles(), $audit['runnerDependencyClosure']['expectedExtraSourceFiles']);
        $t->same(UpstreamRunnerDependencyAudit::expectedBenchmarkExtraSourceFiles(), $audit['benchmarkDependencyClosure']['expectedExtraSourceFiles']);
        $t->same([
            'test/generated-goldens/*.native',
            'test/generated-markdown/*.md',
        ], $audit['runnerDependencyClosure']['present']['test:test-pandoc']['extraSourceFiles']);
        $t->same([
            'test/generated-lua/*.lua',
        ], $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['extraSourceFiles']);
        $t->same([
            'benchmark/generated/*.md',
            'test/generated-goldens/*.native',
            'test/generated-markdown/*.md',
        ], $audit['benchmarkDependencyClosure']['present'][$target]['extraSourceFiles']);
        $t->same($audit['runnerDependencyClosure']['present']['test:test-pandoc']['extraSourceFiles'], $audit['runnerDependencyClosure']['unexpectedExtraSourceFiles']['test:test-pandoc']);
        $t->same($audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['extraSourceFiles'], $audit['runnerDependencyClosure']['unexpectedExtraSourceFiles']['test:test-pandoc-lua-engine']);
        $t->same($audit['benchmarkDependencyClosure']['present'][$target]['extraSourceFiles'], $audit['benchmarkDependencyClosure']['unexpectedExtraSourceFiles'][$target]);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('unexpected Cabal runner extra-source-files: test:test-pandoc (test/generated-goldens/*.native, test/generated-markdown/*.md); test:test-pandoc-lua-engine (test/generated-lua/*.lua)', $blocked);
        $t->contains('unexpected Cabal benchmark extra-source-files: benchmark:benchmark-pandoc (benchmark/generated/*.md, test/generated-goldens/*.native, test/generated-markdown/*.md)', $blocked);
        $t->contains('no unexpected runner or benchmark extra-source-files', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks runner and benchmark extra doc file drift before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['pandoc.cabal'] = str_replace(
            "common common-executable\n  import: common-options",
            implode("\n", [
                'common common-executable',
                '  import: common-options',
                '  extra-doc-files:',
                '    docs/runner-audit.md,',
                '    test/generated-docs/*.md',
            ]),
            $files['pandoc.cabal']
        );
        $files['pandoc.cabal'] = str_replace(
            "benchmark benchmark-pandoc\n  import: common-executable",
            implode("\n", [
                'benchmark benchmark-pandoc',
                '  import: common-executable',
                '  extra-doc-files: benchmark/generated-docs/*.md',
            ]),
            $files['pandoc.cabal']
        );
        $files['pandoc-lua-engine/pandoc-lua-engine.cabal'] = str_replace(
            "common test-options\n  build-depends: base >= 4.12 && < 5",
            implode("\n", [
                'common test-options',
                '  build-depends: base >= 4.12 && < 5',
                '  extra-doc-files: test/generated-lua-docs/*.md',
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
        $t->same([], $audit['runnerDependencyClosure']['unexpectedBuildTools']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedTestOptions']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedDefaultExtensions']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedOtherExtensions']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedCppOptions']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedAutogenModules']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedReexportedModules']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedExtraSourceFiles']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedDataFiles']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedNativeSystemFields']);
        $t->same([], $audit['runnerDependencyClosure']['missingOtherModules']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingTargets']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingDependencies']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedDependencyConstraints']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingExecutableOptions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedMixins']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedBuildTools']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedBenchmarkOptions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedDefaultExtensions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedOtherExtensions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedCppOptions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedAutogenModules']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedReexportedModules']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedOtherModules']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedExtraSourceFiles']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedDataFiles']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedNativeSystemFields']);
        $t->same(UpstreamRunnerDependencyAudit::expectedRunnerExtraDocFiles(), $audit['runnerDependencyClosure']['expectedExtraDocFiles']);
        $t->same(UpstreamRunnerDependencyAudit::expectedBenchmarkExtraDocFiles(), $audit['benchmarkDependencyClosure']['expectedExtraDocFiles']);
        $t->same([
            'docs/runner-audit.md',
            'test/generated-docs/*.md',
        ], $audit['runnerDependencyClosure']['present']['test:test-pandoc']['extraDocFiles']);
        $t->same([
            'test/generated-lua-docs/*.md',
        ], $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['extraDocFiles']);
        $t->same([
            'benchmark/generated-docs/*.md',
            'docs/runner-audit.md',
            'test/generated-docs/*.md',
        ], $audit['benchmarkDependencyClosure']['present'][$target]['extraDocFiles']);
        $t->same($audit['runnerDependencyClosure']['present']['test:test-pandoc']['extraDocFiles'], $audit['runnerDependencyClosure']['unexpectedExtraDocFiles']['test:test-pandoc']);
        $t->same($audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['extraDocFiles'], $audit['runnerDependencyClosure']['unexpectedExtraDocFiles']['test:test-pandoc-lua-engine']);
        $t->same($audit['benchmarkDependencyClosure']['present'][$target]['extraDocFiles'], $audit['benchmarkDependencyClosure']['unexpectedExtraDocFiles'][$target]);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('unexpected Cabal runner extra-doc-files: test:test-pandoc (docs/runner-audit.md, test/generated-docs/*.md); test:test-pandoc-lua-engine (test/generated-lua-docs/*.md)', $blocked);
        $t->contains('unexpected Cabal benchmark extra-doc-files: benchmark:benchmark-pandoc (benchmark/generated-docs/*.md, docs/runner-audit.md, test/generated-docs/*.md)', $blocked);
        $t->contains('no unexpected runner or benchmark extra-doc-files', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks runner and benchmark data file drift before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['pandoc.cabal'] = str_replace(
            "common common-executable\n  import: common-options",
            implode("\n", [
                'common common-executable',
                '  import: common-options',
                '  data-files:',
                '    test/generated-data/*.json,',
                '    test/generated-media/*.png',
            ]),
            $files['pandoc.cabal']
        );
        $files['pandoc.cabal'] = str_replace(
            "benchmark benchmark-pandoc\n  import: common-executable",
            implode("\n", [
                'benchmark benchmark-pandoc',
                '  import: common-executable',
                '  data-files: benchmark/generated/*.json',
            ]),
            $files['pandoc.cabal']
        );
        $files['pandoc-lua-engine/pandoc-lua-engine.cabal'] = str_replace(
            "common test-options\n  build-depends: base >= 4.12 && < 5",
            implode("\n", [
                'common test-options',
                '  build-depends: base >= 4.12 && < 5',
                '  data-files: test/generated-lua/*.json',
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
        $t->same([], $audit['runnerDependencyClosure']['unexpectedBuildTools']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedTestOptions']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedDefaultExtensions']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedOtherExtensions']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedCppOptions']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedAutogenModules']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedReexportedModules']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedExtraSourceFiles']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedNativeSystemFields']);
        $t->same([], $audit['runnerDependencyClosure']['missingOtherModules']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingTargets']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingDependencies']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedDependencyConstraints']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingExecutableOptions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedMixins']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedBuildTools']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedBenchmarkOptions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedDefaultExtensions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedOtherExtensions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedCppOptions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedAutogenModules']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedReexportedModules']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedExtraSourceFiles']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedNativeSystemFields']);
        $t->same(UpstreamRunnerDependencyAudit::expectedRunnerDataFiles(), $audit['runnerDependencyClosure']['expectedDataFiles']);
        $t->same(UpstreamRunnerDependencyAudit::expectedBenchmarkDataFiles(), $audit['benchmarkDependencyClosure']['expectedDataFiles']);
        $t->same([
            'test/generated-data/*.json',
            'test/generated-media/*.png',
        ], $audit['runnerDependencyClosure']['present']['test:test-pandoc']['dataFiles']);
        $t->same([
            'test/generated-lua/*.json',
        ], $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['dataFiles']);
        $t->same([
            'benchmark/generated/*.json',
            'test/generated-data/*.json',
            'test/generated-media/*.png',
        ], $audit['benchmarkDependencyClosure']['present'][$target]['dataFiles']);
        $t->same($audit['runnerDependencyClosure']['present']['test:test-pandoc']['dataFiles'], $audit['runnerDependencyClosure']['unexpectedDataFiles']['test:test-pandoc']);
        $t->same($audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['dataFiles'], $audit['runnerDependencyClosure']['unexpectedDataFiles']['test:test-pandoc-lua-engine']);
        $t->same($audit['benchmarkDependencyClosure']['present'][$target]['dataFiles'], $audit['benchmarkDependencyClosure']['unexpectedDataFiles'][$target]);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('unexpected Cabal runner data-files: test:test-pandoc (test/generated-data/*.json, test/generated-media/*.png); test:test-pandoc-lua-engine (test/generated-lua/*.json)', $blocked);
        $t->contains('unexpected Cabal benchmark data-files: benchmark:benchmark-pandoc (benchmark/generated/*.json, test/generated-data/*.json, test/generated-media/*.png)', $blocked);
        $t->contains('no unexpected runner or benchmark data-files', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks runner and benchmark extra tmp file drift before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['pandoc.cabal'] = str_replace(
            "common common-executable\n  import: common-options",
            implode("\n", [
                'common common-executable',
                '  import: common-options',
                '  extra-tmp-files:',
                '    test/tmp/runner-plan.json,',
                '    test/tmp/*.golden',
            ]),
            $files['pandoc.cabal']
        );
        $files['pandoc.cabal'] = str_replace(
            "benchmark benchmark-pandoc\n  import: common-executable",
            implode("\n", [
                'benchmark benchmark-pandoc',
                '  import: common-executable',
                '  extra-tmp-files: benchmark/tmp/*.json',
            ]),
            $files['pandoc.cabal']
        );
        $files['pandoc-lua-engine/pandoc-lua-engine.cabal'] = str_replace(
            "common test-options\n  build-depends: base >= 4.12 && < 5",
            implode("\n", [
                'common test-options',
                '  build-depends: base >= 4.12 && < 5',
                '  extra-tmp-files: test/tmp/lua-runner.plan',
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
        $t->same([], $audit['runnerDependencyClosure']['unexpectedBuildTools']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedTestOptions']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedDefaultExtensions']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedOtherExtensions']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedCppOptions']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedAutogenModules']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedReexportedModules']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedExtraSourceFiles']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedExtraDocFiles']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedDataFiles']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedNativeSystemFields']);
        $t->same([], $audit['runnerDependencyClosure']['missingOtherModules']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingTargets']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingDependencies']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedDependencyConstraints']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingExecutableOptions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedMixins']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedBuildTools']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedBenchmarkOptions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedDefaultExtensions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedOtherExtensions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedCppOptions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedAutogenModules']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedReexportedModules']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedOtherModules']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedExtraSourceFiles']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedExtraDocFiles']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedDataFiles']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedNativeSystemFields']);
        $t->same(UpstreamRunnerDependencyAudit::expectedRunnerExtraTmpFiles(), $audit['runnerDependencyClosure']['expectedExtraTmpFiles']);
        $t->same(UpstreamRunnerDependencyAudit::expectedBenchmarkExtraTmpFiles(), $audit['benchmarkDependencyClosure']['expectedExtraTmpFiles']);
        $t->same([
            'test/tmp/*.golden',
            'test/tmp/runner-plan.json',
        ], $audit['runnerDependencyClosure']['present']['test:test-pandoc']['extraTmpFiles']);
        $t->same([
            'test/tmp/lua-runner.plan',
        ], $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['extraTmpFiles']);
        $t->same([
            'benchmark/tmp/*.json',
            'test/tmp/*.golden',
            'test/tmp/runner-plan.json',
        ], $audit['benchmarkDependencyClosure']['present'][$target]['extraTmpFiles']);
        $t->same($audit['runnerDependencyClosure']['present']['test:test-pandoc']['extraTmpFiles'], $audit['runnerDependencyClosure']['unexpectedExtraTmpFiles']['test:test-pandoc']);
        $t->same($audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['extraTmpFiles'], $audit['runnerDependencyClosure']['unexpectedExtraTmpFiles']['test:test-pandoc-lua-engine']);
        $t->same($audit['benchmarkDependencyClosure']['present'][$target]['extraTmpFiles'], $audit['benchmarkDependencyClosure']['unexpectedExtraTmpFiles'][$target]);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('unexpected Cabal runner extra-tmp-files: test:test-pandoc (test/tmp/*.golden, test/tmp/runner-plan.json); test:test-pandoc-lua-engine (test/tmp/lua-runner.plan)', $blocked);
        $t->contains('unexpected Cabal benchmark extra-tmp-files: benchmark:benchmark-pandoc (benchmark/tmp/*.json, test/tmp/*.golden, test/tmp/runner-plan.json)', $blocked);
        $t->contains('no unexpected runner or benchmark extra-tmp-files', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks runner and benchmark source directory drift before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['pandoc.cabal'] = str_replace(
            "common common-executable\n  import: common-options",
            implode("\n", [
                'common common-executable',
                '  import: common-options',
                '  hs-source-dirs: test generated-runner shared-runner',
            ]),
            $files['pandoc.cabal']
        );
        $files['pandoc.cabal'] = str_replace(
            "benchmark benchmark-pandoc\n  import: common-executable",
            implode("\n", [
                'benchmark benchmark-pandoc',
                '  import: common-executable',
            ]),
            $files['pandoc.cabal']
        );
        $files['pandoc.cabal'] = str_replace(
            '  hs-source-dirs: benchmark',
            '  hs-source-dirs: benchmark generated-benchmark',
            $files['pandoc.cabal']
        );
        $files['pandoc-lua-engine/pandoc-lua-engine.cabal'] = str_replace(
            "common test-options\n  build-depends: base >= 4.12 && < 5",
            implode("\n", [
                'common test-options',
                '  build-depends: base >= 4.12 && < 5',
                '  hs-source-dirs: test generated-lua',
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
        $t->same([], $audit['runnerDependencyClosure']['unexpectedBuildTools']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedTestOptions']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedDefaultExtensions']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedOtherExtensions']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedCppOptions']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedAutogenModules']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedReexportedModules']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedExtraSourceFiles']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedDataFiles']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedNativeSystemFields']);
        $t->same([], $audit['runnerDependencyClosure']['missingOtherModules']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingTargets']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingDependencies']);
        $t->same([], $audit['benchmarkDependencyClosure']['mismatchedDependencyConstraints']);
        $t->same([], $audit['benchmarkDependencyClosure']['missingExecutableOptions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedMixins']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedBuildTools']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedBenchmarkOptions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedDefaultExtensions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedOtherExtensions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedCppOptions']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedAutogenModules']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedReexportedModules']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedExtraSourceFiles']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedDataFiles']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedNativeSystemFields']);
        $t->same([
            'test',
            'generated-runner',
            'shared-runner',
        ], $audit['runnerDependencyClosure']['present']['test:test-pandoc']['sourceDirectories']);
        $t->same([
            'test',
            'generated-lua',
        ], $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['sourceDirectories']);
        $t->same([
            'test',
            'generated-runner',
            'shared-runner',
            'benchmark',
            'generated-benchmark',
        ], $audit['benchmarkDependencyClosure']['present'][$target]['sourceDirectories']);
        $t->same([
            'generated-runner',
            'shared-runner',
        ], $audit['runnerDependencyClosure']['unexpectedSourceDirectories']['test:test-pandoc']);
        $t->same([
            'generated-lua',
        ], $audit['runnerDependencyClosure']['unexpectedSourceDirectories']['test:test-pandoc-lua-engine']);
        $t->same([
            'test',
            'generated-runner',
            'shared-runner',
            'generated-benchmark',
        ], $audit['benchmarkDependencyClosure']['unexpectedSourceDirectories'][$target]);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('unexpected Cabal runner hs-source-dirs: test:test-pandoc (generated-runner, shared-runner); test:test-pandoc-lua-engine (generated-lua)', $blocked);
        $t->contains('unexpected Cabal benchmark hs-source-dirs: benchmark:benchmark-pandoc (test, generated-runner, shared-runner, generated-benchmark)', $blocked);
        $t->contains('no unexpected runner or benchmark hs-source-dirs', $audit['activationGate']);
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
    'parses cabal conditional branch labels through imported common stanzas' => static function (TestRunner $t): void {
        $contents = implode("\n", [
            'common runner-common',
            '  if os(windows)',
            '    build-depends: Win32',
            '    cpp-options: -DWINDOWS',
            '  elif arch(wasm32)',
            '    build-depends: ghcjs-base',
            '  else',
            '    cpp-options: -DPORTABLE_RUNNER',
            '',
            'test-suite test-pandoc',
            '  import: runner-common',
            '  type: exitcode-stdio-1.0',
            '  if flag(local-runner-fixtures)',
            '    build-depends: local-runner-fixtures',
            '  main-is: test-pandoc.hs',
            '  hs-source-dirs: test',
            '  build-depends: base',
            '',
            'benchmark benchmark-pandoc',
            '  import: runner-common',
            '  type: exitcode-stdio-1.0',
            '  if flag(benchmark-fixtures)',
            '    data-files: benchmark/generated/*.json',
            '  main-is: benchmark-pandoc.hs',
            '  hs-source-dirs: benchmark',
            '  build-depends: base',
        ]);

        $suites = UpstreamRunnerDependencyAudit::parseCabalTestSuites($contents);
        $benchmarks = UpstreamRunnerDependencyAudit::parseCabalBenchmarks($contents);

        $t->same([
            'common runner-common: if os(windows)',
            'common runner-common: elif arch(wasm32)',
            'common runner-common: else',
            'test-suite test-pandoc: if flag(local-runner-fixtures)',
        ], $suites['test-pandoc']['conditionalBranches']);
        $t->same([
            'common runner-common: if os(windows)',
            'common runner-common: elif arch(wasm32)',
            'common runner-common: else',
            'benchmark benchmark-pandoc: if flag(benchmark-fixtures)',
        ], $benchmarks['benchmark-pandoc']['conditionalBranches']);
        $t->same(['base'], $suites['test-pandoc']['buildDepends']);
        $t->same([], $suites['test-pandoc']['cppOptions']);
        $t->same([], $benchmarks['benchmark-pandoc']['dataFiles']);
    },
    'blocks conditional cabal runner and benchmark branches before planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['pandoc.cabal'] = str_replace(
            "common common-executable\n  import: common-options",
            implode("\n", [
                'common common-executable',
                '  import: common-options',
                '  if os(windows)',
                '    build-depends: optional-runner-helper',
                '    ghc-options: -eventlog',
                '  else',
                '    data-files: test/generated-nonwindows/*.json',
            ]),
            $files['pandoc.cabal']
        );
        $files['pandoc.cabal'] = str_replace(
            "benchmark benchmark-pandoc\n  import: common-executable",
            implode("\n", [
                'benchmark benchmark-pandoc',
                '  import: common-executable',
                '  if flag(benchmark-fixtures)',
                '    data-files: benchmark/generated/*.json',
            ]),
            $files['pandoc.cabal']
        );
        $files['pandoc-lua-engine/pandoc-lua-engine.cabal'] = str_replace(
            "common test-options\n  build-depends: base >= 4.12 && < 5",
            implode("\n", [
                'common test-options',
                '  build-depends: base >= 4.12 && < 5',
                '  if os(windows)',
                '    build-depends: Win32',
                '    other-modules: Tests.Lua.WindowsOnly',
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
        $t->same([], $audit['runnerDependencyClosure']['missingDependencies']);
        $t->same([], $audit['runnerDependencyClosure']['missingExecutableOptions']);
        $t->same([], $audit['runnerDependencyClosure']['missingOtherModules']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedDependencies']);
        $t->same([], $audit['runnerDependencyClosure']['unexpectedDataFiles']);
        $t->same([], $audit['benchmarkDependencyClosure']['unexpectedDataFiles']);
        $t->same(false, in_array('optional-runner-helper', $audit['runnerDependencyClosure']['present']['test:test-pandoc']['buildDepends'], true));
        $t->same(false, in_array('-eventlog', $audit['runnerDependencyClosure']['present']['test:test-pandoc']['ghcOptions'], true));
        $t->same(false, in_array('Tests.Optional.Runner', $audit['runnerDependencyClosure']['present']['test:test-pandoc']['otherModules'], true));
        $t->same(false, in_array('Win32', $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['buildDepends'], true));
        $t->same(false, in_array('Tests.Lua.WindowsOnly', $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['otherModules'], true));
        $t->same(UpstreamRunnerDependencyAudit::expectedRunnerConditionalBranches(), $audit['runnerDependencyClosure']['expectedConditionalBranches']);
        $t->same(UpstreamRunnerDependencyAudit::expectedBenchmarkConditionalBranches(), $audit['benchmarkDependencyClosure']['expectedConditionalBranches']);
        $t->same([
            'common common-executable: if os(windows)',
            'common common-executable: else',
        ], $audit['runnerDependencyClosure']['present']['test:test-pandoc']['conditionalBranches']);
        $t->same([
            'common test-options: if os(windows)',
        ], $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['conditionalBranches']);
        $t->same([
            'common common-executable: if os(windows)',
            'common common-executable: else',
            'benchmark benchmark-pandoc: if flag(benchmark-fixtures)',
        ], $audit['benchmarkDependencyClosure']['present'][$target]['conditionalBranches']);
        $t->same($audit['runnerDependencyClosure']['present']['test:test-pandoc']['conditionalBranches'], $audit['runnerDependencyClosure']['unexpectedConditionalBranches']['test:test-pandoc']);
        $t->same($audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['conditionalBranches'], $audit['runnerDependencyClosure']['unexpectedConditionalBranches']['test:test-pandoc-lua-engine']);
        $t->same($audit['benchmarkDependencyClosure']['present'][$target]['conditionalBranches'], $audit['benchmarkDependencyClosure']['unexpectedConditionalBranches'][$target]);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('unexpected Cabal runner conditional branches: test:test-pandoc (common common-executable: if os(windows), common common-executable: else); test:test-pandoc-lua-engine (common test-options: if os(windows))', $blocked);
        $t->contains('unexpected Cabal benchmark conditional branches: benchmark:benchmark-pandoc (common common-executable: if os(windows), common common-executable: else, benchmark benchmark-pandoc: if flag(benchmark-fixtures))', $blocked);
        $t->contains('no unexpected runner or benchmark conditional branches', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
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
    'blocks conditional cabal project branches without polluting unconditional closure' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
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

        $t->same(false, $audit['readyForNonMutatingCabalPlan']);
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
        $t->same([], $audit['projectUnconditionalFieldClosure']['unexpectedFields']);
        $t->same(['if arch(wasm32)'], $audit['projectConditionalBranchClosure']['presentBranches']);
        $t->same(['if arch(wasm32)'], $audit['projectConditionalBranchClosure']['unexpectedBranches']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('unexpected cabal.project conditional branches: if arch(wasm32)', $blocked);
        $t->contains('no unexpected cabal.project conditional branches', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks lua engine library file artifact globs before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $libraryOtherModules = '  other-modules:' . "\n    " . implode(",\n    ", UpstreamRunnerDependencyAudit::expectedLuaEngineLibraryOtherModules());
        $files['pandoc-lua-engine/pandoc-lua-engine.cabal'] = str_replace(
            "library\n  import: test-options\n  hs-source-dirs: src\n  exposed-modules: Text.Pandoc.Lua\n" . $libraryOtherModules . "\n  build-depends:",
            implode("\n", [
                'library',
                '  import: test-options',
                '  hs-source-dirs: src',
                '  exposed-modules: Text.Pandoc.Lua',
                $libraryOtherModules,
                '  extra-source-files: cbits/lua-runner.c',
                '  extra-doc-files: docs/lua-runner.md',
                '  extra-tmp-files: dist/lua-runner.tmp',
                '  data-files:',
                '    data/lua-filter.lua,',
                '    data/defaults/*.json',
                '  build-depends:',
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

        $t->same(false, $audit['readyForNonMutatingCabalPlan']);
        $t->same([], $audit['luaEngineLibraryClosure']['missingDependencies']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedDependencies']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedDefaultExtensions']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedOtherExtensions']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedConditionalBranches']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedNativeSystemFields']);
        $t->same(['cbits/lua-runner.c'], $audit['luaEngineLibraryClosure']['presentExtraSourceFiles']);
        $t->same(['docs/lua-runner.md'], $audit['luaEngineLibraryClosure']['presentExtraDocFiles']);
        $t->same(['dist/lua-runner.tmp'], $audit['luaEngineLibraryClosure']['presentExtraTmpFiles']);
        $t->same([
            'data/defaults/*.json',
            'data/lua-filter.lua',
        ], $audit['luaEngineLibraryClosure']['presentDataFiles']);
        $t->same($audit['luaEngineLibraryClosure']['presentExtraSourceFiles'], $audit['luaEngineLibraryClosure']['unexpectedExtraSourceFiles']);
        $t->same($audit['luaEngineLibraryClosure']['presentExtraDocFiles'], $audit['luaEngineLibraryClosure']['unexpectedExtraDocFiles']);
        $t->same($audit['luaEngineLibraryClosure']['presentExtraTmpFiles'], $audit['luaEngineLibraryClosure']['unexpectedExtraTmpFiles']);
        $t->same($audit['luaEngineLibraryClosure']['presentDataFiles'], $audit['luaEngineLibraryClosure']['unexpectedDataFiles']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('unexpected pandoc-lua-engine library extra-source-files: cbits/lua-runner.c', $blocked);
        $t->contains('unexpected pandoc-lua-engine library extra-doc-files: docs/lua-runner.md', $blocked);
        $t->contains('unexpected pandoc-lua-engine library extra-tmp-files: dist/lua-runner.tmp', $blocked);
        $t->contains('unexpected pandoc-lua-engine library data-files: data/defaults/*.json, data/lua-filter.lua', $blocked);
        $t->contains('no unexpected pandoc-lua-engine library file artifact globs', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks lua engine library mixins and build tools before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['pandoc-lua-engine/pandoc-lua-engine.cabal'] = str_replace(
            implode("\n", [
                'library',
                '  import: test-options',
                '  hs-source-dirs: src',
                '  exposed-modules: Text.Pandoc.Lua',
            ]),
            implode("\n", [
                'library',
                '  import: test-options',
                '  hs-source-dirs: src',
                '  mixins:',
                '    hslua-module-path (HsLua.Module.Path as HsLua.Module.Path.RunnerAudit)',
                '  build-tool-depends:',
                '    hsc2hs:hsc2hs >= 0.68,',
                '    happy:happy >= 1.20',
                '  build-tools: alex, doctest',
                '  exposed-modules: Text.Pandoc.Lua',
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

        $t->same(false, $audit['readyForNonMutatingCabalPlan']);
        $t->same([], $audit['luaEngineLibraryClosure']['missingDependencies']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedDependencies']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedDefaultExtensions']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedOtherExtensions']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedExtraSourceFiles']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedExtraDocFiles']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedExtraTmpFiles']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedDataFiles']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedConditionalBranches']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedNativeSystemFields']);
        $t->same([
            'hslua-module-path (HsLua.Module.Path as HsLua.Module.Path.RunnerAudit)',
        ], $audit['luaEngineLibraryClosure']['presentMixins']);
        $t->same($audit['luaEngineLibraryClosure']['presentMixins'], $audit['luaEngineLibraryClosure']['unexpectedMixins']);
        $t->same([
            'hsc2hs:hsc2hs >= 0.68',
            'happy:happy >= 1.20',
        ], $audit['luaEngineLibraryClosure']['presentBuildToolDepends']);
        $t->same([
            'alex',
            'doctest',
        ], $audit['luaEngineLibraryClosure']['presentBuildTools']);
        $t->same([
            'build-tool-depends: hsc2hs:hsc2hs >= 0.68',
            'build-tool-depends: happy:happy >= 1.20',
            'build-tools: alex',
            'build-tools: doctest',
        ], $audit['luaEngineLibraryClosure']['unexpectedBuildTools']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('unexpected pandoc-lua-engine library mixins: hslua-module-path (HsLua.Module.Path as HsLua.Module.Path.RunnerAudit)', $blocked);
        $t->contains('unexpected pandoc-lua-engine library build-tool dependencies: build-tool-depends: hsc2hs:hsc2hs >= 0.68, build-tool-depends: happy:happy >= 1.20, build-tools: alex, build-tools: doctest', $blocked);
        $t->contains('no unexpected pandoc-lua-engine library mixins or build-tool dependencies', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks lua engine library generated module interface fields before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['pandoc-lua-engine/pandoc-lua-engine.cabal'] = str_replace(
            implode("\n", [
                'library',
                '  import: test-options',
                '  hs-source-dirs: src',
                '  exposed-modules: Text.Pandoc.Lua',
            ]),
            implode("\n", [
                'library',
                '  import: test-options',
                '  hs-source-dirs: src',
                '  autogen-modules:',
                '    Paths_pandoc_lua_engine,',
                '    Text.Pandoc.Lua.Generated.Autogen',
                '  reexported-modules:',
                '    hslua-module-path:HsLua.Module.Path as Text.Pandoc.Lua.Generated.Path',
                '  signatures: Text.Pandoc.Lua.Signature',
                '  virtual-modules:',
                '    Text.Pandoc.Lua.Virtual',
                '  exposed-modules: Text.Pandoc.Lua',
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

        $t->same(false, $audit['readyForNonMutatingCabalPlan']);
        $t->same([], $audit['luaEngineLibraryClosure']['missingDependencies']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedDependencies']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedDefaultExtensions']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedOtherExtensions']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedExtraSourceFiles']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedExtraDocFiles']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedExtraTmpFiles']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedDataFiles']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedConditionalBranches']);
        $t->same([], $audit['luaEngineLibraryClosure']['unexpectedNativeSystemFields']);
        $t->same([
            'Paths_pandoc_lua_engine',
            'Text.Pandoc.Lua.Generated.Autogen',
        ], $audit['luaEngineLibraryClosure']['presentAutogenModules']);
        $t->same($audit['luaEngineLibraryClosure']['presentAutogenModules'], $audit['luaEngineLibraryClosure']['unexpectedAutogenModules']);
        $t->same([
            'hslua-module-path:HsLua.Module.Path as Text.Pandoc.Lua.Generated.Path',
        ], $audit['luaEngineLibraryClosure']['presentReexportedModules']);
        $t->same($audit['luaEngineLibraryClosure']['presentReexportedModules'], $audit['luaEngineLibraryClosure']['unexpectedReexportedModules']);
        $t->same([
            'signatures' => ['Text.Pandoc.Lua.Signature'],
            'virtual-modules' => ['Text.Pandoc.Lua.Virtual'],
        ], $audit['luaEngineLibraryClosure']['presentModuleInterfaceFields']);
        $t->same([
            'signatures: Text.Pandoc.Lua.Signature',
            'virtual-modules: Text.Pandoc.Lua.Virtual',
        ], $audit['luaEngineLibraryClosure']['unexpectedModuleInterfaceFields']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('unexpected pandoc-lua-engine library autogen-modules: Paths_pandoc_lua_engine, Text.Pandoc.Lua.Generated.Autogen', $blocked);
        $t->contains('unexpected pandoc-lua-engine library reexported-modules: hslua-module-path:HsLua.Module.Path as Text.Pandoc.Lua.Generated.Path', $blocked);
        $t->contains('unexpected pandoc-lua-engine library module interface fields: signatures: Text.Pandoc.Lua.Signature, virtual-modules: Text.Pandoc.Lua.Virtual', $blocked);
        $t->contains('no unexpected pandoc-lua-engine library generated, reexported, or module interface fields', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks package-level cabal data files drift before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['pandoc.cabal'] = str_replace(
            '  data/docbook-entities.txt,',
            '  data/generated-template-cache/*.json,',
            $files['pandoc.cabal']
        );
        $files['pandoc-lua-engine/pandoc-lua-engine.cabal'] = str_replace(
            "build-type: Simple\nextra-source-files:",
            "build-type: Simple\ndata-files: data/lua/generated-runner.json\nextra-source-files:",
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
        $t->same(['data/docbook-entities.txt'], $audit['packageDataFileClosure']['missingDataFiles']['pandoc.cabal']);
        $t->same(['data/generated-template-cache/*.json'], $audit['packageDataFileClosure']['unexpectedDataFiles']['pandoc.cabal']);
        $t->same(['data/lua/generated-runner.json'], $audit['packageDataFileClosure']['unexpectedDataFiles']['pandoc-lua-engine/pandoc-lua-engine.cabal']);
        $t->same([], $audit['packageDataFileClosure']['unexpectedDataFiles']['pandoc-server/pandoc-server.cabal'] ?? []);
        $t->same([], $audit['packageDataFileClosure']['unexpectedDataFiles']['pandoc-cli/pandoc-cli.cabal'] ?? []);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('missing Cabal package data-files: pandoc.cabal (data/docbook-entities.txt)', $blocked);
        $t->contains('unexpected Cabal package data-files: pandoc.cabal (data/generated-template-cache/*.json)', $blocked);
        $t->contains('pandoc-lua-engine/pandoc-lua-engine.cabal (data/lua/generated-runner.json)', $blocked);
        $t->contains('package-level data-files', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks package-level cabal extra file glob drift before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['pandoc.cabal'] = preg_replace('/^\s*BUGS,?$/m', '  RUNNER_AUDIT.md', $files['pandoc.cabal'], 1) ?? $files['pandoc.cabal'];
        $files['pandoc.cabal'] = preg_replace('/^\s*test\/bodybg\.gif,?$/m', '  test/generated-audit-fixture.md', $files['pandoc.cabal'], 1) ?? $files['pandoc.cabal'];
        $files['pandoc-lua-engine/pandoc-lua-engine.cabal'] = preg_replace('/^\s*test\/sample\.lua,?$/m', '  test/generated-runner.lua', $files['pandoc-lua-engine/pandoc-lua-engine.cabal'], 1) ?? $files['pandoc-lua-engine/pandoc-lua-engine.cabal'];
        $files['pandoc-cli/pandoc-cli.cabal'] = preg_replace('/^\s*man\/pandoc-server\.1,?$/m', '  man/generated-runner.1', $files['pandoc-cli/pandoc-cli.cabal'], 1) ?? $files['pandoc-cli/pandoc-cli.cabal'];

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
        $t->same(['BUGS'], $audit['packageExtraFileClosure']['missingExtraDocFiles']['pandoc.cabal']);
        $t->same(['RUNNER_AUDIT.md'], $audit['packageExtraFileClosure']['unexpectedExtraDocFiles']['pandoc.cabal']);
        $t->same(['test/bodybg.gif'], $audit['packageExtraFileClosure']['missingExtraSourceFiles']['pandoc.cabal']);
        $t->same(['test/generated-audit-fixture.md'], $audit['packageExtraFileClosure']['unexpectedExtraSourceFiles']['pandoc.cabal']);
        $t->same(['test/sample.lua'], $audit['packageExtraFileClosure']['missingExtraSourceFiles']['pandoc-lua-engine/pandoc-lua-engine.cabal']);
        $t->same(['test/generated-runner.lua'], $audit['packageExtraFileClosure']['unexpectedExtraSourceFiles']['pandoc-lua-engine/pandoc-lua-engine.cabal']);
        $t->same([], $audit['packageExtraFileClosure']['missingExtraSourceFiles']['pandoc-server/pandoc-server.cabal'] ?? []);
        $t->same([], $audit['packageExtraFileClosure']['unexpectedExtraSourceFiles']['pandoc-server/pandoc-server.cabal'] ?? []);
        $t->same(['man/pandoc-server.1'], $audit['packageExtraFileClosure']['missingExtraSourceFiles']['pandoc-cli/pandoc-cli.cabal']);
        $t->same(['man/generated-runner.1'], $audit['packageExtraFileClosure']['unexpectedExtraSourceFiles']['pandoc-cli/pandoc-cli.cabal']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('missing Cabal package extra-doc-files: pandoc.cabal (BUGS)', $blocked);
        $t->contains('unexpected Cabal package extra-doc-files: pandoc.cabal (RUNNER_AUDIT.md)', $blocked);
        $t->contains('missing Cabal package extra-source-files: pandoc.cabal (test/bodybg.gif)', $blocked);
        $t->contains('pandoc-lua-engine/pandoc-lua-engine.cabal (test/sample.lua)', $blocked);
        $t->contains('pandoc-cli/pandoc-cli.cabal (man/pandoc-server.1)', $blocked);
        $t->contains('unexpected Cabal package extra-source-files: pandoc.cabal (test/generated-audit-fixture.md)', $blocked);
        $t->contains('pandoc-lua-engine/pandoc-lua-engine.cabal (test/generated-runner.lua)', $blocked);
        $t->contains('pandoc-cli/pandoc-cli.cabal (man/generated-runner.1)', $blocked);
        $t->contains('exact package-level extra-doc-files and extra-source-files closure', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks package-level cabal extra tmp file globs before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['pandoc.cabal'] = str_replace(
            "extra-source-files:\n",
            "extra-tmp-files: dist/generated-runner-cache/*.json\nextra-source-files:\n",
            $files['pandoc.cabal']
        );
        $files['pandoc-lua-engine/pandoc-lua-engine.cabal'] = str_replace(
            "build-type: Simple\nextra-source-files:",
            "build-type: Simple\nextra-tmp-files: tmp/lua-runner-cache\nextra-source-files:",
            $files['pandoc-lua-engine/pandoc-lua-engine.cabal']
        );
        $files['pandoc-server/pandoc-server.cabal'] = str_replace(
            "build-type: Simple\n",
            "build-type: Simple\nextra-tmp-files: tmp/server-runner-cache\n",
            $files['pandoc-server/pandoc-server.cabal']
        );
        $files['pandoc-cli/pandoc-cli.cabal'] = str_replace(
            "build-type: Simple\nextra-source-files:",
            "build-type: Simple\nextra-tmp-files: tmp/cli-runner-cache\nextra-source-files:",
            $files['pandoc-cli/pandoc-cli.cabal']
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
        $t->same(UpstreamRunnerDependencyAudit::expectedPackageExtraTmpFiles(), $audit['packageExtraFileClosure']['expectedExtraTmpFiles']);
        $t->same(['dist/generated-runner-cache/*.json'], $audit['packageExtraFileClosure']['presentExtraTmpFiles']['pandoc.cabal']);
        $t->same(['tmp/lua-runner-cache'], $audit['packageExtraFileClosure']['presentExtraTmpFiles']['pandoc-lua-engine/pandoc-lua-engine.cabal']);
        $t->same(['tmp/server-runner-cache'], $audit['packageExtraFileClosure']['presentExtraTmpFiles']['pandoc-server/pandoc-server.cabal']);
        $t->same(['tmp/cli-runner-cache'], $audit['packageExtraFileClosure']['presentExtraTmpFiles']['pandoc-cli/pandoc-cli.cabal']);
        $t->same($audit['packageExtraFileClosure']['presentExtraTmpFiles'], $audit['packageExtraFileClosure']['unexpectedExtraTmpFiles']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('unexpected Cabal package extra-tmp-files: pandoc.cabal (dist/generated-runner-cache/*.json)', $blocked);
        $t->contains('pandoc-lua-engine/pandoc-lua-engine.cabal (tmp/lua-runner-cache)', $blocked);
        $t->contains('pandoc-server/pandoc-server.cabal (tmp/server-runner-cache)', $blocked);
        $t->contains('pandoc-cli/pandoc-cli.cabal (tmp/cli-runner-cache)', $blocked);
        $t->contains('no unexpected package-level extra-doc-files, extra-source-files, extra-tmp-files, or native/system dependency fields', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks package-level cabal source repository drift before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $expectedRepository = implode("\n", [
            'source-repository head',
            '  type: git',
            '  location: https://github.com/jgm/pandoc.git',
        ]);
        $files = $requiredFiles($pinnedProject());
        $files['pandoc.cabal'] = str_replace(
            $expectedRepository,
            implode("\n", [
                'source-repository head',
                '  type: hg',
                '  location: https://example.invalid/jgm/pandoc',
                '  branch: runner-audit',
            ]),
            $files['pandoc.cabal']
        );
        $files['pandoc-lua-engine/pandoc-lua-engine.cabal'] = str_replace(
            $expectedRepository,
            '',
            $files['pandoc-lua-engine/pandoc-lua-engine.cabal']
        );
        $files['pandoc-server/pandoc-server.cabal'] .= implode("\n", [
            '',
            '',
            'source-repository this',
            '  type: git',
            '  location: https://github.com/jgm/pandoc-old.git',
        ]);
        $files['pandoc-cli/pandoc-cli.cabal'] = str_replace(
            $expectedRepository,
            $expectedRepository . "\n  subdir: pandoc-cli",
            $files['pandoc-cli/pandoc-cli.cabal']
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
        $t->same(UpstreamRunnerDependencyAudit::expectedPackageSourceRepositories(), $audit['packageSourceRepositoryClosure']['expected']);
        $t->same([
            'pandoc-lua-engine/pandoc-lua-engine.cabal' => ['head'],
        ], $audit['packageSourceRepositoryClosure']['missing']);
        $t->same([
            'pandoc.cabal' => [
                'head' => [
                    'expected' => [
                        'type' => 'git',
                        'location' => 'https://github.com/jgm/pandoc.git',
                    ],
                    'actual' => [
                        'type' => 'hg',
                        'location' => 'https://example.invalid/jgm/pandoc',
                    ],
                ],
            ],
        ], $audit['packageSourceRepositoryClosure']['mismatched']);
        $t->same([
            'pandoc-server/pandoc-server.cabal' => ['this'],
        ], $audit['packageSourceRepositoryClosure']['unexpected']);
        $t->same([
            'pandoc.cabal' => [
                'head' => ['branch: runner-audit'],
            ],
            'pandoc-cli/pandoc-cli.cabal' => [
                'head' => ['subdir: pandoc-cli'],
            ],
        ], $audit['packageSourceRepositoryClosure']['unexpectedFields']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('missing Cabal package source-repository stanzas: pandoc-lua-engine/pandoc-lua-engine.cabal (head)', $blocked);
        $t->contains('mismatched Cabal package source-repository stanzas: pandoc.cabal (head.type expected git, found hg, head.location expected https://github.com/jgm/pandoc.git, found https://example.invalid/jgm/pandoc)', $blocked);
        $t->contains('unexpected Cabal package source-repository stanzas: pandoc-server/pandoc-server.cabal (this)', $blocked);
        $t->contains('unexpected Cabal package source-repository fields: pandoc.cabal (head (branch: runner-audit)); pandoc-cli/pandoc-cli.cabal (head (subdir: pandoc-cli))', $blocked);
        $t->contains('exact package-level source-repository head closure', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks package-level cabal native system fields before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['pandoc.cabal'] = str_replace(
            "build-type: Simple\n",
            implode("\n", [
                'build-type: Simple',
                'c-sources: cbits/pandoc-runner-audit.c',
                'pkgconfig-depends: zlib >= 1.2, libarchive >= 3',
                'ld-options:',
                '  -Wl,--as-needed',
                '  -Wl,--export-dynamic',
                '',
            ]),
            $files['pandoc.cabal']
        );
        $files['pandoc-lua-engine/pandoc-lua-engine.cabal'] = str_replace(
            "build-type: Simple\n",
            implode("\n", [
                'build-type: Simple',
                'extra-libraries: lua5.4 pandoclua',
                'hsc2hs-options:',
                '  --cross-compile',
                '  --template=cbits/lua-template.hsc',
                '',
            ]),
            $files['pandoc-lua-engine/pandoc-lua-engine.cabal']
        );
        $files['pandoc-server/pandoc-server.cabal'] = str_replace(
            "build-type: Simple\n",
            implode("\n", [
                'build-type: Simple',
                'include-dirs: include cbits/server',
                'includes: server-audit.h',
                '',
            ]),
            $files['pandoc-server/pandoc-server.cabal']
        );
        $files['pandoc-cli/pandoc-cli.cabal'] = str_replace(
            "build-type: Simple\n",
            implode("\n", [
                'build-type: Simple',
                'frameworks: CoreFoundation',
                'extra-framework-dirs: /System/Library/Frameworks',
                'cxx-options: -DCLI_AUDIT',
                '',
            ]),
            $files['pandoc-cli/pandoc-cli.cabal']
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
        $t->same(UpstreamRunnerDependencyAudit::expectedPackageNativeSystemFields(), $audit['packageNativeSystemFieldClosure']['expectedNativeSystemFields']);
        $t->same([
            'c-sources' => ['cbits/pandoc-runner-audit.c'],
            'ld-options' => ['-Wl,--as-needed', '-Wl,--export-dynamic'],
            'pkgconfig-depends' => ['libarchive >= 3', 'zlib >= 1.2'],
        ], $audit['packageNativeSystemFieldClosure']['presentNativeSystemFields']['pandoc.cabal']);
        $t->same([
            'extra-libraries' => ['lua5.4', 'pandoclua'],
            'hsc2hs-options' => ['--cross-compile', '--template=cbits/lua-template.hsc'],
        ], $audit['packageNativeSystemFieldClosure']['presentNativeSystemFields']['pandoc-lua-engine/pandoc-lua-engine.cabal']);
        $t->same([
            'include-dirs' => ['cbits/server', 'include'],
            'includes' => ['server-audit.h'],
        ], $audit['packageNativeSystemFieldClosure']['presentNativeSystemFields']['pandoc-server/pandoc-server.cabal']);
        $t->same([
            'cxx-options' => ['-DCLI_AUDIT'],
            'extra-framework-dirs' => ['/System/Library/Frameworks'],
            'frameworks' => ['CoreFoundation'],
        ], $audit['packageNativeSystemFieldClosure']['presentNativeSystemFields']['pandoc-cli/pandoc-cli.cabal']);
        $t->same([
            'c-sources: cbits/pandoc-runner-audit.c',
            'ld-options: -Wl,--as-needed',
            'ld-options: -Wl,--export-dynamic',
            'pkgconfig-depends: libarchive >= 3',
            'pkgconfig-depends: zlib >= 1.2',
        ], $audit['packageNativeSystemFieldClosure']['unexpectedNativeSystemFields']['pandoc.cabal']);
        $t->same([
            'extra-libraries: lua5.4',
            'extra-libraries: pandoclua',
            'hsc2hs-options: --cross-compile',
            'hsc2hs-options: --template=cbits/lua-template.hsc',
        ], $audit['packageNativeSystemFieldClosure']['unexpectedNativeSystemFields']['pandoc-lua-engine/pandoc-lua-engine.cabal']);
        $t->same([
            'include-dirs: cbits/server',
            'include-dirs: include',
            'includes: server-audit.h',
        ], $audit['packageNativeSystemFieldClosure']['unexpectedNativeSystemFields']['pandoc-server/pandoc-server.cabal']);
        $t->same([
            'cxx-options: -DCLI_AUDIT',
            'extra-framework-dirs: /System/Library/Frameworks',
            'frameworks: CoreFoundation',
        ], $audit['packageNativeSystemFieldClosure']['unexpectedNativeSystemFields']['pandoc-cli/pandoc-cli.cabal']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('unexpected Cabal package native/system dependencies: pandoc.cabal (c-sources: cbits/pandoc-runner-audit.c, ld-options: -Wl,--as-needed, ld-options: -Wl,--export-dynamic, pkgconfig-depends: libarchive >= 3, pkgconfig-depends: zlib >= 1.2)', $blocked);
        $t->contains('pandoc-lua-engine/pandoc-lua-engine.cabal (extra-libraries: lua5.4, extra-libraries: pandoclua, hsc2hs-options: --cross-compile, hsc2hs-options: --template=cbits/lua-template.hsc)', $blocked);
        $t->contains('pandoc-server/pandoc-server.cabal (include-dirs: cbits/server, include-dirs: include, includes: server-audit.h)', $blocked);
        $t->contains('pandoc-cli/pandoc-cli.cabal (cxx-options: -DCLI_AUDIT, extra-framework-dirs: /System/Library/Frameworks, frameworks: CoreFoundation)', $blocked);
        $t->contains('no unexpected package-level extra-doc-files, extra-source-files, extra-tmp-files, or native/system dependency fields', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
];
