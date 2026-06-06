# Stream Filter Stack Whitespace Boundary Current Base

Session: `port-dev-markerpdf-stream-filter-stack-20260606T174506Z`
Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260606T174506Z`
Accepted base: `90edc6b63e340cfbca7035a078ed73b69217b640`

## Behavior

PDF stream filters accept only the PDF-defined whitespace bytes around encoded filter data: NUL, tab, LF, form feed, CR, and space. This slice applies that boundary to native ASCII85 and ASCIIHex decoding in page text extraction and embedded-file attachment extraction.

Before this change, a vertical-tab byte could be trimmed before ASCII85 page stream decoding, and attachment ASCIIHex filter stacks could remove vertical-tab bytes as generic PHP whitespace before Flate payload extraction. That allowed malformed page text or malformed attachment payloads to import when they should fail closed.

The patch keeps clean sibling streams importable while rejecting non-PDF vertical-tab bytes before WordPress paragraph text or attachment review. It does not run OCR, models, PDFium, Python conversion, or external PDF tools.

## Red-First Evidence

- `php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php` before production fix: `1 test files, 357 assertions, 1 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentStreamFilterStackBoundaryCurrentBaseTest.php` before production fix: `1 test files, 193 assertions, 1 failures`.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php` after fix: `1 test files, 366 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentStreamFilterStackBoundaryCurrentBaseTest.php` after fix: `1 test files, 219 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentStreamFilterPredictorCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentStreamFilterTerminatorBoundaryCurrentBaseTest.php`: `4 test files, 803 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-stream-filter-whitespace-boundary-currentbase.php`: reports `attachment_count=1`, `leading_ascii85_non_pdf_whitespace_rejected=true`, `attachment_asciihex_stack_non_pdf_whitespace_rejected=true`, `clean_attachment_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.
- PHP lint passed for `PdfTextExtractor.php`, `PdfEmbeddedFileExtractor.php`, `PdfAttachmentExtractor.php`, the two changed focused tests, and the new WordPress smoke.
- `php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "markerpdf json ok\n";'`: `markerpdf json ok`.
- `git diff --check -- lanes/markerpdf`: clean.

## Non-Overlap

This slice does not repeat the accepted null `DecodeParms`, indirect filter operands, missing or stale `/Length`, ASCII85 EOD, LZW EOD, RunLength, Crypt identity, duplicate top-level stream keys, attachment terminator-tail, predictor, object-stream owner, xref-owner, or inline-image filter boundary clusters. It is limited to exact PDF whitespace acceptance at ASCII85/ASCIIHex filter data boundaries.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP parser/filter stack and narrows its filter whitespace predicate. Remaining OCR/model parity stays out of scope under the no-GPU markerPDF directive.
