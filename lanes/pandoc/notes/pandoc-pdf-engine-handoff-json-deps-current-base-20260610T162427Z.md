# pandoc-pdf-engine-handoff-json-deps-current-base-20260610T162427Z

Slice: `plib-crta`, PDF/Typst boundary provenance.

This slice teaches native `PdfEngineHandoff` fake-run provenance to parse
Typst JSON dependency sidecars produced by `--deps-format=json`. JSON sidecars
now feed the same bounded dependency classifier as make-style depfiles, so
local inputs are checked for staged/present files, absolute font paths remain
external provenance, Typst package references remain `typst-package:*`
provenance, and declared output files are surfaced in engine output metadata.

Focused coverage adds a Typst JSON dependency sidecar case with local source,
image, and CSV inputs, an external font URI, a package reference, and multiple
outputs. It proves missing local JSON-declared inputs fail the fake run while
external and package references are preserved for reviewer handoff without
requiring local files.

Verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - 1 test file, 1496 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 60624 assertions, 0 failures

This does not run Pandoc, Typst, TeX/PDF engines, browser engines, external PDF
validators, unzip/zip, office suites, online services, live provider tests, or
network-backed resource fetching. It is limited to bounded native PHP
planning/fake-runner provenance at the PDF/Typst handoff boundary.
