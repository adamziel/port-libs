# markerPDF AcroForm Duplicate Page Annots Boundary

Session: `port-dev-markerpdf-acroform-fields-20260607T081409Z`
Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260607T081409Z`
Base accepted HEAD: `9a99b1354a078d5de605da43f47436ae430ab41c`

## Source Truth

Upstream `sddai/markerPDF` at the manifest-pinned commit routes searchable PDF page text and document structures through PDF parser dependencies before model execution. In this no-GPU native PHP lane, AcroForm fields are review metadata for WordPress imports: widget values, alternate labels, mapping names, and action dictionaries must not become visible paragraph text or execute PDF actions.

This slice keeps the existing lane policy for malformed duplicate dictionary keys consistent across AcroForm boundaries. The parser already uses the last top-level `/Fields`, `/Kids`, widget `/Parent`, and widget `/P` values. Page-owned widget repair now applies the same boundary to page `/Annots`, so a stale first `/Annots` key cannot promote a decoy widget or hide the current page annotation list.

## Behavior

- `PdfAcroFormExtractor::pageWidgetMap()` now reads the last top-level page `/Annots` value before mapping page-owned widgets.
- A malformed page with `/Annots [stale] /Annots [current]` repairs the current omitted parent field and inline widget field.
- The stale first-key widget is excluded from AcroForm review metadata.
- Field values and labels stay review-only and are not emitted as visible WordPress text.
- No PDF actions, JavaScript, Python, OCR, model code, GPU path, or external PDF tools run.

## Red-First Evidence

Before the parser change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsDuplicatePageAnnotsKeyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL uses the last top-level page Annots key before AcroForm page widget repair
Expected: ['listed.email', 'current.category', 'current.inline']
Actual: ['listed.email', 'stale.first.annots']
1 test files, 1 assertions, 1 failures
```

After the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsDuplicatePageAnnotsKeyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses the last top-level page Annots key before AcroForm page widget repair
1 test files, 41 assertions, 0 failures
```

AcroForm field-family guard:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAcroFormFields*CurrentBaseTest.php
Focused test run: 35 selected test files (root lock skipped)
35 test files, 1973 assertions, 0 failures
```

Broader AcroForm extractor/action/appearance guard:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroForm*Test.php
Focused test run: 58 selected test files (root lock skipped)
58 test files, 4182 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-duplicate-page-annots-currentbase.php
```

The smoke exits `0` and emits:

- `last_page_annots_selected=true`
- `promoted_omitted_parent_field=true`
- `promoted_inline_widget_field=true`
- `stale_first_annots_widget_excluded=true`
- `executes_pdf_actions=false`
- `executes_javascript=false`
- `executes_python_or_models=false`
- `executes_external_pdf_tools=false`

## Non-Overlap

This does not repeat accepted AcroForm field key parsing, indirect `/Fields` or `/Kids` arrays, direct widget dictionaries, duplicate `/Fields` or `/Kids` keys, widget `/Parent` and `/P` duplicate keys, wrong-page `/P` rejection, page-tree escaped names, object-stream field expansion, or xref-generation field selection. The new behavior is limited to duplicate page `/Annots` key selection before page-widget AcroForm repair.

## Dependency Closure

No new support component is needed. This reuses the native PDF object parser, duplicate-key value reader, page-tree traversal, AcroForm field repair, page-widget review metadata, and WordPress smoke path. Full upstream runtime/model parity remains intentionally out of scope under the current no-GPU markerPDF directive.

## Next Task

Continue with non-overlapping native searchable-PDF parser behavior around remaining forms, annotations, metadata, fonts, CMaps, xref repair, stream filters, page geometry, image/filter metadata, and supplied-boundary handoffs.
