# Inline Image Tokenizer General Graphics-State Boundary

Slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260607T014736Z`

## Scope

Upstream `sddai/markerPDF` delegates searchable PDF text extraction to parser-backed PDF text/PDFium before image/OCR/model stages. At that boundary, inline image bytes between `BI`, `ID`, and the selected `EI` are raster payload, while normal content-stream graphics operators after the real inline-image terminator must not hide following visible text.

This current-base slice makes the standard general graphics-state operator boundary countable for the native PHP tokenizer. The covered post-image operators are line width `w`, line cap `J`, line join `j`, miter limit `M`, flatness `i`, rendering intent `ri`, and external graphics state `gs`.

No production source change was needed: the current native tokenizer already accepts these bounded general graphics-state operands through `PdfTextExtractor::contentSegmentGraphicsStateOperatorOperands()`. The patch adds focused test and WordPress smoke coverage so this supported parser behavior is locked to the accepted base.

## Verification

Focused before adding the new case:

`php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php`

Result: `1 test files, 683 assertions, 0 failures`.

Focused after adding the new case:

`php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php`

Result: `1 test files, 695 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php`

Selected emitted flags: `preview_only_general_graphics_state_stray_ei_text_preserved_after_safe_boundary=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted malformed `BI` recovery, tight `ID`/`EI`, comment/NUL/vertical-tab separators, compact dictionaries, nested dictionary/text-object decoys, DCT/JPX/JBIG2/CCITT/unsupported-filter payload closure, visible literal/TJ/ActualText fallback, post-terminator comments, same-line text, q/cm/clipping/path-paint/XObject/marked-content/color/pattern/shading/dash/text-state/compatibility/external-close fallback operators, Type3 metrics, image decoding/review metadata, xref repair, annotations, forms, tables, equations, OCR, or model execution.

The bounded behavior is specifically general graphics-state operators between a real preview-only inline image terminator and following visible text before a later stray `EI`.

## Dependency Closure

No new support component is needed. This reuses the native PHP content tokenizer, inline-image fallback scanner, preview-only image filter handling, general graphics-state operator validation, text extraction, and WordPress smoke harness. Live OCR, Surya/Texify/Torch model execution, PDFium raster parity, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF directive.
