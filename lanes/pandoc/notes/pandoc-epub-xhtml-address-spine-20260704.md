# EPUB XHTML address spine parity increment

Date: 2026-07-04

## Increment

Added one checked-in current EPUB package/native pair:

- `lanes/pandoc/fixtures/upstream-current-epub-reader/epub/xhtml-address-spine.epub`
- `lanes/pandoc/fixtures/upstream-current-epub-reader/epub/xhtml-address-spine.native`

The fixture is a compact EPUB 3 package with one linear spine XHTML content document. The content document keeps an XHTML `address` contact block in reading order after a heading and paragraph. The native golden records Pandoc EPUB behavior: raw HTML block wrappers for `<address>` and `</address>` with the parsed contact content between them as a paragraph block.

## Count delta

- Package/native fixture parity: 64/64 -> 65/65
- Checked-in EPUB/native identity files: 128 -> 130
- Package feature fixture count: 64 -> 65
- Normalized native AST matches: 64 -> 65

## Evidence

Updated checked-in identity, package feature coverage, package feature signature, native AST signature, and EPUB reader evidence counts. The new package feature signature is `f8e7b30e1179b9bd13ce3d09f782eb49fd65d907792585424e8093cd46a3d9cd`; the new current native AST signature is `11deed6d7cd3c4f120e11abf2cfa3e0fdae350866afffccd20cd7717f38c9569`.

The EPUB native/package gate was initially run before the new fixture identity rows were added and failed on checked-in identity validation. After updating the identity rows and signatures it passed at 65/65. An EPUB reader evidence command was also first run with an unsupported `summary` positional argument; the corrected reader evidence command passed with package/native parity at 65/65. A focused test rerun exposed one stale `spineLinear=linear:80` report substring, which was updated to the new `linear:81` count and then passed.

The checked-in package/native gate is expected to run with `--require-package-parity=65`, `--require-native-readiness=65`, and `--require-mapped-parity=65`.

This increment does not assert upstream Haskell/Cabal runner execution, EPUB writer parity, or full EPUB feature parity.
