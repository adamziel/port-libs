# markerPDF Inline Image ASCIIHex EOD Boundary

Micro-slice: `markerpdf-inline-image-decode-boundary-current-base-20260605T065757Z`

Base accepted HEAD: `13a03f44f03f1a17e55a3c59df211c0698381848`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through native page/content extraction before OCR/model stages. At that boundary, inline image payload bytes between `BI ... ID` and the real image terminator are raster data, not WordPress-visible text.

For ASCIIHex-filtered inline images, a delimiter-looking `EI` inside the encoded payload is not a valid inline-image terminator before the ASCIIHex end-of-data marker `>` is reached. This slice keeps malformed surplus ASCIIHex bytes closed until the in-band EOD marker after the declared sample floor is satisfied, while keeping malformed surplus payloads out of native RGB preview decoding.

## Implementation

`PdfTextExtractor` now has a bounded ASCIIHex inline-image fallback used only when the normal decoded sample-floor check fails. It applies to a single non-null `ASCIIHexDecode` filter, requires a declared sample floor, requires a `>` EOD marker, and verifies enough hex digits before that marker to meet the declared `/W` x `/H` x component x `/BPC` floor.

This prevents a fake `EI` embedded in malformed surplus ASCIIHex bytes from ending the inline image early and swallowing later page text. `PdfImageRenderer` behavior stays conservative: the same malformed surplus ASCIIHex payload remains rejected for review preview rows instead of being claimed as a native raster decode.

The WordPress inline-image boundary smoke now includes an ASCIIHex payload containing `414243 EI ... >`, verifies the visible before/after paragraphs survive, and confirms the embedded payload text is excluded from imported paragraphs.

## Red First

On the accepted base, a manual current-base fixture with:

```text
BI /W 3 /H 1 /CS /G /BPC 8 /F /AHx ID
414243 EI BT /F1 12 Tf 72 700 Td (ASCIIHex inline image leak) Tj ET >
EI
BT /F1 12 Tf 72 680 Td (After AHx Inline Image) Tj ET
```

returned only `Before AHx Inline Image`, because the tokenizer accepted the fake `EI` before ASCIIHex EOD and consumed the following text as inline-image payload. After the patch, the focused fixture returns only the two real text paragraphs and excludes the ASCIIHex payload text.

## Verification

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php
```

```text
php -l lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
```

```text
php -l lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 218 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageMalformedFilterPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxColorKeyOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfImageColorSpaceMaskInlineOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamJpxCMapRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 14 selected test files (root lock skipped)
14 test files, 1842 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php
```

The smoke exits 0 and emits `fake_ei_inside_asciihex_surplus_payload=true`, `asciihex_surplus_eod_present=true`, `visible_text_imported=true`, `accepts_asciihex_sample_floor_only_after_eod_marker=true`, `asciihex_surplus_preview_decode_rejected=true`, `excluded_inline_image_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted malformed `BI` recovery, unfiltered sample-length `EI` validation, ASCII85 explicit terminator review, Flate DecodeParms delimiter validation, filtered sample-floor tokenizer acceptance, surplus-byte review metadata, terminal whitespace sample handling, named ColorSpace tokenizer fallback, LZW DecodeParms preview rows, RunLength EOD validation, inline DCT/JPX/JBIG2/CCITT preview-only tokenizer framing, inline ImageMask preview rows, inline Indexed palette/alpha previews, indirect inline preview operand resolution, inline filter-array null alignment, object-stream inline-image repair, or image XObject payload exclusion.

The bounded behavior is specifically single-stage ASCIIHex inline-image tokenizer closure across delimiter-looking `EI` bytes until the `>` EOD marker is observed after the declared sample floor.

## Dependency Closure

No new support component is needed. This reuses the native inline-image dictionary expander, stream filter decoder, sample-floor estimator, `PdfTextExtractor`, `PdfImageRenderer`, focused lane tests, and existing WordPress smoke path. Full live OCR/model/raster parity remains intentionally out of scope under the no-GPU markerPDF directive and remains gated on pdftext/PDFium/pypdfium2/PIL, Surya/Torch, tabled-pdf, Texify, runtime app/server workers, benchmark/model downloads, and external OCR/rendering helpers.
