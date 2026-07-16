# markerPDF xref hybrid Prev trailer Size repair

Slice: `xref-hybrid-prev-chain-trailer-size-repair-currentbase-20260602T1640Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF text through `marker/pdf/extract_text.py::get_text_blocks()` and `naive_get_text()`, delegating low-level xref/object recovery to pdftext/PDFium. The local upstream cache path named in the manifest is unavailable in this isolated worktree, so this slice uses the accepted manifest plus the PDF xref-stream parser semantics already documented by neighboring xref notes as source truth.

PDF xref streams without `/Index` use `/Size` as the default object range. Real-world incremental PDFs can underdeclare that trailer `/Size` while still carrying an exact larger number of fixed-width decoded xref rows. At the native PHP dependency boundary, a hybrid xref table `/Prev` chain should repair that default row range from the decoded byte count when the row width divides the stream exactly, instead of falling back to later stale direct objects.

## Behavior

`PdfTextExtractor::xrefStreamEntriesFromDefinition()` now computes the exact decoded xref row count when the decoded stream length is an exact multiple of `/W` row width. `xrefIndexRanges()` uses that count to repair a no-`/Index` `/Size` upward. Explicit `/Index` ranges remain authoritative and malformed non-row-aligned streams are not expanded.

The focused fixture builds:

- a previous xref stream with `/W [1 4 1]`, no `/Index`, and `/Size 4`;
- six exact decoded rows, including object `5 0` current content beyond the declared `/Size`;
- a later stale `5 1` content stream that should not be selected;
- a latest hybrid xref table with `/Prev` pointing at the underdeclared stream and `/XRefStm` present.

Before the repair, WordPress text extraction selected stale generation-1 text. After the repair, it emits only `Current hybrid Prev size page` and `Trailer size repaired row`.

## Evidence

Red baseline before source repair:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefHybridPrevTrailerSizeRepairCurrentBaseTest.php
```

Result:

```text
FAIL repairs underdeclared xref stream Size through hybrid Prev chains before WordPress text extraction
Actual: array (
  0 => 'Stale oversized trailer page',
)
1 test files, 1 assertions, 1 failures
```

Focused green after repair:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefHybridPrevTrailerSizeRepairCurrentBaseTest.php
```

Result:

```text
PASS repairs underdeclared xref stream Size through hybrid Prev chains before WordPress text extraction
1 test files, 8 assertions, 0 failures
```

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-xref-hybrid-prev-trailer-size-repair-currentbase.php
```

The smoke emits `uses_current_hybrid_prev_size_page=true`, `repairs_underdeclared_trailer_size=true`, `excluded_stale_oversized_trailer_page=true`, `page_count=1`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted hybrid table direct/free-row precedence, xref-stream `/Prev` duplicate `/Index` row preservation, invalid explicit xref-stream offset rejection, object-stream omitted member-index repair, stream-owned xref object rejection, xref-stream DecodeParms predictor decoding, incremental free rows, stale object-stream carrier repair, or nested object-stream filter fail-closed fallback.

The new behavior is specifically no-`/Index` xref-stream trailer `/Size` repair by exact decoded row count when that stream is reached through a hybrid xref table `/Prev` chain.

## Dependency Closure

No new support component is needed. This slice reuses the native direct-object scanner, xref table parser, xref stream decoder, `/Prev` chain walker, page-tree walker, and content-stream text extractor. Full upstream markerPDF parity remains dependency-gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
