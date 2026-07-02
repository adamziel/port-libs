# ODF Style Diagnostic Source Containers

Slice: `pandoc-odf-style-diagnostic-source-containers-20260702`
Issue: `plib-88v`
Target: `integration/pandoc-package-odf`

## Scope

- `OdfReader` package style provenance now summarizes diagnostic source containers at the package level and per package part.
- The metadata exposes `diagnosticSourceContainers` and `diagnosticSourceContainerCounts`, so reviewers can distinguish diagnostics from `office:styles` versus `office:automatic-styles` without reading style XML bytes.
- Existing diagnostic grouping by source part and diagnostic code remains unchanged; this slice expands the existing ODF style diagnostics case without increasing the mapped case denominator.

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderStylePackageProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderStylePackageProvenanceTest.php`
  - 1 test file, 70 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderStylePackageProvenanceTest.php lanes/pandoc/tests/OdfReaderTest.php`
  - 2 test files, 5,463 assertions, 0 failures
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- `git diff --check origin/integration/pandoc-package-odf...HEAD -- lanes/pandoc`
- Conflict-marker scan of changed lane files

No Pandoc executable, Cabal/Haskell command, office suite, `zip`/`unzip`,
browser engine, TeX/PDF engine, external validator, online service, or live
provider test was executed.

## Accounting

- `UPSTREAM_TEST_MANIFEST.json` `odfStyleDiagnosticsAssertions`: `41 -> 47`
- `mappedOdfStyleDiagnosticsCases`: unchanged at `2`
