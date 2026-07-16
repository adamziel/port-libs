# Pandoc Markdown GFM Emoji Alias Surge

Scope: native PHP Markdown/CommonMark/GFM emoji shortcode completion after rebase onto current main 2ea3da99f7. No Pandoc, cmark/commonmark runners, Cabal/Haskell runners, browser renderers, Node tooling, online services, live provider tests, live-service provider tests, or external validators were invoked.

- Preserved the already-landed 123 Markdown emoji aliases from current main.
- Added 41 non-overlapping GFM/Pandoc-style emoji shortcodes to `MarkdownEmojiAliases`, bringing the table to 164 aliases.
- Added `MarkdownReaderEmojiSurgeTest.php` for reader, writer, and WordPress handoff coverage for those 41 aliases.
- `phpPass`: `6044 -> 6085`; `phpFail`: `0`.
- `UPSTREAM_TEST_MANIFEST.json` mapped cases: `6034 -> 6075`.
- `mappedMarkdownEmojiExtensionSurgeCases`: `102 -> 143`.
- `markdownEmojiExtensionSurgeAssertions`: `715 -> 1045`.

Verification:

- `php -l lanes/pandoc/src/MarkdownEmojiAliases.php`
- `php -l lanes/pandoc/tests/MarkdownReaderEmojiSurgeTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderEmojiSurgeTest.php` passed: 1 file, 330 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderEmojiSurgeTest.php lanes/pandoc/tests/MarkdownReaderEmojiExtensionSurgeTest.php lanes/pandoc/tests/MarkdownReaderTest.php` passed: 3 files, 8064 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 73 files, 104271 assertions, 0 failures.
