# markerPDF Inline Image Tokenizer XObject Do Boundary Current Base

Micro-slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260605T155112Z`

Base accepted HEAD: `f071fefb2a76a8e9eb3969229618987f332d5aff`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text extraction through pdftext/PDFium before image/OCR/model stages. At that boundary, inline image bytes between `BI ... ID ... EI` are raster payload, while `/Name Do` XObject invocations after the inline image terminator are non-text drawing operators that must not hide following visible text.

## Implementation

`PdfTextExtractor::contentSegmentIsLineSeparatedClosedTextObject()` now treats a single-name `Do` operator as a valid non-text graphics operation while deciding whether a preview-only inline image fallback can close before later stray `EI` bytes.

The new fixture uses a JBIG2 preview-only inline image whose payload contains delimiter-looking text bytes before the real terminator. After that real terminator, it invokes `/Decorative Do`, emits visible text, includes a later stray `EI`, and then emits more visible text. The tokenizer now closes at the real inline image boundary, excludes image payload and XObject resource names from WordPress paragraphs, and preserves both visible text lines.

## Red First

Before the source edit, a current-base probe for the new fixture returned only:

```text
array (
  0 => 'Before XObject Do Stray',
  1 => 'Visible After XObject Do Stray',
)
```

The text line between `/Decorative Do` and the later stray `EI` was swallowed by the preview-only inline image fallback.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS closes preview-only fallback before XObject Do text followed by stray EI operator

1 test files, 310 assertions, 0 failures
```

Scoped inline-image family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxColorKeyOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamJpxCMapRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php
Focused test run: 11 selected test files (root lock skipped)
11 test files, 1481 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
```

The smoke emitted `preview_only_xobject_do_stray_ei_text_preserved_after_safe_boundary=true`, `visible_text_imported=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted malformed `BI` recovery, tight `ID` boundaries, immediate comments after `ID`, NUL whitespace, tight `EI` sample floors, nested dictionary/text-object decoys, JBIG2/CCITT/unsupported-filter payload closure, slash-delimited `EI`, ActualText/TJ array fallback, post-terminator comments, same-line or q/cm/clipping-path/color-state stray `EI` recovery, decoded surplus sample floors, stream filters, image preview metadata, or OCR/model behavior.

The bounded behavior is specifically `/XObject Do` graphics operations between a real preview-only inline image terminator and following visible text before a later stray `EI`.

## Dependency Closure

No new support component is needed. This reuses the native content tokenizer, inline-image dictionary parser, preview-only filter fallback, `PdfTextExtractor`, XObject resource review boundary, and WordPress smoke path. Full live OCR/model/raster parity remains intentionally out of scope under the no-GPU markerPDF directive and remains gated on pdftext/PDFium/pypdfium2/PIL, Surya/Torch, tabled-pdf, Texify, runtime app/server workers, benchmark/model downloads, and external OCR/rendering helpers.
