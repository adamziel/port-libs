# markerPDF xref Prev chain page-review current-base

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260605T141717Z`

## Source truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF text and page metadata through pdftext/PDFium-backed parsing before WordPress-facing conversion. Under the current no-GPU markerPDF lane rule this slice ports the native PDF parser boundary, not OCR/model execution.

PDF incremental updates use the latest `startxref` section as the current base and then merge older sections through `/Prev`. Rows present in the latest section own the current revision. If a current xref stream row has a damaged zero or stale explicit offset for a same-generation direct object appended between the previous xref and the latest xref, the native parser repairs that current row to the matching in-window object before inheriting stale `/Prev` rows.

## Behavior

`PdfPagePropertyExtractor` now repairs current-section direct xref rows before `/Prev` inheritance. This brings page review metadata in line with the existing text, metadata, and embedded-file xref repair paths:

- current `/Page` `/PieceInfo` review metadata is selected;
- current page `/AF` FileSpec review metadata is selected;
- stale previous page review objects and stale attachment names are excluded;
- embedded payload bytes stay out of visible WordPress paragraphs and page-review comments;
- no Python, pdftext, OCR, model, or external PDF tool execution is used.

## Red-first evidence

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageReviewXrefPrevChainCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL repairs page review metadata through xref Prev current update rows
Expected: 1
Actual: 0
1 test files, 3 assertions, 1 failures
```

## Verification

Focused slice:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageReviewXrefPrevChainCurrentBaseTest.php
1 test files, 33 assertions, 0 failures
```

Adjacent page-review family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageAssociatedFilesMarkedContentAltCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceResolvedGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceParentKidsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceMalformedBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageReviewXrefPrevChainCurrentBaseTest.php
5 test files, 123 assertions, 0 failures
```

Adjacent xref Prev chain family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainAttachmentNearMissPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainHybridTableCompressedPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainObjectStreamMetadataCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainUnselectedCarrierCurrentBaseTest.php
5 test files, 526 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-review-xref-prev-chain-currentbase.php
```

The smoke emits `current_import_kept=true`, `stale_prev_review_excluded=true`, `associated_payload_content_omitted=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat accepted text extraction, document metadata, embedded-file name-tree, object-stream carrier, forward `/Prev`, `/Info null`, free-row suppression, hybrid `/XRefStm`, or annotation link xref behavior. The new behavior is specifically `PdfPagePropertyExtractor` page review metadata when the latest xref stream owns damaged same-generation current rows before `/Prev` inheritance.

## Dependency closure

No new support component is needed. The slice reuses the native PHP direct-object scanner, xref stream decoder, `/Prev` chain parser, page property extractor, stream filter decoder, and WordPress smoke path. GPU/model OCR, Surya/Texify/Torch, pypdfium/PDFium runtime calls, and external PDF tools remain intentionally out of scope.
