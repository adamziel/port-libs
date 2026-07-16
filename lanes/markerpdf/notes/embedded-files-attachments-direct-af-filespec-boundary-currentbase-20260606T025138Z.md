# markerPDF direct /AF FileSpec duplicate-key boundary

Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260606T025138Z`

Base accepted HEAD: `4895f6e532bd4f8ad5a12241fb97221375cc8ec2`

## Source Truth

- Upstream markerPDF delegates searchable-PDF text import to PDF text/PDFium paths before OCR/model stages. Under the current no-GPU markerPDF scope, this lane owns native PDF parser preflight for associated files, EmbeddedFiles, and WordPress attachment review.
- PDF FileSpec dictionaries and their `/EF` dictionaries are boundary-sensitive. Duplicate filename or embedded-file stream keys are ambiguous and must fail closed before attachment rows are promoted into WordPress import metadata.

## Change

`PdfAttachmentExtractor` now preserves raw direct FileSpec values from catalog `/AF` and page `/AF` arrays before calling the existing duplicate-key fail-closed guard. This aligns lightweight attachment summaries with `PdfEmbeddedFileExtractor`, which already processes raw `/AF` array items.

The focused fixture includes:

- a malformed direct catalog `/AF` FileSpec with duplicate `/F` keys;
- a malformed direct page `/AF` FileSpec with duplicate `/EF /F` keys;
- one valid direct catalog-associated FileSpec that must remain importable.

The summary now returns only `valid-direct-af.xml`, marks it as a catalog associated file, omits payload bytes from the summary, and excludes all attachment payload text from visible WordPress paragraphs.

## Red-First Evidence

Before the source patch, the new focused test failed because the parsed direct `/AF` FileSpec arrays had already lost raw duplicate-key information:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentDirectAssociatedFileSpecBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL fails closed on malformed direct catalog and page AF FileSpecs before WordPress attachment review (lanes/markerpdf/tests/PdfAttachmentDirectAssociatedFileSpecBoundaryCurrentBaseTest.php)
Values are not identical
Expected: 1
Actual: 3

1 test files, 1 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentDirectAssociatedFileSpecBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed on malformed direct catalog and page AF FileSpecs before WordPress attachment review

1 test files, 52 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentDirectAssociatedFileSpecBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentDirectEscapedDuplicateKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentDirectFileSpecNameTreeMirrorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfEmbeddedFilePageAssociatedBoundaryCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
PASS fails closed on malformed direct catalog and page AF FileSpecs before WordPress attachment review
PASS fails closed on direct inline FileSpec escaped duplicate keys before WordPress attachment preflight
PASS dedupes direct nameless FileSpec mirrors using EmbeddedFiles name-tree filename before attachment import
PASS extracts page associated Filespec entries in EmbeddedFile review and marks name-tree mirrors

4 test files, 195 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachment*CurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFile*CurrentBaseTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
Focused test run: 35 selected test files (root lock skipped)
...
35 test files, 2708 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-direct-af-filespec-boundary-currentbase.php
```

The smoke exits `0` and emits `direct_catalog_af_duplicate_rejected=true`, `direct_page_af_duplicate_rejected=true`, `valid_direct_af_kept=true`, `payload_bytes_omitted_from_summary=true`, `payload_text_excluded_from_visible_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

```text
php -l lanes/markerpdf/src/PdfAttachmentExtractor.php
php -l lanes/markerpdf/tests/PdfAttachmentDirectAssociatedFileSpecBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-attachment-direct-af-filespec-boundary-currentbase.php
```

All reported `No syntax errors detected`.

## Non-Overlap

This does not repeat accepted name-tree direct FileSpec duplicate-key handling, indirect FileSpec duplicate-key handling, `/Names` limits pruning, page `/AF` mirror marking, catalog `/AF` ingestion, direct nameless FileSpec mirror dedupe, encrypted EFF suppression, RF related-file review, EOF-bounded attachment scanning, object-stream attachment selection, or xref `/Prev` generation repair. The bounded behavior is only raw duplicate-key preservation for direct FileSpec dictionaries appearing in catalog/page `/AF` arrays in the lightweight attachment summary path.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, raw dictionary/array item parser, FileSpec parser, stream decoder, attachment summary, full embedded-file extractor, and WordPress smoke path. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, external OCR/rendering helpers, Streamlit/FastAPI workers, and exact upstream model benchmark parity remain intentionally outside the current no-GPU markerPDF scope.
