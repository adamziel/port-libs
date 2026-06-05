# markerPDF xref-stream compressed Prev current-base

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260605T011937Z`
Base: `f36d95ec882305f570396b1764728cb2756da1b8`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF text, metadata, and attachments through parser-backed PDF loading before OCR/model fallback. In this no-GPU PHP lane, the equivalent native boundary is accurate xref-chain walking, object-stream expansion, catalog/Info metadata selection, and EmbeddedFiles name-tree selection without Python, OCR, models, or external PDF tools.

PDF xref streams may store trailer dictionary operands as indirect objects. The text parser already had focused coverage for `/Prev` values whose numeric helper object is compressed inside an object stream. The remaining gap was the metadata and EmbeddedFiles walkers: they could repair current same-generation xref-stream rows only when `/Prev` resolved through direct operands or direct helper objects.

## Behavior

`PdfMetadataExtractor` and `PdfEmbeddedFileExtractor` now resolve a safe numeric `/Prev` helper when that helper is a generation-zero member of a compressed `/ObjStm` that appears before the current xref stream. The resolved previous-xref offset is used only while decoding the current xref stream and repairing current rows, so stale previous-section objects do not win over the current incremental update.

The focused fixture builds:

- a previous classic xref table with stale catalog, page text, XMP, Info, name-tree, Filespec, and EmbeddedFile objects;
- current same-generation replacement objects after the previous EOF;
- a compressed object stream `90 0 obj` containing helper object `30`, whose body is the previous xref offset;
- a current xref stream `40 0 obj` with `/Prev 30 0 R`, damaged explicit offsets for the current rows, and a type-2 row for helper object `30`.

After the patch, WordPress import selects current page text, current XMP, current Info, current catalog language, and the current XML attachment while excluding stale previous-section metadata, attachments, and text.

## Evidence

Focused gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
11 PASS cases
1 test files, 182 assertions, 0 failures
```

Adjacent xref/metadata/attachment family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamIndirectPrevObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamIndirectPrevOffsetRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamCompressedOperandOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamIndirectIndexWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterChainCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
Focused test run: 9 selected test files (root lock skipped)
9 test files, 1558 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-compressed-prev-currentbase.php
current_compressed_prev_text_selected=true
current_compressed_prev_xmp_selected=true
current_compressed_prev_info_selected=true
current_catalog_language_selected=true
current_attachment_selected=true
current_attachment_payload_selected=true
uses_compressed_object_stream_prev_helper=true
stale_prev_metadata_excluded=true
stale_prev_attachment_excluded=true
stale_prev_text_excluded=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat the text-parser-only compressed `/Prev` object-stream slice, direct-object classic indirect `/Prev` metadata/attachment repair, xref-stream `/W` and `/Index` indirect operand repair, same-generation direct `/Prev` damaged-offset repair, stale explicit offset repair, wrong-current-offset row repair, hybrid free-entry behavior, object-stream generation repair, stream-filter operand owner boundaries, runtime preflight work, or OCR/model execution.

The bounded new behavior is specifically metadata and EmbeddedFiles xref-stream current-row repair when `/Prev` resolves from a compressed object-stream numeric helper.

## Dependency Closure

No new support component is needed. This reuses native PHP direct-object scanning, Flate stream decoding, object-stream member table parsing, safe xref operand helper resolution, xref table/stream chain walking, current update row repair, XMP/Info/catalog metadata extraction, EmbeddedFiles name-tree extraction, and WordPress smoke rendering. Full upstream parity remains gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed here.
