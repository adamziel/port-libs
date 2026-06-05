# markerPDF Embedded Files Attachment Duplicate Name-Tree Boundary

Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260605T081646Z`

Base accepted HEAD: `334b4c81d856226190cb2ceeada617f964464a14`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps attachment payloads outside the visible searchable-PDF text path; visible extraction flows through `pdftext.dictionary_output()` / page text APIs, not FileSpec payload text.
- PDF EmbeddedFiles name trees are sorted key/value maps. Duplicate keys are malformed for review preflight; WordPress import should keep the first selected FileSpec row and skip stale duplicate-key rows before counting attachments or exposing payload hashes.
- Current markerPDF lane scope remains native no-GPU/no-model PDF parser behavior. No Python, OCR/model, PDFium rendering, or external PDF tools are executed.

## Behavior

`PdfAttachmentExtractor` now dedupes catalog `/Names /EmbeddedFiles` name-tree entries by name before building lightweight attachment summary rows. A duplicate `/Names` key keeps the first FileSpec and skips later same-key rows, preventing stale duplicate-key FileSpecs from inflating `attachment_count`, `total_bytes`, `filenames`, checksum metadata, or WordPress smoke output.

The focused fixture uses:

- `/Names [(review.csv) 4 0 R (review.csv) 6 0 R]`;
- object `4 0 R` as the current `current-review.csv` FileSpec;
- object `6 0 R` as a stale `stale-duplicate-review.csv` FileSpec with attachment payload text that must stay omitted.

## Evidence

Red-first focused run after adding the test and before implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps first EmbeddedFiles duplicate name-tree key before stale duplicate attachment rows
Expected: 1
Actual: 2
1 test files, 415 assertions, 1 failures
```

Focused run after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php
1 test files, 435 assertions, 0 failures
```

Adjacent guard:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
2 test files, 825 assertions, 0 failures
```

Smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-attachments-preflight.php
```

The smoke emits `duplicate_name_tree_key_pruned=true`, `attachment_count=7`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax/JSON/whitespace verification:

```text
php -l lanes/markerpdf/src/PdfAttachmentExtractor.php
php -l lanes/markerpdf/tests/PdfAttachmentExtractorTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-attachments-preflight.php
php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode((string) file_get_contents($file), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, $file . ": " . json_last_error_msg() . PHP_EOL); exit(1); } echo $file . ": valid JSON\n"; }'
git diff --check -- lanes/markerpdf
```

## Non-Overlap

This does not repeat accepted platform FileSpec filename selection, `/AFRelationship` role mapping, checksum review, related-file review, terminal EOF object bounds, xref row selection, catalog/page `/AF` mirror marking, FileAttachment annotation presentation metadata, encrypted embedded-file suppression, or full `PdfEmbeddedFileExtractor` Portfolio/PieceInfo/XMP/OutputIntent metadata behavior.

The new behavior is only the lightweight `PdfAttachmentExtractor` preflight boundary for duplicate catalog EmbeddedFiles name-tree keys.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP object parser, name-tree traversal, FileSpec parsing, stream decoding, checksum review, and existing WordPress smoke. Full OCR/model/PDFium parity remains intentionally out of scope under the current no-GPU markerPDF direction.
