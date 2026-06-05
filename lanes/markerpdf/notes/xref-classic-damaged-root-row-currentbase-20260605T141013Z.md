# markerPDF classic xref damaged root-row current-base

Micro-slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260605T141013Z`
Base accepted HEAD: `52394894fe770269b8e2ae4edf4a1b9535bc8e02`

## Source truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF text through parser-backed pdftext/PDFium object loading before OCR/model fallback. Under the current no-GPU markerPDF scope, the PHP lane owns the native xref and direct-object repair boundary for searchable PDFs, metadata, and embedded-file import.

Classic xref rebuild must not stop at a syntactically valid row whose explicit offset is damaged. When the latest classic table declares `/Root 20 0 R` and object `20 0 obj` exists before that table, a damaged `0000000000 00000 n` row for object 20 should be repaired from direct object boundaries before stale previous-section catalog, page text, XMP/Info metadata, and EmbeddedFiles rows are considered.

## Implementation

`PdfTextExtractor`, `PdfMetadataExtractor`, `PdfEmbeddedFileExtractor`, and `PdfAttachmentExtractor` now repair classic xref-table in-use rows when the explicit offset is missing, stale, points to the wrong object, or points to the wrong generation, as long as the declared object/generation has a direct body before the selected xref table. The repair remains bounded to pre-table direct object bodies and does not reinterpret free rows or scan after the selected xref.

The focused fixture appends stale objects and a previous valid classic table, then appends current objects 20-31 and a latest classic table whose `/Root` row has a damaged explicit offset. WordPress import now selects current page text, current XMP/Info metadata, current EmbeddedFiles attachment, and current attachment preflight while excluding stale previous rows.

## Evidence

Red-first one-off probe before source edit:

```text
PdfTextExtractor::extractTextLines(...) => []
PdfMetadataExtractor::extractDocumentMetadata(...)[title] => null
PdfEmbeddedFileExtractor::extractEmbeddedFiles(...)[0][name] => current-invalid-root-row.xml
```

Focused test after source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
23 PASS cases
1 test files, 546 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-classic-xref-damaged-root-row-currentbase.php
```

The smoke emits `current_classic_xref_import_kept=true`, `stale_previous_import_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Non-overlap

This does not repeat malformed classic row rejection, zero-count table rejection, comment/composite/string/stream-owned xref decoy skipping, post-startxref trailer rejection, forward `/Prev` repair, classic `/Prev` damaged-offset repair, xref-stream damaged-offset repair, xref-stream malformed `/Index` owner repair, object-stream generation repair, hybrid xref precedence, stream-filter boundary work, fonts/CMaps, OCR, or model execution.

The bounded behavior is specifically a latest classic xref-table rebuild where a declared in-use root row has a damaged explicit offset but the declared object/generation body exists before the selected table.

## Dependency closure

No new support component is needed. This reuses native PHP direct-object scanning, classic xref table parsing, startxref rebuild selection, page-tree text extraction, XMP/Info/catalog metadata extraction, EmbeddedFiles extraction, attachment preflight, and the WordPress smoke renderer. Full upstream model parity remains dependency-gated by pdftext/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed here.
