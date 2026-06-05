# markerPDF outline malformed UTF-16 title boundary

Slice: `markerpdf-outline-metadata-boundary-current-base-20260605T094840Z`

Accepted base: `6c17a53dace9fb9ba9844a3b8d169184f9cf69ee`

## Source truth

The no-GPU markerPDF lane owns native searchable-PDF outline, metadata, and action-review behavior. PDF strings that advertise UTF-16 with a BOM must be well-formed before they can become document metadata, TOC/navigation labels, or action-review labels. A malformed outline title should therefore behave like an absent title instead of producing an empty visible navigation/action row.

This keeps the boundary aligned with upstream markerPDF's parser handoff intent without invoking pdftext/PDFium, OCR, Surya, Texify, Torch, or model workers.

## Implementation

- `PdfMetadataExtractor`, `PdfOutlineExtractor`, and `PdfActionReviewExtractor` now fail closed for odd-length or invalid UTF-16BE/UTF-16LE PDF strings before calling `iconv`.
- `PdfOutlineExtractor` now resolves titles through one boundary helper and skips missing, empty, or malformed titles across TOC, destination-view navigation, outline action review, structure/page context rows, and remote GoTo traversal.
- Added a focused fixture that includes a valid UTF-16BE outline title plus a malformed UTF-16BE title that carries a remote `GoToR` action.
- Added a WordPress smoke proving the malformed outline title and remote action operand stay out of visible block output and review metadata while valid outline navigation remains.

## Red-first evidence

Command:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataMalformedUtf16TitleBoundaryCurrentBaseTest.php
```

Before the fix the focused run failed with `1 test files, 13 assertions, 1 failures`. The malformed title decoded to an empty string, letting an `outline_action_review_actions` row expose `malformed-title-remote.pdf` and `malformed-title-target`. The run also emitted incomplete-multibyte `iconv()` notices from the UTF-16 decoders.

## Verification

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataMalformedUtf16TitleBoundaryCurrentBaseTest.php
```

Result: `1 test files, 18 assertions, 0 failures`.

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfOutline*Test.php
```

Result: `47 test files, 2454 assertions, 0 failures`.

```bash
php tools/run-tests.php lanes/markerpdf/tests/Pdf*Action*Test.php
```

Result: `41 test files, 2442 assertions, 0 failures`.

```bash
php lanes/markerpdf/examples/wordpress-pdf-outline-malformed-utf16-title-currentbase.php
```

Result: emitted `malformed_title_rejected=true`, `malformed_remote_action_rejected=true`, `visible_text_excludes_outline_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-overlap

This slice does not repeat trailer-root/xref-stream/root selection, parent/prev/last/generation traversal, root type boundaries, PageLabels, destination action operand parsing, structure-element title handling, AcroForm boundaries, model/OCR work, or any external PDF-tool execution. It is limited to malformed UTF-16 outline title decoding at the metadata/navigation/action-review boundary.

## Dependency closure

No new support component is needed. The patch reuses the native PHP object parser, outline walker, action review extractor, metadata extractor, and PDF string decoder. The no-GPU exclusion remains: live OCR, Surya/Texify/Torch, Streamlit/FastAPI model workers, and exact upstream model benchmark parity are intentionally out of scope.
