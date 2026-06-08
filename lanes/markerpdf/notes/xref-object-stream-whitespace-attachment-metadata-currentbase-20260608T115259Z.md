# markerPDF object-stream whitespace attachment/metadata boundary

Micro-slice: `markerpdf-object-stream-xref-parser-current-base-20260608T115259Z`
Session: `port-dev-markerpdf-object-xref-20260608T115259Z`
Base accepted HEAD: `ef204610238d00e07d53becb139e28941de74b31`

## Source Truth

markerPDF delegates searchable PDF parsing to `pdftext`/PDFium before OCR/model stages. Under the current no-GPU scope, this lane owns the native PHP PDF 1.5 object-stream and xref parser boundary before WordPress attachment and metadata review. PDF object-stream member offsets are relative to the first object byte and must identify the start of an object token, not PDF whitespace before another token.

## Behavior

This patch aligns the independent attachment, embedded-file, and metadata object-stream member boundary checks with the existing text parser rule:

- a type-2 xref row whose member offset lands on whitespace is rejected;
- a current compressed FileSpec member before the bad offset remains importable;
- whitespace-owned decoy FileSpecs do not enter attachment summaries or embedded-file payload extraction;
- whitespace-owned catalog viewer-preference dictionaries do not become document metadata;
- native parser review still reports the invalid member offset without running Python, models, OCR, PDF actions, JavaScript, or external PDF tools.

## Red-First Evidence

Before the implementation, the focused test failed:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamWhitespaceAttachmentMetadataCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects whitespace-owned object-stream FileSpec offsets before attachment review
Values are not identical
Expected: 1
Actual: 2
FAIL rejects whitespace-owned object-stream catalog metadata operands before import metadata
Values are not identical
Expected: false
Actual: true

1 test files, 11 assertions, 2 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamWhitespaceAttachmentMetadataCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects whitespace-owned object-stream FileSpec offsets before attachment review
PASS rejects whitespace-owned object-stream catalog metadata operands before import metadata

1 test files, 56 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-whitespace-attachment-metadata-currentbase.php
```

Result: emits `current_attachment_kept=true`, `decoy_attachment_excluded=true`, `current_payload_available_to_embedded_review=true`, `payload_bytes_omitted_from_attachment_summary=true`, `catalog_metadata_kept=true`, `whitespace_viewer_preferences_excluded=true`, `attachment_invalid_member_offset_rejection_count=1`, `metadata_invalid_member_offset_rejection_count=1`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted text-only whitespace object-stream offsets, malformed `/First`, later bad offsets inside dictionaries, nested dictionary offsets, duplicate offsets, member-tail rejection, free carrier entries, xref-stream `/Prev` repair, hybrid `/XRefStm` precedence, or image/filter/annotation/form coverage. The bounded behavior is only uniform fail-closed whitespace member offsets in the attachment, embedded-file, and metadata object-stream consumers.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF tokenizer, xref-stream parser, object-stream decoder, attachment and embedded-file review extractors, metadata extractor, and WordPress smoke path. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, external OCR/rendering helpers, Streamlit/FastAPI workers, and exact upstream model benchmark parity remain intentionally out of scope.
