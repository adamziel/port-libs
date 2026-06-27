# Pandoc JSON/native text raw markdown formats

Slice: `plib-lglho`
Area: Pandoc JSON/native AST constructor completeness.

Implemented one bounded constructor-completeness case for raw format nodes
created by the native text reader. `NativeReader` text input now classifies
markdown-family `RawBlock`/`RawInline` formats such as `markdown` and
`gfm+raw_html` as shared `raw_markdown` AST nodes, matching the Pandoc JSON
reader path. The same text-reader branch also accepts TeX-family aliases such as
`latex` and `context` for raw TeX nodes instead of only literal `tex`.

Validation:

- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- Targeted `PandocJsonNativeAstTest.php` regression runner for the new case
- Direct PHP smoke over `NativeReader` text input to `PandocJsonWriter` and
  `NativeWriter` RawBlock/RawInline output

Result:

- New regression `serializes native text markdown raw format constructors through pandoc json writers`: PASS
- Targeted regression runner: 17 assertions, 0 failures
- Direct smoke: `native text raw markdown constructors ok`
- Full `PandocJsonNativeAstTest.php` is not used as a green gate because the
  file remains blocked by unrelated baseline failures documented in lane status.

Accounting:

- `lane-status.json` `phpPass`: `453 -> 454`
- `UPSTREAM_TEST_MANIFEST.json` focused mapped behavior checks: `2302 -> 2303`
- `mappedJsonNativeTextRawFormatCases`: `1`

No Pandoc binary, Markdown engine, TeX engine, Haskell/Cabal runner, browser
renderer, external validator, online service, live provider test, or
live-service provider test was used.
