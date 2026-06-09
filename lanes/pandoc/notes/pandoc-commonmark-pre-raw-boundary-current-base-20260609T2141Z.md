# Pandoc CommonMark Pre Raw Boundary No-Blank-Line Regression

## Scope

- Current main already includes native `MarkdownReader` handling for non-code
  CommonMark `<pre>` raw HTML blocks that terminate at the matching `</pre>`
  closing tag.
- This rebased slice adds the tighter regression where a Markdown heading
  follows `</pre>` immediately, with no blank-line separator.
- The accepted behavior keeps the raw source as a `raw_html` AST block while
  resuming native Markdown parsing for the following heading and paragraph.
- The existing structured `<pre><code>` import path remains covered by the
  broader current-main test and continues to produce native code blocks.

## Verification

- `php -l lanes/pandoc/src/MarkdownReader.php`
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
- `jq empty lanes/pandoc/lane-status.json`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - 1 test file, 6434 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 test files, 58432 assertions, 0 failures

No Pandoc executable, Cabal solver/build/test command, Haskell runner, office
suite, TeX/PDF engine, browser renderer, zip/unzip, Jupyter, Node tooling,
external validator, online service, live provider test, or live-service
provider test was executed.

## Accounting

- `phpPass` moves from 2907 to 2908 after adding the focused CommonMark
  `<pre>` no-blank-line raw-boundary regression case.
- `phpFail` remains 0.
- `suiteProgress` moves from 810 to 811 with one focused Markdown/CommonMark
  reader pass case.
