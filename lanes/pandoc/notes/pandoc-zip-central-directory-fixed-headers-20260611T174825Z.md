# Pandoc ZIP Central Directory Fixed Headers 20260611T174825Z

## Scope

- Bead: plib-6vwn3, Pandoc shared ZIP/OPC package core blocker slice.
- Current base: 0f7efc602c.
- Change: `ZipPackage::centralDirectoryFixedHeaderPreflight()` now exposes raw
  central-directory fixed-header field provenance before local-header
  validation or package construction.
- Handoff behavior: raw and instantiated strict import preflight summaries now
  include `centralDirectoryFixedHeaders` with version made-by/needed fields,
  flags, method, DOS timestamp words, CRC and size values, attributes,
  local-header offsets, and aggregate method/flag counters.

## Verification

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` passed
  1 file / 3203 assertions / 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed 44 files / 64575
  assertions / 0 failures.

No Pandoc binary, office suite, zip/unzip command, browser renderer, external
validator, online service, live provider test, or live-service provider test
was executed.
