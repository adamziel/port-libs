# PDF/Typst Output Format Boundary Current Base Slice

Date: 2026-06-10 UTC
Bead: plib-jah9n

This slice maps one bounded PDF/Typst handoff case for Typst `--format` output requests at the PDF boundary.

`PdfEngineHandoff` now records a `typstOutputFormatPolicy` packet during planning, carries it through fake runner results, includes it in artifact provenance review, and exposes it from fake-run sequences as `finalTypstOutputFormatPolicy`.

The policy records:

- the declared PDF output file;
- the output format inferred from the output path;
- any explicit `--format` values;
- review issues when the explicit Typst format is not `pdf`.

Verification on this slice:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests`

No Pandoc, Typst, TeX/PDF engine, office suite, zip/unzip, browser renderer, external validator, online service, live provider test, or live-service provider test is required or executed.
