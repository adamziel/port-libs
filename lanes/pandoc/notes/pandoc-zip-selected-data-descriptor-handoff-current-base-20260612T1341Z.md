# Pandoc ZIP Selected Data Descriptor Handoff Slice

Scope: shared ZIP/OPC package handoff now preserves selected-entry data-descriptor provenance for native PHP DOCX/EPUB/ODF package review queues.

Implementation:

- Extended `ZipPackage::entryHandoffPreflight()` with per-selected-entry data-descriptor metadata:
  descriptor usage, signed/unsigned marker state, descriptor offsets, value offsets, length, next offset, span, end, surplus/truncated byte counters, CRC32, compressed/uncompressed sizes, ZIP64-sized descriptor flag, local-header CRC/size placeholders, and zero-placeholder state.
- Added selected aggregate counters for descriptor entries, signed/unsigned descriptors, ZIP64-sized descriptor flags, and zero local-header placeholders.
- Kept existing `compressedSize`, `uncompressedSize`, `crc32`, and `crc32Hex` fields unchanged by namespacing the new descriptor values under `dataDescriptor*`.
- Added one focused `ZipPackageTest.php` case covering normal selected entries, signed deflated descriptors, unsigned stored descriptors, optional missing requests, aggregate counters, and the `selectedDataDescriptorProvenanceEntries` review list.

Accounting:

- `phpPass`: `3219 -> 3220`
- `mappedZipSelectedDataDescriptorHandoffCases`: `1`
- `zipSelectedDataDescriptorHandoffAssertions`: `57`

Verification after final rebase:

```bash
php -l lanes/pandoc/src/ZipPackage.php
php -l lanes/pandoc/tests/ZipPackageTest.php
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
php tools/run-tests.php lanes/pandoc/tests
```

Focused result: `1 test files, 4143 assertions, 0 failures`.

Full result: `44 test files, 71550 assertions, 0 failures`.

No Pandoc, office suites, TeX/browser engines, zip/unzip, `ZipArchive`, external validators, online services, live provider tests, or live-service provider tests were invoked.
