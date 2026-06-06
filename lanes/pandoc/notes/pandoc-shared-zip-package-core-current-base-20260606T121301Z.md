# pandoc-shared-zip-package-core-current-base-20260606T121301Z

## Slice

- Lane: `pandoc`
- Micro-slice: `pandoc-shared-zip-package-core-current-base-20260606T121301Z`
- Accepted base: `259f1bb48b87b09ee9889b2d9331db2eb82715fb`
- Upstream contract: bounded native ZIP/OPC package primitive support for
  local-file-header byte ranges and data-descriptor spans. No Pandoc, Cabal,
  Haskell runner, Word, LibreOffice, zip/unzip, ZipArchive, external archive
  tool, online service, live provider test, or live-service provider test was
  used.

## Behavior

DOCX, EPUB, ODT, and OPC readers need to distinguish the byte range occupied by
each local file header, compressed payload, optional data descriptor, and the
central directory boundary before exposing package parts for conversion.

`ZipPackage::localHeaderPreflight()` now reports entry-local layout metadata:
local header offsets and lengths, local name and extra-field lengths, data
starts, compressed data ends, data-descriptor offsets and lengths, record-end
contiguity, local-header placeholder CRC/size fields, and the central-directory
offset. `strictImportPreflight()` includes the same local-header summary so
callers can audit package layout together with existing path, extra-field,
descriptor, permission, and read-integrity checks.

The WordPress ZIP package preflight smoke now asserts that package local-header
spans are visible and that the final local record ends at the central directory
without invoking any external archive reader.

## Verification Evidence

Baseline before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
1 test files, 775 assertions, 0 failures
```

After implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
1 test files, 819 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test
zip package writer preflight self-test passed
```

Focused delta: `+1` PHP PASS case and `+44` net focused assertions for the ZIP
package support row.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `ZipPackage`
local-header parsing, data-descriptor parsing, strict import preflight, and
in-memory package fixtures.

The full upstream Pandoc runner blocker remains unchanged: hydrate the pinned
Pandoc checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with Cabal
project/package files and Haskell Tasty runner dependencies before attempting
runner parity.

## Non-Overlap

This patch does not repeat accepted ZIP central/local extra-field timestamp
handling, NTFS timestamp conflict rejection, ZIP64 extra-field rejection, Unix
symlink rejection, drive-letter path rejection, central-directory digital
signature provenance, raw-deflate trailing-byte validation, or
Unicode-normalized case-insensitive entry-name collision checks. It owns only
local-file-header byte span, descriptor span, record-end, and central-directory
contiguity reporting for package preflight.

## Follow-Up

Keep ZIP64 large archive support, encrypted/AES entries, non-store/deflate
compression methods, split archives, cryptographic signature verification, and
broader reader strict-preflight wiring as separate bounded slices.
