# Portfolio Collection Field Value Review

Micro-slice: `portfolio-collection-field-value-review-currentbase-20260602T114048Z`

Base accepted HEAD: `dd7c0dd1c605f36e3ddc2f37784f7912f6eee524`

## Source Truth

- Upstream markerPDF delegates live PDF parsing/rendering to Python dependencies such as pdftext and pypdfium, while this lane implements bounded native PHP review metadata before WordPress import.
- PDF Portfolio collection dictionaries are review metadata, not page contents. The PDF Collection schema defines field subtypes for text, date, number, file name, description, creation date, modification date, and size. Collection item values may be direct values or `/CollectionSubitem` dictionaries where `/P` is a display prefix and `/D` is the actual value.
- This slice reuses the existing native catalog `/Collection`, FileSpec `/CI`, embedded-file `/Params`, and fallback text-exclusion boundaries.

## Implementation

- `PdfEmbeddedFileExtractor` now emits `portfolio_field_values` for attachments when a catalog `/Collection /Schema` is present.
- File-related collection fields derive from the attachment review row:
  - `/Subtype /F` from the Unicode filename/FileSpec filename.
  - `/Subtype /Desc` from FileSpec `/Desc`.
  - `/Subtype /CreationDate`, `/ModDate`, and `/Size` from embedded-file `/Params`.
- Custom schema fields derive from FileSpec `/CI`, preserving direct values and prefixed `/CollectionSubitem` display values.
- The previous `portfolio_item` shape remains intact for compatibility.
- Dictionary value lookup in this extractor now walks parsed dictionary entries so a value like `/Subtype /N` is not mistaken for the `/N` field-label key.

## Evidence

Red-first focused gate before implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL reviews schema typed portfolio collection field values from Filespec and CI entries
Expected: 'source-unicode.xml'
Actual: NULL
1 test files, 221 assertions, 1 failures
```

Focused gate after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 255 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-portfolio-field-values-review.php
```

The smoke emits `attachment_count=1`, `field_sources` for `file_spec`, `embedded_file_params`, `collection_item`, and `collection_subitem`, `field_value_types` for text/date/number, `priority_display_value=Priority 2`, `excluded_attachment_payload_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and metadata checks:

```text
php -l lanes/markerpdf/src/PdfEmbeddedFileExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfEmbeddedFileExtractor.php

php -l lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-portfolio-field-values-review.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-portfolio-field-values-review.php

jq empty lanes/markerpdf/lane-status.json lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json
PASS

git diff --check -- lanes/markerpdf
PASS
```

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted PDF Portfolio `/Collection` extraction, `/CI` item extraction, `/PieceInfo`, associated-file checksum review, embedded-file payload exclusion, or RichMedia embedded attachment action review. The new behavior is schema-typed field-value review for existing portfolio attachment rows.

## Dependency Closure

No new support component is needed. This reuses native PDF object parsing, dictionary/value parsing, embedded-file Params review, FileSpec metadata, collection schema parsing, and fallback payload exclusion. Full upstream runner parity remains dependency-gated by the Python/model stack (`pdftext`, pypdfium2, Surya, tabled, Texify, Torch, Streamlit/FastAPI, and model downloads).
