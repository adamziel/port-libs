# Inline Image Decode Array Member Boundary Current Base

Slice: `markerpdf-inline-image-decode-boundary-current-base-20260608T180045Z`

Base: `2ec670a1f9d01eec048efaf7acf483379e6550b8`

## Behavior

Native inline image review now treats present but nonnumeric `/Decode` array
members as invalid operands instead of a valid explicit numeric Decode array.
The boundary covers direct `null`, dictionary operands, names, and unresolved
indirect references inside inline image `/D` or `/Decode` arrays.

This keeps WordPress imports fail-closed before native raster preview while the
content tokenizer still owns the whole inline image payload through the real
`EI` terminator, so fake text inside compressed or surplus image bytes does not
become Gutenberg paragraph text.

Valid numeric arrays, indirect numeric operands, exponent-form values, and PDF
comments between array operands remain accepted.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeArrayMemberOperandBoundaryCurrentBaseTest.php`
  - `1 test files, 54 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeNameOperandBoundaryCurrentBaseTest.php`
  - `1 test files, 49 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeArrayMemberOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeArrayOperandCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeNameOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeTailDecodeParmsCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeParmsTailFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php`
  - `7 test files, 1695 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeIdentityCryptPrefixBoundaryCurrentBaseTest.php`
  - `1 test files, 29 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-array-member-boundary-currentbase.php`
  - exits `0`, emits `decode_source=invalid`, `decode_component_count=0`,
    `decode_operand_review_only=true`, `preview_failed_closed=true`, and
    `inline_payload_excluded_from_text=true`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted inline image tokenizer sample-floor, filter-array
abbreviation/null-entry, DecodeParms tail, name-operand preservation,
Identity-Crypt prefix, DCTDecode, JPX, JBIG2, CCITT, or ImageMask review
slices. It only refines the direct inline image `/Decode` array-member operand
classification used by native preview planning.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
PDF tokenizer, array/dictionary scanner, stream-filter preview helpers, and
WordPress smoke harness. No Python, OCR/model, pypdfium/PIL, GPU, decryption,
action execution, or external PDF tools are required or run.
