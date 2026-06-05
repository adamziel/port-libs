# markerPDF xref Prev Info-null outline metadata current-base

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260605T111833Z`

## Source Truth

Pinned upstream markerPDF `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` obtains searchable PDF text and document outline metadata through pdftext/PDFium-backed parsing before model execution. The native no-GPU PHP lane therefore owns the parser boundary where the current xref `/Prev` chain decides whether trailer `/Info` metadata is present, inherited, or explicitly cleared before WordPress review metadata is emitted.

PDF incremental updates can explicitly clear a previous trailer Info dictionary with `/Info null`. The full `PdfMetadataExtractor` already honored this for document metadata, but the lightweight `PdfTextExtractor::extractOutlineMetadata()` path still fell through to its legacy whole-file `/Info ... R` fallback after the current xref-stream trailer returned no Info reference.

## Behavior

`PdfTextExtractor` now treats `/Info null` as terminal while walking the current startxref `/Prev` chain and while scanning fallback trailer dictionaries. `documentInfoFromPdf()` also blocks its legacy whole-file `/Info` regex fallback when the active current chain explicitly clears Info.

The focused fixture reuses the current-base xref-stream `/Prev` chain where the latest xref stream points to the current catalog/page objects, sets `/Info null`, and keeps a stale previous-section Info dictionary in the file. Lightweight outline metadata now returns empty `document_info`, keeps the current page count, and excludes the stale previous title, author, and producer.

## Evidence

Red-first focused run before the parser fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL stops previous Info inheritance in lightweight outline metadata when latest xref-stream trailer sets Info null
Expected: array (
)
Actual: array (
  'title' => 'Stale Info Null Prev Title',
  'author' => 'Stale Info Null Author',
  'producer' => 'Stale Info Null Producer',
)

1 test files, 366 assertions, 1 failures
```

Focused green after the parser fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
22 PASS cases
1 test files, 373 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-incremental-update-currentbase.php
info_null_outline_metadata_stops_prev_info=true
info_null_latest_xref_stream_stops_prev_info=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat xref-stream damaged offset repair, same-generation current object repair, classic xref-table `/Prev` row repair, indirect/compressed `/Prev` operand helpers, xref-stream indirect `/W` and `/Index` operands, latest sparse `/Info` inheritance, latest free-row suppression, or root-free fallback blocking. The bounded new behavior is only explicit current-trailer `/Info null` clearing for lightweight outline `document_info`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF tokenizer, direct-object scanner, xref table/xref-stream `/Prev` chain walker, trailer dictionary parser, lightweight outline metadata extractor, and existing WordPress smoke path. Live OCR, Surya/Texify/Torch, pypdfium/PDFium execution, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope under the current no-GPU markerPDF lane.
