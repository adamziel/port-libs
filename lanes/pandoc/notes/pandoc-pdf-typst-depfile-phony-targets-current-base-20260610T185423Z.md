# pandoc-pdf-typst-depfile-phony-targets-current-base-20260610T185423Z

Slice: `plib-lrjlk`, PDF/Typst boundary provenance.

This slice extends native `PdfEngineHandoff` Typst dependency sidecar
provenance for make-style depfiles that include escaped local paths and
compiler-style phony dependency targets.

Typst-style depfiles can include follow-up empty rules for input dependencies
so make does not fail when a dependency disappears. The fake runner now
recognizes those empty rules when they match already-seen local inputs or Typst
package references and reports them as `engineDependencyPhonyTargets` instead
of treating them as produced PDF outputs. That keeps `engineOutputFiles` and
`typstDependencyOutputPolicy` focused on real output targets while preserving
the phony rows for reviewer provenance.

Focused coverage adds a Typst `--deps` fake-run case with escaped spaces,
escaped hash characters, escaped package-version colons, and a Windows-style
Typst package cache path. It verifies aggregate inputs, structured package
inputs, dependency edge metadata, phony target metadata, artifact-provenance
handoff, output policy, diagnostics, and sequence carry-forward without
invoking Typst or any renderer.

Verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `1 test files, 1523 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 61022 assertions, 0 failures`

This does not run Pandoc, Typst, TeX/PDF engines, browser engines, external PDF
validators, zip/unzip, Node tooling, office suites, Jupyter, online services,
live provider tests, or live-service provider tests. It is limited to bounded
native PHP fake-runner provenance at the PDF/Typst handoff boundary.
