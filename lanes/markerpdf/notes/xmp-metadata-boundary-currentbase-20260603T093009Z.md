# markerPDF XMP Metadata Stream Boundary

Date: 2026-06-03 09:30 UTC

Micro-slice: `markerpdf-xmp-metadata-boundary-current-base-20260603T093009Z`

## Behavior

`PdfMetadataExtractor` now only promotes catalog `/Metadata` bytes into root
document XMP when the referenced stream dictionary is `/Type /Metadata` and
`/Subtype /XML`.

If `/Catalog /Metadata` points at another stream type, such as an embedded-file
XML payload, the stream is rejected as document XMP and summarized under
`catalog.metadata_stream_review` with:

- `accepted_as_document_xmp=false`
- `payload_included=false`
- stream type/subtype/filter metadata
- decoded-byte length and SHA-256
- redacted XMP field/date summary when the payload looks like XMP

Trailer `/Info` remains the document metadata fallback, and rejected XMP text
values are not promoted into root title/author fields or visible WordPress
paragraphs.

## Source Truth

- Upstream `sddai/markerPDF` at pinned commit
  `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` gets searchable-PDF text and
  metadata through the pdftext/PDFium document-loading boundary before later
  layout/OCR/model stages.
- PDF parser source truth for this slice: catalog `/Metadata` is a document
  metadata stream boundary. XML-like streams with a different dictionary type or
  subtype are not document-level XMP roots, even when the payload parses as XMP.

## Evidence

Red-first focused gate before implementation:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php`

Result: failed `rejects non metadata XML streams from catalog Metadata document
roots`; actual source was `["xmp","info"]` instead of expected
`["info","catalog"]`.

Post-fix focused metadata family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadata*Test.php`

Result: `16 test files, 1504 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xmp-metadata-boundary-currentbase.php`

Result: emitted `source=["info","catalog"]`,
`catalog_metadata_review_status="rejected_non_metadata_xml_stream"`,
`catalog_metadata_type="EmbeddedFile"`, `catalog_metadata_subtype="text/xml"`,
`accepted_as_document_xmp=false`, `payload_included=false`,
`xmp_payload_values_redacted=true`, `hidden_xmp_not_promoted=true`, and
`hidden_xmp_not_visible_text=true`.

Changed PHP lint:

- `php -l lanes/markerpdf/src/PdfMetadataExtractor.php`
- `php -l lanes/markerpdf/tests/PdfMetadataExtractorTest.php`
- `php -l lanes/markerpdf/examples/wordpress-pdf-xmp-metadata-boundary-currentbase.php`

Result: no syntax errors.

## Status Delta

- Behavior tests move `1017 -> 1018` for the new current-base XMP metadata
  boundary PASS case.
- Focused metadata family now passes `16` files with `1504` assertions.
- WordPress scenario coverage moves `1017 -> 1018` with the new catalog
  metadata stream boundary smoke.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object
scanner, dictionary parser, stream decoder, XMP review summarizer, `/Info`
decoder, and text extractor. No Python, pypdfium, pdftext, Surya, Texify,
Torch, OCR, image raster, online service, or external PDF tool execution was
run.

## Non-Overlap

This does not repeat accepted XMP packet decoding, PDFDocEncoding `/Info`
fallback, XMP/Info timezone normalization, xref-stream trailer metadata
precedence, encrypted metadata priority, name-tree XMP review, associated-file
XMP review, PieceInfo XMP review, PDF/A OutputIntent association, or stream
filter endstream recovery. The bounded behavior is specifically catalog
`/Metadata` stream dictionary type/subtype validation before root document XMP
promotion.

## Next Task

Continue with non-overlapping native metadata/parser boundaries such as catalog
metadata error provenance, annotation/form metadata review, page geometry,
image/filter metadata, or xref repair behavior under the no-GPU markerPDF
scope.
