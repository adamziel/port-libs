# markerPDF xref object-stream incomplete header current-base slice

Slice: `markerpdf-object-stream-xref-parser-current-base-20260604T214528Z`

Session: `port-dev-markerpdf-object-xref-20260604T214529Z`

Base accepted HEAD: `b71cd6fb809e4ef9d0d33ad21e4e09f9abb6baec`

## Source Truth

- Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF text through `marker/pdf/extract_text.py`, where `get_text_blocks()` delegates PDF text extraction to `pdftext.extraction.dictionary_output(...)` and `naive_get_text()` delegates bounded page text to pypdfium/PDFium before markerPDF emits Markdown/WordPress-visible text.
- PDFium object-stream parsing validates `/Type /ObjStm`, `/N`, and `/First`, then resolves compressed objects through object-stream archive indexes. At this native PHP dependency boundary, an `/ObjStm` whose decoded header does not contain the declared `/N` object-number/offset pairs is malformed and must not expose a prefix member as a valid page object.

## Implemented Behavior

`PdfTextExtractor::objectStreamHeaderMembers()` now fails closed when a decoded object-stream header ends before all declared `/N` object-number/offset pairs are parsed.

The focused fixture declares `/N 2` but provides only one header pair, `4 0`. The xref stream then advertises page object `4` as a type-2 member of that carrier. Before this patch, the native parser expanded the parseable prefix member and WordPress import emitted `Malformed object stream page leak`. After this patch, the incomplete member table is rejected, the page tree keeps only the current direct guard page, and review metadata records `selection_policy=missing_object_stream_member` with `object_stream_member_count=0`.

## Red-First Evidence

Before implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamIncompleteHeaderCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL fails closed on incomplete object-stream header pairs before WordPress text extraction (lanes/markerpdf/tests/PdfXrefObjectStreamIncompleteHeaderCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'Current incomplete-header guard page',
)
Actual: array (
  0 => 'Current incomplete-header guard page',
  1 => 'Malformed object stream page leak',
  2 => 'Incomplete header member ignored',
)

1 test files, 1 assertions, 1 failures
```

After implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamIncompleteHeaderCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed on incomplete object-stream header pairs before WordPress text extraction

1 test files, 19 assertions, 0 failures
```

Adjacent object-stream/xref gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamIncompleteHeaderCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamSkippedHeaderIndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamDuplicateZeroWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamType2IndexCurrentBaseTest.php lanes/markerpdf/tests/PdfObjectStreamNestedTokenBoundaryTest.php lanes/markerpdf/tests/PdfObjectStreamLengthFilterTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterChainCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamCompressedOperandOwnerCurrentBaseTest.php
Focused test run: 8 selected test files (root lock skipped)
8 test files, 124 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-incomplete-header-currentbase.php
```

Smoke output reports `uses_direct_guard_page=true`, `excluded_malformed_object_stream_page=true`, `excluded_incomplete_header_member_text=true`, `object_stream_member_count=0`, `selection_policy=missing_object_stream_member`, `page_count=1`, and no Python/model/external PDF tool execution.

## Non-Overlap

This does not repeat accepted object-stream nested token parsing, indirect `/Length`/`/Filter`/`/N`/`/First` recovery, skipped zero object-number header-row index alignment, explicit type-2 direct `/ObjStm` base preservation, zero-width member-index recovery, duplicate zero-width member fail-closed behavior, object-stream header comment parsing, xref-stream `/Prev` carrier generation repair, hybrid table carrier ownership, current free-entry suppression, or compressed helper filter-chain expansion.

The bounded behavior here is specifically malformed object-stream member-table rejection when the decoded header contains fewer complete object-number/offset pairs than declared by `/N`.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP direct-object scanner, xref-stream parser, object-stream decoder, review metadata path, and WordPress smoke renderer. Full upstream markerPDF runner parity remains intentionally out of scope under the current no-GPU direction and remains gated on live pdftext/pypdfium/PDFium runtime execution, Surya/Torch/OCR model execution, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark workflows, and external PDF/model tooling.

## Next Task

Continue with bounded native searchable-PDF parser behavior: remaining xref repair, stream filters, font/CMap metrics, metadata/action review, annotation/forms, page geometry, image/filter metadata, or supplied-boundary conversion edges that can ship with focused PHP tests and a WordPress smoke.
