# EPUB3 OCF sidecar reference provenance

Slice: `plib-ti8f3`
Date: 2026-06-11 UTC
Base: `4c7bc38809bb3ea7c285607b4bd8e5006f64a9b8`

## Behavior

`EpubReader` now preserves full OCF sidecar reference provenance for
metadata, rights, and XML signature sidecars:

- local and remote sidecar references expose `fragment`, `fragmentKind`,
  `epubCfi`, and `mediaFragment` fields consistently with container links and
  OPF manifest targets;
- local package sidecar references expose `byteSha256` alongside byte length
  and CRC metadata;
- signature references carry the same parsed fragment and hash fields without
  attempting cryptographic validation.

The focused fixture covers a metadata self-reference, a rights reference with
query and fragment suffixes, and a signed XHTML reference with fragment and
byte provenance.

## Verification

- `php -l lanes/pandoc/src/EpubReader.php`
- `php -l lanes/pandoc/tests/EpubReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - `1 test files, 4007 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 63896 assertions, 0 failures`

No Pandoc, EPUBCheck, zip/unzip, office suite, TeX/PDF engine, browser
renderer, Node tooling, Jupyter, external validator, online service, live
provider test, or live-service provider test was invoked.

## Accounting

- `phpPass`: `3069 -> 3070`
- Focused EPUB reader assertions now cover OCF sidecar reference fragment and
  byte-hash provenance.

## Non-overlap

This does not repeat accepted OCF container/rootfile validation, OPF manifest
href suffix reporting, OCF sidecar discovery, metadata link policy, manifest
file-entry validation, encryption reporting, media overlays, nav/NCX parsing,
or compact `EpubPackage` preflight work. It is limited to rich `EpubReader`
OCF sidecar reference fields for already parsed sidecar records.
