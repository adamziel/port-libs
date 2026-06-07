# markerPDF direct EmbeddedFiles name-tree FileSpec boundary

Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260607T000037Z`

Base accepted HEAD: `6d04ff33b7840d32f2f83f995941f5ec6af06983`

## Source Truth

- Upstream markerPDF delegates searchable-PDF text and PDF attachment/file review to parser-backed PDF loading before OCR/model fallback. Under the current no-GPU markerPDF scope, this lane owns native PDF parser preflight for EmbeddedFiles, associated FileSpecs, and WordPress attachment import review.
- PDF EmbeddedFiles name trees carry string/FileSpec key-value pairs. Direct inline FileSpec dictionaries are still parser trust boundaries: duplicate filename keys or duplicate embedded-file reference keys are ambiguous and must fail closed before WordPress import summaries promote attachment metadata.

## Change

`PdfAttachmentExtractor::embeddedFilesNameTreeEntries()` now preserves the raw direct `/Names /EmbeddedFiles` name-tree value from the selected catalog. `nameTreeEntries()` can use that raw node body for direct inline nodes and direct inline kid nodes before it hands FileSpec values to the existing duplicate-key fail-closed checks.

The focused fixture places three direct inline FileSpecs inside a catalog-local EmbeddedFiles name tree:

- a malformed inline FileSpec with duplicate `/F` filename keys;
- a malformed inline FileSpec whose `/EF` dictionary has duplicate `/F` stream keys;
- one valid inline FileSpec sibling that must remain importable.

The attachment summary now returns only `valid-inline-direct.xml`, omits payload bytes from the summary, and keeps duplicate payload bytes out of visible WordPress paragraph text. `PdfEmbeddedFileExtractor` already preserved this raw-node boundary; the patch aligns the lightweight summary path with it.

## Red-First Evidence

Before the source patch, the focused test failed because parsed inline dictionaries had already lost raw duplicate-key evidence:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentDirectNameTreeFileSpecBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL fails closed on direct inline EmbeddedFiles FileSpec duplicate keys before attachment summary import (lanes/markerpdf/tests/PdfAttachmentDirectNameTreeFileSpecBoundaryCurrentBaseTest.php)
Values are not identical
Expected: 1
Actual: 3

1 test files, 1 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentDirectNameTreeFileSpecBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed on direct inline EmbeddedFiles FileSpec duplicate keys before attachment summary import

1 test files, 48 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentDirectNameTreeFileSpecBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentDirectAssociatedFileSpecBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentDirectEscapedDuplicateKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentDirectFileSpecNameTreeMirrorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfEmbeddedFilePageAssociatedBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentEmbeddedFileStreamTypeBoundaryCurrentBaseTest.php
Focused test run: 6 selected test files (root lock skipped)
PASS fails closed on malformed direct catalog and page AF FileSpecs before WordPress attachment review
PASS fails closed on direct inline FileSpec escaped duplicate keys before WordPress attachment preflight
PASS dedupes direct nameless FileSpec mirrors using EmbeddedFiles name-tree filename before attachment import
PASS fails closed on direct inline EmbeddedFiles FileSpec duplicate keys before attachment summary import
PASS excludes typed non-EmbeddedFile EF streams before WordPress attachment import
PASS extracts page associated Filespec entries in EmbeddedFile review and marks name-tree mirrors

6 test files, 308 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachment*CurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFile*CurrentBaseTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
Focused test run: 42 selected test files (root lock skipped)
42 test files, 3146 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-direct-nametree-filespec-boundary-currentbase.php
```

Result: emitted `attachment_count=1`, `filenames=["valid-inline-direct.xml"]`, `duplicate_inline_filespec_rejected=true`, `duplicate_inline_ef_rejected=true`, `payload_bytes_omitted_from_summary=true`, `duplicate_payloads_excluded=true`, `visible_text_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

```text
php -l lanes/markerpdf/src/PdfAttachmentExtractor.php
php -l lanes/markerpdf/tests/PdfAttachmentDirectNameTreeFileSpecBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-attachment-direct-nametree-filespec-boundary-currentbase.php
jq empty lanes/markerpdf/lane-status.json lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json
git diff --check -- lanes/markerpdf
```

All syntax, JSON, and diff whitespace checks passed.

## Non-Overlap

This does not repeat accepted page-level `/AF` preflight, catalog `/AF` ingestion, direct catalog/page `/AF` duplicate-key handling, indirect name-tree key resolution, direct name-tree FileSpec mirror dedupe, `/Names` limits pruning, indirect `/Names` array resolution, object-stream attachment extraction, xref generation repair, encrypted EFF suppression, RF related-file review, typed non-EmbeddedFile `/EF` rejection, or page-resource inheritance. The bounded behavior is only raw duplicate-key preservation for direct inline FileSpec dictionaries inside direct inline catalog `/Names /EmbeddedFiles` name-tree nodes in the lightweight attachment summary path.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, raw dictionary/array item parser, name-tree walker, FileSpec parser, stream decoder, full embedded-file extractor, text extractor, attachment summary, and WordPress smoke path. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, external OCR/rendering helpers, Streamlit/FastAPI workers, and exact upstream model benchmark parity remain intentionally outside the current no-GPU markerPDF scope.
