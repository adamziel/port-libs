# Pandoc ODF OpenDocument Core Current Base

Slice: `pandoc-odf-open-document-core-current-base-20260607T081108Z`

Accepted base: `e54667dcf3d17df8e001d9df1a3dbd7885b17703`

Implemented one bounded native ODF chart-object metadata handoff:

- `OdfReader` now inspects embedded chart object `content.xml` for `chart:chart`, `chart:plot-area`, `chart:categories`, and `chart:series` metadata when a `draw:object` manifest entry is `application/vnd.oasis.opendocument.chart`.
- The reader preserves chart class, sanitized class name, source cell range, label policy, category range, and series ranges in `chartMetadata` on the existing embedded-object placeholder.
- Markdown and WordPress handoff expose only safe `data-odf-chart-*` reviewer attributes such as `data-odf-chart-class="bar"` and `data-odf-chart-cell-range="Sheet1.A1:Sheet1.B4"`; raw chart XML and raw `chart:*` class names stay out of rendered output.
- The ODF import report now counts `chartObjectCount` and `chartMetadataCount`.
- The WordPress ODF handoff example includes a chart data-range fixture and self-tests the sanitized chart metadata attributes.

Source truth: this is bounded to the OpenDocument package/content XML contract already used by the native ODF reader. ODF chart objects are package subdocuments with `office:chart/chart:chart` content, `chart:plot-area` ranges, and `chart:series` metadata. This patch does not evaluate chart formulas, render chart graphics, expose embedded chart bytes, or invoke external office/converter tooling.

Red-first evidence:

```bash
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
```

Result after adding the chart metadata expectations and before implementation: failed as expected in `maps ODT chart draw objects into embedded object review placeholders`; expected `chartMetadata.sourcePart` was `Object Chart/content.xml`, actual was `NULL`. The red run reached `1 test files, 1367 assertions, 1 failures`.

Final focused verification:

```bash
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
```

Result: `1 test files, 1413 assertions, 0 failures`.

```bash
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php lanes/pandoc/tests/OdtReaderTest.php
```

Result: `2 test files, 1508 assertions, 0 failures`.

```bash
php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test
```

Result: `odf open document handoff self-test ok`.

Expected lane movement:

- ODF/OpenDocument core cases: `11 -> 12`.
- Mapped ODF/OpenDocument core cases: `11 -> 12`.
- ODF/OpenDocument focused assertions: `251 -> 297` for this manifest counter, reflecting the `1367 -> 1413` focused ODF assertion movement from the red-first fixture expansion to final green coverage.
- PHP PASS cases: `+1` focused ODF test case.

Dependency closure: no new support component is needed. This reuses native PHP `ZipPackage`, ODF DOM/XML parsing, `AstNode`, `MarkdownWriter`, `WordPressBlockWriter`, and in-memory ODT fixtures. Full upstream Pandoc/Haskell runner parity, chart rendering, chart data evaluation/recalculation, LibreOffice/Word validation, zip/unzip tooling, online services, live provider tests, and live-service provider tests remain out of scope.

Non-overlap: this slice avoids accepted ODF chart placeholder, object-ole, MathML object, field, form, section, table-caption, table-template, text:tab, heading-anchor, generated-index, media, manifest, and encrypted-resource clusters. It only adds bounded chart subdocument metadata extraction for existing chart placeholders.

Follow-up: keep richer chart data extraction, chart style/title/axis metadata, table-range dereferencing, chart previews, export-side ODT writing, and full upstream Pandoc ODT reader parity as separate bounded slices.
