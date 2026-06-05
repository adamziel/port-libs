# markerPDF Inline Image Stacked Native Filter Surplus Boundary

Micro-slice: `markerpdf-inline-image-decode-boundary-current-base-20260605T114650Z`
Base accepted HEAD: `b0b72874e66840fd6a7239e395a47d03eb6b09cc`

## Source Truth

Upstream `sddai/markerPDF` routes searchable PDF text extraction through parser/PDF text extraction before image, OCR, and model stages. At that boundary, inline image bytes from `BI ... ID ... EI` are raster payload and must not become WordPress paragraph text.

This no-GPU native slice covers stacked native inline image filters where the first filter has an explicit EOD marker and the bytes after that marker contain a fake `EI` before the real inline-image terminator. For example, `/F [/AHx /Fl]` may contain ASCIIHex EOD `>` followed by malformed surplus text-like bytes; the tokenizer must keep the fake `EI` closed, but still preserve page text after the real terminator.

## Red First

Before the source edit, an ad-hoc current-base probe for:

```text
BI /W 1 /H 1 /CS /G /BPC 8 /F [/AHx /Fl] ID <hex flate bytes>>ZZ EI BT ... (Stacked Native Surplus Noise) ... rawtail
EI
BT ... (After Stacked Native Surplus) ...
```

returned only:

```text
array (
  0 => 'Before Stacked Native Surplus',
)
```

The following `After Stacked Native Surplus` paragraph was swallowed because the inline-image candidate could not prove the stacked native filter payload reached the declared sample floor after the first filter EOD surplus.

## Implementation

`PdfTextExtractor` now exposes a shared `streamFilterInputEndByteOffset()` helper and uses it from the existing strict bounded-end-marker check. A new inline-image tokenizer helper decodes only the bytes before the first native filter EOD marker through the full native filter stack. It accepts the later real `EI` only when:

- the inline image declares a positive decoded sample floor;
- the filter stack contains at least two concrete native filters;
- the first filter has a bounded end byte offset;
- surplus after that first EOD contains a delimiter-looking fake `EI`;
- decoding the bounded prefix through the full native stack reaches the sample floor.

The WordPress smoke now includes the stacked `/AHx -> /Fl` malformed surplus fixture and records `stacked_native_filter_surplus_payload_excluded_until_real_ei`, `stacked_native_filter_surplus_preview_rejected`, and `stacked_native_filter_clean_preview_decoded`.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 347 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageMalformedFilterPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxColorKeyOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfImageColorSpaceMaskInlineOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamJpxCMapRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 14 selected test files (root lock skipped)
14 test files, 2045 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php
```

The smoke exits 0 and emits `stacked_native_filter_surplus_payload_excluded_until_real_ei=true`, `stacked_native_filter_surplus_preview_rejected=true`, `stacked_native_filter_clean_preview_decoded=true`, `visible_text_imported=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php
```

All syntax checks reported no errors.

```text
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
lane-status json ok

git diff --check -- lanes/markerpdf
```

`git diff --check` passed with no output.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted malformed `BI` recovery, tight `ID`, tight `EI`, single-filter ASCIIHex/ASCII85/RunLength/LZW EOD handling, single Flate post-stream surplus, filtered decoded sample-floor acceptance, inline DCT/JPX/JBIG2/CCITT preview-only tokenizer framing, inline ImageMask/Indexed/ColorKey preview metadata, unsupported `/Crypt` tokenizer boundaries, native prefix metadata before JPX preview, object-stream inline-image repair, CMap stream filter EOD review, or image XObject payload exclusion.

The bounded behavior is specifically stacked native inline image filter surplus after the first filter EOD marker before the real `EI`.

## Dependency Closure

No new support component is needed. This reuses the native PDF content tokenizer, inline image dictionary parser, stream filter decoders, declared sample-size calculator, `PdfTextExtractor`, `PdfImageRenderer` smoke preview path, and WordPress smoke. Full live OCR/model/raster parity remains intentionally out of scope under the no-GPU markerPDF directive and remains gated on pdftext/PDFium/pypdfium2/PIL, Surya/Torch, tabled-pdf, Texify, runtime app/server workers, benchmark/model downloads, and external OCR/rendering helpers.
