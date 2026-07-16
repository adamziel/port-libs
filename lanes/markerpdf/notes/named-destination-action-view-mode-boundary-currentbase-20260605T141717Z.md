# markerPDF Named-Destination Action View-Mode Boundary

Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260605T141717Z`
Session: `port-dev-markerpdf-named-destinations-20260605T141717Z`
Base accepted HEAD: `ecd1b761b52dbc5a61bfd1d229f03aa92b48947e`

## Source Truth

Upstream markerPDF at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` carries searchable-PDF navigation metadata through the PDF parsing boundary before model/OCR stages. Under the no-GPU markerPDF scope, this slice maps the native PDF explicit-destination boundary used by catalog `/Names /Dests` and Link annotation local destination review.

PDF explicit destination arrays must use a valid view name such as `/Fit`, `/FitH`, `/FitR`, `/FitV`, `/XYZ`, or their bounding-box variants. The standalone named-destination extractor already rejected invalid view modes. The missing current-base boundary was the action-review destination map used for Link annotation `/Dest` and `/A /GoTo` promotion.

No Python, pdftext, pypdfium/PDFium, OCR, Surya, Texify, Torch, Streamlit/FastAPI model worker, browser, or external PDF tool execution was used.

## Behavior

- `PdfActionReviewExtractor` now rejects explicit local destination arrays unless the second operand resolves to a valid PDF destination view name.
- Invalid named destinations such as `[page /Launch 77]`, `[page /RichMedia 88]`, and GoTo dictionaries wrapping `[page /Movie 99]` no longer become `local-destination` actions.
- Malformed `/GoTo` action dictionaries still remain review-only as `unsupported-action-review`, preserving the lane's non-executing action-review policy.
- Valid named destinations and safe URI Link annotations still promote to WordPress spans.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationActionViewModeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects invalid named-destination view modes before annotation action review
Expected actions: [["local-destination"],[],[],["review-uri"]]
Actual actions: [["local-destination"],["local-destination"],["local-destination"],["review-uri"]]
FAIL keeps invalid named-destination view modes out of link promotion and visible WordPress text
Expected promoted link objects: [7,10]
Actual promoted link objects: [7,8,9,10]
1 test files, 9 assertions, 2 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationActionViewModeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects invalid named-destination view modes before annotation action review
PASS keeps invalid named-destination view modes out of link promotion and visible WordPress text
1 test files, 43 assertions, 0 failures
```

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/(PdfNamedDestination|PdfLinkAnnotation|PdfAnnotationLink|PdfPageAnnotationWidgetLink|PdfPageWidgetFieldActionLink|PdfAnnotationExtractor|PdfMarkupAnnotationExtractor).*Test\.php$' | sort)
Focused test run: 54 selected test files (root lock skipped)
54 test files, 2056 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-named-destination-action-view-mode-boundary-currentbase.php
```

The smoke emits `destination_names=["Valid Target","LegacyOk"]`, `promoted_link_objects=[7,10]`, `invalid_view_destinations_rejected=true`, `invalid_view_links_rejected=true`, `unsupported_goto_reviewed_without_promotion=true`, `safe_uri_link_preserved=true`, `valid_named_destination_promoted=true`, `visible_text_excludes_destination_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1903 -> 1905`.
- `wordpressScenarios`: `1721 -> 1722`.
- New focused file: `PdfNamedDestinationActionViewModeBoundaryCurrentBaseTest.php` adds 2 PASS cases and 43 assertions.
- New WordPress smoke: `wordpress-pdf-named-destination-action-view-mode-boundary-currentbase.php`.

## Non-Overlap

This does not repeat standalone named-destination view-mode filtering, name-tree byte `/Limits`, byte-limit link promotion, name-key rejection, action-dictionary filtering by `/S`, primary Link `/A` scalar/array rejection, generation-exact destination resolution, page-only destinations, page-operand validation, object-stream/xref repair, trailer-root selection, outline destination enrichment, annotation URI safety, PageLabels, metadata, attachments, fonts/CMaps, image filters, or supplied table/equation behavior. The bounded behavior is only action-review and Link promotion rejection for named destinations whose explicit destination array has an invalid view-mode operand.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF tokenizer, object/reference resolver, page indexer, action review extractor, named-destination extractor, annotation/link promotion path, text extractor, and WordPress smoke renderer. GPU/model/OCR/PDFium/PIL execution remains intentionally outside the current markerPDF no-GPU lane.

## Next Task

Continue non-overlapping native markerPDF work around searchable-PDF fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, security preflight, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
