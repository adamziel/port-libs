# markerpdf-xmp-metadata-boundary-current-base-20260606T071912Z

## Scope

- Lane: `markerpdf`
- Base accepted HEAD: `e5e7af20fff34a2939cfb21b04f9bc546415b4cf`
- Behavior cluster: native no-GPU XMP Dublin Core review metadata for `dc:format`, `dc:language`, and `dc:rights`.

## Source Truth

- Upstream markerPDF keeps PDF text extraction and document metadata as separate conversion surfaces. Native PHP document XMP must therefore preserve useful metadata for WordPress import review without leaking XMP packet text into visible paragraphs.
- PDF catalog `/Metadata` XMP packets use Dublin Core fields for document properties. This slice maps `dc:format`, `dc:language`, and `dc:rights` from the current accepted XMP packet while excluding non-document RDF resources and trailing packet decoys.

## Patch

- `PdfMetadataExtractor` now records accepted XMP `dc:format`, `dc:language`, and `dc:rights` fields.
- The merged document metadata exposes `format`, `language`, `languages`, `rights`, and `xmp_dublin_core` when no catalog `/Lang` overrides language.
- Rejected non-metadata XML streams now summarize those fields by field names and language count while keeping values redacted.
- Added a WordPress smoke proving the review fields are preserved, private/trailing XMP decoys are excluded, visible text stays clean, and no Python/model/external PDF tools execute.

## Evidence

Red-first focused run before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpDublinCoreReviewBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PHP Warning:  Undefined array key "format" in lanes/markerpdf/tests/PdfMetadataXmpDublinCoreReviewBoundaryCurrentBaseTest.php on line 103
FAIL extracts XMP Dublin Core format language and rights review metadata
Expected: 'application/pdf'
Actual: NULL
FAIL summarizes rejected XMP Dublin Core document properties without exposing values
Expected field_names included format, rights, languages, and dublin_core.
Actual field_names only reported title, description, creator_tool, producer, dates, authors, and keywords.
1 test files, 19 assertions, 2 failures
```

Focused run after the patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpDublinCoreReviewBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS extracts XMP Dublin Core format language and rights review metadata
PASS summarizes rejected XMP Dublin Core document properties without exposing values
1 test files, 69 assertions, 0 failures
```

Adjacent XMP metadata family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfMetadataXmp*CurrentBaseTest.php
Focused test run: 40 selected test files (root lock skipped)
40 test files, 2625 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xmp-dublin-core-review-boundary-currentbase.php
```

Passed and emitted `format_preserved=true`, `language_preserved=true`, `languages_preserved=true`, `rights_preserved=true`, `review_only=true`, `payload_included=false`, `packet_boundary_applied=true`, `private_resource_decoy_excluded=true`, `trailing_packet_decoy_excluded=true`, `visible_text_excludes_xmp=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax and format checks:

```text
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfMetadataExtractor.php

php -l lanes/markerpdf/tests/PdfMetadataXmpDublinCoreReviewBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfMetadataXmpDublinCoreReviewBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-xmp-dublin-core-review-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-xmp-dublin-core-review-boundary-currentbase.php

php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
lane-status json ok

git diff --check -- lanes/markerpdf
passed
```

## Non-Overlap

- Does not repeat accepted XMP packet padding, begin/end boundaries, complete-packet fallback, namespace filtering, compact RDF attributes, language alternatives, inherited `xml:lang`, simple text `dc:subject` splitting, qualified/nested values, RDF membership/resource references, media-management IDs, PDF/A schema rows, encrypted metadata source priority, catalog `/Lang` review, metadata stream admission/rejection, xref repair, images, CMaps, forms, annotations, OCR, or model execution.
- The new behavior is specifically current-packet XMP Dublin Core document property review for `dc:format`, `dc:language`, and `dc:rights`.

## Dependency Closure

- No new support component is needed. This reuses the native PDF object parser, stream decoder, XMP packet boundary scanner, DOM-based XMP extraction, and WordPress smoke path.
- Live OCR, Surya/Texify/Torch, pypdfium/PDFium, PIL, Streamlit/FastAPI model workers, and external PDF tools remain intentionally out of scope for the markerPDF no-GPU lane.

## Root Harness

- Not run - isolated micro-slice.
