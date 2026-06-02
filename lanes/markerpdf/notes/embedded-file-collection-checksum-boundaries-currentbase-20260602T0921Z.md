# Embedded File Collection Checksum Boundaries

Session: `port-dev-markerpdf-attach24-20260602T0921Z`
Micro-slice: `embedded-file-collection-checksum-boundaries-currentbase-20260602T0921Z`
Base accepted HEAD: `cd72f21c7f68cede38b530d69670e4adafc04710`

## Source-Truth Boundary

Upstream `sddai/markerPDF` at commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates PDF parsing/text extraction to `pdftext` and pypdfium in `marker/pdf/extract_text.py`; native PHP fallback must keep attachment streams as review/import metadata instead of visible page text:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py

The relevant PDF parser/dependency boundary is the FileSpec and Collection surface. pypdf constants expose catalog `/AF`, FileSpec `/F`, `/UF`, `/EF`, `/Desc`, `/AFRelationship`, `/CI`, embedded-file `/Params`, and collection dictionaries as PDF metadata separate from page `/Contents`:

- https://pypdf.readthedocs.io/en/6.8.0/_modules/pypdf/constants.html

## Native Behavior Added

`PdfEmbeddedFileExtractor` now propagates catalog `/Collection` metadata into catalog `/AF` associated embedded-file rows. This matches the existing name-tree Portfolio path and covers PDFs that use catalog-associated Source/Alternative files rather than only `/Names /EmbeddedFiles`.

The same rows still carry per-FileSpec `/CI` metadata and embedded-file `/Params /CheckSum` review fields. The checksum remains advisory: verified attachments report `checksum_matches=true`, stale generated attachments report `checksum_matches=false`, and neither row is dropped.

The WordPress smoke keeps source XML and generated JSON attachment payload bytes out of visible paragraph text while exposing review comments for schema fields, sort keys, collection item labels, and checksum match states.

## Evidence

Red-first:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
FAIL carries portfolio collection metadata and checksum boundaries on catalog associated files
Expected: 'catalog_collection'
Actual: NULL
1 test files, 145 assertions, 1 failures
```

Focused passing gates:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
1 test files, 168 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php
3 test files, 702 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests
61 test files, 2929 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-collection-associated-checksum-import.php
```

The smoke emits `attachment_count=2`, `portfolio_view=T`, `schema_fields=["Subject","Checksum"]`, `sort_keys=["Subject","Checksum"]`, `portfolio_item_subjects=["WordPress Export","Preview JSON"]`, `checksum_matches=[true,false]`, `excluded_source_payload_text=true`, and `excluded_preview_payload_text=true`, with `executes_python_or_models=false` and `executes_external_pdf_tools=false`.

Syntax and structural checks:

```text
php -l lanes/markerpdf/src/PdfEmbeddedFileExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfEmbeddedFileExtractor.php

php -l lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-collection-associated-checksum-import.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-collection-associated-checksum-import.php

jq empty lanes/markerpdf/lane-status.json lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json
passed with no output

git diff --check -- lanes/markerpdf
passed with no output
```

## Non-Overlap

This does not repeat accepted catalog `/Names /EmbeddedFiles` extraction, catalog `/AF` basic association rows, `/Collection` metadata for name-tree attachments, FileSpec `/CI`, FileSpec `/PieceInfo`, embedded-file `/Params /CheckSum`, page `/AF`, or fallback FileSpec payload exclusion. The new boundary is catalog `/Collection` metadata on catalog-associated `/AF` embedded files, including verified and stale checksum review states.

## Dependency Closure

No new support component is needed. This reuses native PDF object scanning, dictionary/value parsing, FileSpec `/EF` stream decoding, existing collection and collection-item metadata extraction, embedded-file checksum normalization, and fallback-text attachment exclusion. Full upstream Python/model/benchmark parity remains dependency-gated by `pdftext`, pypdfium2, Surya, tabled, Texify, Torch, Streamlit/FastAPI runtime paths, and model downloads.
