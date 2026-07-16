# markerPDF outline page operand boundary current-base slice

Micro-slice: `markerpdf-outline-metadata-boundary-current-base-20260605T053006Z`

Base accepted HEAD: `4d91007bafdf12504e3d93f023ba1b74fc3b19ae`

## Source Truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps PDF bookmarks/TOC as separate metadata through `marker/cleaners/toc.py::get_pdf_toc`, which delegates page resolution to the PDF document backend.
- Upstream text extraction remains separate through `marker/pdf/extract_text.py::get_text_blocks`; outline strings should not become page text.
- Native no-GPU parity boundary: local numeric outline page operands must resolve inside the current document page count before TOC/navigation promotion. Invalid local `/Dest [99 /Fit]` and `/A << /S /GoTo /D [88 /Fit] >>` operands are not valid local pages in a two-page document.

## Red Baseline

After adding `PdfOutlineMetadataPageOperandBoundaryCurrentBaseTest.php`, the accepted base failed before the source patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataPageOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps invalid local numeric page operands as unresolved outline document metadata
FAIL rejects invalid local numeric page operands from TOC and navigation review rows
Expected titles: Page Operand Boundary Chapter, Page Operand Boundary Appendix
Actual titles: Page Operand Boundary Chapter, Invalid Numeric Dest Page Operand, Invalid Numeric Action Page Operand, Page Operand Boundary Appendix
1 test files, 21 assertions, 1 failures
```

`PdfMetadataExtractor` already kept the invalid operands unresolved. `PdfOutlineExtractor` accepted any non-negative integer local destination page index and promoted out-of-range page 99/88 into TOC/navigation rows.

## Implementation

- `PdfOutlineExtractor` now bounds local numeric destination page indexes against the current page count before returning destination view details, plain page indexes, or explicit-destination array page indexes.
- Valid page object references and in-range numeric page indexes still resolve.
- Invalid local GoTo action operands remain non-executing `unsupported-action-review` metadata rows with `page=null`; they are not promoted to TOC/navigation outline rows.

## Verification

Focused red-to-green:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataPageOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps invalid local numeric page operands as unresolved outline document metadata
PASS rejects invalid local numeric page operands from TOC and navigation review rows
1 test files, 40 assertions, 0 failures
```

Adjacent outline/named-destination regression:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutline*Test.php lanes/markerpdf/tests/PdfNamedDestinationPageOperandBoundaryCurrentBaseTest.php
Focused test run: 41 selected test files (root lock skipped)
41 test files, 2302 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-page-operand-boundary-currentbase.php
```

The smoke emits `invalid_dest_page_operand_unpromoted=true`, `invalid_action_page_operand_unpromoted=true`, `destination_resolved=[true,false,false,true]`, `toc_titles=["Import Page Operand Chapter","Import Page Operand Appendix"]`, `navigation_titles=["Import Page Operand Chapter","Import Page Operand Appendix"]`, `invalid_action_review_safety=["unsupported-action-review"]`, `visible_text_excludes_outline_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Full root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass` moves `1470 -> 1472` from the two new focused PASS cases.
- Focused assertion coverage for the new page-operand boundary test is 40 assertions.
- WordPress scenario count moves `1385 -> 1386` from the added smoke.

## Non-Overlap

This does not repeat accepted outline root/type/title/comment/parent/missing-parent/Prev/Last/root-count/generation/EOF/xref-owner boundaries, name-tree limits, remote GoTo/GoToE review, destination action context, page-label/transition/thread enrichment, named-destination page-operand extraction, xref repair, metadata, AcroForm, image, font, stream-filter, or encrypted-permission slices. The bounded behavior is only local numeric page-index validation inside `PdfOutlineExtractor` before TOC/navigation promotion.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF object parser, outline resolver, page tree ordering, destination name-tree resolver, metadata extractor, navigation review paths, and WordPress smoke renderer. GPU/model/OCR/PDFium/PIL execution remains intentionally out of scope under the markerPDF no-GPU directive.
