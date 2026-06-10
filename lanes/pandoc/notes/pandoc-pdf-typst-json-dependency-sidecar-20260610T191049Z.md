# PDF/Typst JSON dependency sidecar provenance

Bead: `plib-h9fxs`, PDF/Typst boundary provenance.

This slice extends native `PdfEngineHandoff` fake-runner dependency sidecar
inspection from make-style Typst depfiles to JSON dependency sidecars. Typst
JSON sidecars with `inputs` and optional `outputs` are now parsed within the
existing bounded dependency artifact path, without executing Typst or any PDF
engine.

The implementation routes JSON input and output paths through the existing
dependency normalizer, so workspace inputs, absolute/font inputs, URI inputs,
and Typst package references continue to land in the same provenance fields as
make depfiles. JSON sidecars also synthesize one aggregate dependency edge so
`artifactProvenanceReview` and `fakeRunSequence()` preserve source/output
relationships for reviewer handoff.

Focused coverage adds a `--deps=build/json-deps.json --deps-format=json` fake
run with local Typst and SVG inputs, an absolute font input, a Typst package
reference, and the declared PDF output.

Verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - 1 test file, 1535 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 61083 assertions, 0 failures

No Pandoc, Typst, TeX/PDF engines, browser engines, external PDF validators,
office suites, zip/unzip, Node tooling, Jupyter, or online services were run.
