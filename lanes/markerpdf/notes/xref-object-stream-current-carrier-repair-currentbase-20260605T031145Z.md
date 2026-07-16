# markerPDF xref object-stream current-carrier repair current-base

Micro-slice: `markerpdf-object-stream-xref-parser-current-base-20260605T031145Z`
Session: `port-dev-markerpdf-object-xref-20260605T031145Z`
Base accepted HEAD: `5fa3b785574733506c7d7bc664e972380aeaa321`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` gets searchable PDF text through `marker/pdf/extract_text.py` and pdftext/PDFium before model execution. This slice stays in the current no-GPU native parser scope.

PDF object streams are selected through xref-stream type-2 rows. The parser must keep the selected `/ObjStm` carrier available before page-tree text extraction, while keeping object-stream member dictionaries and stale fallback streams out of WordPress-visible paragraphs.

## Behavior

The focused fixture builds a PDF whose latest xref stream has:

- object `4` as a type-2 member of object-stream carrier `6`;
- a damaged same-section direct xref row for carrier `6` that selects no direct object;
- a valid direct `/ObjStm` object `6 0` before the xref stream;
- a stale standalone stream that should only leak if page-tree extraction fails and fallback stream scanning wins.

Before the fix, text extraction emitted the current compressed page text plus the stale fallback stream. `PdfTextExtractor::xrefEntriesFromOffsetChain()` now repairs only current object-stream carrier rows that are named by current type-2 entries and whose direct row selects no object, bounded to the current revision window. The current compressed page expands, the stale fallback stream stays out of Gutenberg paragraphs, and review metadata reports carrier `6` as an xref-selected direct object-stream carrier.

## Evidence

Red-first focused run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamCurrentCarrierRepairCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL repairs current xref stream carrier rows before expanding type-2 object-stream members (lanes/markerpdf/tests/PdfXrefObjectStreamCurrentCarrierRepairCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'Current repaired carrier page',
  1 => 'Type two row selected',
)
Actual: array (
  0 => 'Current repaired carrier page',
  1 => 'Type two row selected',
  2 => 'Stale fallback stream leaked',
)

1 test files, 1 assertions, 1 failures
```

Focused passing run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamCurrentCarrierRepairCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS repairs current xref stream carrier rows before expanding type-2 object-stream members

1 test files, 20 assertions, 0 failures
```

Adjacent object-stream/xref sweep:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'ObjectStream|Xref.*Object|Parser.*ObjectStream' | sort)
Focused test run: 42 selected test files (root lock skipped)
42 test files, 732 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-current-carrier-repair-currentbase.php
```

The smoke emitted `uses_current_repaired_carrier_page=true`, `uses_current_type2_member=true`, `excludes_stale_fallback_stream=true`, `excludes_compressed_member_metadata=true`, `object_stream=6`, `object_stream_xref_entry_type=1`, `object_stream_xref_generation=0`, `object_stream_owner_policy=xref_selected_object_stream_carrier`, `page_count=1`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat inherited `/Prev` carrier provenance, previous carrier generation recovery, current free-entry suppression, hybrid table/free owner precedence, object-stream stream-member rejection, explicit member-index alignment, zero-width member-index repair, duplicate member rejection, indirect object-stream filter-chain operand recovery, or named-destination xref offset ownership.

The bounded behavior here is same-section current xref-stream repair for a damaged direct `/ObjStm` carrier row that is required by valid current type-2 compressed-object rows.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP direct-object scanner, xref-stream decoder, current revision xref merger, object-stream expander, page-tree walker, content-stream text extractor, review metadata path, and WordPress smoke renderer. Full upstream markerPDF parity remains intentionally outside this no-GPU slice for live OCR, Surya/Torch, Texify, Streamlit/FastAPI model workers, and exact model benchmark parity.
