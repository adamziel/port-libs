# markerPDF Outline Action Destination Metadata Boundary Current Base

Micro-slice: `markerpdf-outline-metadata-boundary-current-base-20260604T232529Z`

Base accepted HEAD: `dfccfd252d4ec7968da59da8d0cbc92468a86823`

## Source Truth

- Upstream `sddai/markerPDF` is pinned in the lane manifest at commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream TOC extraction treats PDF outline rows as navigation metadata from the PDF engine and keeps them separate from page text blocks and Markdown body output.
- PDF outline `/Dest` entries and outline `/A << /S /GoTo /D ... >>` actions are same-document navigation. `/S /GoToR`, `/S /GoToE`, `/S /URI`, and other non-`GoTo` actions are action-review metadata; their `/D` operands must not be resolved against the current document name tree even if the name collides with a local destination.

## Implementation

- `PdfMetadataExtractor::documentOutlineItemDestination()` now resolves an outline action `/D` into current-document destination details only when the action type is `/GoTo`.
- Non-`GoTo` action rows still retain `action_type`, `action_object`, and the reviewable destination name, but they stay `destination_resolved=false` and do not receive current-document `page`, `page_object`, or view metadata.
- Added a focused fixture where `/GoToR`, `/URI`, and `/GoToE` outline actions all use `/D /CurrentLocalTarget`, colliding with a valid local `/Names /Dests` row.
- Added `wordpress-pdf-outline-action-destination-boundary-currentbase.php` to prove the WordPress-visible import keeps one local outline target resolved, leaves the three non-`GoTo` action rows unresolved in document metadata, keeps action operands in navigation review metadata, and excludes all outline/action operands from visible paragraph text.

## Verification

Red-first focused run after adding the test and before changing source:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataBoundaryCurrentBaseTest.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
PASS summarizes current xref-selected catalog Outlines in document metadata
PASS preserves outline text color metadata without promoting it to page text
PASS keeps outline metadata and stale appended objects out of visible WordPress text
FAIL does not resolve remote outline action destinations as current-document metadata targets (lanes/markerpdf/tests/PdfOutlineMetadataBoundaryCurrentBaseTest.php)
Values are not identical
Expected: 1
Actual: 4

1 test files, 88 assertions, 1 failures
```

After patch:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataBoundaryCurrentBaseTest.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
PASS summarizes current xref-selected catalog Outlines in document metadata
PASS preserves outline text color metadata without promoting it to page text
PASS keeps outline metadata and stale appended objects out of visible WordPress text
PASS does not resolve remote outline action destinations as current-document metadata targets

1 test files, 131 assertions, 0 failures
```

Adjacent outline/metadata family:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataParentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataLastBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineNavigationEofMetadataBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php lanes/markerpdf/tests/PdfMetadataCatalogOutlineAssociatedSecurityBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php
```

Result: `8 test files, 1520 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-outline-action-destination-boundary-currentbase.php
```

Result: emitted `resolved_destination_count=1`, `unresolved_destination_count=3`, `non_goto_actions_unresolved=true`, `remote_action_review_retained=true`, `visible_text_excludes_outline_actions=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted catalog document-outline extraction, current xref-selected metadata, outline parent/last/generation traversal boundaries, EOF-bounded outline selection, page labels, target page transitions, article-thread review, OpenAction review, or remote action review extraction. The new behavior is specifically the document metadata boundary that prevents non-`GoTo` outline action `/D` operands from being resolved as current-document destinations.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF parser, catalog outline traversal, action dictionary reader, destination name-tree resolver, and text extractor. Full upstream runner/model parity remains intentionally out of scope under the current no-GPU markerPDF directive: no pdftext/PDFium execution, Surya/OCR/Texify/Torch model runs, Streamlit/FastAPI workers, or external PDF tools were invoked.

## Next Task

Continue with non-overlapping native markerPDF parser/converter work: searchable-PDF metadata, outlines, annotations, forms, page geometry, image/filter review metadata, xref repair, security preflight, or supplied-boundary table/equation handoffs.
