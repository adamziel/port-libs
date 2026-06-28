# Pandoc PDF/Typst JSON warning envelope provenance

Slice: `plib-cx109`, PDF/Typst boundary provenance.

This slice extends native PHP `PdfEngineHandoff` warning provenance for Typst
machine-readable diagnostics that arrive inside envelope objects. The fake
runner now flattens `diagnostics`, `messages`, and `records` arrays before
classifying warning source paths against the declared Typst root, preserving
inside-root, outside-root, and missing-span review states without executing
Typst.

Validation:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` (1 file, 3,552 assertions, 0 failures)

Accounting:

- Adds one focused `PdfEngineHandoffTest.php` PASS case for Typst JSON warning
  envelope provenance.
- No Pandoc, Typst, TeX/PDF engines, browser renderers, office suites,
  external validators, network services, or archive shell-outs were invoked.
