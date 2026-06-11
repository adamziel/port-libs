# Pandoc Shared ZIP/OPC Content-Type Byte Buckets 2026-06-11

Slice: `plib-v30qt`, shared ZIP/OPC package core blocker.

This slice extends the shared OPC ZIP entry manifest preflight with
`byteCountsByContentType`. Review queues can now inspect resolved OPC content
type byte totals before DOCX/EPUB/ODF handoff exposes package bytes, including:

- entry, compressed-byte, and uncompressed-byte totals;
- default versus override content-type source counts;
- contributing part names, OPC manifest roles, and handoff kinds.

The bucket is populated only for package parts with resolved content types, so
missing content-type parts remain explicit in existing missing-content-type
diagnostics rather than being hidden in a generic binary bucket.

Verification:

- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - 1 test file, 4066 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 63892 assertions, 0 failures

No Pandoc binary, office suite, zip/unzip tool, Cabal/Haskell runner, browser
renderer, external validator, online service, live-provider test, or
live-service provider test was invoked.
