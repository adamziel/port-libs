# markerPDF object-stream xref parser current-base

Micro-slice: `markerpdf-object-stream-xref-parser-current-base-20260606T085206Z`
Session: `port-dev-markerpdf-object-xref-20260606T085206Z`
Accepted base: `9bad70694349fdf8df2944b1d0fdaa86a6613e3b`

## Source Truth

Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF text and metadata through `marker/pdf/extract_text.py::get_text_blocks()` and `pdftext.extraction.dictionary_output(...)`, with `naive_get_text()` delegated to PDFium. Under the current no-GPU markerPDF scope, this lane owns native PHP parser boundaries that decide which xref-selected object-stream members become WordPress text and review metadata before OCR/layout/model handoff.

PDF object streams carry generation-zero object bodies selected by `/ObjStm` header rows and xref-stream type-2 member indexes. A member offset is relative to `/First` and must identify the first byte of the member object, not lexical whitespace before an object token. Comments were already rejected as comment-owned offsets; this slice closes the same fail-closed boundary for whitespace-owned offsets.

## Behavior

Before the source fix, `PdfTextExtractor::objectStreamMemberOffsetHasTokenBoundary()` accepted an xref-selected member whose declared offset pointed at the newline immediately before a fake page dictionary. `objectStreamMemberBody()` then trimmed the newline, promoted the fake compressed page, and emitted stale WordPress paragraph text.

`PdfTextExtractor` now rejects selected object-stream member offsets that start on PDF whitespace. The focused fixture keeps a valid direct guard page and a malformed compressed page whose type-2 row points at a newline before the page dictionary. After the fix, only the direct guard page reaches text extraction, and object-stream review records `selection_policy=invalid_object_stream_member_offset`, `member_offset_token_boundary=false`, and `invalid_member_offset_rejection_count=1`.

## Red First

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamWhitespaceOffsetCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects object-stream member offsets that point at PDF whitespace
Expected: [Current whitespace-offset guard page]
Actual: [Current whitespace-offset guard page, Whitespace-offset compressed leak, Whitespace-owned member ignored]
1 test files, 1 assertions, 1 failures
```

## Verification

Focused after source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamWhitespaceOffsetCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects object-stream member offsets that point at PDF whitespace
1 test files, 20 assertions, 0 failures
```

Adjacent object-stream/xref parser checks:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamWhitespaceOffsetCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamCommentOffsetCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamLiteralOffsetBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamNextOffsetBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamFirstBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamNestedCompositeOffsetCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamDuplicateOffsetCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamLaterBadOffsetBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamType2IndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamOutOfRangeIndexCurrentBaseTest.php
Focused test run: 10 selected test files (root lock skipped)
10 test files, 237 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-whitespace-offset-currentbase.php
```

The smoke emits one Gutenberg paragraph for `Current whitespace-offset guard page` and metadata `compressed_entry_count=2`, `invalid_member_offset_rejection_count=1`, `selection_policy=invalid_object_stream_member_offset`, `compressed_leak_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and diff checks:

- `php -l lanes/markerpdf/src/PdfTextExtractor.php`
- `php -l lanes/markerpdf/tests/PdfXrefObjectStreamWhitespaceOffsetCurrentBaseTest.php`
- `php -l lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-whitespace-offset-currentbase.php`
- `git diff --check -- lanes/markerpdf`

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted object-stream comment-offset rejection, literal-string or nested-composite offset rejection, malformed later-offset end-boundary preservation, duplicate member offset rejection, `/First` boundary validation, incomplete headers, skipped header rows, explicit type-2 member-index selection, out-of-range index review, stream-member rejection, object-stream filter-owner behavior, xref-stream `/Prev` repair, hybrid owner precedence, OCR/model execution, or table/equation handoffs. The bounded behavior is only xref-selected object-stream member offsets that start on PDF whitespace before a member token.

## Dependency Closure

No new support component is needed. This reuses the native PHP direct-object scanner, xref-stream parser, object-stream decoder, member-offset token-boundary scanner, text extractor, review metadata path, and WordPress smoke renderer. Full upstream parity remains gated by live `pdftext`, pypdfium/PDFium, Surya/Torch OCR/layout/table models, Texify equation recognition, benchmark/model downloads, Streamlit/FastAPI runtimes, and external OCR/rendering helpers; none were executed for this no-GPU PHP slice.
