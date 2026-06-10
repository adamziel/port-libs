# pandoc-json-native-null-block-current-base-20260610T151401Z

Slice: `plib-a5ov`, Pandoc JSON/native AST constructor completeness.

This slice closes the remaining core block constructor gap for Pandoc `Null`
blocks. `NativeReader`, `NativeWriter`, `PandocJsonReader`, and
`PandocJsonWriter` now map `Null` through the shared `null_block` AST node so
native JSON and Pandoc JSON packets can round-trip the constructor instead of
falling back to opaque native block handling.

Focused coverage extends the existing native AST and Pandoc JSON core block
constructor tests with imported and generated `Null` packets. The assertions
verify the emitted constructor tag and the read-back shared AST type.

Verification:

- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTest.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - 2 test files, 634 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 60434 assertions, 0 failures

This does not run Pandoc, JSON filters, office suites, TeX/PDF engines, browser
renderers, zip/unzip, Jupyter/Node tooling, external validators, online
services, live provider tests, or live-service provider tests. It is limited to
bounded native PHP constructor mapping under `lanes/pandoc`.
