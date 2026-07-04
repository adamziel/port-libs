# EPUB Reader Upstream Current Fixture Gate

Date: 2026-07-02
Updated: 2026-07-04

The checked-in EPUB reader fixture subset now mirrors the upstream current
`test/epub` inventory plus generated current-upstream edge fixtures used by
the local native/package harness:

- 37 EPUB package inputs in `lanes/pandoc/fixtures/upstream-current-epub-reader/epub`
- 37 same-directory `.native` goldens for every checked-in EPUB package input

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

Added generated `nested-path-media-metadata.epub` plus its Pandoc 3.10 native
golden to exercise an `OPS/book/package.opf` rootfile, two linear spine items
with page-spread properties, one non-linear spine item, TOC/landmarks/page-list
and auxiliary `lot` navigation, metadata refinement and record-link sidecars,
a guide cover reference, local image/stylesheet assets, and a remote audio
manifest declaration. The native golden was generated with:

```sh
/opt/homebrew/bin/pandoc -f epub -t native -o lanes/pandoc/fixtures/upstream-current-epub-reader/epub/nested-path-media-metadata.native lanes/pandoc/fixtures/upstream-current-epub-reader/epub/nested-path-media-metadata.epub
```

Added generated `metadata-link-page-list-image.epub` plus its Pandoc 3.10
native golden to exercise a remote OPF metadata link with `alternate` and
`record` rel tokens, non-empty publisher/rights metadata, TOC/page-list
navigation, and an emitted manifest image. The native golden was generated with:

```sh
/opt/homebrew/bin/pandoc -f epub -t native -o lanes/pandoc/fixtures/upstream-current-epub-reader/epub/metadata-link-page-list-image.native lanes/pandoc/fixtures/upstream-current-epub-reader/epub/metadata-link-page-list-image.epub
```

Verified checked-in gate:

```sh
php tools/pandoc-epub-native-ast-package.php --checked-in-fixtures summary --require-package-parity=37 --require-native-readiness=37 --require-mapped-parity=37 --require-fixture-identity --require-current-package-feature-coverage --require-current-package-feature-signature --require-current-native-ast-signature --require-runner-plan
```

Result: `packageParsedCount=37`, `readerParsedCount=37`,
`nativeParsedCount=37`, `normalizedAstMatchCount=37`, and
`normalizedAstMismatchCount=0`.

This continues not to claim upstream Haskell/Tasty runner parity; the harness
records that runner evidence as the remaining open gap.
