# markerPDF DCTDecode LZW-Prefix Boundary

Micro-slice: `markerpdf-dctdecode-filter-boundary-current-base-20260605T161325Z`

Base accepted HEAD: `1a149821ee82ad6ddf33ef8dcac3e0cfe51adb23`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps searchable PDF text extraction separate from raster image rendering: page text is extracted through `marker/pdf/extract_text.py`, while JPEG image payloads are rendered through `marker/pdf/images.py`/PDFium/PIL. Under the current no-GPU/no-external-renderer lane scope, native PHP must keep DCTDecode image bytes review-only and out of WordPress paragraphs.

This slice covers a remaining native stream boundary for image streams filtered as `/LZWDecode` before `/DCTDecode`. A malformed LZW prefix can expose an early LZW EOD before a fake raw `endstream` token and text-looking nested stream bytes. The importer must keep those bytes image-owned, preserve later page text, and report the DCT image as review-only instead of leaking the fake stream into WordPress content.

## Red First

A current-base PHP probe against `/Filter [/LZWDecode /DCTDecode]` before this patch produced:

```text
array (
  0 =>
  array (
    0 => 'Before LZW DCT stream',
    1 => 'LZW DCT early EOD leak',
    2 => 'After LZW DCT stream',
  ),
  1 => 'Before LZW DCT stream
LZW DCT early EOD leak
After LZW DCT stream',
)
```

The same fixture is now committed as `keeps LZWDecode prefix DCTDecode EOD decoys inside image payload boundaries`.

## Implementation

- `PdfTextExtractor::dctPrefixFilterEndstreamTerminatorOffset()` now treats bounded LZW prefix-filter ownership as a fallback only when it is safe for a DCT preview stream.
- LZW fallback is deliberately stricter than a generic "some EOD exists" check: it scans bounded LZW member starts and accepts a terminator only when the member ending at that terminator decodes to a complete JPEG preview payload. This prevents stream-only extraction from swallowing later real PDF streams.
- `PdfImageRenderer` mirrors the same LZW-prefix ownership rule for direct ICCBased/image-stream review helpers.

## Verification

Focused DCT boundary:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 480 assertions, 0 failures
```

Adjacent DCT/image boundary set:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeRunLengthPrefixBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeSegmentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeCommentReferenceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
Focused test run: 7 selected test files (root lock skipped)
7 test files, 1996 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-dctdecode-lzw-prefix-boundary-currentbase.php
```

The smoke emits `stream_filters=["LZWDecode","DCTDecode"]`, `lzw_prefix_eod_decoy_rejected=true`, `dctdecode_image_payload_excluded_from_text=true`, `preview_only_filters=["DCTDecode"]`, paragraphs `["Before LZW DCT Import","After LZW DCT Import"]`, and all Python/model, pypdfium/PIL, and external PDF tool execution flags false.

Syntax and whitespace:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/src/PdfImageRenderer.php
php -l lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-dctdecode-lzw-prefix-boundary-currentbase.php
git diff --check -- lanes/markerpdf
```

Root harness: not run - isolated micro-slice.

## Additional Check

`php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php` was run as a broad adjacent check and still fails in four ToUnicode/CMap assertions on this accepted base (`honors declared ToUnicode bfchar and bfrange row counts`, `inherits ToUnicode usecmap mappings`, `guards cyclic ToUnicode usecmap inheritance`, and `ignores ToUnicode CMap comments`). Those failures are outside this DCTDecode/LZW image-filter boundary and were not changed by this slice.

## Non-Overlap

This does not repeat accepted raw DCTDecode JPEG SOI/EOI stream recovery, Flate/ASCIIHex/ASCII85/RunLength DCT prefix recovery, null-filter DCT DecodeParms alignment, DCT comment-split indirect references, Crypt Identity DCT, unsupported prefix filters, malformed filter operands, DCT CMYK/YCCK ColorTransform planning, inline DCT tokenizer boundaries, CCITT/JPX/JBIG2 image-filter exclusion, or OCR/model work.

The bounded behavior is specifically LZW-prefix stream ownership before preview-only DCTDecode image boundaries.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF dictionary parser, stream-filter stack resolver, LZW decoder/EOD scanner, DCT JPEG preview boundary checks, image review metadata, and WordPress smoke renderer. Full raster JPEG decoding, PDFium/PIL parity, OCR/model execution, and upstream model benchmark parity remain intentionally out of scope under the current markerPDF no-GPU direction.
