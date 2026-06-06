# markerPDF PageLabels root reversed Limits boundary

## Source truth

- Upstream markerPDF extracts searchable PDF content page-by-page before model execution; native PHP `/PageLabels` remains review/page-break metadata in the no-GPU scope.
- PDF `/PageLabels` is a catalog number tree keyed by zero-based physical page indexes. `/Limits` declares the lower and upper key range for a node. A node whose own limits resolve to a reversed two-integer range has no trustworthy number-tree range and should not seed WordPress page labels.
- Existing lane source already rejected reversed `/Limits` on child nodes. This slice applies the same fail-closed boundary to the root `/PageLabels` node before a later valid duplicate catalog `/PageLabels` tree is considered.

## Implementation

- `PdfTextExtractor::pageLabelNumberTreeEntries()` now returns no sections when the current number-tree dictionary has exactly reversed integer `/Limits`.
- `MarkerAppPreview::pageLabelSections()` mirrors the same guard so preview summaries and page image plans stay aligned with native text extraction.
- Added a focused fixture where catalog `/PageLabels 20 0 R` has `/Limits [3 0]` and stale labels, followed by valid `/PageLabels 30 0 R`.
- Added a WordPress smoke that emits page-break metadata for `Cover-`, `Body 4`, and `App-Z` while proving stale root-reversed labels are excluded.

## Verification

Red-first before source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsRootReversedLimitsCurrentBaseTest.php
FAIL skips reversed root PageLabels Limits before later valid catalog label tree
Actual: ["stale-root-77","stale-body-88","stale-body-89"]
1 test files, 1 assertions, 1 failures
```

After source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsRootReversedLimitsCurrentBaseTest.php
PASS skips reversed root PageLabels Limits before later valid catalog label tree
1 test files, 12 assertions, 0 failures
```

Adjacent PageLabels/preview family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabels*CurrentBaseTest.php lanes/markerpdf/tests/MarkerAppPreviewTest.php
18 test files, 551 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-root-reversed-limits-currentbase.php
```

The smoke emits `reversed_root_limits_rejected=true`, `later_valid_catalog_labels_selected=true`, `stale_reversed_root_labels_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and diff checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php && php -l lanes/markerpdf/src/MarkerAppPreview.php && php -l lanes/markerpdf/tests/PdfPageLabelsRootReversedLimitsCurrentBaseTest.php && php -l lanes/markerpdf/examples/wordpress-pdf-page-labels-root-reversed-limits-currentbase.php
No syntax errors detected in all changed PHP files

php -r '$path="lanes/markerpdf/lane-status.json"; json_decode(file_get_contents($path), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, json_last_error_msg().PHP_EOL); exit(1); } echo "lane-status json ok\n";'
lane-status json ok

git diff --check -- lanes/markerpdf
No output
```

## Status delta

- Focused PASS cases: `+1`
- Focused assertions: `+12`
- WordPress scenarios: `+1`
- Root harness: not run - isolated micro-slice

## Non-overlap

This does not repeat accepted PageLabels direct number-tree extraction, indirect `/Kids`, direct kid dictionaries, inherited/local/indirect/malformed extra-operand `/Limits`, reversed child `/Limits`, no-limits kid source order, same-lower source-order preservation, malformed same-lower contribution guards, duplicate `/Nums` keys, duplicate catalog `/PageLabels` with malformed scalar operands, duplicate `/Nums` dictionary keys, direct/indirect null resets, descending or out-of-range `/Nums` keys, indirect scalar operands, generation-exact dictionaries/arrays/keys, object-stream PageLabels, escaped names, PDFDocEncoding prefixes, trailer `/Root` selection, encrypted preview fallback, viewer-preference composition, outline page-label propagation, page transition/action review, annotations, forms, security, image/filter, font/CMap, or supplied table/equation behavior. The bounded behavior is only a root `/PageLabels` number-tree dictionary whose own `/Limits` resolves to a reversed integer range before a later valid duplicate catalog PageLabels operand.

## Dependency closure

No new support component is needed. This reuses the native PDF object scanner, dictionary/array tokenizer, PageLabels number-tree parser, MarkerAppPreview inventory path, and WordPress block smoke path. Full upstream markerPDF model/PDFium runner parity remains intentionally out of scope under the current no-GPU/no-live-model direction.
