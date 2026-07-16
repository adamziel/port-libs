# markerPDF XMP Metadata Null Extra-Operand Boundary

Micro-slice: `markerpdf-xmp-metadata-boundary-current-base-20260606T131635Z`

## Source Truth

Upstream markerPDF keeps PDF metadata separate from visible page text before later OCR/layout/model stages. In the native no-GPU PHP lane, catalog `/Metadata` is a document-wide XMP trust boundary: a clean PDF `null` object is equivalent to absent metadata, but top-level operands after that `null` make the catalog entry malformed and ambiguous.

## Behavior

`PdfMetadataExtractor::catalogMetadataStreamBoundaryReview()` now checks for malformed trailing top-level operands before treating `/Metadata null` as absent.

This means `/Metadata null` still uses trailer `/Info` fallback with no catalog review row, while `/Metadata null 5 0 R 7 0 R` fails closed as `rejected_malformed_metadata_operand`. The trailing reference object numbers are summarized for review, but trailing XMP/action payload text is not promoted to document metadata or visible WordPress paragraphs.

## Red-First Evidence

Baseline before adding this test:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpMetadataBoundaryCurrentBaseTest.php`

Result: `1 test files / 94 assertions / 0 failures`

After adding the focused test before the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpMetadataBoundaryCurrentBaseTest.php`

Result: `1 test files / 95 assertions / 1 failures`

Failure: `rejects catalog Metadata null followed by extra operands before XMP promotion` expected source `['info', 'catalog']`, but the current base returned `['info']` because the review path returned early on `null`.

## Verification

Focused metadata-boundary test:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataXmpMetadataBoundaryCurrentBaseTest.php`

Result: `1 test files / 111 assertions / 0 failures`

Adjacent metadata/XMP family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfMetadataXmp*CurrentBaseTest.php`

Result: `43 test files / 2777 assertions / 0 failures`

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xmp-metadata-null-extra-operand-boundary-currentbase.php`

Result: passed. The smoke emits `metadata_null_with_trailing_operands_rejected=true`, `review_status=rejected_malformed_metadata_operand`, `metadata_operand_count=3`, `trailing_reference_object_numbers=[5,7]`, `xmp_not_promoted=true`, `payload_included=false`, `accepted_as_document_xmp=false`, `visible_text_is_page_content_only=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

PHP lint:

- `php -l lanes/markerpdf/src/PdfMetadataExtractor.php`
- `php -l lanes/markerpdf/tests/PdfMetadataXmpMetadataBoundaryCurrentBaseTest.php`
- `php -l lanes/markerpdf/examples/wordpress-pdf-xmp-metadata-null-extra-operand-boundary-currentbase.php`

Result: no syntax errors detected.

Required whitespace check:

`git diff --check -- lanes/markerpdf`

Result: passed.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `2555 -> 2556` from one new focused TestRunner PASS case.
- Focused assertions in `PdfMetadataXmpMetadataBoundaryCurrentBaseTest.php`: `94 -> 111`.
- `wordpressScenarios`: `2169 -> 2170` from the new WordPress null-extra-operand boundary smoke.

## Non-Overlap

This does not repeat accepted clean `/Metadata null` handling, duplicate catalog `/Metadata` keys, non-null extra operand rejection, direct catalog metadata dictionaries, unresolved metadata references, unreadable XMP stream filters, non-stream metadata objects, non-metadata XML streams, XMP packet begin/end boundaries, complete-packet fallback, instruction/comment/CDATA/entity boundaries, namespace/root selection, typed-node and RDF value parsing, encrypted metadata source priority, output-intent/PieceInfo/name-tree metadata review, xref repair, image/filter metadata, annotations, forms, OCR, or model execution.

The bounded behavior is only that a PDF null-object catalog `/Metadata` entry followed by extra top-level operands is not silently treated as cleanly absent.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF dictionary parser, top-level operand scanner, XMP metadata trust boundary, trailer `/Info` fallback, text extractor, metadata review summary path, and WordPress smoke pattern. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, external OCR/rendering helpers, Streamlit/FastAPI workers, and exact upstream model benchmark parity remain intentionally outside the current no-GPU markerPDF scope.
