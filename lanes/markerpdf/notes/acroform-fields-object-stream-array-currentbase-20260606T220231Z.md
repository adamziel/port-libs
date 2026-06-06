# AcroForm Object-Stream Array Boundary Current Base

Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260606T220231Z`
Base accepted HEAD: `6fffd38f996ab9693b71b5cf1954418ba30ab820`

## Source Truth

- Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF form/text extraction through native PDF parser structures before OCR/model stages.
- PDF 1.5 object streams may contain ordinary non-stream objects, including arrays. AcroForm `/Fields` and field `/Kids` operands are arrays and may be indirect references to compressed object-stream members.
- For the no-GPU PHP lane, compressed AcroForm arrays must be parsed as review metadata only: form actions, JavaScript, OCR, Python models, and external PDF tools are not executed.

## Implementation

- `PdfAcroFormExtractor` now admits safe top-level array members from `/ObjStm` expansion alongside non-stream dictionary members.
- The new object-stream member admission rejects empty, scalar, malformed, and top-level stream-object payloads while accepting arrays whose top-level array consumes the member body.
- Compressed `/Fields` and `/Kids` array containers now resolve before field-tree ownership checks, preserving hidden/non-widget field lists and page widget ownership.

## Red-First Evidence

Before the source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsObjectStreamArrayBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL expands object-stream AcroForm Fields and Kids array members before field repair
Expected: ['profile.email']
Actual: []
1 test files, 1 assertions, 1 failures
```

## Verification

After the source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsObjectStreamArrayBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS expands object-stream AcroForm Fields and Kids array members before field repair
1 test files, 47 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsObjectStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php
Focused test run: 2 selected test files (root lock skipped)
2 test files, 582 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroForm*.php
Focused test run: 54 selected test files (root lock skipped)
54 test files, 4036 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-object-stream-array-currentbase.php
```

The smoke emits `compressed_fields_array_recovered=true`, `compressed_kids_arrays_recovered=true`, `array_decoys_excluded=true`, `field_values_review_only=true`, `executes_form_actions=false`, `executes_javascript=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Additional checks passed:

```text
php -l lanes/markerpdf/src/PdfAcroFormExtractor.php
php -l lanes/markerpdf/tests/PdfAcroFormFieldsObjectStreamArrayBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-acroform-fields-object-stream-array-currentbase.php
php -r 'foreach (["lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json", "lanes/markerpdf/lane-status.json"] as $f) { json_decode(file_get_contents($f), true, flags: JSON_THROW_ON_ERROR); echo $f, " OK\n"; }'
git diff --check -- lanes/markerpdf
```

## Status Delta

- Focused PHP behavior tests move `2693 -> 2694` pass / `0` fail.
- WordPress scenarios move `2269 -> 2270`.
- Manifest adds `pdfAcroFormFieldsObjectStreamArrayBoundaryCurrentBase` with 1 mapped behavior row.

## Non-Overlap

This does not repeat accepted object-stream AcroForm field/widget dictionary expansion, object-stream member offset literal-boundary guards, root stream rejection, stream field/widget rejection, direct dictionary materialization, indirect array references, page-owned widget repair, generation-exact field references, or action/resource/signature/XFA review. The bounded change is only top-level array members inside PDF object streams when those arrays are used as AcroForm `/Fields` or `/Kids` containers.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, object-stream decoder, array/dictionary tokenizer, generation map, AcroForm field-tree ownership checks, and WordPress smoke path. Live OCR, Surya/Texify/Torch model execution, PDFium rendering, Streamlit/FastAPI model workers, form action execution, JavaScript execution, and exact upstream model benchmark parity remain intentionally out of scope under the current markerPDF no-GPU direction.
