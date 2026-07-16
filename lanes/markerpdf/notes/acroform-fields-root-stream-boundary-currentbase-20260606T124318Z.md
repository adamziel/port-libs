# AcroForm Fields Root Stream Boundary Current Base

Slice: `markerpdf-acroform-fields-boundary-current-base-20260606T124318Z`

Base accepted HEAD: `9cbc46633f4f22e846199d34261df3033943c7d8`

## Source Truth

- Upstream lane source remains `sddai/markerPDF` pinned in `UPSTREAM_TEST_MANIFEST.json` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- The no-GPU PHP lane maps markerPDF's native searchable-PDF parser boundary before OCR/layout/model execution. Catalog `/AcroForm` is an interactive form dictionary; stream objects at that slot are not valid form roots and must not seed field repair or payload text extraction.

## Behavior

`PdfAcroFormExtractor::acroFormDictionaryBody()` now rejects indirect `/AcroForm` targets that are stream objects. This keeps a field-looking stream dictionary from enabling `/Fields`, `/NeedAppearances`, calculation order, page-widget repair, or review metadata.

The focused fixture keeps normal page `/Contents` text visible while placing `/Fields`, `/NeedAppearances`, and field-looking data in a referenced stream object. WordPress import now fails closed for that root and excludes the AcroForm stream payload and field values from both form review JSON and visible paragraphs.

## Red-First Evidence

Before the source edit:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsRootStreamBoundaryCurrentBaseTest.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects stream objects as AcroForm root dictionaries before field repair (lanes/markerpdf/tests/PdfAcroFormFieldsRootStreamBoundaryCurrentBaseTest.php)
Values are not identical
Expected: false
Actual: true

1 test files, 1 assertions, 1 failures
```

After the source edit:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsRootStreamBoundaryCurrentBaseTest.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
PASS rejects stream objects as AcroForm root dictionaries before field repair

1 test files, 14 assertions, 0 failures
```

## Verification

```bash
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-root-stream-boundary-currentbase.php
```

Result: PASS smoke output with `acroform_root_stream_rejected=true`, `stream_root_field_excluded=true`, `root_stream_payload_excluded=true`, `visible_content_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

```bash
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfAcroFormFields.*CurrentBaseTest\.php$' | sort)
```

Result: `28 test files, 1657 assertions, 0 failures`.

```bash
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfAcroForm.*Test\.php$' | sort)
```

Result: `51 test files, 3866 assertions, 0 failures`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted AcroForm field/widget stream-object rejection, direct dictionary materialization, indirect `/Fields` arrays, object-stream fields, page widget repair, wrong-page `/P`, duplicate keys, generation boundaries, field action/value/signature/XFA review, or Type3/font/parser/image/xref slices. The bounded behavior is only the catalog `/AcroForm` root target being a stream object.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP PDF object parser, stream-object detector, AcroForm extractor, text extractor, focused TestRunner, and WordPress smoke pattern. No Python, OCR, Surya, Texify, Torch, Streamlit/FastAPI worker, pypdfium, or external PDF tool execution was added or run.

## Next Task

Continue with non-overlapping native searchable-PDF parser/review behavior around AcroForm dictionary admission, annotation/form action boundaries, fonts/CMaps, xref repair, stream filters, metadata, page geometry, image/filter metadata, and supplied table/equation handoffs.
