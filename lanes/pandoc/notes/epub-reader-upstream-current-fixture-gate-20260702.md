# EPUB Reader Upstream Current Fixture Gate

Date: 2026-07-02

The checked-in EPUB reader fixture subset now mirrors the upstream current
`test/epub` inventory used by the local native/package harness:

- 8 EPUB package inputs in `lanes/pandoc/fixtures/upstream-current-epub-reader/epub`
- 8 same-directory `.native` goldens for every checked-in EPUB package input

Imported from the hydrated upstream cache at
`/Users/admin/port-libs-pandoc-pptx/.upstream-cache/pandoc-full/test/epub`:

- `features.epub`
- `features.native`
- `formatting.epub`
- `formatting.native`
- `wasteland.native`

The missing `epub2_cover`, `epub2_no_cover`, `epub2_picture`, `img`, and
`img_no_cover` native goldens were generated with `pandoc 3.10` using
`pandoc -s -f epub -t native`. That command byte-matched the three existing
checked-in native goldens before the missing files were added.

Verified checked-in gate:

```sh
php tools/pandoc-epub-native-ast-package.php --epub-dir=lanes/pandoc/fixtures/upstream-current-epub-reader/epub --json summary --require-package-parity=8 --require-native-readiness=8 --require-mapped-parity=8
```

Result: `packageParsedCount=8`, `readerParsedCount=8`,
`nativeParsedCount=8`, `normalizedAstMatchCount=8`, and
`normalizedAstMismatchCount=0`.

This continues not to claim upstream Haskell/Tasty runner parity; the harness
records that runner evidence as the remaining open gap.
