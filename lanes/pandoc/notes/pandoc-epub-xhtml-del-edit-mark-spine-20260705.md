# EPUB XHTML del edit mark parity increment

Date: 2026-07-05

## Increment

Added one checked-in current EPUB package/native pair:

- `lanes/pandoc/fixtures/upstream-current-epub-reader/epub/xhtml-del-edit-mark-spine.epub`
- `lanes/pandoc/fixtures/upstream-current-epub-reader/epub/xhtml-del-edit-mark-spine.native`

The fixture is a compact EPUB 3 package with one linear XHTML spine item containing `<del cite datetime>` and `<ins cite datetime>` edit-mark elements. Current upstream Pandoc renders the visible content as plain `Strikeout` and `Underline` in native output without retaining the revision `cite` or `datetime` attributes.

## Count delta

- Package/native fixture parity: 69/69 -> 70/70
- Checked-in EPUB/native identity files: 138 -> 140
- Package feature fixture count: 69 -> 70
- Normalized native AST matches: 69 -> 70

## Verification

Updated checked-in identity, package feature coverage, package feature signature, native AST signature, workflow gates, manifest counters, and EPUB reader evidence counts. The new package feature signature is `92c4703e52216023f982fc7ab7d7112ab7bcdaa6dd73c8fbfc9c39b64036f588`; the new current native AST signature is `82cf287405870c575d18c29d0f4a2f24577ba964f78200cc3c911463fb5d85a9`.

The checked-in package/native gate is expected to run with `--require-package-parity=70`, `--require-native-readiness=70`, and `--require-mapped-parity=70`.
