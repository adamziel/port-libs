# markerpdf-stream-filter-stack-boundary-current-base-20260608T231202Z

## Scope

Native no-GPU markerPDF slice for EmbeddedFiles attachment stream-filter stack
boundaries. The covered behavior is stream-object capture before filter
decoding: if an encoded attachment stream contains a literal `endstream`
sequence before the real filter EOD, the parser must honor an exact declared
direct `/Length` that lands on a valid stream terminator instead of truncating
the object body at the internal marker.

This is a searchable/native PDF parser behavior only. No OCR, Surya, Texify,
Torch, PDFium/PIL, model workers, multiprocessing, or external PDF tools were
invoked.

## Source Truth

PDF stream objects are byte-delimited by `/Length` before filter decoding. The
fixture uses a valid `/EmbeddedFile` stream with `/Filter [ /ASCII85Decode
/FlateDecode ]` and exact direct `/Length`; the ASCII85 wrapper intentionally
contains a literal fake `endstream` marker before the real `~>` EOD. The old
attachment object scanners stopped at that internal marker, so both attachment
summary and embedded-file extraction returned zero rows.

## Implementation

- `PdfAttachmentExtractor` now uses exact direct stream `/Length` during object
  boundary scanning when the declared offset is followed by a valid
  `endstream` terminator. Its stream-body extraction also checks the declared
  terminator before falling back to first-marker scanning, preserving the
  existing short-declared-length complete-stack recovery.
- `PdfEmbeddedFileExtractor` now uses the same object-boundary recovery and
  replaces its stream regex capture with the existing raw dictionary parser
  plus length-aware stream extraction. Fallback trimming remains limited to
  indirect `/Length`, matching prior short direct-length recovery behavior.
- Added a focused regression and a WordPress-facing smoke that recover
  `fake-endstream-stack.csv`, verify checksum/size/filter metadata, emit a
  `wp:file` block, and keep attachment bytes out of summary metadata.

## Evidence

- Red-first before source patch:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentStreamFilterFakeEndstreamBoundaryCurrentBaseTest.php`
  => `1 test files, 2 assertions, 1 failures`
  (`attachment_count` expected `1`, actual `0`).
- Focused after patch:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentStreamFilterFakeEndstreamBoundaryCurrentBaseTest.php`
  => `1 test files, 34 assertions, 0 failures`.
- Attachment stream-filter family:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentStreamFilter*CurrentBaseTest.php`
  => `5 test files, 665 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/markerpdf/examples/wordpress-pdf-attachment-stream-filter-fake-endstream-currentbase.php --self-test`
  => exits `0` with `attachment_count=1`, `embedded_file_count=1`,
  `fake_encoded_endstream_present=true`, `payload_omitted_from_summary=true`,
  `executes_python_or_models=false`, and
  `executes_external_pdf_tools=false`.
- Syntax/status checks:
  `php -l` passed for `PdfAttachmentExtractor.php`,
  `PdfEmbeddedFileExtractor.php`,
  `PdfAttachmentStreamFilterFakeEndstreamBoundaryCurrentBaseTest.php`, and
  `wordpress-pdf-attachment-stream-filter-fake-endstream-currentbase.php`;
  `lane-status.json` parses with `JSON_THROW_ON_ERROR`.

## Non-Overlap

This does not repeat prior accepted stream stack work for page-content
ASCIIHex/ASCII85 terminator tails, Flate-first missing-Length recovery,
attachment non-whitespace after explicit filter terminators, attachment
DecodeParms/predictor/LZW/Crypt validation, short declared attachment lengths,
or comment-split indirect `/Length`. The new boundary is specifically a fake
encoded `endstream` marker inside a valid direct-length EmbeddedFile stream
stack.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
PDF dictionary parser, filter stack decoders, checksum review, and WordPress
attachment smoke path.

## Next

Continue with non-overlapping no-GPU markerPDF parser fidelity around font
CMaps/widths, xref repair, metadata/outlines, annotations/forms, page
geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
Root harness was not run; this is an isolated micro-slice.
