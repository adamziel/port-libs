# Pandoc PDF/Typst Stdout Output Boundary Slice

Slice: `plib-nob4z`, PDF/Typst boundary provenance.

`PdfEngineHandoff` now preserves the Typst stdout output boundary when
`outputPath` is `-`. The plan keeps `typst compile <source> -` as stdout output
instead of treating `-` as a pseudo PDF file path, marks the Typst output-format
policy with `outputDestination: stdout` and `output-stdout-boundary`, and
carries that review state through the Typst boundary matrix.

Fake runs now consume produced PDF bytes from `stdout` for this boundary, so
artifact provenance review and fake-run sequence summaries carry the same PDF
hash, output-format policy, and matrix evidence without staging a `-` file.

Validation:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  (`1` file, `3543` assertions, `0` failures)

No Pandoc binary, Typst engine, TeX/PDF engine, browser renderer, external
validator, network service, or office suite was invoked. This does not claim
direct-format denominator or parity movement; it is a bounded metadata-only
PDF/Typst boundary provenance slice.
