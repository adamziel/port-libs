# markerpdf xref Prev chain duplicate Prev current-base

Slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260607T154121Z`

Accepted base: `ae851dc273eeed6158fd120747071605c45efcaa`

## Source truth

- Upstream markerPDF relies on native PDF parser object/xref recovery before any OCR or model stages. Under the current no-GPU markerPDF scope, this port owns searchable-PDF xref-chain repair for text, metadata, and attachment extraction.
- PDF dictionaries can contain duplicate keys in damaged or producer-written incremental updates. For xref-section trailers and xref-stream dictionaries, the parser should use the last top-level `/Prev` operand in that dictionary, matching the existing last-key top-level operand behavior used elsewhere in the port.
- Existing coverage already handled direct `/Prev` chains, indirect `/Prev` helpers, compressed `/Prev` operands, forward `/Prev` rejection, omitted rows, free rows, and action/page review indirect `/Prev`. This slice targets duplicate top-level `/Prev` operands in the active xref stream.

## Behavior

- `PdfTextExtractor` now reads the last top-level `/Prev` value from a classic trailer or xref-stream dictionary before resolving integer or indirect helper values.
- Duplicate `/Prev` xref-stream dictionaries such as `/Prev <stale-base> /Prev <current-middle>` now follow the final operand, preserving current incremental rows for page text, XMP metadata, Info metadata, catalog language, and EmbeddedFiles name trees.
- The WordPress smoke confirms stale previous-section text, metadata, and attachment payloads stay excluded, with no Python, model, OCR, PDF action execution, or external PDF tools.

## Evidence

Red-first focused run before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainDuplicatePrevCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL uses the last top-level xref-stream Prev entry before inheriting incremental update rows (lanes/markerpdf/tests/PdfXrefPrevChainDuplicatePrevCurrentBaseTest.php)
latest duplicate Prev entry selects current text rows
Expected: array (
  0 => 'Current duplicate Prev page',
  1 => 'Last Prev should win',
)
Actual: array (
  0 => 'Stale duplicate Prev base page',
)

1 test files, 1 assertions, 1 failures
```

Focused after fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainDuplicatePrevCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses the last top-level xref-stream Prev entry before inheriting incremental update rows

1 test files, 25 assertions, 0 failures
```

Adjacent xref `/Prev` family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainDuplicatePrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainOmittedCurrentRowsCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainCompressedRootOmittedRowsCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainForwardPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamIndirectPrevOffsetRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamIndirectPrevObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfPageReviewXrefPrevChainIndirectPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainActionReviewIndirectPrevCurrentBaseTest.php
Focused test run: 9 selected test files (root lock skipped)
...
9 test files, 740 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-duplicate-prev-currentbase.php
```

The smoke exits 0 and reports `duplicate_prev_entries_present=true`, `current_text_selected=true`, `current_xmp_selected=true`, `current_info_selected=true`, `current_attachment_selected=true`, `attachment_preflight_selected=true`, `stale_prev_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

- Does not repeat indirect action-review `/Prev`, page-review indirect `/Prev`, direct `/Prev` current-row repair, compressed `/Prev`, omitted rows, duplicate free rows, forward `/Prev` rejection, or classic xref rebuild boundaries.
- Does not run OCR/model/GPU paths, Python workers, external PDF tools, live services, or PDF actions.

## Dependency closure

No new support component is needed. This reuses the existing native PHP xref stream decoding, top-level dictionary operand scanner, object-reference resolver, metadata extraction, EmbeddedFiles extraction, and WordPress block smoke path.
