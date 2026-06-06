# Pandoc Shared ZIP Package Core Current Base

Slice: `pandoc-shared-zip-package-core-current-base-20260606T233723Z`
Base accepted HEAD: `eb7d11e9bcd6594ca75065e9ce45b3589c10aa36`
Date: 2026-06-06 UTC

## Behavior

This slice tightens native ZIP extra-field parsing for Office/EPUB/ODT package
imports. `ZipPackageEntry` now rejects Info-ZIP extended timestamp (`0x5455`)
payloads that carry unsupported flag bits or trailing bytes after the exact
modified/accessed/created timestamp fields declared by the flag mask.

Valid central modified timestamps and local modified/accessed/created timestamp
handoffs remain readable. Malformed 0x5455 metadata now fails before package
parts are exposed to DOCX, EPUB, ODT, or WordPress media review paths.

## Source Truth

This stays inside the accepted `pandoc-shared-zip-package-core-*` support row
for ZIP/OPC package primitives. The Info-ZIP extended timestamp field consists
of a one-byte flag mask followed by four-byte Unix timestamps in the fixed
modified, accessed, created order for the set low three bits. Unknown flag bits
and trailing bytes are ambiguous package metadata, so the bounded native reader
fails closed instead of silently dropping them.

No Pandoc, Cabal solver/build/test command, Haskell runner, `ZipArchive`,
`zip`, `unzip`, Word, LibreOffice, external archive tool, online service, live
provider test, or live-service provider test was executed.

## Evidence

Red-first focused check:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
1 test files, 978 assertions, 1 failures
```

Failure: malformed extended timestamp flag/trailing-byte payloads were still
accepted before implementation.

Final focused check:

```text
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
1 test files, 980 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test
zip package writer preflight self-test passed
```

Syntax and diff hygiene:

```text
php -l lanes/pandoc/src/ZipPackageEntry.php
php -l lanes/pandoc/tests/ZipPackageTest.php
php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php
git diff --check -- lanes/pandoc
```

Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1417 -> 1418`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1830 -> 1831`
- ZIP package support cases: `22 -> 23`
- ZIP package focused assertions: `161 -> 164`

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`ZipPackageEntry` extra-field parsing, `ZipPackage` local/central metadata
validation, in-memory ZIP fixtures, and the existing WordPress ZIP package
preflight example.

## Non-Overlap

This does not repeat accepted ZIP64 extra-field planning, data-descriptor
handling, NTFS timestamp parsing, duplicate extra-field-id preflight,
central/local extra-field mismatch preflight, invalid DOS timestamps,
central-directory signature policy, Unicode path/comment extra-field handling,
Unicode name collision preflight, general-purpose flag policy, unsupported
compression-method policy, symlink/special-file rejection, local-entry slack
checks, or trailing-deflate validation. It only adds stricter malformed
Info-ZIP extended timestamp validation.

## Follow-Up

Continue ZIP/OPC package closure with reader-specific strict policy wiring,
package relationship/content-type handoff, or other non-overlapping package
metadata preflight. Keep ZIP64 extraction, encrypted payload extraction,
cryptographic signature verification, external archive validators, Pandoc,
Word, LibreOffice, zip/unzip, and Haskell runners out unless explicitly
authorized.
