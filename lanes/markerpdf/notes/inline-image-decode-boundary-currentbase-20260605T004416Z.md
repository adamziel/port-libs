# markerPDF Inline Image Decode Sample Floor Boundary

Micro-slice: `markerpdf-inline-image-decode-boundary-current-base-20260605T004416Z`

Base accepted HEAD: `bfc4f1bfe9ba2597b0c718fe0d3ad4e2014b4f3d`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text extraction through parser-backed PDF text extraction before image/OCR/model stages. At that boundary, `BI ... ID ... EI` inline image payload bytes are raster data, not WordPress paragraph text.

The native PHP tokenizer already uses the declared inline image sample size as the safe boundary for unfiltered images. This slice applies the same sample-floor rule after supported filter chains decode successfully: once decoded bytes reach the declared `/W` x `/H` x color-component x `/BPC` sample floor, a real `EI` can close the inline image even if the decoded image payload has extra bytes.

## Implementation

`PdfTextExtractor::inlineImageCandidateMatchesDictionary()` now accepts supported filtered inline-image candidates when the decoded byte count is greater than or equal to the expected image sample floor. Previously it required exact decoded length equality, so a recoverable malformed image with extra decoded bytes never accepted the real `EI` and swallowed the following page text.

The focused fixture uses a Flate inline image with `/W 1 /H 1 /CS /G /BPC 8`. Its decoded image payload begins with one real sample byte and then contains delimiter-looking text bytes. The compressed bytes also contain ` EI ` before the actual terminator. After the fix, the fake delimiter stays inside image data, the real `EI` closes the image, and the following WordPress paragraph is preserved.

## Red First

Current base before the source edit produced:

```text
array (
  0 => 'Before Extra Decoded',
)
```

for the same oversized filtered inline image fixture. The following `After Extra Decoded` page text was swallowed because the decoded payload length was greater than the declared one-byte image sample floor.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS requires ASCII85 inline image end marker before accepting delimiter-looking EI bytes
PASS requires ASCII85 inline image review payload terminator before RGB preview decoding
PASS decodes Flate DecodeParms inline image payload before accepting EI boundaries
PASS accepts filtered inline image EI after decoded sample floor is reached
PASS resolves current indirect inline image decode operands before Indexed RGB preview
PASS resolves current indirect inline ImageMask geometry before stencil preview

1 test files, 65 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxColorKeyOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamJpxCMapRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 12 selected test files (root lock skipped)
12 test files, 1499 assertions, 0 failures
```

Syntax and whitespace:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php

php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
lane-status json ok

git diff --check -- lanes/markerpdf
passed with no output
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php
```

The smoke emitted `fake_ei_inside_oversized_filtered_payload=true`, `accepts_filtered_inline_sample_floor_before_real_ei=true`, `visible_text_imported=true`, `excluded_inline_image_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, with the six expected Gutenberg paragraph blocks.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted malformed `BI` recovery, unfiltered sample-length `EI` validation, ASCII85 explicit terminator review, Flate DecodeParms exact-boundary validation, inline DCT/JPX/JBIG2/CCITT preview-only tokenizer framing, inline ImageMask preview rows, inline Indexed palette/alpha previews, indirect inline preview operand resolution, inline filter-array null alignment, object-stream inline-image repair, or image XObject payload exclusion.

The bounded behavior is specifically supported filtered inline images whose decoded payload reaches the declared sample floor but contains extra decoded image bytes before the real `EI`.

## Dependency Closure

No new support component is needed. This reuses the native content tokenizer, inline image dictionary parser, stream filter decoder, declared sample-size calculator, `PdfTextExtractor`, and WordPress smoke path. Full live OCR/model/raster parity remains intentionally out of scope under the no-GPU markerPDF directive and remains gated on pdftext/PDFium/pypdfium2/PIL, Surya/Torch, tabled-pdf, Texify, runtime app/server workers, benchmark/model downloads, and external OCR/rendering helpers.
