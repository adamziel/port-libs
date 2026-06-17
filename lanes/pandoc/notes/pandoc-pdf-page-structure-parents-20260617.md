# PDF page structure parent provenance slice

Bead: `plib-9lccc`
Base: `e10697f87d`
Date: 2026-06-17 UTC

## Scope

Added bounded produced-PDF page `/StructParents` provenance to
`PdfEngineHandoff`. Fake-run inspection now exposes `pdfPageStructureParents`
with page number, page object, declared parent-tree index, and source label,
propagates it through `finalPdfPageStructureParents`, and emits deterministic
page structure-parent diagnostics.

This is limited to native produced-byte PDF inspection in `lanes/pandoc`; it
does not invoke Pandoc, Typst, TeX/PDF engines, browser renderers, office
suites, external validators, online services, live provider tests, or
live-service provider tests.

## Coverage

Added one focused `PdfEngineHandoffTest` fixture covering tagged PDF page
dictionaries with `/StructParents 4` and `/StructParents 7`, paired with a
bounded structure parent tree. The fixture asserts the fake-run payload,
sequence payload, total diagnostic, and per-index diagnostics.

## Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - 1 test files, 2721 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 258 test files, 175110 assertions, 0 failures
