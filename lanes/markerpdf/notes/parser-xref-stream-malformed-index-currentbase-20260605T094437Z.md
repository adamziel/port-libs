# markerPDF xref-stream malformed Index object-stream boundary

Micro-slice: `markerpdf-object-stream-xref-parser-current-base-20260605T094437Z`  
Accepted base: `e1fb13284b9e18b6faf2bdd4b50b6b135267d72d`

## Source Truth

PDF xref-stream `/Index` arrays are pairs of non-negative integers: starting object number and entry count. A selected `startxref` xref stream with a malformed `/Index` must not be silently normalized into a valid object-number range, because type-2 rows can then select compressed object-stream members that were not actually owned by a valid xref row.

This slice keeps the existing accepted behavior for indirect `/W` and `/Index` arrays, positive sparse `/Index` repair by current offsets, zero-width type-2 member indexes, explicit type-2 member indexes, malformed `/W` fail-closed handling, and `/Prev` xref-stream repairs. The bounded new behavior is only negative/non-integer/incomplete `/Index` arrays on xref streams.

## Implementation

- `PdfTextExtractor::xrefStreamIndexProblem()` now validates resolved xref-stream `/Index` arrays before row decoding.
- `startxrefXrefStreamFilterDecodeFailed()` treats malformed `/Index` arrays like malformed `/W` arrays: fail closed before fallback object scanning can expand object streams.
- `xrefIndexRanges()` no longer clamps malformed negative `/Index` values to zero.
- `extractXrefObjectStreamIndexReview()` reports `malformed_xref_stream_index_count` and review entries with owner policies such as `negative_xref_stream_index_value`.

## Evidence

Red-first command before the implementation fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserXrefStreamMalformedIndexCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects malformed negative xref-stream Index values before object-stream fallback text
Values are not identical
Expected: array (
)
Actual: array (
  0 => 'Malformed negative Index compressed page leak',
)

1 test files, 1 assertions, 1 failures
```

Focused passing command after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserXrefStreamMalformedIndexCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects malformed negative xref-stream Index values before object-stream fallback text

1 test files, 18 assertions, 0 failures
```

Adjacent xref/object-stream regression command:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserXrefStreamMalformedIndexCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamMalformedWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamIndirectIndexWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexWidthRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamType2IndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamDuplicateZeroWidthCurrentBaseTest.php
Focused test run: 7 selected test files (root lock skipped)
PASS resolves indirect xref-stream W and Index arrays before object-stream current-base selection
PASS rejects malformed negative xref-stream Index values before object-stream fallback text
PASS rejects malformed negative xref-stream W byte widths before object-stream fallback text
PASS fails closed on duplicate object-stream header numbers when xref member indexes are zero-width
PASS keeps current object-stream base direct while applying explicit type-2 member index
PASS keeps first current xref stream Index row before duplicate stale Prev row
PASS repairs malformed Prev xref-stream Index rows with zero-width W fields by current offsets

7 test files, 99 assertions, 0 failures
```

Broader xref-stream family command:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserXrefStream*CurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStream*CurrentBaseTest.php
Focused test run: 19 selected test files (root lock skipped)
PASS ignores stream-owned startxref tokens before xref-stream current-base selection
PASS resolves xref-stream Filter operands from current compressed object-stream helpers
PASS applies xref stream filter DecodeParms before current-base object selection
PASS reviews xref-stream indirect Filter and Length owners before current-base WordPress text extraction
PASS resolves indirect xref-stream W and Index arrays before object-stream current-base selection
PASS resolves xref-stream indirect Prev offsets from compressed helper object streams
PASS repairs damaged current xref-stream offsets after resolving indirect Prev from compressed helper objects
PASS rejects malformed negative xref-stream Index values before object-stream fallback text
PASS rejects malformed negative xref-stream W byte widths before object-stream fallback text
PASS preserves direct xref stream owners when decoded rows form a compressed owner cycle
PASS repairs malformed xref-stream Index object numbers by current generation offsets before Prev rows
PASS rejects stream-owned xref stream objects before current-base WordPress text extraction
PASS keeps current xref-stream free entries authoritative over stale direct and previous object-stream owners
PASS keeps current xref-stream generation Index rows before stale Prev duplicates in metadata imports
PASS recovers Prev hybrid object-stream members when current xref-stream carrier row has generation noise
PASS keeps current xref-stream object-stream owner before stale Prev hybrid type-2 rows
PASS preserves Prev type-2 rows when current sparse Index carrier row keeps the same offset despite generation noise
PASS keeps first current xref stream Index row before duplicate stale Prev row
PASS repairs malformed Prev xref-stream Index rows with zero-width W fields by current offsets

19 test files, 316 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-parser-xref-stream-malformed-index-currentbase.php
```

The smoke emits `malformed_index_rejected=true`, `owner_policy=negative_xref_stream_index_value`, `compressed_entries_expanded=0`, `excludes_negative_index_object_stream_text=true`, `visible_text_empty=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- PHP focused tests: `1685 -> 1686`
- WordPress scenarios: `1546 -> 1547`
- Root harness: not run - isolated micro-slice.
- No GPU/model execution, OCR, PDFium, Poppler, Ghostscript, or external PDF tooling was used.

## Dependency Closure

No new support component is needed. The slice reuses native PHP direct-object scanning, xref stream parsing, indirect operand resolution, object-stream decoding, stream filter decoding, page-tree traversal, and WordPress smoke output. Full upstream markerPDF parity remains dependency-gated by pdftext/PDFium, Surya/Torch/OCR/model execution, and external rendering/model paths, which are intentionally outside the current no-GPU markerPDF scope.

## Non-Overlap

This does not repeat accepted malformed `/W` fail-closed handling, indirect `/W`/`Index` operand resolution, sparse positive `/Index` current-offset repair, zero-width member-index recovery, duplicate zero-width guards, explicit type-2 index selection, object-stream header comments, member-offset token-boundary rejection, stream-member rejection, carrier generation repair, hybrid free-entry ownership, classic xref rebuild, or `/Prev` generation repair. The new boundary is specifically malformed xref-stream `/Index` arrays that would otherwise be clamped before object-stream member expansion.
