# Pandoc HTML document microdata metadata current-base slice

2026-06-10 UTC

- Added native MarkdownReader coverage for full HTML documents that carry top-level Microdata items.
- The reader now records sanitized item types, item ids, scalar properties, nested item records, and property/value summary counts in `meta.microdata`.
- WordPress block output remains sanitized HTML block markup; Microdata review data stays in document metadata.

Validation:

- `php -l lanes/pandoc/src/MarkdownReader.php`
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - 1 test file, 6531 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 43 test files, 59121 assertions, 0 failures

External tools not run: Pandoc, Cabal/Haskell runners, browser renderers, external validators, online services, live provider tests, or live-service provider tests.
