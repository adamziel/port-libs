# markerPDF PageLabels duplicate Type key boundary

## Source Truth

- Upstream markerPDF extracts searchable text page-by-page before model execution; native PHP `/PageLabels` remains page-break and preview metadata under the current no-GPU scope. Source: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDF `/PageLabels` is a catalog number tree keyed by zero-based physical page index. PDFium PageLabel tests model label dictionaries with optional `/Type /PageLabel`, `/S`, `/P`, and `/St` entries. Source: https://pdfium.googlesource.com/pdfium.git/+/refs/heads/chromium/7430/core/fpdfdoc/cpdf_pagelabel_unittest.cpp
- The existing markerPDF PageLabels duplicate-key boundary keeps the first usable duplicate key value for malformed real PDFs. This slice applies that same boundary to duplicate `/Type` keys inside PageLabels value dictionaries: an earlier wrong type must not poison a later usable `/Type /PageLabel`.

## Implementation

- `PdfTextExtractor::pageLabelDictionaryTypeIsValid()` now skips wrong or malformed duplicate `/Type` operands until it finds a usable `/PageLabel` name, while still rejecting dictionaries whose `/Type` entries never resolve to `/PageLabel`.
- `MarkerAppPreview::pageLabelDictionaryTypeIsValid()` applies the same first-usable duplicate `/Type` behavior so preview/page-image labels stay aligned with text extraction.
- Added a focused three-page fixture where page-label value dictionaries contain `/Type /Pages /Type /PageLabel` and indirect stale `/Type 30 0 R /Type /PageLabel` before valid labels.

## Evidence

Red-first probe before the source edit:

```text
duplicate /Type /Pages before /Type /PageLabel returned ["1"] from both PdfTextExtractor and MarkerAppPreview
```

Focused run after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsDuplicateTypeKeyBoundaryCurrentBaseTest.php
PASS keeps first usable duplicate PageLabels Type key before WordPress page metadata
1 test files, 15 assertions, 0 failures
```

Adjacent PageLabels/preview family:

```text
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f -name 'PdfPageLabels*CurrentBaseTest.php' | sort) lanes/markerpdf/tests/MarkerAppPreviewTest.php
46 test files, 934 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-duplicate-type-currentbase.php > /tmp/markerpdf-page-labels-duplicate-type.html
```

The smoke reports `first_usable_duplicate_type_key_preserved=true`, `wrong_first_type_skipped=true`, `labels_excluded_from_visible_paragraph_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and patch checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/src/MarkerAppPreview.php
php -l lanes/markerpdf/tests/PdfPageLabelsDuplicateTypeKeyBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-page-labels-duplicate-type-currentbase.php
git diff --check -- lanes/markerpdf
```

All returned clean.

## Delta

- Focused PHP PASS cases: `3311 -> 3312`
- Focused PageLabels assertions: new test adds `15` assertions
- WordPress scenarios: `2698 -> 2699`
- Mapped upstream denominator: unchanged; this is additive inside the already mapped PageLabels/catalog number-tree behavior cluster.

## Non-Overlap

This does not repeat accepted PageLabels direct number-tree extraction, indirect `/Kids`, direct kid dictionaries, inherited/local/indirect/malformed/reversed/negative `/Limits`, no-limits kid source order, same-lower source-order preservation, malformed same-lower contribution guards, duplicate `/Nums`, `/Kids`, `/Limits`, or catalog `/PageLabels` keys, duplicate scalar `/P` `/S` `/St` operands, duplicate malformed values, descending/out-of-range keys, null resets, indirect scalar operands, generation-exact dictionaries/arrays/keys, object-stream PageLabels, escaped names, PDFDocEncoding/UTF-16/UTF-8 prefixes, malformed dictionary/array object tails, trailer `/Root` selection, encrypted preview fallback, viewer-preference composition, outline page-label propagation, or page transition/action review. The bounded behavior is only duplicate `/Type` keys inside PageLabels value dictionaries where an earlier wrong type is followed by a usable `/PageLabel`.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, dictionary tokenizer, exact-generation object resolver, PageLabels number-tree parser, MarkerAppPreview inventory path, and WordPress block smoke renderer. Full upstream markerPDF model/PDFium runner parity remains intentionally out of scope under the current no-GPU/no-live-model direction.
