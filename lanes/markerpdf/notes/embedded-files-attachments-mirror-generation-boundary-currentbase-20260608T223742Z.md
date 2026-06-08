# markerPDF EmbeddedFiles Attachment Mirror Generation Boundary

Slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260608T223742Z`
Base accepted HEAD: `8e2529b80fc9ffeb2e0df1c830fae9092042b225`

## Source Truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps embedded-file payloads outside visible searchable-PDF text extraction.
- PDF indirect references are generation-qualified. `4 0 R` and `4 1 R` are distinct FileSpec objects, and `5 0 R` and `5 1 R` are distinct EmbeddedFile streams even when the object numbers match.
- This slice stays in the native no-GPU scope: attachment review only, without OCR, Surya, Texify, Torch, PDFium rendering, decryption, or external PDF tools.

## Implementation

- `PdfAttachmentExtractor` now records `file_spec_object_generation` and `stream_object_generation` on attachment summary rows when references provide them.
- Attachment mirror dedupe now requires matching known stream generations, and matching FileSpec generations when both mirrored rows have FileSpec object IDs.
- `PdfEmbeddedFileExtractor` now records `file_spec_generation` and `embedded_file_generation` and includes them in embedded-file dedupe keys.
- Added `PdfAttachmentMirrorGenerationBoundaryCurrentBaseTest.php`.
- Added `wordpress-pdf-attachment-mirror-generation-boundary-currentbase.php`.

## Evidence

Red probe before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentMirrorGenerationBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps same-object-number generation distinct FileSpec mirrors separate before WordPress attachment review
Values are not identical
Expected: 2
Actual: 1

1 test files, 1 assertions, 1 failures
```

Focused checks after the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentMirrorGenerationBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps same-object-number generation distinct FileSpec mirrors separate before WordPress attachment review

1 test files, 44 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentMirrorGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentEfStreamGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfEmbeddedFileGeneratedMirrorRelationshipBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentAssociatedRelationshipMirrorCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentGenerationReferenceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php
Focused test run: 7 selected test files (root lock skipped)
7 test files, 1102 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachment*Test.php lanes/markerpdf/tests/PdfEmbeddedFile*Test.php lanes/markerpdf/tests/PdfEmbeddedFiles*Test.php
Focused test run: 78 selected test files (root lock skipped)
78 test files, 5663 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-mirror-generation-boundary-currentbase.php
```

The smoke exits 0 and emits `attachment_count=2`, `file_spec_generations=[0,1]`, `stream_generations=[0,1]`, `payload_bytes_omitted_from_summary=true`, `visible_text_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- PHP behavior tests: `3551 -> 3552`.
- WordPress scenarios: `2868 -> 2869`.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, generation-aware reference resolver, FileSpec parser, EmbeddedFile stream decoder, attachment mirror dedupe, embedded-file inventory, attachment summary sanitizer, and text extraction boundary. GPU/model/OCR/PDFium parity remains intentionally out of scope for this markerPDF lane.

## Non-Overlap

This does not repeat accepted EF stream generation-exact selection, xref generation repair, page/catalog/annotation/structure associated-file extraction, relationship mirror metadata, FileSpec duplicate-key fail-closed behavior, encrypted EFF handling, or stream filter/DecodeParms boundaries. The new behavior is only preventing same-object-number FileSpec and EmbeddedFile mirrors from being deduped across different object generations.
