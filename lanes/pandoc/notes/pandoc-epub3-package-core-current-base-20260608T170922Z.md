# EPUB3 Package Core Current Base - Metadata Link Targets

Slice: `pandoc-epub3-package-core-current-base-20260608T170922Z`
Base accepted HEAD: `4b4ed6566d9aa97b39e2a564de2e67000bb01006`

## Scope

This slice adds bounded EPUB3 OPF metadata `<link>` target policy reporting in
`EpubReader`. The reader now summarizes metadata links by publication-wide vs.
refined-subject scope, local vs. external targets, manifest-linked vs.
unmanifested package bytes, missing targets, encrypted targets, byte exposure,
and relation grouping.

The behavior is intentionally local to already parsed OPF/package assets. It
does not fetch external metadata, run EPUBCheck, shell out to Pandoc, invoke
Haskell tooling, or inspect a ZIP through external `zip`/`unzip` commands.

## Evidence

Red-first focused check before implementation:

`php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`

Result: `1 test files, 2409 assertions, 1 failures`; the new metadata link
target policy assertion failed because `metadata.linkTargetReport` was absent.

Final focused checks:

`php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`

Result: `1 test files, 2455 assertions, 0 failures`.

`php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`

Result: `epub3 package handoff self-test ok`.

Focused assertion delta: `+46` in `EpubReaderTest.php`.

## Status Delta

- `UPSTREAM_TEST_MANIFEST.json`: EPUB3 package core mapped cases `6 -> 7`;
  mapped denominator `2115 -> 2116`; EPUB3 package core assertions
  `112 -> 159`.
- `lane-status.json`: `phpPass` `1695 -> 1696`; current work and latest
  focused-slice evidence updated for this micro-slice.

## Non-Overlap

This slice does not overlap recent EPUB3 work on nav target policy, fixed-layout
itemref overrides, media fragment reporting, metadata link vocabulary token
validation, duplicate manifest package-part aliases, or generic collection
membership. It only adds target policy aggregation over OPF metadata link
records that the reader already resolves.

## Dependency Closure

No new support component is needed. The slice reuses the existing EPUB package
fixture builder, OPF XML parser, package asset lookup, manifest resolution, and
metadata link resolver.

## Follow-Up

A later EPUB3 slice can route selected metadata link diagnostics into a
conversion warning surface for caller policy decisions. This patch keeps the
new data in metadata/import-report structures without changing conversion
blocking behavior.
