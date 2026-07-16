# ODF OpenDocument Duplicate Named Expressions

Slice: `pandoc-odf-open-document-core-current-base-duplicate-20260609T043924Z`
Base accepted HEAD: `751070fca2ca1c3ef7b50b0753a60f0f2fcd712e`

## Implementation

Native `OdfReader` now preserves duplicate OpenDocument
`table:named-range` / `table:named-expression` declaration names as explicit
review metadata:

- `namedExpressionNameOccurrences`
- `namedExpressionDuplicateNameCount`
- `namedExpressionDuplicateEntryCount`
- `namedExpressionDuplicateNames`
- matching import-report content counts

The full declaration list was already preserved, while the by-name lookup must
collapse duplicate names. This slice keeps that lookup behavior unchanged and
adds collision metadata so WordPress import review packets can flag conflicting
source range/formula declarations instead of silently hiding them. It does not
evaluate formulas, execute database ranges, or invoke office tooling.

## Verification

Baseline focused ODF reader test before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 2951 assertions, 0 failures
```

Final focused ODF reader test:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 2969 assertions, 0 failures
```

WordPress ODF database-field smoke:

```text
php lanes/pandoc/examples/wordpress-odf-database-field-handoff.php --self-test
odf database field handoff self-test ok
```

## Status Delta

- `phpPass`: `2312 -> 2313`
- `benchmarkDenominator.mapped`: `2712 -> 2713`
- `mappedOdfOpenDocumentCoreCases`: `13 -> 14`
- `odfOpenDocumentCoreAssertions`: `295 -> 313`
- Focused ODF assertions: `2951 -> 2969` (`+18`)

## Dependency Closure

No new support component is needed. This reuses native PHP `OdfReader`
DOM/XML parsing, existing content declaration/import-report metadata,
`ZipPackage` fixtures, and the lane-local WordPress ODF database-field smoke.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, external converter, online service, live provider test, or
live-service provider test was executed.

## Non-Overlap

This does not repeat accepted ODF dropdown fields, variable/user field
fallbacks, metadata-field fallbacks, page-variable/chapter/file/statistic
fields, database range subtotal rules, label ranges, calculation settings,
print ranges, data-pilot metadata, table annotations, drawing layers, chart or
object metadata, or rendered table/list style behavior. It is limited to
duplicate named range/expression declaration diagnostics.

## Root Harness

Root harness not run - isolated micro-slice.

## Follow-Up

A useful next ODF slice would be additional data-pilot source metadata,
database-range edge provenance, or style-driven table/list semantics not
already covered by duplicate named declarations.
