# Pandoc JSON/native textual raw TeX-family constructors

Slice: `pandoc-json-native-textual-raw-tex-family`

This bounded JSON/native AST constructor-completeness slice aligns textual
`NativeReader` raw-format classification with the Pandoc JSON reader for
TeX-family raw constructors. Textual native `RawBlock`/`RawInline` values with
`Format "latex"`, `Format "context"`, and `Format "tex+..."` now map to
`raw_tex`/`raw_tex_inline` AST nodes instead of generic raw nodes, while keeping
the original format string for `PandocJsonWriter` and `NativeWriter` output.

This does not invoke Pandoc, TeX engines, browser renderers, JSON filters,
external validators, online services, live provider tests, or live-service
provider tests.

Verification:

- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/tests/NativeReaderEscapeTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderEscapeTest.php`
  - Result: `1 test files, 24 assertions, 0 failures`

Accounting:

- `phpPass`: `469 -> 470`
- `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` `upstream.mapped`: `2311 -> 2312`
- `mappedJsonNativeTextualRawTexFamilyCases`: `1`
