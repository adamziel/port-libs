# Inline Image Tokenizer Scoped Continuation Current Base

Slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260606T205755Z`

Base accepted HEAD: `707b60a141f4e8a970f90fe5df3b1c2d5991fbaa`

## Source Truth

Upstream `sddai/markerPDF` at manifest-pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text extraction through parser-backed pdftext/PDFium before image/OCR/model stages. At that boundary, `BI ... ID ... EI` inline-image bytes are raster payload, while valid content-stream text after the real inline-image terminator remains visible document text for WordPress import.

This native no-GPU slice stays inside the PDF content tokenizer. It does not run OCR, Surya, Texify, Torch, pypdfium, PIL, model workers, or external PDF tools.

## Behavior

Preview-only inline images can contain delimiter-looking `EI` bytes. The existing fallback already handled valid scopes that close immediately after a later stray `EI`; this patch adds the adjacent case where a valid `q`, `BMC`, or `BX` scope remains open across that stray `EI`, emits more text, and closes afterward.

Before the fix, the tokenizer promoted the later stray `EI` to the inline-image terminator and swallowed the visible text before it. After the fix, `PdfTextExtractor` validates the continued scoped segment after the stray `EI`, closes the inline image at the earlier safe boundary, preserves visible text on both sides of the stray operator, and still excludes payload bytes and compatibility operands.

## Red First

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL closes preview-only fallback before scoped text that continues after a stray EI operator
Expected: Before Continued Graphics Stray, Visible Continued Graphics Before Stray, Visible Continued Graphics After Stray, Visible Continued Graphics After Close
Actual: Before Continued Graphics Stray, Visible Continued Graphics After Stray, Visible Continued Graphics After Close
1 test files, 568 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS closes preview-only fallback before scoped text that continues after a stray EI operator
1 test files, 606 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageMalformedFilterPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxColorKeyOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamJpxCMapRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php
Focused test run: 12 selected test files (root lock skipped)
12 test files, 2199 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
```

The smoke exits 0 and reports the three new continuation booleans as true:
`preview_only_continued_graphics_scope_text_before_and_after_stray_ei_preserved`,
`preview_only_continued_marked_content_scope_text_before_and_after_stray_ei_preserved`,
and `preview_only_continued_compatibility_scope_text_before_and_after_stray_ei_preserved`.

## Non-Overlap

This does not repeat accepted malformed `BI` recovery, tight `ID` boundaries, immediate comments after `ID`, NUL whitespace, tight `EI` sample floors, nested dictionary/text-object decoys, JBIG2/CCITT/unsupported-filter payload closure, slash-delimited `EI`, ActualText/TJ array fallback, post-terminator comments, same-line graphics prefixes, color/pattern/shading/dash/text-state operators, XObject Do, marked-content point operators, externally closed Q/EMC/EX scopes, or the prior case where the scope close operator immediately follows the stray `EI`.

The bounded new behavior is only open `q`, `BMC`, and `BX` content scopes that continue with visible text after a stray `EI` before closing.

## Dependency Closure

No new support component is needed. This reuses the native PHP content tokenizer, inline-image dictionary parser, preview-only image fallback scanner, scope validator, and existing WordPress smoke harness. Full raster/OCR/model parity remains gated on pdftext/PDFium/pypdfium2/PIL, Surya/Torch, tabled-pdf, Texify, runtime app/server workers, benchmark/model downloads, and external OCR/rendering helpers, all intentionally out of scope for this no-GPU markerPDF slice.
