# markerPDF outline metadata title-boundary current-base slice

Micro-slice: `markerpdf-outline-metadata-boundary-current-base-20260605T021006Z`

Base accepted HEAD: `c18b7fab17ed6f251da458e5ccae2a3545ed7a6d`

## Source Truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps PDF TOC/bookmark metadata separate from page text through `marker/cleaners/toc.py::get_pdf_toc` and PDF text extraction through `marker/pdf/extract_text.py::get_text_blocks`.
- PDF outline item dictionaries require `/Title`; native WordPress review should treat an untitled outline item as malformed navigation metadata.
- A malformed untitled item can still sit in the `/Next` chain between two valid titled siblings. The native no-GPU boundary should exclude that item descendants and actions, but continue to later valid siblings when their `/Prev` backlink matches the untitled link node.

## Red Baseline

After adding `PdfOutlineMetadataTitleBoundaryCurrentBaseTest.php`, the accepted base failed:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataTitleBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL treats untitled outline items as child traversal boundaries in document metadata
Expected: 2
Actual: 4
FAIL applies untitled outline boundaries to TOC navigation and remote action review
Expected titles: Title Boundary Chapter, Title Boundary Appendix
Actual titles: Title Boundary Chapter, Stale Child Under Untitled Outline, Title Boundary Appendix
1 test files, 9 assertions, 2 failures
```

## Implementation

- `PdfMetadataExtractor::documentOutlineItemMetadataRows()` now descends into child outline lists only when the current item has a resolved `/Title`.
- `PdfOutlineExtractor` applies the same title boundary to:
  - `getPdfToc()`
  - `getPdfTocWithDestinationViews()`
  - `getNavigationReviewMetadata()`
  - outline action review rows
  - remote GoTo outline rows
- Untitled items still advance the sibling chain, so a later titled sibling with `/Prev` pointing at the untitled item remains importable.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataTitleBoundaryCurrentBaseTest.php
PASS treats untitled outline items as child traversal boundaries in document metadata
PASS applies untitled outline boundaries to TOC navigation and remote action review
1 test files, 37 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutline*Test.php
34 test files, 2031 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadata*Test.php
21 test files, 1692 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-metadata-title-boundary-currentbase.php
```

Smoke emitted `imported_item_count=2`, `outline_objects=[6,10]`, `second_item_previous_object=7`, `untitled_child_excluded=true`, `untitled_parent_action_excluded=true`, `stale_remote_actions_excluded=true`, `visible_text_excludes_outline_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Full root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass` moves `1280 -> 1282` from the two new focused PASS cases.
- Focused assertion coverage for the new title-boundary test is 37 assertions.
- WordPress scenario count moves `1243 -> 1244` from the added smoke.

## Non-Overlap

This does not repeat accepted outline metadata color preservation, title encoding, `/SE` StructElem review metadata, declared `/Last` traversal, missing/wrong `/Parent` boundaries, `/Prev` mismatch boundaries, EOF-bounded outline selection, generation-exact outline references, xref owner boundaries, named-destination action context, remote GoTo/GoToE review, action-chain target review, PageLabels, page transition/action metadata, xref repair, attachment, AcroForm, image, font, stream-filter, or encrypted-permission slices. The bounded behavior is only required-title gating for child/action traversal under malformed outline items.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF object parser, outline resolver, destination name-tree resolver, metadata extractor, TOC/navigation review paths, remote action review path, and WordPress smoke renderer. GPU/model/OCR/PDFium/PIL execution remains intentionally out of scope under the markerPDF no-GPU directive.
