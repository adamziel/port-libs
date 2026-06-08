# markerPDF page resource category comment reference current-base

Slice: `markerpdf-page-resource-inheritance-current-base-20260608T133315Z`
Session: `port-dev-markerpdf-resource-inherit-20260608T133315Z`
Base accepted HEAD: `1ae0859e60102323dd11b913da6001a073a626eb`

## Source Truth

Upstream markerPDF routes searchable-PDF text extraction through native PDF page/resource lookup before OCR or model fallback. In PDF syntax, comments are whitespace, including between the object number, generation number, and `R` token of an indirect reference. This slice stays in the no-GPU native parser scope and does not run OCR, Surya, Texify, Torch, PDFium rendering, browser services, or external PDF tools.

## Behavior

Inherited page `/Resources` dictionaries now preserve comment-as-whitespace behavior through category references and marked-content property string resolution. The focused fixture uses an ancestor resource dictionary whose `/Font`, `/XObject`, and `/Properties` categories are indirect references split by PDF comments. The `/Properties` category then resolves a named property dictionary whose `/ActualText` value is itself an indirect string reference split by PDF comments.

`PdfTextExtractor::pdfStringTokenAt()` now uses the shared comment-aware indirect-reference tokenizer instead of a whitespace-only regex, so indirect string operands such as `/ActualText 33 % comment\n 0 % comment\n R` resolve before visible text fallback. This preserves the inherited ActualText replacement and prevents raw glyph text from leaking into WordPress paragraphs.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceCategoryCommentReferenceCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL treats PDF comments as whitespace inside inherited resource category references (lanes/markerpdf/tests/PdfPageResourceCategoryCommentReferenceCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'Category comment inherited font text',
  1 => 'Category comment inherited actual text',
  2 => 'Category comment inherited form text',
)
Actual: array (
  0 => 'Category comment inherited font text',
  1 => 'Category comment raw property glyph',
  2 => 'Category comment inherited form text',
)

1 test files, 1 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceCategoryCommentReferenceCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS treats PDF comments as whitespace inside inherited resource category references

1 test files, 15 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceCategoryCommentReferenceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceCommentReferenceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceEntryCommentBoundaryCurrentBaseTest.php
Focused test run: 3 selected test files (root lock skipped)
PASS treats PDF comments as whitespace inside inherited resource category references
PASS treats PDF comments as whitespace inside page Resources references before inherited lookup
PASS resolves comment-delimited page Resources wrapper objects before inherited lookup
PASS treats PDF comments as whitespace inside inherited resource entry references

3 test files, 69 assertions, 0 failures
```

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'PdfPageResource.*CurrentBaseTest\.php$' | sort)
Focused test run: 46 selected test files (root lock skipped)
46 test files, 1076 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 629 assertions, 0 failures
```

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfPageResourceCategoryCommentReferenceCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-page-resource-category-comment-reference-currentbase.php
```

All reported no syntax errors.

```text
php lanes/markerpdf/examples/wordpress-pdf-page-resource-category-comment-reference-currentbase.php
```

The smoke exits 0 and emits three WordPress paragraph blocks: inherited font text, inherited ActualText replacement, and inherited Form XObject text. It reports `actual_text_reference_comment_split_resolved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

```text
git diff --check -- lanes/markerpdf
```

No whitespace errors.

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused markerPDF PHP pass count: `3113 -> 3114`.
- Added one focused page-resource inheritance PASS case with 15 assertions.
- Added one WordPress page-resource category comment-reference smoke.
- No dashboard/root coordination files were edited.

## Non-Overlap

This does not repeat accepted page `/Resources` comment-delimited references, resource-wrapper object references, inherited resource entry references, direct dictionary tail fail-closed behavior, indirect null inheritance, explicit empty dictionaries, generation filtering, stream resource rejection, category stream rejection, ProcSet metadata, optional-content wrappers, form resource null/malformed handling, duplicate `/Resources` precedence, image XObject review, xref repair, stream filters, CMaps, outlines, attachments, annotations, forms, runtime planners, or OCR/model behavior. The bounded behavior is comment-aware indirect string resolution for inherited marked-content property ActualText reached through page-resource category inheritance.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object table, dictionary/value scanner, comment-aware indirect-reference tokenizer, page-tree resource inheritance resolver, marked-content property replacement path, page-boundary metadata extractor, and lane-local WordPress smoke harness. Full upstream OCR/model/rendering parity remains intentionally out of scope under the current no-GPU markerPDF direction.
