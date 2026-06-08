# Classic Xref Trailer Comment Delimiter Free Map Current Base

Slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260608T172439Z`  
Base accepted HEAD: `54a72b0cf0ca3f53a27590245bb3180a2cb6e2d2`

## Source Truth

- Upstream markerPDF routes searchable PDFs through native parser output before any OCR/model stage. In this no-GPU lane, the PHP parser owns classic xref recovery boundaries used by WordPress text, metadata, embedded-file, attachment, and annotation review.
- PDF comments are lexical whitespace. A classic table trailer written as `trailer% comment` followed by a newline and `<< ... >>` is still the trailer dictionary for that xref table, including for the lightweight free-object map used to suppress stale annotation objects.

## Behavior

`PdfXrefFreeObjectMap::skipWhitespace()` now skips PDF comments as whitespace. This matches the heavier parser paths and lets damaged classic `startxref` rebuild select a current xref table whose trailer dictionary is split by a comment delimiter:

```text
trailer% current trailer comment delimiter
<< /Size 8 /Root 1 0 R /Prev ... >>
```

Before this slice, text extraction could still use the current table, but the free-object map skipped it and fell back to older rows. That let stale link annotations escape free-row suppression during WordPress annotation review.

## Red-First Evidence

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildTrailerCommentDelimiterFreeMapCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL accepts trailer comment delimiters while rebuilding the free-object map before annotation review
The comment-delimited current trailer must keep object 7 free.
1 test files, 5 assertions, 1 failures
```

## Verification

Focused repair check:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildTrailerCommentDelimiterFreeMapCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS accepts trailer comment delimiters while rebuilding the free-object map before annotation review
1 test files, 14 assertions, 0 failures
```

Adjacent free-object-map xref coverage:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildFreeObjectMapCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildFreeObjectMapHeaderGarbageCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildFreeObjectMapWhitespaceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildEarlyEndstreamFreeMapCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicRebuildTrailerCommentDelimiterFreeMapCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
5 test files, 144 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-classic-trailer-comment-delimiter-free-map-currentbase.php
```

Result: exits 0 and emits current WordPress paragraph text with `free_row_current=true`, `stale_link_suppressed=true`, `stale_annotation_suppressed=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted `xref%` header comment-delimiter parsing, commented trailer tokens inside xref rows, literal/name/composite xref decoys, damaged/missing final `startxref` EOF bounding, private-tail startxref rejection, double-EOF post-EOF garbage rejection, early-endstream free-map decoys, malformed header garbage, punctuation-row rejection, trailer stream fallback, or text/metadata/embedded-file trailer parsing.

The bounded behavior here is only the lightweight free-object-map rebuild path accepting comments as PDF whitespace after the selected classic `trailer` keyword before annotation review.

## Dependency Closure

No new support component is needed. This reuses the native PHP direct-object scanner, selected-startxref parser, classic xref table parser, free-object-map helper, link/annotation extractors, and WordPress smoke renderer. GPU/model OCR, PDFium rendering, external PDF tools, live Surya/Texify/Torch execution, Streamlit/FastAPI workers, decryption/password validation, and exact upstream model benchmark parity remain intentionally outside the current no-GPU markerPDF scope.
