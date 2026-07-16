# markerPDF annotations links FileSpec duplicate-key boundary current-base

Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260608T035525Z`

Session: `port-dev-markerpdf-annotations-links-20260608T035525Z`

Accepted base: `e0a13ef9a780753d5899fbbc435cefb0324e5b29`

## Source Truth

- Upstream markerPDF promotes link metadata after searchable-PDF parsing and downstream pdftext/PDFium boundaries. This native PHP slice keeps annotation/link action review local and no-GPU: no OCR, Surya, Texify, Torch, PDFium rendering, Python model workers, PDF action execution, or external PDF tools.
- PDF remote GoToR actions use FileSpec dictionaries for target files. Duplicate dictionary keys are malformed and cannot safely donate a WordPress remote link target, especially when duplicate `/UF` or `/F` entries disagree.

## Behavior

- `PdfActionReviewExtractor::fileSpecValue()` now rejects resolved FileSpec dictionaries that duplicate any file-name selector key: `/UF`, `/F`, `/DOS`, `/Unix`, or `/Mac`.
- Clean remote GoToR FileSpec dictionaries still produce review-only `remote-document-review` link rows.
- Malformed duplicate FileSpec targets become unsupported review actions and are not promoted into supplied WordPress spans.
- Duplicate FileSpec file names, annotation contents, and action operands remain out of visible PDF text and generated WordPress Markdown.

## Evidence

Red-first focused run after adding the test and before source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationFileSpecDuplicateKeyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects duplicate Filespec file-name keys before remote Link promotion
Expected: ["remote-document-review","unsupported-action-review","unsupported-action-review","review-uri"]
Actual: ["remote-document-review","remote-document-review","remote-document-review","review-uri"]
1 test files, 3 assertions, 1 failures
```

Focused run after source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationFileSpecDuplicateKeyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects duplicate Filespec file-name keys before remote Link promotion
1 test files, 37 assertions, 0 failures
```

Adjacent action/link family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationRemoteGoToRBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationRemoteGoToRViewBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationActionOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationDuplicateActionKeyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationDuplicateActionSubtypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationPrimaryActionBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationPrimaryActionArrayBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationPrimaryActionScalarBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php
9 test files, 562 assertions, 0 failures
```

Full link annotation family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfLinkAnnotation.*Test\.php$' | sort)
44 test files, 1542 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-link-filespec-duplicate-key-boundary-currentbase.php
```

Emits `annotation_objects=[7,8,9,10]`, `annotation_action_safety=["remote-document-review","unsupported-action-review","unsupported-action-review","review-uri"]`, `promoted_link_objects=[7,10]`, `promoted_remote_files=["remote-current.pdf"]`, `duplicate_filespec_targets_promoted=false`, `safe_uri_promoted=true`, `visible_text_imported=true`, `executes_pdf_actions=false`, `executes_python_or_models=false`, `executes_ocr=false`, and `executes_external_pdf_tools=false`.

PHP lint:

```text
php -l lanes/markerpdf/src/PdfActionReviewExtractor.php
php -l lanes/markerpdf/tests/PdfLinkAnnotationFileSpecDuplicateKeyBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-link-filespec-duplicate-key-boundary-currentbase.php
```

All changed PHP files reported no syntax errors.

```text
git diff --check -- lanes/markerpdf
```

Passed.

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused markerPDF PHP behavior tests move `2926 -> 2927 pass / 0 fail`.
- WordPress scenarios move `2437 -> 2438`.
- Adds one annotation/link action boundary row for malformed remote FileSpec duplicate file-name keys.

## Non-Overlap

This does not repeat accepted Link URI base resolution, previous-URI `/PA` review, exact object generation resolution, page `/P` ownership, page-tree `/Kids` token boundaries, hidden/no-view flags, escaped annotation keys, indirect `/Subtype`, indirect action subtype `/S`, primary action array/scalar rejection, duplicate Link action `/S`, duplicate annotation `/A` or `/Dest` keys, remote GoToR view-array validation, or clean FileSpec dictionary resolution. This slice owns only duplicate file-name keys inside resolved FileSpec dictionaries used by annotation actions.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP PDF object parser, generation-aware resolver, action-review parser, duplicate-key metadata, annotation/link extraction, supplied marker/pdftext span model, Markdown merge path, and WordPress smoke harness. GPU/model/OCR parity remains intentionally out of scope under the current markerPDF no-GPU directive.
