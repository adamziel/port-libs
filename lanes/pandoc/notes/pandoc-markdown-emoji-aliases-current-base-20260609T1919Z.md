# Pandoc Markdown Emoji Aliases Slice

## Summary

`MarkdownReader` now resolves a bounded native set of Pandoc/GitHub-style emoji aliases beyond the already accepted `:smile:` and `:+1:` pair, including `:heart:`, `:warning:`, `:rocket:`, `:white_check_mark:`, and `:-1:`. The alias table lives in `MarkdownEmojiAliases` so reader and writer behavior stays aligned.

`MarkdownWriter` now emits matching emoji spans back as `:alias:` shortcodes when the `data-emoji` alias and glyph agree. WordPress output continues to preserve emoji spans with `class="emoji"` and `data-emoji` metadata, while unknown aliases remain literal source text.

## Scope

This deepens the already mapped upstream emoji extension row recorded in `upstream-inventory.md` for `:smile: and :+1:` rather than claiming a new upstream denominator row. Lane status moves by one focused PHP pass only:

- `phpPass`: `2824 -> 2825`
- focused checks: `727 -> 728`
- `phpFail`: `0`
- mapped denominator: unchanged

## Verification

- `php -l lanes/pandoc/src/MarkdownEmojiAliases.php`
- `php -l lanes/pandoc/src/MarkdownReader.php`
- `php -l lanes/pandoc/src/MarkdownWriter.php`
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` -> `1 test files, 6219 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests` -> `42 test files, 56988 assertions, 0 failures`

No Pandoc binary, Cabal/Haskell runner, browser renderer, asset fetcher, online service, live provider test, or external validator was invoked.
