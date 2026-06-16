# Pandoc Markdown Profile Attribute Extension Harvest

## Scope

This slice targets Markdown/CommonMark/GFM profile dialect extension handling for
attribute-bearing inline and list fixtures. It does not overlap the active Q/R
citation and remaining-reader harvests, nor the landed N/O/P raw/code,
attribute-profile, and extended-inline harvests.

Inventory at start:

- Active P0 Markdown wrap-up workers: `plib-cs8zq` open for writer citation key
  harvest Q, `plib-8myyw` hooked for reader remaining upstream harvest R,
  `plib-gcu6u` hooked for profile dialect upstream harvest S, and
  `plib-w2090` in progress for reader inline/link/entity completion.
- Earlier N/O/P profile-adjacent slices are closed on `origin/main`, including
  fenced raw/code profile overrides, block attribute profile gating, and
  extended inline fallback coverage.
- `gt mq list port_libs --ready --json` returned `null`; no ready P0 merge MR
  was visible to avoid.

Upstream source inspected: `jgm/pandoc`
`850c7b9613aa7beb1aed8c2c1f7abb01eaf3023e`, specifically
`Text.Pandoc.Extensions`, `Text.Pandoc.Readers.Markdown`, and
`Text.Pandoc.Writers.Markdown(.Inline)` extension gates for
`inline_code_attributes`, `link_attributes`, `attributes`, and
`example_lists`.

## Implementation

- `MarkdownReader` now recognizes upstream-style extension aliases for
  `inline_code_attributes`, `link_attributes`, broad `attributes`, and
  `numbered_example_list(s)`.
- Reader code spans use `inline_code_attributes`; links, images, and autolinks
  use `link_attributes`; broad `attributes` can enable both families for
  CommonMark/GFM profile requests.
- PHP Extra defaults now enable link attributes without enabling inline code
  attributes; MultiMarkdown defaults keep inline code/link attribute tuples
  disabled unless requested.
- `MarkdownWriter` keeps `attributes` as a distinct broad enabling switch
  instead of folding it into bracketed spans, and uses it as an upstream-style
  fallback for code, link, heading, and fenced-code attribute tuple emission.
- `MarkdownWriter` recognizes `numbered_example`, `numbered_examples`, and
  numbered-example-list aliases for writer example-list output.
- `markdown_strict`, `markdown_phpextra`, and `markdown_mmd` writer defaults
  now disable inline code attribute tuple syntax unless the extension is
  explicitly enabled; `markdown_mmd` also disables link attribute tuples by
  default.

No Pandoc, cmark/commonmark runner, Cabal/Haskell runner, Node tooling, browser
renderer, external validator, online service, live provider test, or
live-service provider test was invoked.

## Verification

- `php -l lanes/pandoc/src/MarkdownReader.php`
- `php -l lanes/pandoc/src/MarkdownWriter.php`
- `php -l lanes/pandoc/tests/MarkdownProfileAttributeExtensionHarvestTest.php`
- `php -l lanes/pandoc/tests/MarkdownReaderFlavorProfileSurgeTest.php`
- `php -l lanes/pandoc/tests/MarkdownReaderLineBreakProfileSurgeTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownProfileAttributeExtensionHarvestTest.php`
  - 1 file, 354 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderFlavorProfileSurgeTest.php`
  - 1 file, 716 assertions, 0 failures
- Affected Markdown profile cluster:
  - 11 files, 3483 assertions, 0 failures
- Profile alias regression set:
  - 3 files, 1346 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 189 files, 168810 assertions, 0 failures
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check`
- Conflict-marker scan

## Accounting

- `phpPass`: `15872 -> 15977`
- `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` `upstream.mapped`: `15527 -> 15632`
- `UPSTREAM_TEST_MANIFEST.json` root `mapped`: `15532 -> 15637`
- `mappedMarkdownProfileAttributeExtensionHarvestCases`: `105`
- `markdownProfileAttributeExtensionHarvestAssertions`: `354`
