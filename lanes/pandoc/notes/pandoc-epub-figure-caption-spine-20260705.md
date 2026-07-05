# EPUB figure caption spine parity increment

Date: 2026-07-05

## Increment

Added one checked-in current EPUB package/native pair:

- `lanes/pandoc/fixtures/upstream-current-epub-reader/epub/figure-caption-spine.epub`
- `lanes/pandoc/fixtures/upstream-current-epub-reader/epub/figure-caption-spine.native`

The fixture is a compact EPUB 3 package with one linear XHTML spine item containing a `<figure>` with an image and `<figcaption>`. The EPUB reader routes the spine XHTML through the HTML reader and now matches Pandoc native AST shape for a figure body that is a single image.

## Count delta

- Package/native fixture parity: 71/71 -> 72/72
- Checked-in EPUB/native identity files: 142 -> 144
- Package feature fixture count: 71 -> 72
- Normalized native AST matches: 71 -> 72

## Verification

Updated checked-in identity, package feature coverage, package feature signature, native AST signature, workflow gates, manifest counters, and EPUB reader evidence counts. The new package feature signature is `5c1fc5244659cafa70093ec6db2c8d442ebbea3bc575656904650e82d14a50a6`; the new current native AST signature is `bdaf329c062c0b4583fe29fc3d182a04fd96e8633cc79b7cdd19d243bd7098df`.

The checked-in package/native gate is expected to run with `--require-package-parity=72`, `--require-native-readiness=72`, and `--require-mapped-parity=72`.
