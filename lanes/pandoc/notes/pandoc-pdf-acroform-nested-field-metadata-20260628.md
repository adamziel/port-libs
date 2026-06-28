# PDF AcroForm nested field metadata slice

Slice: `plib-vzfxy`, PDF/Typst boundary provenance.

This slice makes `PdfEngineHandoff` produced-PDF AcroForm field inventory walk
the `/Fields` tree instead of summarizing only top-level field references. The
general `pdfFormFields` metadata now preserves terminal/widget child fields with
inherited fully qualified names and field types, so a parent field such as
`/T (routing)` with a child widget `/T (queue)` is reported as
`routing.queue`.

The change is metadata-only. It does not execute Pandoc, Typst, TeX/PDF
engines, browser renderers, office suites, external validators, or live
services.

Validation:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - 1 file, 3,646 assertions, 0 failures

Mapping delta:

- Adds one focused PDF engine handoff PASS case for nested AcroForm child field
  metadata provenance.
- No direct-format denominator change; this is bounded produced-PDF boundary
  review metadata under the existing PDF/Typst lane.
