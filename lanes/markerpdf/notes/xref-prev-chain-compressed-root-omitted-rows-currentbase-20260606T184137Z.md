# markerPDF xref Prev chain compressed root omitted rows

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260606T184137Z`

Accepted base: `acaa02c88a520d876cc4ddf5ac66d11adb415693`

## Source Truth

Upstream markerPDF at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF text and metadata extraction through pdftext/PDFium-backed parsing before model/OCR fallback. Under the current no-GPU PHP lane scope, the relevant native boundary is PDF xref-chain traversal and object graph selection before WordPress text, metadata, and attachment import.

PDF incremental updates may select the latest trailer `/Root` as a type-2 object-stream member while omitting current same-generation direct rows for the catalog's page tree, XMP metadata, name tree, FileSpec, and EmbeddedFile objects. The xref `/Prev` chain remains available, but stale previous rows must not win for objects reachable from the current compressed catalog.

## Behavior

`PdfTextExtractor`, `PdfMetadataExtractor`, `PdfEmbeddedFileExtractor`, and `PdfAttachmentExtractor` now decode a selected generation-zero type-2 graph object only when its `/ObjStm` carrier is itself selected from the current update window. That bounded member body is used only to discover object references for omitted-row repair before stale `/Prev` rows are inherited.

The fixture builds a stale previous xref table with page text, XMP, Info, and attachment rows. The current update appends direct same-generation replacements for page tree, metadata, and attachment objects, but the latest xref stream only selects `/Root 1 0 R` as an object-stream member, font row `5`, and carrier row `30`. The parser now follows references from the compressed catalog member and repairs the omitted current rows before WordPress import.

## Evidence

Red baseline before source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainCompressedRootOmittedRowsCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL repairs omitted current rows reachable only through a compressed trailer root across Prev chain
Expected: array (
  0 => 'Current compressed-root page',
  1 => 'Omitted rows repaired from compressed catalog',
)
Actual: array (
  0 => 'Stale compressed-root Prev page',
)
1 test files, 1 assertions, 1 failures
```

Focused green after repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainCompressedRootOmittedRowsCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS repairs omitted current rows reachable only through a compressed trailer root across Prev chain
1 test files, 26 assertions, 0 failures
```

Adjacent xref Prev-chain family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChain*CurrentBaseTest.php
Focused test run: 19 selected test files (root lock skipped)
19 test files, 884 assertions, 0 failures
```

Adjacent object-stream family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStream*CurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamIndirectPrevObjectStreamCurrentBaseTest.php
Focused test run: 39 selected test files (root lock skipped)
39 test files, 889 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-compressed-root-omitted-rows-currentbase.php
compressed_root_catalog_selected=true
omitted_current_page_rows_repaired=true
omitted_current_xmp_row_repaired=true
omitted_current_attachment_rows_repaired=true
attachment_summary_current=true
stale_prev_rows_excluded=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted direct trailer-root omitted-row repair, object-stream catalog metadata with explicit rows, compressed `/Prev` helper selection, object-stream carrier repair, stale explicit-offset repair, malformed `/Index` remapping, free annotation suppression, hybrid table companion `/XRefStm` repair, or attachment-summary compressed `/Prev` behavior. The bounded behavior is only omitted current-row repair when the current trailer graph is first reached through a selected compressed root catalog member.

## Dependency Closure

No new support component is needed. The patch reuses native PHP xref table/stream parsing, object-stream member decoding, stream filters, metadata extraction, text extraction, embedded-file extraction, attachment summaries, and the WordPress smoke path. It does not execute shell, Python, CUDA, models, OCR, online services, or external PDF tools.
