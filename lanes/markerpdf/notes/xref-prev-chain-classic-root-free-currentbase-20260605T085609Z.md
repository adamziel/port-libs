# markerPDF xref Prev classic root-free current-base

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260605T085609Z`

Base: `f5c7edb91ea7c6e3cd3926bdcae9179c3343e48f`

## Source Truth

Upstream markerPDF delegates searchable-PDF loading to parser-backed `pdftext`/PDFium behavior before any OCR/model fallback. In this native no-GPU PHP lane, xref traversal and incremental-update object liveness are parser boundaries.

PDF incremental updates keep previous xref sections reachable through `/Prev`, but the latest xref section owns rows it lists. If a latest classic xref table omits `/Root`, stores `/Prev` in a safe direct numeric helper before that table, and marks the inherited catalog object free, the previous catalog/page tree must not be revived through fallback scanning. A same-number helper object after the current table is outside the current table's owner boundary and must not shadow the pre-table `/Prev` helper.

## Implementation

`PdfTextExtractor::xrefSectionEntriesAndPreviousOffset()` now uses the existing bounded `previousXrefOffsetForSectionBody()` resolver for classic table sections. That makes root-free detection follow the same direct-helper owner rule as the main xref-chain merge path.

The focused fixture builds:

- a previous classic xref section with stale catalog, page text, XMP metadata, Info, and EmbeddedFiles;
- a pre-table `30 0 obj` containing the previous xref offset;
- a latest classic xref table with no `/Root`, `/Prev 30 0 R`, and a free row for object `1`; and
- a post-table `30 0 obj` decoy that must not shadow the helper.

## Evidence

Red-first focused run after adding the fixture:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
FAIL blocks previous trailer root fallback when latest classic table frees inherited catalog through direct Prev helper
Expected: array (
)
Actual: array (
  0 => 'Stale classic root free direct Prev page',
)
1 test files, 323 assertions, 1 failures
```

Focused green after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
1 test files, 339 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-root-free-currentbase.php
classic_table_direct_prev_helper_selected=true
classic_table_post_xref_decoy_present=true
classic_table_paragraphs_imported=0
classic_table_stale_page_excluded=true
classic_table_stale_xmp_excluded=true
classic_table_stale_attachment_excluded=true
classic_table_info_source_carried=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

## Non-Overlap

This does not repeat accepted xref-stream root-free suppression, latest xref-stream free rows for metadata/name-tree objects, sparse `/Info` inheritance, `/Info null`, direct or compressed `/Prev` helper repair for live current objects, damaged middle `/Prev` repair, same-generation stale-offset repair, hybrid xref companion handling, object-stream carrier ownership, stream-filter owner boundaries, inline image parsing, runtime conversion boundaries, or OCR/model execution.

The bounded new behavior is specifically latest classic xref-table root-free suppression when `/Prev` is a pre-table direct helper shadowed by a post-table same-number decoy.

## Dependency Closure

No new support component is needed. This reuses native PHP direct-object scanning, safe `/Prev` helper resolution, classic xref table parsing, xref-chain walking, free-row ownership, metadata extraction, EmbeddedFiles extraction, and WordPress smoke rendering. No OCR, Surya, Texify, Torch, Streamlit/FastAPI worker, GPU/model runner, external PDF tool, or online service was used.

## Next Task

Continue with non-overlapping native PDF parser fidelity: fonts/CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
