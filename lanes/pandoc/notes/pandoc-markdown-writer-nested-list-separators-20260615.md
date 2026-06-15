# Pandoc Markdown Writer Nested List Separators 2026-06-15

## Status Delta

- `MarkdownWriter` now applies the existing list/code separator rule inside nested block collections, not only at the top document level.
- This keeps adjacent nested bullet lists, matching ordered lists, and following code blocks distinct inside fenced divs and note definitions.
- `phpPass`: `3731 -> 3732` after rebase onto current main `051fc37f84`.
- `phpFail`: remains `0`.
- `UPSTREAM_TEST_MANIFEST.json` `upstream.mapped`: `3749 -> 3750`.
- Added `mappedMarkdownWriterNestedListSeparatorCases = 1`.
- Added `markdownWriterNestedListSeparatorAssertions = 3`.

## Focused Evidence

- `php -l lanes/pandoc/src/MarkdownWriter.php`
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
- Result: `1 test files, 6781 assertions, 0 failures`.

## Rebased Gate

- Full `php tools/run-tests.php lanes/pandoc/tests` passed after rebase: `46 test files, 88546 assertions, 0 failures`.
- PHP JSON status/manifest validation, `git diff --check`, and conflict-marker scan passed.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
Markdown writer block renderer and separator policy. It does not invoke Pandoc,
Cabal/Haskell runners, browser renderers, external validators, online services,
live provider tests, or live-service provider tests.
