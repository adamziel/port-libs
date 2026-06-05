# markerPDF xref Prev chain unselected carrier current-base

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260605T051357Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text and document metadata through parser-backed pdftext/PDFium boundaries before OCR/model fallback. In the native no-GPU PHP lane, xref `/Prev` chains, object-stream carrier ownership, embedded-file name trees, catalog metadata, and visible WordPress paragraph text are parser dependency boundaries.

PDF xref-stream type-2 rows are owned by their selected object-stream carrier. A previous `/Prev` section can contain a compressed-object row without selecting the carrier row. When a later incremental update contains an unlisted replacement `/ObjStm` with the same carrier object number, the previous compressed row must not bind to that replacement for metadata or EmbeddedFiles attachment import.

## Behavior

`PdfMetadataExtractor` and `PdfEmbeddedFileExtractor` now skip inherited previous type-2 rows when the previous xref chain did not select the row's object-stream carrier, or when the current xref section replaced the carrier storage. This matches the accepted text-extractor boundary and prevents WordPress import from surfacing attachments through unselected replacement object streams.

The focused fixture builds:

- a previous xref stream whose object `8` is a type-2 EmbeddedFiles name-tree row in object stream `6`, but whose previous section never selects carrier object `6`;
- a current incremental xref stream with `/Prev`, current catalog/page text, and an unlisted replacement object stream `6` that contains an attachment decoy for object `8`;
- direct decoy FileSpec and EmbeddedFile payload objects that must stay unreachable once the inherited type-2 row is skipped.

Before the fix, native embedded-file extraction imported `unselected-carrier-leak.xml` from the unselected replacement object stream. After the fix, current page text and catalog language remain selected, while embedded-file extraction returns no files and metadata review excludes the decoy payload.

## Evidence

Red baseline before source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainUnselectedCarrierCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL skips previous type-2 attachment rows whose object-stream carrier was never selected
Expected: array (
)
Actual: array (
  0 =>
  array (
    'source' => 'catalog_names_embedded_files',
    'name' => 'unselected-carrier-leak.xml',
    'filename' => 'unselected-carrier-leak.xml',
...
1 test files, 5 assertions, 1 failures
```

Focused green after repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainUnselectedCarrierCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS skips previous type-2 attachment rows whose object-stream carrier was never selected

1 test files, 12 assertions, 0 failures
```

Adjacent xref/object-stream gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainUnselectedCarrierCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefIncrementalObjectStreamFreeRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainObjectStreamMetadataCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamPrevFreeCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamPrevFreeGenerationBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamFreeEntryPrevCurrentBaseTest.php
Focused test run: 7 selected test files (root lock skipped)
7 test files, 322 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-unselected-carrier-currentbase.php
current_page_text_selected=true
current_catalog_language_selected=true
unselected_carrier_attachment_excluded=true
replacement_object_stream_payload_excluded=true
previous_carrier_text_excluded=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted text-side previous type-2 carrier skipping, current trailer `/Root` and `/Info` generation repair, damaged same-generation xref offsets, stale explicit-offset repair, indirect `/Prev` helpers, compressed `/Prev` helpers, sparse latest `/Info`, `/Info null`, object-stream metadata selection across valid xref rows, hybrid free-entry precedence, or stream-filter boundary work.

The bounded behavior here is metadata and EmbeddedFiles import fail-closed behavior for inherited previous type-2 rows whose previous xref section never selected their object-stream carrier before a current incremental update contains an unselected replacement carrier.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP direct-object scanner, xref table/xref-stream `/Prev` merger, object-stream decoder, metadata extractor, embedded-file extractor, text extractor, and WordPress smoke path. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, external OCR/rendering helpers, and exact upstream model benchmark parity remain intentionally outside the current no-GPU markerPDF scope.
