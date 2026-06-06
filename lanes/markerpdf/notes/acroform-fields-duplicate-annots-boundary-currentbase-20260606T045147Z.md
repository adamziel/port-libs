# markerPDF AcroForm duplicate Annots widget boundary

Micro-slice: `markerpdf-acroform-fields-boundary-current-base-20260606T045147Z`

Base accepted HEAD: `bd267d6c7c3b75fd2d89153f838d469484d0ec30`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned in the manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. Under the current no-GPU markerPDF scope, AcroForm fields are native searchable-PDF parser and review metadata behavior.
- PDF page `/Annots` arrays define page annotation membership and order. A duplicated Widget object reference should not let a later duplicate occurrence overwrite the first accepted page/slot metadata used for AcroForm review.
- No OCR, Surya, Texify, Torch, pypdfium/PDFium rendering, model worker, browser, live service, or external PDF tool was executed.

## Implementation

- `PdfAcroFormExtractor::pageWidgetMap()` now preserves the first validated page `/Annots` occurrence for a Widget object.
- Later duplicate references to the same Widget object are ignored after page ownership validation, so a wrong-page first occurrence can still fail closed and a later valid occurrence can still be accepted.
- Added focused coverage for same-page duplicate Widget references and a no-`/P` Widget repeated on a later page.
- Added `wordpress-pdf-acroform-fields-duplicate-annots-currentbase.php` as the WordPress smoke path.

## Red First

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsDuplicateAnnotsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps first duplicate page Annots widget occurrence before AcroForm field repair (lanes/markerpdf/tests/PdfAcroFormFieldsDuplicateAnnotsBoundaryCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 0,
)
Actual: array (
  0 => 2,
)

1 test files, 11 assertions, 1 failures
```

The red-first WordPress smoke also aborted with:

```text
Duplicate Widget reference must preserve the first page Annots slot for the title field.
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsDuplicateAnnotsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps first duplicate page Annots widget occurrence before AcroForm field repair

1 test files, 33 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFields*Test.php
Focused test run: 25 selected test files (root lock skipped)
25 test files, 1575 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAcroForm*.php lanes/markerpdf/tests/PdfSecurityAcroForm*.php
Focused test run: 50 selected test files (root lock skipped)
50 test files, 3960 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-duplicate-annots-currentbase.php
```

The smoke emits `same_page_duplicate_first_slot_preserved=true`, `same_page_duplicate_last_slot_ignored=true`, `cross_page_duplicate_first_page_preserved=true`, `cross_page_duplicate_later_page_ignored=true`, `form_values_visible_in_text=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

```text
php -l lanes/markerpdf/src/PdfAcroFormExtractor.php
php -l lanes/markerpdf/tests/PdfAcroFormFieldsDuplicateAnnotsBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-acroform-fields-duplicate-annots-currentbase.php
```

All changed PHP files reported no syntax errors. `lanes/markerpdf/lane-status.json` decoded successfully with `json_decode`.

```text
git diff --check -- lanes/markerpdf
```

Passed.

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused PHP behavior cases: `2397 -> 2398`.
- WordPress scenarios: `2047 -> 2048`.
- Added 33 focused assertions in the new duplicate-Annots boundary test.

## Non-Overlap

This does not repeat accepted AcroForm page-widget discovery, wrong-page `/P` rejection, duplicate Widget `/Parent` or `/P` key selection, direct Widget `/Fields` normalization, parent-without-`/Kids` repair, explicit empty `/Kids` exclusion, child-root normalization, duplicate `/Fields`, duplicate `/Kids`, comment-split references, indirect `/Fields`/`Kids` arrays, token-aware array parsing, generation-exact references, indirect scalar/numeric/type operands, widget appearance/action/XFA/signature review, xref repair, stream filters, fonts, images, outlines, security preflight, or supplied layout/order slices. The bounded behavior is only duplicated page `/Annots` entries for the same Widget object before AcroForm field repair.

## Dependency Closure

No new support component is needed. This patch reuses the native PDF object scanner, generation-aware reference resolver, page tree walker, page `/Annots` parser, widget map, field hierarchy builder, visible-text extractor, and WordPress smoke renderer. Full upstream markerPDF runner parity remains intentionally out of scope under the no-GPU direction and remains gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.

## Next Task

Continue native no-GPU markerPDF triage with non-overlapping searchable-PDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
