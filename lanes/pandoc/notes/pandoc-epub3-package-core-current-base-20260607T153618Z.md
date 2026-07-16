# EPUB3 Package Core Current-Base Spine Rendition Handoff

- Slice: `pandoc-epub3-package-core-current-base-20260607T153618Z`
- Base accepted HEAD: `ecdae3d672a8d414071d8e7c8995009a528f904e`
- Lane: `pandoc`

## Behavior

This slice adds bounded native OPF `spine/itemref` rendition property handoff
for fixed-layout review metadata:

- `rendition:flow-auto`
- `rendition:flow-paginated`
- `rendition:flow-scrolled-continuous`
- `rendition:flow-scrolled-doc`
- `rendition:align-x-center`

`EpubReader` now reports those tokens through each spine item, the structured
`spineItemProperties` packet, conflict diagnostics for multiple flow values,
and the raw-HTML AST block attributes used by the WordPress EPUB3 handoff.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  passed with `1 test files, 1816 assertions, 0 failures`.
- Red-first: the same focused command failed with `1 test files, 1817
  assertions, 1 failures` because OPF spine itemrefs did not expose
  `flow=paginated`.
- Final: `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  passed with `1 test files, 1840 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`
  passed.
- PHP lint passed for `EpubReader.php`, `EpubReaderTest.php`, and
  `wordpress-epub3-package-handoff.php`.

## Delta

- `+1` focused PHP PASS case.
- `+24` focused assertions in `EpubReaderTest.php`.
- Lane `phpPass` updated from `1523` to `1524`.

## Dependency Closure

No new support component is needed. The patch reuses the existing native
`ZipPackage` fixture path, DOM/OPF parsing in `EpubReader`, `AstNode` metadata
handoff, focused EPUB tests, and the existing WordPress EPUB3 package handoff
example.

Pandoc, Cabal/Haskell runners, Word, LibreOffice, `zip`/`unzip`, browser
renderers, external converters, online services, live provider tests, and
live-service provider tests were not executed.

## Non-Overlap

This does not repeat accepted EPUB3 coverage for OCF container/rootfiles,
metadata/vendor vocabularies/refinements, manifest fallback chains, nav/NCX,
page-list/page-breaks, page progression direction, page-spread itemref
properties, linear diagnostics, XHTML resource scanning, media overlays,
encryption/font resources, sidecars, CFI fragments, or mimetype placement.

The remaining EPUB3 follow-up should stay in a distinct package-review gap,
such as OPF collection role details, nav target media-fragment policy, or
package-level fixed-layout viewport review metadata.
