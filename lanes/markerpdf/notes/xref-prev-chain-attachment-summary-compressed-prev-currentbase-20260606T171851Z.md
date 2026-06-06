# Xref Prev Chain Attachment Summary Compressed Prev Current Base

Slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260606T171851Z`

Accepted base: `1eadbc21a9035a80b42c4cd6fea8780a0e3f7c72`

## Source Truth

Upstream markerPDF delegates searchable-PDF parsing to pdftext/PDFium. In this native PHP lane, the in-scope behavior is the parser/dependency boundary: xref streams, object streams, attachments, metadata, and WordPress-safe preflight. OCR, Surya, Texify, Torch, model workers, and live benchmark parity remain intentionally out of scope.

PDF incremental updates can point an xref-stream `/Prev` operand at an indirect scalar helper. When that helper is a safe numeric member of a PDF 1.5 `/ObjStm`, the latest xref rows must resolve it before repairing same-generation current update rows. Otherwise current catalog/name-tree/FileSpec rows with damaged or omitted offsets stay unresolved and attachment preflight reports no files even though the embedded-file extractor can recover the payload.

## Implementation

`PdfAttachmentExtractor::objectStreamMemberBody()` now decodes `/ObjStm` carrier streams with a dedicated `decodedObjectStreamBytes()` helper. The helper reuses the existing bounded filter stack but only accepts `/Type /ObjStm` carriers.

Attachment payload decoding still goes through `decodedStreamBytes()`, which continues to require EmbeddedFile-compatible stream dictionaries. This keeps attachment bytes gated while allowing xref helper object streams to be expanded for `/Prev` repair.

The WordPress xref Prev-chain example now includes an attachment-only compressed `/Prev 30 0 R` smoke branch. It verifies that `attachmentSummary()` selects `current-compressed-prev-smoke.xml`, excludes previous attachment names, and records no Python/model/external-PDF execution.

## Evidence

Red-first focused run on this accepted base:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS repairs current metadata and attachments when xref-stream Prev is a compressed object-stream numeric helper
FAIL repairs attachment summary when xref-stream Prev is a compressed object-stream numeric helper
Values are not identical
Expected: 1
Actual: 0
1 test files, 515 assertions, 1 failures
```

Focused green after the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
...
PASS repairs attachment summary when xref-stream Prev is a compressed object-stream numeric helper
...
1 test files, 527 assertions, 0 failures
```

Adjacent xref/object-stream family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainObjectStreamCarrierCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainObjectStreamMetadataCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamNestedHelperObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamIndirectPrevObjectStreamCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
...
5 test files, 631 assertions, 0 failures
```

WordPress smoke after the example update:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-incremental-update-currentbase.php
compressed_object_stream_prev_attachment_summary_selected=true
compressed_object_stream_prev_attachment_description_selected=true
compressed_object_stream_prev_helper_used=true
compressed_object_stream_prev_attachment_no_runtime_execution=true
compressed_object_stream_prev_stale_attachment_excluded=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

Syntax, status, and whitespace:

```text
php -l lanes/markerpdf/src/PdfAttachmentExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfAttachmentExtractor.php

php -l lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-incremental-update-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-incremental-update-currentbase.php

php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
lane-status json ok

git diff --check -- lanes/markerpdf
```

`git diff --check -- lanes/markerpdf` passed with no output. Root harness was not run: isolated micro-slice.

## Non-Overlap

This slice does not repeat metadata extraction, embedded-file extraction, classic `/Prev` direct helper repair, xref-stream indirect `W`/`Index` operand repair, stale explicit-offset repair, object-stream carrier repair, trailer encryption, or page-review xref checks. It specifically fixes `PdfAttachmentExtractor::attachmentSummary()` when xref-stream `/Prev` resolves through a compressed object-stream numeric helper.

## Dependency Closure

No new support component is needed. The patch reuses native PHP PDF parsing, object-stream decoding, bounded stream filters, attachment preflight, and existing focused fixtures. It does not execute shell, Python, CUDA, models, OCR, online services, or external PDF tools.

## Next Task

Continue with non-overlapping native markerPDF parser fidelity around xref repair, stream filters, font/CMap text extraction, page geometry, annotations/forms, image/filter metadata, and supplied-boundary table/equation handoffs.
