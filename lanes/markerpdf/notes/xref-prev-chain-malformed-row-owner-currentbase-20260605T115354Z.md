# markerPDF xref Prev chain malformed row owner current-base

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260605T115354Z`
Base: `0e6781ae8b76f0d938e737f367e60c0dfb521f96`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF text, metadata, and attachments through parser-backed PDF object loading before OCR/model fallback. Under the current no-GPU markerPDF scope, the PHP lane owns the native equivalent for xref `/Prev` chains, xref-stream `/Index` row repair, catalog/Info/XMP metadata, EmbeddedFiles name trees, and WordPress paragraph text without running Python, OCR, models, or external PDF tools.

PDF incremental updates keep previous xref sections reachable through `/Prev`, but current xref-stream rows are still current-section liveness evidence. If a malformed latest xref-stream row names object `6 0` but its damaged offset points to stale object `4 0` in the previous revision, the row should suppress inherited object `6 0` from `/Prev`; it should not be remapped to stale object `4 0` unless the offset owner is inside the current update window.

## Behavior

`PdfTextExtractor`, `PdfMetadataExtractor`, `PdfEmbeddedFileExtractor`, and `PdfAttachmentExtractor` now only use an xref-stream row's offset owner as a row-owner repair when the owner offset is between the previous xref section and the current xref-stream offset. Damaged rows that point back into a stale `/Prev` section stay attached to their declared current row object number, which prevents inherited previous Info, XMP, name-tree, FileSpec, and EmbeddedFile objects from resurfacing.

The text, metadata, and embedded-file xref mergers also skip inherited previous type-2 rows when a newer unselected direct `/ObjStm` carrier exists before the current xref. That preserves accepted current carrier repair while preventing stale previous compressed members from binding to a replacement carrier that the current xref never selected.

The focused fixture appends a current xref stream with `/Index [1 4 6 3 10 2]`. Rows for stale previous metadata and attachment object numbers are present, but their explicit offsets all point to stale previous content object `4 0`. After the patch, WordPress import selects the current page text and catalog language while previous Info metadata, XMP metadata, EmbeddedFiles extraction, attachment preflight, stale page text, and stale payloads remain suppressed.

## Evidence

Pre-edit focused baseline:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 373 assertions, 0 failures
```

Focused green after repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 394 assertions, 0 failures
```

Carrier regression pair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefIncrementalObjectStreamFreeRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainUnselectedCarrierCurrentBaseTest.php
Focused test run: 2 selected test files (root lock skipped)
2 test files, 22 assertions, 0 failures
```

Full xref current-base sweep:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXref*CurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStream*CurrentBaseTest.php
Focused test run: 65 selected test files (root lock skipped)
65 test files, 2073 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-malformed-row-owner-currentbase.php
current_text_selected=true
current_catalog_language_selected=true
previous_info_suppressed_by_malformed_rows=true
previous_xmp_suppressed_by_malformed_rows=true
embedded_file_stale_prev_attachment_excluded=true
attachment_preflight_stale_prev_attachment_excluded=true
stale_visible_text_excluded=true
no_python_or_models_executed=true
no_external_pdf_tools_executed=true
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted `/Info null`, latest free-row authority, indirect `/Prev` helper repair, compressed `/Prev` helper repair, damaged same-generation xref offsets, stale explicit-offset repair, wrong current-offset repair, object-stream metadata selection across valid current rows, unselected previous carrier skipping, hybrid free-entry precedence, object-stream generation repair, stream-filter boundary work, Type3/font behavior, OCR/model execution, or table/equation handoffs.

The bounded behavior here is malformed latest xref-stream row-owner repair when the damaged row offset points to a stale previous-section owner, plus the coupled inherited type-2 carrier guard needed to keep accepted current carrier repair fail-closed.

## Dependency Closure

No new support component is needed. This slice reuses native PHP direct-object scanning, xref table/xref-stream `/Prev` merging, xref-stream `/Index` and `/W` decoding, object-stream carrier repair, text extraction, catalog/Info/XMP metadata extraction, EmbeddedFiles extraction, attachment preflight, and the WordPress smoke path. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, external OCR/rendering helpers, and exact upstream model benchmark parity remain intentionally outside the current no-GPU markerPDF scope.
