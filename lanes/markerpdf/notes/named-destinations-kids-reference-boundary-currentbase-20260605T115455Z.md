# markerPDF Named Destinations Kids Reference Boundary

Session: `port-dev-markerpdf-named-destinations-20260605T115455Z`
Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260605T115455Z`
Base accepted HEAD: `ef8ba76252a11ec55fc47795254706c29b768a7f`

## Source Truth

Upstream markerPDF at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates searchable-PDF navigation and page text extraction to pdftext/PDFium before model stages. Under the current no-GPU markerPDF scope, this slice maps the native PDF parser boundary for catalog `/Names /Dests` name trees.

PDF destination name-tree `/Kids` arrays identify child name-tree nodes by indirect reference. A direct inline dictionary placed inside `/Kids` is malformed and must not donate destination names to WordPress review metadata, local link annotations, supplied-span links, or native TOC rows. Valid indirect child leaves and legacy catalog `/Dests` dictionaries remain supported.

## Red Baseline

After adding `PdfNamedDestinationKidsReferenceBoundaryCurrentBaseTest.php`, the accepted base failed before the source patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationKidsReferenceBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL requires destination name-tree Kids entries to be valid indirect node references before WordPress review
Expected: ["Current Child","Review Summary","LegacyOk"]
Actual: ["Current Child","Review Summary","Direct Child Decoy","LegacyOk"]
FAIL keeps malformed name-tree child dictionaries out of link promotion and visible WordPress text
Expected annotation actions: [["local-destination"],[],["review-uri"]]
Actual annotation actions: [["local-destination"],["local-destination"],["review-uri"]]
1 test files, 4 assertions, 2 failures
```

## Implementation

- `PdfNamedDestinationExtractor` now recurses name-tree `/Kids` only through valid indirect child references.
- `PdfActionReviewExtractor` applies the same rule before building the destination map used by Link annotation `/Dest` promotion.
- `PdfOutlineExtractor` applies the same rule before resolving named destinations for TOC/navigation review rows.
- `PdfMetadataExtractor` applies the rule to document destination metadata and generic catalog name-tree review rows.
- The focused fixture keeps a valid child leaf, a direct inline child dictionary, an invalid-generation child reference, a scalar kid, outline rows, Link annotations, and a legacy `/Dests` entry in one PDF.

## Evidence

Focused run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationKidsReferenceBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS requires destination name-tree Kids entries to be valid indirect node references before WordPress review
PASS keeps malformed name-tree child dictionaries out of link promotion and visible WordPress text
1 test files, 45 assertions, 0 failures
```

Adjacent named-destination family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestination*CurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationExtractorTest.php
Focused test run: 20 selected test files (root lock skipped)
20 test files, 500 assertions, 0 failures
```

Adjacent link/outline family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php lanes/markerpdf/tests/PdfLinkAnnotationNameTreeLimitsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php lanes/markerpdf/tests/PdfOutline*Test.php
Focused test run: 52 selected test files (root lock skipped)
52 test files, 2944 assertions, 0 failures
```

Metadata family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfMetadata*CurrentBaseTest.php
Focused test run: 34 selected test files (root lock skipped)
34 test files, 2308 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-named-destination-kids-reference-boundary-currentbase.php
```

The smoke emits `destination_names=["Current Child","Review Summary","LegacyOk"]`, `document_destination_names=["Current Child","Review Summary","LegacyOk"]`, `toc_titles=["Current Child Outline"]`, `promoted_link_objects=[7,9]`, `direct_child_destination_rejected=true`, `bad_generation_child_rejected=true`, `safe_uri_link_preserved=true`, `visible_text_excludes_destination_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Full root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass` moves `1794 -> 1796` from the two new focused PASS cases.
- Focused assertion coverage for this boundary is 45 assertions.
- `wordpressScenarios` moves `1633 -> 1634` from the new smoke.

## Non-Overlap

This does not repeat accepted destination direct `/Limits` pruning, malformed leaf `/Limits` fallback, malformed intermediate `/Kids` limit recovery, indirect `/Kids`/`/Names`/`/Limits` arrays, internal-node local `/Names` exclusion, PDFDocEncoding string keys, PDF name-key rejection, page-only destinations, page-operand validation, non-GoTo action dictionary rejection, destination view-mode validation, generation-exact destination dictionaries/page refs, object-stream recovery, trailer-root selection, xref-selected duplicate body selection, xref-stream `/Prev` walking, outline destination action context, link annotation name-tree `/Limits`, PageLabels, xref repair, metadata, attachment, font, image/filter, or Type3 behavior. The bounded behavior is only that destination name-tree `/Kids` entries must be valid indirect child-node references before traversal.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF object parser, generation-aware reference resolver, destination name-tree walkers, page tree indexer, metadata extractor, action review extractor, outline resolver, link span promotion, text extractor, and WordPress smoke renderer. GPU/model/OCR/PDFium/PIL execution remains intentionally outside the current no-GPU markerPDF scope.
