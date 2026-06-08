# markerPDF PageLabels start-value boundary

Slice: `markerpdf-page-labels-boundary-current-base-20260608T233257Z`

Accepted base: `9eb676a5cd9add619cf3b6f2435447962ecbfb04`

## Source Truth

- Upstream markerPDF extracts searchable PDF text page-by-page before OCR/model work; under the current no-GPU scope, native PHP `/PageLabels` stays page-break and preview metadata aligned with physical page text.
- PDFium PageLabel coverage models catalog `/PageLabels` as a number tree whose leaf keys are page indices and whose values are page-label dictionaries with optional `/S`, `/P`, and `/St`. Its fixture comments state `/St` must be at least `1` if specified: https://pdfium.googlesource.com/pdfium.git/+/refs/heads/chromium/7430/core/fpdfdoc/cpdf_pagelabel_unittest.cpp
- pypdf documents page labels as zero-based page-index number-tree entries whose simplest fallback is `index + 1`: https://sources.debian.org/src/pypdf/5.4.0-1/pypdf/_page_labels.py/

## Behavior

- `PdfTextExtractor` now treats a `/St` operand as usable only when it resolves to an integer greater than or equal to `1`.
- `MarkerAppPreview` mirrors the same fallback behavior so summary/page-image metadata stays aligned if the preview path parses PageLabels directly.
- Duplicate `/St` operands such as `/St 0 /St 4` and `/St -2 /St 6` now select the first later valid positive start value.
- A label dictionary with no positive `/St` still defaults to start `1`.

## Red-First Evidence

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsStartValueBoundaryCurrentBaseTest.php
FAIL skips non-positive PageLabels St operands before WordPress page metadata
Expected: ['Front 4', 'Body vi', 'App-Z', 'Reset 1']
Actual: ['Front 1', 'Body i', 'App-A', 'Reset 1']
1 test files, 1 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsStartValueBoundaryCurrentBaseTest.php
PASS skips non-positive PageLabels St operands before WordPress page metadata
1 test files, 15 assertions, 0 failures
```

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfPageLabels.*CurrentBaseTest\.php$|/PdfPageLabelsBoundaryCurrentBaseTest\.php$' | sort) lanes/markerpdf/tests/MarkerAppPreviewTest.php
54 test files, 1040 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-start-value-boundary-currentbase.php
exits 0 with page_labels=[Front 4, Body vi, App-Z, Reset 1], non_positive_st_rejected=true, no_positive_st_defaults_to_one=true, executes_python_or_models=false, executes_external_pdf_tools=false
```

## Status Delta

- Adds `1` focused PHP PASS file and `15` focused assertions.
- Adds `1` WordPress smoke/example.
- `phpPass`: `3585 -> 3586`
- `wordpressScenarios`: `2894 -> 2895`
- Mapped upstream denominator: unchanged; this is additive inside the already mapped PageLabels catalog number-tree behavior cluster.

## Non-Overlap

This does not repeat accepted PageLabels direct number-tree extraction, indirect `/Kids`, direct kid dictionaries, inherited/local/indirect/malformed/reversed/negative `/Limits`, no-limits kid source order, same-lower source-order preservation, malformed same-lower contribution guards, duplicate `/Nums`, `/Kids`, `/Limits`, `/Type`, or catalog `/PageLabels` keys, malformed key/value ordering, duplicate malformed values, descending/out-of-range ordering, null resets, indirect scalar operands, generation-exact dictionaries/arrays/keys, object-stream PageLabels, escaped names, PDFDocEncoding/UTF-16/UTF-8 prefix handling, empty hex prefixes, generated suffix bounds, malformed dictionary/array object tails, trailer `/Root` selection, encrypted preview fallback, viewer-preference composition, outline page-label propagation, page transition/action review, or page resource generation behavior. The bounded behavior is only non-positive `/St` start values inside PageLabels value dictionaries not blocking later valid duplicate `/St` operands.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, dictionary/array tokenizer, exact-generation object resolver, PageLabels number-tree parser, MarkerAppPreview inventory path, and WordPress block smoke renderer. Full upstream markerPDF model/PDFium runner parity remains intentionally out of scope under the current no-GPU/no-live-model direction.
