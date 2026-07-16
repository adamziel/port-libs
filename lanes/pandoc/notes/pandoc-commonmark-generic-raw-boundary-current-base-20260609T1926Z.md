# Pandoc CommonMark Generic Raw Boundary Current Base

## Scope

- Added native `MarkdownReader` handling for CommonMark generic raw HTML tag
  lines that are not already owned by structured HTML/XML readers.
- Generic open, self-closing, and closing tag lines such as `span` and custom
  review wrappers now become blank-line-bounded `raw_html` AST blocks.
- The slice keeps inline anchor HTML as inline raw HTML and leaves unsafe
  DocBook `informaltable` XML fallbacks on the existing safe XML/paragraph path.

## Verification

- `php -l lanes/pandoc/src/MarkdownReader.php`
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - 1 test file, 6231 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtml5DomTest.php`
  - 1 test file, 65 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 test files, 57001 assertions, 0 failures

No Pandoc executable, Cabal solver/build/test command, Haskell runner, office
suite, TeX/PDF engine, browser renderer, zip/unzip, Jupyter, Node tooling,
external validator, online service, live provider test, or live-service
provider test was executed.

## Accounting

- `phpPass` moves from 2826 to 2827 after adding the focused CommonMark generic
  raw tag-line boundary case.
- `phpFail` remains 0.
- `suiteProgress` moves from 729 to 730 with one focused Markdown/CommonMark
  reader pass case.
