# Pandoc CommonMark Raw Boundary Current Base

## Scope

- Added native `MarkdownReader` handling for CommonMark block-level raw HTML
  tags that terminate at the next blank line.
- The blank-line boundary keeps the raw source as a `raw_html` AST block while
  allowing following Markdown headings and paragraphs to parse as native blocks.
- The focused WordPress handoff assertion verifies raw HTML remains in HTML
  blocks and the following heading is emitted as ordinary heading markup.

## Verification

- `php -l lanes/pandoc/src/MarkdownReader.php`
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - 1 test file, 6189 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 test files, 56846 assertions, 0 failures

No Pandoc executable, Cabal solver/build/test command, Haskell runner, office
suite, TeX/PDF engine, browser renderer, zip/unzip, Jupyter, Node tooling,
external validator, online service, live provider test, or live-service
provider test was executed.

## Accounting

- `phpPass` moves from 2817 to 2818 after adding the focused CommonMark raw
  boundary case.
- `phpFail` remains 0.
- `suiteProgress` moves from 720 to 721 with one focused Markdown/CommonMark
  reader pass case.
