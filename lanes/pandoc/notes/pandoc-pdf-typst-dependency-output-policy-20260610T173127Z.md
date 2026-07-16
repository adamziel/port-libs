# pandoc-pdf-typst-dependency-output-policy-20260610T173127Z

Slice: `plib-m34t`, PDF/Typst boundary provenance.

This slice adds bounded native review metadata for Typst dependency sidecar
output targets. `PdfEngineHandoff` fake runs now compare captured Typst
make-style dependency targets against the planned PDF output path and expose a
`typstDependencyOutputPolicy` in the fake-run result, artifact provenance review,
and fake-run sequence final output.

The policy records the declared output file, dependency output targets, whether
the declared output is present, extra dependency output targets, and review
issues. A stale depfile can now move provenance review to `review` without
claiming engine execution or invalidating an otherwise present fake PDF artifact.

Focused coverage adds a Typst `--deps` fake-run case with a stale target
(`build/stale.pdf`) while the planned fake PDF is `build/current.pdf`. It
verifies policy metadata, diagnostics, review issues, and sequence carry-forward.

Verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - 1 test file, 1493 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 60890 assertions, 0 failures
