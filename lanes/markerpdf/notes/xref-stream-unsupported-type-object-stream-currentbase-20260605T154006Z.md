# markerPDF xref-stream unsupported type object-stream current base

Micro-slice: `markerpdf-object-stream-xref-parser-current-base-20260605T154006Z`
Session: `port-dev-markerpdf-object-xref-20260605T154006Z`
Base accepted HEAD: `0017586f0ec4000005e9e8925bd3a65b36b8c8d2`

## Source truth

- Upstream markerPDF delegates searchable PDF text extraction to PDF text parsing before model/OCR fallback. This slice stays inside the native PHP searchable-PDF parser path and does not run Python, OCR, PDFium, Surya, Texify, Torch, or external PDF tools.
- PDF xref-stream entry types `0`, `1`, and `2` define free, direct, and compressed object storage. Higher entry types are unsupported for native object ownership here, so they must not resurrect stale `/Prev` direct or object-stream owners.
- The current-base behavior is fail-closed: an unsupported current xref-stream row for object `N` is recorded as a current null/free owner for merge purposes, suppressing older `/Prev` type-2 object-stream members for the same object.

## Implementation

`PdfTextExtractor::xrefStreamEntriesFromDefinition()` now records unsupported xref-stream entry types greater than `2` as current type-`0` ownership rows when the object number is known. This preserves latest-section ownership during `/Prev` replay and prevents a stale compressed object from being imported as a page, catalog, metadata, attachment, or WordPress paragraph source.

## Red-first evidence

Before the parser edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefStreamUnsupportedTypeObjectStreamCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL treats unsupported xref-stream entry types as current null owners before object-stream Prev replay
Expected: ['Current unsupported-type guard page', 'Unknown xref row suppresses stale member']
Actual: ['Current unsupported-type guard page', 'Unknown xref row suppresses stale member', 'Stale unsupported-type compressed page']
1 test files, 1 assertions, 1 failures
```

## Focused verification

After the parser edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefStreamUnsupportedTypeObjectStreamCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS treats unsupported xref-stream entry types as current null owners before object-stream Prev replay
1 test files, 21 assertions, 0 failures
```

Adjacent xref/object-stream family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefStreamUnsupportedTypeObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamObjectOwnerFreeEntryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamFreeEntryPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevObjectStreamGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamObjectOwnerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevHybridOwnerCurrentBaseTest.php
Focused test run: 6 selected test files (root lock skipped)
PASS keeps current xref-stream free row before stale Prev object-stream member
PASS skips Prev type-2 rows when the object-stream carrier is only a compressed previous-generation decoy
PASS preserves Prev type-2 rows when the latest section repeats the same object-stream carrier storage
PASS rejects stream-owned xref stream objects before current-base WordPress text extraction
PASS keeps current xref-stream free entries authoritative over stale direct and previous object-stream owners
PASS keeps current xref-stream object-stream owner before stale Prev hybrid type-2 rows
PASS treats unsupported xref-stream entry types as current null owners before object-stream Prev replay
6 test files, 126 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-stream-unsupported-type-object-stream-currentbase.php
```

The smoke emits `uses_current_guard_page=true`, `reports_current_null_owner=true`, `suppresses_previous_compressed_owner=true`, `previous_entry_type=2`, `previous_object_stream=6`, `excluded_stale_compressed_page=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat the accepted xref-stream free-entry, malformed-width, object-owner, generation-index repair, nested helper object-stream, or `/Prev` direct-owner slices. The new boundary is an unsupported current xref-stream entry type greater than `2` suppressing a stale previous type-`2` object-stream page member before WordPress text import.

## Dependency closure

No new support component is needed. The behavior reuses the existing native PHP xref-stream parser, `/Prev` merge logic, object-stream replay, FlateDecode support, and WordPress smoke/example path. GPU/model/OCR parity remains intentionally out of scope under the current no-GPU markerPDF directive.
