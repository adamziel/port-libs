# EPUB metadata link vocabulary diagnostics parity increment

Date: 2026-07-05

## Increment

Added one checked-in current EPUB package/native pair:

- `lanes/pandoc/fixtures/upstream-current-epub-reader/epub/metadata-link-vocab-diagnostics.epub`
- `lanes/pandoc/fixtures/upstream-current-epub-reader/epub/metadata-link-vocab-diagnostics.native`

The fixture is a compact EPUB 3 package with an OPF metadata `<link>` record carrying mixed vocabulary tokens: plain rel/property tokens, prefixed tokens, absolute URL fragment tokens, duplicate tokens, invalid tokens, and undeclared-prefix tokens.

## Count delta

- Package/native fixture parity: 72/72 -> 73/73
- Checked-in EPUB/native identity files: 144 -> 146
- Package feature fixture count: 72 -> 73
- Normalized native AST matches: 72 -> 73

## Verification

Updated checked-in identity, package feature coverage, package feature signature, native AST signature, manifest counters, and EPUB reader evidence counts. The new package feature signature is `6b5c521b89ab5686738051119204930356ac1cda6e271815d0923a64f9daee4f`; the new current native AST signature is `95f817e5195341ce5e93adb11de0883965600c84a50d12d07f89b927daa34b65`.

The checked-in package/native gate is expected to run with `--require-package-parity=73`, `--require-native-readiness=73`, and `--require-mapped-parity=73`.
