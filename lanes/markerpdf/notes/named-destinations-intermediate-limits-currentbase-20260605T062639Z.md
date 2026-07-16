# markerPDF Named Destinations Intermediate Limits Boundary

Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260605T062639Z`
Session: `port-dev-markerpdf-named-destinations-20260605T062639Z`
Base accepted HEAD: `6ee1d479779753db2f4fef2352c1751e9f674aff`

## Source Truth

Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` carries PDF navigation metadata through the parser/converter boundary before OCR/model handoff. Under the current no-GPU markerPDF scope, this slice maps a native PDF name-tree boundary for catalog `/Names /Dests`: `/Limits` are subtree bounds, but malformed reversed or disjoint intermediate `/Kids` node limits should not poison all valid descendant names when a parent inherited range is available.

No OCR, Surya, Texify, Torch, Python model worker, pypdfium/PDFium, browser, or external PDF tool execution was used.

## Behavior

- `PdfNamedDestinationExtractor` now treats reversed node `/Limits` values as malformed instead of passing them down to child nodes.
- Intersected child/parent limits that become disjoint fall back to the inherited parent range.
- Valid descendant destination names under a malformed intermediate `/Kids` node are recovered.
- Destination labels outside the inherited range remain excluded from WordPress review metadata and visible text.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationIntermediateLimitsCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL falls back to inherited name-tree limits through malformed intermediate Kids nodes
Expected: Current Start, Review Summary, Summary Appendix, LegacyOnly
Actual: LegacyOnly
PASS keeps out-of-range intermediate name-tree labels out of WordPress text and review metadata
1 test files, 10 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationIntermediateLimitsCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS falls back to inherited name-tree limits through malformed intermediate Kids nodes
PASS keeps out-of-range intermediate name-tree labels out of WordPress text and review metadata
1 test files, 18 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationExtractorTest.php lanes/markerpdf/tests/PdfNamedDestinationActionDictionaryBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationGenerationBodyCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationIndirectArraysCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationIntermediateLimitsCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationKidGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationLimitsFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationNameKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationPageOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationTrailerRootBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationViewModeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfNamedDestinationXrefOffsetBoundaryCurrentBaseTest.php
Focused test run: 13 selected test files (root lock skipped)
33 PASS cases
13 test files, 302 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-named-destination-intermediate-limits-currentbase.php
Emits destination_count=4, destination_names=[Current Start, Review Summary, Summary Appendix, LegacyOnly], intermediate_reversed_limits_fallback=true, out_of_range_destination_names_filtered=true, visible_text_excludes_destination_names=true, executes_python_or_models=false, and executes_external_pdf_tools=false.
```

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1519 -> 1521`.
- `wordpressScenarios`: `1420 -> 1421`.
- `pdfNamedDestinationExtractorCurrentBaseBehaviors`: `3 -> 4`.
- `mappedPdfNamedDestinationExtractorCurrentBaseBehaviors`: `3 -> 4`.
- New focused file: `PdfNamedDestinationIntermediateLimitsCurrentBaseTest.php` adds 2 PASS cases and 18 assertions.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, tokenizer, generation-exact resolver, name-tree walker, page-tree indexer, destination normalizer, text extractor, and WordPress smoke renderer. Full upstream runner parity remains gated by pdftext, pypdfium2/PDFium, Surya/Torch OCR/layout/table models, Texify equation recognition, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed for this bounded no-GPU PHP slice.

## Non-Overlap

This does not repeat accepted named-destination direct `/Limits` pruning, malformed leaf `/Limits` fallback, indirect `/Kids`/`/Names`/`/Limits` arrays, PDFDocEncoding string keys, indirect view operands, PDF name-key rejection, page-operand validation, non-GoTo action dictionary rejection, destination view-mode validation, generation-exact destination dictionaries/page refs, object-stream recovery, trailer-root selection, xref-selected duplicate body selection, outline destination action context, PageLabels, xref repair, metadata, attachment, font, image/filter, or Type3 behavior. The bounded behavior is only invalidating malformed intermediate `/Kids` node limits before descendant name-tree traversal.

## Next Task

Continue with non-overlapping native searchable-PDF behavior under the no-GPU scope: metadata, annotations, forms, xref repair, page geometry, image/filter review, font/CMap widths, supplied table/equation boundaries, or remaining runtime review behavior.
