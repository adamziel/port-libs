# pandoc-table-geometry-core-current-base-duplicate-20260609T072211Z

Base accepted HEAD: `93c7fe92d8764429cde901a465ac3a9266aec0d4`

## Behavior

Implemented a bounded table-geometry reader handoff for malformed HTML column-source spans:

- `MarkdownReader` now records `html-column-span-normalized` diagnostics when a source `<colgroup span>` or `<col span>` value is missing the required positive integer shape and is normalized to one source column.
- `TableGeometry` review packet summaries now expose `hasNormalizedColumnSpans`, `normalizedColumnSpanCount`, and `normalizedColumnSpanSourceElements` separately from cell `rowspan` / `colspan` normalization.
- `WordPressBlockWriter` already rendered expanded safe `<col>` elements and filtered source `span` attributes; the updated smoke proves malformed `span="0"`, textual, and negative column-source spans do not leak into WordPress output while raw provenance remains in review packets.

This is intentionally distinct from the already accepted duplicate source-id, duplicate header-id, duplicate `headers` token, source header axis, malformed cell span, and colgroup count-mismatch slices.

## Source Truth

The mapped upstream surface is Pandoc's HTML table reader/writer contract around source column geometry and table colspec preservation from the static inventory at upstream Pandoc `0640c4c9859aa5a3ede082c190fcd5883c24ac83`, especially the existing table fixtures and command cases:

- `test/html-reader.html`
- `test/html-reader.native`
- `test/command/table-with-cell-align.md`
- `test/command/table-with-column-span.md`
- `test/tables.markdown`
- `test/tables.native`

The native PHP slice ports the format contract only. It did not shell out to Pandoc or any external document converter.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - `1 test files, 1348 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - `2 test files, 3236 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - `table geometry handoff self-test ok`

New focused test delta:

- `TableGeometryReaderHandoffTest.php`: +1 PASS case, +34 assertions versus the prior duplicate-source-id slice evidence (`1314 -> 1348`).
- `UPSTREAM_TEST_MANIFEST.json`: `benchmarkDenominator.mapped 2867 -> 2868`, `mappedTableGeometryCoreCases 9 -> 10`, `tableGeometryCoreAssertions 155 -> 189`.
- `lane-status.json`: `phpPass 2489 -> 2490`, `phpFail` remains `0`.

## Dependency Closure

No new native support component is needed. This slice reuses the existing pure-PHP `MarkdownReader`, `TableGeometry`, and `WordPressBlockWriter` paths. Broader Pandoc HTML-table runner parity remains outside this isolated support-library handoff.

## Exclusions

Not run: Pandoc, Cabal/Haskell test runners, Word, LibreOffice, zip/unzip, external template engines, TeX/PDF engines, browser renderers, online services, live provider tests, root harness.

