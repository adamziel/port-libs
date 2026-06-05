# Named destination metadata byte limits boundary

Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260605T112135Z`

Base accepted HEAD: `e4da9ea12fd685abfa3a5046c9f4283f3dcf1004`

## Source truth

Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates searchable PDF navigation/text extraction to native PDF parser dependencies before OCR/model stages. At this no-GPU PHP boundary, catalog `/Names /Dests` name trees are navigation/review metadata only. PDF name-tree `/Limits` compare source PDF string bytes, not decoded Unicode labels, before named destinations are promoted into WordPress review metadata.

## Behavior

`PdfNamedDestinationExtractor` already compared name-tree keys by PDF source bytes, but `PdfMetadataExtractor` still used decoded labels in the `document_destinations`, generic catalog name-tree, and EmbeddedFiles name-tree helper. That let a decoded PDFDocEncoding key such as byte `0x80` (`U+2022`) or an indirect UTF-16BE key pass ASCII limits such as `[<18> <41>]` or `[(A) (M)]`, leaking stale destination rows into WordPress document metadata.

This patch makes `PdfMetadataExtractor` carry both decoded text and original PDF string bytes through the shared name-tree limit helper. Public metadata row labels stay decoded text, while `/Limits` filtering uses source bytes.

## Evidence

Red probe after adding the focused assertion and before the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationByteStringLimitsCurrentBaseTest.php`

Result: `1 test files, 12 assertions, 1 failures`; expected document destination names `['\\u{02d8}', 'A', 'LegacyOk']`, actual included the out-of-byte-range bullet row.

Focused run after the patch:

`php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationByteStringLimitsCurrentBaseTest.php`

Result: `1 test files, 19 assertions, 0 failures`.

Adjacent name-tree run:

`php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/(PdfNamedDestination|PdfMetadataExtractor|PdfMetadata.*NameTree|PdfMetadataTrailerInfoNameTree|PdfMetadataPdfaAssociatedNameTree|PdfMetadataNameTree|PdfAttachmentIndirectNameKey|PdfAttachmentRelatedFileNamePair).*Test\\.php$' | sort)`

Result: `27 test files, 1607 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-named-destination-byte-limits-currentbase.php`

Result: emits `destination_count=3`, `document_destination_count=3`, `out_of_byte_range_decoded_key_rejected=true`, `document_metadata_out_of_byte_range_key_rejected=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat accepted named-destination extraction byte limits, name-key rejection, action-dictionary filtering, generation repair, object-stream/xref-stream recovery, page-only normalization, view-mode validation, or trailer-root selection. The new boundary is specifically `PdfMetadataExtractor` review metadata applying PDF source-byte `/Limits` before `document_destinations` or related catalog name-tree review rows are built.

## Dependency closure

No new support component is needed. This reuses the native PHP PDF object scanner, raw PDF string byte decoders, catalog name-tree walker, document metadata extractor, and WordPress smoke path. Live OCR, Surya/Texify/Torch/model execution, PDFium raster rendering, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF directive.
