# markerPDF DCTDecode DecodeParms Missing-Slot Boundary

Session: `port-dev-markerpdf-dctdecode-filter-20260605T234446Z`
Micro-slice: `markerpdf-dctdecode-filter-boundary-current-base-20260605T234446Z`
Base accepted HEAD: `cd08f68a169af70e0c979f8f7ed342c9a882b0b9`

## Source Truth

Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF image rendering through PDFium/PIL image paths while searchable text extraction stays separate. In the no-GPU PHP lane, DCTDecode JPEG bytes therefore remain review-only, but `/Filter` and `/DecodeParms` alignment still matters because `/ColorTransform` controls future CMYK/YCCK RGB preview decisions.

For a filter stack such as `/Filter [/FlateDecode /DCTDecode]`, DecodeParms entries align by filter slot. A single DecodeParms dictionary in slot 0 is the Flate slot, not the DCT slot. If that dictionary looks like a DCT `/ColorTransform`, native review should fail closed and report the missing DCT DecodeParms slot rather than silently treating the DCT color transform as absent.

## Red First

Before the source edit, a probe on the accepted base returned this renderer review shape:

```json
[
  {"filter":"FlateDecode","preview_only":false,"decode_parms":{"type":"FlateDecode"}},
  {"filter":"DCTDecode","preview_only":true,"decode_parms":null}
]
```

The page Image XObject review row had the same missing DCT DecodeParms metadata, so WordPress review could not distinguish a genuinely absent `/ColorTransform` from a malformed/misaligned one.

## Implementation

- `PdfImageRenderer::imageFilterDetails()` now records fail-closed DCT DecodeParms alignment metadata when a DCT/DCTDecode filter has no aligned DecodeParms slot but the image dictionary declares non-null DecodeParms.
- `PdfImageRenderer::dctDecodeImageColorPlan()` now marks misaligned DCT DecodeParms as invalid and ignored before CMYK/YCCK RGB preview planning.
- `PdfTextExtractor::imageXObjectFilterDetails()` now emits the same DCT DecodeParms alignment review for page Image XObject metadata rows.
- The JPEG payload remains preview-only and excluded from visible WordPress paragraph text.

## Verification

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 626 assertions, 0 failures
```

Adjacent image/filter family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeSegmentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
5 test files, 1885 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-dctdecode-decodeparms-missing-slot-currentbase.php
```

The smoke emits `decode_parms_review=unaligned_dctdecode_decodeparms_fail_closed`, `decode_parms_alignment=missing_filter_slot`, `dct_decodeparms_color_transform_valid=false`, `dct_decodeparms_color_transform_ignored=true`, `payload_excluded_from_paragraphs=true`, clean paragraphs `Before DCT DecodeParms Review` and `After DCT DecodeParms Review`, and all Python/model/PDFium/PIL/external-tool execution flags false.

PHP syntax checks passed for changed PHP files:

```text
php -l lanes/markerpdf/src/PdfImageRenderer.php
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-dctdecode-decodeparms-missing-slot-currentbase.php
```

Whitespace check:

```text
git diff --check -- lanes/markerpdf
```

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted DCT JPEG stream SOI/EOI recovery, Flate/LZW/ASCIIHex/ASCII85 prefix ownership, null filter DecodeParms slot compaction, trailing null filter preservation, correctly aligned DCT DecodeParms, invalid `/ColorTransform` values, duplicate filter declarations, unsupported prefix filters, Crypt Identity boundaries, APP/SOS segment parsing, post-EOI surplus clipping, inline DCT tokenization, CCITT/JPX/JBIG2 filter boundaries, or generic image review-only classification.

The bounded new behavior is only the missing DCT DecodeParms slot after native prefix filters.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF dictionary parser, filter-stack resolver, DecodeParms slot alignment helpers, DCT color preview planner, Image XObject review metadata, and WordPress smoke path. Full JPEG raster decoding remains gated on a future native raster backend or the upstream PDFium/PIL path; OCR, Surya/Texify/Torch model execution, pypdfium, PIL, Poppler, Ghostscript, and external PDF tools were not run.
