# markerPDF Annotation Associated-File Attachment Boundary Current Base

Session: `port-dev-markerpdf-attachments-20260608T065617Z`
Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260608T065617Z`
Base accepted HEAD: `020e2ea23f5994952f6082bab5de6c073c83d6be`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates searchable-PDF object loading to pdftext/PDFium before OCR/model handoff. Under the current no-GPU markerPDF scope, the native PHP parser owns review-only PDF FileSpec, EmbeddedFiles, and associated-file boundaries for WordPress import.

PDF associated-file arrays are not limited to catalog or page dictionaries. Annotation dictionaries can carry `/AF` FileSpec arrays too; those embedded payloads are provenance/review attachments, not visible page text and not executable annotation actions.

## Implementation

- `PdfAttachmentExtractor` now walks annotation `/AF` arrays on page annotations and emits `annotation-associated-file` summary rows with page number, page object, annotation object, subtype, contents, rectangle, and existing annotation review flags.
- Existing EmbeddedFiles/catalog/page attachment rows can now accept annotation `/AF` mirror metadata when they point to the same FileSpec and EmbeddedFile stream.
- `PdfEmbeddedFileExtractor` now includes annotation `/AF` rows in the full embedded-file inventory and merges annotation-associated metadata onto name-tree mirrors.
- Duplicate or tailed annotation `/AF` dictionaries fail closed by reusing the existing associated-file raw dictionary boundary guard.
- The WordPress smoke renders visible page text and file blocks from summary metadata while keeping raw payload bytes out of the summary and visible paragraphs.

## Evidence

Red-first after adding the focused test, before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentAnnotationAssociatedFileBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL extracts annotation AF associated files and merges name-tree mirrors before WordPress review (lanes/markerpdf/tests/PdfAttachmentAnnotationAssociatedFileBoundaryCurrentBaseTest.php)
Values are not identical
Expected: 2
Actual: 1

1 test files, 1 assertions, 1 failures
```

Green after the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentAnnotationAssociatedFileBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS extracts annotation AF associated files and merges name-tree mirrors before WordPress review

1 test files, 64 assertions, 0 failures
```

Adjacent attachment inventory run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentAnnotationAssociatedFileBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentAnnotationFileSpecBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentAnnotationPresentationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfEmbeddedFilePageAssociatedBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentAssociatedFileArrayBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
Focused test run: 7 selected test files (root lock skipped)
7 test files, 1173 assertions, 0 failures
```

Broad attachment/embedded-file family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/Pdf(Attachment|EmbeddedFile).*Test\.php$' | sort)
Focused test run: 59 selected test files (root lock skipped)
59 test files, 4395 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-annotation-associated-file-boundary-currentbase.php
```

The smoke exits 0 and reports `attachment_count=2`, `filenames=["annotation-mirror-smoke.xml","annotation-only-smoke.xml"]`, `annotation_af_mirror_merged=true`, `annotation_only_checksum_matches=true`, `duplicate_annotation_af_suppressed=true`, `summary_exposes_payload_bytes=false`, `visible_text_excludes_attachment_payloads=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted catalog `/AF`, page `/AF`, FileAttachment `/FS`, FileAttachment presentation metadata, direct FileSpec duplicate-key rejection, `/AFRelationship` duplicate/trailing guards, name-tree `/Limits`, duplicate name-tree keys, related-file `/RF`, stream-filter, encrypted EFF, portfolio/Collection, PieceInfo, xref/object-stream attachment repair, StructTree-associated annotation review, action review, OCR/model, or raster behavior.

The bounded behavior is only annotation dictionary `/AF` associated-file arrays feeding attachment review and embedded-file inventory paths.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object parser, page/annotation walkers, associated-file raw dictionary guards, FileSpec parser, EmbeddedFile stream decoder, attachment summary builder, embedded-file inventory, text extractor, and WordPress smoke renderer. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, decryption, PDF action execution, external PDF tools, Streamlit/FastAPI workers, and exact upstream model benchmark parity remain intentionally outside the no-GPU markerPDF scope.
