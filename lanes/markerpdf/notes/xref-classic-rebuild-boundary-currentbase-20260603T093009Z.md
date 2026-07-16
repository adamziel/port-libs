# markerPDF classic xref rebuild boundary

Micro-slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260603T093009Z`

Session: `port-dev-markerpdf-xref-classic-rebuild-20260603T093009Z`

Base accepted HEAD: `ccdbc8f5f239ec3e14bb71edbef4e8cc79cd8677`

## Source Truth

Upstream markerPDF delegates searchable-PDF page text extraction through `marker/pdf/extract_text.py` into pdftext/PDFium. That makes xref repair a native parser boundary for this PHP lane: when `startxref` is damaged, a tolerant parser may rebuild from top-level classic xref tables, but the latest valid classic trailer must remain the current document boundary.

## Behavior

`PdfTextExtractor` now recovers the latest valid top-level classic xref table when the declared `startxref` cannot select a valid xref table or xref stream. The recovered classic trailer `/Root` is used for catalog promotion before page-tree traversal, so stale earlier classic xref tables do not become the first visible catalog during WordPress paragraph extraction.

The focused fixture appends:

- a stale classic xref table and trailer rooted at object `1`;
- a later valid classic xref table and trailer rooted at object `10`;
- a final damaged `startxref 999999`.

Before the fix, text extraction selected the stale first catalog and emitted `Stale classic rebuild page` / `Old trailer root leak`. After the fix, it emits only `Current classic rebuild page` / `Latest trailer boundary kept`.

## Verification

Red-first focused check before the parser patch:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php
```

Result:

```text
1 test files, 1 assertions, 1 failures
```

Focused check after the parser patch:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildBoundaryCurrentBaseTest.php
```

Result:

```text
1 test files, 9 assertions, 0 failures
```

Adjacent xref/parser family:

```bash
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'Pdf(Xref|Parser).*Test\.php|PdfTextExtractorTest\.php' | sort)
```

Result:

```text
79 test files, 1871 assertions, 0 failures
```

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-boundary-currentbase.php
```

Result: emitted two Gutenberg paragraphs for the current classic rebuild text and metadata booleans `uses_latest_classic_trailer_root=true`, `keeps_latest_trailer_boundary=true`, `excludes_stale_classic_rebuild_page=true`, `excludes_old_trailer_root_leak=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted xref-stream `/Prev` exact-offset generation repair, hybrid `/XRefStm` free-entry ownership, object-stream generation-zero conflict repair, xref-stream trailer metadata precedence, stream-owned fake xref offset rejection, direct stream owner lookup, latest trailer root generation recovery, current-base nonzero-generation repair, object-stream filter-chain ownership, or the missing-Length stream-filter stack boundary. The new behavior is specifically damaged-`startxref` recovery to the latest valid classic xref table/trailer boundary before stale earlier classic table catalogs are considered.

## Dependency Closure

No new support component is needed. This slice reuses the native direct-object scanner, classic xref table parser, trailer parser, page-tree walker, stream decoder, content-token extractor, and WordPress smoke path. Full upstream markerPDF parity remains gated by pdftext/PDFium, Surya/Torch OCR/layout/table models, Texify equation recognition, Streamlit/FastAPI model workers, benchmark/model downloads, and GPU/model execution; none were run for this no-GPU native PHP slice.
