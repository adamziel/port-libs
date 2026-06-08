# markerPDF AcroForm Direct Parent Without Kids Boundary

Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260608T192042Z`

Accepted base: `e97bdf9331ef05dac3f6237d837a28df8dd53eb5`

## Source Truth

Pinned upstream markerPDF `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` consumes searchable-PDF text and review metadata before OCR/layout/model fallbacks. Under the current no-GPU scope, catalog `/AcroForm` field tree parsing remains native PDF parser behavior for WordPress import review.

PDF field dictionaries can inherit `/FT`, `/Ff`, `/V`, `/DV`, `/DA`, `/DR`, `/Q`, and `/MaxLen` from ancestor fields. This slice covers a malformed but common repair boundary: a listed child field has a direct `/Parent << ... >>` field dictionary that omits the reverse `/Kids` array. The child explicitly owns the parent relation, so the native parser can synthesize a bounded parent `/Kids [child R]` edge for inheritance. Direct parents with explicit empty or mismatched `/Kids` remain rejected.

## Red-First Evidence

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsDirectParentNoKidsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL repairs direct AcroForm Parent field dictionaries that omit Kids for listed children (lanes/markerpdf/tests/PdfAcroFormFieldsDirectParentNoKidsBoundaryCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'profile.email',
  1 => 'local.only',
)
Actual: array (
  0 => 'email',
  1 => 'local.only',
)

1 test files, 1 assertions, 1 failures
```

The failure showed the terminal child was extracted as `email` without the direct parent `profile` name or inherited field attributes.

## Implementation

- `PdfAcroFormExtractor::materializeDirectFieldParentDictionary()` now handles direct parent field dictionaries that omit `/Kids` by injecting a synthetic generation-exact `/Kids [child generation R]` entry before materializing the parent object.
- Existing explicit `/Kids` ownership remains authoritative: empty or mismatched `/Kids` direct parents still fail the existing `directParentFieldDictionaryOwnsChild()` check.
- The focused fixture verifies inherited `/FT`, `/Ff`, `/DV`, `/DA`, `/DR`, `/Q`, and `/MaxLen`, terminal `/V` override review, default-resource font resolution, page-owned widget metadata, and visible-text isolation.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsDirectParentNoKidsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS repairs direct AcroForm Parent field dictionaries that omit Kids for listed children

1 test files, 73 assertions, 0 failures
```

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfAcroForm.*Test\.php$' | sort)
Focused test run: 90 selected test files (root lock skipped)
...
90 test files, 5681 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-direct-parent-nokids-currentbase.php
<!-- markerpdf:pdf-acroform-fields-direct-parent-nokids-currentbase {"source":"native-pdf-catalog-acroform-direct-parent-nokids-boundary","field_names":["profile.email","local.only"],"synthetic_parent_object":42,"profile_email_inherited_attributes":["FT","Ff","DV","DA","DR","Q","MaxLen"],"terminal_overrides_parent_value":true,"local_empty_kids_parent_rejected":true,"visible_text_excludes_form_values":true,"executes_python_or_models":false,"executes_external_pdf_tools":false} -->
Visible AcroForm direct Parent no Kids boundary body
```

```text
php -l lanes/markerpdf/src/PdfAcroFormExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfAcroFormExtractor.php

php -l lanes/markerpdf/tests/PdfAcroFormFieldsDirectParentNoKidsBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfAcroFormFieldsDirectParentNoKidsBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-acroform-fields-direct-parent-nokids-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-acroform-fields-direct-parent-nokids-currentbase.php
```

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, token-aware dictionary parser, AcroForm direct-dictionary materializer, field hierarchy walker, page widget map, default-resource review path, text extractor, and WordPress smoke renderer. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, form action execution, JavaScript execution, signing/validation, and external PDF tools remain intentionally outside the current markerPDF no-GPU directive.

## Non-Overlap

This does not repeat accepted direct parent dictionaries whose explicit `/Kids` owns the listed child, page-owned Widget parent repair, direct Widget `/Fields` parent-without-`/Kids` normalization, parent ownership rejection, child branch/root normalization, field overlap dedupe, cycle guards, generation-exact references, object-stream field recovery, token/comment array parsing, wrong-page `/P` rejection, XFA/signature/action review, submit/reset review, or default-resource appearance metadata. The bounded behavior is only direct `/Parent <<...>>` field dictionaries on listed child fields where the direct parent omits `/Kids`.
