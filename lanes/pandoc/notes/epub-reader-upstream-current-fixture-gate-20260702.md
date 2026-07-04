# EPUB Reader Upstream Current Fixture Gate

Date: 2026-07-02
Updated: 2026-07-04

The checked-in EPUB reader fixture subset now mirrors the upstream current
`test/epub` inventory plus generated current-upstream edge fixtures used by
the local native/package harness:

- 44 EPUB package inputs in `lanes/pandoc/fixtures/upstream-current-epub-reader/epub`
- 44 same-directory `.native` goldens for every checked-in EPUB package input

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

Added generated `bindings-collections-sidecars.epub` plus its Pandoc 3.10
native golden to exercise OPF media-type bindings with a resolved XHTML handler,
nested OPF collections with local record/content/index links, all four bounded
OCF sidecar kinds (`metadata`, `manifest`, `rights`, and `signatures`), and a
resolved custom manifest fallback while keeping the reading order XHTML-only.
The native golden was generated with:

```sh
/opt/homebrew/bin/pandoc -f epub -t native -o lanes/pandoc/fixtures/upstream-current-epub-reader/epub/bindings-collections-sidecars.native lanes/pandoc/fixtures/upstream-current-epub-reader/epub/bindings-collections-sidecars.epub
```

Added generated `manifest-href-encoding.epub` plus its Pandoc 3.10 native golden
to exercise an `OPS/book/package.opf` rootfile with percent-encoded manifest
hrefs resolving to zip entries with spaces, TOC/landmarks/page-list navigation
targets, a `text` guide reference, one non-linear spine item, a stylesheet
manifest item, and a package metadata `record` link. The native golden was
generated with:

```sh
/opt/homebrew/bin/pandoc -f epub -t native -o lanes/pandoc/fixtures/upstream-current-epub-reader/epub/manifest-href-encoding.native lanes/pandoc/fixtures/upstream-current-epub-reader/epub/manifest-href-encoding.epub
```

Added generated `spine-fallback-resource.epub` plus its Pandoc 3.10 native
golden to exercise a linear spine item whose manifest media type is custom and
declares an XHTML fallback, while TOC/landmarks/page-list navigation points at
the fallback content. Pandoc 3.10 emits only the custom spine marker for this
case. The native golden was generated with:

```sh
/opt/homebrew/bin/pandoc -f epub -t native -o lanes/pandoc/fixtures/upstream-current-epub-reader/epub/spine-fallback-resource.native lanes/pandoc/fixtures/upstream-current-epub-reader/epub/spine-fallback-resource.epub
```

Added generated `xhtml-semantics-spine.epub` plus its Pandoc 3.10 native golden
to exercise XHTML reader semantics inside the EPUB linear spine: ruby raw-inline
preservation, definition-list and table parsing, a guide `text` reference,
TOC/landmarks navigation, stylesheet handling, and creator metadata. The native
golden was generated with:

```sh
/opt/homebrew/bin/pandoc -f epub -t native -o lanes/pandoc/fixtures/upstream-current-epub-reader/epub/xhtml-semantics-spine.native lanes/pandoc/fixtures/upstream-current-epub-reader/epub/xhtml-semantics-spine.epub
```

Added generated `parent-relative-nav.epub` plus its Pandoc 3.10 native golden
to exercise an `OPS/package.opf` rootfile whose EPUB3 nav manifest item uses a
parent-relative href (`../Navigation/nav.xhtml`) while the linear spine remains
inside the package directory. The native golden was generated with:

```sh
/opt/homebrew/bin/pandoc -f epub -t native -o lanes/pandoc/fixtures/upstream-current-epub-reader/epub/parent-relative-nav.native lanes/pandoc/fixtures/upstream-current-epub-reader/epub/parent-relative-nav.epub
```

Added generated `fragment-nav-spine.epub` plus its Pandoc 3.10 native golden to
exercise a compact EPUB3 package whose TOC nav entry targets a fragment inside
the single linear XHTML spine item. The native golden was generated with:

```sh
/opt/homebrew/bin/pandoc -f epub -t native -o lanes/pandoc/fixtures/upstream-current-epub-reader/epub/fragment-nav-spine.native lanes/pandoc/fixtures/upstream-current-epub-reader/epub/fragment-nav-spine.epub
```

Added generated `standalone-footnote.epub` plus its Pandoc 3.10 native golden
to exercise current EPUB noteref behavior where a linear XHTML spine item
contains an `epub:type="noteref"` link and same-document
`epub:type="footnote"` aside. Pandoc emits a native `Note` inline with the
footnote paragraph body. The native golden was generated with:

```sh
/opt/homebrew/bin/pandoc -f epub -t native -o lanes/pandoc/fixtures/upstream-current-epub-reader/epub/standalone-footnote.native lanes/pandoc/fixtures/upstream-current-epub-reader/epub/standalone-footnote.epub
```

Verified checked-in gate:

```sh
php tools/pandoc-epub-native-ast-package.php --checked-in-fixtures summary --require-package-parity=44 --require-native-readiness=44 --require-mapped-parity=44 --require-fixture-identity --require-current-package-feature-coverage --require-current-package-feature-signature --require-current-native-ast-signature --require-runner-plan
```

Result: `packageParsedCount=44`, `readerParsedCount=44`,
`nativeParsedCount=44`, `normalizedAstMatchCount=44`, and
`normalizedAstMismatchCount=0`.

This continues not to claim upstream Haskell/Tasty runner parity; the harness
records that runner evidence as the remaining open gap.
