# markerPDF Embedded Files Attachment EOF Boundary Current Base

Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260604T234038Z`

Base accepted HEAD: `12497e5fdb80be5eaa15ccf8ea2eee0aeb6b8e50`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` extracts visible searchable PDF text through `pdftext.dictionary_output()` and PDF page text APIs; attachment payloads and FileSpec dictionaries are not part of the visible text path.
- PDF attachment preflight is a native no-GPU boundary in this lane: summarize `/Names /EmbeddedFiles`, catalog/page `/AF`, and FileAttachment FileSpec metadata for WordPress review without executing Python, models, external PDF tools, attachment actions, or payload text promotion.
- Terminal `%%EOF` bounds the active PDF byte range for this lightweight preflight. Appended object-looking data after the terminal EOF is not a current attachment source.

## Implementation

`PdfAttachmentExtractor` now bounds its object inventory to bytes through the terminal `%%EOF` before parsing `obj ... endobj` definitions. This prevents appended stale FileSpec and EmbeddedFile objects after EOF from replacing the current attachment rows in WordPress preflight summaries.

The focused test adds a current EmbeddedFiles name-tree attachment before EOF and same-number stale FileSpec/EmbeddedFile objects after EOF. The expected summary keeps `current-eof.csv`, `AFRelationship /Data`, the current MD5 checksum match, and omits `stale-eof.csv` plus stale payload text. The WordPress smoke appends the same post-EOF stale shape to the existing attachment preflight fixture and emits `terminal_eof_bounds_attachment_scan=true`.

## Red/Green Evidence

Red baseline after adding the focused case:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php
```

Result: `1 test files, 180 assertions, 1 failures`

Failure: `bounds attachment preflight object scanning at terminal EOF before stale appended FileSpecs` selected the stale post-EOF payload size (`41`) instead of the current payload size (`42`).

After patch:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php
```

Result: `1 test files, 198 assertions, 0 failures`

Focused family:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
```

Result: `2 test files, 588 assertions, 0 failures`

Smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-attachments-preflight.php
```

Result: emitted `attachment_count=4`, `terminal_eof_bounds_attachment_scan=true`, `catalog_associated_file_preflight=true`, `page_associated_file_preflight=true`, `related_file_preflight=true`, and no Python/model/external PDF tool execution.

Syntax:

```bash
php -l lanes/markerpdf/src/PdfAttachmentExtractor.php && php -l lanes/markerpdf/tests/PdfAttachmentExtractorTest.php && php -l lanes/markerpdf/examples/wordpress-pdf-attachments-preflight.php
```

Result: no syntax errors detected in all three changed PHP files.

Status/manifest JSON:

```bash
php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode((string) file_get_contents($file), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, $file . ": " . json_last_error_msg() . PHP_EOL); exit(1); } echo $file . ": valid JSON\n"; }'
```

Result: both JSON files valid.

Whitespace:

```bash
git diff --check -- lanes/markerpdf
```

Result: passed with no output.

## Non-Overlap

This does not repeat accepted catalog `/AF` ingestion, page `/AF` mirror marking, platform FileSpec filename selection, `/AFRelationship` role mapping, checksum review, related-file review, EmbeddedFiles `/Limits` pruning, portfolio/PieceInfo/XMP/OutputIntent attachment metadata, xref `/Prev` EmbeddedFiles selection, or page resource inheritance work. The new behavior is only EOF-bounded object scanning in the lightweight `PdfAttachmentExtractor` preflight.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object/value parser, FileSpec parsing, stream decoding, checksum review, and existing WordPress smoke pattern. Full markerPDF OCR/model parity remains intentionally out of scope under the current no-GPU direction.
