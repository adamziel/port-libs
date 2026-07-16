# pandoc-epub3-package-core-current-base-20260605T153906Z

Base accepted HEAD: `3b703edd8291ffd1199c4be8cdf020ab56e5efde`

## Slice

Implemented bounded EPUB OCF `META-INF/container.xml` `<links>` handoff in the native PHP EPUB reader.

The reader now reports container-level link metadata alongside rootfiles:

- `container.linkCount`
- `container.links`
- `container.linksByRel`
- `container.linkDiagnostics`

Each link preserves `href`, tokenized `rel`, `media-type`, `properties`, optional `refines`, resolved package-root `target`, package `part`, fragment metadata, EPUB CFI fragment details, external/missing state, byte length, CRC32, SHA-256 for local targets, and per-link diagnostics. Remote links are classified as unfetched external references; missing local package targets remain explicit diagnostics.

## Evidence

Baseline before this slice:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
- Result: `1 test files, 936 assertions, 0 failures`

Red-first check after adding the focused test and before implementation:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
- Result: `1 test files, 937 assertions, 1 failures`
- Failure: `container.linkCount` was missing from the reader output.

Green focused check after implementation:

- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
- Result: `1 test files, 970 assertions, 0 failures`

Example smoke:

- `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
- Result: `epub3 package handoff self-test ok`

Final syntax and metadata checks:

- `php -l lanes/pandoc/src/EpubReader.php`
- Result: `No syntax errors detected in lanes/pandoc/src/EpubReader.php`
- `php -l lanes/pandoc/tests/EpubReaderTest.php`
- Result: `No syntax errors detected in lanes/pandoc/tests/EpubReaderTest.php`
- `php -l lanes/pandoc/examples/wordpress-epub3-package-handoff.php`
- Result: `No syntax errors detected in lanes/pandoc/examples/wordpress-epub3-package-handoff.php`
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status ok\n";'`
- Result: `lane-status ok`
- `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "manifest ok\n";'`
- Result: `manifest ok`
- `git diff --check -- lanes/pandoc`
- Result: passed with no output

## Status Delta

- Added 1 focused PHP PASS case.
- Added 34 focused EPUB reader assertions.
- Updated `lanes/pandoc/lane-status.json` `phpPass` from `977` to `978`.
- Updated `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator from `1432` to `1433`.
- Updated EPUB3 package core mapped cases from `4` to `5` and assertion inventory from `62` to `96`.

## Dependency Closure

No new support component was needed. This reused native `ZipPackage`, `ZipPackageEntry`, OPC package path helpers, XML DOM loading, existing EPUB CFI fragment reporting, and the lane-local PHP test harness.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, ZipArchive, external archive tool, browser renderer, online sanitizer, online service, or external converter was executed.

## Non-Overlap / Exclusions

This slice does not change OPF metadata links, nav/NCX parsing, SMIL overlays, remote XHTML resource fetching, alternate rendition selection, OCF rights/signature cryptographic validation, encrypted/obfuscated font handling, or XHTML-to-AST conversion. Container links are parsed as static package metadata only.

Root harness: not run - isolated micro-slice.
