# markerPDF AcroForm Fields Non-Widget Subtype Boundary

Lane: `markerpdf`
Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260607T110142Z`
Base accepted HEAD: `9796c261eb2e505bb956aa1c10e0f50625834924`

## Source Truth

Pinned upstream markerPDF `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates searchable PDF form/text extraction to PDF parser boundaries before formatting WordPress-visible Markdown. In native PHP scope, AcroForm field dictionaries are field-tree nodes and widget annotations are `/Subtype /Widget`; link, text, and FreeText annotation dictionaries listed in malformed `/Fields` arrays must not become form fields or visible import text.

## Behavior

- `PdfAcroFormExtractor::isNonAcroFormFieldDictionary()` now rejects dictionaries that carry a non-empty non-Widget `/Subtype`.
- Legitimate mixed field/widget dictionaries with `/Subtype /Widget` still import as AcroForm review metadata.
- Malformed `/Fields` entries such as `/Subtype /Link`, `/Subtype /Text`, or `/Subtype /FreeText` with `/T`, `/V`, or nested `/Kids` stay excluded from field review, action execution, and visible WordPress paragraphs.

## Red-First Evidence

Ad-hoc probe before the fix returned both the real field and a `/Subtype /Link` decoy:

```text
[[6,"article.title","Tx"],[10,"link.title",null]]
```

## Verification

Focused after fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsNonWidgetSubtypeBoundaryCurrentBaseTest.php
=> 1 test files, 49 assertions, 0 failures
```

Adjacent AcroForm field-boundary family after fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsNonWidgetSubtypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsActionDictionaryBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsNonFieldParentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsStreamObjectBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFieldsDirectPageWidgetBoundaryCurrentBaseTest.php
=> 5 test files, 209 assertions, 0 failures
```

Full AcroForm focused family after fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroForm*Test.php
=> 61 test files, 4316 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-nonwidget-subtype-boundary-currentbase.php
=> emits non_widget_subtype_fields_excluded=true, inline_widget_preserved=true, form_values_review_only=true, executes_python_or_models=false, executes_external_pdf_tools=false
```

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted stream-object exclusion, action dictionary exclusion, direct dictionary materialization, duplicate key handling, object token boundaries, parent ownership, page-tree non-field parent rejection, direct widget page repair, generation matching, or object-stream AcroForm repair. The bounded behavior is only non-Widget `/Subtype` dictionaries listed in `/Fields` or nested `/Kids`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, dictionary token parser, AcroForm field/widget extractor, page annotation mapper, and WordPress smoke path. Live OCR, Surya/Texify/Torch, pypdfium/PDFium, Streamlit/FastAPI workers, JavaScript/action execution, and external PDF tools remain intentionally out of scope.
