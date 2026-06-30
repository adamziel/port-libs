# Direct ODT Style Next Diagnostics

Slice: `pandoc-odt-style-next-diagnostics-20260630`
Issue: `plib-27la`

## Scope

- `OdtReader` now records `style:next-style-name` on direct text style
  definitions and reports unresolved next-style targets as metadata-only style
  diagnostics.
- Diagnostics include source part, element name, style name, next style name,
  and style family provenance.
- Rendering behavior is unchanged; next-style links are reviewed without
  applying follow-on paragraph style semantics in the direct reader path.

## Verification

- `php -l lanes/pandoc/src/OdtReader.php`
- `php -l lanes/pandoc/tests/OdtReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdtReaderTest.php`
  - 1 test file, 85 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OdtReaderTest.php lanes/pandoc/tests/OdfReaderStyleDiagnosticsTest.php lanes/pandoc/tests/OdfReaderStylePackageProvenanceTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - 4 test files, 2,285 assertions, 0 failures

No Pandoc executable, Cabal/Haskell command, office suite, `zip`/`unzip`,
browser engine, TeX/PDF engine, external validator, online service, or live
provider test was executed.

## Accounting

- `lane-status.json` `phpPass`: `488 -> 489`
- `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json`:
  - `mappedOdtStyleDiagnosticsCases`: `2 -> 3`
  - `odtStyleDiagnosticsAssertions`: `36 -> 46`
