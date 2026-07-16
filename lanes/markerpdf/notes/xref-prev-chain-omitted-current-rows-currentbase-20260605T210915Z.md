# xref /Prev chain omitted current rows current-base slice

Date: 2026-06-05 UTC
Base accepted HEAD: 51a06c2e4d068494c9869cbc4ab8445059008d96
Micro-slice: markerpdf-xref-prev-chain-incremental-update-current-base-20260605T210915Z

## Source-truth behavior

Some incremental-update PDFs append same-generation replacement objects before the latest xref stream, while the latest xref stream names only a sparse row set and points `/Root` and `/Info` at those replacement objects. PDF readers should use the current revision objects reachable from the latest trailer graph before inheriting stale rows from `/Prev`.

This patch keeps the existing `/Prev` merge order but repairs omitted current rows for the latest trailer graph before previous-section rows are inherited. The repair is bounded to direct object definitions between the previous xref offset and the current xref offset, and it does not override any current xref row that is explicitly present, including free rows.

## Implementation

- `PdfTextExtractor` now repairs omitted current direct rows reachable from latest `/Root` and `/Info` before merging stale `/Prev` rows.
- `PdfMetadataExtractor`, `PdfEmbeddedFileExtractor`, and `PdfAttachmentExtractor` apply the same rule so XMP/Info/catalog metadata, EmbeddedFiles name trees, FileSpecs, embedded streams, and attachment summaries select the same current revision.
- Added a focused current-base fixture where the latest xref stream has `/Index [5 1]`, omitting the current same-generation catalog/page/content/Info/metadata/name-tree/FileSpec/embedded-file rows.

## Verification

Red-first:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainOmittedCurrentRowsCurrentBaseTest.php`

Result before source repair: `1 test files, 1 assertions, 1 failures`; text extraction selected `Stale omitted-row Prev page`.

Green:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainOmittedCurrentRowsCurrentBaseTest.php`

Result after source repair: `1 test files, 24 assertions, 0 failures`.

Focused xref family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainOmittedCurrentRowsCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainObjectStreamMetadataCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainUnselectedCarrierCurrentBaseTest.php`

Result: `4 test files, 542 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-omitted-current-rows-currentbase.php`

Output confirms `current_page_text_selected=true`, `current_xmp_title_selected=true`, `current_info_title_selected=true`, `current_attachment_selected=true`, `attachment_summary_current=true`, `omitted_current_rows_repaired=true`, `stale_prev_rows_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

This slice does not repeat damaged explicit-offset repair, stale explicit-offset repair, wrong-current-object offset repair, free-row suppression, indirect `/Prev` helpers, object-stream carrier recovery, compressed `/Prev` helpers, hybrid xref table/stream merging, or CMap/font-width work. It covers only omitted same-generation current rows reachable from the latest trailer graph before stale `/Prev` rows are inherited.

## Dependency closure

No new support component is needed. The implementation reuses the existing native PHP PDF object scanners, trailer dictionary parsing, xref stream/table parsing, and attachment/metadata/text extractor paths. GPU/OCR/model behavior, external PDF tools, and upstream visual/model benchmark parity remain intentionally out of scope for this no-GPU markerPDF lane.

## Next task

Continue native xref repair with another non-overlapping current-base gap, preferably hybrid xref stream/table precedence around sparse current rows, object-stream member recovery boundaries, or xref repair interactions with outlines/forms/annotations.
