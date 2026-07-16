# pandoc-epub3-package-core-current-base-20260609T035040Z

Base accepted HEAD: `64291fcd23e3d1b723e600a8842760d1fbcdb417`

## Behavior

Implemented bounded native PHP EPUB3 package preflight support for OPF
`bindings` media-type handlers in the compact `EpubPackage` API.

- `EpubPackage` now exposes `bindings()` plus `summary()['bindings']`.
- Each binding records source index, media type, handler manifest id, handler
  href, handler package part, handler media type/properties, byte length, CRC,
  and item diagnostics.
- Missing handler manifest items and malformed binding rows remain review
  diagnostics instead of being silently dropped.
- `summary()['wordpressImport']` now carries `mediaTypeBindings` and
  `mediaTypeBindingDiagnostics` so WordPress import review can triage custom
  EPUB resources without executing handlers.

## Evidence

Baseline focused verification before the implementation:

- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
- Result: `1 test files, 90 assertions, 0 failures`

Final focused verification:

- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
- Result: `1 test files, 115 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-epub3-package-preflight.php --self-test`
- Result: `epub3 package preflight self-test ok`
- `php -l lanes/pandoc/src/EpubPackage.php`
- Result: no syntax errors
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- Result: no syntax errors
- `php -l lanes/pandoc/examples/wordpress-epub3-package-preflight.php`
- Result: no syntax errors

Focused delta: +1 PHP PASS line and +25 focused assertions in
`EpubPackageTest.php`.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native PHP `EpubPackage`
OPF/container parsing, `ZipPackage` package part metadata, `OpcPackagePath`
target resolution, focused `EpubPackageTest` coverage, and the existing
WordPress EPUB package preflight example.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, external converter, browser renderer, online service, live provider
test, or live-service provider test was executed.

## Non-Overlap

No active `port-pandoc-*.needs-lane-rework.md` rework note existed before
editing.

This does not repeat rich `EpubReader` binding-handler fallback execution,
OPF metadata refinement preflight, vendor metadata, accessibility metadata,
metadata-link vocabulary, manifest property vocabulary, nav/NCX page-list/audio,
XHTML resource/script/link/form/switch/trigger/semantic scans, media overlays,
remote-resource reconciliation, OCF sidecars, encryption, asset fallback, or
embedded media/object/frame slices. It is restricted to compact `EpubPackage`
preflight summaries for OPF media-type bindings.

## Follow-Up

Good non-overlapping EPUB3 package follow-ups are compact collection metadata
preflight, package-level remote metadata-link target policy, or deeper
lightweight media-overlay summary parity.
