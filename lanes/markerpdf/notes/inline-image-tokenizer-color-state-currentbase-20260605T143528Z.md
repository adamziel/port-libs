# Inline Image Tokenizer Color-State Boundary Current Base

## Source Truth

Upstream markerPDF delegates searchable PDF text extraction to parser-backed pdftext/PDFium before image/OCR/model stages. At that boundary, `BI ... ID ... EI` inline image bytes are raster payload, while valid content operators after the real `EI` terminator remain visible document text for WordPress import.

## Behavior

Preview-only inline images such as JBIG2 may contain delimiter-looking `EI` bytes before the native tokenizer can prove a complete raster payload. The existing fallback waits for a later safe `EI` and then checks whether the following segment is real content before closing the image at the earlier boundary.

This slice extends that content-segment validator to accept bounded graphics-state/color operators before the next visible text object:

- `0 0 1 rg` before `BT ... Tj ET`
- `/DeviceRGB cs` plus `0.2 0.3 0.4 scn` before `BT ... Tj ET`

Before the fix, the red-first probe preserved only the text before the inline image and the text after a later stray `EI`, swallowing the visible color-state paragraph:

```text
Before Color State Stray
Visible After Color Stray
```

After the fix, the tokenizer closes at the real inline-image terminator and preserves both color-state paragraphs while keeping inline image payload noise review-only.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS closes preview-only fallback before color-state text followed by stray EI operator
1 test files, 299 assertions, 0 failures
```

Adjacent inline-image gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
2 test files, 695 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
```

The smoke emits `preview_only_color_state_stray_ei_text_preserved_after_safe_boundary=true`, visible RGB/SCN paragraphs, and no Python/model/external PDF tool execution.

## Non-Overlap

This does not repeat accepted malformed `BI` recovery, tight `ID` boundaries, immediate comments after `ID`, NUL whitespace, tight `EI` sample floors, nested dictionary/text-object decoys, JBIG2/CCITT/unsupported-filter payload closure, slash-delimited `EI`, ActualText/TJ array fallback, post-terminator comments, same-line or q/cm/clipping-path stray `EI` recovery, decoded surplus sample floors, stream filters, image preview metadata, or OCR/model behavior. The new boundary is specifically color graphics-state operators between a real preview-only inline image terminator and following visible text before a later stray `EI`.

## Dependency Closure

No new support component is needed. This reuses the native PHP content tokenizer, inline image dictionary parser, preview-only image fallback scanner, graphics/text content segment validator, and WordPress smoke renderer. Live OCR, Surya/Torch/Texify, PDFium raster parity, and model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF directive.
