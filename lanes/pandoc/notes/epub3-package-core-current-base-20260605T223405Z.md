# EPUB3 Package Title Metadata Handoff

Slice: `pandoc-epub3-package-core-current-base-20260605T223405Z`

Accepted base: `ddb326e0de676cb18d5010ac541b64ef59fcf1be`

## Source Truth

- W3C EPUB 3.3 package metadata keeps publication titles in OPF `dc:title`
  elements and refines them with OPF `meta` properties such as `title-type`,
  `file-as`, and `display-seq`.
- OPF metadata elements may carry language and direction metadata that import
  review queues need to preserve rather than flatten into one display title.

## Behavior

- `EpubReader` now preserves `dir` on DC metadata entries, OPF metadata links,
  and OPF metadata refinement entries.
- OPF title refinements are summarized as `titleDetails`, `titlesByType`,
  `mainTitle`, `subtitle`, `shortTitle`, `collectionTitle`, and `sortTitle`.
- Title summaries preserve `title-type`, `file-as`, `display-seq`,
  `alternate-script`, language, direction, and raw refinements.
- WordPress EPUB handoff metadata now exposes the same grouped title summary on
  the document attributes for reviewer packets.

## Verification Evidence

Red-first:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
FAIL summarizes OPF title-type refinements and direction metadata for review handoff
1 test files, 1093 assertions, 1 failures
```

After implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
1 test files, 1113 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test
epub3 package handoff self-test ok
```

Focused delta: `+1` PHP PASS case, `+61` focused test-file assertions, and
`+21` direct assertions in the new title metadata case.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `ZipPackage`
fixtures, DOM parsing, `EpubReader` OPF metadata parsing, and the existing
WordPress EPUB package handoff example. No Pandoc, Cabal solver/build/test
command, Haskell runner, Word, LibreOffice, zip/unzip, ZipArchive, browser
renderer, JavaScript/media execution, online sanitizer, online service, or live
provider test was executed.

## Non-Overlap

This does not repeat accepted EPUB OCF container/rootfile parsing, OPF
manifest/spine/nav/NCX/XHTML handoff, guide/collection links, remote-resource
reports, media overlays, OCF sidecars, CFI preservation, or the previous
`epub:trigger` slice. It owns only OPF title metadata summary and direction
handoff.

## Follow-Up

Keep XHTML-to-AST conversion, media extraction/export policy, remote-resource
policy, multiple rendition selection, encrypted/obfuscated font handling
beyond preflight, CSS cascade behavior, and active trigger/media playback as
separate bounded slices.
