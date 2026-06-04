# markerPDF Embedded Files Catalog-AF Attachment Boundary Current Base

Micro-slice: `markerpdf-embedded-files-attachments-boundary-current-base-20260604T174658Z`

Base accepted HEAD: `f66c7b0cfe2ab719dee77896627fc0093f6cea75`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` pulls visible searchable-PDF text through `pdftext.dictionary_output()` and pypdfium page text APIs in `marker/pdf/extract_text.py`; embedded attachment payload bytes are not part of that visible text path: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDF associated files are FileSpec dictionaries connected through `/AF`; the PDF Association notes that Associated Files can relate embedded content to PDF objects and recommends EmbeddedFiles name-tree discoverability for compatibility: https://pdfa.org/files-inside-pdf/

The native PHP boundary remains no-GPU/no-model: attachment preflight must summarize FileSpec rows for WordPress review without executing Python, models, external PDF tools, action targets, or attachment payload text promotion.

## Implementation

`PdfAttachmentExtractor` now imports catalog-level `/AF` associated FileSpec rows into the lightweight WordPress attachment preflight. Catalog associated rows carry:

- `source=catalog-associated-file`
- `associated_file=true`
- catalog object id and `/AF` array index
- existing FileSpec filename source, `/EF` key, `/AFRelationship`, content type, size, filter, checksum, and timestamp review metadata

When the same FileSpec and embedded stream are also listed through `/Names /EmbeddedFiles`, the extractor keeps a single attachment row, preserves the `embedded-files-name-tree` source, and marks it with `associated_file_source=catalog_af` plus catalog `/AF` context. This avoids double-counting the common PDF/A/PDF 2.0 mirror shape while still surfacing the association.

The WordPress smoke now includes three review attachments: an EmbeddedFiles name-tree CSV, a catalog-associated source XML attachment, and a page FileAttachment annotation. The smoke confirms `catalog_associated_file_preflight=true` and keeps payload bytes out of the summary.

## Red/Green Evidence

Red baseline after adding the focused case:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php
```

Result: `1 test files, 76 assertions, 1 failures`

Failing case: `summarizes catalog associated FileSpec attachments and dedupes EmbeddedFiles mirrors` expected `attachment_count=1`, actual `0`.

After patch:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php
```

Result: `1 test files, 112 assertions, 0 failures`

Focused family:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
```

Result: `2 test files, 464 assertions, 0 failures`

Syntax:

```bash
php -l lanes/markerpdf/src/PdfAttachmentExtractor.php
php -l lanes/markerpdf/tests/PdfAttachmentExtractorTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-attachments-preflight.php
```

Result: no syntax errors detected.

Smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-attachments-preflight.php
```

Result: emitted `attachment_count=3`, `catalog_associated_file_preflight=true`, `pruned_out_of_limits_name_tree_entry=true`, and `executes_python_or_models=false` / `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat the accepted full `PdfEmbeddedFileExtractor` catalog `/AF`, portfolio `/Collection`, FileSpec `/CI`, PieceInfo, XMP/OutputIntent, page-associated-file, rich-media FileSpec, encrypted associated-file metadata, platform FileSpec filename selection, `/AFRelationship` role mapping, Params checksum match-state, or EmbeddedFiles name-tree `/Limits` slices. The new behavior is only catalog `/AF` associated FileSpec ingestion in the lightweight `PdfAttachmentExtractor` WordPress preflight plus EmbeddedFiles mirror dedupe.

## Dependency Closure

No new support component is needed. This reuses the native PHP object/value parser, FileSpec dictionary parsing, stream filter decoding, checksum review, and WordPress smoke pattern. Full upstream parity remains intentionally outside this worker because live OCR, Surya/Texify/Torch model execution, PDFium rendering, table-model inference, and exact model benchmarks require GPU/model/external runtime paths excluded by the current markerPDF scope.
