# AcroForm Malformed Hex Scalar Boundary Current Base

## Scope

- Lane: `markerpdf`
- Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260608T074250Z`
- Accepted base: `b8ff3cf9b25772df3390d1b24204be6f5e889d6b`
- Behavior: consume malformed PDF hex-string scalar operands inside AcroForm field value arrays before `/V`, `/DV`, and `/Opt` review metadata.

## Source Truth

Upstream markerPDF depends on PDF parser/pdftext layers before conversion. In the native no-GPU scope, PDF text-string operands must be tokenized as atomic PDF strings even when malformed. A malformed hex string such as `<2F /private_choice_decoy>` is not permission to reinterpret the `/private_choice_decoy` bytes as a separate name operand in an AcroForm choice array. WordPress imports should preserve valid sibling values and labels while keeping malformed scalar payload bytes out of form review and visible text.

No OCR, Surya, Texify, Torch, Python execution, model workers, PDF action execution, rendering, or external PDF tools are involved.

## Patch

- `PdfAcroFormExtractor::readScalarAt()` now consumes the full malformed hex-string token before returning null, preventing embedded name tokens from becoming AcroForm choice values or options.
- `PdfAcroFormExtractor::pdfValueToString()` and XFA token hex decoding now share the same hex-string validation helper, avoiding `hex2bin()` warnings on malformed operands.
- Valid UTF-16BE hex-string field names, alternate names, and mapping names remain preserved.

## Evidence

Red-first focused probe before source edit:

```text
php -r '... malformed /V [<2F /private> (publish)] /Opt [<00 /private> ...] ...'
PHP Warning:  hex2bin(): Input string must be hexadecimal string ...
current => ["private", "publish"]
options => [{"export":"private","label":"private"}, ...]
```

Focused run after source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsMalformedHexScalarBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS consumes malformed hex-string scalar operands before AcroForm choice review

1 test files, 37 assertions, 0 failures
```

AcroForm family guard:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfAcroForm.*Test\.php$' | sort)
Focused test run: 72 selected test files (root lock skipped)
...
72 test files, 4826 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-malformed-hex-scalar-currentbase.php --self-test
<!-- markerpdf:pdf-acroform-fields-malformed-hex-scalar-currentbase ... "valid_utf16_field_names_preserved":true,"malformed_hex_choice_values_excluded":true,"malformed_hex_default_values_excluded":true,"malformed_hex_options_excluded":true,"decoy_names_excluded":true,"field_values_visible_in_text":false,"executes_form_actions":false,"executes_javascript":false,"executes_python_or_models":false,"executes_external_pdf_tools":false ... -->
```

Final verification:

```text
php -l lanes/markerpdf/src/PdfAcroFormExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfAcroFormExtractor.php

php -l lanes/markerpdf/tests/PdfAcroFormFieldsMalformedHexScalarBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfAcroFormFieldsMalformedHexScalarBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-acroform-fields-malformed-hex-scalar-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-acroform-fields-malformed-hex-scalar-currentbase.php

php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); fwrite(STDOUT, "markerpdf json ok\n");'
markerpdf json ok

git diff --check -- lanes/markerpdf
```

Root harness not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted AcroForm field traversal, page-widget repair, parent ownership, direct dictionaries, indirect choice arrays, scalar generation checks, duplicate key handling, null whitespace, field action review, XFA/signature review, or action execution policy. The bounded behavior is only fail-closed tokenization for malformed hex-string scalar operands inside AcroForm field value arrays.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF dictionary, array, string, object-generation, AcroForm review, and WordPress smoke harness. Full upstream model/OCR/rendering parity remains intentionally out of scope under the no-GPU markerPDF directive.
