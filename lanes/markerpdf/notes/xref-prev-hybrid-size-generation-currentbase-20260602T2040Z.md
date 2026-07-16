# markerPDF xref Prev hybrid Size generation currentbase

Micro-slice: `xref-prev-hybrid-size-generation-currentbase`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes native PDF page text through `marker/pdf/extract_text.py::get_text_blocks()` via `pdftext.extraction.dictionary_output(...)` and `naive_get_text()` via pypdfium/PDFium page text. The PHP lane therefore owns the parser dependency boundary where xref-selected catalog/page/content objects are recovered before Gutenberg paragraphs are emitted.

Relevant PDF parser behavior for this slice: xref-streams without `/Index` default to the `/Size` range, but malformed incremental PDFs can underdeclare `/Size` while carrying an exact larger decoded row table. The current native parser already repairs that bounded row count. A latest hybrid xref table can also carry a stale same-object generation-zero row while the trailer `/Root` reference names the nonzero-generation catalog recovered through the repaired `/Prev` xref stream.

## Behavior

The focused current-base fixture builds:

- a stale generation-zero catalog/page/content path;
- a previous xref stream with `/W [1 4 1]`, no `/Index`, `/Size 3`, and six exact decoded rows for generation-one catalog/page/content objects;
- a latest hybrid xref table with `/Prev` to that stream, `/XRefStm` present, and stale table rows for the generation-zero catalog/page/content path;
- a latest trailer `/Root 1 1 R`.

`PdfTextExtractor` now carries the latest trailer `/Root` as an exact object/generation reference, repairs that direct generation when available, and reruns generation-aware page-tree reference repair before promoting the root catalog. WordPress text extraction emits only `Current Prev hybrid size generation page` and `Root generation repaired`. The stale generation-zero page stays excluded.

## Evidence

Focused test:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevHybridSizeGenerationCurrentBaseTest.php
```

Red baseline before source repair:

```text
FAIL repairs trailer root generation through Prev hybrid underdeclared Size rows
Actual: array (
  0 => 'Stale root generation zero page',
)
1 test files, 1 assertions, 1 failures
```

Result:

```text
PASS repairs trailer root generation through Prev hybrid underdeclared Size rows
1 test files, 8 assertions, 0 failures
```

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-hybrid-size-generation-currentbase.php
```

The smoke emits `uses_current_prev_hybrid_size_generation_page=true`, `repairs_root_generation_reference=true`, `excludes_stale_root_generation_zero_page=true`, `page_count=1`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted no-`/Index` xref-stream `/Size` repair by itself, hybrid table direct-generation precedence over companion `/XRefStm` type-2 rows, `/Prev` duplicate `/Index` row precedence, previous object-stream carrier generation guards, incremental free-entry suppression, object-stream member-index repair, latest trailer `/Root` object-number promotion, or xref-stream owner-boundary rejection.

The bounded behavior here is specifically the combined current-base evidence that a stale same-object generation-zero latest hybrid table row does not defeat a trailer `/Root` nonzero-generation catalog whose page/content graph is recovered through a `/Prev` xref stream with underdeclared `/Size`.

## Dependency Closure

No new support component is needed. This slice reuses the native direct-object scanner, xref table parser, xref stream decoder, `/Prev` chain walker, generation-aware object-reference repair, page-tree walker, stream decoder, content-token extractor, and WordPress smoke path. Full upstream markerPDF parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
