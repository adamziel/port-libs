# markerpdf stream-filter stack EmbeddedFile unsupported terminal boundary current base

Slice: `markerpdf-stream-filter-stack-boundary-current-base-20260605T103106Z`
Base: `17084c137d0018e6cf17e49bcac91c3e1cb47745`
Scope: native no-GPU markerPDF EmbeddedFiles payload extraction.

## Source-truth behavior

Upstream markerPDF treats attachment/embedded-file payload bytes as downstream review material rather than OCR/model output. In the native PHP port, unsupported or preview-only stream filters cannot be treated as identity for EmbeddedFile payload extraction because that makes undecoded bytes look checksum-valid and import-ready.

The red-first probe used a catalog `/Names << /EmbeddedFiles ... >>` entry whose embedded stream declared `/Filter [ /ASCIIHexDecode /DCTDecode ]` and matching `/Params /CheckSum` for the post-ASCIIHex raw bytes. Before this patch, `PdfEmbeddedFileExtractor` applied ASCIIHexDecode and then treated unsupported `DCTDecode` as identity, returning `unsafe.bin` with `checksum_matches=true`.

## Implemented boundary

`PdfEmbeddedFileExtractor::decodeStreamObject()` now fails closed for any filter not natively decoded by this extractor. Supported `ASCIIHexDecode`/`AHx` and `FlateDecode`/`Fl` stacks still decode normally; unsupported terminal stages such as `DCTDecode`, `JPXDecode`, `CCITTFaxDecode`, named `Crypt`, or other future filters produce no EmbeddedFile row until a native decoder or explicit safe policy exists.

## Evidence

- `php -l lanes/markerpdf/src/PdfEmbeddedFileExtractor.php`
  - `No syntax errors detected in lanes/markerpdf/src/PdfEmbeddedFileExtractor.php`
- `php -l lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php`
  - `No syntax errors detected in lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php`
- `php -l lanes/markerpdf/examples/wordpress-pdf-embedded-file-filter-stack-boundary-currentbase.php`
  - `No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-embedded-file-filter-stack-boundary-currentbase.php`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php`
  - `1 test files, 404 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php`
  - `1 test files, 206 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-embedded-file-filter-stack-boundary-currentbase.php`
  - metadata reports `safe_payload_preserved=true`, `unsupported_stack_rejected=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`

Root harness: not run - isolated micro-slice.

## Dependency closure

No new support component is needed. This reuses existing native ASCIIHex and Flate stream decoders in `PdfEmbeddedFileExtractor` and records unsupported filters as an in-scope native decoder follow-up rather than invoking external PDF tools, OCR, GPU models, or provider services.

## Non-overlap

This does not repeat the accepted inline image post-EOD surplus decode boundary, the broader parser stream-filter stack DecodeParms/null-slot behavior, or attachment-summary unsupported-filter preflight. The patch is limited to EmbeddedFile payload extraction before checksum/content review.
