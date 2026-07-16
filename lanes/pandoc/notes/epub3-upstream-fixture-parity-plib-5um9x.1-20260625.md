# EPUB3 Upstream Fixture Parity Audit - plib-5um9x.1

Date: 2026-06-25

Assigned scope: EPUB3 upstream fixture parity lane. The dispatch referenced
`lanes/pandoc/notes/epub3-supervisor-20260625.md`, but that file is absent on
`origin/main`, `origin/epub3-recovery-baseline-20260625T091320Z`, and
`origin/epub3-integration-20260625T094430`. This audit therefore uses the two
available sources named by the bead:

- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `lanes/pandoc/notes/upstream-inventory.md`

No Haskell runner was executed.

## Upstream EPUB Artifacts Checked

`UPSTREAM_TEST_MANIFEST.json` records the upstream source commit as
`0640c4c9859aa5a3ede082c190fcd5883c24ac83` and counts the EPUB subinventory as:

- `epubDirectoryArtifacts`: 11
- `epubNativeExpectedArtifacts`: 3
- `epubEpubInputArtifacts`: 8

GitHub tree metadata for that commit lists these `test/epub` artifacts:

| Upstream artifact | Kind | Bytes |
| --- | --- | ---: |
| `test/epub/epub2_cover.epub` | EPUB input | 11794 |
| `test/epub/epub2_no_cover.epub` | EPUB input | 3584 |
| `test/epub/epub2_picture.epub` | EPUB input | 11742 |
| `test/epub/features.epub` | EPUB input | 8970 |
| `test/epub/features.native` | Native expectation | 48453 |
| `test/epub/formatting.epub` | EPUB input | 14022 |
| `test/epub/formatting.native` | Native expectation | 172999 |
| `test/epub/img.epub` | EPUB input | 20478 |
| `test/epub/img_no_cover.epub` | EPUB input | 10602 |
| `test/epub/wasteland.epub` | EPUB input | 25840 |
| `test/epub/wasteland.native` | Native expectation | 150477 |

## Current PHP Coverage Mapping

The three upstream Native expectation artifacts are represented by checked-in
lane fixtures:

- `test/epub/wasteland.native` -> `fixtures/upstream-native-epub-section-slice.native`
- `test/epub/features.native` -> `fixtures/upstream-native-epub-math-slice.native`
- `test/epub/formatting.native` -> `fixtures/upstream-native-epub-default-list-style.native`

`MarkdownReaderTest.php` covers those fixtures directly:

- `maps upstream native epub section ids into wordpress html handoff`
- `maps upstream native epub display math without inline downgrade`
- `maps upstream native epub default ordered list style without coercion`

Those tests cover the mapped manifest counters:

- `mappedEpubSectionMetadataCases`: 3
- `mappedEpubCoverImageCases`: 2
- `mappedEpubSourceMarkerCases`: 2
- `mappedEpubSectionDivAttrCases`: 6
- `mappedEpubSectionWordPressCases`: 5
- `mappedEpubNativeRoundTripCases`: 1
- `mappedEpubMathDisplayCases`: 3
- `mappedEpubMathInlineCases`: 1
- `mappedEpubMathNativeRoundTripCases`: 2
- `mappedEpubMathWordPressCases`: 4
- `mappedEpubMathSectionHandoffCases`: 2
- `mappedEpubDefaultListStyleCases`: 8

The eight upstream `.epub` binary inputs are not checked in as byte fixtures,
but their direct EPUB behavior is covered by native PHP fixtures and tests:

- OCF ZIP and stored-first mimetype behavior:
  `EpubPackageTest.php` (`exposes EPUB mimetype stored-first provenance for compact import`)
  and `EpubWriterTest.php` (`writes a valid bounded epub3 package from the shared ast`).
- OPF container, metadata, manifest, spine, nav, NCX, and reading-order
  behavior:
  `EpubPackageReaderTest.php` (`maps epub container opf manifest spine and metadata handoff`,
  `maps epub nav document and ncx fallback outlines`, `maps epub page-list navigation targets for print provenance`)
  and `EpubPackageTest.php` (`preflights EPUB3 container OPF metadata manifest spine and nav handoff`,
  `falls back to NCX navigation and legacy cover metadata`).
- EPUB reader byte/package ingestion:
  `EpubReaderTest.php` (`reads epub metadata and xhtml spine content into shared ast`,
  `reads epub bytes through the converter input path`, `reads epub ncx table of contents metadata`).
- EPUB writer package generation and round trip:
  `EpubWriterTest.php` (`writes epub through the registered converter alias and reads it back`,
  `packages media resources and marks a configured cover image`,
  `packages a metadata cover image resource without a body image reference`,
  `writes nested epub nav entries from heading levels`).
- Source XHTML sections, MathML-derived math packets, image/cover behavior, and
  default ordered-list style semantics:
  the three checked-in Native EPUB fixtures and the corresponding
  `MarkdownReaderTest.php` tests listed above.

## Residual Out Of Scope

This audit does not claim:

- Running upstream Pandoc's Haskell `test-pandoc` runner.
- Byte-for-byte parity against the eight upstream `.epub` input archives.
- JavaScript runtime behavior.
- DRM, encryption/decryption, or crypto authentication.
- XML signature cryptographic validation.
- Browser engine behavior, external EPUB validators, or network resource
  resolution inside EPUB packages.

The current native PHP coverage maps all direct EPUB behavior represented by
the upstream `test/epub` inventory without introducing new non-EPUB lane work.

## Narrow Gap Patched

While rerunning focused EPUB package-reader coverage, the reader-side AST
already preserved source XHTML attributes for definition lists, table captions,
and table elements, but the WordPress handoff dropped some of those attributes.
The patch keeps the current EPUB reader surface unchanged and narrows the fix to
writer handoff of attributes already present in the AST:

- `definition_list` HTML output now preserves allowed block attributes on
  `<dl>`.
- table figure captions now preserve source caption attributes while retaining
  WordPress's `wp-element-caption` class.
- table HTML output now allows the safe legacy `border` attribute already
  represented by the EPUB XHTML fixture.

The EPUB table assertion was also made order-insensitive for equivalent HTML
attribute ordering while continuing to assert the source attributes are present.

## Verification

Passing gates:

- `php -l lanes/pandoc/src/WordPressBlockWriter.php`
- `php -l lanes/pandoc/tests/EpubPackageReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageReaderTest.php`
  (`1` file, `1829` assertions, `0` failures)
- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php lanes/pandoc/tests/EpubWriterTest.php`
  (`2` files, `219` assertions, `0` failures)
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageReaderTest.php lanes/pandoc/tests/EpubPackageTest.php lanes/pandoc/tests/EpubReaderTest.php lanes/pandoc/tests/EpubWriterTest.php`
  (`4` files, `6224` assertions, `0` failures)
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  (`1` file, `4481` assertions, `0` failures)
- `jq empty lanes/pandoc/lane-status.json`
- `git diff --check -- lanes/pandoc`

Full lane gate status:

- `php tools/run-tests.php lanes/pandoc/tests` fails outside this EPUB scope.
  The rerun log at `/tmp/pandoc-tests-plib-5um9x.1.log` begins with
  `CitationCslProcessorTest.php` failures and ends at `276` test files,
  `105639` assertions, and `10829` failures. The first failures are citation
  rendering expectations, not EPUB reader/package/writer coverage.
