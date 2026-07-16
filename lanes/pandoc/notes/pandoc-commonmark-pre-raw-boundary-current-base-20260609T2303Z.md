# Pandoc CommonMark Pre Raw Boundary Current Base

## Scope

- Added native `MarkdownReader` handling for CommonMark type-1 `<pre>` raw
  HTML blocks when the content is not a structured `<pre><code>` code block.
- The raw `<pre>` source is preserved until the closing `</pre>` marker, so
  blank lines and Markdown-looking headings inside the block stay raw while
  following Markdown resumes on the native reader path.
- Existing structured `<pre><code>` imports still become native code blocks.

## Verification

- `php -l lanes/pandoc/src/MarkdownReader.php`
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - 1 test file, 6324 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 test files, 57807 assertions, 0 failures

No Pandoc executable, Cabal solver/build/test command, Haskell runner, office
suite, TeX/PDF engine, browser renderer, zip/unzip, Jupyter, Node tooling,
external validator, online service, live provider test, or live-service
provider test was executed.

## Accounting

- `phpPass` moves from 2870 to 2871 after adding the focused CommonMark
  `<pre>` raw boundary case.
- `phpFail` remains 0.
- `suiteProgress` moves from 773 to 774 with one focused Markdown/CommonMark
  reader pass case.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator moves from 3075 to 3076.
