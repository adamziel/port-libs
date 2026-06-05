# markerPDF Embedded Files Attachment Collection Item Boundary Current Base

Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260605T104112Z`

Base accepted HEAD: `42ff13c6ab18e3cd15e26c4f396809c9223d5900`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned in the manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream markerPDF keeps searchable PDF text extraction separate from attachment and metadata review paths before Markdown/WordPress conversion. In this native no-GPU lane, attachment preflight summarizes FileSpec metadata without executing Python, PDFium, OCR/model workers, JavaScript, PDF actions, or external PDF tools.
- PDF Portfolio FileSpec `/CI` dictionaries are per-file collection item metadata. They should be retained as attachment review fields, while referenced private streams and embedded payload bytes remain out of visible WordPress paragraphs and preflight JSON.

## Implementation

`PdfAttachmentExtractor` now parses FileSpec `/CI` collection item dictionaries into `portfolio_item` and `portfolio_item_count` on attachment summary rows. Scalar string/name/number/boolean values are preserved, `/CollectionSubitem` dictionaries expose `/D`, `/P`, and `display_value`, `/Type` is skipped, and arbitrary dictionary or stream references are ignored so private payloads are not promoted.

Encrypted FileSpec-string preflight redaction now also removes `portfolio_item` and `portfolio_item_count`, matching the existing filename, description, and identifier suppression boundary.

The WordPress smoke adds a PDF Portfolio-like attachment with `/CI` subject, priority, review date, boolean approval, and a private metadata stream decoy. The smoke proves the attachment summary includes the review fields, omits embedded and private stream payload bytes, and leaves visible searchable text unchanged.

## Red/Green Evidence

Red baseline after adding the focused test:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentCollectionItemBoundaryCurrentBaseTest.php
```

Result: `1 test files, 13 assertions, 1 failures`

Failure: `summarizes FileSpec collection item metadata in attachment preflight without payload leakage` expected `portfolio_item.Subject` to be `Current WordPress Export`, but the current base returned `NULL`.

After patch:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentCollectionItemBoundaryCurrentBaseTest.php
```

Result: `1 test files, 29 assertions, 0 failures`

Attachment family:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachment*.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
```

Result: `15 test files, 1462 assertions, 0 failures`

Smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-attachment-collection-item-boundary-currentbase.php
```

Result: emitted `portfolio_item_subject=Current WordPress Export`, `portfolio_item_priority=P2`, `portfolio_item_private_stream_excluded=true`, `attachment_payload_omitted=true`, `private_payload_omitted=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax:

```bash
php -l lanes/markerpdf/src/PdfAttachmentExtractor.php && php -l lanes/markerpdf/tests/PdfAttachmentCollectionItemBoundaryCurrentBaseTest.php && php -l lanes/markerpdf/examples/wordpress-pdf-attachment-collection-item-boundary-currentbase.php
```

Result: no syntax errors detected in all three changed PHP files.

Status JSON:

```bash
php -r 'json_decode((string) file_get_contents("lanes/markerpdf/lane-status.json"), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, json_last_error_msg() . PHP_EOL); exit(1); } echo "lanes/markerpdf/lane-status.json: valid JSON\n";'
```

Result: valid JSON.

Whitespace:

```bash
git diff --check -- lanes/markerpdf
```

Result: passed with no output.

## Non-Overlap

This does not repeat accepted catalog/page `/AF` ingestion, FileAttachment annotation mirroring, platform FileSpec filename selection, `/AFRelationship` roles, checksum and size review, related-file `/RF` rows, EmbeddedFiles name-tree `/Limits`, EOF object-scan bounding, xref/xref-stream attachment selection, path-safe filenames, FileSpec `/ID` and `/V` metadata, PDF Portfolio metadata under `PdfEmbeddedFileExtractor`/`PdfMetadataExtractor`, PieceInfo/XMP/OutputIntent provenance, or payload-byte extraction. The bounded behavior is only FileSpec `/CI` collection item review fields in the lightweight `PdfAttachmentExtractor` preflight path.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object/value parser, FileSpec parsing, attachment summary redaction, existing stream payload exclusion, searchable-text extractor, and WordPress smoke path. Full upstream OCR/model/PDFium parity remains intentionally out of scope under the current no-GPU markerPDF direction.
