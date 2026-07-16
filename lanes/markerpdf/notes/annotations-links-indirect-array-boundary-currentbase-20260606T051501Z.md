# markerPDF annotations links indirect array boundary current-base

Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260606T051501Z`

Accepted base: `294423e88f42509c7d2f710b7f99d68c7b92a051`

## Source Truth

- Upstream markerPDF promotes link metadata after searchable-PDF parsing. This native PHP lane keeps the annotation/link boundary local and no-GPU: no OCR, Surya, Texify, Torch, PDFium rendering, Python model workers, PDF action execution, or external PDF tools.
- PDF page dictionaries carry `/Annots` as annotation-array data. PDF objects can be indirect, and producer/repair paths may layer references before the effective array or annotation dictionary is reached. The importer must follow bounded indirect array fragments while preserving page ownership, generation checks, and hidden/no-view suppression.

## Behavior

- `PdfAnnotationExtractor` now resolves bounded indirect `/Annots` reference chains that land on annotation array fragments, then continues annotation review from the final referenced annotation dictionaries.
- `PdfLinkAnnotationExtractor` uses the same bounded flattening before visible Link/Widget link promotion.
- Direct nested arrays inside `/Annots` remain ignored as malformed decoys; PDF strings that look like references remain ignored; hidden Link annotations stay review-only and do not become WordPress hrefs.
- Annotation review payloads, URI operands, literal decoys, and nested-array decoys remain out of visible WordPress paragraph text.

## Evidence

Red-first on accepted base after adding the focused test and before source edits:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationLinkIndirectArrayBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL flattens indirect Annots array fragments before annotation review and WordPress link promotion
Expected: [7, 8, 9, 16, 12]
Actual: [12]
1 test files, 2 assertions, 1 failures
```

After source edits:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotationLinkIndirectArrayBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS flattens indirect Annots array fragments before annotation review and WordPress link promotion
1 test files, 32 assertions, 0 failures
```

Adjacent families:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotation*Test.php
31 test files, 1093 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotation*Test.php lanes/markerpdf/tests/PdfPageAnnotation*Test.php
16 test files, 938 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-link-indirect-annots-array-currentbase.php
```

Emits `annotation_objects=[7,8,9,16,12]`, `promoted_link_objects=[7,16,12]`, `promoted_uris=["https://example.com/fragment-link","https://example.com/chain-link","https://example.com/direct-link"]`, `hidden_fragment_promoted=false`, `literal_decoy_promoted=false`, `nested_direct_array_decoy_promoted=false`, `annotation_payload_text_visible=false`, `executes_pdf_actions=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

PHP lint:

```text
php -l lanes/markerpdf/src/PdfLinkAnnotationExtractor.php
php -l lanes/markerpdf/src/PdfAnnotationExtractor.php
php -l lanes/markerpdf/tests/PdfAnnotationLinkIndirectArrayBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-link-indirect-annots-array-currentbase.php
```

All changed PHP files reported no syntax errors.

```text
git diff --check -- lanes/markerpdf
```

Passed.

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused markerPDF PHP behavior tests move `2406 -> 2407 pass / 0 fail`.
- WordPress scenarios move `2054 -> 2055`.
- Mapped upstream/native PDF boundary behavior gains one annotation/link repair row.

## Non-Overlap

This does not repeat accepted Link URI base resolution, exact object generation resolution, page `/P` ownership, page-tree `/Kids` token boundaries, hidden/no-view flags, escaped annotation keys, indirect `/Subtype`, indirect action subtype `/S`, primary action array/scalar rejection, widget parent inheritance, QuadPoints geometry, target page context, or annotation comment-dictionary parsing. This slice owns only bounded indirect `/Annots` array fragments and chained references before generic annotation review and visible link promotion.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP PDF tokenizer, selected-object resolver, annotation extractors, action review parser, link span promoter, supplied marker/pdftext span model, Markdown merge path, and WordPress smoke harness. GPU/model/OCR parity remains intentionally out of scope under the current markerPDF no-GPU directive.
