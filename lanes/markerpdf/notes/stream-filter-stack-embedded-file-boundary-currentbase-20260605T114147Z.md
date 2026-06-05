# EmbeddedFile Stream Filter Stack Boundary - 2026-06-05

Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260605T114147Z`

Accepted base: `a20a696ad37cb38330c430dc42489a24868948cb`

## Source Truth

PDF stream filters are applied in declared order to the stream bytes. EmbeddedFile streams may use the standard safe byte filters already handled by the native markerPDF parser and attachment preflight paths. The no-GPU markerPDF scope does not invoke OCR, image raster decoding, model workers, or external PDF tools for this boundary.

## Behavior Delta

`PdfEmbeddedFileExtractor` previously decoded EmbeddedFile payload streams through `ASCIIHexDecode` and `FlateDecode` only. Safe EmbeddedFile stacks that used `ASCII85Decode`/`A85` or `RunLengthDecode`/`RL` were dropped before `/Params` size and checksum review, even though unsupported terminal filters still needed to fail closed.

This slice adds native EmbeddedFile decode support for:

- `ASCII85Decode` and abbreviated `A85`
- `RunLengthDecode` and abbreviated `RL`

Unsupported terminal filters such as `DCTDecode` remain excluded before payload review.

## Evidence

Red-first focused run after adding the new test and before production decode support:

`php tools/run-tests.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php`

Result: new test failed with `Expected: 2`, `Actual: 0`; overall `1 test files, 405 assertions, 1 failures`.

Focused run after patch:

`php tools/run-tests.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php`

Result: `1 test files, 421 assertions, 0 failures`.

Adjacent stream-stack run:

`php tools/run-tests.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php`

Result: `3 test files, 1081 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-embedded-file-filter-stack-boundary-currentbase.php`

Result: emits `safe_file_count=3`, `ascii85_payload_preserved=true`, `runlength_payload_preserved=true`, `unsupported_stack_rejected=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and lane diff checks:

- `php -l lanes/markerpdf/src/PdfEmbeddedFileExtractor.php` => no syntax errors.
- `php -l lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php` => no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-pdf-embedded-file-filter-stack-boundary-currentbase.php` => no syntax errors.
- `php -r '$json = json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true); if (!is_array($json) || json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, json_last_error_msg() . PHP_EOL); exit(1); } echo "lane-status.json OK\n";'` => `lane-status.json OK`.
- `git diff --check -- lanes/markerpdf` => clean.

## Non-Overlap

This does not repeat the existing core text stream-stack boundary or `PdfAttachmentExtractor` ASCII85/Flate coverage. The patch is limited to the catalog/name-tree `PdfEmbeddedFileExtractor` path used for EmbeddedFile payload review metadata.

## Dependency Closure

No new support component is needed. The patch reuses existing native PHP zlib and bounded byte-filter logic patterns already present in markerPDF. GPU/model/OCR execution remains intentionally out of scope for this lane.
