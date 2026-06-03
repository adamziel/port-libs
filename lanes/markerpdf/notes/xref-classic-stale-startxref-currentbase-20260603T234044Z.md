# markerPDF classic stale startxref rebuild boundary

Micro-slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260603T234044Z`

Session: `port-dev-markerpdf-xref-classic-rebuild-20260603T234044Z`

Base accepted HEAD: `9f8ae4fb71ff5c28527f923a11d9eebb6d57eab4`

## Source Truth

Upstream markerPDF delegates searchable-PDF extraction through `marker/pdf/extract_text.py` into pdftext/PDFium. That leaves xref repair as a native parser dependency boundary for this PHP lane: when `startxref` is stale or damaged, a tolerant parser may rebuild from top-level classic xref tables, but the latest valid classic trailer must remain the current document boundary before WordPress text extraction.

## Behavior

`PdfTextExtractor` now treats a final `startxref` that still points at an older valid classic xref table as rebuildable when a later top-level classic xref table and trailer are present. The rebuild helper is intentionally classic-table only: xref-stream startxref failures still use the existing fail-closed path.

The focused fixture appends:

- an earlier valid classic xref table and trailer rooted at stale object `1`;
- later current catalog/page/content objects plus a later valid classic xref table rooted at object `10`;
- a final `startxref` token that incorrectly points back to the earlier valid table.

Before the fix, the parser accepted the stale pointer and emitted `Stale valid startxref page` / `Earlier trailer root leak`. After the fix, WordPress paragraph extraction emits only `Current stale-pointer rebuild page` / `Stale startxref pointer repaired`.

## Verification

Red-first focused check before the parser patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rebuilds damaged startxref from the latest classic xref trailer boundary before WordPress text extraction
FAIL rebuilds stale but valid startxref from the later classic xref trailer boundary before WordPress text extraction
1 test files, 10 assertions, 1 failures
```

Focused green after repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rebuilds damaged startxref from the latest classic xref trailer boundary before WordPress text extraction
PASS rebuilds stale but valid startxref from the later classic xref trailer boundary before WordPress text extraction
1 test files, 18 assertions, 0 failures
```

Adjacent xref safety check:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserSecurityXrefFilterErrorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefOffsetOwnerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefCurrentBaseRepairBoundaryTest.php
Focused test run: 4 selected test files (root lock skipped)
4 test files, 40 assertions, 0 failures
```

Broader xref/parser family after tightening the in-file xref-stream fallback boundary:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'Pdf(Xref|Parser).*Test\.php|PdfTextExtractorTest\.php' | sort)
Focused test run: 80 selected test files (root lock skipped)
80 test files, 1943 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-classic-stale-startxref-currentbase.php
```

Result: emitted two Gutenberg paragraphs for the current stale-pointer rebuild text and metadata booleans `uses_latest_classic_trailer_root=true`, `repairs_stale_valid_startxref_pointer=true`, `excludes_stale_valid_startxref_page=true`, `excludes_earlier_trailer_root_leak=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat the accepted invalid `startxref 999999` classic rebuild slice, xref-stream `/Prev` exact-offset generation repair, hybrid `/XRefStm` free-entry ownership, object-stream generation-zero conflict repair, xref-stream trailer metadata precedence, stream-owned fake xref offset rejection, direct stream owner lookup, latest trailer root generation recovery, current-base nonzero-generation repair, object-stream filter-chain ownership, malformed CMap filter boundary, or missing-Length stream-filter stack recovery.

The new behavior is specifically a stale but syntactically valid final `startxref` pointer to an earlier classic table. The parser rebuilds to a later top-level classic xref table/trailer before selecting visible page text or trailer encryption/root metadata.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP direct-object scanner, classic xref table parser, trailer parser, page-tree walker, stream decoder, content-token extractor, and WordPress smoke path. Full upstream markerPDF parity remains gated by pdftext/PDFium, Surya/Torch OCR/layout/table models, Texify equation recognition, Streamlit/FastAPI model workers, benchmark/model downloads, and GPU/model execution; none were run for this no-GPU native PHP slice.
