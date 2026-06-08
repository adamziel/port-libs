# Doctemplates Core Current Base - Unicode Diagnostic Columns

Slice: `pandoc-doctemplates-core-current-base-20260608T224250Z`

Accepted base: `fb68aedd3080f5c5d86cf57108d39e4c2a7b6359`

## Source Truth

Upstream doctemplates parses templates with Parsec and reports source positions through `sourceColumn`, so diagnostic columns are character positions in the input text rather than UTF-8 byte offsets.

Reference: https://raw.githubusercontent.com/jgm/doctemplates/master/src/Text/DocTemplates/Parser.hs

## Implementation

- `DocTemplate::sourceLocation()` now keeps the existing CR/LF/CRLF line handling while computing columns from the current line prefix as Unicode characters.
- Invalid UTF-8 diagnostics keep the previous byte-count fallback, so malformed source still produces bounded native errors instead of failing inside the location helper.
- The WordPress doctemplate review-packet self-test now covers a Unicode-prefix unsupported-pipe diagnostic.

## Focused Evidence

- `php -l lanes/pandoc/src/DocTemplate.php` passed.
- `php -l lanes/pandoc/tests/DocTemplateTest.php` passed.
- `php -l lanes/pandoc/examples/wordpress-doctemplate-review-packet.php` passed.
- `php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php` passed: `1 test files, 1076 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-doctemplate-review-packet.php --self-test` passed: `OK wordpress doctemplate review packet`.
- JSON metadata validation passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed with no output.

Root harness not run - isolated micro-slice.

## Non-Overlap

This slice only changes doctemplate source-position diagnostics. It does not repeat the accepted doctemplate map-pairs, applied partial rebinding, breakable-space wrapping, braced separator, default Markdown/CommonMark/Beamer/man/ms fallback, parameterized pipe, or nesting coverage.

## Dependency Closure

No new support component is needed. This reuses the native PHP doctemplate parser and renderer plus the existing lane-local WordPress review-packet smoke.

No Pandoc, Cabal solver/build/test command, Haskell runner, Stack, external template engine, browser renderer, online service, live provider test, or live-service provider test was executed. Full upstream runner parity remains gated on hydrating the pinned Pandoc/doctemplates checkout and recording a reviewed non-mutating runner plan.
