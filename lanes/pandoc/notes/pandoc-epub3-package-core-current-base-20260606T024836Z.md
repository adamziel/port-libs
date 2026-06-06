# pandoc-epub3-package-core-current-base-20260606T024836Z

## Slice

- Lane: `pandoc`
- Micro-slice: `pandoc-epub3-package-core-current-base-20260606T024836Z`
- Accepted base: `835c273c51f77a1896fa4f56496ca13e5f4b02f3`
- Contract: bounded native EPUB3 package handoff for EPUB nav-document source
  provenance. No Pandoc, Cabal solver/build/test command, Haskell runner,
  zip/unzip, ZipArchive, EPUBCheck, browser renderer, online sanitizer, online
  service, or live provider test was executed.

## Behavior

`EpubReader` now preserves EPUB3 nav section and list-item provenance while
still treating XHTML spine content as raw review HTML:

- nav sections carry `id`, `class`, `classes`, `xml:lang`, `dir`, `hidden`,
  and raw attribute metadata;
- nav list entries carry item/label IDs, label element kind, item and label
  classes, merged classes, language, direction, hidden state, raw list-item
  attributes, and raw label attributes;
- the same item provenance is propagated into `navigation.items` and
  `pageBreaks.items`, so WordPress review packets can retain source navigation
  anchors and hidden navigation markers before import;
- `nav` reports now summarize `sectionCount`, `hiddenSectionCount`,
  `hiddenItemCount`, and `sectionsByType`.

The WordPress EPUB handoff example now exercises the provenance path for a TOC
section and first navigation link.

## Verification

```text
php -l lanes/pandoc/src/EpubReader.php
No syntax errors detected in lanes/pandoc/src/EpubReader.php

php -l lanes/pandoc/tests/EpubReaderTest.php
No syntax errors detected in lanes/pandoc/tests/EpubReaderTest.php

php -l lanes/pandoc/examples/wordpress-epub3-package-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-epub3-package-handoff.php

php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
1 test files, 1295 assertions, 0 failures

php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test
epub3 package handoff self-test ok
```

Focused delta: `+1` PHP PASS case and `+53` direct assertions in
`EpubReaderTest.php`.

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `ZipPackage`,
`EpubReader` DOM-based navigation parsing, `OpcPackagePath` target resolution,
and the existing WordPress EPUB handoff example.

Full upstream Pandoc runner parity remains gated on hydrating the pinned Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with Cabal project and
package files plus Tasty runner dependency closure.

## Non-Overlap

This does not repeat accepted EPUB OCF mimetype/container validation, OCF
metadata/rights/signatures/manifest sidecars, OPF metadata/manifest/spine
parsing, unique identifier handling, title refinements, raw XHTML spine
handoff, nav/NCX target resolution, CFI classification, page-list/NCX pageList
handoff, guide/collection handling, alternate renditions, spine or asset
fallback chains, bindings, media overlays, trigger/switch review flags,
remote-resource reconciliation, encryption/obfuscated-font preflight, or ZIP
package integrity work.

## Follow-Up

Keep XHTML-to-AST conversion, CSS cascade/resource export policy, actual
`epub:switch` fallback branch selection, media extraction/export policy,
reading-system capability evaluation, EPUBCheck validation, and full upstream
Pandoc Haskell navigation fixture parity as separate bounded slices.
