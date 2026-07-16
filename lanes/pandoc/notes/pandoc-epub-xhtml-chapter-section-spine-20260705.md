# EPUB XHTML chapter section parity increment

Date: 2026-07-05

## Increment

Added one checked-in current EPUB package/native pair:

- `lanes/pandoc/fixtures/upstream-current-epub-reader/epub/xhtml-chapter-section-spine.epub`
- `lanes/pandoc/fixtures/upstream-current-epub-reader/epub/xhtml-chapter-section-spine.native`

The fixture is a compact EPUB 3 package with a linear XHTML spine item containing a sectioning element whose `epub:type` includes `chapter`. Pandoc 3.10 unwraps that chapter section and emits the section contents directly after the spine marker.

## Count delta

- Package/native fixture parity: 74/74 -> 75/75
- Checked-in EPUB/native identity files: 148 -> 150
- Package feature fixture count: 74 -> 75
- Normalized native AST matches: 74 -> 75

## Verification

Updated checked-in identity, package feature coverage, package feature signature, native AST signature, manifest counters, and EPUB reader evidence counts. The new package feature signature is `7a0e930fa84fc8797ad4612d6a86b456a040da258e38e66cc717596448a8105d`; the new current native AST signature is `fec1e23911a8ba3d984c9b8f3de79b3b76a0a659efe85d940a43c2fb62e1fc84`.

The checked-in package/native gate is expected to run with `--require-package-parity=75`, `--require-native-readiness=75`, and `--require-mapped-parity=75`.
