# markerPDF xref-stream indirect W/Index current-base

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260605T004718Z`
Base: `0fe8739ce5356d5a3078fe470f44492bd5ad212c`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` obtains searchable-PDF text and document metadata through parser-backed pdftext/PDFium boundaries before model/OCR fallback. In the native no-GPU PHP lane, xref-stream dictionary operands, `/Prev` chains, catalog metadata, Info dictionaries, page text, and EmbeddedFiles name trees are parser dependency boundaries.

PDF xref-stream dictionaries may store `/W` and `/Index` as indirect objects. Those operands define which current xref rows are authoritative. If they are ignored, a tolerant direct-object scan can select later unreferenced stale objects instead of the latest xref-selected current graph.

## Behavior

`PdfMetadataExtractor` and `PdfEmbeddedFileExtractor` now resolve xref-stream `/W`, `/Index`, and `/Size` operands through their selected object maps when decoding xref streams. This matches the existing text parser behavior and keeps current xref-selected metadata and attachments authoritative across `/Prev` incremental chains.

The focused fixture builds a previous xref table, then a current xref stream with `/W 30 0 R`, `/Index 31 0 R`, and `/Prev` to the previous section. Current catalog, page text, XMP, Info, and EmbeddedFiles rows are selected by the latest xref stream. Stale direct decoy objects with the same object numbers appear after the xref stream but before `startxref`; they must not win by file order.

## Evidence

Focused gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
10 PASS cases
1 test files, 157 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-stream-indirect-operands-currentbase.php
current_xref_operand_text_selected=true
current_xmp_title_selected=true
current_info_title_selected=true
current_catalog_language_selected=true
current_attachment_selected=true
indirect_w_index_helpers_used=true
stale_post_xref_decoy_excluded=true
previous_prev_section_excluded=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat classic indirect `/Prev` repair, compressed-helper `/Prev` text repair, same-generation damaged-offset repair, hybrid `/XRefStm` free-entry precedence, object-stream member-index repair, stream-filter operand owner boundaries, or live OCR/model work.

The bounded behavior is specifically metadata and embedded-file xref-stream `/W` and `/Index` indirect direct-object operands in an incremental `/Prev` chain before stale post-xref direct object decoys.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP direct-object scanner, safe indirect value resolver, Flate stream decoder, xref table/stream `/Prev` chain walker, metadata extractor, EmbeddedFiles name-tree extractor, text parser, and WordPress smoke path. Full upstream parity remains gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers; none were executed here.
