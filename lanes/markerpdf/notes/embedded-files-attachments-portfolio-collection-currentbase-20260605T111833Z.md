# markerPDF EmbeddedFiles Attachment Portfolio Collection Boundary

Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260605T111833Z`

Base accepted HEAD: `490b25f5b27ded338ec316c5d5be7821bb0c7237`

## Source Truth

- Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps searchable PDF text extraction separate from document metadata/review side channels before OCR/model stages.
- PDF Portfolio `/Collection` dictionaries provide review context for EmbeddedFiles rows: `/View`, default document `/D`, `/Schema`, `/Sort`, and FileSpec `/CI` values describe attachments. These values must be attachment review metadata, not visible WordPress text or imported payload bytes.
- This slice stays in the no-GPU native parser scope. It does not run pdftext, PDFium, Surya/Torch, Texify, OCR, model workers, or external PDF tools.

## Implementation

`PdfAttachmentExtractor` now carries catalog `/Collection` metadata into lightweight attachment summaries for EmbeddedFiles name-tree rows and catalog `/AF` rows. The summary includes:

- catalog collection type/view/default document, schema fields, sort keys, and sort direction;
- FileSpec `/CI` collection item values;
- `portfolio_field_values` derived from schema fields, using `/CI` values first and FileSpec/EmbeddedFile fields for `F`, `Desc`, `Size`, `ModDate`, and `CreationDate`;
- decoded `/UF` as `unicode_filename` for preflight parity with the lower-level embedded-file extractor.

Encrypted-string preflight redacts the new `unicode_filename`, `portfolio`, `portfolio_item`, and `portfolio_field_values` keys while preserving identity `/EFF` payload hash/size review metadata.

## Verification

New focused test:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentPortfolioCollectionBoundaryCurrentBaseTest.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
PASS carries catalog Collection schema and sort metadata into attachment preflight
PASS redacts portfolio preflight metadata when FileSpec strings are encrypted

1 test files, 78 assertions, 0 failures
```

Attachment family:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfAttachment*CurrentBaseTest.php
```

Result:

```text
15 test files, 1150 assertions, 0 failures
```

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-attachment-portfolio-collection-boundary-currentbase.php
```

Result: emitted `attachment_count=1`, `portfolio_view=T`, `portfolio_sort_keys=["Priority","ModifiedField"]`, `portfolio_item_priority=P2`, `portfolio_field_name_value=source.xml`, `attachment_payload_omitted=true`, `private_payload_omitted=true`, and `executes_python_or_models=false`.

Syntax checks:

```bash
php -l lanes/markerpdf/src/PdfAttachmentExtractor.php
php -l lanes/markerpdf/tests/PdfAttachmentPortfolioCollectionBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-attachment-portfolio-collection-boundary-currentbase.php
```

Result: no syntax errors.

## Non-Overlap

This does not repeat accepted EmbeddedFiles extraction, indirect name-tree keys, page `/AF`, catalog `/AF` mirror dedupe, FileAttachment annotation mirrors, platform EF-key selection, related-file `/RF` pairs, encrypted `/EFF` payload suppression, xref/object-stream attachment repair, FileSpec `/CI` item-only preflight, or metadata-extractor Portfolio/PDF-A review. The bounded behavior is only catalog `/Collection` portfolio context and schema-derived field values in the lightweight attachment preflight path.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF parser, catalog/name-tree/FileSpec walkers, attachment stream decoder, checksum review, security preflight policy, and WordPress smoke path. Remaining model/OCR/PDFium parity stays intentionally out of scope under the no-GPU markerPDF directive.
