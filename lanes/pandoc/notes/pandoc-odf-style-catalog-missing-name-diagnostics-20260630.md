# ODF Style Catalog Missing-Name Diagnostics

Slice: `pandoc-odf-style-catalog-missing-name-diagnostics-20260630`
Issue: `plib-0i4`

## Scope

- `OdfReader` now records metadata-only diagnostics for nameless non-`style:style`
  style catalog definitions instead of silently dropping them before package
  style provenance is assembled.
- Covered definitions are `style:font-face`, `text:list-style`,
  OpenDocument number/data styles, `table:table-template`, `style:page-layout`,
  and `style:master-page`.
- Diagnostics preserve `sourcePart`, `sourceContainer`, and element-name
  provenance, and are carried through `packageStyles` under the existing
  `odf-style-package-provenance-metadata-only` byte policy.

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderStyleDiagnosticsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderStyleDiagnosticsTest.php`
  - 1 test file, 41 assertions, 0 failures

No Pandoc executable, Cabal/Haskell command, office suite, `zip`/`unzip`,
browser engine, TeX/PDF engine, external validator, online service, or live
provider test was executed.

## Accounting

- `lane-status.json` `phpPass`: `489 -> 490`
- `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json`:
  - `mappedOdfStyleDiagnosticsCases`: `1 -> 2`
  - `odfStyleDiagnosticsAssertions`: `17 -> 41`
