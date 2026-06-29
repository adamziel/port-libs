# Direct ODT Style Parent Diagnostics

Slice: `pandoc-odt-style-parent-diagnostics-20260629`
Issue: `plib-27la`

## Scope

- `OdtReader` now reports missing `style:parent-style-name` targets for direct
  ODT text styles as metadata-only document diagnostics.
- Diagnostics include source part, element name, style name, parent style name,
  and family provenance.
- Rendering behavior is unchanged; parent references are reviewed without
  applying inherited formatting in the direct reader path.

## Verification

- `php -l lanes/pandoc/src/OdtReader.php`
- `php -l lanes/pandoc/tests/OdtReaderTest.php`
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `php tools/run-tests.php lanes/pandoc/tests/OdtReaderTest.php`
  - 1 test file, 75 assertions, 0 failures

No Pandoc executable, Cabal/Haskell command, office suite, `zip`/`unzip`,
browser engine, TeX/PDF engine, external validator, online service, or live
provider test was executed.

## Accounting

- `lane-status.json` `phpPass`: `487 -> 488`
- `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json`:
  - `mappedOdtStyleDiagnosticsCases`: `1 -> 2`
  - `odtStyleDiagnosticsAssertions`: `19 -> 29`
