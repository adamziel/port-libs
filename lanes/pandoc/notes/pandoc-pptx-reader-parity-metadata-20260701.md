# PPTX Reader Parity Metadata

Bead: `plib-et1cm`

Slice: `pandoc-pptx-reader-parity-metadata`

## Scope

- Deepened slide layout/master placeholder inheritance by tagging inherited
  placeholder blocks with source bucket, source part, selected lookup key, and
  all candidate lookup keys.
- Deepened chart cache metadata without exposing embedded workbook bytes:
  cached series now preserve formula references, declared point counts, and
  cache point indexes alongside existing category/value text.
- Deepened table styling/theme parity by preserving DrawingML luminance color
  transforms on table fills/borders and applying those transforms when resolving
  theme colors for cell styling review metadata.

## Verification

- `php -l lanes/pandoc/src/PptxReader.php`
- `php -l lanes/pandoc/tests/PptxReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PptxReaderTest.php`
  - `1 test files, 152 assertions, 0 failures`

No Pandoc binary, office suite, TeX runner, browser renderer, Node tooling,
external validator, zip/unzip command, online service, live provider test, or
payload-expanding external tool was invoked.
