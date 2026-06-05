# MarkerPDF Object Stream/XRef Escaped Type Names

Micro-slice: `markerpdf-object-stream-xref-parser-current-base-20260605T005824Z`  
Accepted base: `41cae8b6fd1e5314059c74ad58c304aea88484db`  
Scope: native no-GPU searchable-PDF parser behavior only.

## Source Truth

PDF names allow `#hh` hex escapes. The native parser already has name-token
decoding helpers, but several xref and object-stream selection paths still
matched raw dictionary text for `/Type /XRef` and `/Type /ObjStm`. That missed
valid dictionaries such as `/Type /XR#65f` and `/Type /Obj#53tm`, so a current
xref stream could be ignored and stale direct stream text could leak into
WordPress output.

This slice decodes Type-name tokens before xref-stream and object-stream
dispatch. It does not run Python, OCR, model workers, external PDF tools, or
live services.

## Behavior Added

- `PdfTextExtractor` now uses decoded-name dictionary matching for xref-stream
  and object-stream `/Type` checks.
- Embedded file and inline-image stream classification now shares the same
  decoded stream-name checks instead of raw `/Type` and `/Subtype` regex
  matches.
- Added a focused fixture where current page/catalog objects live inside an
  escaped `/Type /Obj#53tm` stream and the selected current xref stream is
  escaped as `/Type /XR#65f`.
- Added a WordPress smoke that proves the current xref-selected object-stream
  page imports while a stale direct stream marked free by the escaped xref
  stream is excluded.

## Red-First Evidence

Before the parser change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserObjectStreamTypeNameEscapeCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL decodes escaped xref and object-stream Type names before current-base text extraction
Expected: ['Escaped type object stream page','Name escaped xref selected']
Actual: ['Escaped type object stream page','Name escaped xref selected','Escaped type stale fallback leak']
1 test files, 1 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserObjectStreamTypeNameEscapeCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS decodes escaped xref and object-stream Type names before current-base text extraction
1 test files, 14 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserObjectStreamTypeNameEscapeCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDictionaryEscapeBoundaryTest.php lanes/markerpdf/tests/PdfParserNameArrayCommentEscapeCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserSecurityXrefFilterErrorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamType2IndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamOffsetOrderCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamIndirectIndexWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamFilterLengthOwnerReviewCurrentBaseTest.php
Focused test run: 10 selected test files (root lock skipped)
10 test files, 144 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-parser-object-stream-type-name-escape-currentbase.php
emits uses_escaped_xref_stream_type=true, uses_escaped_object_stream_type=true,
uses_current_object_stream_page=true, excludes_stale_free_stream=true,
compressed_entry_count=4, page_count=1, and no Python/model/external PDF tool
execution.
```

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfParserObjectStreamTypeNameEscapeCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-parser-object-stream-type-name-escape-currentbase.php
```

All changed PHP files reported no syntax errors.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This is not a duplicate of the existing xref/object-stream current-base work:
it does not add another explicit type-2 index, zero-width member, member-offset
ordering, indirect `/W`/`/Index`, `/Prev`, compressed filter, classic xref
comment, or stream-boundary case. The distinct behavior is decoding escaped PDF
name tokens in the `/Type` values that identify XRef and ObjStm stream
dictionaries.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
PDF token/dictionary helpers in `PdfTextExtractor`; no GPU/model/OCR,
external-tool, or online dependency is introduced.

## Next Task

Continue native no-GPU markerPDF parser parity around remaining current-base
xref/object stream repair edges, stream filters, CMaps/fonts, annotations,
forms, metadata, and supplied-boundary handoffs without overlapping the escaped
`/Type` stream identification boundary covered here.
