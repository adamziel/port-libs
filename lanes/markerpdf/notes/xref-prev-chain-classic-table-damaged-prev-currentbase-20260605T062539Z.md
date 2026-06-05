# markerPDF xref Prev classic-table damaged Prev current-base

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260605T062539Z`

## Source truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py` and delegates low-level xref/object recovery to pdftext/PDFium. Under the current no-GPU markerPDF scope, the native PHP lane owns this parser dependency boundary for searchable PDFs, metadata, and embedded-file import.

PDF incremental updates keep older xref sections available through `/Prev`, while the latest xref section owns current objects. A damaged `/Prev` pointer in a latest classic xref table can point into object body bytes instead of the previous table. This slice covers the bounded recovery path where the parser first falls back to the latest valid previous xref section before repairing stale same-generation rows in the current classic table.

## Implementation

`PdfTextExtractor`, `PdfMetadataExtractor`, and `PdfEmbeddedFileExtractor` now repair latest classic xref-table in-use rows after resolving a damaged `/Prev` through the existing previous-section fallback. The xref-existence probe parses candidate classic tables without recursively invoking current-row repair.

The repair remains bounded to:

- latest classic xref-table rows with a trailer `/Prev`;
- stale or invalid in-use row offsets for same object/generation direct definitions;
- direct definitions after the recovered previous xref section and before the current xref table.

It does not reinterpret free rows, skip generation checks, prefer stale previous storage over current bodies, or expand the post-EOF rebuild boundary.

## Evidence

Focused red probe after adding the fixture showed text repair was current but metadata still used the stale XMP object:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
FAIL repairs latest classic xref-table stale rows after damaged Prev pointer recovery
Expected: 'Current Damaged Prev Table XMP Title'
Actual: 'Stale Damaged Prev Table XMP Title'
1 test files, 254 assertions, 1 failures
```

Focused passing run after source edits:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
1 test files, 268 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-incremental-update-currentbase.php
```

The smoke emits `classic_table_damaged_prev_current_xmp_selected=true`, `classic_table_damaged_prev_current_info_selected=true`, `classic_table_damaged_prev_current_text_selected=true`, `classic_table_damaged_prev_current_attachment_selected=true`, `classic_table_damaged_prev_stale_prev_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat the accepted xref-stream damaged explicit-offset repair, classic xref-table valid-`/Prev` damaged-row repair, indirect `/Prev` helper repair, compressed object-stream `/Prev` helper repair, sparse latest Info inheritance, latest `/Info null` cutoff, malformed xref-stream `/Index` direct-offset repair, generation-mismatch metadata guard, trailer `/Root` generation repair for EmbeddedFiles, hybrid xref `/XRefStm` precedence, classic xref rebuild startxref recovery, or object-stream generation/free-entry repairs.

The bounded new behavior is specifically latest classic xref-table stale rows when the latest trailer `/Prev` pointer is itself damaged and must be recovered before current-row repair.

## Dependency closure

No new support component is needed. This slice reuses the native direct-object scanner, classic xref table parser, `/Prev` chain walker, current-update owner scan, XMP/Info/catalog metadata extraction, EmbeddedFiles extractor, WordPress smoke path, and searchable text extractor. Full upstream model parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed here.
