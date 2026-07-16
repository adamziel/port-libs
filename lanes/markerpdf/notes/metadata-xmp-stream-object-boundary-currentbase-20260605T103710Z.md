# markerPDF XMP metadata stream object boundary current base

Micro-slice: `markerpdf-xmp-metadata-boundary-current-base-20260605T103710Z`

Base accepted HEAD: `d9d41d3151c8a8cec51322c58b72834b0637dde0`

## Source truth

Upstream `sddai/markerPDF` remains pinned at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. The no-GPU searchable-PDF path obtains page text from `marker/pdf/extract_text.py::get_text_blocks()` through `pdftext.extraction.dictionary_output(...)` and `naive_get_text()` through pypdfium/PDFium page text extraction:

https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py

At this parser boundary, catalog `/Metadata` XMP is document metadata, not visible paragraph text, and PDF action/sibling tokens after a stream object are not part of the metadata stream payload. The native PHP fallback must therefore fail closed when a catalog `/Metadata` stream object decodes valid XMP but carries extra non-comment top-level tokens after `endstream`.

## Implementation

- `PdfMetadataExtractor::extractXmpMetadata()` now requires catalog `/Metadata` stream objects to consume exactly one stream token before document XMP promotion.
- `catalog_metadata_stream_boundary` review now reports `rejected_malformed_metadata_stream_object` for decoded metadata stream objects with non-comment trailing tokens after `endstream`.
- The review row keeps payload bytes out of metadata while preserving redacted `xmp_summary` field names/dates for import review.
- Comment-only tails after `endstream` remain accepted as PDF whitespace.

## Red-first evidence

Before the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpStreamObjectBoundaryCurrentBaseTest.php`

failed with the malformed stream object promoted as XMP:

`Expected: ["info","catalog"]; Actual: ["xmp","info"]`

Result: `1 test files / 17 assertions / 1 failures`.

After the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpStreamObjectBoundaryCurrentBaseTest.php`

passed:

`1 test files / 37 assertions / 0 failures`.

Adjacent XMP/metadata gate:

`php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'PdfMetadataXmp.*Test\.php' | sort) lanes/markerpdf/tests/PdfMetadataExtractorTest.php`

passed:

`22 test files / 1765 assertions / 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-metadata-xmp-stream-object-boundary-currentbase.php`

emitted `malformed_status="rejected_malformed_metadata_stream_object"`, `malformed_xmp_rejected=true`, `malformed_action_tail_excluded=true`, `comment_tail_xmp_accepted=true`, `visible_text_excludes_xmp_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat accepted XMP packet begin/end boundaries, empty-packet fallback, unpaired-begin handling, xpacket instruction filtering, DTD/entity rejection, CDATA/comment root selection, non-Adobe namespace wrappers, stream dictionary type/subtype checks, unreadable metadata stream filters, encrypted metadata source priority, XMP generation exactness for FileSpec metadata, catalog PieceInfo private metadata boundaries, xref-stream trailer metadata, outline scalar boundaries, or generic stream-filter decoding. The bounded new behavior is only malformed catalog document XMP stream objects that have non-comment top-level tokens after `endstream`.

## Dependency closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream dictionary reader, stream payload boundary finder, XMP parser/reviewer, Info fallback metadata, text extractor, and WordPress smoke path. Live `pdftext`, pypdfium/PDFium, Surya/Torch OCR/layout/table models, Texify, tabled-pdf, Streamlit/FastAPI workers, and external OCR/rendering tools remain intentionally out of scope for this no-GPU markerPDF slice.
