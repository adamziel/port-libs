# Outline Metadata XRef Owner Boundary

Slice: `markerpdf-outline-metadata-boundary-current-base-20260605T002907Z`
Base accepted HEAD: `810d0706bf9e20b666c6562cd776779e2c68b0d5`

## Source Truth

Pinned upstream markerPDF `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `pdftext.dictionary_output()` and gets document TOC rows from `marker.cleaners.toc.get_pdf_toc(doc)`, which calls the PDFium-backed `doc.get_toc(max_depth=max_depth)`. The PHP no-GPU boundary therefore keeps outline metadata review in native parser code but must honor PDF xref-selected object ownership before object-looking unindexed duplicates.

## Behavior

`PdfOutlineExtractor` now parses classic xref rows from the selected `startxref` table and prefers those object offsets/generations when building outline/navigation object values. If an object number has no xref row, the existing direct-object repair fallback remains available for bounded malformed PDFs. If an xref row marks an object free or points to a different generation/offset, that duplicate object no longer overrides the selected outline root.

The focused fixture places a stale duplicate `/Outlines` object and a JavaScript action dictionary after the trailer but before `startxref`. Before the patch, `PdfOutlineExtractor` let that unindexed duplicate win, so TOC/navigation lost the current outline rows. After the patch, current xref-selected outline titles, destinations, text color, and chained current review actions are preserved while stale action text stays out of review metadata and visible WordPress paragraphs.

## Verification

Red-first:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataXrefOwnerBoundaryCurrentBaseTest.php
=> 1 test files, 16 assertions, 1 failures
```

Focused after fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataXrefOwnerBoundaryCurrentBaseTest.php
=> 1 test files, 28 assertions, 0 failures
```

Adjacent outline/metadata family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfOutlineMetadataBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataLastBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataMissingParentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataParentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataPrevBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataUnindexedEofBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineNavigationEofMetadataBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataXrefOwnerBoundaryCurrentBaseTest.php
=> 11 test files, 1557 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-metadata-xref-owner-boundary-currentbase.php
=> stale_unindexed_outline_excluded=true; stale_unindexed_action_excluded=true; visible_text_excludes_outline_metadata=true; executes_python_or_models=false; executes_external_pdf_tools=false
```

## Non-Overlap

This does not repeat DCTDecode/stream-filter image boundaries, outline `/Last`, `/Prev`, missing-parent, generation, or post-EOF traversal boundaries. The bounded behavior is specifically classic xref-selected object ownership inside `PdfOutlineExtractor` before TOC/navigation metadata generation.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF token parser and adds bounded classic xref-table owner parsing. GPU/model OCR, Surya/Texify/Torch, pypdfium execution, and external PDF tools remain out of scope under the current markerPDF no-GPU lane directive.
