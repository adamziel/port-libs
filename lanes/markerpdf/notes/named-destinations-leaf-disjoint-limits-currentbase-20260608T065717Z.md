# markerPDF Named Destinations Leaf Disjoint Limits Boundary

Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260608T065717Z`
Session: `port-dev-markerpdf-named-destinations-20260608T065717Z`
Base accepted HEAD: `ffcd9253ba667545698caf23a94d2a208517e323`

## Source Truth

Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` carries searchable PDF navigation metadata through pdftext/PDFium before OCR/model handoff. Under the current no-GPU markerPDF scope, this slice maps the native PDF name-tree boundary for catalog `/Names /Dests`: child `/Limits` constrain which leaf rows are trusted before WordPress destination metadata and link promotion.

No OCR, Surya, Texify, Torch, Python model worker, pypdfium/PDFium, browser, or external PDF tool execution was used.

## Behavior

- `PdfNamedDestinationExtractor`, `PdfMetadataExtractor`, `PdfOutlineExtractor`, and `PdfActionReviewExtractor` now prune a name-tree leaf when its valid local `/Limits` are disjoint from inherited parent `/Limits` and none of its local name pairs fall inside its own range.
- Existing inherited-limit fallback remains intact for malformed leaves that still contain a local pair inside their own `/Limits`; this preserves accepted recovery behavior for `PdfNamedDestinationLimitsFallbackCurrentBaseTest.php` and `PdfNamedDestinationOutlineLimitsFallbackCurrentBaseTest.php`.
- Disjoint leaf destinations no longer become document-destination metadata, outline/action review targets, annotation `/Dest` rows, or WordPress span links.
- Destination labels, outline labels, and URI/action operands remain review-only and do not leak into visible WordPress paragraphs.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationLeafDisjointLimitsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL prunes disjoint leaf destination Limits before WordPress destination metadata
Expected: Alpha Live, LegacyTail
Actual: Alpha Live, Review Live, LegacyTail
FAIL keeps disjoint leaf destination rows out of annotation promotion and visible WordPress text
Expected annotation objects: [9]
Actual annotation objects: [7, 9]
1 test files, 3 assertions, 2 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationLeafDisjointLimitsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationLimitsFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationOutlineLimitsFallbackCurrentBaseTest.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 70 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestination*.php
Focused test run: 55 selected test files (root lock skipped)
55 test files, 1763 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-named-destination-leaf-disjoint-limits-currentbase.php
Emits disjoint_leaf_destination_pruned=true, decoy_named_links_unresolved=true, uri_link_preserved=true, visible_text_excludes_destination_labels=true, executes_python_or_models=false, and executes_external_pdf_tools=false.
```

```text
php -l lanes/markerpdf/src/PdfNamedDestinationExtractor.php
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/src/PdfOutlineExtractor.php
php -l lanes/markerpdf/src/PdfActionReviewExtractor.php
php -l lanes/markerpdf/tests/PdfNamedDestinationLeafDisjointLimitsBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-named-destination-leaf-disjoint-limits-currentbase.php
All reported no syntax errors.
```

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `2952 -> 2954`.
- `wordpressScenarios`: `2453 -> 2454`.
- `pdfNamedDestinationExtractorCurrentBaseBehaviors`: `3 -> 4`.
- `mappedPdfNamedDestinationExtractorCurrentBaseBehaviors`: `3 -> 4`.
- New focused file: `PdfNamedDestinationLeafDisjointLimitsBoundaryCurrentBaseTest.php` adds 2 PASS cases and 33 assertions.
- New WordPress smoke: `wordpress-pdf-named-destination-leaf-disjoint-limits-currentbase.php`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, tokenizer, generation-aware resolver, name-tree `/Limits` parser, page-tree indexer, destination normalizer, metadata extractor, outline/action review extractors, link span promotion, text extractor, and WordPress smoke renderer. Full upstream runner parity remains gated by pdftext, pypdfium2/PDFium, Surya/Torch OCR/layout/table models, Texify equation recognition, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed for this bounded no-GPU PHP slice.

## Non-Overlap

This does not repeat accepted named-destination direct `/Limits` pruning, malformed leaf/intermediate `/Limits` fallback, overlong/reversed root limits, disjoint internal `/Kids` limits, indirect `/Kids`/`/Names`/`/Limits` arrays, child `/Kids` ordering by `/Limits`, duplicate keys across separate child nodes, duplicate-key leaf row ordering, PDFDocEncoding byte comparisons, indirect view operands, PDF name-key rejection, page-operand validation, non-GoTo action dictionary rejection, destination view-mode validation, generation-exact destination dictionaries/page refs, object-stream recovery, trailer-root selection, xref-selected duplicate body selection, outline destination action context, PageLabels number-tree ordering, link rectangle geometry, metadata root selection, attachments, fonts, images, stream filters, tables, or runtime conversion behavior. The bounded behavior is only leaf-node disjoint local `/Limits` with no locally matching key before destination metadata and link promotion.
