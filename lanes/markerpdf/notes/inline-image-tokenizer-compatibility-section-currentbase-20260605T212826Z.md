# markerPDF inline image tokenizer compatibility-section boundary

Micro-slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260605T212826Z`

Accepted base: `eed9ae01382402613f561a87d1911e817e01a902`

## Source Truth

Upstream markerPDF delegates searchable-PDF text extraction to PDF text/PDFium-style content parsing before image rendering. At that boundary, inline image `BI ... ID ... EI` bytes are raster payload and must not leak into visible WordPress paragraphs. PDF content streams also allow `BX`/`EX` compatibility sections around content so older processors can ignore future operators while still preserving recognized visible text operators.

## Behavior

The native tokenizer already kept preview-only inline image data closed across delimiter-looking payload bytes and recovered before common text, graphics-state, path, color, XObject, and dash-pattern wrappers. This slice adds the adjacent compatibility-section boundary:

- A JBIG2 preview-only inline image payload contains fake `EI BT ... Tj ET` bytes.
- The real inline-image terminator is followed by a balanced `BX ... EX` section.
- The section contains an unknown future operator plus a normal `BT ... Tj ... ET` visible text object.
- A later stray bare `EI` appears after `EX`.
- The same boundary also works when `BX` follows the image terminator on the same content-stream line, matching the already accepted same-line direct `BT` fallback behavior.

`PdfTextExtractor::contentSegmentIsLineSeparatedClosedTextObject()` now tracks balanced compatibility sections, allows `BX` as the first same-line operator after a safe inline-image fallback candidate, and ignores unknown compatibility-section operands/operators while still requiring a closed visible text object and balanced graphics/marked-content/compatibility depth before choosing the earlier safe inline-image boundary.

## Evidence

Red probe before the source change:

```text
extractTextLines($pdf) => [
  'Before Compatibility Stray',
  'Visible After Compatibility Stray',
]
```

The line `Visible Compatibility Before Stray` was swallowed because the fallback boundary waited for the later stray `EI`.

Focused tokenizer test after the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
...
PASS closes preview-only fallback before compatibility-section text followed by stray EI operator

1 test files, 387 assertions, 0 failures
```

Adjacent inline-image family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageMalformedFilterPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxColorKeyOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsInlineImageBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php
Focused test run: 10 selected test files (root lock skipped)
...
10 test files, 1133 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php > /tmp/markerpdf-inline-image-tokenizer-boundary-smoke.html
entity-decoded smoke metadata/text checks:
preview_only_compatibility_section_stray_ei_text_preserved_after_safe_boundary=true
preview_only_same_line_compatibility_section_stray_ei_text_preserved_after_safe_boundary=true
Visible Compatibility Before Stray=yes
Compatibility Payload Noise=no
FutureOp=no
```

Syntax/diff checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
git diff --check -- lanes/markerpdf
```

## Non-Overlap

This does not repeat accepted JBIG2 raw payload, unsupported-filter, ASCII85/ASCIIHex/Flate/LZW/RunLength surplus, DCT/JPX/CCITT preview-only framing, slash-delimited marked-content, graphics-state/path/color/XObject/dash stray-`EI`, inline image renderer preview, Image XObject, CMap, xref repair, PageLabels, or OCR/model behavior. The bounded new behavior is specifically tokenizer fallback selection when visible text after a preview-only inline image is wrapped in a PDF `BX`/`EX` compatibility section, including same-line `BX`, with unknown future operators before a later stray `EI`.

## Dependency Closure

No new support component is needed. This reuses the native content tokenizer, inline-image dictionary parser, preview-only image filter boundary logic, `PdfTextExtractor`, focused lane tests, and the existing WordPress smoke path. Full live OCR/model/raster parity remains intentionally out of scope under the no-GPU markerPDF directive and remains gated on pdftext/PDFium/pypdfium2/PIL, Surya/Torch, tabled-pdf, Texify, runtime app/server workers, benchmark/model downloads, and external OCR/rendering helpers.
