# markerpdf xref Prev-chain sparse latest Info current-base

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260605T033057Z`
Base accepted HEAD: `67464cb5b5d9caf0890590e5e372b6a815b5c8ec`

## Behavior

Some incremental PDFs append a final xref stream that only points at `/Prev`
and omits `/Info`, while the previous xref section carries the current
document information dictionary. `PdfMetadataExtractor` previously inspected
only the latest trailer dictionary for `/Info`, so these files kept current
text and catalog metadata but lost the current Info dictionary.

This patch makes metadata Info resolution follow the `startxref` `/Prev` chain
across xref streams and classic xref tables, then resolves the exact
object-generation reference through the existing current-object owner map. The
old latest-trailer lookup remains as fallback for PDFs without a usable xref
chain.

## Evidence

Red-first after adding the focused fixture:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php`

Result: `1 test files, 203 assertions, 1 failures`; the new sparse latest xref
fixture reported metadata source `catalog` instead of `info, catalog`.

After the implementation:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php`

Result: `1 test files, 217 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-incremental-update-currentbase.php`

Result: exits `0`; reports
`sparse_latest_xref_stream_prev_info_selected=true`, current sparse text and
attachment selected, stale sparse latest trailer content excluded, and no
Python/OCR/model/external PDF tool execution.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP xref
table/stream, `/Prev`, dictionary, compressed helper operand, and
object-generation owner-map primitives already present in markerPDF.

Root harness not run - isolated micro-slice.
