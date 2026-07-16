# markerPDF Inline Image Tokenizer Text-Position Boundary Current Base

Micro-slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260608T120710Z`

Base accepted HEAD: `38d890587d9c57bd0e7241c4cbe95d07a2e2d7f5`

## Source Truth

Upstream markerPDF keeps searchable-PDF text extraction on a parser-backed path before image rendering, OCR, or model fallback. At this native no-GPU boundary, `BI ... ID ... EI` bytes are inline raster payload, while valid text-positioning and text-showing operators after the selected inline-image terminator must remain visible to WordPress import.

The focused fixture uses a preview-only JBIG2 inline image with an early delimiter-looking `EI`, payload text plus `rawtail` that remains image-owned, then the real terminator followed by valid text objects using `Td`, `Tm`, `T*`, single-quote showing, and double-quote showing before a later stray `EI`.

## Change

- Added `PdfInlineImageTokenizerTextPositionBoundaryCurrentBaseTest.php` covering text-positioning and quote-showing content after the inline-image tokenizer boundary.
- Added `wordpress-pdf-inline-image-tokenizer-text-position-currentbase.php` as a WordPress smoke for the same visible paragraph import path.
- Updated lane status for one focused PASS case and one WordPress scenario.

No production source change was needed: the current native tokenizer already supports this PDF text-positioning boundary. This patch makes the behavior countable on the accepted base without running Python, OCR, GPU/model code, PDFium/PIL raster rendering, or external PDF tools.

## Verification

`php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerTextPositionBoundaryCurrentBaseTest.php`

Result: `1 test files, 10 assertions, 0 failures`.

`php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-text-position-currentbase.php`

Result: smoke exits 0 and emits `preview_only_text_position_text_preserved_after_safe_boundary=true`, `inline_payload_text_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted malformed `BI` recovery, tight `ID` or tight `EI` sample-floor handling, comments/NUL/vertical-tab separators, DCT/JPX/JBIG2/CCITT preview framing, unsupported filters, visible literal/TJ/ActualText recovery, post-terminator comments, same-line graphics prefixes, graphics-state/color/path/text-state operators outside text objects, marked-content point operators, XObject `Do`, compatibility sections, external scope close operators, Type3 metrics, stream filters, image review metadata, xref repair, forms, annotations, tables, equations, OCR, or model execution.

The bounded behavior here is specifically text-positioning and quote-showing operators inside valid text objects after the selected inline-image terminator and before a later stray `EI`.

## Dependency Closure

No new support component is needed. This reuses the native PHP content tokenizer, inline-image fallback scanner, PDF text-state/text-positioning parser, text extraction path, and WordPress smoke harness. Full live OCR/model/raster parity remains intentionally out of scope under the current markerPDF no-GPU directive.
