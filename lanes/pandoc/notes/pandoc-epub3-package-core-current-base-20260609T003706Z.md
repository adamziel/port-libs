# pandoc-epub3-package-core-current-base-20260609T003706Z

Slice: EPUB3 NCX navList label-provenance handoff on accepted base
`bbbc4ba0dfc32062a33116d8b1e447ce7ace447b`.

## Behavior

Legacy EPUB NCX files can attach source attributes to `navList/navLabel`,
`navList/navLabel/text`, `navTarget/navLabel`, and `navTarget/navLabel/text`.
The accepted reader already preserved this provenance for NCX `navPoint` and
`pageList` records, but supplemental `navList` reviewer references only kept
the resolved title string.

This slice extends `EpubReader` so NCX supplemental navigation now preserves:

- `navLists[*].labelAttributes` and `navLists[*].labelTextAttributes`.
- `navLists[*].items[*].labelAttributes` and
  `navLists[*].items[*].labelTextAttributes`.
- aggregate `navigation.supplementalItems[*].labelTextAttributes` for
  WordPress review packets.

The target resolution, supplemental target counts, remote/missing diagnostics,
and primary spine coverage semantics are unchanged.

## Focused Evidence

- Baseline before this slice:
  `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  passed with `1 test files, 3233 assertions, 0 failures`.
- Red-first after adding focused assertions:
  `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  failed in `reports NCX navList targets for legacy package review` because
  `navLists[0].labelAttributes` was absent.
- After implementation:
  `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  passed with `1 test files, 3243 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
  passed with `epub3 package handoff self-test ok`.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `ZipPackage`,
`EpubReader`, `OpcPackagePath` reference resolution, DOM/libxml XML parsing,
existing NCX navigation parsing, and the WordPress EPUB3 handoff example.

The local Pandoc upstream checkout referenced by the static manifest is not
present in this isolated environment, so no upstream source tree was read for
this slice. No Pandoc, Cabal solver/build/test command, Haskell runner,
zip/unzip, Word, LibreOffice, browser renderer, online service, live provider
test, or live-service provider test was executed.

## Non-Overlap

This patch does not repeat accepted EPUB OCF mimetype/container/rootfile
validation, OPF metadata/manifest/spine parsing, OPF prefix vocabulary
resolution, raw XHTML spine handoff, nav XHTML parsing, NCX `navMap` target
resolution, NCX head/docTitle/docAuthor metadata, NCX pageList/pageTarget
handoff, NCX navList aggregate target counts, navigation/spine reconciliation,
guide/collection links, alternate renditions, fallback chains, bindings, media
overlays, trigger/switch review flags, remote-resource reconciliation,
encryption, obfuscated-font preflight, EPUB CFI fragments, or ZIP integrity
work.

The covered gap is specifically NCX `navList` and `navTarget` label source
attribute provenance in supplemental navigation handoff records.

## Follow-Up

A later EPUB3 slice can cover fuller NCX navList role/type validation,
XHTML-to-AST conversion, CSS cascade/media export policy, active media
playback, EPUBCheck validation, remote-resource policy, or full Haskell/Pandoc
runner comparison as separate bounded work.
