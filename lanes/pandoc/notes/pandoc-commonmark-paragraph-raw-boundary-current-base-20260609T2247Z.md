# Pandoc CommonMark Paragraph Raw Boundary Current Base

## Scope

- Added native `MarkdownReader` handling for CommonMark raw HTML block starts
  that interrupt an already-open paragraph.
- Paragraph text before an interrupting raw block is flushed as a native
  paragraph, while `section` and `script` raw source is preserved as
  `raw_html` for WordPress HTML-block handoff.
- Generic custom tag-line raw HTML remains on the existing non-interrupting
  path; this slice is limited to CommonMark paragraph-interrupting raw block
  starts and `script`/`pre`/`style` closing-tag boundaries.

## Verification

- `php -l lanes/pandoc/src/MarkdownReader.php`
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - 1 test file, 6364 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 test files, 58094 assertions, 0 failures

No Pandoc executable, Cabal solver/build/test command, Haskell runner, office
suite, TeX/PDF engine, browser renderer, zip/unzip, Jupyter, Node tooling,
external validator, online service, live provider test, or live-service
provider test was executed.

## Accounting

- `phpPass` moves from 2886 to 2887 after adding the focused CommonMark
  paragraph-interrupting raw HTML boundary case.
- `phpFail` remains 0.
- `suiteProgress` moves from 789 to 790 with one focused Markdown/CommonMark
  reader pass case.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator moves from 3084 to 3085.
