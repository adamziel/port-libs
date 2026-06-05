# markerPDF annotations links comment-dictionary boundary current-base

Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260605T043233Z`
Session: `port-dev-markerpdf-annotations-links-20260605T043233Z`
Base accepted HEAD: `5771f733e9e3256de06e48cb643fff27796d43dd`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned in the manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- MarkerPDF promotes links after searchable-PDF text extraction. In the native no-GPU PHP lane, page `/Annots`, Link dictionaries, and action dictionaries are parsed locally before WordPress span promotion.
- PDF comments run from `%` to end-of-line. Dictionary terminators and name/value-looking bytes inside comments must not close the current dictionary, supply `/Subtype`, or replace `/URI`.

## Implementation

- `PdfAnnotationExtractor` now uses its token-aware dictionary entry scanner before regex fallback, and that scanner skips PDF comments when reading top-level keys and values.
- `PdfLinkAnnotationExtractor` now skips comments while reading dictionary values and while tracking dictionary depth.
- `PdfActionReviewExtractor` now skips comments while finding the first object dictionary boundary, so indirect Link action dictionaries with `% >>` decoys do not truncate before the real `/URI`.
- `PdfLinkAnnotationCommentDictionaryBoundaryCurrentBaseTest.php` covers a current Link annotation whose dictionary contains fake comment-only `>>`, `/Subtype`, and URI operands, plus a hidden Link decoy that remains unpromoted.
- `wordpress-pdf-link-comment-dictionary-boundary-currentbase.php` renders only the valid current WordPress link and records the comment-decoy exclusion as review metadata.

## Red First

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationCommentDictionaryBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL skips PDF comments inside Link annotation dictionaries before WordPress span promotion
Expected: array (0 => 'Link', 1 => 'Link')
Actual: array (0 => 'Text', 1 => 'Link')
1 test files, 3 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationCommentDictionaryBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS skips PDF comments inside Link annotation dictionaries before WordPress span promotion
1 test files, 28 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotation*.php lanes/markerpdf/tests/PdfPageAnnots*LinkBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php lanes/markerpdf/tests/PdfPageAnnotsTokenBoundaryCurrentBaseTest.php
Focused test run: 14 selected test files (root lock skipped)
14 test files, 791 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php lanes/markerpdf/tests/PdfAcroFormExtractorTest.php lanes/markerpdf/tests/PdfRichMediaAnnotationExtractorTest.php lanes/markerpdf/tests/PdfJavaScriptActionInspectorTest.php
Focused test run: 4 selected test files (root lock skipped)
4 test files, 1897 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-link-comment-dictionary-boundary-currentbase.php
```

The smoke emits `annotation_objects=[7,8]`, `annotation_subtypes=["Link","Link"]`, `promoted_link_objects=[7]`, `link_uri=https://example.com/commented-link`, `comment_decoy_link_excluded=true`, `comment_decoy_action_excluded=true`, `hidden_comment_decoy_promoted=false`, `visible_text_imported=true`, `annotation_payload_text_visible=false`, and all PDF action, JavaScript, Python/model, and external PDF tool execution flags false.

```text
php -l lanes/markerpdf/src/PdfActionReviewExtractor.php
php -l lanes/markerpdf/src/PdfAnnotationExtractor.php
php -l lanes/markerpdf/src/PdfLinkAnnotationExtractor.php
php -l lanes/markerpdf/tests/PdfLinkAnnotationCommentDictionaryBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-link-comment-dictionary-boundary-currentbase.php
```

All changed PHP files reported no syntax errors.

```text
git diff --check -- lanes/markerpdf
```

Passed.

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused markerPDF PHP behavior tests move `1410 -> 1411 pass / 0 fail`.
- WordPress scenarios move `1344 -> 1345`.
- Mapped upstream denominator is unchanged; this is deeper native PDF annotation/link parser behavior under the existing mapped annotation/link boundary.

## Non-Overlap

This does not repeat accepted page `/Annots` array tokenization, escaped page `/Ann#6fts` lookup, escaped Link dictionary names, exact annotation generation resolution, URI control-byte filtering, crop/rotation/QuadPoints geometry, remote GoToR primary review, primary `/A` versus chained `/Next` promotion, widget field inheritance, link presentation metadata, or generic parser comment coverage in optional-content/outline paths. The bounded behavior is specifically PDF comment handling inside current Link annotation dictionaries and indirect action dictionaries before WordPress link promotion.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, annotation extractor, link span promoter, action review parser, supplied marker/pdftext span model, Markdown merge path, and WordPress smoke. No Python, pdftext, pypdfium/PDFium, Surya/Torch, Texify, OCR/model, Streamlit/FastAPI, JavaScript execution, PDF action execution, or external PDF-tool execution is introduced.
