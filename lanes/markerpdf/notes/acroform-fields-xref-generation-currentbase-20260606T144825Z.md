# markerPDF AcroForm Fields Xref Generation Current Base

## Scope

Native no-GPU markerPDF/PDF parser behavior for AcroForm field review.

Upstream source truth: markerPDF imports searchable PDFs through parser layers before any OCR/model stages, and PDF indirect-object resolution is governed by the selected trailer/xref object generations. A WordPress import must not let a stale higher-generation direct object replace the trailer-selected AcroForm catalog, fields, widgets, or page text.

## Change

- `PdfAcroFormExtractor::pdfObjects()` now records direct object definitions by object number, generation, and byte offset.
- When a classic `startxref` table is available, the extractor selects in-use xref rows, follows `/Prev` chains, and materializes those exact direct object generations before field-tree repair.
- The old direct-object scanner remains the fallback for PDFs without usable classic xref tables, preserving current direct fixture behavior.

## Evidence

Red-first probe after adding the focused fixture showed the stale branch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsXrefGenerationBoundaryCurrentBaseTest.php
FAIL uses xref selected object generations before AcroForm field repair
Expected: current.xref.email, current.xref.status
Actual: stale.xref.email
```

Focused after fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsXrefGenerationBoundaryCurrentBaseTest.php
1 test files, 28 assertions, 0 failures
```

AcroForm field-boundary family after fix:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfAcroFormFields.*CurrentBaseTest\.php$' | sort)
29 test files, 1685 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-xref-generation-currentbase.php
```

Reports `xref_selected_fields_preserved=true`, `stale_higher_generation_fields_excluded=true`, `form_values_visible_in_text=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP parser and bounded classic xref table parsing already present in the markerpdf lane. It does not run OCR, Surya, Texify, Torch, pypdfium, external PDF tools, JavaScript, or form actions.

## Non-Overlap

This does not repeat accepted AcroForm field tree cycles, direct widgets, indirect arrays, comment-split references, scalar generation operands, action field generation, page widget parent repair, trailer-root selection with different object numbers, xref repair in text/metadata/attachment extractors, or XFA/signature/action review. The bounded behavior is only classic xref-selected object generations for AcroForm catalog/field/widget repair when stale higher-generation direct objects with the same object numbers appear later in the file.
