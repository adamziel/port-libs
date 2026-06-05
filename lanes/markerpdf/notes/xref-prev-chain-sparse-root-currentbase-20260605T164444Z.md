# markerpdf xref Prev-chain sparse latest Root current-base

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260605T164444Z`
Base accepted HEAD: `919366e8669f709c0b980d9bb4babab7e3c4f1cd`

## Behavior

Some incremental PDFs append a sparse final xref stream that updates the
objects reachable from the current catalog but omits `/Root` from the stream
dictionary. The previous xref section remains the active trailer owner for
`/Root`, while the current xref stream rows replace that inherited catalog,
page tree, XMP metadata, Info dictionary, and EmbeddedFiles name tree.

`PdfTextExtractor` already followed the active `startxref` `/Prev` chain for
this Root selection. The lighter metadata, embedded-file, and attachment
preflight extractors only inspected the latest trailer dictionary, so they
could fall back to an unreferenced lower-numbered `/Type /Catalog` decoy when
the final xref stream omitted `/Root`.

This patch makes those extractors resolve trailer `/Root` through the active
`startxref` `/Prev` chain across xref streams, classic xref tables, repaired
Prev offsets, and hybrid `/XRefStm` dictionaries. Explicit non-reference Root
values such as `/Root null` still block fallback, preserving the accepted
root-null behavior.

## Evidence

Red-first after adding the focused fixture:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php`

Result: `1 test files, 439 assertions, 1 failures`; metadata selected
`Decoy Sparse Root XMP Title` from the unreferenced lower-numbered catalog
instead of inheriting the previous trailer Root and applying current rows.

After the implementation:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php`

Result: `1 test files, 466 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-sparse-root-currentbase.php`

Result: exits `0`; reports
`latest_sparse_xref_stream_inherits_prev_root=true`,
`current_info_selected=true`, `current_attachment_selected=true`,
`attachment_preflight_uses_current_root=true`, `decoy_catalog_excluded=true`,
`previous_root_excluded_after_current_update=true`, and no Python/OCR/model or
external PDF tool execution.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP xref
table/stream parser, trailer dictionary parsing, `/Prev` traversal,
`/XRefStm` handling, compressed stream filters, and current object-generation
selection already present in markerPDF.

Root harness not run - isolated micro-slice.
