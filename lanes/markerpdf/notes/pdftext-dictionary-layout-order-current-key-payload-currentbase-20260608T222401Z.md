# markerPDF pdftext dictionary layout/order current-key payload boundary

Slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260608T222401Z`
Base: `1a91e11e37bf1452c01f3630ee84977c3a03b00f`

## Source Truth

- Upstream `sddai/markerPDF` at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` gets searchable-PDF pages from `pdftext.dictionary_output(..., page_range=...)` and then zips one supplied layout/order prediction to each selected Marker page.
- Native no-GPU adapters can cache layout/order sidecars inside typed wrappers such as `layout_result` or `order_result`, with nested `dictionary_output` object maps keyed by the original pdftext page number.
- Exact source-page keys are trusted page identity. A stale unmarked sibling payload in the same nested map must not make the exact current keyed payload ambiguous or leak into WordPress metadata/text.
- This stays in the native no-GPU markerPDF scope: no OCR, Surya/Texify/Torch, pypdfium/PDFium rendering, model workers, action execution, or external PDF tools.

## Implementation

- `LayoutAnnotator::matchingDirectLayoutResultPayloadEnvelopeCandidates()` now separates marker-bearing matches from unmarked fallback matches when resolving nested direct payload envelopes.
- `LayoutOrderer::matchingDirectOrderResultPayloadEnvelopeCandidates()` applies the same rule for supplied order payloads.
- If exactly one current source-keyed nested payload matches, it is selected before stale unmarked siblings. Multiple marker-bearing matches still remain ambiguous and fail closed through the existing path.

## Red-First Evidence

Before the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderCurrentKeyPayloadBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL uses exact current keyed nested order payload before stale unmarked dictionary rows
Expected: First current-key nested payload column, Second current-key nested payload column
Actual: Second current-key nested payload column, First current-key nested payload column
FAIL uses exact current keyed nested layout and order payloads for WordPress imports
Expected: 1
Actual: 0
1 test files, 8 assertions, 2 failures
```

The failing fixture had a typed `order_result`/`layout_result` wrapper with trusted current page metadata. Its nested `dictionary_output` contained one exact current source-key payload plus one stale unmarked payload. The previous matcher treated both as matches and skipped assignment.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderCurrentKeyPayloadBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses exact current keyed nested order payload before stale unmarked dictionary rows
PASS uses exact current keyed nested layout and order payloads for WordPress imports
1 test files, 38 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrder*Test.php
29 test files, 1762 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/LayoutOrdererTest.php lanes/markerpdf/tests/LayoutAnnotatorTest.php
2 test files, 75 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-current-key-payload-currentbase.php
exits 0; emits layout_current_key_selected=true, order_current_key_selected=true, heading_before_body=true, cover_excluded=true, payload_excluded=true, executes_python_or_models=false, executes_external_pdf_tools=false
```

## Dependency Closure

No new support component is needed. This reuses the existing native PHP pdftext dictionary converter, supplied-artifact selector, layout annotator, layout orderer, supplied document converter, Markdown/WordPress finalizer, and focused PHP TestRunner. Live `pdftext`, pypdfium/PDFium rendering, Surya/Torch layout/order/OCR models, Texify, tabled-pdf model execution, Streamlit/FastAPI workers, raster rendering, and external PDF tools remain intentionally out of scope.

## Non-Overlap

This does not repeat accepted selected page-range slicing, sparse keyed map matching, source-key exact maps, page-map/pageMap aliases, decimal keys, direct option envelopes, JSON artifact envelopes, typed JSON payload envelopes, ambiguous unmarked list rejection, page-number alias rejection, metadata sibling filtering, wrapper-list rejection, duplicate key rejection, marker conflict rejection, row-level stale marker filtering, normalized/named/polygon/coordinate-order geometry, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security, OCR, or equation/image supplied-boundary work. The bounded behavior is only exact current keyed nested layout/order payloads inside typed wrappers when stale unmarked sibling payloads are present.

## Next

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser and supplied-boundary behavior, especially fonts, CMaps, stream filters, xref repair, metadata, outlines, annotations, forms, page geometry, ExtGState/image/filter metadata, and remaining table/equation handoff envelope boundaries.
