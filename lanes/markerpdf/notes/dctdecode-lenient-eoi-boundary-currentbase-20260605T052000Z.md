# markerPDF DCTDecode Lenient EOI Boundary

Micro-slice: `markerpdf-dctdecode-filter-boundary-current-base-20260605T052000Z`

Base accepted HEAD: `689a1d63f07b4ac9ee6dd4da0f28692001c18354`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text extraction through parser-backed text extraction before image rendering. At that boundary, `/Filter /DCTDecode` streams are image payloads for review/render handoff, not WordPress paragraph text.

Some lightweight PDFs contain malformed JFIF/APP bytes that require lenient JPEG EOI scanning. When a stale `/Length` stops at the first lenient `FF D9` and a later valid image boundary exists, markerPDF must not let a fake `endstream/endobj` region between those markers become searchable text.

## Red First

A current-base probe leaked the fake object text between the false and actual DCT boundaries:

```text
array (
  0 => 'Before malformed DCT',
  1 => 'Malformed DCT lenient EOI leak',
  2 => 'After malformed DCT',
)
```

The focused test added in this slice captures the same boundary in both stream-only fallback extraction and page XObject review extraction.

## Implementation

`PdfTextExtractor` now enumerates all candidate DCT EOI offsets when a malformed JPEG preview falls back to lenient scanning. Stale-length recovery paths can pass a minimum terminator floor, so a false lenient EOI followed by a fake `endstream` before that floor is skipped and the later actual DCT boundary is selected.

The prefix-filter DCT path remains on the existing decoded-payload completeness check and does not inherit the stale-length floor, preserving Flate/ASCIIHex/ASCII85/RunLength stack behavior.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 195 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeSegmentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php lanes/markerpdf/tests/PdfParserStreamFilterXrefOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfImageRendererTest.php
Focused test run: 8 selected test files (root lock skipped)
8 test files, 1544 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-dctdecode-lenient-eoi-boundary-currentbase.php
```

The smoke emits `false_eoi_before_fake_endstream_ignored=true`, `dctdecode_image_payload_excluded_from_text=true`, `raw_length_after_boundary_recovery=208`, `xobject_native_raster_decode=false`, `xobject_decoded_with_current_filters=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted direct DCTDecode SOI/EOI framing, NUL-padded JPEG EOI handling, valid APP segment false-EOI handling, inline DCT image boundaries, Flate/ASCIIHex/ASCII85/RunLength prefix-stack DCT boundaries, null-filter DecodeParms alignment, indirect DCT filter owner repair, unsupported `/Crypt /DCTDecode` fail-closed behavior, generic image XObject exclusion, or stream-filter stack recovery.

The bounded behavior is malformed JFIF/APP DCT streams that need lenient EOI fallback and contain an earlier stale false EOI followed by fake PDF object syntax before the real image boundary.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream boundary recovery helpers, DCT image review metadata, focused lane tests, and the WordPress smoke path. Full live OCR/model/raster parity remains intentionally out of scope under the current no-GPU markerPDF directive and remains gated on pdftext/PDFium/pypdfium2/PIL, Surya/Torch, tabled-pdf, Texify, runtime app/server workers, benchmark/model downloads, and external OCR/rendering helpers; none were run for this native parser slice.
