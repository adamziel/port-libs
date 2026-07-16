# markerPDF outline metadata parent-boundary current-base slice

Micro-slice: `markerpdf-outline-metadata-boundary-current-base-20260604T180109Z`

Base accepted HEAD: `f67476b356aa9223837bf1adc57ac27863c446b0`

## Source Truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps PDF TOC/bookmark metadata separate from page text through `marker/cleaners/toc.py::get_pdf_toc` and PDF text extraction through `marker/pdf/extract_text.py::get_text_blocks`.
- PDF outline item lists are parent-scoped linked lists. A child outline item's `/Next` must not pull in a top-level sibling whose `/Parent` points back to the outline root.
- WordPress import needs malformed outline navigation to fail bounded: preserve the valid top-level sibling once in review metadata, but do not duplicate it through the child list or promote outline titles into body text.

## Red Baseline

After adding `PdfOutlineMetadataParentBoundaryCurrentBaseTest.php`, the current base failed:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataParentBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL bounds document outline metadata traversal by each item parent object
Expected: 3
Actual: 4
FAIL applies the same outline parent boundary to TOC and navigation review rows
Expected titles: Parent Boundary Chapter, Parent Boundary Child, Parent Boundary Appendix
Actual titles: Parent Boundary Chapter, Parent Boundary Child, Parent Boundary Appendix, Parent Boundary Appendix
1 test files, 11 assertions, 2 failures
```

## Implementation

- `PdfMetadataExtractor::documentOutlineItemMetadataRows()` now receives the expected parent object for the current outline list and stops traversal when the next outline item has a different non-null `/Parent`.
- `PdfOutlineExtractor` applies the same parent-boundary rule to:
  - `getPdfToc()`
  - `getPdfTocWithDestinationViews()`
  - `getNavigationReviewMetadata()`
  - outline action review rows
  - remote GoTo outline rows
- The guard accepts missing `/Parent` values to avoid regressing older minimal fixtures, but enforces a present parent object when the PDF declares one.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataParentBoundaryCurrentBaseTest.php
PASS bounds document outline metadata traversal by each item parent object
PASS applies the same outline parent boundary to TOC and navigation review rows
1 test files, 34 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php lanes/markerpdf/tests/PdfOutline*CurrentBaseTest.php
25 test files, 1691 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadata*Test.php
16 test files, 1504 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-metadata-parent-boundary-currentbase.php
```

Smoke emitted `appendix_duplicate_excluded=true`, `outline_levels=[1,2,1]`, `visible_text_excludes_outline_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Full root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF object parser, outline resolver, destination name-tree resolver, metadata extractor, TOC/navigation review paths, and WordPress smoke renderer. GPU/model/OCR/PDFium/PIL execution remains intentionally out of scope under the markerPDF no-GPU directive.

## Non-Overlap

This does not repeat accepted EOF-bounded outline selection, named destination resolution, remote GoTo/GoToE review, outline action-chain context, PageLabels, page transition/action metadata, outline style/color metadata, outline `/SE` structure metadata, xref repair, attachment, AcroForm, image, font, or stream-filter slices. The bounded behavior is only parent-scoped outline `/Next` traversal for malformed child lists.
