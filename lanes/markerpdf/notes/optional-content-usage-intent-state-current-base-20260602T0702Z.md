# markerpdf optional-content usage intent state current-base

## Source truth

PDF optional content visibility is a native PDF parser concern before markerPDF hands text to pdftext/model/layout stages. This slice follows PDF optional-content configuration semantics for the current viewing context:

- catalog `/OCProperties /D` establishes the current optional-content configuration;
- `/BaseState`, `/ON`, and `/OFF` provide the baseline OCG state;
- default configuration `/AS` usage application dictionaries for `/Event /View` apply `/Usage /View /ViewState` only to listed OCGs and categories;
- OCG `/Intent` must match the current configuration intent, defaulting to `/View`;
- the resolved state must flow through marked-content property resources, OCMD policies, Form XObject `/OC`, and annotation appearance `/OC`.

The upstream markerPDF Python path delegates this level of visibility to PDF backends before Markdown/block cleanup. The native PHP port now performs this reduced visibility decision without Python, pdftext, pypdfium, or external PDF tools.

## Non-overlap

This does not repeat the accepted optional-content layer slice. That slice covered catalog `/BaseState` plus explicit `/ON`/`OFF` layer visibility for marked content, forms, and annotations. This slice adds the missing configuration `/AS` usage application and OCG `/Intent` state behavior on top of the current base, including the guard that unused `/Usage /ViewState /ON` metadata must not resurrect a layer explicitly listed in `/OFF`.

## Focused evidence

- `php -l lanes/markerpdf/src/PdfTextExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfTextExtractorTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-optional-content-usage-intent-import.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php` passed on integration base: 1 test file, 343 assertions, 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdf-optional-content-usage-intent-import.php` emitted `Base Current View`, `Usage View Visible`, `Visible Usage Form`, `Mixed Intent Visible`, and `Visible Usage Annotation`; it excluded design-intent, usage-off, explicit-off, OCMD-membership, hidden-form, and hidden-annotation text.
- `php tools/run-tests.php lanes/markerpdf/tests` passed on integration base: 58 test files, 2340 assertions, 0 failures.
- `git diff --check -- lanes/markerpdf` passed.

## Dependency closure

No new support component is needed. The slice reuses the existing native PDF object parser, dictionary/array readers, content stream tokenizer, Form XObject expansion, annotation appearance extraction, and optional-content marked-block filtering already present in `PdfTextExtractor`.

## Expected movement

- Behavior tests: `409 -> 410`.
- Mapped markerPDF semantics: `262 -> 263 / 78`.
- Root harness: not run, isolated micro-slice.
