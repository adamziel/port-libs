# EPUB XHTML details/summary spine parity increment

Date: 2026-07-05

## Increment

Added one checked-in current EPUB package/native pair:

- `lanes/pandoc/fixtures/upstream-current-epub-reader/epub/xhtml-details-summary-spine.epub`
- `lanes/pandoc/fixtures/upstream-current-epub-reader/epub/xhtml-details-summary-spine.native`

The fixture is a compact EPUB 3 package with one linear spine XHTML content document. The content document keeps an XHTML `details` disclosure block with a direct `summary`, a paragraph, and a nested bullet list in reading order after a heading. The native golden records Pandoc EPUB behavior: raw HTML block wrappers for `<details>`, `<summary>`, `</summary>`, and `</details>` with parsed block content between them.

## Count delta

- Package/native fixture parity: 67/67 -> 68/68
- Checked-in EPUB/native identity files: 134 -> 136
- Package feature fixture count: 67 -> 68
- Normalized native AST matches: 67 -> 68

## Evidence

Updated checked-in identity, package feature coverage, package feature signature, native AST signature, workflow gates, manifest counters, and EPUB reader evidence counts. The new package feature signature is `bba76e8e32b85e27ddc74f5eea96f590deaff9ca7f398d9e29ef5595cd3796f2`; the new current native AST signature is `c437ff6db26de5d81de79db49dfb1792adffdf6b562b5af5899de66634d339f9`.

The checked-in package/native gate is expected to run with `--require-package-parity=68`, `--require-native-readiness=68`, and `--require-mapped-parity=68`.

This increment keeps HTML tree construction delegated to `Dom\HTMLDocument` through `Html5Dom`; the EPUB-specific change only maps DOM `details` and direct-child `summary` elements into Pandoc-compatible raw block wrappers.
