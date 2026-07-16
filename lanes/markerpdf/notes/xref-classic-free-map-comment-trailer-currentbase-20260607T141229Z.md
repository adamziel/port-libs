# markerPDF classic xref free-map comment trailer boundary

Micro-slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260607T141229Z`  
Session: `port-dev-markerpdf-xref-classic-rebuild-20260607T141229Z`  
Accepted base: `9fa2532d1407cdfcf7979d602b49aba1b4031366`

## Source Truth

Upstream markerPDF relies on parser-backed searchable-PDF extraction before OCR/model stages. In this no-GPU PHP lane, the native parser owns classic xref table repair and free-entry suppression before WordPress link/annotation promotion. PDF classic xref table parsing treats `%` comments as whitespace; a `trailer` token inside a comment line is not the trailer dictionary for the table.

## Behavior

`PdfXrefFreeObjectMap` now locates the real classic xref-table trailer with token-aware scanning. It skips comment lines, literal strings, hex strings, arrays, and dictionaries, and stops at `startxref` or `%%EOF` if no valid trailer dictionary is found.

Before this patch, the lightweight free-object map path used the first raw `trailer` keyword after `xref`. A commented trailer-looking token before a later `7 1` free-row subsection truncated the current table, causing object `7` to be inherited from the previous section as a stale Link annotation. WordPress link extraction could then promote the stale URI. The main text extractor already kept current page text; this fixes the annotation/free-row boundary.

## Evidence

Red-first:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildFreeObjectMapCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
...
FAIL skips commented trailer tokens while rebuilding the free-object map before annotation review
1 test files, 56 assertions, 1 failures
```

After fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildFreeObjectMapCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rebuilds damaged classic startxref for the free-object map before annotation review
PASS ignores literal-string xref decoy while rebuilding the free-object map before annotation review
PASS ignores name-delimited xref pseudo-table while rebuilding the free-object map before annotation review
PASS uses EOF-bounded current classic xref for the free-object map when final startxref is missing
PASS skips commented trailer tokens while rebuilding the free-object map before annotation review
1 test files, 64 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-free-object-map-comment-trailer-currentbase.php
```

The smoke exits 0 and emits `commented_trailer_token_skipped=true`, `current_free_row_preserved=true`, `stale_link_annotation_suppressed=true`, `span_link_promoted=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted classic xref damaged `startxref`, stale valid `startxref`, missing final `startxref`, post-EOF xref garbage, stream-owned xref payloads, object-owned startxref boundaries, name-delimited xref pseudo-tables, literal-string decoys, NUL/form-feed whitespace, plus-signed headers, zero-count sections, generation-offset repair, malformed rows, overdeclared counts, or full text/metadata/attachment xref rebuild behavior. The bounded behavior is only the free-object map trailer scanner rejecting commented trailer-looking tokens before link/annotation suppression.

## Dependency Closure

No new support component is needed. This reuses the native PHP direct-object scanner, classic xref table parser, free-object map, annotation extractor, link annotation extractor, and WordPress smoke path. Live OCR, Surya/Texify/Torch, pypdfium/PDFium execution, raster rendering, Streamlit/FastAPI model workers, JavaScript/PDF action execution, and external PDF tools remain intentionally out of scope under the current markerPDF no-GPU directive.
