# Pandoc Shared ZIP Package Core Current Base - Generated DOS Timestamp Guard

Date: 2026-06-09 UTC
Micro-slice: `pandoc-shared-zip-package-core-current-base-20260609T000634Z`
Base accepted HEAD: `35d557737dc1b88c45279aeb585788c53834812d`

## Behavior

`ZipPackage::fromParts()` now rejects explicit `modifiedDosTime` and
`modifiedDosDate` fields that fit in uint16 but do not encode a valid DOS
date/time. This keeps generated Office, EPUB, and ODT package bytes from
carrying timestamp metadata that the strict package import preflight would
later flag as invalid.

The guarded invalid writer cases are:

- DOS date with month zero.
- DOS time with hour 24.
- DOS time with minute 60.
- DOS time with second 60 after the ZIP two-second field expansion.

Omitted modification fields still use the existing generated-package default.

## Evidence

No current `port-pandoc-*.needs-lane-rework.md` note existed for this lane.

Red-first probe before implementation:

```bash
php -r 'require "tools/bootstrap.php"; $p = PortLibs\Pandoc\ZipPackage::fromParts([["name"=>"word/document.xml","data"=>"ok","modifiedDosTime"=>0x0000,"modifiedDosDate"=>0x0001]]); var_export($p->modificationTimePreflight()["entries"][0]); echo "\n";'
```

Result before the patch: the invalid writer timestamp was accepted, and
`modificationTimePreflight()` later reported `invalid-dos-modified-timestamp`.

Baseline focused test:

```bash
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
```

Result: `1 test files, 2043 assertions, 0 failures`.

Final focused test:

```bash
php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php
```

Result: `1 test files, 2047 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test
```

Result: `zip package writer preflight self-test passed`.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP
`ZipPackage` writer, existing DOS timestamp decoding/preflight semantics, and
the WordPress ZIP package preflight example.

No Pandoc, Cabal solver/build/test command, Haskell runner, `zip`, `unzip`,
`ZipArchive`, Word, LibreOffice, external archive tool, online service, live
provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted ZIP slices for raw/import invalid DOS timestamp
reporting, central-directory/local-header name or metadata mismatch, central
directory local-header offset diagnostics, writer comment control-byte
validation, data descriptors, split archives, ZIP64 local-header preflight,
Unicode name collisions, or trailing deflate bytes. It only covers generated
writer-side semantic DOS timestamp validation before package bytes are emitted.

## Next

A next non-overlapping ZIP package slice could cover ZIP64 EOCD locator
provenance, remaining data-descriptor edge diagnostics, or package-reader
handoff behavior not already covered by timestamp, comment, name, split, ZIP64,
or central-directory local-header offset slices.
