# Typst short format alias provenance - 2026-06-11

Bead: plib-ymk36

Scope: PDF/Typst boundary provenance only. The short `-f` output-format alias now feeds the same `typstOutputFormatPolicy` review path as `--format`, so a non-PDF alias such as `-f png` is preserved as explicit output-format provenance in the plan, fake-run artifact review, and fake-run sequence summary.

Verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php` passed 1 test file, 1854 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed 44 test files, 67201 assertions, 0 failures.

External tools intentionally not invoked: Pandoc, Typst, TeX/PDF engines, browser renderers, external validators, online services, live provider tests, or live-service provider tests.
