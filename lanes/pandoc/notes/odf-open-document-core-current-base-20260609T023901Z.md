# ODF OpenDocument Label Ranges

Slice: `pandoc-odf-open-document-core-current-base-20260609T023901Z`
Base: `cff2757f3c2ce59e8912b5b48a787409562aacb3`

## Implementation

Native `OdfReader` content declarations now preserve bounded
OpenDocument `table:label-ranges` metadata:

- `table:label-range@table:label-cell-range-address`
- `table:label-range@table:data-cell-range-address`
- `table:label-range@table:orientation`
- aggregate `labelRangeCount` and `labelRangeOrientationCounts`

This stays metadata-only. The reader does not evaluate spreadsheet formulas,
execute labels, run LibreOffice, or shell out to Pandoc.

## Verification

Focused ODF reader test:

```bash
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
```

Result:

```text
1 test files, 2772 assertions, 0 failures
```

WordPress ODF database-field smoke:

```bash
php lanes/pandoc/examples/wordpress-odf-database-field-handoff.php --self-test
```

Result:

```text
odf database field handoff self-test ok
```

PHP syntax checks:

```bash
php -l lanes/pandoc/src/OdfReader.php
php -l lanes/pandoc/tests/OdfReaderTest.php
php -l lanes/pandoc/examples/wordpress-odf-database-field-handoff.php
```

Result: all reported no syntax errors.

Final workspace checks:

```bash
php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'
git diff --check -- lanes/pandoc
```

Result: JSON validation printed `pandoc json ok`; `git diff --check -- lanes/pandoc` passed with no output.

## Status Delta

- `phpPass`: `2162 -> 2163`
- `benchmarkDenominator.mapped`: `2586 -> 2587`
- `mappedOdfOpenDocumentCoreCases`: `13 -> 14`
- `odfOpenDocumentCoreAssertions`: `295 -> 311`
- New focused assertions: `+16`

## Dependency Closure

No new support component is needed. This reuses native PHP `OdfReader` DOM
parsing, `ZipPackage` fixture construction, the focused ODF reader suite, and
the WordPress ODF database-field handoff example. Full upstream Pandoc runner
parity remains a separate upstream-runner dependency task requiring a hydrated
Pandoc checkout and Haskell test executables.

## Non-Overlap

This does not repeat accepted ODF database range filters/sorts/subtotal rules,
named expressions, data-pilot tables, tracked table changes, DDE fields, draw
layers, style-driven table cells, covered-cell provenance, dropdown fields, or
manifest/media/encryption handling. It adds only metadata preservation for
`table:label-ranges`.

## Follow-Up

Good follow-up ODF slices: table scenarios, calculation settings, print ranges,
or additional data-pilot source edge metadata.
