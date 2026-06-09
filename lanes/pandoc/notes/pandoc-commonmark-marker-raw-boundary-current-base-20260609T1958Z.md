# Pandoc CommonMark Marker Raw Boundary Current Base

## Scope

- Added native `MarkdownReader` handling for CommonMark raw HTML processing
  instructions, declarations, and CDATA sections.
- These raw block forms now stay as marker-bounded `raw_html` AST blocks:
  `<?` through `?>`, `<!` plus an uppercase declaration name through `>`, and
  `<![CDATA[` through `]]>`.
- The slice keeps following Markdown blocks on the native reader path after the
  raw marker closes and hands the raw source to WordPress HTML blocks without
  invoking external converters or validators.

## Verification

- `php -l lanes/pandoc/src/MarkdownReader.php`
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - 1 test file, 6260 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtml5DomTest.php`
  - 1 test file, 65 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 test files, 57077 assertions, 0 failures

No Pandoc executable, Cabal solver/build/test command, Haskell runner, office
suite, TeX/PDF engine, browser renderer, zip/unzip, Jupyter, Node tooling,
external validator, online service, live provider test, or live-service
provider test was executed.

## Accounting

- `phpPass` moves from 2830 to 2831 after adding the focused CommonMark
  marker-bounded raw HTML boundary case.
- `phpFail` remains 0.
- `suiteProgress` moves from 733 to 734 with one focused Markdown/CommonMark
  reader pass case.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator moves from 3045 to 3046.
