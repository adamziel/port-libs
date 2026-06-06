# markerPDF EmbeddedFiles Attachment PDFDocEncoding Byte Limits Boundary

Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260606T211303Z`
Session: `port-dev-markerpdf-attachments-20260606T211303Z`
Base accepted HEAD: `88ddfe94849d1a826e6777df8358e3d94635ff84`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates searchable-PDF parsing to PDF text/PDF parser dependencies before OCR/model fallback. Under the current no-GPU scope, this lane owns native PHP searchable-PDF object parsing and attachment review metadata.
- PDF name-tree `/Limits` and keys are PDF strings whose ordering is by raw string bytes. Decoding PDFDocEncoding before comparing limits can invert low control-byte keys such as `0x18` because they map to higher Unicode text.
- Embedded payload bytes and name-tree keys remain review metadata and must not become visible WordPress paragraph text.

No OCR, Surya, Texify, Torch, Python model worker, pypdfium/PDFium, browser, or external PDF tool execution was used.

## Behavior

- `PdfAttachmentExtractor` now carries raw PDF string bytes through EmbeddedFiles name-tree `/Limits`, leaf-key filtering, and child `/Kids` sorting.
- `PdfEmbeddedFileExtractor` applies the same byte-aware limit handling for the full embedded-file inventory even though it parses raw dictionary values rather than tokenized object arrays.
- PDFDocEncoding names still decode for WordPress review labels after the byte-range decision.
- Out-of-byte-range stale attachments remain excluded from summaries and embedded-file rows.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentNameTreePdfDocEncodingByteLimitsCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL uses raw PDF string bytes for EmbeddedFiles name-tree Limits before WordPress attachment review
Values are not identical
Expected: 2
Actual: 3
1 test files, 1 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentNameTreePdfDocEncodingByteLimitsCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses raw PDF string bytes for EmbeddedFiles name-tree Limits before WordPress attachment review
1 test files, 25 assertions, 0 failures
```

```text
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f \( -name 'PdfAttachment*Test.php' -o -name 'PdfEmbeddedFile*Test.php' -o -name 'PdfEmbeddedFiles*Test.php' \) | sort)
Focused test run: 40 selected test files (root lock skipped)
40 test files, 3039 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-pdfdocencoding-byte-limits-currentbase.php
```

The smoke exits `0` and reports `attachment_count=2`, decoded name keys `\u02d8` and `z-report.xml`, `stale_out_of_byte_limits_excluded=true`, `payload_bytes_omitted_from_summary=true`, `visible_text_excludes_attachment_payloads=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `2684 -> 2685`.
- `wordpressScenarios`: `2261 -> 2262`.
- `pdfAttachmentNameTreePdfDocEncodingByteLimitsCurrentBaseBehaviors`: `0 -> 1`.
- `mappedPdfAttachmentNameTreePdfDocEncodingByteLimitsCurrentBaseBehaviors`: `0 -> 1`.
- New focused file: `PdfAttachmentNameTreePdfDocEncodingByteLimitsCurrentBaseTest.php` adds 1 PASS case and 25 assertions.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, parser, PDF string byte preservation, PDFDocEncoding decoder, name-tree `/Limits` handling, FileSpec and EmbeddedFile extraction, attachment summary path, embedded-file inventory path, text extractor, and WordPress smoke pattern. Full upstream model/OCR parity remains intentionally out of scope under the current no-GPU markerPDF directive.

## Non-Overlap

This does not repeat accepted attachment EOF-bounded scanning, FileSpec duplicate-key rejection, EmbeddedFiles duplicate-name handling, direct FileSpec mirror dedupe, platform EF key selection, name-tree EF fallback order, child `/Kids` limit sorting, related-file metadata, encrypted EFF suppression, stream-filter stack boundaries, object-stream/xref attachment recovery, catalog/page `/AF` review, Portfolio/PieceInfo metadata, page StructTree associated-file review, or general PDFDocEncoding filename decoding. The bounded behavior is only raw-byte `/Limits` comparison for PDFDocEncoding EmbeddedFiles name-tree keys before attachment and embedded-file review.
