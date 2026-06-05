# markerPDF xref-stream direct Prev owner current-base

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260605T054918Z`
Base: `59f74ed0eba0c82ff3e4a59978f6d445940ec730`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF text, metadata, and attachments through parser-backed PDF loading before OCR/model fallback. In this no-GPU PHP lane, the equivalent native boundary is accurate xref-chain walking, xref-stream operand resolution, catalog/Info metadata selection, and EmbeddedFiles name-tree selection without Python, OCR, models, or external PDF tools.

Incremental PDF updates can put the current xref stream's `/Prev` value in a direct indirect numeric helper. That helper must be selected from objects that appear before the current xref stream. A later same-number direct object after the current xref stream is a stale decoy for current-base recovery and must not shadow the operand owner used to repair damaged current rows.

## Behavior

`PdfTextExtractor` now resolves xref-stream dictionary operand helper objects through the latest matching direct object whose offset is before the current xref stream, not the latest same-number object anywhere in the file.

`PdfMetadataExtractor` and `PdfEmbeddedFileExtractor` now resolve safe direct `/Prev` numeric helpers before falling back to compressed object-stream helper lookup. This keeps current same-generation xref-stream rows repairable when their explicit offsets are damaged and a post-xref same-number decoy appears before `startxref`.

The focused fixture builds:

- a previous classic xref table with stale catalog, page text, XMP, Info, name-tree, Filespec, and EmbeddedFile objects;
- current same-generation replacement objects with damaged zero offsets in the current xref stream;
- a direct helper `30 0 obj` before the current xref stream whose body is the previous xref offset;
- a current xref stream with `/Prev 30 0 R`; and
- a later `30 0 obj` decoy after the current xref stream.

After the patch, WordPress import selects current page text, current XMP, current Info, current catalog language, and the current XML attachment while excluding stale previous-section metadata, attachments, and text.

## Evidence

Existing focused baseline before this slice:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
14 PASS cases
1 test files, 231 assertions, 0 failures
```

Focused green after adding the direct `/Prev` owner boundary:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
15 PASS cases
1 test files, 250 assertions, 0 failures
```

Adjacent xref operand-owner family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamIndirectPrevObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamCompressedOperandOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamIndirectIndexWidthCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
4 test files, 334 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-direct-prev-owner-currentbase.php
current_direct_prev_owner_title_selected=true
current_direct_prev_owner_info_selected=true
current_direct_prev_owner_language_selected=true
current_direct_prev_owner_text_selected=true
current_direct_prev_owner_attachment_selected=true
current_direct_prev_owner_payload_selected=true
direct_prev_helper_reference_present=true
post_xref_same_number_decoy_present=true
stale_direct_prev_owner_metadata_excluded=true
stale_direct_prev_owner_attachment_excluded=true
stale_direct_prev_owner_text_excluded=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat classic xref-table indirect `/Prev` helper repair, compressed object-stream `/Prev` helper repair, indirect `/W` and `/Index` xref-stream operand repair, same-generation direct `/Prev` damaged-offset repair, stale explicit offset repair, wrong-current-offset row repair, damaged middle `/Prev` fallback, latest sparse Info inheritance, latest `/Info null`, hybrid free-entry behavior, object-stream generation repair, stream-filter operand owner boundaries, runtime preflight work, or OCR/model execution.

The bounded new behavior is specifically xref-stream `/Prev` direct indirect helper owner selection when a post-xref same-number direct object would otherwise shadow the current-base helper.

## Dependency Closure

No new support component is needed. This reuses native PHP direct-object scanning, Flate stream decoding, safe xref operand helper resolution, xref table/stream chain walking, current update row repair, XMP/Info/catalog metadata extraction, EmbeddedFiles name-tree extraction, and WordPress smoke rendering. Full upstream parity remains gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed here.
