# markerPDF AcroForm indirect-array direct-dictionary boundary

Lane: `markerpdf`
Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260605T234345Z`
Base accepted HEAD: `cd08f68a169af70e0c979f8f7ed342c9a882b0b9`

## Source Truth

Upstream markerPDF relies on PDF parser layers before conversion. In native no-GPU scope, AcroForm field discovery must therefore preserve PDF field-tree structure before WordPress form review without executing OCR, Surya, Texify, Torch, PDFium rendering, JavaScript, form actions, browsers, live services, or external PDF tools.

This slice maps the PDF parser boundary where catalog `/AcroForm /Fields` and field `/Kids` point to indirect array objects, and those array objects contain top-level direct field dictionaries. The accepted direct-dictionary slice covered direct arrays, and the accepted indirect-array slice covered indirect arrays of indirect references. This patch covers the combined boundary only.

## Behavior

`PdfAcroFormExtractor::materializeDirectDictionariesInNamedArray()` now rewrites valid generation-matched referenced array objects as well as direct arrays. Top-level direct field dictionaries inside `/Fields 20 0 R` and `/Kids 21 0 R` are materialized into synthetic generation-zero review objects, then the existing hierarchy/value/widget code handles them.

The same token-aware rules remain in force: literal-string references, nested arrays, nested dictionaries, and comment-only dictionaries remain decoys and are not promoted into AcroForm metadata. Field values and labels remain review-only and do not become visible WordPress paragraph text.

## Red-First Evidence

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsIndirectDirectDictionaryBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL materializes direct AcroForm dictionaries inside indirect Fields and Kids arrays
Values are not identical
Expected: array (
  0 => 'indirect.direct.root',
  1 => 'indirect.direct.parent.child',
)
Actual: array (
)

1 test files, 1 assertions, 1 failures
```

## Verification

```text
php -l lanes/markerpdf/src/PdfAcroFormExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfAcroFormExtractor.php

php -l lanes/markerpdf/tests/PdfAcroFormFieldsIndirectDirectDictionaryBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfAcroFormFieldsIndirectDirectDictionaryBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-acroform-fields-indirect-direct-dictionary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-acroform-fields-indirect-direct-dictionary-currentbase.php

php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "markerpdf JSON ok\n";'
markerpdf JSON ok
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsIndirectDirectDictionaryBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS materializes direct AcroForm dictionaries inside indirect Fields and Kids arrays

1 test files, 59 assertions, 0 failures
```

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'PdfAcroFormFields.*Test\.php$' | sort)
Focused test run: 19 selected test files (root lock skipped)
...
19 test files, 1338 assertions, 0 failures
```

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'PdfAcroForm.*Test\.php$' | sort)
Focused test run: 42 selected test files (root lock skipped)
...
42 test files, 3547 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-indirect-direct-dictionary-currentbase.php
```

The smoke emits `field_names=["indirect.direct.root","indirect.direct.parent.child"]`, `indirect_fields_array_materialized=true`, `indirect_kids_array_materialized=true`, `child_hierarchy_preserved=true`, `array_decoy_fields_excluded=true`, `form_values_used_as_visible_text=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

```text
git diff --check -- lanes/markerpdf
```

No whitespace errors.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `2281 -> 2282`
- `wordpressScenarios`: `1959 -> 1960`
- Added 1 focused current-base behavior test with 59 assertions.
- Added 1 WordPress AcroForm review smoke.
- Added manifest row `pdfAcroFormFieldsIndirectDirectDictionaryBoundaryCurrentBase`.

## Non-Overlap

This does not repeat accepted AcroForm page-owned widget repair, direct widget `/Fields` entries, direct field dictionaries in direct `/Fields` or `/Kids` arrays, indirect arrays of indirect references, generation-exact references, duplicate `/Fields` or `/Kids`, comment-only widget subtype markers, object-stream field expansion, parent ownership repair, explicit empty `/Kids` rejection, quadding metadata, appearance/action/XFA/signature review, page annotation link promotion, xref repair, stream filters, image metadata, OCR, or supplied table/equation handoffs.

The bounded behavior is only direct field dictionaries inside referenced AcroForm field-tree array objects.

## Dependency Closure

No new support component is needed. This reuses the native PHP object scanner, generation guard, token-aware array/dictionary parser, synthetic direct-dictionary materializer, AcroForm field hierarchy builder, page widget map, visible text extractor, and WordPress smoke harness.

Full upstream model/OCR/rendering parity remains intentionally out of scope under the markerPDF no-GPU directive.
