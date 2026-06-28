# PDF/Typst JSON dependency sidecar provenance

Bead: `plib-b5gyq`, PDF/Typst boundary provenance core blocker slice.

This slice extends native PHP `PdfEngineHandoff` fake-run dependency sidecar
inspection from recorder, make, and zero sidecars to declared Typst JSON
dependency sidecars. The parser is gated to the planned dependency artifact, so
unrelated JSON artifacts such as timings sidecars are not reclassified as
dependency manifests.

JSON sidecars with `inputs` and `outputs` now route local files, absolute/font
references, URI-like references, and Typst package references through the
existing bounded dependency normalizer. The resulting dependency edge,
package-dependency provenance, external-dependency policy, and declared output
policy are preserved in `artifactProvenanceReview` and `fakeRunSequence()`.

Validation:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - 1 test file, 3524 assertions, 0 failures

Mapping/accounting:

- `phpPass` moves `457 -> 458`; `phpFail` stays `0`.

No Pandoc, Typst, TeX/PDF engine, browser engine, external PDF validator,
office suite, `zip`/`unzip`, Node tooling, Jupyter, or online service was run.
