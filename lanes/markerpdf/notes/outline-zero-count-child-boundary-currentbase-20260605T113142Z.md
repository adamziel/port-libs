# markerPDF outline zero-count child-boundary current-base slice

Micro-slice: `markerpdf-outline-metadata-boundary-current-base-20260605T113142Z`

Base accepted HEAD: `5cc1cb8c4d627591b12d77b58e620af0751191d7`

## Source Truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps PDF TOC/bookmark metadata separate from page text through its PDF/PDFium-backed outline extraction path.
- PDF outline `/Count` is the declared descendant visibility count for an outline item. A zero count with contradictory `/First` and `/Last` child references should not let hidden child bookmarks or child actions enter WordPress navigation metadata.
- Current markerPDF lane scope is no-GPU/no-model: this slice uses only native PHP searchable-PDF parsing, metadata review, TOC/navigation review, and the WordPress smoke renderer.

## Red Baseline

After adding `PdfOutlineMetadataZeroCountChildBoundaryCurrentBaseTest.php`, the accepted base failed because the child row under an item with `/Count 0` was still traversed:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataZeroCountChildBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL does not traverse outline children when item Count declares zero descendants in document metadata
Expected: 2
Actual: 3
FAIL applies zero Count child boundary to TOC navigation and remote outline actions
Expected titles: Zero Count Boundary Chapter, Zero Count Boundary Appendix
Actual titles: Zero Count Boundary Chapter, Zero Count Hidden Child, Zero Count Boundary Appendix
1 test files, 9 assertions, 2 failures
```

## Implementation

- `PdfMetadataExtractor::documentOutlineItemMetadataRows()` now descends into child outline lists only when the current item does not resolve `/Count` to `0`.
- `PdfOutlineExtractor` applies the same zero-count child traversal boundary to:
  - `getPdfToc()`
  - `getPdfTocWithDestinationViews()`
  - `getNavigationReviewMetadata()`
  - outline action review rows
  - remote GoTo outline rows
- Parent rows still preserve `/First`, `/Last`, `/Count`, and `descendant_count` review metadata, so contradictory child references are visible for review without importing hidden child titles/actions.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataZeroCountChildBoundaryCurrentBaseTest.php
PASS does not traverse outline children when item Count declares zero descendants in document metadata
PASS applies zero Count child boundary to TOC navigation and remote outline actions
1 test files, 36 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutline*Test.php
50 test files, 2563 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadata*Test.php
34 test files, 2311 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-outline-zero-count-child-boundary-currentbase.php
```

Smoke emitted `imported_item_count=2`, `parent_first_child_object=8`, `parent_outline_count=0`, `parent_descendant_count=0`, `remote_action_count=0`, `zero_count_child_excluded=true`, `hidden_remote_action_excluded=true`, `visible_text_excludes_outline_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and status checks:

```text
php -l lanes/markerpdf/src/PdfOutlineExtractor.php
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/tests/PdfOutlineMetadataZeroCountChildBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-outline-zero-count-child-boundary-currentbase.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
```

Full root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass` moves `1777 -> 1779` from the two new focused TestRunner PASS cases.
- Focused assertion coverage for the new zero-count child-boundary test is 36 assertions.
- WordPress scenario count moves `1618 -> 1619` from the added smoke.

## Non-Overlap

This does not repeat accepted outline metadata title, color, page operand, root count, `/Last`, `/Prev`, `/Parent`, missing-parent, titleless bridge, direct root, generation, xref owner, EOF, structure element, named-destination action, remote GoTo/GoToE, page transition, PageLabels, or security/action-chain slices. The bounded behavior is only zero `/Count` child traversal gating for outline metadata and review rows.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF object parser, outline resolver, destination name-tree resolver, metadata extractor, TOC/navigation review paths, remote action review path, and WordPress smoke renderer. GPU/model/OCR/PDFium/PIL execution remains intentionally out of scope under the markerPDF no-GPU directive.
