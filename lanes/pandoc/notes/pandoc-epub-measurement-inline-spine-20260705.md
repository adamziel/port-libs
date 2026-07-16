# EPUB measurement inline spine parity increment

Date: 2026-07-05

## Increment

Added one checked-in current EPUB package/native pair:

- `lanes/pandoc/fixtures/upstream-current-epub-reader/epub/measurement-inline-spine.epub`
- `lanes/pandoc/fixtures/upstream-current-epub-reader/epub/measurement-inline-spine.native`

The fixture is a compact EPUB 3 package with one linear XHTML spine item containing inline `<data>`, `<meter>`, and `<progress>` elements. The EPUB reader routes the spine XHTML through `HtmlReader`, preserving visible inline content for `data` and `meter` and retaining the raw inline wrapper behavior for `progress`.

## Count delta

- Package/native fixture parity: 70/70 -> 71/71
- Checked-in EPUB/native identity files: 140 -> 142
- Package feature fixture count: 70 -> 71
- Normalized native AST matches: 70 -> 71

## Verification

Updated checked-in identity, package feature coverage, package feature signature, native AST signature, workflow gates, manifest counters, and EPUB reader evidence counts. The new package feature signature is `cb60f25c450d9183301e9259e30d7120d820e61c7a93636e4fad2378f1be529a`; the new current native AST signature is `afcd30aeb3a0f9d6fcbdb925e05610f5a722c4c363898fd06232fdc1ab7cdc7c`.

The checked-in package/native gate is expected to run with `--require-package-parity=71`, `--require-native-readiness=71`, and `--require-mapped-parity=71`.
