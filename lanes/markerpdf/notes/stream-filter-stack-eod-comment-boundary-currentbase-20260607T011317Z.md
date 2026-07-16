# Stream Filter Stack EOD Comment Boundary

Slice: `markerpdf-stream-filter-stack-boundary-current-base-20260607T011317Z`

Base: `05080f39db5ee2c2bd812547f2fb1754cdd82f98`

## Behavior

The native no-GPU PDF parser now accepts a stream-filter end marker followed by a PDF percent comment when the parser-captured stream ends at that comment. The stream parser consumes the newline before `endstream`, so a valid `~>% comment\nendstream` ASCII85 stack previously looked like an unterminated trailing comment and failed closed.

This patch applies the same boundary rule in:

- `PdfTextExtractor`
- `PdfAttachmentExtractor`
- `PdfEmbeddedFileExtractor`

Valid ASCII85/Flate text and EmbeddedFiles attachment stacks now decode, while raw non-whitespace bytes after filter EOD markers remain rejected by the existing stream-filter stack tests.

## Evidence

- `php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php` => `1 test files / 390 assertions / 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentStreamFilterStackBoundaryCurrentBaseTest.php` => `1 test files / 253 assertions / 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackOverdeclaredLengthCurrentBaseTest.php` => `4 test files / 674 assertions / 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-attachment-stream-filter-stack-boundary-currentbase.php` reports `eod_comment_attachment_decoded=true`, payload bytes omitted from summary, payload/comment excluded from visible text, and no Python/model/OCR or external PDF tools.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP stream-filter decoders and keeps GPU/model/OCR execution out of scope under the current markerPDF lane directive.

## Non-Overlap

This does not repeat existing null-filter, DecodeParms, short-length, overdeclared-length, RunLength/LZW EOD, dictionary-filter, duplicate-key, or raw post-EOD rejection slices. It only covers the parser boundary where a valid PDF percent comment ends at the captured stream boundary before `endstream`.
