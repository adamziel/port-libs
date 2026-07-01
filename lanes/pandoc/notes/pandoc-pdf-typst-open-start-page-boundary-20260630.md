# PDF/Typst open-start page boundary provenance

Slice: `plib-ijwgh`, PDF/Typst boundary provenance.

`PdfEngineHandoff` now treats Typst page selections such as `--pages=-3`
as safe open-start `range-to` selections instead of invalid dash-prefixed
scalars. The PDF export boundary matrix carries a
`pageSelectionRangeToSegmentCount` detail, while dash-prefixed non-page
scalar boundaries such as invalid jobs, PPI, and creation timestamps remain
review diagnostics.

Focused coverage adds a Typst `--pages=-3,5-` fixture and updates the
dash-prefixed scalar fixture so `-3` is preserved as a page range-to segment
while `-1`, `-2`, and `-4` stay invalid for their scalar options.

Validation:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed with 1 test file, 3,470 assertions, and 0 failures.

No Pandoc, Typst, TeX/PDF engines, browser renderers, office suites, archive
tools, Node tooling, external validators, or online services were invoked.
