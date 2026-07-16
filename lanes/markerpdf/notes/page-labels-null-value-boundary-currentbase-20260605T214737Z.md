# markerPDF PageLabels null value boundary

Micro-slice: `markerpdf-page-labels-boundary-current-base-20260605T214737Z`

## Source Truth

- Upstream markerPDF extracts searchable PDF text page-by-page through `pdftext`/PDFium before OCR/layout/model work; native PHP keeps `/PageLabels` as page-break and preview metadata aligned to physical page text, not visible body text. Source: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- pypdf's page-label reader models `/Nums` as sorted key/value pairs and falls back to the physical page label when the selected value is not a dictionary. Source: https://sources.debian.org/src/pypdf/5.4.0-1/pypdf/_page_labels.py/#L426
- This no-GPU slice stays inside the native parser boundary: no OCR, Surya, Texify, Torch, pypdfium/PDFium execution, PIL rendering, Python runner, or external PDF tools were run.

## Implementation

- `PdfTextExtractor` now treats a direct or indirect `null` PageLabels `/Nums` value as an explicit physical-label section by starting a decimal label range at `page_index + 1`.
- `MarkerAppPreview` mirrors the same fallback behavior in its direct PageLabels parser for preview inventory paths that cannot delegate to `PdfTextExtractor`.
- Added a focused fixture where page 1 uses direct `null` to stop `Front v`, page 3 uses an indirect `null` object to stop `Body 9`, and both import and preview metadata emit physical labels `2` and `4`.

## Evidence

Red probe before source edit:

```text
php -r '<focused PageLabels null probe>'
["Front iv","Front v","App-Z"]
```

Focused null-value test after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsNullValueBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS resets PageLabels ranges on direct and indirect null values before WordPress page metadata
1 test files, 10 assertions, 0 failures
```

Focused PageLabels family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabels*Test.php
Focused test run: 8 selected test files (root lock skipped)
8 test files, 334 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-null-value-currentbase.php
```

The smoke emits `page_labels=["Front iv","2","Body 8","4"]`, `preview_page_labels=["Front iv","2","Body 8","4"]`, `direct_null_resets_to_physical_label=true`, `indirect_null_resets_to_physical_label=true`, `previous_label_ranges_stopped=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Delta

- Focused PHP PASS cases: `2230 -> 2231`
- New focused assertions: `10`
- Focused PageLabels family: `8 test files / 334 assertions / 0 failures`
- WordPress scenarios: `1922 -> 1923`
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted PageLabels direct number-tree extraction, direct or indirect `/Kids`, inherited/local/indirect/malformed `/Limits`, same-lower source-order preservation, malformed same-lower contribution guards, duplicate `/Nums` keys, duplicate catalog `/PageLabels`, duplicate `/Nums` dictionary keys, descending or out-of-range `/Nums` keys, indirect scalar operands, generation-exact dictionaries/arrays/keys, object-stream PageLabels, escaped names, PDFDocEncoding prefixes, malformed dictionary/array object tails, malformed string value ordering, trailer `/Root` selection, viewer-preference composition, outline page-label propagation, or page transition/action review. The bounded behavior is only explicit direct and indirect `null` values in a PageLabels `/Nums` array resetting the active label range to physical page numbers.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, dictionary/array tokenizer, exact-generation object resolver, PageLabels number-tree parser, MarkerAppPreview inventory path, and WordPress block smoke renderer. Full upstream markerPDF model/PDFium runner parity remains intentionally gated by the current no-GPU/no-live-model scope.
