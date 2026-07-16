# markerPDF AcroForm Fields Object Dictionary Owner Boundary

Slice: `markerpdf-acroform-fields-boundary-current-base-20260608T040733Z`

## Source Truth

Upstream markerPDF consumes searchable PDF parser output before OCR/model stages. Under the no-GPU markerPDF scope, AcroForm review must stay native and fail closed on malformed PDF object ownership: `/Fields` references are valid field roots only when the referenced object value is a top-level PDF dictionary. Dictionaries hidden in literal strings, comments, arrays, or non-dictionary object tails must not become WordPress form review rows.

## Change

- `PdfAcroFormExtractor::dictionaryObjectBody()` now requires a referenced object body to begin with a top-level PDF dictionary after PDF whitespace/comments.
- Added a focused fixture where one valid AcroForm text field is listed beside four malformed `/Fields` references whose field-like dictionaries are owned by a literal string, a comment, an array, and a name-prefixed tail.
- Added a WordPress smoke proving the valid page-owned widget remains review metadata while malformed object-body decoys stay out of review output and visible text.

## Red-First Evidence

Before the source patch, the new focused test failed because the extractor imported all four decoys:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsObjectDictionaryOwnerBoundaryCurrentBaseTest.php
FAIL requires top-level dictionaries for AcroForm field reference objects before WordPress review
Expected: ['owner.valid']
Actual: ['owner.valid', 'literal.owner.decoy', 'comment.owner.decoy', 'array.owner.decoy', 'tail.owner.decoy']
1 test files, 1 assertions, 1 failures
```

## Verification

```text
php -l lanes/markerpdf/src/PdfAcroFormExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfAcroFormExtractor.php

php -l lanes/markerpdf/tests/PdfAcroFormFieldsObjectDictionaryOwnerBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfAcroFormFieldsObjectDictionaryOwnerBoundaryCurrentBaseTest.php

php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsObjectDictionaryOwnerBoundaryCurrentBaseTest.php
1 test files, 46 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfAcroForm*CurrentBaseTest.php
70 test files, 3993 assertions, 0 failures
```

## Non-Overlap

This does not repeat accepted direct AcroForm dictionaries inside arrays, indirect array-object targets, array-object tails, reference-object tails, object-stream AcroForm fields, stream-object rejection, root dictionary tail rejection, duplicate keys, comments-as-reference-whitespace, NUL whitespace, page-widget repair, direct widget canonicalization, signature/XFA/action review, OCR, Surya/Texify/Torch, model-worker, or external PDF-tool behavior. The bounded behavior is only top-level object ownership for referenced AcroForm field/widget dictionaries.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, AcroForm field/widget repair path, and WordPress smoke path. Live OCR, page rasterization, Surya/Texify/Torch model execution, JavaScript/action execution, signature validation, and exact upstream model benchmark parity remain intentionally out of scope.

## Next Task

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, outlines, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
