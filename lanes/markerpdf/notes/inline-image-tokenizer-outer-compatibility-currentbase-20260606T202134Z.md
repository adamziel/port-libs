# markerPDF inline image tokenizer outer compatibility current-base

Slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260606T202134Z`

Accepted base: `70e2b099de7160dce6dbdeb6dab8788b4080a2fa`

## Source Truth

Upstream markerPDF delegates searchable PDF page text extraction to parser-backed pdftext/PDFium before image/OCR/model stages. At that boundary, `BI ... ID ... EI` bytes are inline raster payload, while valid content-stream text after the real `EI` terminator remains visible WordPress import text. PDF `BX`/`EX` compatibility sections may wrap unknown future operators; those unknown operands must not force the inline-image tokenizer to consume later visible text while looking for another stray `EI`.

## Change

`PdfTextExtractor` now tracks the current outer `BX` compatibility depth while tokenizing content streams and passes that depth into the preview-only inline-image fallback validator. When a compatibility section is opened before the inline image, the post-image segment validator can accept unknown compatibility operands/operators after the real `EI`, preserve the following closed text object, and still require the outer `EX` to close before or immediately after the later stray `EI`.

The new focused fixture covers:

- `BX` opened before a `/JBIG2Decode` inline image.
- A fake early `EI` inside the preview-only image payload.
- The real `EI` followed by `(future compatibility operand) FutureOp`.
- Visible text before a later stray `EI`.
- Payload noise, future compatibility operands, `BX`, and `EX` excluded from WordPress paragraphs.

## Evidence

Red-first after adding the focused test:

`php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php`

Result: `1 test files, 554 assertions, 1 failures`; failing case was `closes preview-only fallback inside outer compatibility section before unknown operator text and stray EI operator`, missing `Visible Compatibility Unknown Before Stray`.

After source fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php`

Result: `1 test files, 567 assertions, 0 failures`.

## Non-Overlap

This does not repeat accepted malformed `BI` recovery, tight `ID`/`EI`, comments after `ID`, NUL/vertical-tab separator handling, compact dictionaries, text-object decoys, DCT/JPX/JBIG2/CCITT/unsupported-filter payload boundaries, visible literal/TJ/ActualText `EI` recovery, post-terminator comments, same-line graphics prefixes, ordinary `BX/EX` compatibility sections opened after the image, external `Q`/`EMC`/`EX` closes, open-scope close-after-stray behavior, Type3 metric operators, image preview metadata, OCR, or model execution.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP content tokenizer, inline-image fallback scanner, compatibility-section validator, focused markerPDF tests, and WordPress smoke example. Full live OCR/model/raster parity remains intentionally out of scope for this no-GPU markerPDF slice.
