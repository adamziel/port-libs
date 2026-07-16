# Pandoc JSON/native Attr tuple provenance

Slice: plib-w0yr1, 2026-06-11T16:45:49Z
Base: origin/main ac1f74a84c2791cf3746a18ac25b8c57855cb209

This slice keeps Pandoc JSON/native AST constructor work native to PHP while
recording source `Attr` tuple provenance on attributed block, inline, and table
helper nodes.

## Coverage

- `PandocJsonReader` records `attrConstructor = Attr` and `attrNative` for
  attributed constructors.
- `NativeReader` mirrors the same provenance for native JSON packets.
- Empty table sections do not become semantically present solely because of
  provenance metadata.
- `NativeWriter` ignores `attrConstructor`/`attrNative` during native-payload
  reuse comparison so unchanged tagged native payload reuse remains stable.

## Verification

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - 1 test files, 835 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 64089 assertions, 0 failures
