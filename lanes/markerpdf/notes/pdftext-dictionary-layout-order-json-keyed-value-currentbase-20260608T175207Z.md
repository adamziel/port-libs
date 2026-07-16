# markerPDF pdftext dictionary layout-order JSON keyed-value current-base

Micro-slice: `markerpdf-pdftext-dictionary-layout-order-boundary-current-base-20260608T175207Z`

Accepted base: `196aeee97e13991dc56717436cd4ee56caa47808`

## Source Truth

Upstream markerPDF at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDFs through `marker/pdf/extract_text.py::get_text_blocks()`: it calls `pdftext.extraction.dictionary_output(..., page_range=..., keep_chars=False, ...)`, then enumerates the sliced dictionary pages into Marker page objects. Upstream `marker/layout/layout.py::surya_layout()` and `marker/layout/order.py::surya_order()` zip layout/order predictions with those selected pages. In this no-GPU PHP lane, caller-supplied layout/order artifacts stand in for Surya/PDFium outputs.

## Behavior

Native adapters can cache source-page keyed layout/order/image sidecars as JSON object strings:

```php
[
    '12701' => '{"image_bbox":[0,0,612,792],"bboxes":[...]}',
    '12700' => '{"image_bbox":[0,0,612,792],"bboxes":[...]}',
]
```

`PdfPageArtifactSelector` now decodes artifact-shaped JSON values before source-key map selection so the numeric key remains selector-only page identity. `SuppliedDocumentConverter` now accepts the same shape at the WordPress option boundary instead of rejecting it as a non-array keyed artifact map.

Stale cover-page JSON payloads are excluded before layout annotation, reading-order assignment, metadata serialization, or Gutenberg text output.

## Red/Green Evidence

Pre-fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderJsonKeyedValueBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL preserves source keys while decoding raw JSON keyed order values before selected pdftext assignment
FAIL preserves source keys while decoding raw JSON keyed layout and order values for WordPress imports
1 test files, 3 assertions, 2 failures
```

Post-fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderJsonKeyedValueBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS preserves source keys while decoding raw JSON keyed order values before selected pdftext assignment
PASS preserves source keys while decoding raw JSON keyed layout and order values for WordPress imports
1 test files, 33 assertions, 0 failures
```

Adjacent layout/order family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextDictionaryLayoutOrder*.php lanes/markerpdf/tests/SuppliedDocumentConverterTest.php
Focused test run: 25 selected test files (root lock skipped)
...
25 test files, 2419 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-json-keyed-value-currentbase.php
json_keyed_values_selected=true
stale_json_keyed_value_payload_excluded=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

Syntax/status/diff checks:

```text
php -l lanes/markerpdf/src/PdfPageArtifactSelector.php
php -l lanes/markerpdf/src/SuppliedDocumentConverter.php
php -l lanes/markerpdf/tests/PdfTextDictionaryLayoutOrderJsonKeyedValueBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdftext-dictionary-layout-order-json-keyed-value-currentbase.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status JSON OK\n";'
git diff --check -- lanes/markerpdf
```

All passed.

## Non-Overlap

This does not repeat accepted raw JSON strings under `dictionary_output`/`pages`/`pdftext` artifact envelopes, JSON list-entry artifact decoding, source-keyed array artifact maps, typed JSON envelopes, typed payload-list ambiguity rejection, page-map/pageMap envelopes, metadata sibling exclusion, selected page-range trimming, page marker aliases, duplicate key guards, row-level stale marker filtering, normalized/named/polygon geometry, parser/xref repair, fonts/CMaps/widths, image/filter metadata, annotations/forms/security, table recognition, OCR, or equation/image supplied-boundary work.

The bounded behavior is only raw JSON strings used as values inside source-page keyed supplied layout/order/image maps before selected-page assignment.

## Dependency Closure

No new support component is needed. This reuses the native PHP supplied artifact selector, layout annotator, layout orderer, pdftext dictionary converter, supplied document converter, and WordPress smoke harness. Live OCR, Surya layout/order models, Torch/CUDA, PDFium rendering, pypdfium/PIL, raster image decoding, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.
