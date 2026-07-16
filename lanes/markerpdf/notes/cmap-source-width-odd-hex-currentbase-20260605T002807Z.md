# markerPDF CMap source-width odd hex fallback

Session: `port-dev-markerpdf-source-width-20260605T002807Z`
Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260605T002807Z`
Base accepted HEAD: `a3a1253c64da4206cd42417144520cca3b0fe590`

## Source Truth

- Upstream `sddai/markerPDF` at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py` and the `pdftext.extraction.dictionary_output` dependency boundary before Marker creates span, line, block, and page objects.
- PDF hexadecimal strings with an odd number of digits are decoded by appending the missing low nibble as `0`. The native PHP fallback already did this for visible text decoding, but the source-width path used `normalizeHexKey()`, which left-padded odd operands and desynchronized text widths from decoded glyph bytes.

## Behavior Added

`PdfTextExtractor::textOperandSourceHex()` now right-pads odd-length hex string operands before CMap source-key segmentation. CMap dictionary source keys still use the existing normalizer; the change is limited to text-showing operands.

The focused PDF fixture uses a Type0 `/Identity-H` font, one-byte ToUnicode source keys, CIDFont `/W` metrics for `@ABC` and `EFGH`, and an odd text operand `<4142434>`. Visible text decoding treats that as `41 42 43 40`, yielding `ABCD`. Before this patch, the width source path treated it as `04 14 24 34`, under-advanced the first span, and inserted a false WordPress paragraph gap before `EFGH`.

## Evidence

Red-first focused check after adding the test, before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL pads odd-length hex string operands on the right before CMap source-width fallback on current base
Expected: array (0 => 'ABCDEFGH',)
Actual: array (0 => 'ABCD EFGH',)
1 test files, 52 assertions, 1 failures
```

Passing focused gate after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses zero-padded CMap source widths before CID fallback text gaps on current base
PASS uses predefined Identity-H source width when ToUnicode declares one-byte codespace before WordPress gaps
PASS uses CIDFont default width for zero-padded CMap source fallback before WordPress text gaps
PASS falls back to ToUnicode source widths when Identity-H chunks miss explicit CID metrics on current base
PASS inserts TJ adjustment word gap after CMap source-width fallback on current base
PASS preserves TJ source-width adjustment gaps in extracted text runs on current base
PASS pads odd-length hex string operands on the right before CMap source-width fallback on current base
1 test files, 61 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-source-width-fallback-import.php
```

The smoke emits `odd_hex_operand_right_padding_applied=true`, `odd_hex_operand_false_gap_excluded=true`, `odd_hex_operand_span_widths=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- `phpPass`: `1185 -> 1186`
- `wordpressScenarios`: `1168 -> 1169`
- Mapped upstream denominator stays unchanged; this is an additive current-base PHP behavior case inside the already mapped CMap/font source-width fallback cluster.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted zero-padded source-width fallback, predefined `/Identity-H` source widths, CIDFont default `/DW` fallback, metric-miss ToUnicode width fallback, `TJ` line/styled gap handling, `extractTextRuns()` `TJ` parity, Type0 Encoding CMap CID width priority, indirect `/W`/`DW` parsing, CIDSet grouping, Type3 CMap/CIDSet width grouping, quote-operator spacing, vertical `/W2`, or styled-span scaled text-matrix advances. The new boundary is specifically odd-length PDF hex text operands using right-padded source bytes for CMap width grouping before WordPress paragraph import.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, stream decoder, ToUnicode parser, predefined CID CMap metadata, CIDFont width parser, content-token text-positioning path, styled-span extraction path, and WordPress smoke renderer. Full upstream markerPDF runner parity remains intentionally out of scope for this lane because pdftext, pypdfium2/PDFium, Surya/Torch model downloads, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark tooling, and OCR/rendering helpers are not run under the current no-GPU directive.
