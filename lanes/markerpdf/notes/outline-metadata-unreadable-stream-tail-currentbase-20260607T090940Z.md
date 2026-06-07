# markerPDF outline metadata unreadable stream-tail current-base

## Scope

Upstream markerPDF delegates searchable PDF parsing to pdftext/PDFium before OCR/model fallback. Under the current no-GPU markerPDF scope, this lane owns native PHP parser boundaries for document metadata, outlines, stream filters, and WordPress review output without executing Python, PDF actions, OCR, models, or external PDF tools.

This slice covers outline root and outline item `/Metadata` stream references when the referenced stream has an unreadable malformed Flate payload and a hidden top-level operand after `endstream`. Those hidden operands must be surfaced as malformed review-only stream-tail metadata instead of being flattened into a generic `unreadable_metadata_stream` row.

## Behavior

- `PdfMetadataExtractor` now checks outline-local metadata stream ownership after an unreadable decode result.
- Root `/Outlines /Metadata` and item `/Metadata` references with post-`endstream` top-level operands report:
  - `rejected_malformed_outline_root_metadata_stream` or `rejected_malformed_outline_item_metadata_stream`;
  - `stream_tail_operand_rejected=true`;
  - `native_metadata_decode=false`;
  - payload-free review fields with object, generation, stream type/subtype, filter, and declared length.
- Existing decodable malformed outline metadata stream behavior still keeps decoded payload byte/sha summaries while adding the explicit tail-rejection fields.
- TOC/navigation rows preserve valid outline titles and page destinations while metadata payloads and hidden action targets remain excluded from review JSON and visible WordPress text.

## Red-First Evidence

Before the source change:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataUnreadableStreamTailBoundaryCurrentBaseTest.php
```

Failed with both root and item metadata stream reviews reporting `unreadable_metadata_stream` instead of the malformed stream-tail statuses.

## Verification

Focused test:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataUnreadableStreamTailBoundaryCurrentBaseTest.php
```

Result: `1 test files, 65 assertions, 0 failures`.

Adjacent outline metadata stream suite:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataUnreadableStreamTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataStreamTypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataRootMetadataStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataReferenceTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataBoundaryCurrentBaseTest.php
```

Result: `6 test files, 404 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-outline-metadata-unreadable-stream-tail-currentbase.php
```

Result: exits `0` and emits `rejected_malformed_outline_root_metadata_stream`, `rejected_malformed_outline_item_metadata_stream`, `stream_tail_operand_rejected=true`, `native_metadata_decode=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

PHP lint:

```bash
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/tests/PdfOutlineMetadataUnreadableStreamTailBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-outline-metadata-unreadable-stream-tail-currentbase.php
```

Result: no syntax errors.

## Non-Overlap

This does not repeat accepted outline metadata stream review for clean Flate streams, decodable malformed Flate streams, duplicate `/Type` or `/Subtype` stream dictionaries, root metadata stream review, duplicate root metadata fallback, tailed `/Metadata` reference operands, catalog `/Outlines` operand boundaries, outline title boundaries, remote outline action review, xref `/Prev` outline repair, encrypted permission preflight, or stream filter stack text extraction. The bounded behavior is only unreadable outline-local metadata streams whose stream owner has trailing top-level operands after `endstream`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, dictionary parser, stream dictionary reader, stream-length/endstream boundary helper, outline metadata review path, navigation review propagation, and WordPress smoke renderer. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, external OCR/rendering helpers, Streamlit/FastAPI workers, and exact upstream model benchmark parity remain intentionally outside the current no-GPU markerPDF scope.
