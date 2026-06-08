# XMP PDF Schema Boundary Current Base

Slice: `markerpdf-xmp-metadata-boundary-current-base-20260608T153105Z`

Base accepted HEAD: `866ea52a67a61e534ee4668a27bf164b07d3651b`

## Behavior

Root Catalog `/Metadata` XMP now preserves standard PDF namespace review
scalars from `http://ns.adobe.com/pdf/1.3/` when the packet carries
`pdf:PDFVersion` or `pdf:Trapped`. The new `xmp_pdf` review block is
review-only, redacts raw payload bytes, normalizes the closed-choice
`Trapped` values `True`, `False`, and `Unknown`, and carries adjacent
`pdf:Producer` / `pdf:Keywords` values for import review correlation.

Rejected non-document XML streams now summarize the PDF schema field names and
keyword count without exposing producer, keyword, version, trapped, or payload
text values.

## Non-Overlap

This patch does not repeat CCITT Fax filter boundaries, xref repair, XMP packet
selection, Dublin Core review metadata, PDF/A extension schemas, XMP Media
Management identifiers, resource-reference boundaries, or ordinary
`pdf:Producer` / `pdf:Keywords` promotion. The new review block is emitted only
when PDF schema review scalars such as `PDFVersion` or `Trapped` are present.

## Focused Evidence

Initial focused run after implementation exposed one overly broad assertion
about raw trailer Info redaction:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpPdfSchemaBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL extracts PDF namespace XMP scalars as review metadata before stale Info
PASS summarizes rejected PDF namespace XMP streams without exposing text values

1 test files, 42 assertions, 1 failures
```

The assertion was corrected to preserve the existing raw Info-dictionary review
contract while still proving XMP wins the promoted fields.

Final focused runs:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpPdfSchemaBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS extracts PDF namespace XMP scalars as review metadata before stale Info
PASS summarizes rejected PDF namespace XMP streams without exposing text values

1 test files, 43 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmp*Test.php
Focused test run: 69 selected test files (root lock skipped)
...
69 test files, 3251 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 884 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xmp-pdf-schema-boundary-currentbase.php
```

Result: exits `0`; emits `xmp_preferred_over_info=true`,
`pdf_schema_review_present=true`, `pdf_schema_payload_included=false`,
`pdf_version=1.7`, `trapped_normalized=False`,
`metadata_stream_excluded_from_text=true`,
`executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF parser,
Flate stream decoding, XMP DOM parsing, XMP packet boundary scanner, and
existing metadata redaction/review helpers. No GPU/model execution, OCR,
Python marker workers, external PDF tools, or online services were used.

Root harness status: not run - isolated micro-slice.
