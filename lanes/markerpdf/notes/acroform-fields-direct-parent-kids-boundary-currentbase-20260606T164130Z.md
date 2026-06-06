# AcroForm Direct Parent Kids Boundary Current Base

Slice: `markerpdf-acroform-fields-boundary-current-base-20260606T164130Z`
Base: `beefb9c61faa06047be0268dd66c6d5afebefc6c`

## Behavior

This patch covers malformed AcroForm PDFs where `/AcroForm /Fields` omits a parent field, but a page `/Annots` entry contains a top-level direct `/Subtype /Widget` dictionary whose `/Parent` points to that parent and the parent `/Kids` array contains the same direct Widget dictionary.

`PdfAcroFormExtractor` now treats the equivalent direct Widget dictionary in `/Kids` as ownership evidence for page-widget parent repair, then deduplicates the synthetic direct widget reference against the page-owned widget metadata. The recovered WordPress form field is emitted once, with page object/page annotation metadata preserved. Detached direct-Kids fields remain excluded, and form values, alternate names, mapping names, defaults, and max-length data stay review-only.

## Evidence

Red-first focused run after adding the test:

`php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php`

Result: `1 test files, 503 assertions, 1 failures`

Focused after implementation:

`php tools/run-tests.php lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php`

Result: `1 test files, 532 assertions, 0 failures`

Focused AcroForm fields family:

`php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'PdfAcroFormFields.*Test\.php$' | sort)`

Result: `29 test files, 1715 assertions, 0 failures`

Broader AcroForm family:

`php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'PdfAcroForm.*Test\.php$' | sort)`

Result: `52 test files, 3924 assertions, 0 failures`

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-acroform-fields-boundary-currentbase.php`

Result: reports `field_count=10`, `direct_parent_kids_widget_repaired=true`, `promoted_page_widget_parent_fields=["omitted.category","directkids.parent"]`, and no form actions, JavaScript, Python/models, or external PDF tools execute.

Final hygiene:

`php -l lanes/markerpdf/src/PdfAcroFormExtractor.php`

Result: `No syntax errors detected in lanes/markerpdf/src/PdfAcroFormExtractor.php`

`php -l lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php`

Result: `No syntax errors detected in lanes/markerpdf/tests/PdfAcroFormFieldsBoundaryCurrentBaseTest.php`

`php -l lanes/markerpdf/examples/wordpress-pdf-acroform-fields-boundary-currentbase.php`

Result: `No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-acroform-fields-boundary-currentbase.php`

`php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "markerpdf JSON ok\n";'`

Result: `markerpdf JSON ok`

`git diff --check -- lanes/markerpdf`

Result: clean, no output.

## Non-Overlap

This does not repeat accepted AcroForm coverage for indirect `/Fields` and `/Kids` arrays, direct AcroForm dictionary materialization, direct page Widget materialization, wrong-page `/P` rejection, parent fields that omit `/Kids`, explicit empty `/Kids` exclusion, or action/script review. The new boundary is specifically equivalent top-level direct Widget dictionaries shared between page `/Annots` and an omitted parent field's `/Kids` tree before parent repair.

## Dependency Closure

No new support component is needed. The implementation reuses the native PDF object scanner, dictionary/value parsing helpers, AcroForm field hierarchy repair, and page-widget map. It does not require OCR, Surya/Texify/Torch, PDF rendering, external PDF tools, JavaScript execution, or form action execution.
