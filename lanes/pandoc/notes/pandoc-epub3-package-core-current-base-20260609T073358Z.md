# pandoc-epub3-package-core-current-base-20260609T073358Z

Base accepted HEAD: `4d33e428da4780248f05e2619ed97a382cb59fe0`

## Behavior

Implemented compact native PHP EPUB3 package preflight support for OPF
manifest fallback chains in `EpubPackage`.

- `EpubPackage::manifestFallbacks()` now exposes items with OPF `fallback` or
  `fallback-style` declarations.
- Manifest fallback chains preserve terminal id, href, package part, media
  type, byte length, CRC32, core media-type kind, EPUB content-document status,
  and usable/resolved flags.
- Manifest fallback-style chains preserve CSS terminal metadata and report
  missing, cyclic, and non-CSS fallback-style diagnostics.
- `summary()` and `summary()['wordpressImport']` expose fallback items,
  fallback-style items, and flattened diagnostics for WordPress import review.

This is compact package preflight only. It does not parse fallback XHTML into
blocks, fetch remote resources, run handlers, or invoke EPUB validators.

## Evidence

Baseline focused verification before the new test:

- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
- Result: `1 test files, 324 assertions, 0 failures`

Red-first focused verification after adding the test:

- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
- Result: failed because `EpubPackage::manifestFallbacks()` was missing; run
  reached `1 test files, 324 assertions, 1 failures`.

Final focused verification:

- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
- Result: `1 test files, 367 assertions, 0 failures`

Example smoke:

- `php lanes/pandoc/examples/wordpress-epub3-package-preflight.php --self-test`
- Result: `epub3 package preflight self-test ok`

Focused delta: +1 PHP TestRunner PASS case and +43 focused assertions in
`EpubPackageTest.php`. `lane-status.json` moves `phpPass` from `2503` to
`2504`; `UPSTREAM_TEST_MANIFEST.json` moves mapped support from `2879` to
`2880`, `mappedEpub3PackageCoreCases` from `6` to `7`, and
`epub3PackageCoreAssertions` from `112` to `155`.

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new native support component is needed. This slice reuses native PHP
`EpubPackage`, `ZipPackage`, `ZipPackageEntry`, existing OPF manifest parsing,
focused `EpubPackageTest` coverage, and the existing WordPress EPUB3 package
preflight example.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, tar, external converter, EPUBCheck, browser renderer, online
service, live provider test, or live-service provider test was executed.

Full upstream Pandoc runner parity remains a separate upstream-runner
dependency task requiring hydrated pinned upstream sources and Haskell test
executables.

## Non-Overlap

No active `port-pandoc-*.needs-lane-rework.md` rework note existed before
editing.

This does not repeat accepted OCF container/rootfile handling, compact OPF
metadata refinements, metadata link records or vocabulary diagnostics,
guide/nav-section preflight, media-type bindings, OPF collections,
remote-resource policy, compact media-overlay summaries, full `EpubReader`
fallback block extraction, full `EpubReader` asset fallback reports,
vendor metadata, accessibility metadata, XHTML/CSS resource scans, sidecar
metadata, encryption exposure policy, or ZIP integrity work. It is restricted
to compact `EpubPackage` fallback and fallback-style chain preflight.

## Follow-Up

Good non-overlapping EPUB3 package follow-ups are encrypted-resource compact
policy, package-level rendition metadata in `EpubPackage`, XHTML-to-AST
conversion boundaries, or EPUBCheck-style static validation diagnostics.
