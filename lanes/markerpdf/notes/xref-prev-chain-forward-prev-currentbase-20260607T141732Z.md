# markerpdf xref Prev chain forward Prev row repair current base

Slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260607T141732Z`

Base accepted HEAD: `9fa2532d1407cdfcf7979d602b49aba1b4031366`

## Behavior

Native xref-stream row repair now normalizes the section `/Prev` operand before
deciding whether explicit type-1 rows point at stale previous-section storage.
When the latest xref stream declares a self/forward `/Prev`, the parser uses the
same repaired earlier xref base for row repair that the chain merge already uses.
This prevents stale current-section rows from replaying previous text,
metadata, catalog, name-tree, filespec, or embedded-file objects.

The change is scoped to native searchable-PDF parsing. It does not invoke OCR,
Surya, Texify, Torch, model workers, external PDF tools, or live services.

## Evidence

Red before source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainForwardPrevCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL repairs stale current xref-stream rows after forward Prev fallback
Expected: Current forward Prev page / Repaired stale xref-stream rows
Actual: Stale forward Prev page
1 test files, 3 assertions, 1 failures
```

Green after source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainForwardPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
Focused test run: 2 selected test files (root lock skipped)
30 PASS cases
2 test files, 580 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-forward-prev-currentbase.php > /tmp/markerpdf-forward-prev-smoke.html
markerpdf-xref-prev-chain-forward-prev-currentbase-smoke:
current_text_selected=true, current_metadata_selected=true,
current_attachment_selected=true, stale_*_excluded=true,
executes_python_or_models=false, executes_external_pdf_tools=false
```

## Non-overlap

This does not repeat the accepted stale explicit-offset, zero-width offset,
sparse-root, unsupported-row, damaged-middle, direct/compressed `/Prev` helper,
classic forward `/Prev`, attachment near-miss, or object-stream carrier
coverage. The new boundary is specifically current xref-stream type-1 stale
row repair when `/Prev` itself must first be repaired from a self/forward
operand to the prior valid xref section.

## Dependency closure

No new support component is needed. The slice reuses the existing native xref
section discovery, stream decoding, and object-offset repair helpers.

## Next task

Keep extending native xref repair into object-stream member and hybrid table
edge cases only where the current-base tests can prove a stale current row would
otherwise replay previous-section storage.
