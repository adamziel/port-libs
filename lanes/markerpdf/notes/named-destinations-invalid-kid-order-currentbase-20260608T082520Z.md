# markerPDF Named Destinations Invalid Kids Order Boundary

Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260608T082520Z`
Session: `port-dev-markerpdf-named-destinations-20260608T082520Z`
Base accepted HEAD: `6c29be4bda70f43b52fe8fb02b6dc807643e8db3`

## Source Truth

Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` carries searchable-PDF navigation metadata through pdftext/PDFium before OCR/model handoff. Under the current no-GPU markerPDF scope, this slice maps the native PDF name-tree boundary for catalog `/Names /Dests`: malformed `/Kids` entries are skipped as invalid child nodes, but they must not disable `/Limits` ordering for valid bounded sibling nodes.

No OCR, Surya, Texify, Torch, Python model worker, pypdfium/PDFium, browser, or external PDF tool execution was used.

## Behavior

- `PdfNamedDestinationExtractor`, `PdfActionReviewExtractor`, `PdfMetadataExtractor`, and `PdfOutlineExtractor` now treat invalid `/Kids` entries as unbounded placeholders in the child sorter instead of returning the physical child order.
- Valid bounded child nodes are still sorted by effective `/Limits`, preserving current duplicate-name replacement even when `null`, scalar, direct-dictionary, or unresolved children are interleaved.
- Invalid child dictionaries remain excluded from standalone destination metadata, document metadata, TOC/outline review, annotation action review, link promotion, supplied span links, and visible WordPress text.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationInvalidKidOrderBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL orders valid bounded destination kids even when malformed Kids entries are present
Expected: [A Broad, DuplicateReview, LegacyTail]
Actual: [DuplicateReview, A Broad, LegacyTail]
FAIL keeps invalid-Kids destination rows out of annotation promotion and visible WordPress text
Expected promoted local/URI links on the page; actual named-destination link count was 0.
1 test files, 8 assertions, 2 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationInvalidKidOrderBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS orders valid bounded destination kids even when malformed Kids entries are present
PASS keeps invalid-Kids destination rows out of annotation promotion and visible WordPress text
1 test files, 45 assertions, 0 failures
```

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfNamedDestination.*CurrentBaseTest\.php$|/PdfNamedDestinationExtractorTest\.php$' | sort)
Focused test run: 57 selected test files (root lock skipped)
57 test files, 1853 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php lanes/markerpdf/tests/PdfLinkAnnotationNameTreeLimitsBoundaryCurrentBaseTest.php $(rg --files lanes/markerpdf/tests | rg '/PdfOutline.*NamedDestination.*CurrentBaseTest\.php$|/PdfOutlineMetadataLightweightNamedDestinationBoundaryCurrentBaseTest\.php$' | sort)
Focused test run: 6 selected test files (root lock skipped)
6 test files, 383 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php $(rg --files lanes/markerpdf/tests | rg '/PdfMetadata.*CurrentBaseTest\.php$' | sort)
Focused test run: 75 selected test files (root lock skipped)
75 test files, 4327 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-named-destination-invalid-kid-order-currentbase.php
```

The smoke emits `valid_bounded_kids_sorted_around_invalid_entries=true`, `duplicate_later_target_preserved=true`, `invalid_kid_destinations_rejected=true`, `promoted_link_objects=[7,9]`, `visible_text_excludes_destination_labels=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `2989 -> 2991`.
- `wordpressScenarios`: `2478 -> 2479`.
- `pdfNamedDestinationExtractorCurrentBaseBehaviors`: `3 -> 4`.
- `mappedPdfNamedDestinationExtractorCurrentBaseBehaviors`: `3 -> 4`.
- New focused file: `PdfNamedDestinationInvalidKidOrderBoundaryCurrentBaseTest.php` adds 2 PASS cases and 45 assertions.
- New WordPress smoke: `wordpress-pdf-named-destination-invalid-kid-order-currentbase.php`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, tokenizer, generation-aware resolver, name-tree `/Limits` parser, page-tree indexer, destination normalizer, metadata extractor, action review extractor, outline extractor, link span promotion, text extractor, and WordPress smoke renderer. Full upstream runner parity remains gated by pdftext, pypdfium2/PDFium, Surya/Torch OCR/layout/table models, Texify equation recognition, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed for this bounded no-GPU PHP slice.

## Non-Overlap

This does not repeat accepted named-destination direct `/Limits` pruning, malformed leaf/intermediate `/Limits` fallback, indirect `/Kids`/`/Names`/`/Limits` arrays, valid child `/Kids` ordering by `/Limits`, partial bounded child ordering, kid-reference rejection, duplicate-key leaf row ordering, PDFDocEncoding byte comparisons, UTF-8 BOM decoding, indirect view operands, PDF name-key rejection, page-operand validation, non-GoTo action dictionary rejection, destination view-mode validation, generation-exact destination dictionaries/page refs, object-stream recovery, trailer-root selection, xref-selected duplicate body selection, outline destination action context, PageLabels number-tree ordering, link rectangle geometry, metadata root selection, attachments, fonts, images, stream filters, tables, or runtime conversion behavior. The bounded behavior is only valid bounded destination child ordering when malformed `/Kids` entries are interleaved.
