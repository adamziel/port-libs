# Attachment stream-filter stack short-Length boundary current-base

Slice: `markerpdf-stream-filter-stack-boundary-current-base-20260606T134752Z`
Accepted base: `dbf0bb1a336256d489bc4e8ddb73cb43d0089b14`

## Behavior

`PdfAttachmentExtractor` now keeps declared stream bytes first when they decode, but if a stale short `/Length` cuts through a direct attachment filter stack, it retries the full bytes before the selected `endstream` only when the existing filter decoder proves:

- the declared slice is incomplete;
- the full direct stack decodes successfully;
- the full stack has only PDF whitespace/comments after the filter end marker.

This is intentionally bounded to native searchable-PDF attachment stream parsing. It does not run OCR, Surya, Texify, Torch, PDFium, model workers, or external PDF tools.

## Evidence

Red probe after adding the focused test on accepted base:

`php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentStreamFilterStackBoundaryCurrentBaseTest.php`

Result: `1 test files / 162 assertions / 1 failures`, failing `attachment_count` `0` vs expected `1` for the short declared `/Length` ASCII85+Flate attachment.

After implementation:

`php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentStreamFilterStackBoundaryCurrentBaseTest.php`

Result: `1 test files / 192 assertions / 0 failures`.

Adjacent attachment filter family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentStreamFilterTerminatorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentStreamFilterPredictorCurrentBaseTest.php`

Result: `3 test files / 410 assertions / 0 failures`.

Neighbor parser stream-filter guard:

`php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackOverdeclaredLengthCurrentBaseTest.php`

Result: `2 test files / 357 assertions / 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-attachment-stream-filter-stack-boundary-currentbase.php`

Result: metadata reports `short_length_attachment_recovered=true`, `short_length_surplus_attachment_rejected=true`, `short_length_payload_bytes_omitted_from_summary=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This slice does not repeat text-content missing-Length recovery, overdeclared stream length handling, LZW short-length recovery, Identity Crypt attachment handling, all-null filter slots, extra DecodeParms rejection, indirect filter operand resolution, or terminator-only attachment boundaries already covered by adjacent markerPDF slices. The new behavior is specific to attachment summary extraction when a stale short `/Length` truncates direct filter-stack input before the stack can reach its own EOD marker.

## Dependency Closure

No new support component is required. The patch reuses native PHP stream filter decoders and the existing bounded filter end-marker checks inside `PdfAttachmentExtractor`.

## Next

A useful follow-up would be indirect-object-carried attachment filter stacks with stale short `/Length`, preserving the same rule: recover only from complete bounded stacks and fail closed on cycles, unsupported filters, or surplus payload bytes.
