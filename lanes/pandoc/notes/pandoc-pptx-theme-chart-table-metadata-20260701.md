# PPTX theme, chart, and table metadata

Bead: `plib-et1cm`

## Scope

- Added bounded theme format-scheme review metadata, including fill, line,
  effect, and background fill style summaries.
- Added chart-level display metadata for chart style ids, rounded corners,
  legends, visible-only plotting, blank-display policy, and data-label overflow.
- Added advanced table border line metadata for DrawingML joins and line-end
  decorations without rendering or fetching external resources.

## Verification

- `php -l lanes/pandoc/src/PptxReader.php`
- `php -l lanes/pandoc/tests/PptxReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PptxReaderTest.php`
  - `1 test files, 185 assertions, 0 failures`

No Pandoc binary, office suite, TeX runner, browser renderer, Node tooling,
external validator, zip/unzip command, online service, live provider test, or
payload-expanding external tool was invoked.
