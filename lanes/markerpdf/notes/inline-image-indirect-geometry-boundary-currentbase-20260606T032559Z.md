# Inline Image Indirect Geometry Boundary - 2026-06-06

Slice: `markerpdf-inline-image-decode-boundary-current-base-20260606T032559Z`
Base: `48c802ea8046e77fb772cdde5b23074ce89ff045`

## Source Truth

- PDF inline images are content-stream `BI ... ID ... EI` payloads. The native parser must keep image sample bytes out of searchable text and WordPress paragraphs.
- This no-GPU markerPDF lane ports native parser/converter behavior only. OCR, Surya, Texify, Torch, pypdfium raster parity, and model-worker execution remain out of scope.
- The local upstream-compatible boundary is that image extraction/review metadata can exist, but inline image payload bytes must not be promoted as text.

## Red-First Evidence

Before the source edit, an unfiltered inline image dictionary using indirect-looking geometry operands:

```text
BI /W 101 0 R /H 102 0 R /CS /G /BPC 8 ID
abc EI BT /F1 12 Tf 72 660 Td (Indirect Geometry Inline Noise) Tj ET rawtail
EI
```

was treated as if `/W 101` and `/H 102` were direct dimensions. The impossible sample floor swallowed the following content. A focused probe returned only:

```text
Before Indirect Geometry Inline
```

After the source edit, the same probe returns:

```text
Before Indirect Geometry Inline
After Indirect Geometry Inline
```

and excludes `Indirect Geometry Inline Noise`, `abc EI`, `rawtail`, and `101 0 R` from visible text.

## Implementation

- `PdfTextExtractor` now distinguishes direct integer inline-image operands from indirect-looking `object generation R` tokens before computing unfiltered sample floors.
- Unfiltered inline images with unresolved geometry operands fail closed for sample-floor acceptance while still allowing the parser to recover at the real `EI` boundary before subsequent content.
- CCITT inline height ownership now uses the same direct-integer helper so indirect-looking `/Height` operands do not masquerade as direct row counts.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php`
  - `1 test files, 647 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageMalformedFilterPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxColorKeyOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamJpxCMapRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php`
  - `12 test files, 1811 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php`
  - emits `visible_text_imported=true`
  - emits `indirect_geometry_inline_payload_excluded_until_real_ei=true`
  - emits `excluded_inline_image_text=true`
  - emits `executes_python_or_models=false`
  - emits `executes_external_pdf_tools=false`

## Non-Overlap

This slice avoids the accepted CCITT Fax indirect Rows stream-ownership patch and previous native-filter, JPX, DCT, RunLength, LZW, null-filter, tokenizer, and renderer inline image boundary cases. It owns only unfiltered inline-image geometry operands that look like indirect references.

## Dependency Closure

No new support component is needed. The patch reuses the existing PHP content-stream tokenizer, inline image dictionary parser, stream-filter resolution, and focused WordPress smoke path.

## Next

Continue with a non-overlapping native markerPDF gap: font/CMap text extraction, xref repair, metadata, annotations/forms, image/filter metadata, or supplied-boundary table/equation handoffs. Keep GPU/model OCR parity recorded as an intentional lane limit.
