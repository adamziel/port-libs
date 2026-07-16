# Inline Image Decode Boundary Current Base - 2026-06-06T02:07:10Z

## Slice

- Lane: `markerpdf`
- Micro-slice: `markerpdf-inline-image-decode-boundary-current-base-20260606T020710Z`
- Accepted base: `0f344fe5e92e069e811b55e3b6740f8331906302`
- Scope: native no-GPU PDF content-stream inline image tokenizer/filter boundary behavior.

## Source Truth

PDF inline images use `BI ... ID ... EI`; filter stacks apply in declared order. A native prefix wrapper such as `ASCIIHexDecode` may carry a complete terminal `FlateDecode` member plus decoded surplus bytes. If those decoded post-zlib bytes contain delimiter-looking `EI` and text operators, they remain image data until the raw inline-image `EI` terminator after the wrapper EOD. Identity `Crypt` with `/Name /Identity` is byte-preserving and must not change that ownership boundary.

## Red-First Evidence

Before source edits, a focused PHP probe using the accepted parser returned only the `Before` text line for both:

- `/F [/AHx /Fl]`
- `/F [/Crypt /AHx /Fl] /DP [<< /Name /Identity >> null null]`

Both payloads were `ASCIIHexDecode(gzcompress("K") . "ZZ EI ... rawtail") . ">"`. The renderer already rejected surplus preview and decoded the clean wrapped stream, so the gap was tokenizer ownership: `PdfTextExtractor` did not decode the native prefix before detecting the terminal Flate member boundary.

## Implementation

- Added `inlineWrappedFlateCandidateReachesSampleFloorBeforeDecodedPostStreamSurplus()` in `PdfTextExtractor`.
- The helper decodes only native filters before the terminal Flate filter, then checks the terminal zlib member's explicit end offset and decoded post-stream surplus for delimiter-looking `EI`.
- The helper returns ownership only. Existing image preview validation still rejects malformed/surplus preview data and still validates clean wrapped streams through the renderer.

## Tests And Smoke

- Added one focused behavior case to `PdfInlineImageDecodeBoundaryCurrentBaseTest.php` covering both the plain native stack and Identity `Crypt` pass-through variant.
- Updated `wordpress-pdf-inline-image-decode-boundary-currentbase.php` to emit wrapped terminal Flate text exclusion, preview rejection, and clean-preview decode metadata.

Focused verification:

- `php -l lanes/markerpdf/src/PdfTextExtractor.php` => no syntax errors
- `php -l lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php` => no syntax errors
- `php -l lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php` => no syntax errors
- `php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php` => `1 test files, 605 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php` => `3 test files, 424 assertions, 0 failures`
- Example metadata parse confirmed `wrapped_terminal_flate_*`, `identity_crypt_wrapped_terminal_flate_*`, and `excluded_inline_image_text` flags are true.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted raw JPX post-EOC, Flate-wrapped JPX, ASCII85/ASCIIHex/RunLength/LZW post-EOD surplus, direct null filters, invalid DecodeParms fail-closed, short decoded Flate, predictor short-row, raw stacked native first-filter surplus, Identity `Crypt` + Flate, or Identity `Crypt` + JPX coverage. This slice owns only decoded terminal Flate surplus hidden behind an outer native wrapper.

## Dependency Closure

No new dependency or support component is needed. The patch reuses native PHP stream filter boundary helpers, zlib inflation, inline image tokenizer logic, and existing renderer preview validation. No OCR, GPU, model execution, Python model worker, external PDF tool, or online service was invoked.

## Next Task

Continue with non-overlapping native markerPDF parser/converter behavior: fonts/CMaps, xref repair, forms/annotations, page geometry, metadata, image/filter metadata, and supplied-boundary table/equation handoffs.
