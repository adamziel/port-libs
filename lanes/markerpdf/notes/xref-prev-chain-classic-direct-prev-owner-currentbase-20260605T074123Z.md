# markerPDF xref Prev classic direct-helper owner current-base

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260605T074123Z`
Base: `77f7b54408a215b8868ef1c3927a9ab284ffa262`

## Source truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text, metadata, and attachments through parser-backed PDF loading before OCR/model fallback. Under the current no-GPU markerPDF scope, the native PHP lane owns the equivalent parser dependency boundary for xref-chain walking, current object selection, catalog/Info metadata, and EmbeddedFiles name-tree selection without Python, OCR, models, or external PDF tools.

PDF incremental updates can store a classic xref table trailer `/Prev` value in an indirect numeric helper. That helper must be selected from direct objects that appear before the current xref table. A later same-number direct object after the xref table is a decoy for current-base recovery and must not shadow the helper used to repair current rows.

## Behavior

`PdfTextExtractor` now resolves xref `/Prev` safe direct numeric helper objects from before the current xref section before falling back to compressed helper lookup. This brings the text extractor in line with the metadata and attachment extractors for classic xref-table `/Prev` owner selection.

The focused fixture builds:

- a previous classic xref table with stale catalog, page text, XMP, Info, name-tree, FileSpec, and EmbeddedFile objects;
- current same-generation replacement objects before a latest classic xref table;
- a direct helper `30 0 obj` before the latest table whose body is the previous xref offset;
- latest table rows with damaged zero offsets and trailer `/Prev 30 0 R`; and
- a later `30 0 obj` decoy after the latest table.

After the patch, WordPress import selects current page text, current XMP, current Info, current catalog language, and the current XML attachment while excluding stale previous-section metadata, attachments, and text.

## Evidence

Red-first focused run after adding the fixture:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL repairs classic xref-table direct Prev helper before post-table same-number decoys
Expected: array (
  0 => 'Current classic direct Prev owner page',
  1 => 'Classic direct Prev helper selected before decoy',
)
Actual: array (
)
1 test files, 287 assertions, 1 failures
```

Focused green after source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
18 PASS cases
1 test files, 305 assertions, 0 failures
```

Adjacent xref Prev family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainHybridTableCompressedPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainUnselectedCarrierCurrentBaseTest.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 336 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-classic-direct-prev-owner-currentbase.php
classic_table_direct_prev_helper_selected=true
post_table_same_number_decoy_present=true
current_text_selected=true
stale_text_excluded=true
current_xmp_title_selected=true
current_info_title_selected=true
current_attachment_selected=true
stale_prev_metadata_excluded=true
stale_prev_attachment_excluded=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

Root harness status: not run - isolated micro-slice.

## Non-overlap

This does not repeat xref-stream direct `/Prev` owner selection, classic xref-table indirect `/Prev` helper repair without a post-table decoy, compressed object-stream `/Prev` helper repair, indirect `/W` and `/Index` xref-stream operand repair, same-generation direct `/Prev` damaged-offset repair, stale explicit offset repair, wrong-current-offset row repair, damaged middle `/Prev` fallback, latest sparse Info inheritance, latest `/Info null`, latest free-row suppression, hybrid free-entry behavior, object-stream generation repair, stream-filter operand owner boundaries, runtime preflight work, or OCR/model execution.

The bounded new behavior is specifically classic xref-table `/Prev` direct numeric helper owner selection when a post-table same-number direct object would otherwise shadow the current-base helper.

## Dependency closure

No new support component is needed. This reuses native PHP direct-object scanning, safe xref operand helper validation, classic xref table parsing, `/Prev` chain walking, current update row repair, XMP/Info/catalog metadata extraction, EmbeddedFiles name-tree extraction, and WordPress smoke rendering. Full upstream parity remains gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed here.
