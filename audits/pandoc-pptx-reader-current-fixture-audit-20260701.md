# Pandoc PPTX Reader Current Fixture Audit - 2026-07-01

Scope: native PHP `PortLibs\Pandoc\PptxReader` coverage for the upstream Pandoc PPTX reader golden fixture. PPTX writer package parity remains out of scope for this reader audit.

## Upstream Basis

- Upstream repository: `jgm/pandoc`.
- Fixture commit: `612e143fbe6d735b612c4800d21e61b7d44e4dca`.
- Reader test source: `test/Tests/Readers/Pptx.hs`.
- Fixture pair:
  - `test/pptx-reader/basic.pptx`
  - `test/pptx-reader/basic.native`

## Checked-In Fixture Evidence

- Local fixture directory: `lanes/pandoc/fixtures/upstream-current-pptx-reader`.
- `basic.pptx` SHA-256: `e48fd9c2f8369d1792197e301d5fea676bf6e51097a24af7d85831a6f96dc2dc`.
- `basic.native` SHA-256: `42804b9b1954094a4b0ff0be20084e2e6d9bc0a84272f34f7f219f82505da6b4`.

## Local Gate

`lanes/pandoc/tests/PptxReaderTest.php` now parses `basic.native` through `NativeReader`, parses `basic.pptx` through `PptxReader`, and compares a normalized Pandoc-reader content signature. The normalization excludes local package review sidecars, local native pretty-printer tokenization differences, and empty table-foot representation differences while preserving the golden document block tree, heading ids, text content, list structure, table content, image target/title, SmartArt classes, and SmartArt nested blocks.

Latest focused verification:

- `php -l lanes/pandoc/src/PptxReader.php`
- `php -l lanes/pandoc/tests/PptxReaderTest.php`
- `php -l lanes/pandoc/src/PandocFormatRegistry.php`
- `php tools/run-tests.php lanes/pandoc/tests/PptxReaderTest.php`: `149` assertions, `0` failures.
- `php tools/run-tests.php lanes/pandoc/tests/PptxReaderTest.php lanes/pandoc/tests/PptxWriterTest.php lanes/pandoc/tests/PandocFormatRegistryTest.php lanes/pandoc/tests/RichPackageUnsupportedFormatRegistryTest.php`: `986` assertions, `0` failures.
- `php tools/run-tests.php lanes/pandoc/tests/PptxReaderTest.php lanes/pandoc/tests/PptxWriterTest.php lanes/pandoc/tests/PandocFormatRegistryTest.php lanes/pandoc/tests/RichPackageUnsupportedFormatRegistryTest.php lanes/pandoc/tests/DocxWriterTest.php`: `1419` assertions, `0` failures.

This closes the current upstream PPTX reader golden fixture content gate. It does not claim full PPTX writer parity or full PowerPoint package round-trip parity.
