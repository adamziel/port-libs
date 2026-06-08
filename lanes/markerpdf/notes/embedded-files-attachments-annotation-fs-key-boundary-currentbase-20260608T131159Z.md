# markerPDF FileAttachment Annotation FS Key Boundary Current Base

Session: `port-dev-markerpdf-attachments-20260608T131159Z`
Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260608T131159Z`
Base accepted HEAD: `d6ec1fb5ef671b6ea22e454e765ca0d7b78582a5`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps visible searchable-PDF text extraction on the `pdftext.dictionary_output()` and PDF page text path. FileAttachment annotation payload streams are not visible body text. In this native no-GPU PHP lane, WordPress preflight summarizes the attachment FileSpec as review metadata without executing Python, OCR/model code, external PDF tools, PDF actions, or embedded payload text.

PDF FileAttachment annotations own their attachment FileSpec through the annotation dictionary `/FS` key. Duplicate escaped `/FS` keys or extra top-level operands after `/FS` are ambiguous owner declarations and must fail closed before the FileSpec is dereferenced.

## Implementation

`PdfAttachmentExtractor::fileAttachmentAnnotationEntries()` now applies a token-boundary guard to the annotation dictionary's `/FS` owner entry before reading the FileSpec. If `/FS` is duplicated, including escaped-name duplicates such as `/#46S`, or if `/FS` leaves extra operands before the next key, the malformed annotation is skipped. Valid FileAttachment annotations continue to import normally.

This preserves WordPress attachment review for well-formed annotations while excluding decoy FileSpecs, filenames, checksums, and payload bytes from malformed annotation owners.

## Red-First Evidence

Before the source patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentAnnotationFsKeyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL fails closed on duplicate and tailed FileAttachment annotation FS keys before WordPress attachment review (lanes/markerpdf/tests/PdfAttachmentAnnotationFsKeyBoundaryCurrentBaseTest.php)
Values are not identical
Expected: 1
Actual: 3

1 test files, 1 assertions, 1 failures
```

After the source patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentAnnotationFsKeyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed on duplicate and tailed FileAttachment annotation FS keys before WordPress attachment review

1 test files, 61 assertions, 0 failures
```

Adjacent annotation and attachment regression sweep:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentAnnotationFsKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentAnnotationFileSpecBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentAnnotationPresentationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentAnnotationAssociatedFileBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php
Focused test run: 5 selected test files (root lock skipped)
25 PASS lines
5 test files, 681 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-annotation-fs-key-boundary-currentbase.php
```

Passed. The smoke emits `attachment_count=1`, `valid_attachment=valid-annotation-fs.xml`, `malformed_annotation_fs_owners_excluded=true`, `payload_text_excluded_from_visible_text=true`, `payload_bytes_omitted_from_summary=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted direct FileSpec duplicate-key handling, direct annotation FileSpec dictionary duplicate handling, `/EF` stream-key duplicate handling, `/AF` associated-file array guards, annotation presentation metadata, annotation associated files, name-tree limits/order/duplicate behavior, encrypted EFF redaction, related-file `/RF`, portfolio/PieceInfo metadata, xref/object-stream attachment selection, or stream-filter decoding. The bounded new behavior is only duplicate and tailed `/FS` owner entries on FileAttachment annotation dictionaries before lightweight attachment summary import.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP PDF object parser, annotation traversal, raw dictionary token scanner, FileSpec parser, EmbeddedFile stream decoder, attachment summary redaction, and WordPress smoke pattern. Full OCR, Surya/Texify/Torch model execution, PDFium rendering, Streamlit/FastAPI model workers, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF direction.
