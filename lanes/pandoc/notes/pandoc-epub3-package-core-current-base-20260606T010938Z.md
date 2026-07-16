# pandoc-epub3-package-core-current-base-20260606T010938Z

## Slice

- Lane: `pandoc`
- Micro-slice: `pandoc-epub3-package-core-current-base-20260606T010938Z`
- Accepted base: `cf4b5a94f87aa553cb264969d6be0b6e867277bb`
- Upstream contract: bounded native EPUB3 package handoff for OPF manifest fallback chains on non-spine assets, without invoking Pandoc, Cabal, Haskell runners, zip/unzip, ZipArchive, EPUBCheck, browser renderers, online services, or live provider tests.

## Behavior

EPUB package manifests can declare `fallback` ids for assets that require another publication resource when the original media type is not supported. Earlier native EPUB support resolved foreign spine items to an XHTML fallback for AST review, but the non-spine asset report only exposed the original asset. This slice extends `EpubReader` asset reporting so package review packets can inspect the OPF fallback chain before a WordPress import chooses an export or attachment policy.

`EpubReader` now reports per non-spine asset:

- `fallbackId`, `fallback`, and ordered `fallbackChain` entries;
- terminal `fallbackContentId`, `fallbackContentTarget`, `fallbackContentPart`, and `fallbackContentMediaType`;
- fallback attachment handoff fields including `fallbackAttachmentCandidate`, `fallbackAttachmentRole`, and `fallbackByteSha256`;
- bounded diagnostics for missing fallback ids, external fallback targets, encrypted/unreadable fallback bytes, and cyclic fallback chains.

The aggregate `importReport.assets` summary now exposes `fallbackCount`, `fallbackItems`, `fallbackDiagnosticCount`, and `fallbackDiagnostics` so WordPress review queues can identify fallback-heavy or malformed packages without scanning every manifest item.

## Verification

Red-first evidence before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
FAIL reports non-spine OPF asset fallback chains for package review
1 test files, 1159 assertions, 1 failures
```

Focused verification after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
1 test files, 1188 assertions, 0 failures
```

WordPress handoff smoke:

```text
php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test
epub3 package handoff self-test ok
```

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP `ZipPackage`, `EpubReader`, `OpcPackagePath`, and EPUB WordPress handoff fixture paths. It did not run Pandoc, Cabal solver/build/test commands, Haskell runners, Word, LibreOffice, zip/unzip, ZipArchive, EPUBCheck, browser renderers, JavaScript, online sanitizers, online services, or live provider tests.

## Non-Overlap

This patch does not repeat accepted OCF mimetype/container validation, OCF metadata sidecars, OPF metadata/manifest/spine parsing, raw XHTML spine handoff, nav/NCX target resolution, guide/collection handling, alternate renditions, spine fallback-to-XHTML resolution, bindings, media overlays, trigger/switch review flags, remote-resource reconciliation, encryption/obfuscated-font preflight, OCF rights/signatures, EPUB CFI fragments, ZIP package integrity work, or PDF/DOCX/ODT support rows. The new surface is only non-spine OPF asset fallback-chain handoff and diagnostics.

## Follow-Up

Keep CSS cascade/resource export policy, actual fallback selection UX, active handler execution, EPUBCheck validation, remote-resource policy, media playback, and full XHTML-to-AST conversion as separate bounded EPUB slices.
