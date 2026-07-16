# markerPDF PageLabels encrypted preview boundary

## Source truth

- Upstream markerPDF receives searchable page text and page-local metadata through pdftext/PDFium before model work; encrypted PDFs require password/decryption before content import. The native no-GPU path therefore keeps encrypted catalog `/PageLabels` out of visible and preview metadata.
- PDFium PageLabel tests model `/PageLabels` as a catalog number tree with page-index `/Nums` keys and optional `/S`, `/P`, and `/St` fields. This slice reuses that existing parser only after the encrypted-document preflight allows native metadata extraction.

## Implementation

- Added `PdfTextExtractor::isEncrypted()` as a narrow public wrapper around the existing trailer `/Encrypt` detector.
- `MarkerAppPreview` now checks that encrypted preflight before its fallback catalog PageLabels parser. When a PDF is encrypted, preview inventory returns physical page labels (`1`, `2`, ...) and does not parse encrypted catalog `/PageLabels`.
- Added a focused fixture where `PdfTextExtractor::extractPageLabels()` correctly returns `[]`, but the preview fallback previously leaked `Secret-9`.

## Evidence

Red-first probe before the source edit:

```text
PdfTextExtractor::extractPageLabels($pdf) => []
MarkerAppPreview::pageLabels($pdf) => ["Secret-9"]
```

Focused verification after the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsEncryptedPreviewBoundaryCurrentBaseTest.php
PASS blocks encrypted catalog PageLabels fallback in preview metadata

1 test files, 12 assertions, 0 failures
```

Focused PageLabels family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabels*CurrentBaseTest.php
10 test files, 357 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-labels-encrypted-preview-currentbase.php
emits markerpdf-page-labels-encrypted-preview-boundary with encrypted=true, content_extraction_allowed=false, text_extraction_policy=blocked_without_decryption, text_extractor_page_labels=[], preview_page_labels=["1"], catalog_label_leaked_to_preview=false, raw_encrypted_text_exposed=false, executes_python_or_models=false, and executes_external_pdf_tools=false.
```

## Non-overlap

This does not repeat accepted direct PageLabels number-tree extraction, indirect `/Kids`, inherited/local/indirect/malformed `/Limits`, same-lower source-order preservation, malformed same-lower contribution guards, duplicate catalog `/PageLabels`, duplicate `/Nums` dictionary keys, descending/out-of-range `/Nums` keys, direct/indirect null reset values, scalar comments, object-stream PageLabels, trailer `/Root` selection, viewer-preference composition, outline page-label propagation, or encrypted permission metadata review. The bounded behavior is only suppressing the `MarkerAppPreview` fallback PageLabels parser when the existing encrypted-PDF preflight blocks native extraction.

## Dependency closure

No new support component is needed. This reuses the native PDF object scanner, trailer `/Encrypt` detector, PageLabels number-tree parser, MarkerAppPreview inventory path, security preflight metadata, and WordPress smoke renderer. Decryption, password handling, PDFium, Python models, OCR, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.
