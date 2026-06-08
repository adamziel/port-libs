# embedded-files malformed EF fallback text current-base slice

Session: `port-dev-markerpdf-attachments-20260608T200330Z`
Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260608T200330Z`
Base accepted HEAD: `04e99b68d5dc6e073f4bb0aa436e72dabb16d510`

## Behavior

Malformed FileSpec `/EF` and `/RF` values are not valid attachment dictionaries,
so `PdfAttachmentExtractor` and `PdfEmbeddedFileExtractor` continue to reject
them before WordPress attachment import. This patch closes the parallel text
containment boundary: when a malformed `/EF` or `/RF` value points directly at
stream objects, those stream objects are treated as attachment payload
candidates and excluded from fallback text extraction.

The new coverage keeps a malformed `/EF 11 0 R 12 0 R` FileSpec out of both
attachment review surfaces while preserving a valid sibling EmbeddedFiles
attachment. The malformed streams intentionally omit `/Type /EmbeddedFile` and
contain text operators, so the regression would leak into WordPress paragraphs
without this boundary.

## Evidence

Red-first probe before the fix:

`php -r '... PdfTextExtractor malformed /EF 11 0 R 12 0 R fixture ...'`

Result: `PdfAttachmentExtractor` reported `attachment_count=0`,
`PdfEmbeddedFileExtractor` returned `[]`, but `PdfTextExtractor` returned
`Malformed EF Payload Leak`.

Focused regression after the fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfEmbeddedFilesMalformedEfFallbackTextBoundaryCurrentBaseTest.php`

Result: `1 test files, 51 assertions, 0 failures`.

Adjacent attachment/fallback boundary check:

`php tools/run-tests.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfAttachmentRelatedFilePairOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfEmbeddedFilesAttachmentStreamOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentNameTreePairOperandBoundaryCurrentBaseTest.php`

Result: `4 test files, 625 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-embedded-files-malformed-ef-fallback-text-currentbase.php`

Result: exits `0`; reports `attachment_count=1`,
`filenames=["valid-source.xml"]`, `malformed_attachment_excluded=true`,
`malformed_ef_payload_excluded_from_text=true`, `fallback_text_empty=true`,
and no Python/models or external PDF tools.

## Non-overlap

This does not repeat EmbeddedFiles name-tree pair validation, `/Names` and
`/Kids` operand boundaries, valid EF dictionary stream extraction, related-file
pair validation, duplicate-name handling, page-level `/AF`, EOF bounds, or
stream dictionary `/Params` and `/DecodeParms` boundary checks. The new behavior
is specifically fallback text containment for malformed direct EF/RF stream
operands that were already rejected as attachment dictionaries.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP PDF object
parser, stream decoder, text extractor, attachment summary extractor, and
embedded-file extractor. No OCR, GPU/model execution, raster rendering,
pypdfium/PIL, external PDF tools, Python subprocesses, or live services are
involved.

## Next Task

A useful follow-up is a distinct native attachment boundary around associated
file arrays or name-tree object repair that does not repeat malformed direct
EF/RF fallback-text containment.
