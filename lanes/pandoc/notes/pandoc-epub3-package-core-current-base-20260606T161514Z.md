# pandoc-epub3-package-core-current-base-20260606T161514Z

## Slice

- Lane: `pandoc`
- Micro-slice: `pandoc-epub3-package-core-current-base-20260606T161514Z`
- Accepted base: `75fa47f3fd4a092265a672a9ef4ebfe9b906474c`
- Upstream contract: bounded native EPUB/NCX package-review handoff for legacy
  NCX `navMap` / `navPoint` provenance. No Pandoc, Cabal, Haskell runner,
  zip/unzip, ZipArchive, EPUBCheck, browser renderer, online service, live
  provider test, or live-service provider test was used.

## Behavior

Legacy NCX `navPoint` records carry package-review provenance beyond target
resolution: classes, `xml:lang`, `dir`, `navLabel` attributes, `content`
attributes, and local package-byte metadata. `EpubReader` already resolved NCX
navMap targets, but those source fields were not exposed like the existing
EPUB3 nav and NCX navList reports.

`EpubReader::readNcxPoints()` now preserves:

- `class`, `classes`, `language`, and `direction` for each `navPoint`;
- raw `navPoint`, `navLabel`, text, and `content` attributes;
- local target `byteLength` and CRC32 provenance from the resolved package part.

The data flows through `ncx.items`, `navigation.items`, `importReport['ncx']`,
and the WordPress EPUB3 package handoff smoke.

## Verification Evidence

Accepted-base focused baseline:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
1 test files, 1509 assertions, 0 failures
```

Red-first after adding the focused case:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
FAIL preserves NCX navPoint provenance for legacy package review
1 test files, 1511 assertions, 1 failures
```

After implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
1 test files, 1539 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test
epub3 package handoff self-test ok
```

Focused delta: `+1` PHP PASS case and `+30` net focused assertions for the
EPUB3 package support row.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `ZipPackage`,
`OpcPackagePath`, `EpubReader`, DOM/libxml parsing, in-memory EPUB fixtures,
and the existing WordPress EPUB3 handoff example.

Full upstream Pandoc runner parity remains unchanged: hydrate the pinned Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with Cabal
project/package files and Haskell Tasty runner dependencies before attempting
runner parity.

## Non-Overlap

This patch does not repeat accepted EPUB OCF mimetype/container/rootfile
validation, OCF sidecars, OPF metadata/DC/meta extraction, metadata link byte
resolution, metadata refinement subject diagnostics, package vocabulary prefix
resolution, OPF manifest/spine parsing, spine/page-spread diagnostics, fallback
chains, bindings, guide/collections, alternate renditions, EPUB3 nav section
and auxiliary-navigation summaries, NCX head/navList/pageList support,
navigation/spine reconciliation, raw XHTML spine handoff, content resource
scanning, remote-resource reporting, cover-image provenance,
encryption/obfuscated-font preflight, SMIL media overlays, or EPUB CFI
propagation. It owns only legacy NCX navMap navPoint provenance fields.

## Follow-Up

Keep XHTML-to-AST conversion, CSS cascade/media export policy, remote-resource
fetch policy, richer EPUBCheck-style validation, encrypted-resource decryption,
active media-overlay playback semantics, and full nav-to-AST rendering beyond
package review summaries as separate bounded EPUB slices.
