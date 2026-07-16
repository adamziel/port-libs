# markerpdf annotations links Kids token boundary current base

Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260605T151954Z`

Accepted base: `73c0bbac79227ee2db977ad15039a8acb1dad8b8`

## Source truth

- Upstream `sddai/markerPDF` remains pinned in the manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- The upstream no-GPU import boundary for searchable PDFs is page-local: page-tree `/Kids` supplies child page nodes, and page `/Annots` entries are reviewed or promoted only for actual page leaves.
- PDF object references inside literal strings, nested dictionaries, or nested arrays inside `/Kids` are not child page references. This slice maps that token boundary before WordPress link promotion.

## Implementation

- `PdfAnnotationExtractor`, `PdfLinkAnnotationExtractor`, and `PdfMarkupAnnotationExtractor` now collect page-tree child references by walking top-level PDF array tokens instead of regex-matching every `n n R` substring in the `/Kids` array body.
- `PdfAnnotationLinkKidsTokenBoundaryCurrentBaseTest.php` covers a two-page tree whose `/Kids` array includes a literal-string reference, nested-dictionary reference, and nested-array reference to decoy page objects with link annotations.
- `wordpress-pdf-annotation-link-kids-token-boundary-currentbase.php` renders only the two real WordPress links and proves all decoy link URIs stay out of annotation/link review rows and Markdown output.

## Red first

Before implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationLinkKidsTokenBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps page-tree Kids references token bounded before promoting Link annotations
Only direct top-level Kids references are page leaves.
Expected: 2
Actual: 5
1 test files, 1 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationLinkKidsTokenBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps page-tree Kids references token bounded before promoting Link annotations
1 test files, 20 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationLinkPageReferenceBoundaryCurrentBaseTest.php
1 test files, 38 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationLinkPageGenerationBoundaryCurrentBaseTest.php
1 test files, 23 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationOverlapSpecificityBoundaryCurrentBaseTest.php
1 test files, 27 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php
1 test files, 125 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php
1 test files, 263 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfMarkupAnnotationExtractorTest.php
1 test files, 104 assertions, 0 failures
```

```text
php -l lanes/markerpdf/src/PdfAnnotationExtractor.php
php -l lanes/markerpdf/src/PdfLinkAnnotationExtractor.php
php -l lanes/markerpdf/src/PdfMarkupAnnotationExtractor.php
php -l lanes/markerpdf/tests/PdfAnnotationLinkKidsTokenBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-annotation-link-kids-token-boundary-currentbase.php
No syntax errors detected in all changed PHP files.
```

```text
php lanes/markerpdf/examples/wordpress-pdf-annotation-link-kids-token-boundary-currentbase.php
```

The smoke emits `annotation_page_objects=[3,4]`, `promoted_link_page_objects=[3,4]`, `literal_string_kids_decoy_excluded=true`, `nested_dictionary_kids_decoy_excluded=true`, `nested_array_kids_decoy_excluded=true`, and all Python/model/external-tool/PDF-action execution flags false.

```text
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
lane-status json ok

git diff --check -- lanes/markerpdf
passed with no output
```

Root harness: not run - isolated micro-slice.

## Status delta

- Focused PHP behavior cases: `2028 -> 2029`.
- WordPress scenarios: `1755 -> 1756`.
- Added 1 focused test file with 20 assertions and 1 WordPress smoke for page-tree `/Kids` token boundaries before annotation/link promotion.

## Non-overlap

This does not repeat accepted top-level page `/Annots` ownership, annotation `/P` page-reference ownership, annotation/page exact generation ownership, escaped annotation names, widget field action inheritance, URI control-byte filtering, primary action gating, link overlap specificity, rotated/UserUnit/CropBox geometry, malformed QuadPoints, or CMap/font/xref/image/filter work.

The bounded behavior is only page-tree `/Kids` token parsing for annotation/link page discovery when decoy page references appear inside non-child PDF values.

## Dependency closure

No new support component is needed. This reuses the native PDF dictionary/array token readers, page-tree traversal, annotation extractors, link-span application, supplied pdftext page model, Markdown merge path, and WordPress smoke path. Live OCR, Surya/Torch/Texify models, pypdfium/PDFium rendering, PDF action execution, and external PDF tools remain intentionally out of scope for the no-GPU markerPDF lane.

## Next task

Continue with non-overlapping native searchable-PDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
