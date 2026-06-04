# markerPDF Page Associated Attachment Preflight Current Base

Session: `port-dev-markerpdf-attachments-20260604T210100Z`
Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260604T210100Z`
Base accepted HEAD: `c674c1795b84c61024a12ba621d4f2f182064509`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` extracts visible searchable-PDF text through `pdftext.dictionary_output()` and pypdfium page text APIs in `marker/pdf/extract_text.py`; attachment FileSpec payload bytes are not part of that visible text path:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py

PDF 2.0 associated files can be attached to document objects such as pages through `/AF`, while EmbeddedFiles name-tree entries remain a compatibility/discoverability path. The lightweight native PHP preflight should therefore summarize page-associated FileSpec rows for WordPress review, merge name-tree mirrors, and keep payload bytes out of visible text and summary JSON.

## Behavior

`PdfAttachmentExtractor` now collects page-level `/AF` FileSpec arrays after catalog `/AF` rows and before FileAttachment annotations.

Page-associated rows carry:

- `source=page-associated-file`
- `associated_file=true`
- `page_associated_file=true`
- page number, page object id, and page `/AF` array index
- the existing FileSpec filename, `/EF` key, `/AFRelationship`, content type, size, checksum, filter, and timestamp review metadata

When the same FileSpec and embedded stream are also listed through `/Names /EmbeddedFiles`, the preflight keeps one `embedded-files-name-tree` row and marks it with `page_associated_file_source=page_af` plus page context. This mirrors the catalog `/AF` dedupe behavior and avoids double-counting common associated-file mirror shapes.

## Red/Green Evidence

Red baseline after adding the focused page `/AF` case:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL summarizes page associated FileSpec attachments and marks EmbeddedFiles mirrors
Expected: 1
Actual: 0
1 test files, 143 assertions, 1 failures
```

After patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS extracts document EmbeddedFiles name tree attachments for WordPress review
PASS reports platform FileSpec names relationship and checksum match state in attachment preflight
PASS prunes out-of-limits EmbeddedFiles name-tree attachments in WordPress preflight
PASS summarizes catalog associated FileSpec attachments and dedupes EmbeddedFiles mirrors
PASS summarizes page associated FileSpec attachments and marks EmbeddedFiles mirrors
PASS summarizes related-file streams in WordPress attachment preflight without bytes
PASS extracts page FileAttachment annotation embedded streams with page metadata
PASS summarizes attachments without exposing bytes in WordPress preflight payloads
PASS ignores external file specifications and non-attachment streams
1 test files, 178 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-attachments-preflight.php
```

Emits `attachment_count=4`, `filenames=["review-notes.csv","source.xml","page-source.xml","review-note.txt"]`, `page_associated_file_preflight=true`, `page_associated_file_payload_omitted=true`, and `executes_python_or_models=false` / `executes_external_pdf_tools=false`.

Focused delta:

- `PdfAttachmentExtractorTest.php`: +1 PASS case over the prior 8-case file.
- Focused assertions: red-first 143 assertions with 1 failure -> 178 assertions with 0 failures.
- Expected lane movement: `phpPass` and WordPress scenarios `1091 -> 1092`.

## Non-Overlap

This does not repeat accepted EmbeddedFiles name-tree extraction, name-tree `/Limits` pruning, platform filename source selection, `/EF` key selection, catalog `/AF` ingestion/dedupe, FileAttachment annotations, `/AFRelationship` role mapping, Params checksum match-state, related-file `/RF` summaries, page review associated-file metadata, StructTree associated-file rows, Portfolio `/Collection`, PieceInfo, XMP/OutputIntent, or security action FileSpec slices.

The bounded behavior is only page-level `/AF` associated FileSpec ingestion in the lightweight `PdfAttachmentExtractor` WordPress preflight plus EmbeddedFiles mirror marking.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP object/value parser, page tree traversal, FileSpec dictionary parsing, stream filter decoding, checksum review, and WordPress smoke pattern. Full live OCR, Surya/Texify/Torch model execution, PDFium rendering, table-model inference, and exact upstream model benchmark parity remain intentionally outside the current no-GPU markerPDF scope.
