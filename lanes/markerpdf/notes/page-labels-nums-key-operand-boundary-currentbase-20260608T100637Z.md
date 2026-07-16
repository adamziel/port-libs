# PageLabels Nums Key Operand Boundary Current Base

Slice: `markerpdf-page-labels-boundary-current-base-20260608T100637Z`
Base: `6bc71cbbbe736a9858bd60708161d8103d8ce185`

## Behavior

Catalog `/PageLabels` `/Nums` arrays are key/value pairs. When a key-like page-index operand is malformed, for example an indirect scalar object that resolves to `0 /Private`, the malformed key consumes its paired value before the parser resynchronizes to the next number-tree key. This prevents a tailed decoy dictionary from being shifted onto the next page label.

The implementation updates both native label paths:

- `PdfTextExtractor::extractPageLabels()` for WordPress text/page-break import.
- `MarkerAppPreview::pageLabels()` and page image metadata.

Missing-generation references and malformed dictionary object tails keep the accepted resync behavior; they are covered by the existing PageLabels family tests.

## Evidence

Red-first:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsNumsKeyOperandBoundaryCurrentBaseTest.php
```

Result before source change: `1 test files, 1 assertions, 1 failures`, with actual labels `['Cover-', 'Shifted-9', 'App-Z']`.

Green:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsNumsKeyOperandBoundaryCurrentBaseTest.php
```

Result after source change: `1 test files, 16 assertions, 0 failures`.

Adjacent family:

```bash
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f -name '*PageLabels*Test.php' | sort)
```

Result: `39 test files, 732 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-page-labels-nums-key-operand-currentbase.php
```

Result: exits `0`, with `page_labels=[Cover-, Body 4, App-Z]`, `selected_preview_page_label=App-Z`, and `shifted_label_rejected=true`.

## Non-Overlap

This does not repeat accepted PageLabels duplicate key, `/Kids`, `/Limits`, null-value, comment, object-stream, generation, missing-generation, dictionary-tail, UTF-16 prefix, or top-level operand slices. The bounded behavior here is only malformed key-like scalar operands inside `/Nums` key/value arrays.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PDF token readers and object-resolution helpers. No Python, OCR, CUDA, model execution, raster rendering, PDF action execution, external PDF tools, or live provider services are involved.
