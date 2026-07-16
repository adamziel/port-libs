# markerPDF Embedded Files Attachment Type-Key Boundary Current Base

Session: `port-dev-markerpdf-attachments-20260609T000817Z`
Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260609T000817Z`
Base accepted HEAD: `35d557737dc1b88c45279aeb585788c53834812d`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps searchable-PDF text extraction behind pdftext/PDFium in `marker/pdf/extract_text.py`; embedded file payloads are document artifacts, not visible Markdown text. The native no-GPU PHP lane therefore treats FileSpec and EmbeddedFile stream metadata as WordPress review/preflight rows while keeping payload bytes and ambiguous private dictionaries out of visible blocks.

PDF dictionaries use `/Type` to gate whether a dictionary is a FileSpec and whether an `/EF` stream is an EmbeddedFile. Duplicate `/Type` or `/Subtype` declarations are ambiguous attachment boundaries. Before this slice, a dictionary ending with `/Type /Filespec` or `/Type /EmbeddedFile` could pass even if an earlier `/Type` marked it as another object kind.

## Implementation

- `PdfAttachmentExtractor` now treats FileSpec `/Type` and EmbeddedFile stream `/Type` and `/Subtype` as boundary keys for duplicate and trailing-operand rejection.
- `PdfEmbeddedFileExtractor` uses the same boundary keys, so lightweight WordPress attachment summaries and full embedded-file extraction agree.
- The focused fixture includes one valid attachment plus three ambiguous decoys:
  - FileSpec dictionary with duplicate `/Type /Catalog /Type /Filespec`;
  - stream dictionary with duplicate `/Type /Metadata /Type /EmbeddedFile`;
  - stream dictionary with duplicate `/Subtype /application#2Fpdf /Subtype /text#2Fxml`.

## Evidence

Red-first:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentTypeKeyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects duplicate FileSpec and EmbeddedFile stream Type keys before WordPress attachment review (lanes/markerpdf/tests/PdfAttachmentTypeKeyBoundaryCurrentBaseTest.php)
Values are not identical
Expected: 1
Actual: 4

1 test files, 1 assertions, 1 failures
```

After patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentTypeKeyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects duplicate FileSpec and EmbeddedFile stream Type keys before WordPress attachment review

1 test files, 72 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-type-key-boundary-currentbase.php
```

Exited 0 and emitted `attachment_count=1`, `filenames=["valid-type.xml"]`, `duplicate_filespec_type_excluded=true`, `duplicate_stream_type_excluded=true`, `duplicate_stream_subtype_excluded=true`, `payload_bytes_omitted_from_summary=true`, `visible_text_excludes_attachment_payloads=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted platform filename source selection, `/EF` key order, unknown/private `/EF` key rejection, FileSpec `/Desc` and metadata operand boundaries, related-file `/RF` rows, name-tree limits and duplicate names, catalog/page/annotation associated-file mirrors, stream filter DecodeParms/fake-endstream boundaries, encrypted EFF preflight, xref/object-stream attachment selection, or Portfolio/PieceInfo/XMP/OutputIntent attachment metadata. The bounded behavior is only duplicate `/Type` and `/Subtype` gates for FileSpec and EmbeddedFile stream acceptance.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, dictionary boundary parser, FileSpec resolver, EmbeddedFile stream decoder, attachment summary path, full embedded-file extractor, text extractor, and WordPress smoke pattern. GPU/OCR/model execution, Python workers, pypdfium/PDFium rendering, external PDF tools, and live services remain intentionally out of scope.
