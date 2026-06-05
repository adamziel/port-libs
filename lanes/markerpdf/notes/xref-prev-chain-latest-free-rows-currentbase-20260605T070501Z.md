# markerPDF xref Prev chain latest free rows current-base

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260605T070501Z`
Base: `f6c038717601b56c1747a9a15940b54293030915`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF text and metadata through pdftext/PDFium-backed object loading before WordPress-facing conversion. Under the current no-GPU markerPDF scope, the native PHP lane owns the equivalent parser boundary for xref `/Prev` chain selection, catalog metadata, Info dictionaries, page text, and EmbeddedFiles name trees without running Python, OCR, models, or external PDF tools.

PDF incremental updates keep older xref sections reachable through `/Prev`, but the latest xref section remains authoritative for object liveness. When the latest xref stream marks an older same-generation object as free, import must not revive stale previous-section metadata, text, or attachments just because current catalog/trailer references still name those object numbers.

## Behavior

The focused fixture appends a latest xref stream with `/Prev` pointing to a previous classic xref table. The latest catalog still references `/Metadata 7 0 R` and `/Names << /EmbeddedFiles 8 0 R >>`, and the latest xref stream trailer still references `/Info 6 0 R`. The current xref stream then marks the previous Info, XMP, name-tree, FileSpec, and EmbeddedFile object numbers as free while keeping the current catalog, page tree, page, content stream, and font live.

The current base already handles this object-liveness boundary correctly. WordPress import selects the current page text and current catalog language, while previous-section XMP, Info metadata, EmbeddedFiles summaries, attachment payloads, and stale page text remain suppressed. No production source change was required.

## Evidence

Focused green:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
17 PASS cases
1 test files, 286 assertions, 0 failures
```

PHP lint:

```text
php -l lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-free-rows-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-free-rows-currentbase.php
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-free-rows-currentbase.php
current_text_selected=true
stale_text_excluded=true
current_catalog_language_selected=true
previous_xmp_suppressed_by_free_row=true
previous_info_suppressed_by_free_row=true
previous_attachment_suppressed_by_free_row=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted indirect `/Prev` helper repair, compressed `/Prev` helper repair, damaged latest classic-table `/Prev` fallback, damaged xref-stream explicit-offset repair, sparse latest Info inheritance, `/Info null` cutoff, object-stream free-entry precedence, hybrid xref `/XRefStm` free-entry precedence, stream-filter boundary work, encryption preflight, OCR/model execution, or table/equation handoffs.

The bounded behavior is specifically latest xref-stream free-row authority over previous `/Prev` metadata, text, and attachment objects when current catalog/trailer references still point at those now-free object numbers.

## Dependency Closure

No new support component is needed. This reuses native PHP direct-object scanning, xref stream parsing, classic `/Prev` chain walking, latest-section object liveness, text extraction, XMP/Info/catalog metadata extraction, EmbeddedFiles name-tree extraction, and the WordPress smoke path. Full upstream model parity remains dependency-gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed here.
