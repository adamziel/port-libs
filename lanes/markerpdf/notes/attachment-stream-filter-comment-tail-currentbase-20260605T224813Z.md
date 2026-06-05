# markerPDF attachment stream-filter comment-tail boundary

Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260605T224813Z`

## Source Truth

- Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF parsing and attachment/image review through PDF parser dependencies before OCR/model stages.
- PDF comments are lexical whitespace. For explicit-end stream filters such as `ASCII85Decode`, `ASCIIHexDecode`, and `RunLengthDecode`, a comment-only tail after the filter EOD marker is still a bounded stream tail; arbitrary non-comment bytes after the EOD marker remain unsafe and fail closed.

## Behavior

`PdfAttachmentExtractor` and `PdfEmbeddedFileExtractor` now accept PDF whitespace plus `%...EOL` comments after explicit filter terminators when validating EmbeddedFile stream stacks. This lets valid attachment streams like:

```text
/Filter [ /ASCII85Decode /FlateDecode ]
stream
...~>
% producer comment
endstream
```

decode through native PHP attachment review while streams with real bytes after the filter terminator still fail closed and stay out of WordPress attachment summaries, extracted embedded-file payloads, and visible page text.

## Evidence

Red-first focused run before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentStreamFilterTerminatorBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects attachment streams with non-whitespace bytes after filter terminators
Expected: 5
Actual: 2
1 test files, 1 assertions, 1 failures
```

Focused pass after the patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentStreamFilterTerminatorBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects attachment streams with non-whitespace bytes after filter terminators
PASS accepts PDF comments after explicit attachment filter terminators before WordPress review
1 test files, 123 assertions, 0 failures
```

Adjacent attachment gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentStreamFilterTerminatorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileAttachmentGenerationBoundaryCurrentBaseTest.php
4 test files, 702 assertions, 0 failures
```

Embedded-file/predictor gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileEofBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentStreamFilterPredictorCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentStreamFilterTerminatorBoundaryCurrentBaseTest.php
4 test files, 664 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-stream-filter-terminator-boundary-currentbase.php
```

The smoke emitted `attachment_count=5`, `comment_tail_attachments_selected=true`, `embedded_payloads_available_to_review=true`, all surplus decoys excluded, `payload_bytes_omitted_from_summary=true`, `visible_text_kept_clean=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted page-content stream filter stack recovery, parser-comment split indirect references, default/identity Crypt semantics, null-filter DecodeParms alignment, extra/nested DecodeParms fail-closed behavior, LZW/RunLength EOD detection, CCITT/DCT/JPX/JBIG2 image review boundaries, or CMap-specific filter handling.

The bounded behavior is only comment-only bytes after explicit attachment/EmbeddedFile stream filter terminators before WordPress attachment review.

## Dependency Closure

No new support component is needed. This reuses the native PHP object scanner, attachment stream parser, filter-stack decoder, checksum review, embedded-file extractor, and WordPress smoke path. No GPU/model execution, Python bridge, PDF renderer, OCR/model worker, or external PDF tool is required.
