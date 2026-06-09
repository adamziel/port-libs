# pandoc-epub3-package-core-current-base-20260609T053523Z

Base accepted HEAD: `43b1a4a1010b27f9642a54fbdd65b896e3bf9eec`

## Behavior

Implemented compact native PHP EPUB3 package preflight support for OPF
metadata link vocabulary tokens in `EpubPackage`.

`EpubPackage` now parses OPF `package@prefix` declarations, preserves effective
reserved/package prefix bindings and prefix diagnostics, and attaches
`relVocabulary` plus `propertyVocabulary` reports to OPF metadata `<link>`
records. The compact summary now exposes `packageLinkVocabulary` and WordPress
import aliases for vocabulary diagnostics.

The report classifies:

- NMTOKEN values such as `record` and `schema-org`;
- resolved prefixed names such as `schema:associatedMedia`;
- absolute vocabulary URLs only when they include a fragment identifier;
- invalid tokens;
- duplicate tokens;
- unknown package prefixes.

This is compact `EpubPackage` preflight only. It does not change the richer
`EpubReader` package reader and does not fetch remote metadata records.

## Evidence

Baseline focused verification before adding the new test:

- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
- Result: `1 test files, 208 assertions, 0 failures`

Red-first focused verification after adding the test:

- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
- Result: failed because `relVocabulary`, `propertyVocabulary`, and
  `prefixBindings` were absent; run reached `1 test files, 209 assertions, 1 failures`.

Final focused verification:

- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
- Result: `1 test files, 248 assertions, 0 failures`

Example smoke:

- `php lanes/pandoc/examples/wordpress-epub3-package-preflight.php --self-test`
- Result: `epub3 package preflight self-test ok`

Syntax and JSON checks:

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php -l lanes/pandoc/examples/wordpress-epub3-package-preflight.php`
- Result: no syntax errors
- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $f) { json_decode((string) file_get_contents($f), true, 512, JSON_THROW_ON_ERROR); echo $f . " ok\n"; }'`
- Result: both JSON files decoded successfully
- `git diff --check -- lanes/pandoc`
- Result: passed

Focused delta: +1 PHP TestRunner PASS case and +40 focused assertions in
`EpubPackageTest.php`. `lane-status.json` moves `phpPass` from `2382` to
`2383`; `UPSTREAM_TEST_MANIFEST.json` moves mapped EPUB/Pandoc support from
`2776` to `2777`, `mappedEpub3PackageCoreCases` from `6` to `7`, and
`epub3PackageCoreAssertions` from `112` to `152`.

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new native support component is needed. This slice reuses native PHP
`EpubPackage`, `ZipPackage`, `ZipPackageEntry`, `OpcPackagePath`, DOM/libxml
NONET XML parsing, the focused PHP test runner, and the existing WordPress
EPUB3 package preflight example.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, EPUBCheck, external converter, browser renderer, online service,
live provider test, or live-service provider test was executed.

Full upstream Pandoc runner parity remains a separate upstream-runner
dependency task requiring hydrated pinned upstream sources and Haskell test
executables.

## Non-Overlap

No active `port-pandoc-*.needs-lane-rework.md` rework note existed before
editing; only historical stale notes were present under the stale handoff
directory.

This does not repeat accepted EPUB OCF mimetype/container/rootfile validation,
compact OPF metadata refinements, compact OPF metadata link target records,
compact OPF guide/nav-section preflight, compact media-type bindings, compact
OPF collections, compact package/collection remote-resource policy summaries,
rich `EpubReader` metadata link reports, vendor metadata, accessibility
metadata, manifest property vocabulary, nav/NCX/page-list/audio, XHTML/CSS
resource scans, `EpubReader` remote-resource reconciliation, media overlays,
OCF sidecars, encryption, asset fallback, or embedded media/object/frame
slices. It is restricted to compact OPF metadata link vocabulary diagnostics
for WordPress EPUB package preflight.

## Follow-Up

Good non-overlapping EPUB3 package follow-ups are compact media-overlay summary
parity, encrypted-resource review policy, XHTML-to-AST conversion boundaries,
or EPUBCheck-style OPF validation diagnostics.
