# markerPDF Named Destinations Internal Leaf Boundary

Session: `port-dev-markerpdf-named-destinations-20260605T093431Z`
Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260605T093431Z`
Base accepted HEAD: `1fdb5223a4b72ef1c1155f017cdae1bee3efbbfd`

## Source Truth

Upstream markerPDF at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps PDF TOC/navigation and link metadata separate from extracted page text before OCR/model stages. Under the current no-GPU markerPDF scope, this slice maps the native searchable-PDF parser boundary for catalog `/Names /Dests` name trees.

PDF name-tree nodes with `/Kids` are internal nodes. Their valid destination entries come from descendant leaf nodes, not local `/Names` arrays on the internal node. A malformed internal node that carries both `/Kids` and `/Names` must not promote the local names into WordPress destination review metadata, TOC rows, or link spans.

## Red Baseline

After adding `PdfNamedDestinationInternalLeafBoundaryCurrentBaseTest.php`, the accepted base failed before the source patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationInternalLeafBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL treats destination name-tree nodes with Kids as internal nodes before WordPress review metadata
Expected: ["Child Target","Review Summary","LegacyOnly"]
Actual: ["Inline Parent Target","Child Target","Review Summary","LegacyOnly"]
FAIL keeps internal-node local destination names out of link promotion and visible WordPress text
Expected annotation actions: [["local-destination"],[],["review-uri"]]
Actual annotation actions: [["local-destination"],["local-destination"],["review-uri"]]
1 test files, 4 assertions, 2 failures
```

## Implementation

- `PdfNamedDestinationExtractor` now processes `/Names` entries only on destination name-tree leaves without `/Kids`.
- `PdfActionReviewExtractor` and `PdfOutlineExtractor` use the same leaf-only destination map so annotations, catalog/open actions, outlines, TOC, and navigation rows do not diverge.
- `PdfMetadataExtractor` applies the boundary to document destination metadata and generic name-tree review summaries so WordPress review bundles do not surface internal-node destination entries through a secondary field.
- Child leaf `/Names` entries still resolve through effective `/Limits`, legacy `/Dests` fallback remains intact, and safe URI link annotations remain promoted.

## Evidence

Focused run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationInternalLeafBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS treats destination name-tree nodes with Kids as internal nodes before WordPress review metadata
PASS keeps internal-node local destination names out of link promotion and visible WordPress text
1 test files, 38 assertions, 0 failures
```

Adjacent named-destination family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestination*CurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationExtractorTest.php
Focused test run: 17 selected test files (root lock skipped)
17 test files, 415 assertions, 0 failures
```

Adjacent outline/link family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfOutline*Test.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php lanes/markerpdf/tests/PdfLinkAnnotationNameTreeLimitsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php
Focused test run: 49 selected test files (root lock skipped)
49 test files, 2853 assertions, 0 failures
```

Metadata regression:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadata*CurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php
Focused test run: 31 selected test files (root lock skipped)
31 test files, 2184 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-named-destination-internal-leaf-boundary-currentbase.php
```

The smoke emits `destination_names=["Child Target","Review Summary","LegacyOnly"]`, `toc_titles=["Child Target Outline"]`, `child_destination_linked=true`, `internal_parent_destination_unpromoted=true`, `safe_uri_link_preserved=true`, `visible_text_excludes_destination_names=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Full root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass` moves `1678 -> 1680` from the two new focused PASS cases.
- Focused assertion coverage for this boundary is 38 assertions.
- `wordpressScenarios` moves `1541 -> 1542` from the new smoke.

## Non-Overlap

This does not repeat accepted destination direct `/Limits` pruning, malformed leaf `/Limits` fallback, malformed intermediate `/Kids` limit recovery, indirect `/Kids`/`/Names`/`/Limits` arrays, PDFDocEncoding string keys, PDF name-key rejection, page-only destinations, page-operand validation, non-GoTo action dictionary rejection, destination view-mode validation, generation-exact destination dictionaries/page refs, object-stream recovery, trailer-root selection, xref-selected duplicate body selection, outline destination action context, PageLabels, xref repair, metadata, attachment, font, image/filter, or Type3 behavior. The bounded behavior is only the leaf-only rule for destination name-tree nodes that contain `/Kids`.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF object parser, generation-aware reference resolver, destination name-tree walkers, page tree indexer, metadata extractor, action review extractor, outline resolver, link span promotion, text extractor, and WordPress smoke renderer. GPU/model/OCR/PDFium/PIL execution remains intentionally outside the current no-GPU markerPDF scope.
