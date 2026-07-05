# EPUB direct SVG spine parity increment

Date: 2026-07-05

## Increment

Added one checked-in current EPUB package/native pair:

- `lanes/pandoc/fixtures/upstream-current-epub-reader/epub/direct-svg-spine.epub`
- `lanes/pandoc/fixtures/upstream-current-epub-reader/epub/direct-svg-spine.native`

The fixture is a compact EPUB 3 package with one linear spine item whose media type is `image/svg+xml`. Current upstream Pandoc records the spine item as a marker paragraph containing the resource basename rather than emitting an `Image` block, so the native golden is:

```native
[ Para [ Span ( "cover.svg" , [] , [] ) [] ] ]
```

## Count delta

- Package/native fixture parity: 68/68 -> 69/69
- Checked-in EPUB/native identity files: 136 -> 138
- Package feature fixture count: 68 -> 69
- Normalized native AST matches: 68 -> 69

## Verification

Updated checked-in identity, package feature coverage, package feature signature, native AST signature, workflow gates, manifest counters, and EPUB reader evidence counts. The new package feature signature is `35490d8e6f7e28419b8aad59f772cd35161cdfbea67380a536bebbea11ff9511`; the new current native AST signature is `46c74f9be9c8345c9837393d60b4de002887f9e05c7e6c1585e5d1412cdf677e`.

The checked-in package/native gate is expected to run with `--require-package-parity=69`, `--require-native-readiness=69`, and `--require-mapped-parity=69`.

This increment keeps SVG spine items in the upstream-compatible marker-only path and records the SVG package resource as an image-package resource without adding it to the media bag.
