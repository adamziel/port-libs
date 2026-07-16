# ODF Configuration Sidecar Byte Policy Parity - 2026-07-01

Bead: `plib-51dc5`

This slice closes a compact/rich ODF package-ingestion parity gap for
`Configurations2/` package sidecars. Compact `OpenDocumentPackage` configuration
review items now keep configuration bytes blocked the same way as `OdfReader`:

- `byteLength` is `null`
- `crc32` is `null`
- `canExposeBytes` is `false`
- `readableCount` remains `0`
- `storedByteLength` and `storedCrc32` remain available as metadata-only package
  review fields

`OdfReader` now also reports the aggregate `readableCount` for configuration
package metadata so compact and rich summaries can both state that no
configuration sidecar bytes are readable.

Focused validation:

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php -l lanes/pandoc/tests/OdfCompactConfigurationBytePolicyParityTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfCompactConfigurationBytePolicyParityTest.php`
  - 1 file, 42 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - 1 file, 2248 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - 1 file, 5293 assertions, 0 failures

No Pandoc, office suite, TeX/browser engine, zip/unzip command, Node tooling, or
external validator was invoked. Direct-format parity remains active for the
broader Pandoc lane.
