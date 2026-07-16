# markerpdf outline metadata stream operand boundary current-base

## Scope

Pinned upstream markerPDF `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` obtains outline TOC rows from parser-provided document outlines before model/OCR fallback, while searchable page text is sourced separately through `pdftext`/PDFium-backed parsing. Under the current no-GPU markerPDF lane, the native PHP port owns the parser trust boundary where PDF outline metadata streams are reviewed without promoting their payloads to document metadata or WordPress paragraphs.

This slice covers outline item and outline root `/Metadata` stream dictionaries. The root path already delegates through the item stream review, so applying catalog-style stream operand checks at the shared item boundary protects both roots and items.

## Behavior

- `PdfMetadataExtractor::documentOutlineItemMetadataStreamReview()` now checks outline-local metadata stream dictionaries before decoding stream bytes.
- The outline review reuses the same native checks as catalog XMP streams:
  - `/Filter` operand boundary;
  - `/DecodeParms` operand boundary;
  - CCITTFax filter/DecodeParms consistency;
  - `/Length` operand boundary.
- Malformed helper operands fail closed with payload-free review rows:
  - `rejected_malformed_metadata_stream_filter_operand`;
  - `rejected_malformed_metadata_stream_decodeparms_operand`;
  - `metadata_reference_resolved=true`;
  - `has_stream=true`;
  - `native_metadata_decode=false`.
- Valid outline metadata streams still remain review-only and continue to expose type/subtype, filters, decoded byte count, SHA-256, and redacted XMP summary metadata.
- TOC/navigation rows preserve valid outline titles and page destinations while malformed stream payloads and helper-tail action text stay out of document metadata, navigation metadata, and visible WordPress text.

## Red-First Evidence

Before the source change:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataStreamOperandBoundaryCurrentBaseTest.php
```

Result: `1 test files, 32 assertions, 2 failures`.

The two failing rows showed both malformed outline metadata stream helper operands were decoded as `reviewed_outline_item_metadata_stream` instead of failing closed.

## Verification

Focused regression:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataStreamOperandBoundaryCurrentBaseTest.php
```

Result: `1 test files, 102 assertions, 0 failures`.

Adjacent outline metadata stream suite:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataStreamOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataRootMetadataStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataUnreadableStreamTailBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataStreamTypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataStreamDuplicateTypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataReferenceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataDuplicateKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfOutlineMetadataNavigationReviewCurrentBaseTest.php
```

Result: `9 test files, 502 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-outline-metadata-stream-operand-boundary-currentbase.php
```

Result: exits `0` and emits `filter_operand_status=rejected_malformed_metadata_stream_filter_operand`, `decodeparms_operand_status=rejected_malformed_metadata_stream_decodeparms_operand`, `malformed_streams_decoded=false`, payload-exclusion flags, and no Python/OCR/external-tool execution flags.

PHP lint:

```bash
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/tests/PdfOutlineMetadataStreamOperandBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-outline-metadata-stream-operand-boundary-currentbase.php
```

Result: no syntax errors.

## Non-Overlap

This does not repeat accepted outline metadata clean stream review, unreadable stream-tail review, duplicate `/Type` or `/Subtype` stream dictionary boundaries, duplicate `/Metadata` keys, malformed `/Metadata` reference operands, root metadata stream review, catalog `/Outlines` operand boundaries, title/parent/prev/next/count traversal boundaries, destination/action operand boundaries, remote outline action review, xref repair, encrypted permission preflight, pdftext dictionary boundaries, image/table handoffs, or OCR/model work. The bounded behavior is only outline-local `/Metadata` stream dictionary operand checks before native stream decoding.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, indirect-object selector, dictionary parser, catalog metadata stream operand reviewers, stream-filter decoder, outline metadata review path, navigation review propagation, and WordPress smoke renderer. Live OCR, Surya/Texify/Torch model execution, PDFium/pypdfium execution, Python pdftext execution, external OCR/rendering helpers, Streamlit/FastAPI workers, and exact upstream model benchmark parity remain intentionally outside the current no-GPU markerPDF scope.
