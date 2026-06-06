# markerPDF Named Destinations Partial Kid Action Review Boundary

Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260606T203644Z`
Session: `port-dev-markerpdf-named-destinations-20260606T203644Z`
Accepted base: `1a04e44c91a22f3d4217b77b07bd40823238f1c6`

## Source Truth

Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` carries PDF navigation/link metadata through the PDF parser/PDFium boundary before OCR or model handoff. Under the no-GPU markerPDF scope, this slice maps the native PDF name-tree boundary for catalog `/Names /Dests`: bounded child nodes are ordered by effective `/Limits` even when another sibling lacks local `/Limits`, so stale broad duplicate destination entries cannot override current exact destination entries in WordPress link or outline review.

No OCR, Surya, Texify, Torch, Python model worker, pypdfium/PDFium, browser, or external PDF tool execution was used.

## Implementation

- `PdfActionReviewExtractor` now sorts bounded destination name-tree `/Kids` by effective lower-limit bytes while preserving unbounded sibling slots instead of returning raw physical order whenever one sibling lacks local `/Limits`.
- `PdfOutlineExtractor` now applies the same partial bounded-kid ordering before collecting destination maps and action-review destination maps.
- `PdfNamedDestinationPartialKidLimitsActionReviewCurrentBaseTest.php` covers the red boundary where a no-`/Limits` sibling previously let a physically later stale broad `/FitH 111` duplicate win link and outline review after the current exact `/XYZ` duplicate.
- `wordpress-pdf-named-destination-partial-kid-action-review-currentbase.php` proves the WordPress path promotes the current named destination to link/outline review while keeping stale duplicate operands and destination labels out of visible text.

## Verification

Red-first focused run before the fix:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationPartialKidLimitsActionReviewCurrentBaseTest.php
```

Result: `1 test files, 13 assertions, 2 failures`.

After the fix:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationPartialKidLimitsActionReviewCurrentBaseTest.php
```

Result: `1 test files, 29 assertions, 0 failures`.

Additional verification for the handoff:

```bash
php lanes/markerpdf/examples/wordpress-pdf-named-destination-partial-kid-action-review-currentbase.php
```

Emits `outline_pages=[1]`, `link_destination_pages=[1]`, `link_view_modes=["XYZ"]`, `stale_broad_duplicate_hidden_from_review=true`, `visible_text_excludes_destination_labels=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- `phpPass`: `2676 -> 2677`.
- `wordpressScenarios`: `2255 -> 2256`.
- `pdfNamedDestinationExtractorCurrentBaseBehaviors`: `3 -> 4`.
- `mappedPdfNamedDestinationExtractorCurrentBaseBehaviors`: `3 -> 4`.
- New focused file: `PdfNamedDestinationPartialKidLimitsActionReviewCurrentBaseTest.php` adds 2 PASS cases and 29 assertions.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object parser, name-tree `/Limits` parser, destination normalizer, outline extractor, action-review extractor, link annotation promoter, text extractor, and WordPress smoke renderer. Full upstream runner parity remains gated by pdftext, pypdfium2/PDFium, Surya/Torch OCR/layout/table models, Texify equation recognition, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed for this bounded no-GPU PHP slice.

## Non-Overlap

This does not repeat accepted named-destination direct `/Limits` pruning, leaf duplicate ordering, full kid `/Limits` ordering, partial kid ordering for standalone metadata, action-dictionary validation, alias-cycle handling, PDFDocEncoding keys, page-operand validation, non-XYZ null-coordinate rejection, object-stream recovery, trailer-root selection, xref repair, outline destination action transition review, PageLabels, annotations/forms/security/image/filter/font/table behavior, or DCTDecode preview-prefix boundaries. The bounded behavior is only partial bounded `/Kids` ordering for destination maps consumed by link action review and outline navigation review.
