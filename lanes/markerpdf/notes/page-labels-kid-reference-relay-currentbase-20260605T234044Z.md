# markerPDF PageLabels Kid reference relay boundary

Micro-slice: `markerpdf-page-labels-boundary-current-base-20260605T234044Z`

Accepted base: `f21be460e1ed7cf1f642983d23d5e3353410f92e`

## Source truth

- Upstream markerPDF extracts searchable PDF text page-by-page before model/OCR work; native PHP keeps catalog `/PageLabels` as page-break and preview metadata aligned to those physical page boundaries.
- PDF `/PageLabels` is a number tree. `/Kids` entries are indirect node references, and indirect references carry object number plus generation. This slice keeps the native parser generation-exact when a kid object body is itself a relay reference to the actual node.

## Implementation

- `PdfTextExtractor::pageLabelKidDictionaryNodes()` now resolves a referenced `/Kids` object body through the existing PageLabels dictionary resolver instead of accepting only a directly embedded dictionary body.
- The referenced kid object is marked seen before resolving the relay, so cycles and stale same-object generations remain fail-closed.
- Added `PdfPageLabelsKidReferenceRelayCurrentBaseTest.php` and `wordpress-pdf-page-labels-kid-reference-relay-currentbase.php`.

## Red-first evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsKidReferenceRelayCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL resolves PageLabels Kid reference relay before WordPress page metadata (lanes/markerpdf/tests/PdfPageLabelsKidReferenceRelayCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'Front iv',
  1 => 'Body 7',
  2 => 'App-Z',
)
Actual: array (
  0 => '1',
  1 => '2',
  2 => 'App-Z',
)

1 test files, 1 assertions, 1 failures
```

## Focused verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsKidReferenceRelayCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS resolves PageLabels Kid reference relay before WordPress page metadata

1 test files, 14 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabels*Test.php
Focused test run: 11 selected test files (root lock skipped)
...
11 test files, 371 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-kid-reference-relay-currentbase.php
```

The smoke emits `page_labels=["Front iv","Body 7","App-Z"]`, `preview_page_labels=["Front iv","Body 7","App-Z"]`, `selected_preview_page_label="App-Z"`, `relay_kid_reference_imported=true`, `stale_relay_generation_rejected=true`, `stale_target_generation_rejected=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status delta

- PHP behavior tests tracked: `2044 -> 2045`
- `phpPass`: `2279 -> 2280`
- WordPress scenarios: `1958 -> 1959`
- `mappedMarkerAppPreviewPageLabelsCurrentBaseBehaviors`: `5 -> 6`
- Root harness: not run - isolated micro-slice.

## Non-overlap

This does not repeat accepted PageLabels direct number-tree extraction, direct kid dictionaries, indirect `/Kids` arrays, inherited/local/indirect/malformed `/Limits`, same-lower source-order preservation, malformed same-lower contribution guards, duplicate `/Nums` keys, duplicate catalog `/PageLabels`, duplicate `/Nums` dictionary keys, descending or out-of-range `/Nums` keys, indirect scalar operands, generation-exact dictionaries/arrays/keys, object-stream PageLabels, escaped names, PDFDocEncoding prefixes, malformed dictionary/array object tails, malformed string value ordering, trailer `/Root` selection, encrypted preview fallback, viewer-preference composition, outline page-label propagation, or page transition/action review. The bounded behavior is only `/PageLabels /Kids` entries whose referenced object body relays to another generation-exact indirect node dictionary.

## Dependency closure

No new support component is needed. This reuses the native PDF object scanner, generation-indexed object body table, top-level dictionary/array tokenizer, PageLabels number-tree parser, MarkerAppPreview summary path, and WordPress block smoke renderer. Full upstream markerPDF model/PDFium runner parity remains intentionally gated by the current no-GPU/no-live-model scope.
