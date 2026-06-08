# markerpdf-xmp-metadata-boundary-current-base-20260608T183422Z

Scope: native no-GPU markerPDF metadata extraction only. This slice closes the
XMP encoding trust boundary where a Catalog `/Metadata` stream declares or
sniffs as UTF-16 but contains malformed source code units.

Source truth and non-overlap:
- Upstream markerPDF uses PDF metadata as document metadata when the catalog
  stream is actually document XMP. This PHP port must preserve that behavior
  for valid XMP while failing closed on malformed encoded bytes.
- Existing accepted XMP slices already cover valid BOM-less UTF-16 packets,
  declared Windows-1252 packets, packet begin/end boundaries, unsafe DTD/entity
  rejection, malformed first packet boundaries, namespace/comment/CDATA
  handling, and nested RDF resource-reference boundaries.
- This patch is disjoint: it changes only malformed strict UTF-16 decode
  handling before promotion. It does not touch OCR, Surya, Texify, Torch,
  Streamlit/FastAPI workers, named destinations, outlines, annotations, forms,
  or model benchmark parity.

Behavior:
- Non-fallback XMP encoding conversion now uses strict `iconv(..., "UTF-8")`
  rather than `UTF-8//IGNORE`, so invalid UTF-16 source bytes cannot be silently
  discarded and promoted as repaired document metadata.
- Malformed UTF-16 document XMP streams now remain review-only with
  `catalog.metadata_stream_review.status =
  rejected_malformed_document_xmp_encoding`.
- The redacted `xmp_summary` keeps provenance (`packet_encoding`, strict
  encoding boundary, reason) without exposing title/author/keyword payload
  strings.

Red-first evidence:
- Before the source change,
  `php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpMalformedEncodingBoundaryCurrentBaseTest.php`
  failed after 1 assertion because the malformed UTF-16BE packet was promoted
  as `source = ["xmp","info"]` and title `Malformed XMP Title` after the lone
  surrogate was dropped.

Focused verification:
- `php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpMalformedEncodingBoundaryCurrentBaseTest.php`
  => 1 test file, 27 assertions, 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpMalformedEncodingBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpUtf16BoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpDeclaredEncodingBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpUnsafePacketBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpPacketBoundaryCurrentBaseTest.php`
  => 5 test files, 233 assertions, 0 failures.

WordPress smoke:
- `php lanes/markerpdf/examples/wordpress-pdf-xmp-malformed-encoding-boundary-currentbase.php`
  exits 0 and emits WordPress comment metadata showing
  `rejected_malformed_document_xmp_encoding`, `packet_encoding = UTF-16BE`,
  `payload_included = false`, and repaired XMP text excluded from visible
  paragraphs.

Dependency closure:
- No new support component is needed. This reuses the existing native PDF
  parser, Flate stream decoder, DOM XML parser with `LIBXML_NONET`, and XMP
  metadata boundary helpers. No external PDF utilities, Python, OCR/models,
  GPU execution, or online services are required.

Next task:
- Continue non-overlapping native searchable-PDF work around fonts/CMaps,
  stream filters, xref repair, outlines, annotations, forms, page geometry,
  image/filter metadata, and supplied-boundary table/equation handoffs.
