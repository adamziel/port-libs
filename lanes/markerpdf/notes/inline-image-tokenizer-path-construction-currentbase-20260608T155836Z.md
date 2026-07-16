# Inline Image Tokenizer Path-Construction Boundary

Slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260608T155836Z`

## Scope

Upstream `sddai/markerPDF` delegates searchable PDF text extraction to parser-backed PDF text/PDFium before image/OCR/model stages. At that boundary, inline image bytes between `BI`, `ID`, and the selected `EI` are raster payload, while ordinary PDF content operators after the real inline-image terminator must remain available for WordPress paragraph import.

This current-base slice makes Bezier path-construction operators countable for the native PHP tokenizer. The covered post-image operators are `c`, `v`, and `y`, each placed between a preview-only inline image fallback boundary and visible text before a later stray `EI` operator.

No production source change was needed: the current native tokenizer already accepts these bounded path-construction operands through `PdfTextExtractor::contentSegmentPathOperatorOperands()`. The patch adds focused test and WordPress smoke coverage so this supported parser behavior is locked to the accepted base.

Manifest delta: `pdfInlineImageTokenizerBoundaryCurrentBaseBehaviors` and `mappedPdfInlineImageTokenizerBoundaryCurrentBaseBehaviors` move from `1` to `2`.

## Verification

Focused baseline before adding the new case:

`php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php`

Result: `1 test files, 759 assertions, 0 failures`.

Focused new test:

`php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerPathConstructionBoundaryCurrentBaseTest.php`

Result: `1 test files, 14 assertions, 0 failures`.

Adjacent tokenizer family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizer*CurrentBaseTest.php`

Result: `4 test files, 805 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-path-construction-currentbase.php`

Result: exits 0 and emits `visible_text_imported=true`, `preview_only_curve_path_text_preserved_after_safe_boundary=true`, `inline_payload_text_excluded=true`, `path_construction_operands_excluded_from_visible_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted malformed `BI` recovery, tight `ID`/`EI`, comment/NUL/vertical-tab separators, compact dictionaries, nested dictionary/text-object decoys, DCT/JPX/JBIG2/CCITT/unsupported-filter payload closure, visible literal/TJ/ActualText fallback, post-terminator comments, same-line text, q/cm/clipping/path-paint/XObject/marked-content/color/pattern/shading/dash/text-state/compatibility/external-close fallback operators, Type3 metrics, image decoding/review metadata, xref repair, annotations, forms, tables, equations, OCR, or model execution.

The bounded behavior is specifically Bezier path-construction operators after a real preview-only inline image terminator and before following visible text with a later stray `EI`.

## Dependency Closure

No new support component is needed. This reuses the native PHP content tokenizer, inline-image fallback scanner, preview-only image filter handling, path-construction operator validation, text extraction, and WordPress smoke harness. Live OCR, Surya/Texify/Torch model execution, PDFium raster parity, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF directive.
