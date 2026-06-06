# Xref Prev Chain Object-Stream Carrier Current Base

Slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260606T123915Z`

Accepted base: `fe05e2e35ce542cd316066edfc41d801f52e0e2c`

## Source truth

Upstream markerPDF treats PDF extraction as a parser handoff from pdftext/PDFium for searchable PDFs. In this native PHP lane, the in-scope behavior is the PDF xref/object parser boundary, not OCR/model execution. PDF incremental updates use `/Prev` chains where later rows override older rows, but inherited previous-section rows are still needed when the latest update omits a live object.

For object streams, type-2 compressed member rows depend on the selected object-stream carrier body. A final incremental xref stream can override the carrier with a damaged explicit row, while the previous xref section still contains the valid carrier row and the compressed catalog/Info/name-tree/FileSpec member rows. In that case the parser must recover the previous carrier row instead of inheriting compressed member rows that cannot be decompressed.

## Implementation

`PdfTextExtractor` already had `currentCarrierEntryCanRecoverPreviousObjectStreamStorage()` in the xref merge path. This patch applies that same current-carrier repair gate to:

- `PdfMetadataExtractor`
- `PdfEmbeddedFileExtractor`
- `PdfAttachmentExtractor`

Both classic-table and xref-stream `/Prev` merge paths now replace a damaged current carrier row with the previous valid carrier row when the previous compressed rows still depend on that carrier. Normal latest-row precedence is unchanged, and `previousCompressedEntryUsesUpdatedObjectStream()` still blocks stale type-2 rows when the selected current carrier is valid and different.

## Evidence

Red-first before source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainObjectStreamCarrierCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL recovers previous object-stream carrier when latest xref Prev section has a damaged carrier row
Expected metadata sources ['xmp','info','catalog'] but got []
1 test files, 3 assertions, 1 failures
```

Focused green after source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainObjectStreamCarrierCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS recovers previous object-stream carrier when latest xref Prev section has a damaged carrier row

1 test files, 27 assertions, 0 failures
```

Adjacent xref-family verification:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainObjectStreamCarrierCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainObjectStreamMetadataCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamCurrentCarrierRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamPrevGenerationRebuildCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainUnselectedCarrierCurrentBaseTest.php
Focused test run: 6 selected test files (root lock skipped)
6 test files, 635 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-carrier-currentbase.php
current_xmp_title_selected=true
current_info_selected=true
current_catalog_language_selected=true
current_page_text_selected=true
current_attachment_selected=true
attachment_summary_current=true
damaged_carrier_row_present=true
stale_prev_excluded=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

Syntax and whitespace:

```text
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/src/PdfEmbeddedFileExtractor.php
php -l lanes/markerpdf/src/PdfAttachmentExtractor.php
php -l lanes/markerpdf/tests/PdfXrefPrevChainObjectStreamCarrierCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-carrier-currentbase.php
git diff --check -- lanes/markerpdf
```

All syntax checks passed. `git diff --check -- lanes/markerpdf` passed. Root harness was not run: isolated micro-slice.

## Non-overlap

This does not repeat the prior same-generation damaged explicit-offset repairs, stale explicit-offset repairs, generation-exact Metadata/EmbeddedFiles selection, free-row suppression, unselected-carrier guard, or standalone object-stream current-carrier repair. This slice is specifically the final incremental `/Prev` chain case where metadata and attachment extractors inherit previous compressed rows but also need the previous valid object-stream carrier body after the latest carrier row is damaged.

## Dependency closure

No new support component is needed. The patch reuses the native PHP xref-chain parser, object-stream decoder, metadata extraction, embedded-file extraction, and attachment summary extraction. No GPU/OCR/model path, Python subprocess, external PDF tool, live service, or upstream benchmark runner is required.

## Next task

Continue with non-overlapping native markerPDF parser fidelity around xref repair, stream filters, font/CMap text extraction, page geometry, annotations/forms, metadata, and supplied-boundary table/equation handoffs.
