# PDF/Typst font environment URI boundary provenance

Bead: `plib-37ak0`

Base: `origin/main` `67769737bb05826f7cd74e5c5a4390c79660fcec`

`PdfEngineHandoff` now applies the same guarded font-path splitting to
`TYPST_FONT_PATHS` environment values that it already uses for command-line
`--font-path` values. A single URI or Windows-drive-style font path is preserved
as one boundary entry instead of being split on the scheme or drive colon.

The slice stays in the native PDF/Typst provenance handoff: no Pandoc, Typst,
TeX/PDF engines, browser renderers, office suites, external validators, online
services, live provider tests, or live-service provider tests were invoked.

Verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - `1 test files, 2614 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `257 test files, 174959 assertions, 0 failures`

Lane status: adds one mapped PDF/Typst boundary provenance case; `phpFail`
remains `0`.
