# markerPDF Annotation FileSpec Attachment Boundary

Session: `port-dev-markerpdf-attachments-20260607T044412Z`
Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260607T044412Z`
Base accepted HEAD: `69d7585618048be7a5327c65ade026da42be2670`

## Source Truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF text through parser-backed PDF text/PDFium paths before OCR/model fallback. Under this no-GPU markerPDF scope, attachment payloads remain a native PHP parser/review boundary, not visible page text.
- PDF FileAttachment annotations carry attachment FileSpec dictionaries through `/FS`. A direct annotation `/FS` dictionary is the same boundary-sensitive FileSpec surface as catalog/page `/AF` and EmbeddedFiles name-tree FileSpecs: duplicate filename keys or duplicate `/EF` stream keys are ambiguous and must fail closed before WordPress attachment review.

## Change

`PdfAttachmentExtractor` now preserves the raw annotation-object `/FS` value when collecting FileAttachment annotations and passes it into the existing FileSpec duplicate-key guard. This keeps direct inline annotation FileSpecs aligned with the already accepted direct EmbeddedFiles name-tree and catalog/page `/AF` FileSpec behavior.

The focused fixture includes:

- one malformed FileAttachment annotation with duplicate direct `/FS` `/F` filename keys;
- one malformed FileAttachment annotation with duplicate direct `/FS /EF /F` stream keys;
- one valid direct annotation FileSpec that must remain importable as review metadata.

After the fix, only `valid-annotation.xml` is summarized, the malformed annotation FileSpecs are excluded, payload bytes are omitted from the WordPress summary row, and embedded XML payload text stays out of visible PDF text.

## Red-First Evidence

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentAnnotationFileSpecBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL fails closed on direct FileAttachment annotation FileSpec duplicate keys before WordPress attachment review (lanes/markerpdf/tests/PdfAttachmentAnnotationFileSpecBoundaryCurrentBaseTest.php)
Values are not identical
Expected: 1
Actual: 3

1 test files, 1 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentAnnotationFileSpecBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed on direct FileAttachment annotation FileSpec duplicate keys before WordPress attachment review

1 test files, 41 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentAnnotationFileSpecBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentAnnotationPresentationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentDirectAssociatedFileSpecBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentDirectNameTreeFileSpecBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentDirectEscapedDuplicateKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php
Focused test run: 6 selected test files (root lock skipped)
26 PASS cases

6 test files, 701 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachment*CurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFile*CurrentBaseTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
Focused test run: 45 selected test files (root lock skipped)
95 PASS cases

45 test files, 3345 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-annotation-filespec-boundary-currentbase.php
```

The smoke exits `0` and emits `attachment_count=1`, `filenames=["valid-annotation.xml"]`, `duplicate_annotation_filespec_rejected=true`, `duplicate_annotation_ef_rejected=true`, `valid_annotation_kept=true`, `payload_bytes_omitted_from_summary=true`, `payload_text_excluded_from_visible_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

```text
php -l lanes/markerpdf/src/PdfAttachmentExtractor.php
php -l lanes/markerpdf/tests/PdfAttachmentAnnotationFileSpecBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-attachment-annotation-filespec-boundary-currentbase.php
```

All reported `No syntax errors detected`.

```text
php -r 'foreach (["lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json", "lanes/markerpdf/lane-status.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file, " valid\n"; }'
lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json valid
lanes/markerpdf/lane-status.json valid
```

```text
git diff --check -- lanes/markerpdf
```

No output.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted EmbeddedFiles name-tree direct FileSpec duplicate-key handling, catalog/page `/AF` direct FileSpec duplicate-key handling, escaped duplicate-key handling, indirect FileSpec duplicate-key handling, `/Names` limits pruning, FileAttachment annotation presentation metadata, catalog/page `/AF` mirror dedupe, encrypted `/EFF` suppression, related-file `/RF` rows, EOF-bounded attachment scanning, object-stream attachment selection, or xref `/Prev` generation repair. The bounded behavior is only raw duplicate-key preservation for direct FileSpec dictionaries appearing under FileAttachment annotation `/FS` entries in the lightweight attachment summary path.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, raw dictionary-value parser, FileSpec parser, stream decoder, attachment summary, text fallback payload exclusion, and WordPress smoke path. Full upstream runtime/model parity remains intentionally out of scope under the no-GPU markerPDF directive because live OCR, Surya/Texify/Torch model execution, PDFium rendering, table-model inference, Streamlit/FastAPI workers, and external OCR/rendering helpers were not executed.
