# markerPDF outline metadata Last-boundary current-base slice

Micro-slice: `markerpdf-outline-metadata-boundary-current-base-20260604T193810Z`

Base accepted HEAD: `2eb30dcd499f3dc00a244d1b2fe1a3591cc33d7e`

## Source Truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps PDF TOC/bookmark metadata separate from page text through `marker/cleaners/toc.py::get_pdf_toc` and PDF text extraction through `marker/pdf/extract_text.py::get_text_blocks`.
- PDF outline sibling lists are declared by `/First` and `/Last`, with `/Next` and `/Prev` linking the list. A malformed same-parent `/Next` after the declared `/Last` must not pull stale or decoy bookmark rows into WordPress navigation review.
- WordPress import needs the valid `/Next` object numbers preserved as review metadata, but traversal must stop at `/Last` for document metadata, TOC rows, navigation review rows, outline action review rows, and remote GoTo review rows.

## Red Baseline

After adding `PdfOutlineMetadataLastBoundaryCurrentBaseTest.php`, the accepted base failed:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataLastBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL bounds document outline metadata traversal by declared Last item
Expected: 3
Actual: 5
FAIL applies Last boundary to TOC navigation and remote outline action review
Expected titles: Last Boundary Chapter, Last Boundary Child, Last Boundary Appendix
Actual titles: Last Boundary Chapter, Last Boundary Child, Stale Child After Last, Last Boundary Appendix, Stale Root After Last
1 test files, 9 assertions, 2 failures
```

## Implementation

- `PdfMetadataExtractor::documentOutlineItemMetadataRows()` now receives the declared sibling-list `/Last` object and stops traversal after processing that object.
- `PdfOutlineExtractor` applies the same terminal bound to:
  - `getPdfToc()`
  - `getPdfTocWithDestinationViews()`
  - `getNavigationReviewMetadata()`
  - outline action review rows
  - remote GoTo outline rows
- Nested child lists pass their own `/Last` object, so malformed child `/Next` links and malformed top-level `/Next` links are both bounded.
- Row metadata still preserves the raw `next_object`, so review UIs can see that a malformed terminal item pointed at a decoy without traversing it.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataLastBoundaryCurrentBaseTest.php
PASS bounds document outline metadata traversal by declared Last item
PASS applies Last boundary to TOC navigation and remote outline action review
1 test files, 41 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataLastBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataParentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataBoundaryCurrentBaseTest.php
3 test files, 154 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php lanes/markerpdf/tests/PdfOutline*CurrentBaseTest.php
26 test files, 1732 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadata*Test.php
16 test files, 1504 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-metadata-last-boundary-currentbase.php
```

Smoke emitted `outline_objects=[6,8,7]`, `outline_next_objects=[7,10,9]`, `stale_root_after_last_excluded=true`, `stale_child_after_last_excluded=true`, `stale_remote_actions_excluded=true`, `visible_text_excludes_outline_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Full root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF object parser, outline resolver, destination name-tree resolver, metadata extractor, TOC/navigation review paths, remote action review path, and WordPress smoke renderer. GPU/model/OCR/PDFium/PIL execution remains intentionally out of scope under the markerPDF no-GPU directive.

## Non-Overlap

This does not repeat accepted EOF-bounded outline selection, parent-scoped outline `/Next` traversal, named destination resolution, remote GoTo/GoToE review, outline action-chain context, PageLabels, page transition/action metadata, outline style/color metadata, outline `/SE` structure metadata, xref repair, attachment, AcroForm, image, font, stream-filter, or encrypted-permission slices. The bounded behavior is only `/Last`-terminated outline sibling traversal with stale same-parent `/Next` decoys after the terminal item.
