# Pandoc ODF OpenDocument Core Current Base

Slice: `pandoc-odf-open-document-core-current-base-20260607T084829Z`

Accepted base: `bed5eb0577e7b3da6f9d9150fbc09175dc986376`

Implemented one bounded native ODF chart metadata handoff:

- `OdfReader` now inspects embedded chart object `content.xml` for `chart:title`, `chart:axis`, and `chart:legend` metadata in addition to the existing chart class, range, category, and series records.
- Chart title text/style/position, axis dimension/name/style/title metadata, and legend position/alignment/style metadata are preserved in `chartMetadata`.
- Markdown and WordPress handoff expose only sanitized reviewer attributes such as `data-odf-chart-title`, `data-odf-chart-axis-count`, and `data-odf-chart-legend-position`; raw chart XML remains hidden.
- The ODT import report now counts chart title, axis, and legend metadata.
- The WordPress ODF handoff example self-test covers the new chart metadata attributes.

Source truth: this is bounded to the OpenDocument package/content XML contract already used by the native ODF reader. ODF chart objects are package subdocuments with `office:chart/chart:chart`; chart titles, axes, and legends are metadata-bearing chart children. This patch does not evaluate chart formulas, render chart graphics, dereference table ranges, expose embedded chart bytes, or invoke external office/converter tooling.

Red-first evidence:

```bash
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
```

Result after adding the title/axis/legend expectations and before implementation: failed as expected in `maps ODT chart title axes and legend into sanitized review metadata`; expected chart title text was `Quarterly revenue`, actual was `NULL`. The red run reached `1 test files, 1416 assertions, 1 failures`.

Focused verification:

```bash
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
```

Result: `1 test files, 1439 assertions, 0 failures`.

```bash
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php lanes/pandoc/tests/OdtReaderTest.php
```

Result: `2 test files, 1534 assertions, 0 failures`.

```bash
php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test
```

Result: `odf open document handoff self-test ok`.

Expected lane movement:

- `phpPass`: `1477 -> 1478`.
- Mapped upstream denominator: `1896 -> 1897`.
- ODF/OpenDocument core cases: `11 -> 12`.
- Mapped ODF/OpenDocument core cases: `11 -> 12`.
- ODF/OpenDocument focused assertions: `251 -> 277`, reflecting the `1413 -> 1439` focused ODF assertion movement.

Dependency closure: no new support component is needed. This reuses native PHP `ZipPackage`, ODF DOM/XML parsing, `AstNode`, `MarkdownWriter`, `WordPressBlockWriter`, in-memory ODT fixtures, and the focused pandoc lane test harness. Full upstream Pandoc/Haskell runner parity, chart data evaluation, chart rendering, table-range dereferencing, LibreOffice/Word validation, zip/unzip tooling, online services, live provider tests, and live-service provider tests remain out of scope.

Non-overlap: this slice avoids accepted ODF chart placeholder/range/series metadata, MathML object, OLE object, field, form, section, table-caption, table-template, text:tab, heading-anchor, generated-index, media, manifest, and encrypted-resource clusters. It only adds bounded chart title/axis/legend metadata extraction for existing chart placeholders.

Follow-up: keep richer chart data-table dereferencing, chart style/plot-area metadata, export-side ODT writing, and full upstream Pandoc ODT reader parity as separate bounded slices.
