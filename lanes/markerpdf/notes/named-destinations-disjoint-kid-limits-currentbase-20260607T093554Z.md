# markerPDF Named Destinations Disjoint Kid Limits Current Base

Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260607T093554Z`
Session: `port-dev-markerpdf-named-destinations-20260607T093554Z`
Base accepted HEAD: `b86d159cdf99a07a68249d9af6c697b1a15bfa78`

## Source Truth

Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` carries searchable-PDF navigation metadata through pdftext/PDFium before OCR/model handoff. Under the current no-GPU markerPDF scope, this slice maps the native PDF name-tree boundary for catalog `/Names /Dests`: internal child nodes with valid `/Limits` that do not intersect inherited parent `/Limits` must not be traversed, because their nested `/Kids` are outside the selected name-tree range.

No OCR, Surya, Texify, Torch, Python model worker, pypdfium/PDFium, browser, or external PDF tool execution was used.

## Behavior

- `PdfNamedDestinationExtractor` now prunes disjoint internal destination name-tree kids before collecting nested destination rows.
- `PdfMetadataExtractor`, `PdfActionReviewExtractor`, and `PdfOutlineExtractor` apply the same internal-kid pruning so document metadata, annotation/link action review, and outline destination maps agree.
- Existing malformed leaf `/Limits` fallback is preserved: leaf nodes whose local limits match none of their own keys still fall back to inherited parent bounds.
- Destination labels and action operands remain review metadata only and do not leak into visible WordPress paragraph text.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationDisjointKidLimitsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL prunes disjoint destination kid Limits before WordPress destination metadata
Expected: ["Alpha Start","Review Link","LegacyTail"]
Actual: ["Beta Decoy","Review Link","Alpha Start","LegacyTail"]
FAIL keeps disjoint named destinations unresolved for WordPress link promotion and visible text
Expected annotation objects: [7,9]
Actual annotation objects: [7,8,9]
1 test files, 3 assertions, 2 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationDisjointKidLimitsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS prunes disjoint destination kid Limits before WordPress destination metadata
PASS keeps disjoint named destinations unresolved for WordPress link promotion and visible text
1 test files, 32 assertions, 0 failures
```

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/(PdfNamedDestination.*|PdfLinkAnnotationNameTreeLimitsBoundaryCurrentBase|PdfOutlineExtractor|PdfOutlineMetadataDestination.*)Test\.php$' | sort)
Focused test run: 49 selected test files (root lock skipped)
49 test files, 1795 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-named-destination-disjoint-kid-limits-currentbase.php
```

The smoke exits 0 and emits `disjoint_child_pruned=true`, `metadata_order_matches_review_order=true`, `valid_named_link_promoted=true`, `disjoint_named_link_unresolved=true`, `uri_link_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `2825 -> 2827`.
- `wordpressScenarios`: `2372 -> 2373`.
- `pdfNamedDestinationExtractorCurrentBaseBehaviors`: `3 -> 4`.
- `mappedPdfNamedDestinationExtractorCurrentBaseBehaviors`: `3 -> 4`.
- New focused file: `PdfNamedDestinationDisjointKidLimitsBoundaryCurrentBaseTest.php` adds 2 PASS cases and 32 assertions.
- New WordPress smoke: `wordpress-pdf-named-destination-disjoint-kid-limits-currentbase.php`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, tokenizer, generation-aware resolver, name-tree `/Limits` parser, page-tree indexer, destination normalizer, metadata extractor, action review extractor, outline extractor, link span promotion, text extractor, and WordPress smoke renderer. Full upstream runner parity remains gated by pdftext, pypdfium2/PDFium, Surya/Torch OCR/layout/table models, Texify equation recognition, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed for this bounded no-GPU PHP slice.

## Non-Overlap

This does not repeat accepted direct `/Limits` pruning, malformed leaf/intermediate `/Limits` fallback, partial child-limit ordering, kid `/Limits` ordering, duplicate leaf ordering, PDFDocEncoding byte comparisons, indirect `/Kids`/`/Names` arrays, generation-exact destination dictionaries/page refs, action dictionary rejection, view-mode validation, xref/object-stream recovery, PageLabels number-tree behavior, link rectangle geometry, outline destination action context, metadata root selection, attachments, fonts, images, stream filters, tables, or runtime conversion behavior. The bounded behavior is only pruning valid internal child destination name-tree `/Limits` ranges that are disjoint from inherited parent bounds before nested `/Kids` traversal.
