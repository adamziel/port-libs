# markerPDF CMap short bfrange array source-width fallback

Session: `port-dev-markerpdf-source-width-20260606T101653Z`

Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260606T101653Z`

Base accepted HEAD: `e1661ddde6bf69323245293250d294a721f7503c`

## Source Truth

Pinned upstream markerPDF routes searchable-PDF text extraction through PDF parser font/CMap decoding before Marker assembles page text and Markdown. In this no-GPU PHP lane, native searchable-PDF import owns ToUnicode CMap parsing, CIDFont width grouping, text runs, styled spans, and WordPress paragraph output.

The bounded behavior here is malformed ToUnicode `beginbfrange` rows whose destination array is shorter than the mapped source-code range. Those rows must fail closed before mutating exact `beginbfchar` mappings, otherwise a bad later range can erase valid source-code text that the source-width fallback needs for positioned text grouping.

## Implementation

`PdfTextExtractor::parseToUnicodeRanges()` now counts the source codes covered by a `beginbfrange` array row, honoring same-width CMap code-space ranges when present. If the destination array does not provide enough targets for the covered sources, the row is ignored before direct ToUnicode mappings or lazy Unicode ranges are changed.

This preserves existing source-order behavior for valid ranges while keeping malformed short arrays from replacing exact `bfchar` rows.

## Evidence

Red-first inline probe before source edit:

```text
short ToUnicode bfrange array over <20>..<27> with only four targets decoded as `XYZW$%&'`
```

Focused after source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapShortBfrangeArraySourceWidthCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS ignores short ToUnicode bfrange arrays before CMap source-width fallback on current base

1 test files, 12 assertions, 0 failures
```

Adjacent CMap source-width regression gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapShortBfrangeArraySourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthOrderCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapLargeCidRangeSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapLargeToUnicodeBfrangeSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapMultiRangeSparseSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapNotdefCharSourceWidthCurrentBaseTest.php
Focused test run: 7 selected test files (root lock skipped)
7 test files, 460 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-short-bfrange-array-source-width-currentbase.php
```

The smoke emits `exact_bfchar_rows_preserved=true`, `source_width_runs_preserved=true`, `cid_widths_applied=true`, `short_bfrange_array_excluded=true`, `raw_source_glyphs_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, then renders the Gutenberg paragraph `ABCDEFGH`.

## Status Delta

- Added 1 focused PHP PASS case.
- Added 1 WordPress smoke scenario.
- Current adjacent CMap source-width family is green at 460 assertions.
- Root harness not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted zero-padded source-width fallback, predefined Identity-H/UCS2-H widths, default `/DW` fallback, metric-miss chunk fallback, `TJ` adjustment gaps, odd hex padding, repeated zero-padding, explicit longer ToUnicode source keys, mixed-width ToUnicode range rejection, `usecmap` inheritance, late ToUnicode source-order precedence for valid ranges, malformed broad codespace fallback, notdef rows, code-space sequence ordering, sparse multi-range source-width ranking, bytewise codespace membership, large CID ranges, large ToUnicode bfranges, or simple-font `/Widths` boundary work.

The new boundary is specifically an undersized ToUnicode `beginbfrange` destination array that would otherwise erase exact `beginbfchar` rows before CMap source-width grouping.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream decoder, CMap parser, CIDFont width parser, text-position grouping, styled-span extraction, and WordPress smoke renderer. OCR, Surya/Texify/Torch/model execution, PDFium/pdftext parity runs, Streamlit/FastAPI model workers, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.
