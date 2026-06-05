# markerPDF Named Destinations Kid Limits Order Boundary

Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260605T230221Z`
Session: `port-dev-markerpdf-named-destinations-20260605T230221Z`
Base accepted HEAD: `13d069769033a9b5e2cc2577f3200aec1f8fed06`

## Source Truth

Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` carries PDF navigation metadata through pdftext/PDFium before OCR/model handoff. Under the current no-GPU markerPDF scope, this slice maps the native PDF name-tree boundary for catalog `/Names /Dests`: child `/Kids` nodes are logically ordered by their `/Limits`, so stale physical child-array order must not reorder WordPress document-destination review rows.

No OCR, Surya, Texify, Torch, Python model worker, pypdfium/PDFium, browser, or external PDF tool execution was used.

## Behavior

- `PdfNamedDestinationExtractor` now orders fully bounded destination name-tree child `/Kids` by effective child `/Limits` lower bytes before collecting destination rows.
- `PdfMetadataExtractor` uses the same bounded child ordering for `document_destinations`, so metadata review names match the standalone named-destination extractor.
- If any child lacks valid local/effective limits, traversal preserves the original source order as a fail-safe.
- Same-lower sibling limits preserve source order, matching the existing PageLabels boundary behavior.
- Destination names remain review metadata only and are not promoted into visible WordPress paragraph text.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationKidLimitsOrderBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL orders destination name-tree kids by Limits before WordPress destination review metadata
Expected: Alpha Start, Deck Body, Review Summary, Same Lower Current, Same Lower Narrow, Zulu Appendix, LegacyTail
Actual: Zulu Appendix, Review Summary, Alpha Start, Deck Body, Same Lower Current, Same Lower Narrow, LegacyTail
PASS keeps name-tree destination labels out of visible WordPress text after kid reordering
1 test files, 11 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationKidLimitsOrderBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS orders destination name-tree kids by Limits before WordPress destination review metadata
PASS keeps name-tree destination labels out of visible WordPress text after kid reordering
1 test files, 25 assertions, 0 failures
```

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfNamedDestination.*Test\.php$' | sort)
Focused test run: 32 selected test files (root lock skipped)
32 test files, 888 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfMetadataXmpOutputIntentNameTreeCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationKidLimitsOrderBoundaryCurrentBaseTest.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 921 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-named-destination-kid-limits-order-currentbase.php
Emits metadata_order_matches_review_order=true, same_lower_source_order_preserved=true, visible_text_excludes_destination_labels=true, executes_python_or_models=false, and executes_external_pdf_tools=false.
```

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `2261 -> 2263`.
- `wordpressScenarios`: `1945 -> 1946`.
- `pdfNamedDestinationExtractorCurrentBaseBehaviors`: `3 -> 4`.
- `mappedPdfNamedDestinationExtractorCurrentBaseBehaviors`: `3 -> 4`.
- New focused file: `PdfNamedDestinationKidLimitsOrderBoundaryCurrentBaseTest.php` adds 2 PASS cases and 25 assertions.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, tokenizer, generation-exact resolver, name-tree `/Limits` parser, page-tree indexer, destination normalizer, metadata extractor, text extractor, and WordPress smoke renderer. Full upstream runner parity remains gated by pdftext, pypdfium2/PDFium, Surya/Torch OCR/layout/table models, Texify equation recognition, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed for this bounded no-GPU PHP slice.

## Non-Overlap

This does not repeat accepted named-destination direct `/Limits` pruning, malformed leaf/intermediate `/Limits` fallback, indirect `/Kids`/`/Names`/`/Limits` arrays, PDFDocEncoding byte comparisons, indirect view operands, PDF name-key rejection, page-operand validation, non-GoTo action dictionary rejection, destination view-mode validation, generation-exact destination dictionaries/page refs, object-stream recovery, trailer-root selection, xref-selected duplicate body selection, duplicate name-tree key behavior, outline destination action context, PageLabels number-tree ordering, xref repair, metadata root selection, attachment, font, image/filter, table, or Type3 behavior. The bounded behavior is only ordering valid destination name-tree child nodes by effective `/Limits` before document-destination review.
