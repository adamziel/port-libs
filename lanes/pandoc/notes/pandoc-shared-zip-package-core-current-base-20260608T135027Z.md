# pandoc-shared-zip-package-core-current-base-20260608T135027Z

Base accepted HEAD: `866acb3705c41894d861c1038e1d10801bbc0d5b`

## Scope

Implemented a bounded raw-byte ZIP strict import preflight for the Pandoc support library. The new `ZipPackage::rawStrictImportPreflight()` aggregates existing native ZIP scanners for EOCD layout, ZIP64 accounting, split archive markers, central-directory inventory, archive extra data records, encryption, compression methods, ZIP64 extra fields, data descriptors, and normal strict import when instantiation succeeds.

This covers the previously noted ZIP follow-up where unsupported package bytes could not be passed through `ZipPackage::fromString()` just to get structured strict-import diagnostics.

## Evidence

- No `port-pandoc-*.needs-lane-rework.md` note existed for this lane before editing.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` passed with `1 test files, 1511 assertions, 0 failures`.
- Final: `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` passed with `1 test files, 1569 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test` passed.
- PHP lint passed for:
  - `lanes/pandoc/src/ZipPackage.php`
  - `lanes/pandoc/tests/ZipPackageTest.php`
  - `lanes/pandoc/examples/wordpress-zip-package-preflight.php`
- Lane JSON validation passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed.
- Lane counters: one new focused PHP PASS case, `+58` focused assertions, `phpPass` `1659 -> 1660`, `benchmarkDenominator.mapped` `2079 -> 2080`.

## Dependency Closure

No new native PHP support component is needed. This slice reuses existing bounded ZIP package primitives and does not invoke Pandoc, Cabal, Haskell runners, zip/unzip, ZipArchive, Word, LibreOffice, external archive tools, online services, live provider tests, or live-service provider tests.

## Non-Overlap

This does not repeat the accepted ZIP slices for central-directory signatures, archive extra scanning, split archive scanning, ZIP64 EOCD accounting, encryption preflight, compression-method preflight, ZIP64 extra-field rejection, invalid DOS timestamps, Unicode name collisions, raw deflate stream-consumption validation, local-header order, or strict import aggregation over already-instantiated packages. The new behavior is the raw strict aggregation wrapper for unsupported package bytes before instantiation.

## Follow-Up

A useful non-overlapping ZIP/OPC follow-up would be to carry central-directory digital signature and raw strict import diagnostics into OPC package import reports so DOCX/ODT/EPUB readers can surface package-level rejection reasons without attempting entry materialization.
