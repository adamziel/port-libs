# markerPDF Named Destinations Leaf Order Boundary

Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260606T172053Z`
Session: `port-dev-markerpdf-named-destinations-20260606T172053Z`
Base accepted HEAD: `1eadbc21a9035a80b42c4cd6fea8780a0e3f7c72`

## Source Truth

Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` carries searchable PDF navigation metadata through pdftext/PDFium before OCR/model handoff. Under the current no-GPU markerPDF scope, this slice maps a native PDF name-tree boundary for catalog `/Names /Dests`: a leaf can contain duplicate string keys, and the later duplicate value should win without leaving the review row at the stale first physical insertion position.

No OCR, Surya, Texify, Torch, Python model worker, pypdfium/PDFium, browser, or external PDF tool execution was used.

## Behavior

- `PdfNamedDestinationExtractor`, `PdfMetadataExtractor`, and `PdfActionReviewExtractor` now collect valid duplicate-key leaf rows, sort those duplicate-containing leaf rows by source bytes, and keep same-key source order stable.
- Later duplicate destination values still win, so a later `(Alpha Start)` destination can replace a stale earlier `(Alpha Start)` target.
- Normal non-duplicate leaf source order is preserved, matching existing accepted named-destination tests.
- Destination labels, outline labels, and URI/action target text remain review metadata and do not leak into visible WordPress paragraphs.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationLeafOrderBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL orders leaf destination name pairs by source bytes before WordPress review metadata
Expected: Alpha Start, Middle Review, Zulu Appendix, LegacyTail
Actual: Zulu Appendix, Alpha Start, Middle Review, LegacyTail
PASS preserves duplicate destination targets without leaking leaf names into WordPress text
1 test files, 23 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationLeafOrderBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS orders duplicate-key leaf destination name pairs by source bytes before WordPress review metadata
PASS preserves duplicate destination targets without leaking leaf names into WordPress text
1 test files, 38 assertions, 0 failures
```

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/(PdfNamedDestination.*|PdfLinkAnnotationNameTreeLimitsBoundaryCurrentBase)Test\.php$' | sort)
Focused test run: 40 selected test files (root lock skipped)
40 test files, 1166 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-named-destination-leaf-order-currentbase.php
Emits review_order_from_source_bytes=[Alpha Start, Middle Review, Zulu Appendix, LegacyTail], duplicate_name_later_target_preserved=true, visible_text_excludes_destination_labels=true, executes_python_or_models=false, and executes_external_pdf_tools=false.
```

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `2621 -> 2623`.
- `wordpressScenarios`: `2220 -> 2221`.
- `pdfNamedDestinationExtractorCurrentBaseBehaviors`: `3 -> 4`.
- `mappedPdfNamedDestinationExtractorCurrentBaseBehaviors`: `3 -> 4`.
- New focused file: `PdfNamedDestinationLeafOrderBoundaryCurrentBaseTest.php` adds 2 PASS cases and 38 assertions.
- New WordPress smoke: `wordpress-pdf-named-destination-leaf-order-currentbase.php`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, tokenizer, generation-aware resolver, name-tree `/Limits` parser, page-tree indexer, destination normalizer, metadata extractor, action review extractor, outline extractor, link span promotion, text extractor, and WordPress smoke renderer. Full upstream runner parity remains gated by pdftext, pypdfium2/PDFium, Surya/Torch OCR/layout/table models, Texify equation recognition, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed for this bounded no-GPU PHP slice.

## Non-Overlap

This does not repeat accepted named-destination direct `/Limits` pruning, malformed leaf/intermediate `/Limits` fallback, indirect `/Kids`/`/Names`/`/Limits` arrays, child `/Kids` ordering by `/Limits`, duplicate keys across separate child nodes, PDFDocEncoding byte comparisons, indirect view operands, PDF name-key rejection, page-operand validation, non-GoTo action dictionary rejection, destination view-mode validation, generation-exact destination dictionaries/page refs, object-stream recovery, trailer-root selection, xref-selected duplicate body selection, outline destination action context, PageLabels number-tree ordering, link rectangle geometry, metadata root selection, attachments, fonts, images, stream filters, tables, or runtime conversion behavior. The bounded behavior is only duplicate-key leaf row ordering before document-destination and link-review insertion.
