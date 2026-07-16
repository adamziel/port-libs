# markerPDF EmbeddedFiles attachment indirect name-key current-base slice

Session: `port-dev-markerpdf-attachments-20260605T093129Z`
Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260605T093129Z`
Base accepted HEAD: `cd9b1dd080be5ba6f083eb763beca98a277cc0e1`

## Source Truth

- Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF text and attachment review through native PDF parsing before model/OCR stages.
- PDF name-tree `/Names` arrays are key/value pairs whose keys are strings; malformed or compact producers can store those strings as indirect string objects. Lightweight attachment preflight must resolve those keys before `/Limits` pruning and FileSpec lookup.
- WordPress import needs the attachment row for review/media handoff, but embedded payload bytes must stay out of visible paragraphs and attachment summaries.

## Behavior

`PdfAttachmentExtractor::nameTreeEntries()` now resolves each `/Names` pair key before converting it to a string. This preserves existing direct-key behavior and lets indirect string keys such as `8 0 R` select the current FileSpec row.

The focused fixture places the current `indirect-key.csv` name key in an indirect string object and includes an out-of-limits stale `zz-stale-key.csv` row in the same EmbeddedFiles name tree. Before the patch the attachment preflight returned no attachment. After the patch it reports `indirect-key.csv`, resolves the `/UF` embedded-file stream, records checksum and size review metadata, omits payload bytes from `attachmentSummary()`, and keeps both current and stale payload text out of visible PDF text.

## Evidence

Red-first:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentIndirectNameKeyCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL resolves indirect EmbeddedFiles name-tree string keys in attachment preflight (lanes/markerpdf/tests/PdfAttachmentIndirectNameKeyCurrentBaseTest.php)
Values are not identical
Expected: 1
Actual: 0

1 test files, 1 assertions, 1 failures
```

After patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentIndirectNameKeyCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS resolves indirect EmbeddedFiles name-tree string keys in attachment preflight

1 test files, 35 assertions, 0 failures
```

Attachment family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachment*Test.php
Focused test run: 12 selected test files (root lock skipped)
12 test files, 969 assertions, 0 failures
```

Syntax and JSON:

```text
php -l lanes/markerpdf/src/PdfAttachmentExtractor.php
php -l lanes/markerpdf/tests/PdfAttachmentIndirectNameKeyCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-attachment-indirect-name-key-currentbase.php
jq empty lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json lanes/markerpdf/lane-status.json
No syntax errors detected in changed PHP files; JSON validation passed.
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-indirect-name-key-currentbase.php
```

The smoke exits `0` and reports `indirect_name_key_resolved=true`, `stale_out_of_limits_attachment_excluded=true`, `payload_bytes_omitted_from_summary=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted page-level `/AF` preflight, catalog `/AF` mirror dedupe, duplicate direct name-tree key pruning, indirect `/Names` array resolution, indirect `/Kids` array traversal, platform EF-key selection, RF related-file pairs, object-stream attachment extraction, xref generation repair, encrypted EFF suppression, or default `/Crypt` stream-filter behavior. The bounded behavior is only the attachment summary path for indirect string objects used as EmbeddedFiles name-tree keys.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object parser, name-tree walker, FileSpec attachment parser, stream-filter decoder, checksum review, text extractor, and WordPress smoke path. OCR, Surya/Texify/Torch, PDFium rendering, model workers, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.

## Next

Continue with non-overlapping native searchable-PDF parser behavior around fonts/CMaps, stream filters, xref repair, metadata, annotations/forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
