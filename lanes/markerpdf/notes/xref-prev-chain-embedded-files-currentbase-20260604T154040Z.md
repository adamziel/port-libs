# markerPDF xref Prev chain embedded-file current-base

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260604T154040Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates PDF parsing to pdftext/PDFium before attachment and metadata review. The native PHP lane owns the no-GPU parser boundary where the latest xref `/Prev` chain selects the current catalog and name-tree objects before WordPress attachment import.

PDF incremental updates can keep stale catalog `/Names /EmbeddedFiles` trees in previous xref sections while the latest xref stream names a nonzero-generation `/Root`. Damaged current in-use rows with explicit bad offsets must not cause the attachment extractor to drop the current catalog or fall back to stale `/Prev` attachments when the current direct generation bodies are present.

## Behavior

`PdfEmbeddedFileExtractor` now repairs the latest trailer `/Root` direct generation after xref-chain selection, then follows nonzero-generation references from repaired dictionaries for name-tree, Filespec, and EmbeddedFile objects. This mirrors the existing metadata-side current-generation repair, but keeps the change local to embedded-file import.

The focused fixture appends generation-one catalog, EmbeddedFiles name-tree, Filespec, and EmbeddedFile payload objects after a stale generation-zero attachment tree. The latest xref stream has `/Prev`, `/Root 1 1 R`, and damaged explicit offset `0` rows for the current generation-one objects. WordPress attachment review now selects `current-source.xml` and its current payload while excluding `stale-source.xml` and the stale payload.

## Evidence

Existing focused baseline before this additive case:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS repairs current metadata generation objects through damaged xref Prev chain offsets
PASS does not resolve generation-zero catalog Metadata to a generation-one current xref object

1 test files, 23 assertions, 0 failures
```

Focused green after patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS repairs current metadata generation objects through damaged xref Prev chain offsets
PASS does not resolve generation-zero catalog Metadata to a generation-one current xref object
PASS repairs trailer Root generation before embedded-file name-tree attachment import

1 test files, 37 assertions, 0 failures
```

Adjacent embedded-file gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
Focused test run: 2 selected test files (root lock skipped)
2 test files, 389 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-incremental-update-currentbase.php
embedded_file_current_attachment_selected=true
embedded_file_current_payload_selected=true
embedded_file_stale_prev_attachment_excluded=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This extends the accepted xref `/Prev` current-generation metadata repair into the embedded-file attachment extractor. It does not repeat CMap width fallback, xref object-stream free-entry repair, hybrid `/XRefStm` generation recovery, trailer `/Encrypt` or `/ID` precedence, xref-stream sparse `/Index` generation metadata, stream-filter owner boundaries, or the previous metadata-only xref `/Prev` repair.

## Dependency Closure

No new support component is needed. This slice reuses the native direct-object scanner, xref table and xref-stream `/Prev` chain merger, Flate stream decoder, EmbeddedFiles name-tree walker, Filespec parser, and WordPress smoke renderer. GPU/model/OCR, PDFium, pdftext, PIL, Surya/Torch, Texify, Streamlit/FastAPI, and external PDF tools were not run and remain intentionally outside the current no-GPU markerPDF scope.
