# Stream Filter Stack Negative Length Current Base

Slice: `markerpdf-stream-filter-stack-boundary-current-base-20260606T170443Z`
Base: `cd0e5891c156b74b93e3a6ddb7bf05dd8f35f257`

## Source Truth

markerPDF delegates searchable-PDF text extraction to native PDF text parsing before any OCR/model fallback. In the current no-GPU markerPDF scope, this port must keep malformed searchable-PDF stream dictionaries from leaking filtered payload text into WordPress paragraphs. A PDF stream `/Length` operand is length metadata for the stream byte range; a direct negative integer is malformed and should fail closed rather than be treated as a missing length eligible for fallback decoding.

## Behavior

`PdfTextExtractor` now rejects direct negative `/Length` operands in `streamLengthOperandIsWellFormed()` before filter-stack fallback text extraction. The focused fixture places a compressed hidden payload in a stream with `/Length -N /Filter /FlateDecode` followed by a valid unfiltered content stream. The hidden filtered payload stays excluded and the later valid content still imports.

## Evidence

Red-first probe:

`php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php`

Result after adding the focused test before the source fix: `1 test files, 348 assertions, 1 failure`. The failing assertion showed `Negative Length Filter Leak` imported before `Visible After Negative Length`.

Focused after fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php`

Result: `1 test files, 356 assertions, 0 failures`.

Stream/filter boundary family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackOverdeclaredLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterTrailingPayloadBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamLengthStartxrefRecoveryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDecodeParmsOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserIndirectDecodeParmsFilterOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserFilterArrayDictOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php lanes/markerpdf/tests/PdfParserStreamDictionaryEscapeBoundaryTest.php`

Result: `9 test files, 452 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-stream-filter-stack-boundary-currentbase.php | rg 'negative_direct_length_filter_stream_rejected|negative_length_payload_excluded|Negative Length'`

Result: emitted `negative_direct_length_filter_stream_rejected=true`, `negative_length_payload_excluded=true`, and `<p>Visible After Negative Length</p>` while keeping `Negative Length Filter Leak` out of paragraphs.

## Non-Overlap

This does not repeat accepted missing-Length recovery, stale positive declared-Length recovery, short LZW/RunLength boundary recovery, parser-comment split indirect `/Length` references, malformed indirect length helper rejection, duplicate stream dictionary key rejection, DecodeParms/filter-owner validation, Crypt filter handling, inline image boundaries, image filter metadata, xref repair, annotations, forms, OCR, or model execution.

## Dependency Closure

No new support component is needed. The patch reuses the native PDF object scanner, stream dictionary reader, direct/indirect `/Length` validator, filter-stack decoder, text content parser, and WordPress smoke renderer. GPU/model execution, Python marker workers, OCR, and external PDF tools remain intentionally out of scope.
