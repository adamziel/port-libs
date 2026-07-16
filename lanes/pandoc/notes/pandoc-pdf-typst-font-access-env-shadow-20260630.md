# Pandoc PDF/Typst Font Access Environment Shadow Slice

Slice: `pandoc-pdf-typst-font-access-env-shadow-20260630`

`PdfEngineHandoff` now preserves `TYPST_IGNORE_SYSTEM_FONTS` and
`TYPST_IGNORE_EMBEDDED_FONTS` provenance when CLI
`--ignore-system-fonts` or `--ignore-embedded-fonts` flags shadow those
environment controls. Shadowed entries are marked for review, retained in
`typstBoundaryProvenance`, surfaced through diagnostics, included in the
`environment-shadows` matrix case, and detailed in `font-access-controls`
without invoking Typst or a PDF engine.

Direct-format parity accounting:

- `mappedTypstFontAccessEnvironmentShadowCases = 1`
- `typstFontAccessEnvironmentShadowAssertions = 22`

Verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- Focused single-fixture runner for
  `preserves shadowed typst font access environment provenance without executing`
  (`1` test, `22` assertions, `0` failures)
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  still has one pre-existing failure in
  `plans latex pdf engine handoff without executing a tex engine`, where the
  current rendered source contains `$E = mc^2$` and the test expects
  `\(E = mc^2\)`. The new font-access shadow fixture passed in that run.

No Pandoc binary, Typst engine, TeX/PDF engine, browser renderer, Node tool,
external validator, network service, or office suite was invoked.
