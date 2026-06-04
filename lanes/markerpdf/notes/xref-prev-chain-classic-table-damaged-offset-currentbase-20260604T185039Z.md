# markerPDF xref Prev classic-table damaged offset current-base

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260604T185039Z`

## Source truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py` and delegates low-level xref/object recovery to pdftext/PDFium. Under the current no-GPU markerPDF scope, the native PHP lane owns this parser dependency boundary.

PDF incremental updates keep older xref sections available through `/Prev`, but the latest xref section owns current objects. This slice covers the classic xref-table form of the already accepted xref-stream repair: same-generation current catalog/page/content/metadata/attachment objects are appended after the previous xref table, while the latest classic table marks them in-use with damaged explicit `0000000000 ... n` offsets.

## Implementation

`PdfTextExtractor`, `PdfMetadataExtractor`, and `PdfEmbeddedFileExtractor` now repair latest classic xref-table in-use rows when:

- the table trailer has a valid `/Prev`;
- the explicit row offset does not resolve to a direct object definition;
- a same object/generation direct definition exists after the previous xref section and before the current xref table.

The repair reuses the existing bounded current-update owner scan used by xref-stream rows. It does not reinterpret free rows, rows whose offsets already resolve, rows outside the current update span, or unrelated fallback object scans.

## Evidence

Red-first focused run before source edits:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
FAIL repairs same-generation current update objects when classic xref Prev rows have damaged explicit offsets
Actual: array (
)
1 test files, 72 assertions, 1 failures
```

Focused passing run after source edits:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
1 test files, 88 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-incremental-update-currentbase.php
```

The smoke emits `classic_table_same_generation_current_xmp_selected=true`, `classic_table_same_generation_current_info_selected=true`, `classic_table_same_generation_current_text_selected=true`, `classic_table_same_generation_current_attachment_selected=true`, `classic_table_same_generation_stale_prev_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat the accepted xref-stream `/Prev` damaged-offset repair, malformed xref-stream `/Index` direct-offset repair, generation-mismatch metadata guard, trailer `/Root` generation repair for EmbeddedFiles, hybrid xref `/XRefStm` precedence, classic xref rebuild startxref recovery, or object-stream generation/free-entry repairs.

The bounded new behavior is specifically latest classic xref-table `/Prev` rows with damaged explicit offsets for same-generation current-update objects.

## Dependency closure

No new support component is needed. This slice reuses the native direct-object scanner, xref table parser, `/Prev` chain walker, current-update owner scan, XMP/Info/catalog metadata extraction, EmbeddedFiles extractor, and searchable text extractor. Full upstream model parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed here.
