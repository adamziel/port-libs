# PDF/Typst Page Asset Output Template Provenance

## Slice

`PdfEngineHandoff` now records selected Typst `png`/`svg` output formats as
asset-producing direct formats in `typstOutputFormatPolicy`. When the declared
handoff output path contains Typst page-number templates such as `{p}` or
`{0p}`, the policy carries:

- asset output possibility and multi-file possibility
- page-template presence, token counts, token values, and zero-padded token use
- `page-asset-output-template-boundary` as a review issue

The output-format boundary matrix mirrors those fields so review packets can
distinguish a plain direct non-PDF output request from one that can fan out
through page-numbered asset paths.

Primary Typst references checked for the page-template contract:

- https://typst.app/docs/reference/png/
- https://typst.app/docs/reference/svg/

## Direct-Format Parity

This does not make the PDF handoff execute Typst or accept non-PDF output as a
successful Pandoc PDF target. It preserves parity accounting for direct Typst
image export boundaries inside the existing native provenance path.

## Validation

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`

Focused result: `PdfEngineHandoffTest.php` passed with 3,659 assertions and 0
failures.

No Pandoc binary, Typst engine, TeX/browser engine, ZIP tools, office suite, or
external validator was invoked.
