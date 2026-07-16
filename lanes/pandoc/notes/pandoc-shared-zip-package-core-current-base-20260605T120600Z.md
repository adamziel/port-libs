# Pandoc Shared ZIP Package Core Current Base

Slice: `pandoc-shared-zip-package-core-current-base-20260605T120600Z`
Base accepted HEAD: `ee870bfe781c6a15fd507d5c5749acf3414a1925`
Date: 2026-06-05 UTC

No `port-pandoc-*.needs-lane-rework.md` note was present for this lane before
starting the slice.

## Behavior

- Added bounded ZIP compression-method package preflight.
- `ZipPackage::compressionMethodPreflight()` now reports supported stored and
  deflated entries, unsupported method counts, per-entry method labels, and the
  unsupported-entry subset.
- `ZipPackage::assertSupportedCompressionMethods()` rejects unsupported ZIP
  compression methods before DOCX, ODT, EPUB, or WordPress media handoff paths
  read package bytes.
- Existing read-time rejection for unsupported methods remains in place.
- The WordPress ZIP package preflight smoke now proves method-12 media entries
  are rejected as package policy before attachment bytes are exposed.

## Source Truth

Pandoc consumes DOCX, ODT, and EPUB as ZIP-backed containers. This bounded
native PHP package reader supports the ZIP primitives needed by those package
readers: stored entries, deflated entries, central-directory inventory, local
header integrity, metadata preflight, and bounded byte reads. Other compression
methods remain explicit follow-up work rather than being silently exposed in a
package inventory and failing only when a caller reads the part.

No Pandoc, Cabal build, Haskell runner, `ZipArchive`, `zip`, `unzip`, Word,
LibreOffice, office tooling, external archive tool, external conversion
service, online sanitizer, or online service was used.

## Verification

Baseline focused check before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
1 test files, 341 assertions, 0 failures
```

Focused verification after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
1 test files, 359 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test
zip package writer preflight self-test passed
```

Syntax, JSON, and diff checks:

```text
php -l lanes/pandoc/src/ZipPackage.php
No syntax errors detected in lanes/pandoc/src/ZipPackage.php

php -l lanes/pandoc/tests/ZipPackageTest.php
No syntax errors detected in lanes/pandoc/tests/ZipPackageTest.php

php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php
No syntax errors detected in lanes/pandoc/examples/wordpress-zip-package-preflight.php

php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " ok\n"; }'
lanes/pandoc/lane-status.json ok
lanes/pandoc/UPSTREAM_TEST_MANIFEST.json ok

git diff --check -- lanes/pandoc
```

Root harness not run - isolated micro-slice.

## Delta

- Focused ZIP PASS cases: `48 -> 49`, adding 1 PASS case.
- Focused ZIP assertions: `341 -> 359`, adding 18 assertions.
- Manifest mapped checks: `1344 -> 1345`.
- ZIP package support cases: `21 -> 22`.
- ZIP package core assertions: `131 -> 149`.
- Lane PHP pass count: `886 -> 887`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`ZipPackage` and `ZipPackageEntry` primitives, in-process CRC/DEFLATE handling,
package preflight example, and focused PHP test harness. Full upstream Pandoc
runner parity remains gated on hydrating/building the pinned Pandoc checkout at
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`, but ZIP compression-method
package policy is not blocked by that runner.

## Non-Overlap

This does not repeat accepted central-directory parsing, local entry order,
data descriptors, CRC/local-header integrity, central/local extra-field
parsing, extended or NTFS timestamps, ZIP64 extra-field rejection, Unix symlink
rejection, raw/decoded unsafe path rejection, directory payload rejection,
local-entry overlap/slack rejection, duplicate local-header-offset rejection,
aggregate size preflight, Unix executable preflight, package comment preflight,
central-directory digital-signature provenance, bounded extraction-version
policy, bounded per-entry reads, gzip/tar/LZ4 archive streams, OPC
relationships, DOCX/ODT/EPUB readers, table geometry, doctemplates, math/TeX,
syntax highlighting, or Markdown/HTML reader/writer behavior. It owns only
first-class unsupported compression-method preflight and assertion policy.

## Follow-Up

Keep non-deflate decompressor implementation, AES/encrypted payload support,
spanning archives, cryptographic central-directory signature validation, full
ZIP64 large-archive support, and package-reader wiring for
`assertSupportedCompressionMethods()` as separate bounded slices unless a
concrete DOCX/ODT/EPUB fixture requires one earlier.
