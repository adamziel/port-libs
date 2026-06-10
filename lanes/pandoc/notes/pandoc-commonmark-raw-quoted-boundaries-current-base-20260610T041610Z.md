# Pandoc CommonMark Raw HTML Quoted Boundaries

## Scope

- Integrated the `plib-d9wb` raw HTML boundary behavior on top of the current
  CommonMark paragraph-interrupt implementation.
- Raw HTML block detection now treats `>` inside quoted attributes as part of
  the tag, not as the tag close.
- Malformed quoted tag starts remain parsed Markdown paragraphs instead of
  creating raw HTML blocks.
- The quote-aware matching is shared across blank-line raw tags, known raw
  container tags, table tags, void tags, self-closing checks, and paragraph
  interruption.

## Verification

- `php -l lanes/pandoc/src/MarkdownReader.php`
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - 1 test file, 6482 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 test files, 58663 assertions, 0 failures

No Pandoc executable, Cabal solver/build/test command, Haskell runner, browser
renderer, office suite, external validator, online service, live provider test,
or live-service provider test was executed.

## Accounting

- `phpPass` moves from 2923 to 2924.
- `phpFail` remains 0.
- `suiteProgress` moves from 826 to 827 focused mapped checks.
- The mapped denominator moves from 3105 to 3106 with one focused CommonMark
  raw HTML quoted-boundary pass case.
