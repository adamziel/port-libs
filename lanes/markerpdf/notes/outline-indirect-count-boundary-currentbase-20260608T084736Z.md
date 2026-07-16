# markerPDF Outline Indirect Count Boundary Current Base

Micro-slice: `markerpdf-outline-metadata-boundary-current-base-20260608T084736Z`

Base: `44a6b8ca63648f8139e32dc4d9ba796599473fd3`

## Source-Truth Boundary

Upstream `sddai/markerPDF` at manifest-pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` receives PDF outline/bookmark rows from PDFium/pdftext-style native PDF parsing before model execution. The current no-GPU markerPDF lane maps that parser boundary in native PHP: outline root and item `/Count` values are structural integers, and an indirect scalar count object must consume one top-level PDF token before traversal, TOC promotion, navigation action review, or `document_outline` metadata can trust it.

## Pre-Edit Probe

Before the patch, a PDF with root `/Count 9 0 R` where object `9 0 obj` contained `1 99 0 R` still promoted `Indirect Count Chapter` into `document_outline` and TOC metadata:

```bash
php -r 'require "tools/bootstrap.php"; /* fixture probe omitted in note */'
```

Observed summary:

```json
{"item_count":1,"titles":["Indirect Count Chapter"],"toc":[{"title":"Indirect Count Chapter","level":1,"page":0,"destination":null,"view_mode":"FitH","view_position":[720],"view_parameters":{"top":720}}]}
```

## Implementation

- `PdfOutlineExtractor` now rejects root/item child traversal when `/Count` references an object whose selected value has trailing top-level operands, and it reports that count as absent in outline structure state.
- `PdfMetadataExtractor` now applies the same indirect single-token guard before `document_outline` root/item traversal.
- `PdfTextExtractor` now applies the guard in its lightweight fallback `pdf_toc` parser so `extractOutlineMetadata()` cannot re-promote rows rejected by the richer outline extractor.
- Added `PdfOutlineMetadataIndirectCountBoundaryCurrentBaseTest.php` covering malformed root and item indirect `/Count` objects.
- Added `wordpress-pdf-outline-indirect-count-boundary-currentbase.php`, which emits WordPress paragraph/navigation review metadata while excluding hidden child titles/actions from visible text.

## Verification

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataIndirectCountBoundaryCurrentBaseTest.php
```

Result: `1 test files, 59 assertions, 0 failures`.

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataCountOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataLightweightCountOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataRootCountBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataRootZeroCountBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataZeroCountChildBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataIndirectCountBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineExtractorTest.php
```

Result: `7 test files, 564 assertions, 0 failures`.

```bash
php lanes/markerpdf/examples/wordpress-pdf-outline-indirect-count-boundary-currentbase.php
```

Result: exits `0` and emits `hidden_child_excluded=true`, `hidden_remote_action_excluded=true`, `visible_text_excludes_outline_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object parser, outline extractor, document metadata extractor, lightweight pdftext metadata path, named-destination review, and WordPress smoke infrastructure. Full upstream OCR/model parity remains intentionally out of scope for this markerPDF lane under the no-GPU directive.

## Non-Overlap

This does not repeat accepted direct `/Count` trailing-operand tests, decimal malformed count handling, root zero-count behavior, title operand boundaries, catalog `/Outlines` operand boundaries, destination/action chain review, encrypted permission preflight, xref repair, stream-filter recovery, or model/OCR handoff work. The bounded new behavior is specifically an indirect outline `/Count` scalar object with trailing top-level PDF operands.
