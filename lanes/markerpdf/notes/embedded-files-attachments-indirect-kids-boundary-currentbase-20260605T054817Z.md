# Embedded Files Attachments Indirect Kids Boundary

Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260605T054817Z`

Base accepted HEAD: `526ad869d7da7675b3a423e96ae8ddab1ee95e78`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps searchable PDF text extraction on the pdftext/PDFium page-text path. Embedded file payloads and FileSpec dictionaries are not visible text input.
- The native no-GPU PHP lane owns the matching parser boundary for PDF attachment preflight: `/Names /EmbeddedFiles`, catalog/page `/AF`, and FileAttachment annotation FileSpecs are review metadata for WordPress import, not content-stream text.
- PDF name-tree and page-tree `/Kids` entries are ordinary PDF object values. They may be direct arrays or indirect array objects. Attachment preflight must resolve those arrays before walking child name-tree nodes or page leaves.

## Implementation

`PdfAttachmentExtractor` now resolves `/Kids` operands before treating them as arrays in both:

- EmbeddedFiles name-tree traversal.
- Catalog-selected page-tree traversal used for page-level associated files.

The focused fixture uses a selected trailer root and classic xref table so fallback page scanning cannot hide the bug. It carries:

- `/Names /EmbeddedFiles 6 0 R`, where object `6` has `/Kids 7 0 R` and object `7` is `[8 0 R]`.
- `/Pages 2 0 R`, where object `2` has `/Kids 30 0 R` and object `30` is `[3 0 R]`.
- A stale out-of-limits name-tree FileSpec row that must remain pruned.

## Evidence

Red-first:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php
FAIL resolves indirect EmbeddedFiles and page-tree Kids arrays in attachment preflight
Expected: 2
Actual: 0
1 test files, 354 assertions, 1 failures
```

After implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php
1 test files, 392 assertions, 0 failures
```

Focused attachment family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
2 test files, 782 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-attachments-preflight.php
```

The smoke exits 0 and emits:

- `indirect_embeddedfiles_kids_array_preflight=true`
- `indirect_page_tree_kids_array_preflight=true`
- `indirect_kids_attachment_count=2`
- `indirect_kids_payload_omitted=true`
- `indirect_kids_stale_name_tree_entry_pruned=true`
- `executes_python_or_models=false`
- `executes_external_pdf_tools=false`

Syntax and diff checks:

```text
php -l lanes/markerpdf/src/PdfAttachmentExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfAttachmentExtractor.php

php -l lanes/markerpdf/tests/PdfAttachmentExtractorTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfAttachmentExtractorTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-attachments-preflight.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-attachments-preflight.php

git diff --check -- lanes/markerpdf
passed with no output
```

## Non-Overlap

This does not repeat accepted catalog `/AF`, page `/AF`, annotation FileAttachment extraction, direct FileSpec mirror dedupe, related-file review, EOF-bounded object scanning, xref/current-row attachment selection, encrypted attachment redaction, portfolio/PieceInfo/XMP attachment metadata, or indirect `/Names` array handling. The bounded behavior is only indirect `/Kids` array resolution in the lightweight attachment preflight graph.

## Dependency Closure

No new support component is needed. The slice reuses native PHP PDF object parsing, indirect value resolution, FileSpec stream decoding, checksum review, name-tree limit pruning, page-tree traversal, and WordPress smoke rendering. Full markerPDF OCR/model parity remains intentionally out of scope under the current no-GPU direction.
