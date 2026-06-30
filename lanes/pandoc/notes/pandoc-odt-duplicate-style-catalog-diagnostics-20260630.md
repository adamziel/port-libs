# Direct ODT Duplicate Style Catalog Diagnostics

Slice: `pandoc-odt-duplicate-style-catalog-diagnostics-20260630`
Issue: `plib-27la`

## Scope

- `OdtReader` now reports duplicate direct ODT `style:style`,
  `style:font-face`, and `text:list-style` catalog names as metadata-only
  `odtStyleDiagnostics`.
- Diagnostics preserve previous/replacement family, element, and source-part
  provenance where available while keeping the existing direct-reader shadowing
  and list-style merge behavior unchanged.
- Rendering behavior is unchanged; the slice only enriches native reviewer
  diagnostics under `lanes/pandoc`.

## Verification

- `php -l lanes/pandoc/src/OdtReader.php`
- `php -l lanes/pandoc/tests/OdtReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdtReaderTest.php`
  - 1 test file, 152 assertions, 0 failures

No Pandoc executable, Cabal/Haskell command, office suite, `zip`/`unzip`,
browser engine, TeX/PDF engine, external validator, online service, or live
provider test was executed.

## Accounting

- `lane-status.json` `phpPass`: `492 -> 493`
- `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json`:
  - `mappedOdtStyleDiagnosticsCases`: `4 -> 5`
  - `odtStyleDiagnosticsAssertions`: `56 -> 70`
