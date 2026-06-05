# markerPDF Inline Image Tokenizer Stray EI Operator Boundary

Micro-slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260605T091620Z`

Base accepted HEAD: `4aa0cc3d1c79d46c5770f63de91624ccc6645a18`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF page text through parser-backed extraction before image, OCR, and model stages. At that boundary, `BI ... ID ... EI` inline image bytes are raster payload, but following closed text objects remain visible document text even if malformed content later contains a stray bare `EI` token outside an inline image.

This no-GPU native slice keeps preview-only JBIG2/CCITT/unsupported inline image payloads closed while preventing a later stray `EI` operator from moving the fallback boundary forward and swallowing already resumed WordPress paragraph text.

## Red First

Accepted-base probe before the patch:

```text
BI /W 128 /H 1 /IM true /F /JBIG2Decode ID
<preview-only bytes> EI BT ... (Payload Noise Token) ... rawtail
EI
BT ... (Visible Before Stray Operator) ... ET
EI
BT ... (Visible After Stray Operator) ... ET
```

`PdfTextExtractor` returned only `Before Stray Operator` and `Visible After Stray Operator`; the closed text object before the stray bare `EI` was swallowed into the preview-only inline image fallback.

## Implementation

`PdfTextExtractor::skipInlineImage()` now checks safe preview-only fallback segments with `contentSegmentIsLineSeparatedClosedTextObject()`. The helper is intentionally narrow: it only closes at the previous fallback when the segment after that fallback is line-separated and consists of closed `BT ... ET` text object content with a text-showing operator. Payload-noise segments that contain extra bare bytes still stay closed until the later fallback.

The focused tokenizer test adds `closes preview-only fallback before line-separated text followed by stray EI operator`, and the WordPress smoke now emits `preview_only_stray_ei_operator_text_preserved_after_safe_boundary=true`.

## Verification

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php
```

```text
php -l lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
```

```text
php -l lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 208 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 7 selected test files (root lock skipped)
7 test files, 1223 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
```

The smoke exits 0 and emits `preview_only_stray_ei_operator_text_preserved_after_safe_boundary=true`, `real_inline_image_payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted malformed `BI` preamble recovery, early `EI` sample-floor checks, tight `ID`, comment-bounded `ID`, tight `EI`, compact slash-delimited dictionaries, nested dictionary decoys, JBIG2/raw-JBIG2/CCITT/unsupported-filter payload closure, visible literal/TJ-array/marked-content `EI` recovery, post-terminator comment `EI` exclusion, named ColorSpace fallbacks, ASCIIHex/RunLength/Flate/LZW decode boundaries, DCT/JPX preview framing, object-stream inline-image repair, or inline image review metadata.

The bounded new behavior is specifically a later stray bare `EI` token after a line-separated closed text object following a preview-only inline image fallback.

## Dependency Closure

No new support component is needed. This reuses the native content tokenizer, inline-image dictionary parser, preview-only filter boundary logic, literal/array/name token readers, focused lane tests, and the existing WordPress smoke path. Live OCR/model/raster parity remains intentionally out of scope under the no-GPU markerPDF directive and remains gated on pdftext/PDFium/pypdfium2/PIL, Surya/Torch, tabled-pdf, Texify, runtime app/server workers, benchmark/model downloads, and external OCR/rendering helpers.
