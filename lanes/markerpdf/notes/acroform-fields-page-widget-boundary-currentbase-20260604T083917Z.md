# markerPDF AcroForm Page-Widget Field Boundary

## Source truth

Relevant PDF parser behavior for this slice: AcroForm fields are normally reached from catalog `/AcroForm /Fields`, but Widget annotations are page-owned annotations and can carry field dictionaries directly or point to a `/Parent` field. The native no-GPU markerPDF boundary should therefore repair malformed field discovery only from page annotation widgets, while keeping detached widgets and form payload values out of visible WordPress text.

This maps the native searchable-PDF/parser side of markerPDF. No OCR, Surya, Texify, pypdfium rendering, model worker, browser, or external PDF tool was executed.

## Implementation

`PdfAcroFormExtractor` now augments root field references after reading `/AcroForm /Fields`:

- existing `/Fields` roots remain first and authoritative;
- page-referenced Widget annotations whose `/Parent` field dictionary is not reachable from the field tree are promoted for review;
- standalone page Widget dictionaries with field attributes such as `/FT` and `/T` are promoted for review;
- detached widgets not referenced by page `/Annots` remain excluded.

This preserves existing field hierarchy, widget appearance, action-review, submit/reset, XFA, and signature behavior.

## Red-First Evidence

Before implementation, the focused test failed because only the listed field-tree root was extracted:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL repairs AcroForm field discovery from page owned widget annotations only
Expected: [listed.email, omitted.category, inline.note]
Actual: [listed.email]
1 test files, 1 assertions, 1 failures
```

After implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS repairs AcroForm field discovery from page owned widget annotations only
1 test files, 30 assertions, 0 failures
```

Adjacent AcroForm family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroForm*.php
Focused test run: 24 selected test files (root lock skipped)
24 test files, 2205 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-boundary-currentbase.php
```

Emitted `field_count=3`, `field_names=["listed.email","omitted.category","inline.note"]`, `detached_widget_excluded=true`, and all execution flags false.

## Non-Overlap

This does not repeat accepted AcroForm field hierarchy value inheritance, widget appearance state, appearance resource review, submit/reset action review, seed/lock/signature review, XFA value/action review, page annotation widget link promotion, or security permission slices. The bounded behavior is only page annotation Widget field discovery when `/AcroForm /Fields` omits a page-owned field parent or standalone widget field.

## Dependency Closure

No new support component is needed. The patch reuses the native object scanner, dictionary parser, page tree walker, page annotation widget map, field hierarchy builder, and existing AcroForm value/action review code. Full upstream model parity remains intentionally out of scope under the current no-GPU markerPDF directive.
