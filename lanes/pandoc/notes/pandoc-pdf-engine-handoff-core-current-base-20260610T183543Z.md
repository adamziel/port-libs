# pandoc-pdf-engine-handoff-core-current-base-20260610T183543Z

Slice: `plib-zca1`, PDF/Typst boundary provenance.

This slice extends native `PdfEngineHandoff` Typst dependency provenance for
cache-backed package paths. Typst `--deps` sidecars can expose package files as
filesystem paths under `typst/packages/<namespace>/<package>/<version>/...`
instead of literal `@namespace/package:version` tokens. The fake runner now
normalizes those Unix, file-URI, and Windows-style cache paths into stable
`typst-package:@...` external inputs before generic absolute-path handling,
then carries them through structured package dependency metadata and fake-run
sequence summaries.

Focused coverage adds one Typst depfile regression for cache paths from
preview and typst package namespaces. It proves package cache files are not
treated as missing workspace inputs while namespace, package, version, and
subpath remain reviewable.

Verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - 1 test file, 1502 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 60993 assertions, 0 failures

This does not run Pandoc, Typst, TeX/PDF engines, browser engines, external PDF
validators, unzip/zip, Node tooling, office suites, Jupyter, or network-backed
resource fetching. It is limited to bounded native PHP planning/fake-runner
provenance at the PDF/Typst handoff boundary.
