# Direct ODT Style Font-Face Diagnostics

Slice: `pandoc-odt-style-font-face-diagnostics-20260630`
Issue: `plib-27la`

## Scope

- `OdtReader` now records direct `style:font-face` declarations and reports
  unresolved `style:text-properties style:font-name` references as
  metadata-only `odtStyleDiagnostics`.
- Diagnostics include source part, element name, style name, font name, and
  style family provenance.
- Rendering behavior is unchanged; this only enriches style review metadata.

## Verification

- `php -l lanes/pandoc/src/OdtReader.php`
- `php -l lanes/pandoc/tests/OdtReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdtReaderTest.php`
  - 1 test file, 138 assertions, 0 failures

No Pandoc executable, Cabal/Haskell command, office suite, `zip`/`unzip`,
browser engine, TeX/PDF engine, external validator, online service, or live
provider test was executed.

## Accounting

- `lane-status.json` `phpPass`: `491 -> 492`
- `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json`:
  - `mappedOdtStyleDiagnosticsCases`: `3 -> 4`
  - `odtStyleDiagnosticsAssertions`: `46 -> 56`
