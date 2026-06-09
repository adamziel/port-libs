# markerpdf page resource Form inline image ColorSpace current-base slice

Slice: `markerpdf-page-resource-inheritance-current-base-20260608T234459Z`

Accepted base: `04878c2d5c57d16172dcae66b4ced2d6a4447658`

## Source truth

- Upstream markerPDF delegates searchable-PDF page content extraction to native PDF parser semantics before model/OCR handoff. Under the current no-GPU scope, this slice ports the native PDF resource behavior only.
- PDF Form XObjects have their own optional `/Resources` dictionary. When a Form explicitly declares local `/Resources /ColorSpace`, inline images inside that Form must tokenize against the Form-local ColorSpace map, not only against page-level resources.
- This is disjoint from the already accepted inherited page inline image ColorSpace case, duplicate resource-category filtering, image XObject Form provenance, and Type3 CharProcs sibling stream-generation boundary.

## Behavior

`PdfTextExtractor` now carries inline image ColorSpace resource maps through Form XObject expansion. The same map is used while rewriting Form-local font, marked-content property, and ExtGState font operands so inline image payload bytes do not become text/rewrite tokens before the Form walker gets to them.

The focused red-first failure was:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceFormInlineImageColorSpaceCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL uses Form XObject local ColorSpace resources for inline image boundaries before WordPress text import
Actual text lines included: Form Inline ColorSpace Payload Noise
1 test files, 1 assertions, 1 failures
```

After the patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceFormInlineImageColorSpaceCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses Form XObject local ColorSpace resources for inline image boundaries before WordPress text import
1 test files, 8 assertions, 0 failures
```

Adjacent resource-family regression command:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageResourceFormInlineImageColorSpaceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceInlineImageColorSpaceInheritanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceColorSpaceValidationCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectFormResourceProvenanceCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceExtGStateFontCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceDuplicateExtGStateFontCurrentBaseTest.php
Focused test run: 6 selected test files (root lock skipped)
7 PASS cases
6 test files, 108 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-form-inline-image-colorspace-currentbase.php --self-test
self_test_passed=true
form_local_colorspace_used=true
inline_image_payload_not_imported=true
page_resource_fallback_not_required=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

## Dependency closure

No new support component is needed. The patch reuses the existing native PHP content tokenizer, resource dictionary resolver, Form XObject expander, and inline image boundary matcher. GPU/model execution, OCR, PDFium, external PDF tools, and live services remain intentionally out of scope.

## Next

Continue with non-overlapping native PDF resource behavior: page/form resource generation boundaries, CMap/font inheritance, image/filter metadata, annotations/forms, outlines, xref repair, or supplied-boundary table/equation handoffs.
