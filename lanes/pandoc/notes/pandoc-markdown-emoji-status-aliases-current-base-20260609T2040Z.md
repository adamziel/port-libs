# Pandoc Markdown emoji status aliases current-base slice

Date: 2026-06-09 UTC
Bead: plib-m8ub

## Scope

- Expanded the native PHP Markdown emoji alias table with bounded review/status shortcodes:
  `heavy_check_mark`, `information_source`, `bug`, `fire`, `sparkles`, and `zap`.
- Added focused Markdown reader/writer/WordPress handoff coverage proving known aliases become emoji spans with
  `data-emoji` metadata, Markdown output round-trips back to shortcodes, WordPress output preserves safe span
  metadata, and unknown aliases remain literal text.
- This deepens the existing upstream Markdown emoji extension row; it does not claim a new direct upstream
  denominator row.

## Verification

- `php -l lanes/pandoc/src/MarkdownEmojiAliases.php`
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file / 6504 assertions / 0 failures.
- Post-rebase `php tools/run-tests.php lanes/pandoc/tests` passed 42 files / 58694 assertions / 0 failures.

No Pandoc, Cabal/Haskell runners, office suites, TeX/PDF engines, browser renderers, Node tooling, zip/unzip,
external validators, asset fetchers, or online services were invoked.
