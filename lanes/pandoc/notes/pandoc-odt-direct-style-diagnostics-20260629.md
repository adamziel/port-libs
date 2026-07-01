# Direct ODT Style Diagnostics

Slice: `pandoc-odt-direct-style-diagnostics-20260629`
Issue: `plib-27la`

## Scope

- `OdtReader` now records metadata-only style diagnostics on the document meta
  packet without changing rendering behavior.
- The direct reader reports malformed `style:style` definitions, nameless
  `text:list-style` definitions, and unresolved `text:span`/`text:list` style
  references from `content.xml`.
- Diagnostics include compact source provenance (`sourcePart`, ODT element
  name, style/list style names, and family where relevant).

## Verification

- `php -l lanes/pandoc/src/OdtReader.php`
- `php -l lanes/pandoc/tests/OdtReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdtReaderTest.php`
  - 1 test file, 65 assertions, 0 failures

No Pandoc executable, Cabal/Haskell command, office suite, `zip`/`unzip`,
browser engine, TeX/PDF engine, external validator, online service, or live
provider test was executed.

## Accounting

- `lane-status.json` `phpPass`: `486 -> 487`
- `UPSTREAM_TEST_MANIFEST.json`:
  - `mappedOdtStyleDiagnosticsCases`: `1`
  - `odtStyleDiagnosticsAssertions`: `19`
