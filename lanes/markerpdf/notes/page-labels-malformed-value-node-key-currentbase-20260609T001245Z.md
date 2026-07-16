# markerPDF PageLabels malformed value node-key boundary

## Source truth

- Upstream markerPDF treats searchable PDF text as page-local content before model/OCR stages; under this lane's no-GPU scope, native PHP `/PageLabels` remains review/page-break metadata aligned to those physical pages.
- PDF `/PageLabels` is a catalog number tree keyed by zero-based page index. Its `/Nums` values are page-label dictionaries with `/P`, `/S`, and `/St`; real malformed PDFs can contain trailing-operand junk after dictionary keys, and this lane's existing duplicate-key policy skips such unusable operands before selecting the first usable value.

## Implementation

- `PdfTextExtractor::pageLabelDictionaryHasNumberTreeKeys()` now treats `/Nums`, `/Kids`, and `/Limits` as value-dictionary node keys only when the top-level entry has no trailing operand.
- `MarkerAppPreview::pageLabelDictionaryHasNumberTreeKeys()` applies the same boundary so preview inventory, `pageLabels()`, and `getPageImagePlan()` stay aligned with native text extraction.
- Added a focused fixture where label value dictionaries contain malformed `/Nums ... 77`, `/Kids ... 88`, and `/Limits ... 99` decoys before valid `/P`, `/S`, and `/St` operands. The stale nested labels remain excluded.

## Red first

`php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsMalformedValueNodeKeyBoundaryCurrentBaseTest.php`

Failed before the source change:

```text
Expected: array (
  0 => 'Cover-',
  1 => 'Body 4',
  2 => 'App-Z',
)
Actual: array (
  0 => '1',
  1 => '2',
  2 => '3',
)

1 test files, 1 assertions, 1 failures
```

## Verification

`php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsMalformedValueNodeKeyBoundaryCurrentBaseTest.php`

```text
PASS keeps PageLabels value dictionaries with malformed node-key operands before WordPress metadata
1 test files, 20 assertions, 0 failures
```

`php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsMalformedValueNodeKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageLabelsValueNodeKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageLabelsTopLevelOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageLabelsDuplicateTypeKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageLabelsDuplicateScalarOperandBoundaryCurrentBaseTest.php`

```text
5 test files, 79 assertions, 0 failures
```

`php lanes/markerpdf/examples/wordpress-pdf-page-labels-malformed-value-node-key-currentbase.php`

Exits 0 and reports `page_labels=["Cover-","Body 4","App-Z"]`, `selected_preview_page_label="App-Z"`, `stale_nested_labels_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness not run - isolated micro-slice.

## Non-overlap

This does not repeat accepted PageLabels direct number-tree extraction, indirect `/Kids`, direct kid dictionaries, inherited/local/indirect/malformed/reversed/negative `/Limits`, no-limits kid source order, same-lower source-order preservation, malformed same-lower contribution guards, duplicate `/Nums`, `/Kids`, `/Limits`, `/Type`, or catalog `/PageLabels` keys, duplicate scalar `/P` `/S` `/St` operands, duplicate malformed values, valid node-shaped value dictionaries, descending/out-of-range keys, null resets, indirect scalar operands, generation-exact dictionaries/arrays/keys, object-stream PageLabels, escaped names, PDFDocEncoding/UTF-16/UTF-8 prefixes, selected trailer-root behavior, encrypted preview fallback, viewer preferences, outline page-label propagation, or page transition/action review. The bounded behavior is only malformed trailing-operand number-tree keys inside otherwise valid PageLabel value dictionaries.

## Dependency closure

No new support component is needed. This reuses the native PDF dictionary tokenizer, PageLabels number-tree parser, `PdfTextExtractor`, `MarkerAppPreview`, and the WordPress block smoke path. Full upstream markerPDF model/PDFium parity remains intentionally out of scope under the current no-GPU/no-live-model direction.
