## Page Resource Form Malformed Literal Boundary

Slice: `markerpdf-page-resource-inheritance-current-base-20260605T223405Z`

Accepted base: `ddb326e0de676cb18d5010ac541b64ef59fcf1be`

Source truth: upstream `sddai/markerPDF` routes searchable PDF page text through parser/PDFium/pdftext page structures before OCR/model stages. In this native no-GPU PHP boundary, an invoked Form XObject with an explicit but unresolved or malformed `/Resources` value is not a trustworthy resource owner. It must fail closed instead of letting literal form stream text become WordPress paragraph text.

Implementation:

- `PdfTextExtractor::formXObjectResourceOwnerBody()` now returns `null` for blocked explicit Form XObject `/Resources` resolution.
- Form expansion and nested image-review recursion skip invoked Form XObjects whose explicit resources are malformed.
- Missing or `null` Form XObject `/Resources` still inherits the invoking page resource owner, preserving the accepted compatibility behavior.

Red-first evidence:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceFormMalformedBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS form XObject malformed Resources do not promote top-level decoy resource keys
FAIL form XObject malformed Resources block literal form text before WordPress paragraphs
Values are not identical
Expected: array (
  0 => 'Page before unresolved form',
  1 => 'Page after unresolved form',
)
Actual: array (
  0 => 'Page before unresolved form',
  1 => 'Malformed form literal text leak',
  2 => 'Page after unresolved form',
)

1 test files, 4 assertions, 1 failures
```

Focused verification after fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceFormMalformedBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS form XObject malformed Resources do not promote top-level decoy resource keys
PASS form XObject malformed Resources block literal form text before WordPress paragraphs

1 test files, 6 assertions, 0 failures
```

Adjacent resource/image family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceFormMalformedBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceMalformedBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceCategoryStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceEntryStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceImageXObjectInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
8 test files, 1365 assertions, 0 failures
```

Wider page-resource family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResource*CurrentBaseTest.php lanes/markerpdf/tests/PdfPageFormXObjectStructTreeClipCurrentBaseTest.php lanes/markerpdf/tests/PdfPagePropertyExtractorTest.php
18 test files, 748 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-page-resource-form-malformed-boundary-currentbase.php
```

The smoke emits two Gutenberg paragraphs, `literal_form_text_promoted=false`, `private_form_resource_promoted=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Required local checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfPageResourceFormMalformedBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfPageResourceFormMalformedBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-page-resource-form-malformed-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-page-resource-form-malformed-boundary-currentbase.php

php -r '$s=file_get_contents("lanes/markerpdf/lane-status.json"); json_decode($s, true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, json_last_error_msg()."\n"); exit(1); } echo "lane-status.json valid\n";'
lane-status.json valid

git diff --check -- lanes/markerpdf
passed with no output
```

Non-overlap:

This does not repeat accepted page-tree resource inheritance, escaped `/Kids`, catalog-path recovery, page `/Resources` malformed fail-closed behavior, stream-valued page resources, resource category stream rejection, inherited image XObject review, Form XObject `null` resource inheritance, or nested decoy resource-key rejection. The bounded behavior is only literal visible-text suppression for invoked Form XObjects whose explicit `/Resources` cannot resolve.

Dependency closure:

No new support component is needed. The slice reuses the native PHP PDF object scanner, generation-aware resource resolver, page/Form XObject expansion, image-review recursion, and WordPress smoke renderer. Full upstream PDFium/pdftext parity, live OCR/layout/table/equation models, raster rendering, and exact GPU/model benchmark parity remain intentionally out of scope under the no-GPU markerPDF directive.
