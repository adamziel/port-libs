# Inline Image Decode Comment EOD Current Base

Micro-slice: `markerpdf-inline-image-decode-boundary-current-base-20260605T213832Z`

Accepted base: `657d8d9880c9b7e72e8e4cabf7a3db63b8a0a3fd`

## Source Truth

Upstream markerPDF keeps searchable PDF text extraction separate from image rendering and OCR/model fallbacks. Under the current no-GPU scope, native inline image payloads stay out of visible WordPress text, while the PDF parser still needs to honor PDF lexical comments around operators and stream boundaries.

For native filter inline images, an ASCIIHex EOD marker (`>`) can be followed by PDF whitespace before the real `EI` operator. This slice treats newline-terminated `%...` comments after that EOD marker as whitespace, but does not allow an unterminated comment candidate to close the image at a fake `EI` inside the comment.

## Behavior

`PdfTextExtractor` now keeps fake `EI BT ... Tj ET` text inside a PDF comment after an ASCIIHex inline-image EOD marker image-owned until the real newline-terminated `EI` boundary.

`PdfImageRenderer` now accepts the same newline-terminated comment tail as lexical whitespace when verifying explicit native filter EOD markers before preview decoding. The decoded image samples and `/Decode [1 0]` inversion remain available as review metadata.

## Evidence

Current-base probes before the final fix:

```text
php -r 'require "tools/bootstrap.php"; $r=new PortLibs\MarkerPDF\PdfImageRenderer(); try { $r->inlineImageColorSpaceMaskOutputPreviewRows("/W 3 /H 1 /CS /G /BPC 8 /F /AHx /D [0 1]", "414243>% comment EI BT fake\n", [], 3); } catch (Throwable $e) { echo get_class($e), ": ", $e->getMessage(), "\n"; }'
InvalidArgumentException: Inline image prefix filters must be complete before output preview.
```

```text
php -r 'require "tools/bootstrap.php"; ... extractTextLines($pdf)'
array (
  0 =>
  array (
    0 => 'Before Comment EOD',
    1 => 'Inline Comment EOD Noise',
    2 => 'After Comment EOD',
  ),
  1 => 'Before Comment EOD
Inline Comment EOD Noise
After Comment EOD',
)
```

Focused run after the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS treats PDF comments after inline native filter EOD as whitespace before real EI boundaries
1 test files, 542 assertions, 0 failures
```

Adjacent inline-image/image-renderer gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php
4 test files, 1437 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php > /tmp/markerpdf-inline-image-decode-boundary-currentbase.html
```

Metadata check result:

```text
inline image comment EOD smoke metadata ok
```

The smoke metadata reports `asciihex_comment_eod_payload_excluded_until_real_ei=true`, `asciihex_comment_eod_preview_decoded=true`, `excluded_inline_image_text=true`, `visible_text_imported=true`, and all Python/model/external-tool execution flags false.

Syntax and whitespace checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/src/PdfImageRenderer.php
php -l lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php
```

All reported `No syntax errors detected`.

```text
git diff --check -- lanes/markerpdf
```

Passed with no output.

## Non-Overlap

This does not repeat accepted inline image dictionary abbreviation expansion, ASCII85/ASCIIHex/LZW/RunLength EOD handling, NUL whitespace after EOD, post-EOD non-comment surplus rejection, Flate/JPX native-prefix completion, malformed Decode/DecodeParms fail-closed handling, unsupported/Crypt filter review, inline ImageMask, Indexed palette preview, DCT/JBIG2/CCITT preview-only filters, image XObject metadata, CMaps, xref repair, OCR/model execution, PDFium, or external raster tooling.

The bounded behavior is specifically newline-terminated PDF comments after native inline image filter EOD markers.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP content-stream tokenizer, stream-filter boundary helpers, inline image preview decoder, and WordPress smoke path. Live OCR, PDFium/PIL raster execution, Surya/Texify/Torch models, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope.
