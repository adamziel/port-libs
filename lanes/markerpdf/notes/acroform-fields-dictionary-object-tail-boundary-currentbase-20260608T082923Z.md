# AcroForm Field Dictionary Object Tail Boundary Current Base

Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260608T082923Z`

Source truth: native markerPDF searchable-PDF form review stays within PDF parser behavior. An indirect object reference used as an AcroForm field or widget target is expected to resolve to one complete PDF dictionary object; stray top-level operands after that dictionary are malformed and must fail closed before WordPress form review. Comment-only tails after a complete dictionary remain acceptable PDF whitespace/comment material. This slice does not execute form actions, JavaScript, Python, OCR, model workers, raster decoding, or external PDF tools.

Implemented behavior:

- Field-tree object references now use a complete AcroForm dictionary-object boundary before field candidate, parent, Kids, page-widget repair, and widget-equivalence handling.
- Tailed indirect field dictionaries such as `<< /FT /Tx ... >> << /FT /Tx ... >>` are excluded before page-widget repair, so sibling decoy field names and values do not surface.
- Tailed indirect widget dictionaries such as `<< /Subtype /Widget ... >> 99 0 R` are not attached to parent fields, while the parent field's review metadata remains available.
- Comment-only widget dictionary tails stay valid and continue to preserve page annotation widget metadata.

Red-first evidence before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsDictionaryObjectTailBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects tailed indirect AcroForm field dictionary objects before page-widget repair
Expected: ['valid.page.repair']; Actual: ['tailed.field.object','valid.page.repair']
FAIL rejects tailed widget dictionary objects while preserving comment-only widget objects
Expected: ['valid.comment.widget']; Actual: ['tailed.widget.parent','valid.comment.widget']
1 test files, 2 assertions, 2 failures
```

Passing verification after the implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsDictionaryObjectTailBoundaryCurrentBaseTest.php
1 test files, 57 assertions, 0 failures

php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfAcroFormFields.*CurrentBaseTest\.php$' | sort)
49 test files, 2637 assertions, 0 failures

php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfAcroForm.*Test\.php$' | sort)
72 test files, 4846 assertions, 0 failures
```

Non-overlap: this does not repeat existing direct root-tail, root dictionary-tail, indirect Fields/Kids array object-tail, reference-object tail, stream object, object-stream array, generation, action dictionary, direct widget, page-widget repair, XFA/signature/action/resource, or model/OCR slices. It narrows the indirect field/widget dictionary object boundary used by AcroForm review paths.

Dependency closure: no new support component is needed. The patch reuses the native parser's top-level dictionary scanner and existing AcroForm field tree/page widget helpers.
