# Pandoc PDF/Typst boundary source summary

Slice: `plib-xv79b`

`PdfEngineHandoff` now carries a compact `typstBoundarySourceSummary`
alongside existing Typst boundary provenance, summary, and matrix packets. The
new packet groups selected and shadowed Typst boundary controls by source:

- engine options;
- selected environment values;
- shadowed environment values;
- implicit controls.

The rollup also exposes source counts, path-source counts, controls by source,
environment variable lists, shadowed environment variables, and inherited
boundary issues. It is threaded through plan output, fake-run output, artifact
provenance review, and fake-run sequence final state without executing Typst or
PDF engines and without changing existing boundary provenance or matrix shapes.

Direct-format parity remains active. This slice only improves bounded native PHP
PDF/Typst boundary/provenance review metadata; it does not claim PDF generation
or Typst execution support through external tools.

Validation:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTypstBoundarySourceSummaryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoff*.php lanes/pandoc/tests/PdfReaderTest.php`

No external Pandoc, office suite, TeX/browser engine, Typst, Node, zip/unzip,
validator, or live service was invoked.
