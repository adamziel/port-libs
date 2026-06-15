# Pandoc Markdown Generic Raw HTML List Items

## Scope

- `MarkdownReader` now lets Markdown list items whose first content starts with
  any recognized raw HTML block reuse the same native raw-block parser used at
  top level.
- The focused fixture preserves section and figure raw HTML blocks inside a
  single list item, keeps Markdown-looking source text raw, and hands the
  reviewer markup through `WordPressBlockWriter` without escaping it as
  paragraph text.

## Accounting

- `phpPass`: `3711 -> 3712`.
- `phpFail`: `0`.
- `UPSTREAM_TEST_MANIFEST.json` `.upstream.mapped`: `3734 -> 3735`.
- `mappedMarkdownGenericRawHtmlListItemCases`: `1`.
- `markdownGenericRawHtmlListItemAssertions`: `15`.

## Verification

- `php -l lanes/pandoc/src/MarkdownReader.php`
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - `1 test files, 6793 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 87914 assertions, 0 failures`

No Pandoc executable, Cabal/Haskell runner, browser renderer, external
validator, online service, live provider test, or live-service provider test
was invoked.
