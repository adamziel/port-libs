# EPUB Compact Rootfile Validation

Slice: `pandoc-epub3-package-rootfile-validation-current-base-20260610T121603Z`
Date: 2026-06-10 UTC

## Behavior

This slice adds compact native PHP EPUB package validation for OCF
`META-INF/container.xml` rootfile inventories in `EpubPackage`.

- `validation.rootfiles` now reports the selected OPF rootfile, alternate
  rootfiles, OPF rootfile count, missing rootfile package targets, non-OPF
  rootfile entries, duplicate rootfile package parts, and diagnostics.
- Rootfile diagnostics are merged into `validation.diagnostics` and exposed
  through `summary().wordpressImport.packageValidation`.
- Hard rejection for malformed packages is unchanged. These diagnostics cover
  reviewable alternate rootfiles while the primary package remains loadable.

## Evidence

Focused syntax checks:

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`

Focused test:

- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
- Result: `1 test files, 752 assertions, 0 failures`

Example smoke:

- `php lanes/pandoc/examples/wordpress-epub3-package-preflight.php --self-test`
- Result: `epub3 package preflight self-test ok`

Full lane gate:

- `php tools/run-tests.php lanes/pandoc/tests`
- Result: `44 test files, 59905 assertions, 0 failures`

Lane accounting:

- `phpPass`: `2960 -> 2961`
- `phpFail`: remains `0`
- Added focused case: compact EPUB container rootfile diagnostics.

## Dependency Closure

No new support component is needed. This reuses native PHP `EpubPackage`,
`ZipPackage`, `OpcPackagePath`, existing OCF container parsing, and the
lane-local PHP test runner.

No Pandoc, EPUBCheck, `zip`/`unzip`, Cabal solver/build/test command, Haskell
runner, browser renderer, external validator, online service, live provider
test, or live-service provider test was executed.

## Non-Overlap

This does not repeat prior EPUB package work for mimetype hard validation, OPF
metadata/manifest/spine parsing, OPF metadata links, package sidecars,
fallback chains, encryption, media overlays, rich `EpubReader` multi-rendition
reporting, or navigation document diagnostics. It is limited to compact
non-fatal rootfile inventory diagnostics in `EpubPackage::validationReport()`.
