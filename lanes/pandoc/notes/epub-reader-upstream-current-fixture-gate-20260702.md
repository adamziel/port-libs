# EPUB Reader Upstream Current Fixture Gate

Date: 2026-07-02

The checked-in EPUB reader fixture subset now mirrors the upstream current
`test/epub` inventory used by the local native/package harness:

- 8 EPUB package inputs in `lanes/pandoc/fixtures/upstream-current-epub-reader/epub`
- 3 same-directory `.native` goldens for `features`, `formatting`, and
  `wasteland`

Imported from the hydrated upstream cache at
`/Users/admin/port-libs-pandoc-pptx/.upstream-cache/pandoc-full/test/epub`:

- `features.epub`
- `features.native`
- `formatting.epub`
- `formatting.native`
- `wasteland.native`

Verified checked-in gate:

```sh
php tools/pandoc-epub-native-ast-package.php --epub-dir=lanes/pandoc/fixtures/upstream-current-epub-reader/epub --json summary --require-package-parity=8 --require-native-readiness=3 --require-mapped-parity=3
```

Result: `packageParsedCount=8`, `readerParsedCount=8`,
`nativeParsedCount=3`, `normalizedAstMatchCount=3`, and
`normalizedAstMismatchCount=0`.

This continues not to claim upstream Haskell/Tasty runner parity; the harness
records that runner evidence as the remaining open gap.
