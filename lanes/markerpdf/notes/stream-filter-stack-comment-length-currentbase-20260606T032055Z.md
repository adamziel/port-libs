# Stream Filter Stack Comment Length Boundary Current Base

Micro-slice: `markerpdf-stream-filter-stack-boundary-current-base-20260606T032055Z`

Accepted base: `3c8b9e6cdbfac97ac54f81052e1e910b2e2834ae`

## Behavior

Native PDF stream extraction now resolves parser-comment split indirect `/Length`
references using the same comment-aware indirect-reference tokenizer already
used for stream `/Filter` and `/DecodeParms` references. This lets exact
declared stream lengths bound damaged content streams whose terminator is
missing, while still keeping helper objects, filter names, and object syntax out
of WordPress paragraph text.

Focused source truth: PDF dictionary tokens may be separated by comments because
comments are whitespace in PDF lexical grammar. The slice stays within no-GPU
markerPDF scope and uses only native searchable-PDF stream parsing.

## Evidence

Red-first probe before the source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php`

Result: `1 test files, 304 assertions, 1 failures`; the new case imported only
`Visible After Comment Length` and skipped `Comment Split Length Imports`.

After the source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php`

Result: `1 test files, 313 assertions, 0 failures`.

Final focused family check:

`php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDictionaryEscapeBoundaryTest.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php lanes/markerpdf/tests/PdfParserIndirectDecodeParmsFilterOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserFilterArrayDictOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDecodeParmsOwnerCurrentBaseTest.php`

Result: `6 test files, 374 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-stream-filter-stack-boundary-currentbase.php`

Result: emitted `parser_comment_split_length_reference_resolved=true`,
`parser_comment_split_length_damaged_terminator_bounded=true`,
`Comment Split Length Imports`, and `Visible After Comment Length`.

Diff check: `git diff --check -- lanes/markerpdf` completed with no output.

## Non-Overlap

This does not duplicate the accepted stream-filter stack slices for ASCII85 EOD,
RunLength/LZW EOD, DecodeParms alignment, Crypt identity, duplicate stream keys,
or parser-comment split `/Filter` and `/DecodeParms` references. The new
boundary is specifically parser-comment split indirect `/Length` resolution
before damaged stream terminator fallback.

## Dependency Closure

No new support component is needed. The patch reuses the existing native
`PdfTextExtractor::pdfIndirectReferenceTokenAt()` parser path; no OCR, model,
GPU, external PDF tool, Python runner, or live service is required.

## Next Task

Continue with non-overlapping native searchable-PDF behavior, especially stream
filters, xref repair, fonts/CMaps, page geometry, annotations/forms,
metadata/attachments, and supplied-boundary table/equation handoffs.
