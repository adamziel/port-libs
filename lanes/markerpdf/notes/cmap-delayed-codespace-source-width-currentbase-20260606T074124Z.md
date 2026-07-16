# markerPDF delayed code-space CID range source-width current base

Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260606T074124Z`

Session: `port-dev-markerpdf-source-width-20260606T074124Z`

Base accepted HEAD: `e9ae20f5b9827255fc6c5ece376150c0bc8003d6`

## Source Truth

Pinned upstream markerPDF (`sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`) routes searchable PDF text extraction through `pdftext.extraction.dictionary_output(...)` and pypdfium/PDFium page text before Markdown conversion. Under the no-GPU markerPDF lane scope, this native PHP slice owns the parser/font boundary that decides text runs, span bboxes, and WordPress paragraph gaps without launching PDFium, OCR, or model workers.

Relevant PDF behavior: Type0 `/Encoding` CMaps map content-stream source codes to descendant CIDs before CIDFont `/W` and word-spacing decisions. When a malformed or broad `begincidrange` starts before the first valid `begincodespacerange` key, the first valid source key in that code-space window still needs a stable CID offset for width lookup; otherwise the native fallback falls back to raw source-code/default widths.

## Implementation

`PdfTextExtractor::singleCodeSpaceSequenceOffsetInCidRange()` now ranks a single code-space range from the later of the CID range start and the code-space range start. This keeps delayed valid code-space windows from falling through to the bounded scanner or default width path.

`PdfTextExtractor::fontMapFromFontBody()` now marks range-only CID CMaps as eligible for source-space word spacing. That lets `sourceKeyUsesWordSpacing()` resolve CID 32 through `cidRanges`, not just through eagerly expanded `cidMap` rows.

The focused fixture maps `<200000>` and `<200001>` to visible `AB`, while the font Encoding CMap declares a code-space window only for those keys and a broader CID range from `<000000>` to `<200001>` with base CID 32. The expected styled span width is 39 points: CID 32 width 1000, CID 33 width 250, plus `24 Tw` word spacing for the CID-32 source key.

## Evidence

Red-first focused run after adding the test and before the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php`

Result: `1 test files, 365 assertions, 1 failures`. The new case decoded `AB` but measured the span bbox as `[0.0, 0.0, 6.0, 12.0]` instead of `[0.0, 0.0, 39.0, 12.0]`.

After the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php`

Result: `1 test files, 368 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-cmap-delayed-codespace-source-width-currentbase.php`

Expected flags: `delayed_codespace_cid_range_resolved=true`, `source_space_word_spacing_applied=true`, `text_runs_preserved=true`, `visible_text_clean=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted zero-padded source-width fallback, Identity-H/UCS2 source widths, default-width fallback, partial metric misses, TJ adjustment gaps, vertical W2 fallback, odd hex padding, UseCMap inheritance/order, explicit low CID rows, high CID range expansion, notdef rows, sparse code-space ranges whose CID range starts at the first valid source key, invalid later CID range rejection, or bytewise code-space boundary checks.

The bounded behavior is specifically a delayed valid code-space window inside a broader CID range, plus source-space word spacing from range-only CID maps.

## Dependency Closure

No new support component is needed. This reuses the native PHP object scanner, CMap parser, CIDFont width metrics, text operand source-key segmentation, styled-span geometry, WordPress smoke rendering, and PHP test runner. Live `pdftext`, pypdfium/PDFium, Surya/Torch OCR/layout/table models, Texify, Streamlit/FastAPI workers, raster rendering, and external PDF tools remain intentionally out of scope and were not executed.
