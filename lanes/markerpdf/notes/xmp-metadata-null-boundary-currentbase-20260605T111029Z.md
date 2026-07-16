# XMP Metadata Null Boundary Current Base

Micro-slice: `markerpdf-xmp-metadata-boundary-current-base-20260605T111029Z`

## Source Truth

Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps PDF metadata separate from visible page text through the PDF document-loading boundary before later OCR/layout/model stages.

At the native PDF parser boundary, a catalog `/Metadata` entry whose value is the PDF `null` object is equivalent to an absent metadata stream. WordPress import should therefore use trailer `/Info` fallback metadata and must not emit a malformed catalog metadata-stream review row.

## Behavior

`PdfMetadataExtractor::catalogMetadataStreamBoundaryReview()` now treats top-level `null` catalog `/Metadata` values as absent before document XMP promotion.

This preserves the existing fail-closed behavior for direct dictionaries, unresolved indirect references, unreadable streams, non-`/Type /Metadata /Subtype /XML` streams, unsafe XMP entities, malformed stream-object tails, generation-exact references, encrypted metadata source policy, and XMP packet/root boundaries.

## Red-First Evidence

Before the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpMetadataBoundaryCurrentBaseTest.php`

Result: `1 test files / 36 assertions / 1 failure`

Failure: the new null-object assertion expected source `['info']`, but the current base returned `['info','catalog']` because `/Metadata null` was classified as `rejected_non_indirect_metadata_reference`.

## Verification

Focused XMP metadata boundary:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpMetadataBoundaryCurrentBaseTest.php`

Result: `1 test files / 42 assertions / 0 failures`

Adjacent metadata/XMP family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfMetadataXmp*CurrentBaseTest.php`

Result: `22 test files / 1772 assertions / 0 failures`

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xmp-metadata-null-boundary-currentbase.php`

Result: passed. The smoke emits `metadata_null_treated_as_absent=true`, `catalog_metadata_review_absent=true`, `xmp_not_promoted=true`, `visible_text_is_page_content_only=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

PHP lint:

- `php -l lanes/markerpdf/src/PdfMetadataExtractor.php`
- `php -l lanes/markerpdf/tests/PdfMetadataXmpMetadataBoundaryCurrentBaseTest.php`
- `php -l lanes/markerpdf/examples/wordpress-pdf-xmp-metadata-null-boundary-currentbase.php`

Result: no syntax errors.

Required whitespace check:

`git diff --check -- lanes/markerpdf`

Result: passed.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1760 -> 1761` from one new focused TestRunner PASS case.
- `wordpressScenarios`: `1604 -> 1605` from the new WordPress null-boundary smoke.

## Non-Overlap

This does not repeat accepted direct/unresolved/unreadable catalog `/Metadata` reference boundaries, non-metadata XML stream rejection, XMP packet padding, complete-packet fallback, unpaired-begin handling, instruction filtering, DTD/entity rejection, CDATA/comment root selection, namespace wrapper filtering, typed-node parsing, language alternatives, qualified/nested values, FileSpec XMP generation exactness, encrypted metadata source priority, OutputIntent/PieceInfo/name-tree metadata review, xref repair, page resources, image/filter metadata, annotations, forms, OCR, or model execution.

The bounded behavior is only the PDF null-object boundary for catalog `/Metadata`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP PDF dictionary parsing, PDF whitespace/comment trimming, metadata merge behavior, trailer `/Info` fallback, text extraction, and the WordPress smoke path. GPU/model/OCR/PDFium/Python execution remains intentionally out of scope under the current no-GPU markerPDF directive.
