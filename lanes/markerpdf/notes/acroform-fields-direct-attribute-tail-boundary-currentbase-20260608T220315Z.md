# AcroForm Fields Direct Attribute Tail Boundary Current Base

Slice: `markerpdf-acroform-fields-boundary-current-base-20260608T220315Z`

Accepted base: `744d742adbbbf391182231a7a5b4f2d0d558edc2`

## Source Truth

This slice stays inside markerPDF's native no-GPU PDF parser/review scope. AcroForm field dictionaries may carry scalar attributes such as `/FT`, `/Ff`, `/V`, `/DV`, `/Q`, `/MaxLen`, `/DA`, and `/DR`, but each attribute value must be one complete top-level PDF value before the next dictionary key. A direct value followed by a stray scalar/reference operand is malformed and must fail closed instead of populating WordPress field-review metadata from the first token.

The implementation reuses the existing token-aware dictionary value span reader and whitespace/comment boundary helper. It does not execute form actions, JavaScript, OCR/model code, PDFium/PIL raster rendering, Python, or external PDF tools.

## Behavior

`PdfAcroFormExtractor::mergeFieldAttributes()` now rejects any AcroForm field attribute whose top-level value is followed by a non-comment operand before the next dictionary key. This generalizes the prior choice-array-only tail guard to scalar and dictionary-valued field attributes.

The focused fixture proves:

- `/FT /Tx 90 0 R` does not set field type.
- `/Ff 4096 91 0 R` does not set flags.
- `/V (...) 92 0 R` and `/DV (...) 93 0 R` do not set current/default values.
- `/Q 2 94 0 R`, `/MaxLen 24 95 0 R`, and `/DA (...) 96 0 R` do not override safe AcroForm defaults.
- Comment-only tails after valid direct scalar attributes remain accepted.

## Evidence

Red-first focused run before the implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsDirectAttributeTailBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects tailed direct AcroForm scalar attributes before WordPress field review
Values are not identical
Expected: NULL
Actual: 'Tx'

1 test files, 12 assertions, 1 failures
```

Focused run after the implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsDirectAttributeTailBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects tailed direct AcroForm scalar attributes before WordPress field review

1 test files, 63 assertions, 0 failures
```

AcroForm regression family:

```text
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f -name 'PdfAcroForm*Test.php' | sort)
Focused test run: 93 selected test files (root lock skipped)
...
93 test files, 5816 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-direct-attribute-tail-currentbase.php
```

The smoke exits `0` and reports `tailed_field_type_rejected=true`, `tailed_flags_rejected=true`, `tailed_value_rejected=true`, `tailed_default_rejected=true`, `tailed_max_length_rejected=true`, `tailed_quadding_fell_back_to_acroform_default=true`, `comment_only_tails_preserved=true`, `form_values_visible_in_text=false`, `executes_form_actions=false`, `executes_javascript=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted AcroForm scalar-object tails, direct Fields/Kids array tails, indirect reference-object tails, malformed hex scalar arrays, generation-aware fields/actions, page-widget ownership repair, direct parent/Kids repair, duplicate field/key/subtype boundaries, object-stream field arrays, XFA/signature/lock/action review, or form action execution policy. The bounded change is only direct field attribute value tail rejection before inherited/effective AcroForm metadata is built.

## Dependency Closure

No new dependency or support-library component is needed. The patch reuses the native PHP PDF object scanner, dictionary value tokenizer, AcroForm field attribute merger, page-widget review path, and WordPress smoke pattern.

## Next

Continue with non-overlapping native markerPDF parser/review gaps around fonts, CMaps, stream filters, xref repair, metadata, annotations/forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
