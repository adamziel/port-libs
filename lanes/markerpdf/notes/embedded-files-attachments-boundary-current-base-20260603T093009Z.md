# markerPDF Embedded Files Attachment Boundary Current Base

Session: `port-dev-markerpdf-attachments-20260603T093009Z`
Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260603T093009Z`
Base accepted HEAD: `ccdbc8f5f239ec3e14bb71edbef4e8cc79cd8677`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` pulls visible searchable-PDF text through `pdftext.dictionary_output()` and PDFium page text APIs in `marker/pdf/extract_text.py`; attachment payloads and FileSpec metadata are not part of that visible text path:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py

The native PHP boundary remains no-GPU/no-model: attachment preflight must summarize PDF FileSpec rows for WordPress review without executing Python, models, external PDF tools, action targets, or attachment payload text promotion.

## Behavior

`PdfAttachmentExtractor` now carries the FileSpec review fields that the lightweight attachment preflight previously dropped:

- platform filename source from `/UF`, `/F`, `/DOS`, `/Unix`, or `/Mac`, falling back to the name-tree key only when no FileSpec filename exists;
- selected `/EF` stream key, so `/DOS`, `/Mac`, `/Unix`, `/F`, and `/UF` stream choices are reviewable;
- `/AFRelationship` plus standard relationship role/status metadata;
- decoded filter stack, declared-size match state, computed MD5 checksum, and checksum match state from EmbeddedFile `/Params`;
- `attachmentSummary()` still removes raw `bytes` from the WordPress preflight payload.

The red-first focused fixture used `/DOS (LEGACY.CSV)` with `/EF << /DOS 5 0 R >>`, `/AFRelationship /Supplement`, `/ASCIIHexDecode`, and a matching PDF `/Params /CheckSum`. Before this patch the summary fell back to the name-tree key and omitted the relationship/checksum match-state. After the patch it reports `filename=LEGACY.CSV`, `filename_source=DOS`, `ef_key=DOS`, `relationship=Supplement`, `relationship_role=supplemental_representation`, `checksum_matches=true`, and no raw bytes in the summary row.

## Evidence

Focused before/after:

- before: `PdfAttachmentExtractorTest.php` had 4 PASS cases / 35 assertions;
- after: `PdfAttachmentExtractorTest.php` has 5 PASS cases / 59 assertions;
- delta: +1 behavior PASS case, +24 focused assertions.

Commands:

```text
php -l lanes/markerpdf/src/PdfAttachmentExtractor.php && php -l lanes/markerpdf/tests/PdfAttachmentExtractorTest.php && php -l lanes/markerpdf/examples/wordpress-pdf-attachments-preflight.php
No syntax errors detected in lanes/markerpdf/src/PdfAttachmentExtractor.php
No syntax errors detected in lanes/markerpdf/tests/PdfAttachmentExtractorTest.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-attachments-preflight.php

php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS extracts document EmbeddedFiles name tree attachments for WordPress review
PASS reports platform FileSpec names relationship and checksum match state in attachment preflight
PASS extracts page FileAttachment annotation embedded streams with page metadata
PASS summarizes attachments without exposing bytes in WordPress preflight payloads
PASS ignores external file specifications and non-attachment streams
1 test files, 59 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-attachments-preflight.php

php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode((string) file_get_contents($file), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, $file . ": " . json_last_error_msg() . PHP_EOL); exit(1); } echo $file . ": valid JSON\n"; }'
lanes/markerpdf/lane-status.json: valid JSON
lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json: valid JSON

git diff --check -- lanes/markerpdf
passed with no output
```

The smoke emits `attachment_count=2`, `relationship_roles=["base_data_for_visual_presentation"]`, `checksum_matches=[true]`, and no raw attachment bytes in summary rows.

## Non-Overlap

This does not repeat the accepted full `PdfEmbeddedFileExtractor` Portfolio, catalog `/AF`, FileSpec `/PieceInfo`, attachment-local XMP/OutputIntent, page-associated-file, rich-media FileSpec, or security action FileSpec slices. The new behavior is only the lightweight `PdfAttachmentExtractor` preflight summary boundary for platform FileSpec names, `/EF` key selection, `/AFRelationship`, filter, declared-size, and checksum match-state metadata.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP PDF object/value parser, FileSpec dictionary parsing, stream filter decoding, and WordPress smoke pattern. Full upstream parity remains intentionally outside this worker because live OCR, Surya/Texify/Torch model execution, PDFium rendering, table-model inference, and exact model benchmarks require GPU/model/external runtime paths that are excluded by the current markerPDF scope.
