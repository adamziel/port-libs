# Direct ODT Style Parent-Cycle Diagnostics

Slice: `pandoc-odt-style-parent-cycle-diagnostics-20260701`
Issue: `plib-88v`

## Scope

- `OdtReader` now reports metadata-only `odt-style-parent-cycle`
  diagnostics for direct ODT `style:parent-style-name` cycles.
- Diagnostics include source part, element name, style name, cycle members,
  cycle path, and family provenance.
- Rendering behavior is unchanged; cyclic parent links are reviewed without
  applying inherited formatting in the direct reader path.

## Verification

- `php -l lanes/pandoc/src/OdtReader.php`
- `php -l lanes/pandoc/tests/OdtReaderTest.php`
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `php tools/run-tests.php lanes/pandoc/tests/OdtReaderTest.php`
  - 1 test file, 163 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OdtReaderTest.php lanes/pandoc/tests/OdfReaderStyleDiagnosticsTest.php lanes/pandoc/tests/OdfReaderStylePackageProvenanceTest.php lanes/pandoc/tests/OdfReaderTest.php`
  - 4 test files, 5,661 assertions, 0 failures

No Pandoc executable, Cabal/Haskell command, office suite, `zip`/`unzip`,
browser engine, TeX/PDF engine, external validator, online service, or live
provider test was executed.

## Accounting

- Direct-format parity: direct ODT style diagnostics now carry parent-cycle
  review metadata matching the existing package ODF style parent-cycle
  diagnostic shape.
- `UPSTREAM_TEST_MANIFEST.json`:
  - `mappedOdtStyleDiagnosticsCases`: `5 -> 6`
  - `odtStyleDiagnosticsAssertions`: `70 -> 81`
