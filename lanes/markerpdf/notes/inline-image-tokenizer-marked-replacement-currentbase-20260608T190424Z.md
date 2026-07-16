# Inline Image Tokenizer Marked Replacement Boundary

Slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260608T190424Z`

## Scope

Upstream `sddai/markerPDF` relies on parser-backed searchable-PDF extraction before image/OCR/model stages. At the native PHP boundary, preview-only inline image bytes between `BI`, `ID`, and the selected `EI` must remain raster payload, while direct marked-content replacement text after the real `EI` must stay visible even when a later stray `EI` appears in normal content.

This slice fixes `PdfTextExtractor::contentSegmentIsLineSeparatedClosedTextObject()` so the preview-only fallback scanner recognizes direct replacement-only marked content as a safe post-image boundary. The validator now mirrors the extractor's marked-content emission rule for direct `/ActualText` and `/Alt` dictionaries: if the marked-content scope closes with no text object and no image XObject invocation, the replacement is visible content and can close the inline-image fallback before a later stray `EI`.

## Red Probe

Before the source edit, a focused current-base probe with `BI /W 128 /H 1 /IM true /F /JBIG2Decode ID ... EI /Span << /ActualText (...) >> BDC EMC EI BT ... ET` printed:

`Before Replacement Stray`

`Visible After Replacement Stray`

The visible direct `/ActualText` replacement was swallowed with the preview-only inline image payload. After the source edit, the same probe prints:

`Before Replacement Stray`

`Visible Empty Replacement Before Stray`

`Visible After Replacement Stray`

## Verification

Focused regression:

`php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerMarkedReplacementBoundaryCurrentBaseTest.php`

Result: `1 test files, 24 assertions, 0 failures`.

Adjacent boundary suite:

`php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php`

Result: `1 test files, 759 assertions, 0 failures`.

Combined focused command:

`php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerMarkedReplacementBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php`

Result: `2 test files, 783 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-marked-replacement-currentbase.php`

Selected emitted flags: `actual_text_replacement_preserved=true`, `alt_replacement_preserved=true`, `payload_text_suppressed=true`, `stray_ei_after_replacement_ignored=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted malformed `BI` recovery, tight `ID`/`EI`, comment/NUL/vertical-tab separators, compact dictionaries, nested dictionary/text-object decoys, DCT/JPX/JBIG2/CCITT/unsupported-filter payload closure, visible literal/TJ/quote/ActualText-with-text-object fallback, post-terminator comments, same-line text, q/cm/clipping/path-paint/XObject/marked-content/color/pattern/shading/dash/general-graphics/text-state/compatibility/external-close fallback operators, Type3 metrics, image decoding/review metadata, xref repair, annotations, forms, tables, equations, OCR, or model execution.

The bounded behavior is specifically direct replacement-only marked content immediately after a preview-only inline-image terminator and before a later stray `EI`.

## Dependency Closure

No new support component is needed. This reuses the native PHP content tokenizer, inline-image fallback scanner, marked-content replacement parsing, preview-only image filter handling, text extraction, and WordPress smoke harness. Live OCR, Surya/Texify/Torch model execution, PDFium raster parity, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF directive.
