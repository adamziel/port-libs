# markerPDF Vertical W2 Source-Width Fallback Current Base

Session: `port-dev-markerpdf-source-width-20260605T185158Z`

Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260605T185158Z`

Base accepted HEAD: `0743792c5d680e9fed5e8a0846fa60f1ef7412bd`

## Source Truth

Pinned upstream markerPDF routes searchable PDF text through the pdftext/PDF parser boundary before Marker converts page dictionaries into spans, lines, blocks, and Markdown. Under the current no-GPU directive, this native PHP lane maps the in-scope Type0 CMap, text-showing, and CIDFont width behavior needed before WordPress import without running pdftext, pypdfium/PDFium, Python model workers, OCR, or external PDF tools.

This slice stays inside the CMap source-width fallback cluster. In vertical writing mode, descendant CIDFont `/W2` and `/DW2` displacements are the glyph-advance evidence used for source-code segmentation. If a recoverable descendant dictionary omits `/Subtype` but still carries usable `/W2` data, the native fallback should not count leading zero padding bytes as independent vertical glyphs.

## Implementation

`PdfTextExtractor::fontWidthMapContainsCid()` and source-key width evidence helpers now treat vertical `/W2` and `/DW2` displacement data as valid source-width evidence only when the active CMap writing mode is vertical.

Visible ToUnicode decoding is unchanged. The repair is limited to source-key segmentation for glyph advance, styled-span vertical bboxes, and positioned text gap grouping.

## Focused Fixture

`PdfCMapSourceWidthFallbackCurrentBaseTest.php` adds a Type0 vertical fixture with:

- `/Encoding /MissingCustom-V`;
- a ToUnicode CMap with one-byte source rows `<01>` through `<06>`;
- zero-padded source operands `<0001000200030004>` and `<00050006>`;
- a descendant dictionary with `/DW2` and `/W2` but no `/Subtype`; and
- a second `Tm` position where padding bytes previously created a false WordPress word gap.

Red-first inline probe before the source fix:

```text
extractTextLines => ['Vert XY']
styled bboxes => [[0,0,12,72], [12,0,24,30]]
```

Passing focused gate after the source and test fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 311 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-vertical-w2-source-width-currentbase.php
```

The smoke emits `VertXY`, `vertical_w2_spans_applied=true`, `padding_bytes_not_counted_as_vertical_glyphs=true`, `false_word_gap_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `2156 -> 2157`
- `wordpressScenarios`: `1857 -> 1858`
- Mapped upstream denominator stays unchanged; this is a current-base PHP behavior case inside the already mapped CMap/font source-width fallback cluster.

## Non-Overlap

This does not repeat accepted horizontal zero-padded source-width fallback, CIDFont `/DW` fallback, repeated zero-padding collapse, Type0 Encoding CMap CID range remapping, high CID range source rows, sparse codespace sequence source rows, ToUnicode bfrange source rows, valid vertical `/W2` geometry, predefined vertical CMap writing-mode detection, usecmap vertical-width inheritance, malformed CMap filter boundaries, or catalog/PageLabels work.

The bounded behavior is specifically vertical source-width evidence from `/W2` and `/DW2` when horizontal CID width evidence is absent.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream decoder, ToUnicode parser, font/CMap writing-mode detection, CIDFont vertical displacement parser, content-token parser, text-run/line/styled-span extraction, and WordPress smoke renderer. Full OCR/model/PDFium benchmark parity remains intentionally out of scope under the no-GPU markerPDF directive.
