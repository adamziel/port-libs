# markerPDF Attachment Dictionary Filter Boundary

Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260605T182543Z`

## Source Truth

- Upstream markerPDF routes searchable PDF and attachment-facing import decisions through native PDF parsing before any OCR/model handoff.
- PDF stream `/Filter` values are a filter name or an array of filter names. A dictionary-valued `/Filter << ... >>` is malformed and must not be treated as an absent identity stack.
- WordPress attachment preflight should fail closed on malformed stream-filter operands so invalid EmbeddedFile payloads cannot be counted as importable attachments.

## Behavior

`PdfAttachmentExtractor::filterSlots()` now distinguishes absent or explicit `null` `/Filter` values from present malformed operands. Dictionary, scalar, unresolved reference, and other non-name/non-array filter values now reject the attachment payload before summary rows, declared-size checks, or checksum review.

`PdfEmbeddedFileExtractor` already rejected this shape, so the change aligns the lightweight attachment-summary path with the stricter embedded-file payload extractor.

## Red Probe

Before the source edit, the focused test failed because `attachmentSummary()` counted a malformed EmbeddedFile stream:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentStreamFilterStackBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS treats Identity Crypt as a byte-preserving attachment stream stack stage while rejecting private crypt filters
FAIL rejects dictionary-valued attachment Filter operands before summary or payload extraction
Values are not identical
Expected: 0
Actual: 1
1 test files, 36 assertions, 1 failures
```

The in-memory probe showed `summary_count=1` and `summary_names=["dict-filter.csv"]` while `PdfEmbeddedFileExtractor` returned zero files.

## Evidence

Focused attachment stream-filter stack test after the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentStreamFilterStackBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS treats Identity Crypt as a byte-preserving attachment stream stack stage while rejecting private crypt filters
PASS rejects dictionary-valued attachment Filter operands before summary or payload extraction
1 test files, 50 assertions, 0 failures
```

Adjacent attachment and EmbeddedFile filter family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentStreamFilterPredictorCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentStreamFilterTerminatorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
5 test files, 1075 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-stream-filter-stack-boundary-currentbase.php
```

The smoke emits `dictionary_filter_attachment_rejected=true`, `dictionary_filter_filename_excluded=true`, `dictionary_filter_payload_excluded=true`, `dictionary_filter_visible_text_preserved=true`, `identity_crypt_stage_applied=true`, `private_crypt_payload_suppressed=true`, `payload_bytes_omitted_from_summary=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted page-content ASCII85/Flate stream-boundary recovery, RunLength/LZW EOD recovery, null-filter DecodeParms alignment, compact DecodeParms arrays, Identity/private Crypt content-stream behavior, embedded-file ASCII85/RunLength decoding, attachment predictor DecodeParms, attachment stream terminator rejection, CMap dictionary filter review, object-stream filter ownership, xref-stream DecodeParms recovery, image filters, inline-image tokenizer boundaries, or encrypted PDF preflight.

The bounded behavior is specifically dictionary-valued `/Filter` operands in the WordPress attachment-summary path before EmbeddedFile payload import.

## Dependency Closure

No new support component is needed. This reuses the native PDF value parser, attachment FileSpec walker, stream-filter slot resolver, EmbeddedFile payload extractor, and WordPress smoke renderer. Live OCR, Surya/Texify/Torch model execution, pypdfium/PDFium rendering, external PDF tools, and exact upstream model benchmark parity remain outside the no-GPU markerPDF scope.
