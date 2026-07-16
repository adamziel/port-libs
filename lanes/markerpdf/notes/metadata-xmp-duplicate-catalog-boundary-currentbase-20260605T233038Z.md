# markerPDF duplicate catalog XMP metadata boundary current base

Micro-slice: `markerpdf-xmp-metadata-boundary-current-base-20260605T233038Z`

Base accepted HEAD: `88bd304ac9f81983b2962e10b4ceec4a58890a16`

## Source Truth

Upstream `sddai/markerPDF` remains pinned at
`da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. The no-GPU searchable-PDF path
gets page text from `marker/pdf/extract_text.py::get_text_blocks()` via
`pdftext.extraction.dictionary_output(...)` and `naive_get_text()` via
pypdfium/PDFium text extraction.

At the native parser boundary, catalog `/Metadata` XMP is document metadata
only when the catalog has a single unambiguous metadata stream reference.
Duplicate top-level dictionary keys are malformed and ambiguous, so duplicate
catalog `/Metadata` entries must not let a hidden later XMP stream replace
trailer `/Info` fallback or appear in visible WordPress text.

## Behavior

- `PdfMetadataExtractor::extractXmpMetadata()` now requires exactly one
  top-level catalog `/Metadata` entry before XMP promotion.
- `catalog_metadata_stream_boundary` review now reports
  `rejected_duplicate_metadata_entries` for duplicate catalog `/Metadata`
  declarations.
- The duplicate review row records candidate object numbers and redacted entry
  summaries without decoding or exposing XMP payload text.
- Single valid catalog `/Metadata` streams, null metadata, direct dictionaries,
  unresolved references, unreadable streams, non-stream metadata objects, and
  non-metadata XML stream review behavior remain unchanged.

## Evidence

Probe before the source edit promoted the second duplicate `/Metadata` stream as
document XMP:

`source=["xmp","info"]`, `title="Duplicate Metadata Hidden XMP Title"`.

After the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpMetadataBoundaryCurrentBaseTest.php`

Result: `1 test files / 77 assertions / 0 failures`.

Adjacent XMP metadata family:

`php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f \( -name 'PdfMetadataXmp*Test.php' -o -name 'PdfMetadataExtractorTest.php' \) | sort)`

Result: `35 test files / 2364 assertions / 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xmp-metadata-boundary-currentbase.php`

Result: emitted `duplicate_metadata_status="rejected_duplicate_metadata_entries"`,
`duplicate_metadata_entry_count=2`, `duplicate_metadata_candidate_objects=[8]`,
`duplicate_metadata_values_redacted=true`, `duplicate_metadata_not_visible_text=true`,
`executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- `phpPass`: `2273 -> 2274` from one new focused TestRunner PASS case.
- `wordpressScenarios`: `1954 -> 1955` from the updated WordPress XMP metadata
  boundary smoke.
- `UPSTREAM_TEST_MANIFEST.json` now records
  `pdfMetadataXmpDuplicateCatalogMetadataCurrentBase`.

## Non-Overlap

This does not repeat accepted catalog `/Metadata` null/direct/unresolved/
unreadable boundaries, non-stream metadata-object rejection, non-metadata XML
stream rejection, stream-object tail rejection, packet begin/end boundaries,
complete-packet fallback, external `rdf:about` filtering, UTF-16/declared
encoding parsing, DTD/entity rejection, namespace wrapper filtering, language
alternatives, qualified values, resource references, associated FileSpec XMP
generation exactness, encrypted metadata source priority, OutputIntent/
PieceInfo/name-tree metadata review, fonts, images, annotations, forms, OCR, or
model execution.

The bounded behavior is only duplicate top-level catalog `/Metadata` keys before
document XMP promotion.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object
scanner, dictionary entry parser, stream dictionary reviewer, XMP parser,
trailer Info fallback, text extractor, and WordPress smoke path. Live
`pdftext`, pypdfium/PDFium, Surya/Torch OCR/layout/table models, Texify,
tabled-pdf, Streamlit/FastAPI workers, and external OCR/rendering tools remain
intentionally out of scope for this no-GPU markerPDF slice.
