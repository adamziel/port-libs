# Shared ZIP/OPC central manifest ZIP64 byte sentinels 20260612T020828Z

Slice: `plib-chu3j`

## Change

- `OpcRelationshipGraph::preflightZipCentralDirectoryManifest()` now preserves
  per-entry central-directory size sentinel issues from
  `ZipPackage::centralDirectorySizePreflight()`.
- Raw central-directory OPC manifests no longer treat ZIP64
  `0xffffffff` compressed or uncompressed size placeholders as exact payload
  bytes in OPC role and handoff byte buckets.
- The manifest now exposes exact-byte status, unknown-byte entry count, and
  named unknown-byte entries while keeping declared central-directory sizes
  available on each entry for review.

## Verification

- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests`

No Pandoc, office suites, `zip`/`unzip`, browser renderers, external
validators, online services, live provider tests, or live-service provider tests
were invoked.
