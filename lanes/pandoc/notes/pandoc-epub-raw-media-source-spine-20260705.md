# EPUB raw media source spine parity increment

Date: 2026-07-05

## Increment

Added one checked-in current EPUB package/native pair:

- `lanes/pandoc/fixtures/upstream-current-epub-reader/epub/raw-media-source-spine.epub`
- `lanes/pandoc/fixtures/upstream-current-epub-reader/epub/raw-media-source-spine.native`

The fixture is a compact EPUB 3 package with a linear XHTML spine item containing raw `<video>`, `<source>`, and `<track>` media markup, plus poster, video, and caption package resources.

## Count delta

- Package/native fixture parity: 73/73 -> 74/74
- Checked-in EPUB/native identity files: 146 -> 148
- Package feature fixture count: 73 -> 74
- Normalized native AST matches: 73 -> 74

## Verification

Updated checked-in identity, package feature coverage, package feature signature, native AST signature, manifest counters, and EPUB reader evidence counts. The new package feature signature is `bf7391c81e08a9cce8b29cc5dc6c255e0f5fcd8c57fdec2ec0d46d84c857e9be`; the new current native AST signature is `bfb3804b89f429daa0893f086c74080456ec56daeb6c02ccfec03b85e77e5c2c`.

The checked-in package/native gate is expected to run with `--require-package-parity=74`, `--require-native-readiness=74`, and `--require-mapped-parity=74`.
