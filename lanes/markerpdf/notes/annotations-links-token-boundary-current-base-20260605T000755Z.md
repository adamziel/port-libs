# markerPDF Annotations Links Token Boundary Current Base

Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260605T000755Z`
Base accepted HEAD: `23ef3aeaa54ed1b30f19bf25f9b8ec5a5f9f5662`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned in the manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. In the no-GPU PHP lane, searchable PDF text, page annotations, and link metadata are native parser responsibilities before any model/PDFium handoff.
- PDF arrays are token streams. References inside comments, literal strings, hex strings, or nested private arrays are not top-level page `/Annots` entries and must not become WordPress link or markup metadata.

## Implementation

- `PdfAnnotationExtractor`, `PdfLinkAnnotationExtractor`, and `PdfMarkupAnnotationExtractor` now parse `/Annots` array operands token by token, skip PDF comments, ignore literal/hex-string decoys, and do not recurse into nested private arrays.
- Their array readers now skip `%` comments so a `]` inside a comment cannot prematurely close the current page annotation array.
- `PdfPageAnnotsTokenBoundaryCurrentBaseTest.php` covers a current Link, Highlight, and Text annotation plus decoy Link references in a comment, literal string, hex string, and nested array.
- `wordpress-pdf-page-annots-token-boundary-currentbase.php` shows the current WordPress Markdown link and highlight review while proving decoy annotation references remain unpromoted.

## Red First

Before implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageAnnotsTokenBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL tokenizes page Annots arrays before promoting link and markup annotations
Expected: array (0 => 7, 1 => 9, 2 => 13)
Actual: array (0 => 7, 1 => 8)
1 test files, 2 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageAnnotsTokenBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS tokenizes page Annots arrays before promoting link and markup annotations
1 test files, 39 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageAnnotsTokenBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotsTopLevelLinkBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageAnnotsEscapedNameLinkBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php lanes/markerpdf/tests/PdfMarkupAnnotationExtractorTest.php
6 test files, 574 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-page-annots-token-boundary-currentbase.php
```

The smoke emits `annotation_objects=[7,9,13]`, `page_link_count=1`, `link_uri=https://example.com/current-docs-token`, `markup_annotation_object=9`, `comment_decoy_promoted=false`, `literal_decoy_promoted=false`, `hex_decoy_promoted=false`, `nested_decoy_promoted=false`, `visible_text_imported=true`, `annotation_payload_text_visible=false`, and all PDF action, JavaScript, Python/model, and external PDF tool execution flags false.

```text
php -l lanes/markerpdf/src/PdfAnnotationExtractor.php
php -l lanes/markerpdf/src/PdfLinkAnnotationExtractor.php
php -l lanes/markerpdf/src/PdfMarkupAnnotationExtractor.php
php -l lanes/markerpdf/tests/PdfPageAnnotsTokenBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-page-annots-token-boundary-currentbase.php
```

All changed PHP files reported no syntax errors.

```text
git diff --check -- lanes/markerpdf
```

Passed.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted top-level page `/Annots` ownership, escaped `/Annots` names, escaped Link dictionary keys, exact-generation link operands, hidden/no-view widget filtering, rotated/UserUnit link rectangles, annotation appearance/popup/sound review, StructParent action context, or text-markup QuadPoints geometry. The bounded behavior is specifically token-boundary parsing inside the current page `/Annots` array.

## Dependency Closure

No new support component is needed. This reuses the native object scanner, dictionary/array token readers, annotation/link/markup extractors, span-link application, markup-review application, Markdown merge, and WordPress smoke path. No Python, pdftext, pypdfium/PDFium, Surya/Torch, Texify, OCR/model, Streamlit/FastAPI, media playback, JavaScript, or external PDF-tool execution is introduced.
