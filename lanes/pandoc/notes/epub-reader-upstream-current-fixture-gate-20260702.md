# EPUB Reader Upstream Current Fixture Gate

Date: 2026-07-02
Updated: 2026-07-04

The checked-in EPUB reader fixture subset now mirrors the upstream current
`test/epub` inventory plus generated current-upstream edge fixtures used by
the local native/package harness:

- 35 EPUB package inputs in `lanes/pandoc/fixtures/upstream-current-epub-reader/epub`
- 35 same-directory `.native` goldens for every checked-in EPUB package input

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

Added generated `content-image-nav-media.epub` plus its Pandoc 3.10 native
golden to exercise a single EPUB3 package with linear and non-linear spine
items, TOC/landmarks/page-list navigation, an emitted manifest image, an unused
manifest image, and an audio manifest item.

Added generated `package-spine-nav-media-metadata.epub` plus its Pandoc 3.10
native golden to exercise title/creator/language/subject/date metadata, two
linear spine items, TOC/landmarks/page-list navigation, a stylesheet manifest
item, and a content image resolved through the media bag.

Added generated `title-page-guide-media-metadata.epub` plus its Pandoc 3.10
native golden to exercise a title-page guide reference, TOC/landmarks/page-list
and auxiliary `loa` navigation, creator/contributor/subject metadata, a
package metadata `preview` link, a non-linear glossary spine item, a stylesheet,
and an emitted title-page image.

Verified checked-in gate:

```sh
php tools/pandoc-epub-native-ast-package.php --checked-in-fixtures summary --require-package-parity=35 --require-native-readiness=35 --require-mapped-parity=35 --require-fixture-identity --require-current-package-feature-coverage --require-current-package-feature-signature --require-current-native-ast-signature --require-runner-plan
```

Result: `packageParsedCount=35`, `readerParsedCount=35`,
`nativeParsedCount=35`, `normalizedAstMatchCount=35`, and
`normalizedAstMismatchCount=0`.

This continues not to claim upstream Haskell/Tasty runner parity; the harness
records that runner evidence as the remaining open gap.
