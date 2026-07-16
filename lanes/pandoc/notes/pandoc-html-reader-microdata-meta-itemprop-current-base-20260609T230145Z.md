# Pandoc HTML Reader Microdata Meta Itemprop

Implemented one bounded native PHP HTML reader microdata metadata slice for
hidden `<meta itemprop="..." content="...">` scalar values.

## Behavior

- `Html5DomFragment` now converts valid `meta[itemprop][content]` nodes into
  inert reviewer spans with `data-pandoc-microdata-property`,
  `data-pandoc-microdata-value`, and `data-pandoc-microdata-source="meta"`.
- The generated metadata nodes feed the existing scoped microdata item summary,
  so hidden scalar content contributes to property and value counts.
- Malformed `itemprop` tokens and empty `content` values remain diagnostic-only;
  source `<meta>`, `itemprop`, and `content` attributes are not emitted into
  WordPress blocks.

This slice does not invoke Pandoc, Cabal/Haskell runners, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests.

## Verification

- `php -l lanes/pandoc/src/Html5DomFragment.php`
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: 1 test file, 2537 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: 42 test files, 57912 assertions, 0 failures.

Status delta after rebasing onto `f5e0b56fc`: `phpPass` moves from `2874` to
`2875`; mapped focused checks move from `777` to `778`.
`UPSTREAM_TEST_MANIFEST.json` mapped denominator moves from `3078` to `3079`.
