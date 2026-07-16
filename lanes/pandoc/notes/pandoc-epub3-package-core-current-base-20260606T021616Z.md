# EPUB3 Package Core Current-Base OCF Manifest Sidecar

Slice: `pandoc-epub3-package-core-current-base-20260606T021616Z`
Base accepted HEAD: `3d74120a9c1b8d588cf826b675c1e5e30d4592e7`
Date: 2026-06-06 UTC

## Scope

Implemented bounded native PHP reporting for the optional EPUB OCF `META-INF/manifest.xml` sidecar. The sidecar is treated as review-only package inventory: it is surfaced in `ocf.manifest`, `importReport.ocf.manifest`, and document OCF metadata, but it does not become an OPF manifest source and does not suppress normal unmanifested-asset diagnostics.

Source-truth note: EPUB OCF 3.2 describes `META-INF/manifest.xml` as optional, does not require a specific format, and says reading systems must not use it while processing renditions. This slice therefore accepts an ODF-compatible `manifest:manifest` shape for bounded review metadata and reports nonstandard roots without using them for conversion decisions.

## Behavior

- Parses ODF-compatible `manifest:file-entry` records with `manifest:full-path`, media type, version, and size.
- Resolves local package references, including root `/`, and reports missing, invalid, and external targets.
- Records ZIP byte length, CRC32, SHA-256 for readable local entries, declared-size mismatches, encrypted-entry byte exposure guards, and item/reference aggregate counts.
- Keeps OPF asset reporting authoritative. A file listed only in OCF `manifest.xml` remains an unmanifested package asset in the EPUB import report.
- Updates the WordPress EPUB example smoke to include and assert OCF manifest sidecar handoff.

## Verification

Baseline before the slice:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
1 test files, 1188 assertions, 0 failures
```

After implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
1 test files, 1242 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test
epub3 package handoff self-test ok
```

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native PHP `ZipPackage`, XML DOM parsing, `OpcPackagePath`, existing OCF sidecar diagnostics, `AstNode` metadata, and the WordPress block writer handoff. No Pandoc, Cabal/Haskell runner, zip/unzip, ZipArchive, browser renderer, external XML validator, online service, or live provider test was executed.

## Non-Overlap

Avoided recent EPUB3 package slices for OCF `metadata.xml`, rights/signatures, NCX pageList fallback, SMIL media overlays, OPF fallback chains, and non-spine asset fallback reporting. This slice owns only optional OCF `META-INF/manifest.xml` sidecar inventory and its WordPress review handoff.
