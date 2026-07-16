# markerPDF hybrid classic xref table compressed Prev current-base

Micro-slice: `markerpdf-xref-prev-chain-incremental-update-current-base-20260605T022717Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates searchable PDF text and metadata extraction through `marker/pdf/extract_text.py` into pdftext/PDFium-backed parsing. The native PHP lane owns the parser dependency boundary for xref section selection, object-stream expansion, metadata extraction, and WordPress import review without GPU/model execution.

PDF incremental updates can be hybrid-reference files: a classic xref table trailer can include `/XRefStm` pointing at a companion xref stream. The trailer `/Prev` operand is normally an integer offset, but damaged or producer-specific files can store that integer behind a generation-zero indirect object. This slice covers the bounded case where the helper object is a compressed object-stream member selected by the companion xref stream.

## Behavior

`PdfTextExtractor`, `PdfMetadataExtractor`, and `PdfEmbeddedFileExtractor` now resolve a classic xref table trailer `/Prev` indirect object through the companion xref stream/object-stream context when the helper body is a safe integer-only operand. The guard rejects empty helpers, object syntax, stream syntax, xref/trailer/startxref keywords, and non-integer bodies.

The focused fixture builds:

- a previous classic xref section containing the selected base page, catalog language, Info fallback, and EmbeddedFiles name tree;
- a latest classic xref table whose current catalog and Info rows have damaged explicit offsets;
- a companion `/XRefStm` whose type-2 row selects object `30` from object-stream carrier `90`;
- object `30` as the compressed numeric `/Prev` helper for the previous classic xref offset;
- post-helper decoy page and attachment objects after the companion stream but before the final table.

After the patch, the current xref table repairs current catalog and Info rows while following `/Prev 30 0 R` to the earlier base section, so WordPress import selects the current base page text, current Info/catalog metadata, and current attachment while excluding post-helper decoys.

## Evidence

Red baseline before source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainHybridTableCompressedPrevCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL resolves classic xref table Prev offsets from compressed companion helper object streams (lanes/markerpdf/tests/PdfXrefPrevChainHybridTableCompressedPrevCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'Current hybrid table compressed Prev page',
  1 => 'Compressed table Prev helper selected',
)
Actual: array (
  0 => 'Post-helper hybrid table decoy page',
)

1 test files, 1 assertions, 1 failures
```

Focused green after repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainHybridTableCompressedPrevCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS resolves classic xref table Prev offsets from compressed companion helper object streams

1 test files, 19 assertions, 0 failures
```

Adjacent xref-chain regression:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefPrevChainHybridTableCompressedPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainIncrementalUpdateCurrentBaseTest.php
Focused test run: 2 selected test files (root lock skipped)
2 test files, 219 assertions, 0 failures
```

Compressed xref-stream helper regression:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserXrefStreamIndirectPrevObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevChainHybridTableCompressedPrevCurrentBaseTest.php
Focused test run: 2 selected test files (root lock skipped)
2 test files, 53 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-chain-hybrid-table-compressed-prev-currentbase.php
uses_current_base_page=true
resolves_compressed_prev_helper=true
keeps_current_info_metadata=true
keeps_current_catalog_language=true
imports_current_attachment=true
current_attachment_payload_matches=true
excludes_previous_info_title=true
excludes_post_helper_decoy_page=true
excludes_post_helper_decoy_attachment=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted xref-stream compressed `/Prev` helper behavior, indirect classic-table `/Prev` direct helper behavior, xref-stream `/W` and `/Index` compressed helpers, damaged middle `/Prev` fallback, same-generation xref-stream damaged-offset repair, classic table damaged-offset repair, object-stream header repair, or stream-filter boundary work.

The bounded new behavior is specifically a hybrid-reference classic xref table trailer whose `/Prev` value is an indirect numeric helper stored as a compressed object-stream member selected by the companion `/XRefStm`.

## Dependency Closure

No new support component is needed. This slice reuses the native direct-object scanner, classic xref table parser, xref-stream parser, object-stream Flate decoder, current-update xref row repair, metadata extractor, embedded-file extractor, and WordPress smoke renderer. Full upstream model parity, OCR, Surya/Torch, Texify, Streamlit/FastAPI workers, and external rendering/OCR helpers remain intentionally out of scope under the no-GPU markerPDF directive.
