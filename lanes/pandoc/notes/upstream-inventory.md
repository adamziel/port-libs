# pandoc Upstream Test Inventory

Inventory source: blob-filtered shallow clone at `.upstream-cache/pandoc`.

- Upstream commit: `0640c4c9859aa5a3ede082c190fcd5883c24ac83`
- Main suite declared by `pandoc.cabal`: `test-suite test-pandoc`
- Runner shape: Haskell Tasty executable `test/test-pandoc.hs`
- License: GPL-2.0-or-later, with GPL-compatible exceptions documented in
  `COPYRIGHT`

## 2026-05-24 HTML Reader Standalone Button Slice

- Superseded by the standalone SVG slice below. Focused PHP coverage was
  419 behavior tests with 4,470 assertions.

## 2026-05-25 HTML Reader Standalone SVG Slice

- Focused PHP coverage is now 420 behavior tests with 4,477 assertions.
- Added bounded coverage for upstream `Text.Pandoc.Readers.HTML` `pPlain`
  inline behavior, `pSvg`/raw-inline SVG handling, and `TagCategories`
  `eitherBlockOrInline` classification for standalone `<svg>` fragments.
- `MarkdownReader` now admits top-level `<svg>...</svg>` fragments into the
  existing HTML inline parser. With raw HTML enabled, WordPress output keeps
  the raw SVG source markup visible for source-review handoff instead of
  treating the packet as an unmapped block. Existing in-paragraph SVG behavior
  and disabled-raw SVG data-image fallback remain unchanged.
- Added `fixtures/upstream-html-standalone-svg-inline.html` and
  `examples/wordpress-native-html-standalone-svg-handoff.php` for a WordPress
  Data Liberation handoff where imported source icons remain auditable as raw
  SVG in a paragraph.
- Dependency-closure checkpoint: this slice remains lane-local on existing
  `DOMDocument` parsing. It does not activate `xml-html5-dom-core`,
  `shared-zip-package-core`, OpenXML/OpenDocument package parsing, PDF
  handoff/text extraction, citation/CSL, PlainMath/MathML conversion, TeX
  reference conversion, Unicode/charset, or syntax-highlighting support rows.
  Broader HTML5 tree-construction, SVG sanitization policy, image extraction,
  and arbitrary browser DOM behavior remain unclaimed.
- Focused evidence: targeted `git -C .upstream-cache/pandoc show` inspected
  `src/Text/Pandoc/Readers/HTML.hs` and
  `src/Text/Pandoc/Readers/HTML/TagCategories.hs`; `php -l` passed for
  `MarkdownReader.php`, `MarkdownReaderTest.php`, and
  `wordpress-native-html-standalone-svg-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-html-standalone-svg-handoff.php`
  emitted WordPress paragraph raw SVG markup; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 4,477 assertions,
  0 failures. No no-argument root harness was assigned or started.

- Focused PHP coverage is now 419 behavior tests with 4,470 assertions.
- Added bounded coverage for upstream `Text.Pandoc.Readers.HTML` `pPlain`
  inline behavior, `pRawHtmlInline` fallback, and `TagCategories`
  `eitherBlockOrInline` classification for standalone `<button>` fragments.
- `MarkdownReader` now admits top-level `<button>...</button>` fragments into
  the existing HTML inline parser, preserving raw HTML button boundaries while
  parsing nested inline content such as `<strong>`. The older indented
  list-item button handling remains raw-block compatible for the mapped
  upstream list-item raw HTML tests.
- Added `fixtures/upstream-html-standalone-button-inline.html` and
  `examples/wordpress-native-html-standalone-button-handoff.php` for a
  WordPress Data Liberation handoff where imported classic-editor review
  controls remain active and auditable.
- Dependency-closure checkpoint: this slice remains lane-local on existing
  `DOMDocument` parsing. It does not activate `xml-html5-dom-core`,
  `shared-zip-package-core`, OpenXML/OpenDocument package parsing, PDF
  handoff/text extraction, citation/CSL, PlainMath/MathML conversion, TeX
  reference conversion, Unicode/charset, or syntax-highlighting support rows.
  Broader HTML5 tree-construction, form submission semantics, and browser
  interaction behavior remain unclaimed.
- Focused evidence: targeted `git -C .upstream-cache/pandoc show` inspected
  `src/Text/Pandoc/Readers/HTML.hs` and
  `src/Text/Pandoc/Readers/HTML/TagCategories.hs`; `php -l` passed for
  `MarkdownReader.php`, `MarkdownReaderTest.php`, and
  `wordpress-native-html-standalone-button-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-html-standalone-button-handoff.php`
  emitted WordPress paragraph button markup; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 4,470 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 HTML Reader Standalone Ins Slice

- Superseded by the standalone button slice above. Focused PHP coverage was
  418 behavior tests with 4,460 assertions.

- Focused PHP coverage is now 418 behavior tests with 4,460 assertions.
- Added bounded coverage for upstream `Text.Pandoc.Readers.HTML` `pPlain`
  inline behavior and `TagCategories` `eitherBlockOrInline` classification
  for standalone `<ins>` fragments.
- `MarkdownReader` already routes top-level `<ins>...</ins>` fragments
  through the HTML inline parser; this slice records the native semantics:
  inserted editorial text lowers to the existing underline AST and WordPress
  `<u>` paragraph handoff.
- Added `fixtures/upstream-html-standalone-ins-inline.html` and
  `examples/wordpress-native-html-standalone-ins-handoff.php` for a WordPress
  Data Liberation review packet where imported insertion text remains visible
  for editorial audit.
- Dependency-closure checkpoint: this slice remains lane-local on existing
  `DOMDocument` parsing. It does not activate `xml-html5-dom-core`,
  `shared-zip-package-core`, OpenXML/OpenDocument package parsing, PDF
  handoff/text extraction, citation/CSL, PlainMath/MathML conversion, TeX
  reference conversion, Unicode/charset, or syntax-highlighting support rows.
  Broader HTML5 tree-construction, change-tracking metadata preservation for
  arbitrary HTML, and DOCX/OpenXML review metadata parsing remain unclaimed.
- Focused evidence: targeted `git -C .upstream-cache/pandoc show` inspected
  `src/Text/Pandoc/Readers/HTML.hs` and
  `src/Text/Pandoc/Readers/HTML/TagCategories.hs`; `php -l` passed for
  `MarkdownReader.php`, `MarkdownReaderTest.php`, and
  `wordpress-native-html-standalone-ins-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-html-standalone-ins-handoff.php`
  emitted WordPress paragraph underline markup; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 4,460 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 HTML Reader Standalone Noscript Slice

- Focused PHP coverage is now 417 behavior tests with 4,455 assertions.
- Added bounded coverage for upstream `Text.Pandoc.Readers.HTML` `pPlain`
  plus inline raw-HTML fallback behavior and `TagCategories`
  `eitherBlockOrInline` classification for standalone `<noscript>` fragments.
- `MarkdownReader` now admits top-level `<noscript>...</noscript>` fragments
  into the existing HTML inline parser and preserves raw HTML noscript
  boundaries when raw HTML is enabled. WordPress output keeps script-disabled
  fallback links reviewable instead of escaping them as literal text.
- Added `fixtures/upstream-html-standalone-noscript-inline.html` and
  `examples/wordpress-native-html-standalone-noscript-handoff.php` for a
  WordPress Data Liberation handoff where fallback import links stay visible as
  active paragraph HTML.
- Dependency-closure checkpoint: this slice remains lane-local on existing
  `DOMDocument` parsing. It does not activate `xml-html5-dom-core`,
  `shared-zip-package-core`, OpenXML/OpenDocument package parsing, PDF
  handoff/text extraction, citation/CSL, PlainMath/MathML conversion, TeX
  reference conversion, Unicode/charset, or syntax-highlighting support rows.
  Broader HTML5 tree-construction, script execution policy, and arbitrary
  noscript browser rendering semantics remain unclaimed.
- Focused evidence: targeted `git -C .upstream-cache/pandoc show` inspected
  `src/Text/Pandoc/Readers/HTML.hs` and
  `src/Text/Pandoc/Readers/HTML/TagCategories.hs`; `php -l` passed for
  `MarkdownReader.php`, `MarkdownReaderTest.php`, and
  `wordpress-native-html-standalone-noscript-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-html-standalone-noscript-handoff.php`
  emitted WordPress paragraph noscript markup; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 4,455 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 HTML Reader Standalone Applet Slice

- Focused PHP coverage is now 416 behavior tests with 4,448 assertions.
- Added bounded coverage for upstream `Text.Pandoc.Readers.HTML` `pPlain`
  plus inline raw-HTML fallback behavior and `TagCategories`
  `eitherBlockOrInline` classification for standalone `<applet>` fragments.
- `MarkdownReader` now admits top-level `<applet>...</applet>` fragments into
  the existing HTML inline parser and preserves raw HTML applet boundaries
  when raw HTML is enabled. WordPress output keeps legacy Java applet handoff
  markup reviewable instead of escaping it as literal text.
- Added `fixtures/upstream-html-standalone-applet-inline.html` and
  `examples/wordpress-native-html-standalone-applet-handoff.php` for a
  WordPress Data Liberation handoff where legacy applet fallback text and
  source boundaries stay visible as active paragraph HTML.
- Dependency-closure checkpoint: this slice remains lane-local on existing
  `DOMDocument` parsing. It does not activate `xml-html5-dom-core`,
  `shared-zip-package-core`, OpenXML/OpenDocument package parsing, PDF
  handoff/text extraction, citation/CSL, PlainMath/MathML conversion, TeX
  reference conversion, Unicode/charset, or syntax-highlighting support rows.
  Broader HTML5 tree-construction, arbitrary applet parameter handling, and
  browser/plugin execution semantics remain unclaimed.
- Focused evidence: targeted `git -C .upstream-cache/pandoc show` inspected
  `src/Text/Pandoc/Readers/HTML.hs` and
  `src/Text/Pandoc/Readers/HTML/TagCategories.hs`; `php -l` passed for
  `MarkdownReader.php`, `MarkdownReaderTest.php`, and
  `wordpress-native-html-standalone-applet-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-html-standalone-applet-handoff.php`
  emitted WordPress paragraph applet markup; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 4,448 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 HTML Reader Standalone Object/Embed Slice

- Superseded by the standalone applet slice above. Focused PHP coverage was
  415 behavior tests with 4,442 assertions for this slice.
- Added bounded coverage for upstream `Text.Pandoc.Readers.HTML` `pPlain`
  plus inline raw-HTML fallback behavior and `TagCategories`
  `eitherBlockOrInline` classification for standalone `<object>` fragments
  with `<embed>` fallback children.
- `MarkdownReader` now admits top-level `<object>...</object>` fragments into
  the existing HTML inline parser and preserves raw HTML object/embed
  boundaries when raw HTML is enabled. WordPress output keeps legacy
  interactive embed markup reviewable instead of escaping it as literal text.
- Added `fixtures/upstream-html-standalone-object-embed-inline.html` and
  `examples/wordpress-native-html-standalone-object-handoff.php` for a
  WordPress Data Liberation handoff where classic-editor object/embed packets
  remain visible as active paragraph HTML.
- Dependency-closure checkpoint: this slice remains lane-local on existing
  `DOMDocument` parsing. It does not activate `xml-html5-dom-core`,
  `shared-zip-package-core`, OpenXML/OpenDocument package parsing, PDF
  handoff/text extraction, citation/CSL, PlainMath/MathML conversion, TeX
  reference conversion, Unicode/charset, or syntax-highlighting support rows.
  Broader HTML5 tree-construction, arbitrary media/object fallback semantics,
  and full plugin/embed DOM policy remain unclaimed.
- Focused evidence: targeted `git -C .upstream-cache/pandoc show` inspected
  `src/Text/Pandoc/Readers/HTML.hs` and
  `src/Text/Pandoc/Readers/HTML/TagCategories.hs`; `php -l` passed for
  `MarkdownReader.php`, `MarkdownReaderTest.php`, and
  `wordpress-native-html-standalone-object-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-html-standalone-object-handoff.php`
  emitted WordPress paragraph object/embed markup; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 4,442 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 HTML Reader Standalone Video/Source/Track Slice

- Superseded by the standalone object/embed slice above. Focused PHP coverage
  was 414 behavior tests with 4,435 assertions for this slice.
- Added bounded coverage for upstream `Text.Pandoc.Readers.HTML` `pPlain`
  plus inline raw-HTML fallback behavior and `TagCategories`
  `eitherBlockOrInline` classification for standalone `<video>` fragments
  with `<source>` and `<track>` children.
- `MarkdownReader` now admits top-level `<video>...</video>` fragments into
  the existing HTML inline parser and preserves raw HTML video/source/track
  boundaries when raw HTML is enabled. WordPress output keeps active imported
  video markup and caption-track metadata instead of escaping it as literal
  text.
- Added `fixtures/upstream-html-standalone-video-inline.html` and
  `examples/wordpress-native-html-standalone-video-handoff.php` for a
  WordPress Data Liberation handoff where classic-editor video embeds stay
  playable during review.
- Dependency-closure checkpoint: this slice remains lane-local on existing
  `DOMDocument` parsing. It does not activate `xml-html5-dom-core`,
  `shared-zip-package-core`, OpenXML/OpenDocument package parsing, PDF
  handoff/text extraction, citation/CSL, PlainMath/MathML conversion, TeX
  reference conversion, Unicode/charset, or syntax-highlighting support rows.
  Broader HTML5 tree-construction, arbitrary media/object fallback semantics,
  and full media DOM policy remain unclaimed.
- Focused evidence: targeted `git -C .upstream-cache/pandoc show` inspected
  `src/Text/Pandoc/Readers/HTML.hs` and
  `src/Text/Pandoc/Readers/HTML/TagCategories.hs`; `php -l` passed for
  `MarkdownReader.php`, `MarkdownReaderTest.php`, and
  `wordpress-native-html-standalone-video-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-html-standalone-video-handoff.php`
  emitted WordPress paragraph video markup; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 4,435 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 HTML Reader Standalone Audio/Source/Track Slice

- Superseded by the standalone video/source/track slice above. Focused PHP
  coverage was 413 behavior tests with 4,427 assertions for this slice.
- Added bounded coverage for upstream `Text.Pandoc.Readers.HTML` `pPlain`
  plus inline raw-HTML fallback behavior and `TagCategories`
  `eitherBlockOrInline` classification for standalone `<audio>` fragments
  with `<source>` and `<track>` children.
- `MarkdownReader` now admits top-level `<audio>...</audio>` fragments into
  the existing HTML inline parser and preserves raw HTML audio/source/track
  boundaries when raw HTML is enabled. WordPress output keeps active imported
  audio markup and caption-track metadata instead of escaping it as literal
  text.
- Added `fixtures/upstream-html-standalone-audio-inline.html` and
  `examples/wordpress-native-html-standalone-audio-handoff.php` for a
  WordPress Data Liberation handoff where classic-editor audio embeds stay
  playable during review.
- Dependency-closure checkpoint: this slice remains lane-local on existing
  `DOMDocument` parsing. It does not activate `xml-html5-dom-core`,
  `shared-zip-package-core`, OpenXML/OpenDocument package parsing, PDF
  handoff/text extraction, citation/CSL, PlainMath/MathML conversion, TeX
  reference conversion, Unicode/charset, or syntax-highlighting support rows.
  Broader HTML5 tree-construction, arbitrary media/object fallback semantics,
  and full media DOM policy remain unclaimed.
- Focused evidence: targeted `git -C .upstream-cache/pandoc show` inspected
  `src/Text/Pandoc/Readers/HTML.hs` and
  `src/Text/Pandoc/Readers/HTML/TagCategories.hs`; `php -l` passed for
  `MarkdownReader.php`, `MarkdownReaderTest.php`, and
  `wordpress-native-html-standalone-audio-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-html-standalone-audio-handoff.php`
  emitted WordPress paragraph audio markup; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 4,427 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 HTML Reader Standalone Map/Area Slice

- Superseded by the standalone audio/source/track slice above. Focused PHP
  coverage was 412 behavior tests with 4,419 assertions for this slice.
- Added bounded coverage for upstream `Text.Pandoc.Readers.HTML` `pPlain`
  plus inline raw-HTML fallback behavior and `TagCategories`
  `eitherBlockOrInline` classification for standalone `<map>` fragments with
  `<area>` children. Targeted source inspection covered `pPlain`, inline
  fallback through `pRawHtmlInline`, `isBlockTag`, `isInlineTag`, and the
  `eitherBlockOrInline` row that includes `map` and `area`; targeted
  inspection counted 6 focused source hits.
- `MarkdownReader` now admits top-level `<map>...</map>` fragments into the
  existing HTML inline parser and preserves raw HTML map/area boundaries when
  raw HTML is enabled. WordPress output keeps active image-map hotspot markup
  instead of escaping it as literal text.
- Added `fixtures/upstream-html-standalone-map-inline.html` and
  `examples/wordpress-native-html-standalone-map-handoff.php` for a WordPress
  Data Liberation handoff where classic-editor image-map hotspots stay visible
  as active paragraph HTML.
- Dependency-closure checkpoint: this slice remains lane-local on existing
  `DOMDocument` parsing. It does not activate `xml-html5-dom-core`,
  `shared-zip-package-core`, OpenXML/OpenDocument package parsing, PDF
  handoff/text extraction, citation/CSL, PlainMath/MathML conversion, TeX
  reference conversion, Unicode/charset, or syntax-highlighting support rows.
  Broader HTML5 tree-construction, arbitrary either-block-or-inline
  classification, standalone anchor reconciliation, and full image-map DOM
  semantics remain unclaimed.
- Focused evidence: targeted `git -C .upstream-cache/pandoc show` inspected
  `src/Text/Pandoc/Readers/HTML.hs` and
  `src/Text/Pandoc/Readers/HTML/TagCategories.hs`; `php -l` passed for
  `MarkdownReader.php`, `MarkdownReaderTest.php`, and
  `wordpress-native-html-standalone-map-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-html-standalone-map-handoff.php`
  emitted WordPress paragraph image-map markup; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 4,419 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 HTML Reader Standalone Del Slice

- Superseded by the standalone progress and map/area slices below. Focused PHP coverage was
  410 behavior tests with 4,407 assertions for this slice.
- Added bounded coverage for upstream `Text.Pandoc.Readers.HTML` `pPlain`
  plus inline dispatch behavior and `TagCategories` `eitherBlockOrInline`
  classification for standalone `<del>` fragments. Targeted source inspection
  covered `pPlain`, inline `s`/`strike`/`del` strikeout handling, and the
  `eitherBlockOrInline` row that includes `del`; targeted inspection counted 5
  focused source hits.
- `MarkdownReader` now admits top-level `<del>...</del>` fragments into the
  existing HTML inline parser. The parsed AST keeps native `strikeout` inlines
  and WordPress output emits paragraph `<del>` markup instead of escaped tags
  or raw block boundaries.
- Added `fixtures/upstream-html-standalone-del-inline.html` and
  `examples/wordpress-native-html-standalone-del-handoff.php` for a WordPress
  Data Liberation handoff where classic-editor deletion markup remains active
  reviewer HTML alongside inserted replacement copy.
- Dependency-closure checkpoint: this slice remains lane-local on existing
  `DOMDocument` parsing. It does not activate `xml-html5-dom-core`,
  `shared-zip-package-core`, OpenXML/OpenDocument package parsing, PDF
  handoff/text extraction, citation/CSL, PlainMath/MathML conversion, TeX
  reference conversion, Unicode/charset, or syntax-highlighting support rows.
  Broader HTML5 tree-construction, standalone anchor reconciliation, arbitrary
  inline raw HTML flow, and full del/ins block-container ambiguity remain
  unclaimed.
- Focused evidence: targeted `git -C .upstream-cache/pandoc show` inspected
  `src/Text/Pandoc/Readers/HTML.hs` and
  `src/Text/Pandoc/Readers/HTML/TagCategories.hs`; `php -l` passed for
  `MarkdownReader.php`, `MarkdownReaderTest.php`, and
  `wordpress-native-html-standalone-del-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-html-standalone-del-handoff.php`
  emitted WordPress paragraph deletion/insert markup; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 4,407 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 HTML Reader Standalone Progress Slice

- Superseded by the standalone map/area slice above.
- Focused PHP coverage is now 411 behavior tests with 4,413 assertions.
- Added bounded coverage for upstream `Text.Pandoc.Readers.HTML` `pPlain`
  plus inline raw-HTML fallback behavior and `TagCategories`
  `eitherBlockOrInline` classification for standalone `<progress>` fragments.
  Targeted source inspection covered `pPlain`, inline fallback through
  `pRawHtmlInline`, `isBlockTag`, and the `eitherBlockOrInline` row that
  includes `progress`; targeted inspection counted 6 focused source hits.
- `MarkdownReader` now admits top-level `<progress>...</progress>` fragments
  into the existing HTML inline parser and preserves raw HTML open/close
  boundaries around text content when raw HTML is enabled. WordPress output
  keeps active progress markup instead of dropping to text-only paragraph
  output.
- Added `fixtures/upstream-html-standalone-progress-inline.html` and
  `examples/wordpress-native-html-standalone-progress-handoff.php` for a
  WordPress Data Liberation handoff where import/review progress indicators
  stay visible as active paragraph HTML.
- Dependency-closure checkpoint: this slice remains lane-local on existing
  `DOMDocument` parsing. It does not activate `xml-html5-dom-core`,
  `shared-zip-package-core`, OpenXML/OpenDocument package parsing, PDF
  handoff/text extraction, citation/CSL, PlainMath/MathML conversion, TeX
  reference conversion, Unicode/charset, or syntax-highlighting support rows.
  Broader HTML5 tree-construction, arbitrary either-block-or-inline
  classification, standalone anchor reconciliation, and full form-control DOM
  semantics remain unclaimed.
- Focused evidence: targeted `git -C .upstream-cache/pandoc show` inspected
  `src/Text/Pandoc/Readers/HTML.hs` and
  `src/Text/Pandoc/Readers/HTML/TagCategories.hs`; `php -l` passed for
  `MarkdownReader.php`, `MarkdownReaderTest.php`, and
  `wordpress-native-html-standalone-progress-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-html-standalone-progress-handoff.php`
  emitted WordPress paragraph progress markup; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 4,413 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 HTML Reader Standalone Linebreak Slice

- Focused PHP coverage is now 409 behavior tests with 4,405 assertions.
- Added bounded coverage for upstream `Text.Pandoc.Readers.HTML` `pPlain`
  plus `pLineBreak` behavior when a source packet starts with a standalone
  HTML `<br>` fragment. Targeted source/fixture inspection covered `pPlain`,
  the `inline` dispatch branch for `"br" -> pLineBreak`, the `pLineBreak`
  definition, and command fixtures `test/command/2874.md` and
  `test/command/3619.md`; targeted grep counted 7 focused source/fixture
  hits.
- `MarkdownReader` now routes top-level `<br/>`, `<br>text`, and attributed
  `<br>` fragments through the existing HTML inline parser instead of treating
  them as literal Markdown text. The parsed AST keeps native `linebreak`
  inlines, and WordPress output emits paragraph `<br/>` markup.
- Added `fixtures/upstream-html-standalone-linebreak.html` and
  `examples/wordpress-native-html-standalone-linebreak-handoff.php` for a
  WordPress Data Liberation handoff where classic-editor line-break
  placeholders stay active reviewer HTML instead of escaped tags.
- Dependency-closure checkpoint: this slice remains lane-local on existing
  `DOMDocument` parsing. It does not activate `xml-html5-dom-core`,
  `shared-zip-package-core`, OpenXML/OpenDocument package parsing, PDF
  handoff/text extraction, citation/CSL, PlainMath/MathML conversion, TeX
  reference conversion, Unicode/charset, or syntax-highlighting support rows.
  Broader standalone anchor/html-flow reconciliation and arbitrary HTML5
  tree-construction parity remain unclaimed.
- Focused evidence: targeted `git -C .upstream-cache/pandoc show` inspected
  `src/Text/Pandoc/Readers/HTML.hs` around `pPlain`, `inline`, and
  `pLineBreak`; targeted `git -C .upstream-cache/pandoc grep` inspected
  `test/command/2874.md` and `test/command/3619.md`; `php -l` passed for
  `MarkdownReader.php`, `MarkdownReaderTest.php`, and
  `wordpress-native-html-standalone-linebreak-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-html-standalone-linebreak-handoff.php`
  emitted WordPress paragraph line breaks; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 4,405 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 HTML Reader Standalone Inline Flow Slice

- Focused PHP coverage is now 408 behavior tests with 4,390 assertions.
- Added bounded coverage for upstream `Text.Pandoc.Readers.HTML` `pPlain` plus
  `inline` dispatch behavior when a source packet starts with balanced inline
  HTML rather than a block tag. Targeted source inspection covered `pPlain`,
  the `inline` case dispatch, `pSmall`, `pSuperscript`, `pSubscript`,
  `pSpanLike`, `pQ`, `pBdo`, `pRawHtmlInline`, and `pInlinesInTags`; targeted
  `git show | rg` counted 37 focused source hits.
- `MarkdownReader` now routes bounded standalone inline fragments through the
  existing HTML inline parser instead of treating them as literal Markdown text.
  The accepted slice covers `<small>`, `<sup>`, `span.smallcaps`, `<time>`,
  `<q cite>`, and `<cite>`. Standalone `<a>` is deliberately excluded because
  a prior Markdown raw-HTML anchor boundary test expects that top-level form to
  remain raw HTML unless a future slice reconciles both behaviors explicitly.
- Added `fixtures/upstream-html-standalone-inline-flow.html` and
  `examples/wordpress-native-html-standalone-inline-flow-handoff.php` for a
  WordPress Data Liberation handoff where classic-editor inline fragments become
  WordPress paragraphs with fine print, small-caps terms, time metadata, quoted
  sources, and cite markup preserved.
- Dependency-closure checkpoint: this slice remains lane-local on existing
  `DOMDocument` parsing. It does not activate `xml-html5-dom-core`,
  `shared-zip-package-core`, OpenXML/OpenDocument package parsing, PDF
  handoff/text extraction, citation/CSL, PlainMath/MathML conversion, TeX
  reference conversion, Unicode/charset, or syntax-highlighting support rows.
  Broader HTML5 tree-construction, standalone anchor reconciliation, and
  arbitrary inline raw HTML flow remain unclaimed.
- Focused evidence: `php -l` passed for `MarkdownReader.php`,
  `MarkdownReaderTest.php`, and
  `wordpress-native-html-standalone-inline-flow-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-html-standalone-inline-flow-handoff.php`
  emitted WordPress paragraph HTML for the fixture; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 4,390 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 HTML Reader Cite/Wbr Raw Inline Slice

- Focused PHP coverage is now 407 behavior tests with 4,358 assertions.
- Added bounded coverage for upstream `Text.Pandoc.Readers.HTML`
  `pRawHtmlInline` fallback behavior for inline tags that are not handled by a
  richer semantic branch. Targeted source inspection covered the default
  `inline` fall-through to `pRawHtmlInline`, the `Ext_raw_html` raw-inline
  branch, the disabled-extension `ignore raw` branch, and `isInlineTag` /
  `isBlockTag` classification; targeted grep counted 12 focused source hits.
- `MarkdownReader` now preserves `<cite>` open/close boundaries around parsed
  child inlines and preserves `<wbr>` as a raw inline boundary while keeping
  the text that libxml attaches under the void-like element. With raw HTML
  disabled, the same bounded slice drops the raw boundaries but keeps parsed
  child text/emphasis.
- Added `fixtures/upstream-html-cite-wbr-raw-inline.html` and
  `examples/wordpress-native-html-cite-wbr-handoff.php` for a WordPress Data
  Liberation handoff where imported source-title markup and long-slug word
  break hints remain visible in reviewer HTML.
- Dependency-closure checkpoint: this slice remains lane-local on existing
  `DOMDocument` parsing. It does not activate `xml-html5-dom-core`,
  `shared-zip-package-core`, OpenXML/OpenDocument package parsing, PDF
  handoff/text extraction, citation/CSL, PlainMath/MathML conversion, TeX
  reference conversion, Unicode/charset, or syntax-highlighting support rows.
  Full HTML5 raw inline fallback parity beyond the bounded generic tags remains
  unclaimed.
- Focused evidence: targeted `git -C .upstream-cache/pandoc show` and
  `git -C .upstream-cache/pandoc grep` inspected
  `src/Text/Pandoc/Readers/HTML.hs`; `php -l` passed for
  `MarkdownReader.php`, `MarkdownReaderTest.php`, and
  `wordpress-native-html-cite-wbr-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-html-cite-wbr-handoff.php` emitted
  WordPress paragraph HTML with preserved `<cite>` and `<wbr>` boundaries;
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1
  file, 4,358 assertions, 0 failures. No no-argument root harness was assigned
  or started.

## 2026-05-24 HTML Reader Pre/Code Break Slice

- Focused PHP coverage is now 406 behavior tests with 4,336 assertions.
- Added bounded coverage for upstream `Text.Pandoc.Readers.HTML` `pCodeBlock`
  and `tagToText` behavior: `TagText` contributes literal text, `TagOpen "br"`
  contributes a newline, and `<pre>` content imports as a native `CodeBlock`.
  Targeted source inspection covered `src/Text/Pandoc/Readers/HTML.hs` lines
  around `pCodeBlock`, `matchTagOpen "pre"`, `matchTagOpen "code"`,
  `tagToText`, `TagText`, `TagOpen "br"`, and `codeBlockWith`, counting 9
  focused source branch lines.
- Strengthened the static upstream inventory with a local native smoke pass:
  `NativeReader` parsed 252/252 upstream `.native` fixtures under `test/`, and
  all 252 rendered through `WordPressBlockWriter`, `HtmlWriter`, and
  `MarkdownWriter`. This is inventory/smoke evidence, not full Haskell runner
  parity.
- `MarkdownReader` now preserves `<br>` as newlines inside HTML pre/code source
  snippets, imports bare `<pre>` exports as code blocks, and keeps the existing
  upstream-like attribute precedence where `<pre>` attributes win over nested
  `<code>` attributes.
- Added `fixtures/upstream-html-pre-code-br.html` and
  `examples/wordpress-native-html-pre-code-breaks-handoff.php` for a WordPress
  Data Liberation handoff where classic-editor code exports keep reviewer line
  breaks and bare preformatted exports become WordPress code blocks.
- Dependency-closure checkpoint: this slice remains lane-local on existing
  `DOMDocument` parsing. It does not activate `xml-html5-dom-core`,
  `shared-zip-package-core`, OpenXML/OpenDocument package parsing, PDF
  handoff/text extraction, citation/CSL, PlainMath/MathML conversion, TeX
  reference conversion, Unicode/charset, or syntax-highlighting support rows.
  General HTML5 tree-construction and broader malformed-HTML parser parity
  remain unclaimed.
- Focused evidence: targeted `git -C .upstream-cache/pandoc show` and
  `git -C .upstream-cache/pandoc grep` inspected
  `src/Text/Pandoc/Readers/HTML.hs`; `php -l` passed for
  `MarkdownReader.php`, `MarkdownReaderTest.php`, and
  `wordpress-native-html-pre-code-breaks-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-html-pre-code-breaks-handoff.php`
  emitted WordPress code blocks with preserved line breaks; `php
  tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file,
  4,336 assertions, 0 failures. No no-argument root harness was assigned or
  started.

## 2026-05-24 WordPress Writer DOCX Nested Link Label Slice

- Focused PHP coverage is now 405 behavior tests with 4,316 assertions.
- Added bounded coverage for upstream `test/docx/nested_anchors_in_header.native`
  and Pandoc writer nested-link handling. The upstream DOCX fixture has 15
  lines and 10 `Link` constructors, including outer TOC/cross-reference links
  whose labels contain inner page-number links. Targeted source inspection
  covered `Text.Pandoc.Writers.Shared.removeLinks` and the `Writer.HTML`
  `Link` call sites that use `removeLinks` before rendering anchor labels.
- `WordPressBlockWriter` now applies the same no-nested-anchor rule to link
  labels: nested `link` inline nodes are rendered as `span` nodes with link
  target/title fields removed, while the outer link remains active. This keeps
  DOCX-generated TOC and cross-reference labels valid for WordPress HTML.
- Added `fixtures/upstream-native-docx-nested-anchors-header.native` and
  `examples/wordpress-native-docx-nested-links-handoff.php` for a WordPress
  Data Liberation handoff where page-number labels are spans inside the outer
  source links instead of nested anchors.
- Dependency-closure checkpoint: this slice remains lane-local. It does not
  activate `shared-zip-package-core`, `xml-html5-dom-core`, OpenXML package
  parsing, PDF handoff/text extraction, citation/CSL, PlainMath/MathML
  conversion, TeX reference conversion, Unicode/charset, or syntax-highlighting
  support rows. Direct DOCX package ingestion remains behind the shared
  ZIP/OpenXML gate; this slice maps a copied upstream Native fixture only.
- Focused evidence: targeted `git -C .upstream-cache/pandoc show` and
  `git -C .upstream-cache/pandoc grep` inspected
  `test/docx/nested_anchors_in_header.native`,
  `src/Text/Pandoc/Writers/Shared.hs`,
  `src/Text/Pandoc/Writers/HTML.hs`,
  `test/Tests/Readers/Docx.hs`, and `test/Tests/Writers/Docx.hs`. `php -l`
  passed for `WordPressBlockWriter.php`, `MarkdownReaderTest.php`, and
  `wordpress-native-docx-nested-links-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-docx-nested-links-handoff.php` emitted
  outer WordPress links with inner page labels as spans; `php
  tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file,
  4,316 assertions, 0 failures. No no-argument root harness was assigned or
  started.

## 2026-05-24 Markdown Writer GFM Details List Slice

- Focused PHP coverage is now 404 behavior tests with 4,302 assertions.
- Added bounded coverage for upstream command fixture `test/command/9792.md`,
  where Pandoc `-t gfm` inserts blank lines around a nested list enclosed by
  raw `<details>` boundaries inside a list item. The static upstream checkpoint
  inspected `Text.Pandoc.Writers.Markdown` `fixBlocks`/`RawBlock`/list
  branches and counted 21 lines in `test/command/9792.md` plus 31 focused
  source/fixture hits for `fixBlocks`, `RawBlock`, list blocks, and
  `details`.
- `MarkdownWriter` now keeps the default Markdown writer behavior unchanged
  while adding the GFM-specific blank line before a nested list after a raw
  `<details>` opening block, and before a raw `</details>` closing block after
  that nested list.
- Added `fixtures/upstream-command-gfm-details-list.md` and
  `examples/wordpress-markdown-gfm-details-list-handoff.php` for a WordPress
  reviewer handoff where the same AST can produce GFM-safe disclosure markup
  and active WordPress list/raw-HTML blocks.
- Dependency-closure checkpoint: this slice remains lane-local. It does not
  activate `xml-html5-dom-core`, shared ZIP/OpenXML/OpenDocument, PDF
  handoff/text extraction, citation/CSL, PlainMath/MathML conversion,
  TeX reference conversion, Unicode/charset, or syntax-highlighting support
  rows. General HTML5 tree-construction, arbitrary raw block container parsing,
  and broader CommonMark/GFM raw HTML container rules remain unclaimed.
- Focused evidence: targeted `git -C .upstream-cache/pandoc show` and
  `git -C .upstream-cache/pandoc grep` inspected `test/command/9792.md`,
  `src/Text/Pandoc/Writers/Markdown.hs`, and
  `src/Text/Pandoc/Writers/CommonMark.hs`; `test/command/9792.md` has 21
  lines and the focused grep counted 31 source/fixture hits. `php -l` passed
  for `MarkdownWriter.php`, `MarkdownReaderTest.php`, and
  `wordpress-markdown-gfm-details-list-handoff.php`; `php
  lanes/pandoc/examples/wordpress-markdown-gfm-details-list-handoff.php`
  emitted the expected GFM reviewer handoff and WordPress list/raw-HTML blocks;
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed
  1 file, 4,302 assertions, 0 failures. No no-argument root harness was
  assigned or started.

## 2026-05-24 Markdown Details/Summary Raw HTML Slice

- Focused PHP coverage is now 403 behavior tests with 4,297 assertions.
- Added bounded coverage for upstream command fixture `test/command/6385.md`,
  where Pandoc preserves `<details>`/`<summary>` raw HTML boundaries while the
  details body remains Markdown-parsed content. The static upstream checkpoint
  also inspected `HTML/TagCategories.hs`, where `details` and `summary` are
  block HTML tags, plus `test/command/9792.md` for the related list-contained
  details fixture.
- `MarkdownReader` now recognizes a balanced top-level `<details>` block,
  emits raw HTML boundaries for the `details` and `summary` tags when raw HTML
  is enabled, and recursively parses the remaining body as Markdown paragraphs
  and inline markup. With raw HTML disabled, the slice drops the raw boundaries
  and keeps the summary text/body content for review.
- Added `fixtures/upstream-command-details-summary.md` and
  `examples/wordpress-markdown-details-summary-handoff.php` for a WordPress
  Data Liberation handoff where imported disclosure widgets are no longer
  escaped as paragraph text, while their body copy remains editable paragraph
  content.
- Dependency-closure checkpoint: this slice remains lane-local. It does not
  activate `xml-html5-dom-core`, shared ZIP/OpenXML/OpenDocument, PDF
  handoff/text extraction, citation/CSL, PlainMath/MathML conversion,
  TeX reference conversion, Unicode/charset, or syntax-highlighting support
  rows. General HTML5 tree-construction and arbitrary raw block container
  parsing remain unclaimed; the related list-nested GFM writer formatting is
  covered by the later `test/command/9792.md` slice above.
- Focused evidence: targeted `git -C .upstream-cache/pandoc grep` counted 17
  `details`/`summary` hits across `test/command/6385.md`,
  `test/command/9792.md`, `src/Text/Pandoc/Readers/HTML/TagCategories.hs`,
  and `src/Text/Pandoc/Readers/HTML.hs`; `test/command/6385.md` has 18 lines.
  `php -l` passed for `MarkdownReader.php`, `MarkdownReaderTest.php`, and
  `wordpress-markdown-details-summary-handoff.php`; `php
  lanes/pandoc/examples/wordpress-markdown-details-summary-handoff.php` emitted
  active WordPress raw HTML blocks for `<details>`/`<summary>` plus editable
  paragraph body blocks; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 4,297 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 HTML Reader Orphan List Block Slice

- Focused PHP coverage is now 402 behavior tests with 4,276 assertions.
- Added bounded coverage for upstream `Text.Pandoc.Readers.HTML`
  `pBulletList`, `pListItem`, and `pOrderedList` orphan handling around
  `#9187`. Upstream treats direct block children under `ul`/`ol` outside
  `<li>` scope as list content: leading orphans become the first list item,
  while orphans after a list item are concatenated into that item.
- `MarkdownReader` now imports direct orphan paragraphs, nested lists, and
  block continuations in source order. This keeps malformed source-export list
  content attached to native `list_item` nodes instead of dropping it.
- Added `fixtures/upstream-html-orphan-list-blocks.html` and
  `examples/wordpress-native-html-orphan-list-blocks-handoff.php` for a
  WordPress Data Liberation handoff where malformed source lists preserve a
  leading review paragraph, nested orphan list, and ordered-list continuation
  block.
- Dependency-closure checkpoint: this slice remains lane-local. It does not
  activate `xml-html5-dom-core`, shared ZIP/OpenXML/OpenDocument, PDF
  handoff/text extraction, citation/CSL, PlainMath/MathML conversion,
  TeX reference conversion, Unicode/charset, or syntax-highlighting support
  rows. Broader malformed-HTML parser parity beyond this source-order orphan
  list branch remains explicitly unclaimed.
- Focused evidence: targeted `git -C .upstream-cache/pandoc show` inspected
  `src/Text/Pandoc/Readers/HTML.hs` lines around `pBulletList`, `pListItem`,
  `pOrderedList`, `orphans`, `mconcat xs`, and the `#9187` comments. Targeted
  `git grep` counted 8 source branch lines and `rg -c` counted 21 source
  pattern hits. No dedicated command fixture for `#9187` was found in the
  static inventory, so the mapped native fixture records the source branch
  directly. `php -l` passed for `MarkdownReader.php`,
  `MarkdownReaderTest.php`, and
  `wordpress-native-html-orphan-list-blocks-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-html-orphan-list-blocks-handoff.php`
  emitted the malformed-list WordPress handoff; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 4,276 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 HTML Reader List Item ID Slice

- Focused PHP coverage is now 401 behavior tests with 4,248 assertions.
- Added bounded coverage for upstream `Text.Pandoc.Readers.HTML` `pListItem`
  `addId` behavior from `test/command/3596.md`. Upstream wraps a tight
  list item's first `Plain` inline run in `Span (id, [], [])` and wraps
  block/loose item content in `Div (id, [], [])`.
- `MarkdownReader` now imports tight `<li id>` content as a native `span`
  anchor around the leading inline run, leaves nested child lists outside that
  span, and imports loose/block `<li id>` content as a native `div` anchor.
- Added `fixtures/upstream-html-list-item-id.html` and
  `examples/wordpress-native-html-list-item-id-handoff.php` for a WordPress
  Data Liberation handoff where source list anchors survive inside review list
  markup without broadening into a full HTML5 DOM support port.
- Dependency-closure checkpoint: this slice remains lane-local. It does not
  activate `xml-html5-dom-core`, shared ZIP/OpenXML/OpenDocument, PDF
  handoff/text extraction, citation/CSL, PlainMath/MathML conversion,
  TeX reference conversion, Unicode/charset, or syntax-highlighting support
  rows. Orphan list-block handling from upstream #9187 remains explicitly
  unclaimed.
- Focused evidence: targeted `git -C .upstream-cache/pandoc show` inspected
  `src/Text/Pandoc/Readers/HTML.hs` lines around `pListItem`, `addId`,
  `Span`, and `B.divWith`; targeted `rg -c` counted 6 source branch lines and
  6 `test/command/3596.md` fixture hits around tight, nested, and loose
  `<li id>` behavior. `php -l` passed for `MarkdownReader.php`,
  `MarkdownReaderTest.php`, and
  `wordpress-native-html-list-item-id-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-html-list-item-id-handoff.php`
  emitted tight span anchors and loose div anchors inside WordPress lists;
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed
  1 file, 4,248 assertions, 0 failures. No no-argument root harness was
  assigned or started.

## 2026-05-24 HTML Reader Generic Raw Inline Fallback Slice

- Focused PHP coverage is now 400 behavior tests with 4,227 assertions.
- Added bounded coverage for upstream `Text.Pandoc.Readers.HTML`
  `pRawHtmlInline` fallback behavior. Upstream emits `RawInline (Format
  "html")` for raw inline tags/comments when raw HTML is enabled and ignores
  those raw boundaries when raw HTML is disabled.
- `MarkdownReader` now imports bounded generic inline tags (`button`, `time`,
  and `blink`) as raw HTML opening/closing boundary nodes with parsed children
  between them. HTML comments become `raw_html_inline` nodes when raw HTML is
  enabled. The disabled `htmlRawHtml`/`rawHtml` path drops those raw
  boundaries/comments while keeping child text and inline structure.
- Added `fixtures/upstream-html-generic-raw-inline.html` and
  `examples/wordpress-native-html-generic-raw-inline-handoff.php` for a
  WordPress Data Liberation handoff where source action markup, time metadata,
  and migration comments remain reviewable without broadening into a full
  HTML5 DOM support port.
- Dependency-closure checkpoint: this slice remains lane-local. It does not
  activate `xml-html5-dom-core`, shared ZIP/OpenXML/OpenDocument, PDF
  handoff/text extraction, citation/CSL, PlainMath/MathML conversion,
  TeX reference conversion, Unicode/charset, or syntax-highlighting support
  rows. Raw inline fallback beyond the bounded tags/comments remains
  explicitly unclaimed.
- Focused evidence: targeted `git -C .upstream-cache/pandoc show` inspected
  `src/Text/Pandoc/Readers/HTML.hs` lines around `pRawHtmlInline`; targeted
  `sed`/`rg -c` counted 7 source branch lines for `pRawHtmlInline`,
  `tagComment`, `isInlineTag`, `Ext_raw_html`, `B.rawInline "html"`, and
  `ignore raw`; targeted `git grep` counted 4 `test/command/parse-raw.md`
  fixture hits around `<blink>` raw inline behavior. `php -l` passed for
  `MarkdownReader.php`, `MarkdownReaderTest.php`, and
  `wordpress-native-html-generic-raw-inline-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-html-generic-raw-inline-handoff.php`
  emitted raw button/time/comment WordPress markup; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 4,227 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 HTML Reader Span SmallCaps Class Slice

- Focused PHP coverage is now 399 behavior tests with 4,199 assertions.
- Added bounded coverage for upstream `Text.Pandoc.Readers.HTML` `pSpan`
  logic where a `span` whose class tokens include `smallcaps` is lowered to
  native `SmallCaps`, alongside the already mapped `font-variant:
  small-caps` style path.
- `MarkdownReader` now imports `<span class="smallcaps">` as a `small_caps`
  AST node even when neighboring source classes are present, drops the source
  span id/classes/data attributes like Pandoc's `SmallCaps` constructor, and
  preserves nested inline content such as links.
- Added `fixtures/upstream-html-smallcaps-class.html` and
  `examples/wordpress-native-html-smallcaps-class-handoff.php` for a
  WordPress Data Liberation handoff where source glossary text keeps native
  small-caps semantics without leaking legacy span metadata.
- Dependency-closure checkpoint: this slice remains lane-local. It does not
  activate `xml-html5-dom-core`, shared ZIP/OpenXML/OpenDocument, PDF
  handoff/text extraction, citation/CSL, PlainMath/MathML conversion,
  TeX reference conversion, Unicode/charset, or syntax-highlighting support
  rows. Broader malformed-HTML parser parity remains explicitly unclaimed.
- Focused evidence: targeted `git -C .upstream-cache/pandoc grep` inspected
  `fontVariant == "small-caps"`, `"smallcaps" \`elem\` classes`, and
  `span class="smallcaps"` output in `src/Text/Pandoc/Readers/HTML.hs`,
  `test/command/1592.md`, `test/command/4528.md`, and
  `test/command/nested-spanlike.md`, counting 2 source branch lines and 3
  command fixture files. `php -l` passed for `MarkdownReader.php`,
  `MarkdownReaderTest.php`, and
  `wordpress-native-html-smallcaps-class-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-html-smallcaps-class-handoff.php`
  emitted WordPress small-caps spans with a nested glossary link; `php
  tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1
  file, 4,199 assertions, 0 failures. No no-argument root harness was
  assigned or started.

## 2026-05-24 HTML Reader Checkbox List Slice

- Focused PHP coverage is now 398 behavior tests with 4,182 assertions.
- Added bounded coverage for upstream `Text.Pandoc.Readers.HTML`
  `pCheckbox` and the inline `input type="checkbox"` branch guarded by
  `inListItem`. Upstream emits checked/unchecked ballot markers only for
  checkbox inputs inside list items; non-checkbox controls and outside-list
  checkboxes do not become task-list content in the mapped default path.
- `MarkdownReader` now imports checked and unchecked HTML checkbox inputs
  inside `<li>` as task-list item metadata, strips the generated source
  control marker from reviewer text, keeps literal ballot glyphs as text,
  keeps mixed lists from claiming every item is a task, and drops non-checkbox
  or outside-list controls.
- Added `fixtures/upstream-html-checkbox-list.html` and
  `examples/wordpress-native-html-checkbox-list-handoff.php` for a WordPress
  Data Liberation handoff where HTML-export reviewer task lists become native
  WordPress checkbox labels without leaking source form controls.
- Dependency-closure checkpoint: this slice remains lane-local. It does not
  activate `xml-html5-dom-core`, shared ZIP/OpenXML/OpenDocument, PDF
  handoff/text extraction, citation/CSL, PlainMath/MathML conversion,
  TeX reference conversion, Unicode/charset, or syntax-highlighting support
  rows. Full form-control DOM semantics and raw checkbox passthrough outside
  list items remain explicitly unclaimed.
- Focused evidence: targeted `git -C .upstream-cache/pandoc grep` counted 7
  source hits for `pCheckbox`, `inListItem`, `type="checkbox"`, and ballot
  marker handling in `src/Text/Pandoc/Readers/HTML.hs`, plus 26 checkbox
  command-fixture hits across `test/command/9047.md`,
  `test/command/tasklist.md`, and `test/command/gfm.md`. `php -l` passed for
  `MarkdownReader.php`, `MarkdownReaderTest.php`, and
  `wordpress-native-html-checkbox-list-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-html-checkbox-list-handoff.php`
  emitted WordPress checkbox labels plus a plain non-task item and outside-list
  paragraph; `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  passed 1 file, 4,182 assertions, 0 failures. No no-argument root harness was
  assigned or started.

## 2026-05-24 HTML Reader MathML Annotation Slice

- Focused PHP coverage is now 397 behavior tests with 4,152 assertions.
- Added bounded coverage for upstream `Text.Pandoc.Readers.HTML` `pMath`,
  `extractTeXAnnotation`, and the `pSpan` `MJX_Assistive_MathML` branch.
  Upstream unwraps assistive MathML spans, uses embedded
  `annotation encoding="application/x-tex"` before attempting full MathML
  conversion, and treats `display="block"` as display math.
- `MarkdownReader` now imports embedded TeX annotations as native `math` nodes,
  unwraps `MJX_Assistive_MathML` spans so they do not leak as generic spans,
  and leaves MathML without embedded TeX as a reviewable `span` with class
  `math` instead of claiming full MathML-to-TeX parity.
- Added `fixtures/upstream-html-mathml-annotation.html` and
  `examples/wordpress-native-html-mathml-annotation-handoff.php` for a
  WordPress Data Liberation handoff where source MathJax/KaTeX assistive
  MathML keeps the authored TeX once, while opaque MathML remains visible for
  review.
- Dependency-closure checkpoint: this slice remains lane-local. It does not
  activate `xml-html5-dom-core`, shared ZIP/OpenXML/OpenDocument, PDF
  handoff/text extraction, citation/CSL, PlainMath/MathML full conversion,
  TeX reference conversion, Unicode/charset, or syntax-highlighting support
  rows. General MathML conversion beyond embedded TeX annotations remains
  explicitly unclaimed.
- Focused evidence: targeted `git -C .upstream-cache/pandoc show
  HEAD:src/Text/Pandoc/Readers/HTML.hs | sed -n '880,965p'` inspected
  `MJX_Assistive_MathML`, `pMath`, `mathMLToTeXMath`, and
  `extractTeXAnnotation`; targeted `rg -c` counted 13 source hits around
  those branches; targeted `git grep` found the exact source branches plus
  three MathML-related command fixtures under `test/command`. `php -l` passed
  for `MarkdownReader.php`, `MarkdownReaderTest.php`, and
  `wordpress-native-html-mathml-annotation-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-html-mathml-annotation-handoff.php`
  emitted inline and display WordPress math spans plus a reviewable fallback
  math span; `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  passed 1 file, 4,152 assertions, 0 failures. No no-argument root harness was
  assigned or started.

## 2026-05-24 HTML Reader Doc-Noteref Table Placement Slice

- Focused PHP coverage is now 396 behavior tests with 4,129 assertions.
- Added bounded coverage for upstream command fixtures
  `test/command/8770-document.md`, `test/command/8770-block.md`, and
  `test/command/8770-section.md`, which exercise Pandoc footnote placement
  around `role="doc-noteref"` anchors in a document paragraph, table caption,
  table header cell, table body cell, and following paragraph.
- The upstream source checkpoint maps `Text.Pandoc.Readers.HTML` `replaceNotes`
  and `eNoteref` plus `Text.Pandoc.Readers.HTML.Table` caption parsing through
  `pInTags "caption" block`.
- `MarkdownReader` already imports those anchors as native `note` nodes inside
  table caption/header/body locations. `WordPressBlockWriter` now renders table
  caption inlines before row content for numbering purposes, then emits the
  saved caption HTML after the table to keep WordPress table markup stable.
  This preserves Pandoc's logical caption-before-cell footnote numbering while
  keeping WordPress `figcaption` placement.
- Added `fixtures/upstream-html-doc-noteref-table-placement.html` and
  `examples/wordpress-native-html-doc-noteref-table-handoff.php` for a
  WordPress Data Liberation handoff where source table notes stay ordered as
  paragraph, caption, header cell, body cell, and following paragraph.
- Dependency-closure checkpoint: this slice remains lane-local. It does not
  activate `xml-html5-dom-core`, shared ZIP/OpenXML/OpenDocument, PDF
  handoff/text extraction, citation/CSL, PlainMath/MathML, Unicode/charset, or
  syntax-highlighting support rows. Block/section reference-location import
  shape from 8770 remains mapped as upstream inventory, not claimed as a
  full WordPress placement policy beyond the document fixture.
- Focused evidence: targeted `git -C .upstream-cache/pandoc grep -n` over the
  three 8770 command fixtures counted paragraph, caption, header-cell,
  body-cell, and following-paragraph doc-noteref anchors plus document/block/
  section footnote containers; targeted `git grep` over
  `src/Text/Pandoc/Readers/HTML.hs` and `src/Text/Pandoc/Readers/HTML/Table.hs`
  confirmed `replaceNotes`, `RawInline (Format "noteref")`, doc-noteref
  matching, `pTable block`, and `pInTags "caption" block`. `php -l` passed for
  `WordPressBlockWriter.php`, `MarkdownReaderTest.php`, and
  `wordpress-native-html-doc-noteref-table-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-html-doc-noteref-table-handoff.php`
  emitted ordered WordPress footnotes 1-5; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 4,129 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 HTML Reader Math Renderer Span Skip Slice

- Focused PHP coverage is now 395 behavior tests with 4,111 assertions.
- Added bounded `MarkdownReader` coverage for upstream
  `Text.Pandoc.Readers.HTML` `pSpan` guards that discard visual MathJax and
  KaTeX renderer output. Upstream skips spans carrying `mjx-chtml`,
  `MathJax_CHTML`, or `MathJax_Preview`, and skips exact
  `class="katex-html"` spans.
- `MarkdownReader` now applies those skip rules before generic span lowering,
  so HTML imports that include both `script type="math/tex"` source and
  generated renderer spans produce one native math node and no duplicated
  visual text in Native or WordPress output.
- Added `fixtures/upstream-html-math-renderer-spans.html` and
  `examples/wordpress-native-html-math-renderer-handoff.php` for a WordPress
  Data Liberation handoff where source equations stay reviewable while
  renderer-only MathJax/KaTeX spans are omitted.
- Dependency-closure checkpoint: this slice remains lane-local. It does not
  activate `xml-html5-dom-core`, PlainMath/MathML conversion, TeX math/ref
  conversion, citation/CSL, shared ZIP/OpenXML/OpenDocument, PDF handoff/text
  extraction, Unicode/charset, or syntax-highlighting support rows.
  `MJX_Assistive_MathML` MathML-to-TeX parity remains explicitly unclaimed.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:src/Text/Pandoc/Readers/HTML.hs | sed -n '872,900p'` inspected the
  `pSpan` renderer guards; `git -C .upstream-cache/pandoc grep -n
  'mjx-chtml\|MathJax_CHTML\|MathJax_Preview\|MJX_Assistive_MathML\|katex-html'
  HEAD -- src/Text/Pandoc/Readers/HTML.hs test/command
  test/Tests/Readers/HTML.hs` counted 5 upstream source branches and no
  checked-in command fixtures for these class names. `php -l` passed for
  `MarkdownReader.php`, `MarkdownReaderTest.php`, and
  `wordpress-native-html-math-renderer-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-html-math-renderer-handoff.php`
  emitted single MathJax/KaTeX WordPress math spans without duplicate renderer
  text; `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  passed 1 file, 4,111 assertions, 0 failures. No no-argument root harness was
  assigned or started.

## 2026-05-24 HTML Reader Span Strikeout Slice

- Focused PHP coverage is now 394 behavior tests with 4,089 assertions.
- Added bounded `MarkdownReader` coverage for upstream
  `Text.Pandoc.Readers.HTML` `pStrikeout` handling. Upstream routes `<s>`,
  `<strike>`, `<del>`, and exact `<span class="strikeout">` to native
  `Strikeout`, and routes `<u>`/`<ins>` to native `Underline`.
- `MarkdownReader` now handles the exact span-class branch in the DOM-backed
  HTML path before the generic span fallback, so legacy source strikeout spans
  become native `strikeout` nodes and WordPress gets the existing `<del>`
  review handoff. The slice also confirms adjacent `del`/`ins` edit markup.
- Added `fixtures/upstream-html-span-strikeout.html` and
  `examples/wordpress-native-html-span-strikeout-handoff.php` for a WordPress
  Data Liberation handoff where legacy strikeout spans and explicit edit marks
  remain reviewable as native WordPress inline markup.
- Dependency-closure checkpoint: this slice remains lane-local. It does not
  activate `xml-html5-dom-core`, shared ZIP/OpenXML/OpenDocument, PDF
  handoff/text extraction, citation/CSL, PlainMath/MathML, Unicode/charset, or
  syntax-highlighting support rows. Broader malformed HTML/class-token
  normalization beyond upstream exact `class="strikeout"` matching is not
  claimed by this slice.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:src/Text/Pandoc/Readers/HTML.hs | sed -n '760,825p'` inspected
  `pStrikeout` and `pUnderline`; targeted `rg -c` counted 10 source hits around
  the `s`/`strike`/`del`/`u`/`ins` and `span class="strikeout"` branches; `git
  -C .upstream-cache/pandoc grep -l` counted 7 related command/reader fixture
  files. `php -l` passed for `MarkdownReader.php`, `MarkdownReaderTest.php`,
  and `wordpress-native-html-span-strikeout-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-html-span-strikeout-handoff.php`
  emitted the WordPress deletion/review handoff; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 4,089 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 HTML Reader Line-Block Slice

- Focused mapped count is now 2,276 Markdown/HTML/Native/WordPress checks.
- Added bounded `MarkdownReader` coverage for upstream
  `Text.Pandoc.Readers.HTML` `pLineBlock` handling. Upstream dispatches an
  exact `<div class="line-block">` to `pLineBlock`, trims inline content,
  filters `SoftBreak`, and splits on `LineBreak` to produce a native
  `LineBlock`.
- `MarkdownReader` now handles this behavior for both fragment-level imports
  and DOM-backed full HTML documents: exact line-block divs become
  `line_block` nodes, hard `<br>` elements split lines, double breaks preserve
  empty lines, and NBSP indentation remains in the Native/WordPress handoff.
- Added `fixtures/upstream-html-line-block.html` and
  `examples/wordpress-native-html-line-block-handoff.php` for a WordPress Data
  Liberation handoff where reviewer stanzas and source edit links stay in a
  paragraph-shaped handoff instead of a generic div.
- Dependency-closure checkpoint: this slice remains lane-local. It does not
  activate `xml-html5-dom-core`, shared ZIP/OpenXML/OpenDocument, PDF
  handoff/text extraction, citation/CSL, PlainMath/MathML, Unicode/charset, or
  syntax-highlighting support rows. Broader malformed HTML/class-token parity
  is not claimed by this slice.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:src/Text/Pandoc/Readers/HTML.hs | sed -n '228,242p'` inspected the
  `div` dispatch to `pLineBlock`; `git -C .upstream-cache/pandoc show
  HEAD:src/Text/Pandoc/Readers/HTML.hs | sed -n '478,490p'` inspected
  `pLineBlock`, `splitWhen (== LineBreak)`, and `filter (/= SoftBreak)`;
  targeted `git grep` counted 6 line-block source hits and 2 command fixture
  files (`test/command/10825.md`, `test/command/4162.md`). `php -l` passed for
  `MarkdownReader.php`, `MarkdownReaderTest.php`, and
  `wordpress-native-html-line-block-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-html-line-block-handoff.php` emitted
  the WordPress stanza handoff; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 4,073 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 HTML Reader Raw-HTML Disabled Skip Slice

- Focused mapped count is now 2,268 Markdown/HTML/Native/WordPress checks.
- Added bounded `MarkdownReader` coverage for upstream
  `Text.Pandoc.Readers.HTML` raw HTML extension guards. Upstream
  `pRawHtmlBlock` and `pRawHtmlInline` return raw HTML only when
  `Ext_raw_html` is enabled; otherwise they call `ignore` and skip the raw
  payload.
- `MarkdownReader` now handles this behavior in the DOM-backed full HTML
  document path: with `htmlRawHtml`/`rawHtml` disabled, inline and block
  `<style>`, generic `<script>`, and `<textarea>` payloads are skipped rather
  than emitted as raw nodes. Body-level `script type="math/tex..."` remains a
  native `math` node inside a `plain` block.
- Added `fixtures/upstream-html-raw-disabled-skip.html` and
  `examples/wordpress-native-html-raw-disabled-handoff.php` for a WordPress
  Data Liberation handoff where raw migration payloads are suppressed while
  TeX source math remains reviewable.
- Dependency-closure checkpoint: this slice remains lane-local. It does not
  activate `xml-html5-dom-core`, shared ZIP/OpenXML/OpenDocument, PDF
  handoff/text extraction, citation/CSL, PlainMath/MathML, Unicode/charset, or
  syntax-highlighting support rows. SkippedContent warning-message parity is
  not claimed by this slice.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:src/Text/Pandoc/Readers/HTML.hs | sed -n '536,565p'` inspected
  `pRawHtmlBlock`, `pHtmlBlock`, and `ignore`; `git -C .upstream-cache/pandoc
  show HEAD:src/Text/Pandoc/Readers/HTML.hs | sed -n '900,930p'` inspected
  `pRawHtmlInline`; `git -C .upstream-cache/pandoc show
  HEAD:src/Text/Pandoc/Readers/HTML.hs | sed -n '620,730p'` inspected
  `pPlain` and script math dispatch. Targeted `rg -c` counted 21 source hits
  around `Ext_raw_html`, raw block/inline, `ignore`, `SkippedContent`, and
  script/style/textarea boundaries; `git -C .upstream-cache/pandoc grep -l`
  counted 45 raw_html/style/script/textarea fixture files under
  `test/command` and `test/Tests/Readers/HTML.hs`. `php -l` passed for
  `MarkdownReader.php`, `MarkdownReaderTest.php`, and
  `wordpress-native-html-raw-disabled-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-html-raw-disabled-handoff.php`
  emitted the sanitized WordPress handoff; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 4,055 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 HTML Reader Script Raw-Block Slice

- Focused mapped count is now 2,251 Markdown/HTML/Native/WordPress checks.
- Added bounded `MarkdownReader` coverage for upstream
  `Text.Pandoc.Readers.HTML` generic script raw-block handling. Upstream
  `pRawHtmlBlock` tries `pHtmlBlock "script"` before style and textarea, while
  the inline parser still reserves `script type="math/tex..."` for
  `pScriptMath`.
- `MarkdownReader` now handles this behavior in the DOM-backed full HTML
  document path: body-level generic `<script>...</script>` becomes a
  `raw_html` block, `NativeWriter` emits `RawBlock (Format "html")`, and
  `WordPressBlockWriter` emits a core HTML block instead of wrapping the
  script in a paragraph as raw inline HTML. Body-level math/tex scripts remain
  native `math` nodes inside a `plain` block.
- Added `fixtures/upstream-html-script-raw-block.html` and
  `examples/wordpress-native-html-script-block-handoff.php` for a WordPress
  Data Liberation handoff where migration script payloads remain reviewable as
  source HTML blocks.
- Dependency-closure checkpoint: this slice remains lane-local. It does not
  activate `xml-html5-dom-core`, shared ZIP/OpenXML/OpenDocument, PDF
  handoff/text extraction, citation/CSL, PlainMath/MathML, Unicode/charset, or
  syntax-highlighting support rows. Raw-HTML disabled warning/skip parity is
  not claimed by this slice.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:src/Text/Pandoc/Readers/HTML.hs | sed -n '536,565p'` inspected
  `pRawHtmlBlock` and `pHtmlBlock`; `git -C .upstream-cache/pandoc show
  HEAD:src/Text/Pandoc/Readers/HTML.hs | sed -n '620,730p'` inspected `pPlain`
  and inline script math dispatch; `git -C .upstream-cache/pandoc show
  HEAD:src/Text/Pandoc/Readers/HTML/TagCategories.hs | sed -n '1,100p'`
  inspected the `script` block/either-block-or-inline category; targeted
  `rg -c` counted 25 static source hits around script raw-block and script-math
  boundaries; `git -C .upstream-cache/pandoc grep -l '<script' HEAD --
  test/command test/Tests/Readers/HTML.hs` counted 3 static command fixtures.
  `php -l` passed for `MarkdownReader.php`, `MarkdownReaderTest.php`, and
  `wordpress-native-html-script-block-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-html-script-block-handoff.php` emitted
  the WordPress HTML-block handoff; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 4,038 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 HTML Reader Style Raw-Block Slice

- Focused mapped count is now 2,233 Markdown/HTML/Native/WordPress checks.
- Added bounded `MarkdownReader` coverage for upstream
  `Text.Pandoc.Readers.HTML` block-level style handling. The upstream block
  dispatcher routes `"style" -> pRawHtmlBlock`, and `pRawHtmlBlock` uses
  `pHtmlBlock "style"` to preserve the complete element as `RawBlock
  (Format "html")`.
- `MarkdownReader` now handles this behavior in the DOM-backed full HTML
  document path: body-level `<style>...</style>` becomes a `raw_html` block,
  `NativeWriter` emits `RawBlock (Format "html")`, and `WordPressBlockWriter`
  emits a core HTML block instead of wrapping the stylesheet in a paragraph as
  raw inline HTML.
- Added `fixtures/upstream-html-style-raw-block.html` and
  `examples/wordpress-native-html-style-block-handoff.php` for a WordPress
  Data Liberation handoff where migration stylesheet payloads remain reviewable
  as source HTML blocks.
- Dependency-closure checkpoint: this slice remains lane-local. It does not
  activate `xml-html5-dom-core`, shared ZIP/OpenXML/OpenDocument, PDF
  handoff/text extraction, citation/CSL, PlainMath/MathML, Unicode/charset, or
  syntax-highlighting support rows. Raw-HTML disabled warning/skip parity and
  generic script block parity are not claimed by this slice.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:src/Text/Pandoc/Readers/HTML.hs | sed -n '152,285p'` inspected block
  dispatch; `git -C .upstream-cache/pandoc show
  HEAD:src/Text/Pandoc/Readers/HTML.hs | sed -n '536,566p'` inspected
  `pRawHtmlBlock` and `pHtmlBlock`; targeted `rg -c` counted 11 source hits
  around style raw-block and inline-style boundaries; `git -C
  .upstream-cache/pandoc grep -l '<style' HEAD -- test/command
  test/Tests/Readers/HTML.hs` counted 29 static files with style-shaped
  fixtures. `php -l` passed for `MarkdownReader.php`,
  `MarkdownReaderTest.php`, and `wordpress-native-html-style-block-handoff.php`;
  `php lanes/pandoc/examples/wordpress-native-html-style-block-handoff.php`
  emitted the WordPress HTML-block handoff; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 4,016 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 HTML Reader Doc-Noteref Footnote Slice

- Focused mapped count is now 2,216 Markdown/HTML/Native/WordPress checks.
- Added bounded `MarkdownReader` coverage for upstream
  `Text.Pandoc.Readers.HTML` note replacement semantics: `role="doc-endnotes"`
  containers populate the note table, `role="doc-noteref"` anchors become
  native `Note` nodes, and `role="doc-backlink"` links are stripped from the
  note body.
- `MarkdownReader` now handles this behavior for full HTML imports parsed via
  the DOM path. Fragment-level forward replacement and table/caption note
  placement remain future slices; this slice does not claim EPUB extension
  parity beyond recognizing the same bounded `epub:type` names.
- Added `fixtures/upstream-html-doc-noteref-footnotes.html` and
  `examples/wordpress-native-html-doc-noteref-handoff.php` for a WordPress
  Data Liberation handoff where imported source notes become native WordPress
  endnotes instead of duplicating the original HTML endnotes section.
- Dependency-closure checkpoint: this slice remains lane-local. It does not
  activate `xml-html5-dom-core`, shared ZIP/OpenXML/OpenDocument, PDF
  handoff/text extraction, citation/CSL, PlainMath/MathML, Unicode/charset, or
  syntax-highlighting support rows.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:src/Text/Pandoc/Readers/HTML.hs | sed -n '120,140p'` inspected
  `replaceNotes`; `git -C .upstream-cache/pandoc show
  HEAD:src/Text/Pandoc/Readers/HTML.hs | sed -n '286,337p'` inspected
  `eFootnote`, `eFootnotes`, and `eNoteref`; `git -C .upstream-cache/pandoc
  show HEAD:src/Text/Pandoc/Readers/HTML.hs | sed -n '684,690p'` inspected the
  inline dispatch to `eNoteref`; targeted `rg -c` counted 30 static source
  hits around note-table, doc-endnotes, doc-noteref, and backlink handling; `git
  -C .upstream-cache/pandoc grep -l` counted 6 command fixtures mentioning the
  same doc-noteref/doc-endnotes/epub-noteref surface. `php -l` passed for
  `MarkdownReader.php`, `MarkdownReaderTest.php`, and
  `wordpress-native-html-doc-noteref-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-html-doc-noteref-handoff.php` emitted
  the WordPress footnote handoff; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 3,999 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 HTML Reader Textarea Raw-Block Slice

- Focused mapped count is now 2,199 Markdown/HTML/Native/WordPress checks.
- Added bounded `MarkdownReader` coverage for the upstream
  `Text.Pandoc.Readers.HTML` `pRawHtmlBlock` branch where block-level
  `<textarea>` is preserved as `RawBlock (Format "html")`.
- `MarkdownReader` now keeps top-level and body-level
  `<textarea>...</textarea>` blocks as `raw_html` nodes for `NativeWriter`, `HtmlWriter`, and
  `WordPressBlockWriter` handoff. Inline textarea parsing, form-control
  semantics, browser DOM behavior, and disabled raw-HTML warning behavior are
  not claimed by this slice.
- Added `fixtures/upstream-html-textarea-raw-block.html` and
  `examples/wordpress-native-html-textarea-handoff.php` for a WordPress source
  review packet where a legacy textarea payload stays literal inside a core
  HTML block.
- Dependency-closure checkpoint: this slice remains lane-local. It does not
  activate `xml-html5-dom-core`, shared ZIP/OpenXML/OpenDocument, PDF
  handoff/text extraction, citation/CSL, PlainMath/MathML, Unicode/charset, or
  syntax-highlighting support rows.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:src/Text/Pandoc/Readers/HTML.hs | sed -n '215,255p'` inspected the
  block dispatch where `"textarea" -> pRawHtmlBlock`; `git -C
  .upstream-cache/pandoc show HEAD:src/Text/Pandoc/Readers/HTML.hs | sed -n
  '542,566p'` inspected `pRawHtmlBlock` and `pHtmlBlock "textarea"`; targeted
  `rg -c` over the same branch counted 6 static source hits; `php -l` passed
  for `MarkdownReader.php`, `MarkdownReaderTest.php`, and
  `wordpress-native-html-textarea-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-html-textarea-handoff.php` emitted
  the WordPress textarea review block; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 3,983 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 HTML Reader Style/Script Inline Slice

- Focused mapped count is now 2,182 Markdown/HTML/Native/WordPress checks.
- Added bounded `MarkdownReader` coverage for the upstream
  `Text.Pandoc.Readers.HTML` inline `style` and `script type="math/tex..."`
  branches. Upstream preserves `<style>` through `B.rawInline "html"` and
  converts `math/tex` script payloads through `pScriptMath` into inline or
  display math.
- `MarkdownReader` now keeps inline `<style>...</style>` elements as
  `raw_html_inline` nodes and converts `<script type="math/tex">` plus
  display-suffixed `math/tex` script payloads to native `math` nodes. Generic
  TeX parsing, PlainMath, MathML conversion, and TeX reference/environment
  conversion are not claimed by this slice.
- Added `fixtures/upstream-html-style-script-inline.html` and
  `examples/wordpress-native-html-style-script-handoff.php` for a WordPress
  source review packet where source CSS remains visible and TeX math stays
  native in the WordPress handoff.
- Dependency-closure checkpoint: this slice remains lane-local. It does not
  activate `xml-html5-dom-core`, shared ZIP/OpenXML/OpenDocument, PDF
  handoff/text extraction, citation/CSL, PlainMath/MathML, Unicode/charset, or
  syntax-highlighting support rows.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:src/Text/Pandoc/Readers/HTML.hs | sed -n '640,865p'` inspected inline
  dispatch to `style`, `script`, and `pRawHtmlInline`; `git -C
  .upstream-cache/pandoc show HEAD:src/Text/Pandoc/Readers/HTML.hs | sed -n
  '865,960p'` inspected `pRawHtmlInline` and `pScriptMath`; `git -C
  .upstream-cache/pandoc show HEAD:src/Text/Pandoc/Readers/HTML.hs | sed -n
  '1070,1090p'` inspected `isInlineTag` style/script boundaries; a targeted
  `rg -c` over `style`, `script`, `math/tex`, `pScriptMath`,
  `pRawHtmlInline`, `pHtmlBlock "style"`, and inline tag cases counted 24
  static source hits around the branch; `php -l` passed for
  `MarkdownReader.php`, `MarkdownReaderTest.php`, and
  `wordpress-native-html-style-script-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-html-style-script-handoff.php`
  emitted the WordPress style/script review block; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 3,964 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 HTML Reader SVG Raw-HTML-Enabled Slice

- Focused mapped count is now 2,162 Markdown/HTML/Native/WordPress checks.
- Added bounded `MarkdownReader` coverage for the upstream
  `Text.Pandoc.Readers.HTML` SVG branch split: `pSvg` handles the
  disabled-raw-HTML image fallback, while the raw-HTML-enabled path falls back
  through `pRawHtmlInline` and `B.rawInline "html"` instead of rewriting the
  SVG to a data image.
- `MarkdownReader` now preserves inline `<svg>...</svg>` elements as
  `raw_html_inline` nodes when `htmlRawHtml`/`rawHtml` is enabled. The existing
  disabled-raw path remains unchanged and still maps SVG to a
  `data:image/svg+xml;base64,...` image fallback with Font Awesome width hints.
- Added `fixtures/upstream-html-svg-raw-html.html` and
  `examples/wordpress-native-html-svg-raw-handoff.php` for a WordPress source
  review packet where source SVG markup stays visible as raw inline HTML.
- Dependency-closure checkpoint: this slice remains lane-local. It does not
  activate `xml-html5-dom-core`, shared ZIP/OpenXML/OpenDocument, PDF
  handoff/text extraction, citation/CSL, PlainMath/MathML, Unicode/charset, or
  syntax-highlighting support rows.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:src/Text/Pandoc/Readers/HTML.hs | sed -n '680,780p'` inspected inline
  dispatch to `pSvg` and `pRawHtmlInline`; `git -C .upstream-cache/pandoc show
  HEAD:src/Text/Pandoc/Readers/HTML.hs | sed -n '800,865p'` inspected `pSvg`;
  a targeted `rg -c` over `pSvg`, `guardDisabled Ext_raw_html`,
  `pRawHtmlInline`, `B.rawInline "html"`, and `renderTags` counted 20 static
  source hits around the branch; `php -l`
  passed for `MarkdownReader.php`, `MarkdownReaderTest.php`, and
  `wordpress-native-html-svg-raw-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-html-svg-raw-handoff.php` emitted the
  raw SVG WordPress review block; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 3,944 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 HTML Reader Span-Like Inline Slice

- Focused mapped count is now 2,148 Markdown/HTML/Native/WordPress checks.
- Added bounded `MarkdownReader` coverage from upstream
  `Text.Pandoc.Readers.HTML` `pSpanLike` behavior and
  `Text.Pandoc.Shared` `htmlSpanLikeElements` without invoking upstream
  Pandoc, live fetching, a browser, converter shell-outs, PDF processing,
  ZIP/package parsing, citation/CSL engines, or broader XML/HTML parser
  expansion.
- Targeted upstream inspection covered the inline dispatch fallback for
  `htmlSpanLikeElements`, the `pSpanLike` parser, the
  `Set.fromList ["kbd", "mark", "dfn", "abbr"]` element set, and the
  `tagName : cs` class-prepending behavior. The PHP slice maps `<kbd>`,
  `<mark>`, `<dfn>`, and `<abbr>` to Pandoc `span` nodes with the tag-name
  class prepended, preserves source id/classes/data/title metadata, preserves
  nested inline content, and keeps `<kbd>` distinct from the existing
  `code`/`tt`/`samp`/`var` code path.
- Added `fixtures/upstream-html-spanlike-inline.html` and
  `examples/wordpress-native-html-spanlike-handoff.php` for a WordPress
  source review packet where keyboard shortcuts, publish highlights, and
  source terms remain reviewable as semantic inline HTML.
- Dependency-closure checkpoint: this slice remains lane-local. It does not
  activate `xml-html5-dom-core`, shared ZIP/OpenXML/OpenDocument, PDF
  handoff/text extraction, citation/CSL, PlainMath/MathML, Unicode/charset, or
  syntax-highlighting support rows.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:src/Text/Pandoc/Readers/HTML.hs | sed -n '650,910p'` inspected the
  upstream inline dispatch and `pSpanLike` branch; `git -C
  .upstream-cache/pandoc show HEAD:src/Text/Pandoc/Shared.hs | sed -n
  '704,714p'` inspected `htmlSpanLikeElements`; `git -C
  .upstream-cache/pandoc show HEAD:src/Text/Pandoc/Readers/HTML.hs | rg -c
  'htmlSpanLikeElements|pSpanLike|tagName : cs|Set\.fromList \["kbd",
  "mark", "dfn", "abbr"\]|"kbd"|"mark"|"dfn"|"abbr"'` counted 6 static source
  hits around the branch; `php -l` passed for `MarkdownReader.php`,
  `MarkdownReaderTest.php`, and `wordpress-native-html-spanlike-handoff.php`;
  `php lanes/pandoc/examples/wordpress-native-html-spanlike-handoff.php`
  emitted the WordPress span-like review block; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 3,930 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 HTML Reader BDO Direction Slice

- Focused mapped count is now 2,132 Markdown/HTML/Native/WordPress checks.
- Added bounded `MarkdownReader` coverage from upstream
  `Text.Pandoc.Readers.HTML` `pBdo` behavior without invoking upstream
  Pandoc, live fetching, a browser, converter shell-outs, PDF processing,
  ZIP/package parsing, citation/CSL engines, or broader XML/HTML parser
  expansion.
- Targeted upstream inspection covered the inline dispatch branch for
  `"bdo" -> pBdo`, the `pBdo` definition, its `dir` lookup, the
  `B.spanWith ("",[],[("dir",T.toLower dir)])` lowering, and the no-dir
  branch that returns plain contents. The PHP slice maps `<bdo dir>` to a
  Pandoc `span` node with lowercased `dir` metadata, preserves nested inline
  content, and returns plain inline contents when `dir` is absent.
- Added `fixtures/upstream-html-bdo-direction.html` and
  `examples/wordpress-native-html-bdo-handoff.php` for a WordPress source
  review packet where directional source title text remains reviewable as a
  span with `dir` metadata.
- Dependency-closure checkpoint: this slice remains lane-local. It does not
  activate `xml-html5-dom-core`, shared ZIP/OpenXML/OpenDocument, PDF
  handoff/text extraction, citation/CSL, PlainMath/MathML, Unicode/charset, or
  syntax-highlighting support rows.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:src/Text/Pandoc/Readers/HTML.hs | sed -n '677,875p'` inspected the
  upstream inline dispatch and `pBdo` branch; `git -C .upstream-cache/pandoc
  show HEAD:src/Text/Pandoc/Readers/HTML.hs | rg -c
  '"bdo" -> pBdo|pBdo|tagOpen \(=="bdo"\)|lookup "dir"|B\.spanWith
  \("", \[], \[\("dir",T\.toLower dir\)\]\)|Nothing  -> contents'` counted 7
  static source hits around the branch; `php -l` passed for
  `MarkdownReader.php`, `MarkdownReaderTest.php`, and
  `wordpress-native-html-bdo-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-html-bdo-handoff.php` emitted the
  WordPress bdo direction review block; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 3,909 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 HTML Reader Small Inline Slice

- Focused mapped count is now 2,121 Markdown/HTML/Native/WordPress checks.
- Added bounded `MarkdownReader` coverage from upstream
  `Text.Pandoc.Readers.HTML` `pSmall` behavior without invoking upstream
  Pandoc, live fetching, a browser, converter shell-outs, PDF processing,
  ZIP/package parsing, citation/CSL engines, or broader XML/HTML parser
  expansion.
- Targeted upstream inspection covered the inline dispatch branch for
  `"small" -> pSmall`, the `pSmall` definition, and its
  `B.spanWith ("",["small"],[])` lowering. The PHP slice maps `<small>` to a
  Pandoc `span` node with class `small`, preserves nested inline content, and
  intentionally drops source id/class attributes to match upstream.
- Added `fixtures/upstream-html-small-inline.html` and
  `examples/wordpress-native-html-small-inline-handoff.php` for a WordPress
  source review packet where imported source fine print remains reviewable as
  a classed inline span.
- Dependency-closure checkpoint: this slice remains lane-local. It does not
  activate `xml-html5-dom-core`, shared ZIP/OpenXML/OpenDocument, PDF
  handoff/text extraction, citation/CSL, PlainMath/MathML, Unicode/charset, or
  syntax-highlighting support rows.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:src/Text/Pandoc/Readers/HTML.hs | sed -n '692,786p'` inspected the
  upstream inline dispatch and `pSmall` branch; `git -C
  .upstream-cache/pandoc show HEAD:src/Text/Pandoc/Readers/HTML.hs | rg -c
  '"small" -> pSmall|pSmall|pInlinesInTags "small"|B\.spanWith
  \("",\["small"\],\[\]\)'` counted 3 static source hits around the branch;
  `php -l` passed for `MarkdownReader.php`, `MarkdownReaderTest.php`, and
  `wordpress-native-html-small-inline-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-html-small-inline-handoff.php`
  emitted the WordPress small-inline review block; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 3,894 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 HTML Reader SVG Disabled Raw-HTML Slice

- Focused mapped count is now 2,106 Markdown/HTML/Native/WordPress checks.
- Added bounded `MarkdownReader` coverage from upstream
  `Text.Pandoc.Readers.HTML` `pSvg` behavior without invoking upstream
  Pandoc, live fetching, a browser, converter shell-outs, PDF processing,
  ZIP/package parsing, citation/CSL engines, or broader XML/HTML DOM support.
- Targeted upstream inspection covered the `pSvg` branch in
  `src/Text/Pandoc/Readers/HTML.hs`, including `guardDisabled Ext_raw_html`,
  base64 `data:image/svg+xml` image fallback, id/class preservation, and
  Font Awesome `fa-w-14`/`fa-w-16`/`fa-fw` width hints. The PHP slice maps the
  disabled-raw-HTML branch through `htmlRawHtml=false` or `rawHtml=false`; it
  does not claim exact raw-HTML-enabled token passthrough.
- `MarkdownReader` now maps inline `<svg>` nodes to Pandoc `image` nodes with
  data-image URLs, empty alt/title labels, preserved id/classes, and
  `width=1em` for the Font Awesome width classes. This keeps local source SVG
  icons inspectable without running external renderers.
- Added `fixtures/upstream-html-svg-disabled-raw-html.html` and
  `examples/wordpress-native-html-svg-disabled-raw-handoff.php` for a
  WordPress source review packet where an imported SVG icon remains reviewable
  as an inline image.
- Dependency-closure checkpoint: this slice remains lane-local. It does not
  activate `xml-html5-dom-core`, shared ZIP/OpenXML/OpenDocument, PDF
  handoff/text extraction, citation/CSL, PlainMath/MathML, Unicode/charset, or
  syntax-highlighting support rows.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:src/Text/Pandoc/Readers/HTML.hs | sed -n '830,845p'` inspected the
  upstream branch; `git -C .upstream-cache/pandoc show
  HEAD:src/Text/Pandoc/Readers/HTML.hs | rg -c
  'pSvg|guardDisabled Ext_raw_html|data:image/svg|fa-w-14|fa-w-16|fa-fw|B.imageWith'`
  counted 11 static source hits around the branch; `php -l` passed for
  `MarkdownReader.php`, `MarkdownReaderTest.php`, and
  `wordpress-native-html-svg-disabled-raw-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-html-svg-disabled-raw-handoff.php`
  emitted the WordPress SVG review block; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 3,879 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 HTML Reader Iframe Local-Resource Slice

- Focused mapped count is now 2,080 Markdown/HTML/Native/WordPress checks.
- Added bounded `MarkdownReader` coverage from upstream
  `Text.Pandoc.Readers.HTML` `pIframe` behavior without invoking upstream
  Pandoc, live fetching, a browser, converter shell-outs, PDF processing,
  ZIP/package parsing, citation/CSL engines, or broader XML/HTML parser
  expansion.
- Targeted upstream inspection covered the `pIframe` branch in
  `src/Text/Pandoc/Readers/HTML.hs`, including the disabled-raw-HTML guard,
  required `src`, text/html resource parsing, image resource wrapping, generic
  MIME fallback with `src`, and fetch-failure ignore path. The PHP slice maps
  the resource-result branches through explicit local resource injection only;
  it does not claim live `openURL` parity.
- `MarkdownReader` now accepts `htmlIframeResources` as a local resource map.
  Text/html resources are parsed through the native HTML reader into a
  `Div .iframe`; `image/*` resources become a plain image inside `Div
  .iframe`; other MIME values become an empty `Div .iframe` with the resolved
  `src` stored in the native AST.
- Added `fixtures/upstream-html-iframe-local-resource.html` and
  `examples/wordpress-native-html-iframe-handoff.php` for a WordPress source
  review packet where embedded HTML and image frames remain reviewable without
  fetching remote URLs.
- Dependency-closure checkpoint: this slice remains lane-local. It does not
  activate `xml-html5-dom-core`, shared ZIP/OpenXML/OpenDocument, PDF
  handoff/text extraction, citation/CSL, PlainMath/MathML, Unicode/charset, or
  syntax-highlighting support rows.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:src/Text/Pandoc/Readers/HTML.hs | sed -n '516,540p'` inspected the
  upstream branch; `git -C .upstream-cache/pandoc show
  HEAD:src/Text/Pandoc/Readers/HTML.hs | rg -n
  'pIframe|iframe|openURL|CouldNotFetchResource'` verified the targeted
  source hits; `php -l` passed for `MarkdownReader.php`,
  `MarkdownReaderTest.php`, and
  `wordpress-native-html-iframe-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-html-iframe-handoff.php` emitted the
  WordPress iframe review blocks; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 3,853 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 HTML Writer Link removeLinks Slice

- Focused mapped count is now 2,072 Markdown/HTML/Native/WordPress checks.
- Added bounded `HtmlWriter` coverage from upstream
  `Text.Pandoc.Writers.HTML` `Link` label handling and
  `Text.Pandoc.Writers.Shared` `removeLinks` behavior without invoking
  upstream Pandoc, converter shell-outs, PDF processing, ZIP/package parsing,
  XML/HTML parser expansion, citation/CSL engines, TeX math/ref conversion, or
  broader syntax-highlighting support.
- Targeted upstream inspection covered the two HTML writer `Link` branches
  that call `removeLinks`, the shared `removeLinks` helper that rewrites
  nested `Link` nodes to `Span` nodes, and the 29 `Link` constructors counted
  in `test/testsuite.native`.
- `HtmlWriter` now renders link labels through a recursive links-as-spans pass
  before emitting the outer anchor. Nested link target URL/title metadata is
  dropped, while label ids, classes, and source data attributes are preserved
  on the resulting span. This maps Pandoc's invalid-nested-anchor guard for
  native AST packets and hand-built writer inputs.
- Added `examples/wordpress-html-writer-remove-links-handoff.php` for a
  WordPress source-review packet where a nested source-note link becomes a
  span inside the outer review link, keeping source metadata visible in a
  WordPress HTML block without emitting nested anchors.
- Dependency-closure checkpoint: this slice remains lane-local. It does not
  activate shared ZIP/OpenXML/OpenDocument, PDF handoff/text extraction,
  citation/CSL, PlainMath/MathML, XML/HTML DOM, Unicode/charset, or
  syntax-highlighting support rows.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:src/Text/Pandoc/Writers/HTML.hs | sed -n '1580,1602p'` inspected the
  upstream `Link` branches; `git -C .upstream-cache/pandoc show
  HEAD:src/Text/Pandoc/Writers/Shared.hs | sed -n '813,823p'` inspected
  `removeLinks`; `git -C .upstream-cache/pandoc show
  HEAD:src/Text/Pandoc/Writers/HTML.hs | rg -n 'removeLinks|\(Link'` counted
  two HTML writer link branches using the helper; `git -C
  .upstream-cache/pandoc show HEAD:test/testsuite.native | rg -c '\bLink\b'`
  counted 29 `Link` constructors; `php -l` passed for `HtmlWriter.php`,
  `MarkdownReaderTest.php`, and
  `wordpress-html-writer-remove-links-handoff.php`; `php
  lanes/pandoc/examples/wordpress-html-writer-remove-links-handoff.php`
  emitted the HTML nested-link-label preview and WordPress review block; `php
  tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1
  file, 3,832 assertions, 0 failures. No no-argument root harness was assigned
  or started.

## 2026-05-24 HTML Writer RawInline Slice

- Focused mapped count is now 2,065 Markdown/HTML/Native/WordPress checks.
- Added bounded `HtmlWriter` coverage from upstream
  `Text.Pandoc.Writers.HTML` `RawInline` behavior without invoking upstream
  Pandoc, converter shell-outs, PDF processing, ZIP/package parsing, external
  renderers, citation/CSL engines, or broader syntax-highlighting support.
- Targeted upstream inspection covered the `RawInline` branch in
  `src/Text/Pandoc/Writers/HTML.hs`, the single `RawInline` constructor
  occurrence in `test/testsuite.native`, and `test/command/parse-raw.md` raw
  attribute examples. This slice maps HTML-family raw inline pass-through and
  non-HTML raw inline omission. TeX math-environment/ref conversion from raw
  TeX inlines is explicitly not claimed and remains behind a future bounded
  math support gate if required.
- `HtmlWriter` now renders `raw_inline` nodes whose format is `html`,
  `html4`, `html5`, EPUB, or supported HTML slide formats as pre-escaped HTML,
  and renders other raw inline formats as empty output on the HTML writer path.
- Added `examples/wordpress-html-writer-raw-inline-handoff.php` for a
  WordPress source-review packet that preserves trusted inline HTML badges and
  markups inside a reviewable block-editor HTML section while omitting a TeX
  citation payload from the HTML preview.
- Dependency-closure checkpoint: this slice remains lane-local. It does not
  activate shared ZIP/OpenXML/OpenDocument, PDF handoff/text extraction,
  citation/CSL, PlainMath/MathML, Unicode/charset, or syntax-highlighting
  support rows.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:src/Text/Pandoc/Writers/HTML.hs | sed -n '1515,1595p'` inspected the
  upstream `RawInline` branch; `git -C .upstream-cache/pandoc show
  HEAD:test/testsuite.native | rg -c '\bRawInline\b'` counted 1 constructor
  occurrence; `git -C .upstream-cache/pandoc show
  HEAD:test/command/parse-raw.md` inspected raw-attribute examples; `php -l`
  passed for `HtmlWriter.php`, `MarkdownReaderTest.php`, and
  `wordpress-html-writer-raw-inline-handoff.php`; `php
  lanes/pandoc/examples/wordpress-html-writer-raw-inline-handoff.php` emitted
  the HTML raw-inline preview and WordPress review block; `php
  tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1
  file, 3,825 assertions, 0 failures. No no-argument root harness was assigned
  or started.

## 2026-05-24 HTML Writer SoftBreak/LineBreak Slice

- Focused mapped count is now 2,057 Markdown/HTML/Native/WordPress checks.
- Added bounded `HtmlWriter` coverage from upstream
  `Text.Pandoc.Writers.HTML` `inlineToHtml` break handling without invoking
  upstream Pandoc, converter shell-outs, PDF processing, ZIP/package parsing,
  external renderers, citation/CSL engines, or broader syntax-highlighting
  support.
- Targeted upstream inspection covered `SoftBreak` output under `WrapAuto`,
  `WrapNone`, and `WrapPreserve`, the hard `LineBreak` branch that emits
  `<br />` plus a following newline, `Text.Pandoc.Options.WrapOption`
  spellings/defaults, and the 30 `SoftBreak`/`LineBreak` occurrences in
  `test/testsuite.native`.
- `HtmlWriter` now defaults softbreaks to a space, preserves source line folds
  only when `writerWrapText`/`wrap` is `preserve` or `wrap-preserve`, and
  keeps hard line breaks as semantic HTML breaks followed by a newline.
- Added `examples/wordpress-html-writer-softbreak-handoff.php` for a
  WordPress source-review packet that compares compact HTML output with
  source-line-preserving output inside a reviewable block-editor HTML section.
- Dependency-closure checkpoint: this slice remains lane-local. It does not
  activate shared ZIP/OpenXML/OpenDocument, PDF handoff/text extraction,
  citation/CSL, PlainMath/MathML, Unicode/charset, or syntax-highlighting
  support rows.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:src/Text/Pandoc/Writers/HTML.hs | sed -n '1400,1425p'` inspected the
  upstream break branches; `git -C .upstream-cache/pandoc show
  HEAD:src/Text/Pandoc/Options.hs | rg -n
  'WrapNone|WrapAuto|WrapPreserve|writerWrapText' -C 2` inspected option
  spellings/defaults; `git -C .upstream-cache/pandoc show
  HEAD:test/testsuite.native | rg -c '\bSoftBreak\b|\bLineBreak\b'` counted
  30 constructor occurrences; `php -l` passed for `HtmlWriter.php`,
  `MarkdownReaderTest.php`, and `wordpress-html-writer-softbreak-handoff.php`;
  `php lanes/pandoc/examples/wordpress-html-writer-softbreak-handoff.php`
  emitted compact, source-line-preserving, and WordPress review block output;
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed
  1 file, 3,817 assertions, 0 failures. No no-argument root harness was
  assigned or started.

## 2026-05-24 HTML Writer Span-Like Class Lowering Slice

- Focused mapped count is now 2,049 Markdown/HTML/Native/WordPress checks.
- Added bounded `HtmlWriter` coverage from upstream
  `Text.Pandoc.Writers.HTML` `Span` class lowering without invoking upstream
  Pandoc, converter shell-outs, PDF processing, ZIP/package parsing, external
  renderers, citation/CSL engines, or broader syntax-highlighting support.
- Targeted upstream inspection covered the `Span` branch in
  `src/Text/Pandoc/Writers/HTML.hs`, `htmlSpanLikeElements` in
  `src/Text/Pandoc/Shared.hs`, and `test/command/nested-spanlike.md`. The
  slice maps `kbd`, `mark`, `dfn`, and `abbr` span-like elements,
  `smallcaps` and `underline` composition, class dropping before the first
  span-like marker, later non-special class retention on the outer lowered
  tag, id/data/title attribute carrying, and `csl-no-emph`,
  `csl-no-strong`, and `csl-no-smallcaps` style reset markers.
- Added `examples/wordpress-html-writer-spanlike-handoff.php` for a WordPress
  source-review packet that preserves keyboard shortcuts, highlighted publish
  preview text, and abbr/dfn source notes inside a reviewable block-editor
  HTML section.
- Dependency-closure checkpoint: this slice remains lane-local. It does not
  activate shared ZIP/OpenXML/OpenDocument, PDF handoff/text extraction,
  citation/CSL, PlainMath/MathML, Unicode/charset, or syntax-highlighting
  support rows.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:src/Text/Pandoc/Writers/HTML.hs | sed -n '1408,1452p'` inspected the
  upstream `Span` branch; `git -C .upstream-cache/pandoc show
  HEAD:src/Text/Pandoc/Shared.hs | rg -n 'htmlSpanLikeElements' -C 3`
  inspected the bounded element set; `git -C .upstream-cache/pandoc show
  HEAD:test/command/nested-spanlike.md` inspected the command example; `php -l`
  passed for `HtmlWriter.php`, `MarkdownReaderTest.php`, and
  `wordpress-html-writer-spanlike-handoff.php`; `php
  lanes/pandoc/examples/wordpress-html-writer-spanlike-handoff.php` emitted the
  HTML span-like preview and WordPress review block; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 3,807 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 HTML Writer Media Category Slice

- Focused mapped count is now 2,027 Markdown/HTML/Native/WordPress checks.
- Added bounded `HtmlWriter` coverage from upstream
  `Text.Pandoc.Writers.HTML` image media-category output and
  `Text.Pandoc.MIME.mediaCategory` behavior without invoking upstream Pandoc,
  fetching media, converter shell-outs, PDF processing, ZIP/package parsing,
  external renderers, or broader rich document-format support.
- Targeted upstream inspection covered `test/command/video-audio.md` with five
  command-output cases, `src/Text/Pandoc/Writers/HTML.hs` `Image` branch cases
  for image/video/audio/embed output, and `src/Text/Pandoc/MIME.hs`
  `mediaCategory`. The slice maps mp4/webm video output with fallback links
  and `controls`, mp3 audio output, PDF generic embed output, ordinary image
  output, image text fallback labels, and width attributes on media nodes.
- Added `examples/wordpress-html-writer-media-handoff.php` for a WordPress
  source-review packet that preserves the HTML media preview inside a
  reviewable block-editor HTML section.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:test/command/video-audio.md` inspected the five media command examples;
  `git -C .upstream-cache/pandoc show HEAD:src/Text/Pandoc/Writers/HTML.hs |
  sed -n '1600,1640p'` inspected the upstream image/media branch; `git -C
  .upstream-cache/pandoc show HEAD:src/Text/Pandoc/MIME.hs | sed -n '1,70p'`
  inspected `mediaCategory`; `php -l` passed for `HtmlWriter.php`,
  `MarkdownReaderTest.php`, and `wordpress-html-writer-media-handoff.php`;
  `php lanes/pandoc/examples/wordpress-html-writer-media-handoff.php` emitted
  the HTML media preview and WordPress review block; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 3,789 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 HTML Reader Figure/Figcaption Slice

- Focused mapped count is now 2,017 Markdown/HTML/Native/WordPress checks.
- Added bounded `MarkdownReader` coverage from upstream
  `Text.Pandoc.Readers.HTML` `pFigure` and `pImage` behavior without invoking
  upstream Pandoc, a browser, converter shell-outs, ZIP/package parsing,
  external renderers, or broader XML/HTML support-library expansion.
- Targeted upstream inspection covered `src/Text/Pandoc/Readers/HTML.hs`
  `pFigure`/`pImage` branches, `test/command/html-read-figure.md` with five
  reader command examples, and `test/command/figures-html.md` with two
  reader figure examples. The slice maps caption-before and caption-after
  ordering, missing captions, p-wrapped image bodies, rich caption inlines,
  preserved figure classes/data attributes, bare image `Plain` body blocks,
  and list body content inside figures.
- Added `fixtures/upstream-html-figure-caption.html` and
  `examples/wordpress-native-html-figure-handoff.php` for a WordPress
  source-media review packet that keeps source media id/classes, image
  alt/title metadata, and rich figcaption links/emphasis reviewable as a
  core image block.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:src/Text/Pandoc/Readers/HTML.hs | sed -n '632,652p'` inspected
  `pFigure`; `git -C .upstream-cache/pandoc show
  HEAD:src/Text/Pandoc/Readers/HTML.hs | sed -n '812,850p'` inspected
  `pImage`; `git -C .upstream-cache/pandoc show
  HEAD:test/command/html-read-figure.md` inspected five figure reader
  examples; `git -C .upstream-cache/pandoc show HEAD:test/command/figures-html.md`
  inspected two reader examples; `php -l` passed for `MarkdownReader.php`,
  `MarkdownReaderTest.php`, and `wordpress-native-html-figure-handoff.php`;
  `php lanes/pandoc/examples/wordpress-native-html-figure-handoff.php`
  emitted the WordPress image-block preview; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 3,783 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 HTML Reader Native Div-Like Section/Aside Slice

- Focused mapped count is now 2,005 Markdown/HTML/Native/WordPress checks.
- Added bounded `MarkdownReader` coverage from upstream
  `Text.Pandoc.Readers.HTML` native div-like parsing for `section` and
  `aside` under `Ext_native_divs`, extending the existing `main` and `header`
  coverage without invoking upstream Pandoc, a browser, converter shell-outs,
  ZIP/package parsing, external renderers, or broader XML/HTML support-library
  expansion.
- Targeted upstream inspection covered `test/Tests/Readers/HTML.hs` native
  div tests for `main`/`header` and `src/Text/Pandoc/Readers/HTML.hs`
  `isDivLike`/`pDiv` branches for `div`, `section`, `header`, `main`, and
  `aside`. The slice maps section/aside wrapper classes, preserved
  id/class/data attributes, nested block contents, and the upstream rule that
  clears a first heading id when it is the same as the native div wrapper id.
- Added `fixtures/upstream-html-section-aside-native-divs.html` and
  `examples/wordpress-native-html-section-aside-handoff.php` for a WordPress
  source-review packet that keeps source section content and migration-note
  aside content reviewable as block-editor HTML with stable wrapper metadata.
- Additional static smoke evidence: a local PHP sweep over upstream Native
  expectation files under `test/odt/native`, `test/docx`, `test/epub`, and
  `test/html-reader.native` parsed 145 files, rendered them through
  `NativeWriter`, and rendered them through `WordPressBlockWriter` with 0
  exceptions. This is not full upstream runner parity and does not claim all
  fixture semantics, but it strengthens the cloned static denominator evidence.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:test/Tests/Readers/HTML.hs | sed -n '80,135p'` inspected native-div
  tests; `git -C .upstream-cache/pandoc show
  HEAD:src/Text/Pandoc/Readers/HTML.hs | sed -n '480,520p'` and `sed -n
  '190,246p'` inspected `isDivLike`/`pDiv` branches; `php -l` passed for
  `MarkdownReader.php`, `MarkdownReaderTest.php`, and
  `wordpress-native-html-section-aside-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-html-section-aside-handoff.php`
  emitted the main/section/aside WordPress preview; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 3,754 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 HTML Writer Math Method Slice

- Focused mapped count is now 1,995 Markdown/HTML/Native/WordPress checks.
- Added bounded `HtmlWriter` coverage from upstream
  `Text.Pandoc.Writers.HTML` math output branches for MathJax, KaTeX,
  WebTeX, and GladTeX methods without invoking upstream Pandoc, TeXMath
  conversion, MathML conversion, image fetching, browser tooling, converter
  shell-outs, ZIP/package parsing, external renderers, or a broader math
  support library.
- The cloned static denominator remains 2,276 inspected upstream
  test/data/benchmark artifacts. Targeted subinventory for this slice
  inspected the upstream `Math` branch in
  `src/Text/Pandoc/Writers/HTML.hs`, the default writer option in
  `src/Text/Pandoc/Options.hs`, counted 7 `Math` constructors in
  `test/testsuite.native` including 6 `InlineMath` and 1 `DisplayMath`, and
  counted 2 math-targeted Markdown command fixtures by `git ls-tree` path
  inspection.
- `HtmlWriter` now renders bounded `math` AST nodes as
  `span.math.inline`/`span.math.display` output, preserving MathJax delimiters
  for `htmlMathMethod=mathjax` and raw TeX payloads for
  `writerHTMLMathMethod=katex`. It also renders WebTeX as `img` tags with
  vertical-align style, `math inline`/`math display` classes, stripped TeX in
  `alt`/`title`, and URL-encoded `\textstyle`/`\displaystyle` TeX payloads,
  plus GladTeX `eq` tags with `env="math"` or `env="displaymath"`.
  Unsupported PlainMath/MathML conversion stays an escaped TeX fallback rather
  than claiming TeX parser/MathML parity.
- Added `examples/wordpress-html-writer-math-handoff.php` for a WordPress
  source-review packet that emits MathJax, KaTeX, WebTeX, and GladTeX HTML
  previews while the matching `WordPressBlockWriter` output keeps equation
  source reviewable as block editor HTML.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:src/Text/Pandoc/Writers/HTML.hs | sed -n '1500,1575p'` inspected the
  WebTeX/GladTeX/MathJax/KaTeX/PlainMath/MathML branches; `git -C
  .upstream-cache/pandoc show HEAD:src/Text/Pandoc/Options.hs | rg -n
  'HTMLMathMethod|MathJax|KaTeX|WebTeX|GladTeX|PlainMath|MathML|writerHTMLMathMethod|defaultWebTeXURL'`
  inspected defaults and method names; `git -C .upstream-cache/pandoc show
  HEAD:src/Text/Pandoc/URI.hs | sed -n '1,80p'` inspected the `urlEncode`
  wiring; `git -C .upstream-cache/pandoc show
  HEAD:test/testsuite.native | rg -c '\bMath\b'` counted 7 Math constructors;
  `rg -c 'InlineMath'` counted 6 and `rg -c 'DisplayMath'` counted 1; `git -C
  .upstream-cache/pandoc ls-tree -r --name-only HEAD test/command | rg -c
  '(math|mathml|katex|mathjax|latex-math|tex-group).*\.md$'` counted 2 command
  fixtures; `php -l` passed for `HtmlWriter.php`, `MarkdownReaderTest.php`,
  and `wordpress-html-writer-math-handoff.php`; `php
  lanes/pandoc/examples/wordpress-html-writer-math-handoff.php` emitted
  MathJax, KaTeX, WebTeX, GladTeX, and WordPress previews; `php
  tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1
  file, 3,732 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 HTML Writer Citation Role Slice

- Focused mapped count is now 1,979 Markdown/HTML/Native/WordPress checks.
- Added bounded `HtmlWriter` coverage from upstream
  `Text.Pandoc.Writers.HTML` citation and footnote role branches without
  invoking upstream Pandoc, citeproc/CSL processing, browser tooling,
  converter shell-outs, ZIP/package parsing, external renderers, or rich
  document-format support.
- The cloned static denominator remains 2,276 inspected upstream
  test/data/benchmark artifacts. Targeted subinventory for this slice
  inspected the upstream `Note`, `Cite`, `addBibliorefRole`, `data-cites`,
  `doc-noteref`, and `doc-backlink` branches in
  `src/Text/Pandoc/Writers/HTML.hs`, counted 5 `Note` constructors in
  `test/testsuite.native`, and counted 72 footnote/citation Markdown command
  fixtures by `git ls-tree` path inspection.
- `HtmlWriter` now renders bounded `citation` AST nodes as
  `<span class="citation" data-cites="...">`, adds `role="doc-biblioref"` to
  citation display links targeting `#ref-*`, preserves ordinary citation
  display links as ordinary links, and emits `role="doc-noteref"` and
  `role="doc-backlink"` on HTML footnote reference/backlink anchors.
- Added `examples/wordpress-html-writer-citation-role-handoff.php` for a
  WordPress source-review packet that emits an accessible HTML citation and
  footnote preview while the matching `WordPressBlockWriter` output keeps the
  citation payload and bibliography source reviewable as WordPress blocks.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:src/Text/Pandoc/Writers/HTML.hs | rg -n
  '\(Note contents\)|\(Cite cits il\)|addBibliorefRole|doc-noteref|doc-backlink|footnote-back|data-cites'`
  inspected the branch targets; `git -C .upstream-cache/pandoc show
  HEAD:test/testsuite.native | rg -c '\bNote\b'` counted 5 Note constructors;
  `git -C .upstream-cache/pandoc ls-tree -r --name-only HEAD test/command |
  rg -c '(footnote|note|citeproc|citation|cite-in-inline-note|locators).*\.md$'`
  counted 72 command fixtures; `php -l` passed for `HtmlWriter.php`,
  `MarkdownReaderTest.php`, and
  `wordpress-html-writer-citation-role-handoff.php`; `php
  lanes/pandoc/examples/wordpress-html-writer-citation-role-handoff.php`
  emitted the HTML preview plus WordPress source-block handoff; `php
  tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1
  file, 3,716 assertions, 0 failures. No no-argument root harness was
  assigned or started.

## 2026-05-24 HTML Writer Wrapper/CSL Div Slice

- Focused mapped count is now 1,971 Markdown/HTML/Native/WordPress checks.
- Added bounded `HtmlWriter` coverage from upstream
  `Text.Pandoc.Writers.HTML` `Div` wrapper and CSL bibliography branches
  without invoking upstream Pandoc, a CSL processor, browser tooling,
  converter shell-outs, ZIP/package parsing, external renderers, or rich
  document-format support.
- The cloned static denominator remains 2,276 inspected upstream
  test/data/benchmark artifacts. Targeted subinventory for this slice counted
  26 upstream `test/command/*.csl` files and 70 citeproc/citation Markdown
  command fixtures by `git ls-tree` path inspection, kept the existing 5
  `Div` constructors in `test/testsuite.native`, and inspected the upstream
  wrapper transfer, `refs`/`csl-bib-body`, `csl-entry`, role, entry-spacing,
  and `paraToPlain` branches in `src/Text/Pandoc/Writers/HTML.hs`.
- `HtmlWriter` now unwraps `Div` nodes with `wrapper=1` onto the single child
  root element, removing the wrapper marker while preserving id/classes and
  data attributes. CSL bibliography bodies emit `role="list"`, CSL entries
  emit `role="listitem"`, and paragraphs inside `csl-entry` nodes render as
  plain inline content instead of nested paragraphs.
- Added `examples/wordpress-html-writer-csl-wrapper-handoff.php` for a
  WordPress bibliography review packet that emits an accessible HTML preview
  and the matching WordPress HTML-block source handoff.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:src/Text/Pandoc/Writers/HTML.hs | rg -n
  'wrapper|csl-bib-body|csl-entry|entry-spacing|role"|paraToPlain'` inspected
  the branch targets; `git -C .upstream-cache/pandoc ls-tree -r --name-only
  HEAD test/command | rg -c '\.csl$'` counted 26 CSL files; `git -C
  .upstream-cache/pandoc ls-tree -r --name-only HEAD test/command | rg -c
  '(citeproc|cite-in-inline-note|locators).*\.md$'` counted 70 citation
  command fixtures; `php -l` passed for `HtmlWriter.php`,
  `MarkdownReaderTest.php`, and
  `wordpress-html-writer-csl-wrapper-handoff.php`; `php
  lanes/pandoc/examples/wordpress-html-writer-csl-wrapper-handoff.php` emitted
  the wrapper paragraph preview plus CSL role/list preview and WordPress
  blocks; `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  passed 1 file, 3,712 assertions, 0 failures. No no-argument root harness
  was assigned or started.

## 2026-05-24 HTML Writer RawBlock/Div Slice

- Focused mapped count is now 1,961 Markdown/HTML/Native/WordPress checks.
- Added bounded `HtmlWriter` coverage from upstream
  `Text.Pandoc.Writers.HTML` `Div` and `RawBlock` rendering without invoking
  upstream Pandoc, browser tooling, converter shell-outs, ZIP/package parsing,
  external renderers, or rich document-format support.
- The cloned static denominator remains 2,276 inspected upstream
  test/data/benchmark artifacts. Targeted subinventory for this slice counted
  23 `RawBlock` constructors and 5 `Div` constructors in
  `test/testsuite.native`, and inspected the upstream `Div`, generic div,
  section div, `RawBlock`, `isRawHtml`, EPUB raw-format, and slide raw-format
  branches.
- `HtmlWriter` now renders bounded `div` AST nodes as `div` wrappers,
  section-class divs as `section` elements, nested block contents inside those
  wrappers, and column `width` keyvals as `style` width declarations. It
  passes through HTML-format raw blocks (`html`, `html4`, `html5`, EPUB, and
  HTML slide variants) and omits non-HTML raw blocks in HTML output.
- Added `examples/wordpress-html-writer-raw-div-handoff.php` for a WordPress
  source-review packet that emits an HTML section preview with raw HTML and
  nested div structure while the matching `WordPressBlockWriter` output keeps
  raw source packets reviewable inside the div handoff.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:src/Text/Pandoc/Writers/HTML.hs | sed -n '700,860p'`, `sed -n
  '860,960p'`, `sed -n '1788,1812p'`, and `sed -n '1812,1832p'` were
  inspected for div/raw-block writer semantics; `git -C .upstream-cache/pandoc
  show HEAD:test/testsuite.native | rg -c "RawBlock"` counted 23 constructors
  and `rg -c "Div \\("` counted 5 constructors; `php -l` passed for
  `HtmlWriter.php`, `MarkdownReaderTest.php`, and
  `wordpress-html-writer-raw-div-handoff.php`; `php
  lanes/pandoc/examples/wordpress-html-writer-raw-div-handoff.php` emitted an
  HTML section preview plus WordPress raw-source review handoff; `php
  tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1
  file, 3,706 assertions, 0 failures. No no-argument root harness was assigned
  or started.

## 2026-05-24 HTML Writer Table Slice

- Focused mapped count is now 1,953 Markdown/HTML/Native/WordPress checks.
- Added bounded `HtmlWriter` coverage from upstream
  `Text.Pandoc.Writers.HTML` `Table` rendering without invoking upstream
  Pandoc, browser tooling, converter shell-outs, ZIP/package parsing, external
  renderers, or rich document-format support.
- The cloned static denominator remains 2,276 inspected upstream
  test/data/benchmark artifacts. Targeted subinventory for this slice counted
  1 `Table` constructor in `test/testsuite.native` and inspected the upstream
  `tableToHtml`, `tableBodyToHtml`, `tableHeadToHtml`, `tableFootToHtml`,
  `tablePartToHtml`, `colSpecListToHtml`, `tableRowToHtml`, and
  `tableCellToHtml` branches for caption, colgroup, section, row, cell,
  colspan/rowspan, row-head, and alignment behavior.
- `HtmlWriter` now renders bounded `table` AST nodes as HTML tables with
  table id/classes/key/value attributes, caption inlines, colgroups from
  explicit column widths, table-level width style when explicit widths sum
  below 100%, `thead`/`tbody`/`tfoot` sections, intermediate body head rows,
  row-head columns, cell spans, escaped block cell content, and column/cell
  alignment styles.
- Added `examples/wordpress-html-writer-table-handoff.php` for a WordPress
  source-review packet that emits an HTML table preview with caption,
  colgroup, section metadata, row-head cells, spans, and review text while the
  matching `WordPressBlockWriter` output emits a core table handoff.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:src/Text/Pandoc/Writers/HTML.hs | sed -n '1140,1188p'`,
  `sed -n '1180,1375p'`, and `sed -n '1375,1495p'` were inspected for table
  writer semantics; `git -C .upstream-cache/pandoc show
  HEAD:test/testsuite.native | rg -n "Table|TableHead|TableBody|TableFoot|Caption|ColSpec"`
  found the table fixture area; `php -l` passed for `HtmlWriter.php`,
  `MarkdownReaderTest.php`, and `wordpress-html-writer-table-handoff.php`;
  `php lanes/pandoc/examples/wordpress-html-writer-table-handoff.php` emitted
  an HTML table preview plus WordPress table handoff; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 3,698 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 HTML Writer Structural Block Slice

- Focused mapped count is now 1,940 Markdown/HTML/Native/WordPress checks.
- Added bounded `HtmlWriter` coverage from upstream
  `Text.Pandoc.Writers.HTML` `HorizontalRule`, `LineBlock`, and `Figure`
  branches without invoking upstream Pandoc, browser tooling, converter
  shell-outs, ZIP/package parsing, external renderers, or rich document-format
  support.
- The cloned static denominator remains 2,276 inspected upstream
  test/data/benchmark artifacts. Targeted subinventory for this slice counted
  13 `HorizontalRule` occurrences and 1 `Figure` occurrence in
  `test/testsuite.native`, and inspected the upstream HTML writer branches for
  `<hr />`, `div.line-block` with `LineBreak` separators, HTML5 `figure`
  output, `figcaption`, and `aria-hidden` captions when the caption text
  matches the image alt text.
- `HtmlWriter` now renders bounded `horizontal_rule`, `line_block`, and
  `figure` AST nodes as native HTML output. Figure output preserves
  id/classes/key/value attributes on `<figure>`, renders the bounded body
  blocks, emits captions, and marks alt-equivalent captions with
  `aria-hidden="true"`.
- Added `examples/wordpress-html-writer-figure-line-handoff.php` for a
  WordPress source-review packet that emits an HTML figure/line-block/section
  break preview while the matching `WordPressBlockWriter` output emits image,
  paragraph, and separator block handoff markup.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:src/Text/Pandoc/Writers/HTML.hs | sed -n '760,780p'`, `sed -n
  '936,966p'`, and `sed -n '1080,1116p'` were inspected for the `LineBlock`,
  `HorizontalRule`, and `Figure` branches; `git -C .upstream-cache/pandoc show
  HEAD:test/testsuite.native | rg -c 'HorizontalRule'` counted 13
  occurrences; `git -C .upstream-cache/pandoc show HEAD:test/testsuite.native
  | rg -c '^  , Figure|^  \\[ Figure|Figure'` counted 1 occurrence; `php -l`
  passed for `HtmlWriter.php`, `MarkdownReaderTest.php`, and
  `wordpress-html-writer-figure-line-handoff.php`; `php
  lanes/pandoc/examples/wordpress-html-writer-figure-line-handoff.php` emitted
  an HTML preview plus WordPress image/paragraph/separator handoff; `php
  tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1
  file, 3,694 assertions, 0 failures. No no-argument root harness was
  assigned or started.

## 2026-05-24 HTML Writer CodeBlock Fallback Slice

- Focused mapped count is now 1,928 Markdown/HTML/Native/WordPress checks.
- Added bounded `HtmlWriter` coverage from upstream
  `Text.Pandoc.Writers.HTML` `CodeBlock` fallback behavior without invoking
  upstream Pandoc, browser tooling, converter shell-outs, ZIP/package parsing,
  external renderers, or a syntax-highlighting engine.
- The cloned static denominator remains 2,276 inspected upstream
  test/data/benchmark artifacts. Targeted subinventory for this slice counted
  11 `CodeBlock` constructors in `test/testsuite.native` and inspected the
  upstream HTML writer `CodeBlock` branch where non-highlighted fallback output
  renders `pre > code` and keeps the original identifier, classes, and
  key/value attributes on `pre`.
- `HtmlWriter` now renders bounded `code_block` AST nodes as HTML
  `<pre><code>...</code></pre>` output, escapes code text, preserves multiline
  code, and renders fallback id/class/data attributes on `pre`.
- Added `examples/wordpress-html-writer-code-block-handoff.php` for a
  WordPress source-review packet that emits an HTML code-block preview with
  source attributes while the matching `WordPressBlockWriter` output emits a
  block-editor code handoff.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:src/Text/Pandoc/Writers/HTML.hs | sed -n '949,1000p'` was inspected
  for `CodeBlock` fallback semantics; `git -C .upstream-cache/pandoc show
  HEAD:test/testsuite.native | rg -c 'CodeBlock'` counted 11 constructors;
  `php -l` passed for `HtmlWriter.php`, `MarkdownReaderTest.php`, and
  `wordpress-html-writer-code-block-handoff.php`; `php
  lanes/pandoc/examples/wordpress-html-writer-code-block-handoff.php` emitted
  an HTML code-block preview plus WordPress code-block handoff; `php
  tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1
  file, 3,684 assertions, 0 failures. No no-argument root harness was
  assigned or started.

## 2026-05-24 HTML Writer List Slice

- Focused mapped count is now 1,922 Markdown/HTML/Native/WordPress checks.
- Added bounded `HtmlWriter` coverage from upstream
  `Text.Pandoc.Writers.HTML` `BulletList` and `OrderedList` branches plus
  `test/testsuite.native` list constructor shapes without invoking upstream
  Pandoc, browser tooling, converter shell-outs, ZIP/package parsing, or rich
  document-format support.
- The cloned static denominator remains 2,276 inspected upstream
  test/data/benchmark artifacts. Targeted subinventory for this slice counted
  17 `BulletList` and 19 `OrderedList` constructors in `test/testsuite.native`
  and inspected the upstream HTML writer list branches for list item wrapping,
  `ul`/`ol` emission, task-list class handling, ordered `start` attributes,
  and HTML5 ordered-list `type` attributes for decimal/roman/alpha styles.
- `HtmlWriter` now renders bounded `bullet_list` and `ordered_list` AST nodes
  as HTML lists. Tight Plain-shaped items render as bare `li` content, loose
  Para-shaped items keep paragraph wrappers, nested lists remain inside the
  parent `li`, explicit ordered-list styles emit `type`, non-1 starts emit
  `start`, and task-list items render checkbox labels under `ul.task-list`.
- Added `examples/wordpress-html-writer-list-handoff.php` for a WordPress
  source-review packet that emits an HTML import preview with ordered
  start/type metadata, nested list evidence, and task checkbox labels while the
  matching `WordPressBlockWriter` output emits block-list handoff markup.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:src/Text/Pandoc/Writers/HTML.hs | sed -n '1048,1075p'` was inspected
  for `BulletList`/`OrderedList` branches; `sed -n '480,530p'` and `sed -n
  '1370,1382p'` were inspected for list item wrapping; `git -C
  .upstream-cache/pandoc show HEAD:test/testsuite.native | sed -n '340,450p'`
  was inspected for tight/loose/nested list fixture shapes; `rg -c` over that
  targeted fixture counted 17 `BulletList` and 19 `OrderedList` constructors;
  `php -l` passed for `HtmlWriter.php`, `MarkdownReaderTest.php`, and
  `wordpress-html-writer-list-handoff.php`; `php
  lanes/pandoc/examples/wordpress-html-writer-list-handoff.php` emitted HTML
  list preview plus WordPress block-list handoff; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 3,679 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 LaTeX Writer Ordered-List Slice

- Focused mapped count is now 1,908 Markdown/HTML/Native/WordPress checks.
- Added bounded `LatexWriter` coverage from upstream
  `Text.Pandoc.Writers.LaTeX` `OrderedList` and `Text.Pandoc.Shared`
  `isTightList` behavior without invoking upstream Pandoc, a TeX/PDF engine,
  templates, converter shell-outs, or rich package/document-format support.
- The cloned static denominator remains 2,276 inspected upstream
  test/data/benchmark artifacts. This slice maps upstream ordered-list label
  and counter semantics: explicit lower-roman, decimal, and upper-alpha styles
  emit LaTeX `\def\label...` commands; non-1 starts emit `\setcounter`; nested
  ordered lists use `enumii`; tight lists emit `\tightlist`; and paragraph
  list items remain loose without `\tightlist`.
- `LatexWriter` now tracks nested ordered-list depth, renders first-four-level
  enumerate counters (`enumi` through `enumiv`), applies Pandoc-style label
  commands for one-paren/two-parens/period delimiters, preserves start offsets,
  and indents nested list output inside list items. The existing blockquote and
  inline-code list expectations were updated to include Pandoc's tight-list
  line for tight list shapes.
- Added `examples/wordpress-latex-ordered-list-handoff.php` for a WordPress
  source review packet that emits LaTeX ordered-list labels/counters while the
  matching WordPress block handoff keeps `start` and `type` metadata on the
  ordered lists.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:src/Text/Pandoc/Writers/LaTeX.hs | sed -n '615,650p'` was inspected for
  `OrderedList` label/counter/tight-list behavior; `git -C
  .upstream-cache/pandoc show HEAD:src/Text/Pandoc/Shared.hs | sed -n
  '648,660p'` was inspected for `isTightList`; `php -l` passed for
  `LatexWriter.php`, `MarkdownReaderTest.php`, and
  `wordpress-latex-ordered-list-handoff.php`; `php
  lanes/pandoc/examples/wordpress-latex-ordered-list-handoff.php` emitted
  LaTeX ordered-list counter output plus WordPress ordered-list handoff; `php
  tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1
  file, 3,670 assertions, 0 failures. No no-argument root harness was assigned
  or started.

## 2026-05-24 LaTeX Writer Quote/Horizontal-Rule Slice

- Focused mapped count is now 1,898 Markdown/HTML/Native/WordPress checks.
- Added bounded `LatexWriter` coverage from upstream
  `Text.Pandoc.Writers.LaTeX` `BlockQuote` and `HorizontalRule` branches
  without invoking upstream Pandoc, a TeX/PDF engine, templates, or converter
  shell-outs.
- The cloned static denominator remains 2,276 inspected upstream
  test/data/benchmark artifacts. This slice maps the direct upstream writer
  semantics where block quotes render as `quote` environments and horizontal
  rules render as Pandoc's centered `\rule{0.5\linewidth}{0.5pt}` block. It
  also maps the `test/testsuite.native` Block Quotes and horizontal-rule
  fixture shapes, including quote children with paragraphs, code blocks,
  ordered lists, nested quotes, and section separators.
- `LatexWriter` now renders bounded `blockquote` AST nodes as nested LaTeX
  quote environments and renders `horizontal_rule` nodes as the centered rule
  output used by upstream Pandoc. This is a writer branch only and does not
  activate TeX/PDF execution, doctemplates, syntax highlighting, ZIP/package,
  OpenXML/OpenDocument, or rich document-format support gates.
- Added `examples/wordpress-latex-quote-hr-handoff.php` for a WordPress source
  review packet that emits a LaTeX quote plus centered section break while the
  matching WordPress block handoff keeps a core quote block, separator block,
  and following publish checklist paragraph.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:src/Text/Pandoc/Writers/LaTeX.hs | sed -n '470,510p'` and `sed -n
  '660,672p'` were inspected for `BlockQuote` and `HorizontalRule` semantics;
  `git -C .upstream-cache/pandoc show HEAD:test/testsuite.native | sed -n
  '230,296p'` was inspected for the Block Quotes fixture shape; `php -l`
  passed for `LatexWriter.php`, `MarkdownReaderTest.php`, and
  `wordpress-latex-quote-hr-handoff.php`; `php
  lanes/pandoc/examples/wordpress-latex-quote-hr-handoff.php` emitted LaTeX
  quote/rule output plus WordPress quote/separator handoff; `php
  tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1
  file, 3,663 assertions, 0 failures. No no-argument root harness was assigned
  or started.

## 2026-05-24 LaTeX Writer Raw TeX Passthrough Slice

- Focused mapped count is now 1,891 Markdown/HTML/Native/WordPress checks.
- Added bounded `LatexWriter` coverage from upstream
  `Text.Pandoc.Writers.LaTeX` `RawInline` and `RawBlock` branches without
  invoking upstream Pandoc, a TeX/PDF engine, templates, or a converter
  shell-out.
- The cloned static denominator remains 2,276 inspected upstream
  test/data/benchmark artifacts. This slice maps the direct upstream writer
  semantics where `Format "tex"` and `Format "latex"` raw inlines/blocks pass
  through literally, while non-LaTeX raw formats are omitted from LaTeX output.
  It also maps the `test/testsuite.native` LaTeX section fixtures for
  `RawInline (Format "tex") "\\cite[22-23]{smith.1899}"` and the tabular
  `RawBlock (Format "tex")` packet.
- `LatexWriter` now renders bounded raw TeX/LaTeX inline and block AST nodes
  literally and keeps non-LaTeX raw nodes out of LaTeX output. This is a writer
  passthrough branch only and does not activate TeX/PDF execution,
  doctemplates, syntax highlighting, ZIP/package, OpenXML/OpenDocument, or rich
  document-format support gates.
- Added `examples/wordpress-latex-raw-tex-handoff.php` for a WordPress source
  review packet that emits raw citation/table TeX in the LaTeX reviewer export
  while the matching WordPress block handoff preserves raw TeX as review-safe
  inline/code markup.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:src/Text/Pandoc/Writers/LaTeX.hs | sed -n '570,592p'` and `sed -n
  '1070,1112p'` were inspected for the `RawBlock`/`RawInline` passthrough
  branches; `git -C .upstream-cache/pandoc show HEAD:test/testsuite.native |
  sed -n '1338,1425p'` and `sed -n '1488,1514p'` were inspected for the raw
  TeX citation and tabular fixtures; `php -l` passed for `LatexWriter.php`,
  `MarkdownReaderTest.php`, and `wordpress-latex-raw-tex-handoff.php`; `php
  lanes/pandoc/examples/wordpress-latex-raw-tex-handoff.php` emitted raw TeX
  LaTeX output plus WordPress block handoff; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 3,657 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 LaTeX Writer Highlighted Strikeout Inline-Code Slice

- Focused mapped count is now 1,883 Markdown/HTML/Native/WordPress checks.
- Added bounded `LatexWriter` coverage from upstream `Tests.Writers.LaTeX`
  `inline code` behavior for the `struck out and highlighted` case without
  invoking upstream Pandoc, a TeX/PDF engine, templates, or a syntax
  highlighting engine.
- The cloned static denominator remains 2,276 inspected upstream
  test/data/benchmark artifacts. This slice maps the direct upstream case
  where `strikeout (codeWith ("",["haskell"],[]) "foo" <> space <> str
  "bar")` renders as `\st{\mbox{\VERB|\NormalTok{foo}|} bar}`.
- `LatexWriter` now renders bounded Haskell inline code spans as
  `\VERB|\NormalTok{...}|` under default inline highlighting and keeps
  Pandoc's `\mbox{...}` protection inside strikeout. The disabled-highlight
  guard still falls back to `\texttt{...}`. This is a single inline-code
  token boundary and does not activate the broader syntax-highlighting,
  TeX/PDF, document-template, ZIP/package, or rich document-format support
  gates.
- Added `examples/wordpress-latex-highlighted-strikeout-code-handoff.php` for
  a WordPress source-review packet that emits highlighted strikeout LaTeX for
  a Haskell import helper while the matching WordPress block handoff preserves
  `<del>`, inline `code` class, and `data-source` metadata.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:test/Tests/Writers/LaTeX.hs | sed -n '55,95p'` was inspected for the
  bounded inline-code group; `git -C .upstream-cache/pandoc show
  HEAD:src/Text/Pandoc/Writers/LaTeX.hs | sed -n '1000,1125p'` was inspected
  for the `Code`/`VERB`/soul-command protection boundary; `php -l` passed for
  `LatexWriter.php`, `MarkdownReaderTest.php`, and
  `wordpress-latex-highlighted-strikeout-code-handoff.php`; `php
  lanes/pandoc/examples/wordpress-latex-highlighted-strikeout-code-handoff.php`
  emitted highlighted strikeout LaTeX plus WordPress block handoff; `php
  tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1
  file, 3,652 assertions, 0 failures. No no-argument root harness was
  assigned or started.

## 2026-05-24 LaTeX Writer Listing Code-Block Slice

- Focused mapped count is now 1,878 Markdown/HTML/Native/WordPress checks.
- Added bounded `LatexWriter` coverage from upstream `Tests.Writers.LaTeX`
  `code blocks` behavior for `writerHighlightMethod = IdiomaticHighlighting`
  without invoking upstream Pandoc, a TeX/PDF engine, templates, or a syntax
  highlighting engine.
- The cloned static denominator remains 2,276 inspected upstream
  test/data/benchmark artifacts. This slice maps two direct upstream cases:
  `codeBlockWith ("id",[],[]) "hi"` renders as `lstlisting` with
  `[label=id]`, and a plain `codeBlock "hi"` renders as `lstlisting` without
  an option bracket. Default `Verbatim` output remains covered by the
  footnote-code-block slice.
- `LatexWriter` now accepts `writerHighlightMethod`/`highlightMethod` values
  for IdiomaticHighlighting/listing output and renders bounded `code_block`
  AST nodes as `\begin{lstlisting}` / `\end{lstlisting}`, preserving node ids
  as listing labels. This is a writer option branch only; it does not activate
  broader TeX/PDF, document-template, syntax-highlighting, ZIP/package, or rich
  document-format support-library gates.
- Added `examples/wordpress-latex-listing-code-handoff.php` for a WordPress
  source-snippet review packet that emits LaTeX listing output with a stable
  snippet label while the WordPress block handoff preserves the code-block id,
  language class, and source metadata.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:test/Tests/Writers/LaTeX.hs | sed -n '35,75p'` was inspected for the
  bounded code-block listing group; `php -l` passed for `LatexWriter.php`,
  `MarkdownReaderTest.php`, and `wordpress-latex-listing-code-handoff.php`;
  `php lanes/pandoc/examples/wordpress-latex-listing-code-handoff.php` emitted
  `lstlisting` LaTeX output plus WordPress code-block handoff; `php
  tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1
  file, 3,648 assertions, 0 failures. No no-argument root harness was
  assigned or started.

## 2026-05-24 LaTeX Writer Underline/Strikeout Inline-Note Slice

- Focused mapped count is now 1,872 Markdown/HTML/Native/WordPress checks.
- Added bounded `LatexWriter` coverage from upstream `Tests.Writers.LaTeX`
  `inline note` behavior for `underline` and `strikeout`, plus the adjacent
  strikeout code-span mbox shape, without invoking upstream Pandoc, a TeX/PDF
  engine, templates, or syntax-highlighting tooling.
- The cloned static denominator remains 2,276 inspected upstream
  test/data/benchmark artifacts. This slice maps three direct upstream cases:
  multi-paragraph notes split outside `\ul{}`, multi-paragraph notes split
  outside `\st{}`, and strikeout code spans render as
  `\st{\mbox{\texttt{foo}} bar}`. The prior emph/strong inline-note cases
  remain covered.
- `LatexWriter` now renders bounded underline and strikeout inline AST nodes as
  LaTeX style commands, treats multi-block notes inside those style scopes as
  style boundaries with indented continuation footnote blocks, and wraps code
  spans inside strikeout with `\mbox{}`. This does not activate broader TeX/PDF,
  document-template, syntax-highlighting, ZIP/package, or rich
  document-format support-library gates.
- Added `examples/wordpress-latex-underline-strikeout-note-handoff.php` for a
  WordPress review packet that emits split LaTeX insertion/deletion mark output
  while preserving WordPress `<u>`/`<del>` inline markup plus endnotes.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:test/Tests/Writers/LaTeX.hs | sed -n '80,170p'` was inspected for the
  bounded inline-note and strikeout-code group; `php -l` passed for
  `LatexWriter.php`, `MarkdownReaderTest.php`, and
  `wordpress-latex-underline-strikeout-note-handoff.php`; `php
  lanes/pandoc/examples/wordpress-latex-underline-strikeout-note-handoff.php`
  emitted split LaTeX underline/strikeout note output plus WordPress block
  handoff; `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  passed 1 file, 3,642 assertions, 0 failures. No no-argument root harness
  was assigned or started.

## 2026-05-24 LaTeX Writer Styled Inline-Note Slice

- Focused mapped count is now 1,866 Markdown/HTML/Native/WordPress checks.
- Added bounded `LatexWriter` coverage from upstream `Tests.Writers.LaTeX`
  `inline note` behavior for `emph` and `strong` without invoking upstream
  Pandoc, a TeX/PDF engine, templates, or syntax-highlighting tooling.
- The cloned static denominator remains 2,276 inspected upstream
  test/data/benchmark artifacts. This slice maps five direct upstream cases
  from the inline-note group: multi-paragraph notes split outside `\emph{}`,
  multi-paragraph notes split outside `\textbf{}`, single-paragraph notes stay
  inside `\emph{}`, nested `emph`/`strong` note splits close and reopen both
  wrappers, and two multi-paragraph notes in one emphasized run split twice.
  Underline and strikeout note branches remain unmapped.
- `LatexWriter` now renders bounded emphasized and strong inline AST nodes as
  LaTeX style commands and treats multi-block notes inside those style scopes
  as style boundaries with indented continuation footnote blocks. This does not
  activate broader TeX/PDF, document-template, syntax-highlighting, ZIP/package,
  or rich document-format support-library gates.
- Added `examples/wordpress-latex-inline-note-style-handoff.php` for a
  WordPress review packet that emits the split LaTeX reviewer output while
  preserving emphasized/strong WordPress inline markup plus endnotes.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:test/Tests/Writers/LaTeX.hs | sed -n '80,160p'` was inspected for the
  bounded inline-note group; `php -l` passed for `LatexWriter.php`,
  `MarkdownReaderTest.php`, and
  `wordpress-latex-inline-note-style-handoff.php`; `php
  lanes/pandoc/examples/wordpress-latex-inline-note-style-handoff.php` emitted
  split LaTeX emphasized/strong note output plus WordPress block handoff; `php
  tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1
  file, 3,634 assertions, 0 failures. No no-argument root harness was
  assigned or started.

## 2026-05-24 LaTeX Writer Top-Level Division Slice

- Focused mapped count is now 1,856 Markdown/HTML/Native/WordPress checks.
- Added bounded `LatexWriter` coverage from upstream `Tests.Writers.LaTeX`
  `writer options` / `top-level division` behavior without invoking upstream
  Pandoc, a TeX/PDF engine, or syntax-highlighting tooling.
- The cloned static denominator remains 2,276 inspected upstream
  test/data/benchmark artifacts. This slice maps four direct normal-LaTeX
  writer option cases from the upstream group: `TopLevelChapter` maps level
  1/2/3 headings to `\chapter`, `\section`, and `\subsection`;
  `TopLevelPart` maps them to `\part`, `\chapter`, and `\section`;
  `TopLevelDefault` stays on section-style defaults; and an unnumbered
  part-level heading emits `\part*` plus `\addcontentsline{toc}{part}{...}`.
  The remaining beamer-specific branches stay unmapped.
- `LatexWriter` now accepts `writerTopLevelDivision`/`topLevelDivision`
  options and uses the selected command family for regular and unnumbered
  heading output. This does not activate broader TeX/PDF, document-template,
  ZIP/package, syntax-highlighting, or document-format support-library gates.
- Added `examples/wordpress-latex-top-level-division-handoff.php` for a
  WordPress review packet that emits LaTeX chapter/section reviewer output
  while preserving ordinary WordPress heading blocks for source book hierarchy.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:test/Tests/Writers/LaTeX.hs | sed -n '120,230p'` was inspected for the
  bounded writer-options group; `php -l` passed for `LatexWriter.php`,
  `MarkdownReaderTest.php`, and
  `wordpress-latex-top-level-division-handoff.php`; `php
  lanes/pandoc/examples/wordpress-latex-top-level-division-handoff.php`
  emitted LaTeX chapter/section output plus WordPress heading output; `php
  tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1
  file, 3,624 assertions, 0 failures. No no-argument root harness was
  assigned or started.

## 2026-05-24 LaTeX Writer Unnumbered-Heading Note Slice

- Focused mapped count is now 1,848 Markdown/HTML/Native/WordPress checks.
- Added bounded `LatexWriter` coverage from upstream `Tests.Writers.LaTeX`
  `headers` / `unnumbered header` behavior without invoking upstream Pandoc, a
  TeX/PDF engine, or syntax-highlighting tooling.
- The cloned static denominator remains 2,276 inspected upstream
  test/data/benchmark artifacts. This slice maps the direct upstream case
  where `headerWith ("foo",["unnumbered"],[]) 1 (text "Header 1" <> note
  (plain $ text "note"))` renders as a starred section with
  `\texorpdfstring{Header 1\footnote{note}}{Header 1}`, `\label{foo}`, and
  `\addcontentsline{toc}{section}{Header 1}`.
- `LatexWriter` now renders bounded unnumbered heading AST nodes with starred
  section commands, PDF-string fallbacks for note-bearing headings, labels, and
  table-of-contents lines. Note text is omitted from the PDF-string fallback.
  This does not activate broader TeX/PDF, document-template, ZIP/package,
  syntax-highlighting, or document-format support-library gates.
- Added `examples/wordpress-latex-unnumbered-heading-note-handoff.php` for a
  WordPress review packet that emits both LaTeX reviewer heading/footnote
  output and WordPress heading/endnote handoff markup for source audit context.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:test/Tests/Writers/LaTeX.hs` was inspected for the bounded headers
  group; `php -l` passed for `LatexWriter.php`, `MarkdownReaderTest.php`, and
  `wordpress-latex-unnumbered-heading-note-handoff.php`; `php
  lanes/pandoc/examples/wordpress-latex-unnumbered-heading-note-handoff.php`
  emitted LaTeX starred heading/footnote output plus WordPress heading/endnote
  output; `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  passed 1 file, 3,616 assertions, 0 failures. No no-argument root harness
  was assigned or started.

## 2026-05-24 LaTeX Writer Footnote-Code-Block Slice

- Focused mapped count is now 1,842 Markdown/HTML/Native/WordPress checks.
- Added bounded `LatexWriter` coverage from upstream `Tests.Writers.LaTeX`
  `code blocks` / `in footnotes` behavior without invoking upstream Pandoc, a
  TeX/PDF engine, or syntax-highlighting tooling.
- The cloned static denominator remains 2,276 inspected upstream
  test/data/benchmark artifacts. This slice maps the direct upstream case
  where a Note containing `Para "hi"` and `CodeBlock "hi"` renders as
  `\footnote{hi` plus a blank line and a `Verbatim` code block inside the
  footnote.
- `LatexWriter` now renders bounded `code_block` AST nodes as
  `\begin{Verbatim}` / `\end{Verbatim}` blocks and inline `note` nodes as
  `\footnote{...}`. This does not activate broader TeX/PDF, syntax
  highlighting, listings, document-template, ZIP/package, or document-format
  support-library gates.
- Added `examples/wordpress-latex-footnote-code-handoff.php` for a WordPress
  review packet that emits both LaTeX reviewer footnote/code output and
  WordPress endnote/code-block handoff markup for the same source audit note.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:test/Tests/Writers/LaTeX.hs` was inspected for the bounded code blocks
  and inline note groups; `php -l` passed for `LatexWriter.php`,
  `MarkdownReaderTest.php`, and `wordpress-latex-footnote-code-handoff.php`;
  `php lanes/pandoc/examples/wordpress-latex-footnote-code-handoff.php`
  emitted LaTeX footnote/Verbatim output plus WordPress endnote/code-block
  output; `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  passed 1 file, 3,610 assertions, 0 failures. No no-argument root harness
  was assigned or started.

## 2026-05-24 LaTeX Writer Heading-Image Slice

- Focused mapped count is now 1,836 Markdown/HTML/Native/WordPress checks.
- Added bounded `LatexWriter` coverage from upstream `Tests.Writers.LaTeX`
  `headers` / `containing image` behavior without invoking upstream Pandoc, a
  TeX/PDF engine, or image-processing tooling.
- The cloned static denominator remains 2,276 inspected upstream
  test/data/benchmark artifacts. This slice maps the direct upstream case
  where `header 1 (image "imgs/foo.jpg" "" (text "Alt text"))` renders as
  `\section{\texorpdfstring{\protect\pandocbounded{\includegraphics[keepaspectratio,alt={Alt text}]{imgs/foo.jpg}}}{Alt text}}`.
- `LatexWriter` now detects image-bearing headings, protects nested
  `\pandocbounded{\includegraphics[...]}` output for LaTeX moving arguments,
  and supplies the heading's plain alt text as the PDF-string fallback. This
  does not activate broader TeX/PDF, image-processing, syntax-highlighting,
  ZIP/package, or document-template support-library gates.
- Added `examples/wordpress-latex-heading-image-handoff.php` for a WordPress
  review packet that emits both LaTeX reviewer heading output and WordPress
  heading-block handoff markup for the same source hero image.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:test/Tests/Writers/LaTeX.hs | sed -n '1,340p'` was inspected for the
  bounded headers group; `php -l` passed for `LatexWriter.php`,
  `MarkdownReaderTest.php`, and `wordpress-latex-heading-image-handoff.php`;
  `php lanes/pandoc/examples/wordpress-latex-heading-image-handoff.php`
  emitted LaTeX heading output plus WordPress heading block output; `php
  tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1
  file, 3,604 assertions, 0 failures. No no-argument root harness was assigned
  or started.

## 2026-05-24 LaTeX Writer Figure-Placement Slice

- Focused mapped count is now 1,832 Markdown/HTML/Native/WordPress checks.
- Added bounded `LatexWriter` coverage from upstream `Tests.Writers.LaTeX`
  figure placement behavior without invoking upstream Pandoc, a TeX/PDF
  engine, or image-processing tooling.
- The cloned static denominator remains 2,276 inspected upstream
  test/data/benchmark artifacts. This slice maps the direct upstream
  `figures`/`placement` case: a figure with `latex-placement="htbp"` renders
  as a LaTeX `figure` environment, emits `\centering`, renders the first
  nested image as
  `\pandocbounded{\includegraphics[keepaspectratio,alt={alt text}]{img.jpg}}`,
  and preserves the caption as `\caption{caption}`.
- `LatexWriter` now renders bounded `figure` AST nodes, placement options,
  nested image alt text, and captions. This does not activate broader
  TeX/PDF, image-processing, syntax-highlighting, ZIP/package, or document
  template support-library gates.
- Added `examples/wordpress-latex-figure-handoff.php` for a WordPress media
  review packet that emits both LaTeX reviewer figure output and WordPress
  image block handoff markup for the same imported frame.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:test/Tests/Writers/LaTeX.hs | sed -n '1,340p'` was inspected for the
  bounded figures group; `php -l` passed for `LatexWriter.php`,
  `MarkdownReaderTest.php`, and `wordpress-latex-figure-handoff.php`; `php
  lanes/pandoc/examples/wordpress-latex-figure-handoff.php` emitted LaTeX
  figure output plus WordPress image block output; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 3,600 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 LaTeX Writer Definition-List Slice

- Focused mapped count is now 1,827 Markdown/HTML/Native/WordPress checks.
- Added bounded `LatexWriter` coverage from upstream `Tests.Writers.LaTeX`
  definition-list and heading-in-definition-list cases without invoking
  upstream Pandoc or any TeX/PDF engine.
- The cloned static denominator remains 2,276 inspected upstream
  test/data/benchmark artifacts. This slice maps two direct upstream cases:
  a definition-list term that is an internal link renders inside a LaTeX
  `description` item with `\tightlist` and `\hyperref`, and a definition body
  beginning with a level-2 header renders after the `\item[foo] ~ ` marker
  shape.
- `LatexWriter` now renders bounded `definition_list` AST nodes as
  `description` environments, emits `\tightlist` for plain-only definitions,
  and renders internal `link` nodes as LaTeX `\hyperref` targets. This does
  not activate a broader TeX/PDF, template, syntax-highlighting, or document
  class support-library gate.
- Added `examples/wordpress-latex-definition-list-handoff.php` for a
  WordPress review-definition packet that emits both LaTeX description output
  and WordPress definition-list HTML for linked source-review terms.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:test/Tests/Writers/LaTeX.hs | sed -n '1,260p'` was inspected for the
  bounded definition-list and header groups; `php -l` passed for
  `LatexWriter.php`, `MarkdownReaderTest.php`, and
  `wordpress-latex-definition-list-handoff.php`; `php
  lanes/pandoc/examples/wordpress-latex-definition-list-handoff.php` emitted
  LaTeX reviewer description output plus WordPress definition-list blocks;
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1
  file, 3,595 assertions, 0 failures. No no-argument root harness was assigned
  or started.

## 2026-05-24 LaTeX Writer Heading Slice

- Focused mapped count is now 1,819 Markdown/HTML/Native/WordPress checks.
- Added bounded `LatexWriter` coverage from upstream `Tests.Writers.LaTeX`
  heading groups without invoking upstream Pandoc or any TeX/PDF engine.
- The cloned static denominator remains 2,276 inspected upstream
  test/data/benchmark artifacts. This slice maps two direct upstream cases:
  the writer-options default top-level output where level 1/2/3 headings
  render as `\section`, `\subsection`, and `\subsubsection`, and the
  `headers` group list-item case where a level-2 heading renders after
  `\item ~`.
- `LatexWriter` now renders bounded heading AST nodes and preserves the
  special list-item heading marker shape used by upstream Pandoc. This does
  not activate a broader TeX/PDF, template, syntax-highlighting, or document
  class support-library gate.
- Added `examples/wordpress-latex-heading-handoff.php` for a WordPress review
  outline packet that emits both LaTeX section commands and WordPress heading
  blocks for the same migration-review outline.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:test/Tests/Writers/LaTeX.hs | sed -n '55,160p'` was inspected for the
  bounded header and writer-options groups; `php -l` passed for
  `LatexWriter.php`, `MarkdownReaderTest.php`, and
  `wordpress-latex-heading-handoff.php`; `php
  lanes/pandoc/examples/wordpress-latex-heading-handoff.php` emitted LaTeX
  reviewer section commands plus WordPress heading blocks; `php
  tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1
  file, 3,587 assertions, 0 failures. No no-argument root harness was
  assigned or started.

## 2026-05-24 LaTeX Writer Inline Code Escape Slice

- Focused mapped count is now 1,813 Markdown/HTML/Native/WordPress checks.
- Added bounded `LatexWriter` coverage from upstream `Tests.Writers.LaTeX`
  inline code groups without invoking upstream Pandoc or any TeX/PDF engine.
- The cloned static denominator remains 2,276 inspected upstream
  test/data/benchmark artifacts. This slice maps two direct upstream cases:
  code text `dog's` renders as `\texttt{dog\textquotesingle{}s}`, and code
  text `` `nu?` `` renders as
  `\texttt{\textasciigrave{}nu?\textasciigrave{}}`.
- `LatexWriter` now renders bounded `code` AST nodes as `\texttt{...}` and
  escapes apostrophes/backticks inside that code literal surface. This is a
  narrow writer slice and does not activate a broader TeX, PDF, syntax
  highlighting, or listings support-library gate.
- Added `examples/wordpress-latex-code-handoff.php` for a WordPress review
  packet that emits both LaTeX reviewer export text and WordPress inline code
  spans for source command/code handoff.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:test/Tests/Writers/LaTeX.hs | sed -n '70,105p'` was inspected for the
  bounded inline code group; `php -l` passed for `LatexWriter.php`,
  `MarkdownReaderTest.php`, and `wordpress-latex-code-handoff.php`; `php
  lanes/pandoc/examples/wordpress-latex-code-handoff.php` emitted escaped
  LaTeX reviewer code plus WordPress inline code spans; `php
  tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1
  file, 3,581 assertions, 0 failures. No no-argument root harness was
  assigned or started.

## 2026-05-24 LaTeX Writer Math Pipe Slice

- Focused mapped count is now 1,807 Markdown/HTML/Native/WordPress checks.
- Added bounded `LatexWriter` coverage from upstream `Tests.Writers.LaTeX`
  math group without invoking upstream Pandoc or any TeX/PDF engine.
- The cloned static denominator remains 2,276 inspected upstream
  test/data/benchmark artifacts. This slice maps the direct upstream
  `math`/`escape |` case: an inline math node containing
  `\sigma|_{\{x\}}` renders as `\(\sigma|_{\{x\}}\)`, preserving the
  pipe operator inside math rather than treating it as escapable prose.
- `LatexWriter` now renders inline and display `math` AST nodes as
  `\(...\)` and `\[...\]` in paragraph/list contexts. This is a bounded
  writer surface and does not activate a broader TeX, PDF, or math-rendering
  support-library gate.
- Added `examples/wordpress-latex-math-handoff.php` for a WordPress review
  packet that emits both LaTeX reviewer export text and WordPress block math
  handoff markup for inline and display equations.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:test/Tests/Writers/LaTeX.hs | sed -n '1,120p'` was inspected for the
  bounded math group; `php -l` passed for `LatexWriter.php`,
  `MarkdownReaderTest.php`, and `wordpress-latex-math-handoff.php`; `php
  lanes/pandoc/examples/wordpress-latex-math-handoff.php` emitted inline and
  display LaTeX math plus WordPress block math spans; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 3,575 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 HTML Writer Section-Div Footnotes Slice

- Focused mapped count is now 1,803 Markdown/HTML/Native/WordPress checks.
- Added bounded `HtmlWriter` coverage from upstream `Tests.Writers.HTML`
  footnote groups for the remaining `EndOfSection` plus `writerSectionDivs`
  case.
- The cloned static denominator remains 2,276 inspected upstream
  test/data/benchmark artifacts. This slice maps the fourth direct upstream
  writer footnote case without invoking upstream Pandoc: `Page title` is
  wrapped in a level1 section div, `First section` and `Second section` are
  wrapped in level2 section divs, and the first section's footnotes are emitted
  before the note-bearing level2 section closes.
- `HtmlWriter` now accepts `writerSectionDivs`/`sectionDivs` for a bounded
  heading-derived section tree, preserves the previously mapped
  `EndOfDocument`, `EndOfBlock`, and non-section `EndOfSection` footnote
  placements, and keeps end-of-section note blocks inside their section.
- Added `examples/wordpress-html-writer-section-div-footnotes-handoff.php` for
  a WordPress review HTML packet where source-note footnotes stay attached to
  the source-notes section before the publish checklist section starts.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:test/Tests/Writers/HTML.hs | sed -n '1,260p'` was inspected for the
  bounded footnote group; `git -C .upstream-cache/pandoc show
  HEAD:src/Text/Pandoc/Shared.hs | sed -n '520,610p'` was inspected for
  `makeSections`; `php -l` passed for `HtmlWriter.php`,
  `MarkdownReaderTest.php`, and
  `examples/wordpress-html-writer-section-div-footnotes-handoff.php`; `php
  lanes/pandoc/examples/wordpress-html-writer-section-div-footnotes-handoff.php`
  emitted section-wrapped reviewer footnotes with backlinks; `php
  tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1
  file, 3,570 assertions, 0 failures. No no-argument root harness was assigned
  or started.

## 2026-05-24 HTML Writer Footnote-Placement Slice

- Focused mapped count is now 1,798 Markdown/HTML/Native/WordPress checks.
- Added bounded `HtmlWriter` coverage from upstream `Tests.Writers.HTML`
  footnote groups.
- The cloned static denominator remains 2,276 inspected upstream
  test/data/benchmark artifacts. This slice maps three of the four direct
  upstream writer footnote cases without invoking upstream Pandoc:
  `EndOfDocument`, `EndOfBlock`, and `EndOfSection`. The upstream section-divs
  case was left for a separate slice and is now mapped in the section above.
- `HtmlWriter` now renders inline note references as Pandoc-style
  `footnote-ref` anchors, gathers footnote bodies, renders backlinks, preserves
  note-body links, and emits blockquotes around note-bearing quoted sections.
- Added `examples/wordpress-html-writer-footnote-placement-handoff.php` for a
  WordPress review HTML packet where paragraph-scoped and quote-scoped reviewer
  notes are emitted at the end of each block with source edit links preserved.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:test/Tests/Writers/HTML.hs | sed -n '1,260p'` was inspected for the
  bounded footnote group; `php -l` passed for `HtmlWriter.php`,
  `MarkdownReaderTest.php`, and
  `examples/wordpress-html-writer-footnote-placement-handoff.php`; `php
  lanes/pandoc/examples/wordpress-html-writer-footnote-placement-handoff.php`
  emitted end-of-block reviewer footnotes with backlinks; `php
  tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1
  file, 3,565 assertions, 0 failures. No no-argument root harness was
  assigned or started.

## 2026-05-24 HTML Writer Highlighted-Code Slice

- Focused mapped count is now 1,786 Markdown/HTML/Native/WordPress checks.
- Added bounded `HtmlWriter` coverage from upstream `Tests.Writers.HTML`
  inline-code style groups.
- The cloned static denominator remains 2,276 inspected upstream
  test/data/benchmark artifacts. This slice maps four direct upstream writer
  cases without invoking upstream Pandoc: `haskell` code renders as
  `sourceCode haskell` with the `>>=` operator inside `span.op`,
  `nolanguage` code remains an unhighlighted classed `code`, sample+haskell
  code wraps the highlighted code in `samp`, and haskell+variable code wraps
  the highlighted code in `var`.
- `HtmlWriter` now implements a deliberately bounded native Haskell
  operator-token highlighting branch for the accepted upstream examples. It
  does not activate the broader Skylighting support-library gate.
- Added `examples/wordpress-html-writer-highlighted-code-handoff.php` for a
  WordPress review HTML packet where source transform diagnostics retain
  highlighted Haskell-style operator markup while preserving semantic
  `sample`/`variable` roles.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:test/Tests/Writers/HTML.hs | sed -n '1,140p'` was inspected for the
  bounded inline-code and style-code groups; `php -l` passed for
  `HtmlWriter.php`, `MarkdownReaderTest.php`, and
  `examples/wordpress-html-writer-highlighted-code-handoff.php`; `php
  lanes/pandoc/examples/wordpress-html-writer-highlighted-code-handoff.php`
  emitted reviewer `sourceCode haskell` HTML with `samp`/`var` wrappers; `php
  tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1
  file, 3,557 assertions, 0 failures. No no-argument root harness was
  assigned or started.

## 2026-05-24 HTML Writer Definition-List Slice

- Focused mapped count is now 1,778 Markdown/HTML/Native/WordPress checks.
- Added bounded `HtmlWriter` coverage from upstream `Tests.Writers.HTML`
  block groups.
- The cloned static denominator remains 2,276 inspected upstream
  test/data/benchmark artifacts. This slice maps the direct upstream writer
  case for a definition list with an empty `<dt>`: the blank term is preserved
  as `<dt></dt>`, and the paragraph definition remains inside `<dd>`.
- `HtmlWriter` now renders `definition_list` AST nodes as Pandoc-style
  multiline `<dl>`, `<dt>`, and `<dd>` HTML without inventing placeholder text
  for blank source terms.
- Added `examples/wordpress-html-writer-definition-list-handoff.php` for a
  WordPress review HTML packet where a source glossary/status list preserves a
  blank term for editorial audit without shelling out to Pandoc.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:test/Tests/Writers/HTML.hs | sed -n '50,75p'` was inspected for the
  bounded blocks group; `php -l` passed for `HtmlWriter.php`,
  `MarkdownReaderTest.php`, and
  `examples/wordpress-html-writer-definition-list-handoff.php`; `php
  lanes/pandoc/examples/wordpress-html-writer-definition-list-handoff.php`
  emitted reviewer `<dl>` HTML with an empty `<dt>`; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 3,550 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 HTML Writer Quote/Q-Tags Slice

- Focused mapped count is now 1,774 Markdown/HTML/Native/WordPress checks.
- Added bounded `HtmlWriter` coverage from upstream `Tests.Writers.HTML`
  quote groups.
- The cloned static denominator remains 2,276 inspected upstream
  test/data/benchmark artifacts. This slice maps two direct upstream writer
  cases without invoking upstream Pandoc: quote-with-cite default output
  renders smart quote punctuation around a cite-bearing `span`, while
  `htmlQTags` output lifts `cite` onto a `q` element.
- `HtmlWriter` now accepts an `htmlQTags` option for this accepted branch.
  Default output keeps the inner span, and `htmlQTags` output emits
  `<q cite="...">...</q>` when the quote contains only a cite-bearing span.
- Added `examples/wordpress-html-writer-quote-cite-handoff.php` for a
  WordPress review HTML packet where a source reviewer quote keeps its cite
  metadata on `q` without shelling out to Pandoc.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:test/Tests/Writers/HTML.hs | sed -n '75,90p'` was inspected for the
  bounded quote group; `php -l` passed for `HtmlWriter.php`,
  `MarkdownReaderTest.php`, and
  `examples/wordpress-html-writer-quote-cite-handoff.php`; `php
  lanes/pandoc/examples/wordpress-html-writer-quote-cite-handoff.php` emitted
  a `q cite` reviewer HTML fragment; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 3,545 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 HTML Writer Image/Heading Attribute Slice

- Focused mapped count is now 1,768 Markdown/HTML/Native/WordPress checks.
- Added bounded `HtmlWriter` coverage from upstream `Tests.Writers.HTML`
  image and block attribute groups.
- The cloned static denominator remains 2,276 inspected upstream
  test/data/benchmark artifacts. This slice maps two direct upstream writer
  cases without invoking upstream Pandoc: image alt text stringification for
  formatted inline labels (`my ` plus emphasized `image`) and heading
  attribute filtering where `invalid="1"` is dropped while `lang="en"` is
  preserved.
- `HtmlWriter` now renders accepted image inlines with escaped `src`, `title`,
  plain-stringified `alt`, and safe data/aria handoff attributes. Heading
  output filters unsupported attributes for this HTML writer branch while
  keeping allowed document-language metadata.
- Added `examples/wordpress-html-writer-image-attrs-handoff.php` for a
  WordPress media-review packet where a source image title/data marker and
  formatted alt label survive, while noisy legacy heading metadata is not
  emitted.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:test/Tests/Writers/HTML.hs | sed -n '55,78p'` was inspected for the
  bounded image and heading-disallowed-attributes groups; `php -l` passed for
  `HtmlWriter.php`, `MarkdownReaderTest.php`, and
  `examples/wordpress-html-writer-image-attrs-handoff.php`; `php
  lanes/pandoc/examples/wordpress-html-writer-image-attrs-handoff.php` emitted
  a lang-preserving heading plus media-review image with title/alt/data-source
  attributes; `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  passed 1 file, 3,541 assertions, 0 failures. No no-argument root harness was
  assigned or started.

## 2026-05-24 HTML Writer Code-Role Slice

- Focused mapped count is now 1,762 Markdown/HTML/Native/WordPress checks.
- Added bounded `HtmlWriter` coverage from upstream `Tests.Writers.HTML`
  simple code-role groups.
- The cloned static denominator remains 2,276 inspected upstream
  test/data/benchmark artifacts. This slice maps four direct upstream writer
  cases without invoking upstream Pandoc: escaped ordinary Code `@&`, ordinary
  Code text `Answer is 42`, sample-class Code rendering as `samp`, and
  variable-class Code rendering as `var`.
- `HtmlWriter` is intentionally narrow for this slice: it supports small HTML
  fragments for plain/paragraph/heading blocks and core inline nodes, while
  the accepted upstream behavior is limited to the code/sample/variable role
  boundary. Syntax highlighting and the broader HTML writer suite remain
  future slices.
- Added `examples/wordpress-html-writer-code-roles-handoff.php` for a
  WordPress reviewer-diagnostics packet where a block name remains `code`, a
  sample diagnostic renders as `samp`, and a post-field variable renders as
  `var`.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:test/Tests/Writers/HTML.hs | sed -n '50,95p'` was inspected for the
  bounded code/sample/variable groups; `php -l` passed for `HtmlWriter.php`,
  `MarkdownReaderTest.php`, and
  `examples/wordpress-html-writer-code-roles-handoff.php`; `php
  lanes/pandoc/examples/wordpress-html-writer-code-roles-handoff.php` emitted
  reviewer diagnostics with `code`, `samp`, and `var`; `php
  tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1
  file, 3,535 assertions, 0 failures. No no-argument root harness was assigned
  or started.

## 2026-05-24 HTML Pre/Code Attribute Reader Slice

- Focused mapped count is now 1,754 Markdown/HTML/Native/WordPress checks.
- Added bounded MarkdownReader/WordPress coverage from upstream
  `Tests.Readers.HTML` `code block` group.
- The cloned static denominator remains 2,276 inspected upstream
  test/data/benchmark artifacts. This slice maps two direct upstream HTML
  reader cases without invoking upstream Pandoc:
  `<pre><code id="a" class="python">\nprint('hi')\n</code></pre>` and
  `<pre id="c"><code id="d">print('hi mom!')\n</code></pre>`.
- MarkdownReader now preserves nested `code` id/classes/data attributes on
  `code_block` nodes, strips `language-` prefixes for code-block language
  classes, and applies Pandoc's `pre`-attribute precedence when the wrapper
  has its own attr tuple.
- Added `fixtures/upstream-html-pre-code-attributes.html` and
  `examples/wordpress-native-html-codeblock-attrs-handoff.php` for a WordPress
  import fixture whose source snippet id, language, and safe reviewer metadata
  survive on the WordPress code block, while a `pre` wrapper id beats a nested
  `code` id when upstream precedence applies.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:test/Tests/Readers/HTML.hs | sed -n '124,150p'` was inspected for the
  bounded `code block` group; `php -l` passed for `MarkdownReader.php`,
  `WordPressBlockWriter.php`, `MarkdownReaderTest.php`, and
  `examples/wordpress-native-html-codeblock-attrs-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-html-codeblock-attrs-handoff.php`
  emitted WordPress code blocks with `legacy-snippet`/`data-source` metadata
  and `pre-wrapper-wins` precedence metadata; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 3,528 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 HTML Lang Metadata Reader Slice

- Focused mapped count is now 1,748 Markdown/HTML/Native/WordPress checks.
- Added bounded MarkdownReader/WordPress coverage from upstream
  `Tests.Readers.HTML` `lang` group.
- The cloned static denominator remains 2,276 inspected upstream
  test/data/benchmark artifacts. This slice maps two direct upstream HTML
  reader cases without invoking upstream Pandoc: `<html lang="es">hola` and
  `<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="es"><head></head><body>hola</body></html>`.
- MarkdownReader now recognizes EOF-terminated `<html>` documents for this
  upstream shape, carries root `lang` or `xml:lang` into document metadata,
  and keeps the body text as parsed paragraph content instead of literal HTML
  source text.
- Added `fixtures/upstream-html-lang-metadata.html` and
  `examples/wordpress-native-html-lang-metadata-handoff.php` for a WordPress
  import fixture whose source language is visible in an opt-in metadata review
  block alongside the parsed body copy.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:test/Tests/Readers/HTML.hs | sed -n '1,220p'` was inspected for the
  bounded `lang` group; `php -l` passed for `MarkdownReader.php`,
  `MarkdownReaderTest.php`, and
  `examples/wordpress-native-html-lang-metadata-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-html-lang-metadata-handoff.php` emitted
  a metadata review block with `lang=es`; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 3,506 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 HTML Inline Code Alias Reader Slice

- Focused mapped count is now 1,742 Markdown/HTML/Native/WordPress checks.
- Added bounded MarkdownReader/WordPress coverage from upstream
  `Tests.Readers.HTML` `code`, `tt`, `samp`, and `var` groups.
- The cloned static denominator remains 2,276 inspected upstream
  test/data/benchmark artifacts. This slice maps four direct upstream HTML
  reader cases without invoking upstream Pandoc: `<code>Answer is 42</code>`,
  `<tt>Answer is 42</tt>`, `<samp>Answer is 42</samp>`, and
  `<var>result</var>`.
- MarkdownReader now recognizes top-level inline-code HTML fragments and
  emits paragraph `code` inlines rather than literal raw tag text. `tt`
  follows ordinary code semantics, while `samp` carries Pandoc's `sample`
  class and `var` carries Pandoc's `variable` class.
- Added `fixtures/upstream-html-inline-code-aliases.html` and
  `examples/wordpress-native-html-inline-code-handoff.php` for a WordPress
  import fixture whose inline block name, shortcode-like legacy `tt` text,
  sample diagnostic, and variable name remain visible as inline code.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:test/Tests/Readers/HTML.hs | sed -n '112,128p'` was inspected for the
  bounded code/tt/samp/var groups; `php -l` passed for `MarkdownReader.php`,
  `MarkdownReaderTest.php`, and
  `examples/wordpress-native-html-inline-code-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-html-inline-code-handoff.php` emitted
  inline code with `class="sample"` and `class="variable"` handoff markers;
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1
  file, 3,495 assertions, 0 failures. No no-argument root harness was assigned
  or started.

## 2026-05-24 HTML Native-Div Header Reader Slice

- Focused mapped count is now 1,734 Markdown/HTML/Native/WordPress checks.
- Added bounded MarkdownReader/WordPress coverage from upstream
  `Tests.Readers.HTML` native-div `header` group.
- The cloned static denominator remains 2,276 inspected upstream
  test/data/benchmark artifacts. This slice maps the upstream
  `<header id="title">Title</header>` case without invoking upstream Pandoc,
  plus WordPress handoff coverage for article headers nested inside extracted
  `<main>` content.
- MarkdownReader now has an opt-in `htmlNativeDivs` path that converts
  top-level `<header>` fragments and recursive HTML block headers into native
  `div` nodes with the Pandoc `header` class. Existing id/class/data metadata
  is preserved, and nested headers inside plain `<main>` content remain in the
  extracted document body.
- Added `fixtures/upstream-html-header-native-divs.html` and
  `examples/wordpress-native-html-header-handoff.php` for a WordPress import
  fixture whose main body keeps an attributed article header div while still
  using the previous native-div main extraction path.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:test/Tests/Readers/HTML.hs | sed -n '82,128p'` was inspected for the
  bounded main/header group; `php -l` passed for `MarkdownReader.php`,
  `MarkdownReaderTest.php`, and
  `examples/wordpress-native-html-header-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-html-header-handoff.php` emitted the
  expected WordPress handoff with nested `class="header"` article metadata;
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1
  file, 3,472 assertions, 0 failures. No no-argument root harness was assigned
  or started.

## 2026-05-24 HTML Native-Div Main Reader Slice

- Focused mapped count is now 1,729 Markdown/HTML/Native/WordPress checks.
- Added bounded MarkdownReader/WordPress coverage from upstream
  `Tests.Readers.HTML` native-div `main` group.
- The cloned static denominator remains 2,276 inspected upstream
  test/data/benchmark artifacts. This slice maps five upstream HTML reader
  cases without invoking upstream Pandoc: `<main>` content extraction,
  role-preserving main-to-div conversion, attributed main-to-div conversion
  with generated `role=main`, implicit `<p>` close before `<main>`, and
  trailing non-main text exclusion.
- MarkdownReader now has an opt-in `htmlNativeDivs` path that extracts the
  first `<main>` element from full HTML documents or HTML fragments. Plain
  `<main>` content is unwrapped into document blocks, while attributed main
  elements become native `div` nodes with preserved id/classes/data
  attributes. Surrounding header/nav/footer/text boilerplate is ignored for
  this focused upstream branch.
- Added `fixtures/upstream-html-main-native-divs.html` and
  `examples/wordpress-native-html-main-handoff.php` for a WordPress import
  fixture whose attributed main body survives with `role="main"` while export
  boilerplate is dropped.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:test/Tests/Readers/HTML.hs | sed -n '82,112p'` was inspected for the
  bounded main group; `php -l` passed for `MarkdownReader.php`,
  `MarkdownReaderTest.php`, and
  `examples/wordpress-native-html-main-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-html-main-handoff.php` emitted only
  the attributed main wrapper and body content; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 3,451 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 HTML Anchor/Image Attribute Reader Slice

- Focused mapped count is now 1,720 Markdown/HTML/Native/WordPress checks.
- Added bounded MarkdownReader/WordPress coverage from upstream
  `Tests.Readers.HTML` anchors and img groups.
- The cloned static denominator remains 2,276 inspected upstream
  test/data/benchmark artifacts. This slice maps three upstream HTML reader
  cases without invoking upstream Pandoc: `<a name="anchor"/>` without href,
  `<img data-external="1" ...>`, and `<img title="...">`.
- MarkdownReader now converts href-less HTML anchors with `name` or `id`
  attributes into Pandoc-style `span` anchors instead of empty links. Explicit
  `<a href="">` placeholders remain links, preserving existing empty-link
  import behavior.
- MarkdownReader now preserves safe image attributes such as `data-external`
  on HTML image nodes while keeping `src`, `alt`, and `title` in their
  dedicated image fields. WordPress output carries `data-external` through to
  the rendered `<img>` tag for reviewer handoff.
- Added `fixtures/upstream-html-anchor-image-attrs.html` and
  `examples/wordpress-native-html-anchor-image-attrs-handoff.php` for a
  WordPress import fixture with legacy section anchors, reviewer jump targets,
  and external image metadata.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:test/Tests/Readers/HTML.hs | sed -n '40,82p'` was inspected for the
  bounded anchor/img groups; `php -l` passed for `MarkdownReader.php`,
  `MarkdownReaderTest.php`, and
  `examples/wordpress-native-html-anchor-image-attrs-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-html-anchor-image-attrs-handoff.php`
  emitted span anchors and an image retaining `data-external`; `php
  tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1
  file, 3,427 assertions, 0 failures. No no-argument root harness was assigned
  or started.

## 2026-05-24 HTML Base Href Reader Slice

- Focused mapped count is now 1,714 Markdown/HTML/Native/WordPress checks.
- Added bounded MarkdownReader/WordPress coverage from upstream
  `Tests.Readers.HTML` base-tag cases.
- The cloned static denominator remains 2,276 inspected upstream
  test/data/benchmark artifacts. This slice maps four upstream base URL
  shapes without invoking upstream Pandoc: file-like base paths, directory
  base paths, root-relative image paths, and already-absolute image URLs.
- MarkdownReader now records the first `<base href>` while parsing a full HTML
  document and resolves HTML `<img src>` plus `<a href>` values against it.
  Existing no-base HTML reader imports keep their original source URLs.
- Added `fixtures/upstream-html-base-media.html` as an upstream-shaped
  WordPress import fixture. It proves relative exported media, relative audit
  links, and root-relative media become absolute WordPress URLs before
  WordPressBlockWriter output.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:test/Tests/Readers/HTML.hs` was inspected for the bounded base-tag
  group; `php -l` passed for `MarkdownReader.php`, `MarkdownReaderTest.php`,
  and `examples/wordpress-native-html-base-media-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-html-base-media-handoff.php` emitted
  absolute image/link URLs resolved from the HTML base; `php
  tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1
  file, 3,411 assertions, 0 failures. No no-argument root harness was assigned
  or started.

## 2026-05-24 Markdown Abbreviation Reader Slice

- Focused mapped count is now 1,707 Markdown/HTML/Native/WordPress checks.
- Added bounded MarkdownReader/NativeWriter/WordPress coverage from upstream
  command fixture `test/command/md-abbrevs.md` plus the upstream
  `data/abbreviations` list.
- The command subinventory remains 1,155 artifacts under `test/command`, and
  the data subinventory remains 247 artifacts under `data/`. This slice maps
  the md-abbrevs command output without invoking upstream Pandoc.
- The copied fixture records Pandoc's native output for `Mr. Bob` as
  `Str "Mr.\160Bob"` and the escaped-period case `Hi Mr\. Bob` as ordinary
  `Space` constructors.
- MarkdownReader now recognizes known abbreviations from upstream
  `data/abbreviations` when the period is unescaped and followed by a letter,
  binding the following space as `U+00A0`. Escaped periods remain ordinary text
  plus `Space`, and digit-following initials such as `M.A. 2007` stay ordinary
  spacing to preserve existing upstream reader fixtures.
- WordPress output preserves the nonbreaking abbreviation groups in paragraph
  text so migration review packets do not split titles such as `Mr. Bob`,
  `Dr. Rivera`, or `e.g. examples` during import review.
- Focused evidence: `diff -u <(git -C .upstream-cache/pandoc show
  HEAD:test/command/md-abbrevs.md)
  lanes/pandoc/fixtures/upstream-command-md-abbrevs.md` produced no output;
  `git -C .upstream-cache/pandoc show HEAD:data/abbreviations` was inspected
  for the bounded abbreviation list; `php -l` passed for `MarkdownReader.php`,
  `MarkdownReaderTest.php`, and
  `examples/wordpress-markdown-abbrev-handoff.php`; `php
  lanes/pandoc/examples/wordpress-markdown-abbrev-handoff.php` emitted the
  expected WordPress abbreviation handoff; the first focused test run exposed
  an over-broad digit-following `M.A. 2007` conversion, which was corrected;
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` then
  passed 1 file, 3,401 assertions, 0 failures. No no-argument root harness was
  assigned or started.

## 2026-05-24 Markdown Raw Attribute Reader Slice

- Focused mapped count is now 1,698 Markdown/HTML/Native/WordPress checks.
- Added bounded MarkdownReader/MarkdownWriter/NativeWriter/WordPress coverage
  from upstream command fixture `test/command/parse-raw.md`.
- The command subinventory remains 1,155 artifacts under `test/command`, and
  this slice maps the raw-enabled Markdown output examples without invoking
  upstream Pandoc.
- The copied fixture records Pandoc's raw TeX/HTML command outputs:
  ``*Hi `\foo{there}`{=latex}*`` for `latex+raw_tex`, and
  ``*Hi `<blink>`{=html}there`</blink>`{=html}*`` for `html+raw_html`.
- MarkdownReader now consumes code-span raw attributes before ordinary code
  attributes, preserving `{=latex}` as `RawInline (Format "latex")` and
  `{=html}` as raw HTML inline nodes instead of leaving `{=format}` as literal
  paragraph text. It also parses fenced raw-attribute blocks into raw block
  nodes for WordPress handoff tests.
- WordPress output preserves raw HTML literally, while latex/opml-style raw
  payloads remain visible as `data-pandoc-raw-format` reviewer spans or code
  blocks. Ordinary code attributes such as `{.javascript}` remain code nodes.
- Focused evidence: `diff -u <(git -C .upstream-cache/pandoc show
  HEAD:test/command/parse-raw.md)
  lanes/pandoc/fixtures/upstream-command-parse-raw.md` produced no output;
  `php -l` passed for `MarkdownReader.php`, `MarkdownReaderTest.php`, and
  `examples/wordpress-markdown-raw-attribute-handoff.php`; `php
  lanes/pandoc/examples/wordpress-markdown-raw-attribute-handoff.php` emitted
  the expected WordPress raw-attribute reviewer handoff; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 3,390 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 Native DOCX Table GridBefore Slice

- Focused mapped count is now 1,676 Markdown/HTML/Native/WordPress checks.
- Added bounded NativeReader/NativeWriter/WordPress coverage from a compact
  upstream-derived slice of `test/docx/table_gridbefore.native`.
- The targeted DOCX subinventory remains 233 artifacts under `test/docx`,
  including 112 `.native` expectations, 121 `.docx` inputs/goldens, and 38
  golden `.docx` outputs. This maps the Native table packet without activating
  DOCX ZIP/OpenXML package parsing.
- The copied slice preserves the DOCX gridBefore/gridAfter shape as explicit
  empty cells, with eleven scientific `ColWidth` values, a header cell spanning
  eight bit columns, spacer rows, a body cell with `ColSpan 2`, and a reserved
  text cell with `ColSpan 10`.
- NativeReader preserves the scientific widths, empty cells, and colspans;
  NativeWriter read-back keeps `ColWidth` and `ColSpan` markers; default
  WordPressBlockWriter preserves blank table cells without extra attributes,
  while opt-in `markEmptyTableCells` emits `data-pandoc-empty-cell="true"` on
  nineteen blank source cells for reviewer handoffs.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:test/docx/table_gridbefore.native` was inspected for the bounded
  fixture slice; `php -l` passed for `WordPressBlockWriter.php`,
  `MarkdownReaderTest.php`, and
  `examples/wordpress-native-docx-table-gridbefore-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-docx-table-gridbefore-handoff.php`
  emitted the expected WordPress table with empty-cell reviewer markers; `php
  tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1
  file, 3,360 assertions, 0 failures. No no-argument root harness was assigned
  or started.

## 2026-05-24 Empty Paragraph Command Slice

- Focused mapped count is now 1,651 Markdown/HTML/Native/WordPress checks.
- Added bounded NativeReader/NativeWriter/WordPress coverage from upstream
  command fixture `test/command/empty_paragraphs.md`.
- The command subinventory remains 1,155 artifacts under `test/command`, and
  this slice maps the native-to-HTML empty-paragraph policy without invoking
  upstream Pandoc.
- The copied command fixture records the default `html5` behavior dropping two
  empty `Para []` blocks and the `html5+empty_paragraphs` behavior preserving
  them as two empty `<p></p>` elements.
- NativeReader preserves the two empty paragraph packets; NativeWriter
  read-back keeps `Para [  ]`; WordPressBlockWriter keeps the existing default
  drop behavior and adds opt-in `preserveEmptyParagraphs` output for import
  review packets that need source blank paragraph evidence.
- Focused evidence: `diff -u <(git -C .upstream-cache/pandoc show
  HEAD:test/command/empty_paragraphs.md)
  lanes/pandoc/fixtures/upstream-command-empty-paragraphs.md` produced no
  output; `php -l` passed for `WordPressBlockWriter.php`,
  `MarkdownReaderTest.php`, and
  `examples/wordpress-native-empty-paragraphs-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-empty-paragraphs-handoff.php` emitted
  four WordPress paragraph blocks including two empty `<p></p>` blocks; `php
  tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1
  file, 3,335 assertions, 0 failures. The focused file now contains 330
  behavior tests. `jq empty` passed for the lane manifest and lane status, and
  `git diff --check -- lanes/pandoc` passed. No no-argument root harness was
  assigned or started.

## 2026-05-24 Native ODT Multi-Header Table Slice

- Focused mapped count is now 1,643 Markdown/HTML/Native/WordPress checks.
- Added bounded NativeReader/NativeWriter/WordPress coverage from upstream ODT
  Native fixture `test/odt/native/simpleTableWithMultipleHeaderRows.native`.
- The targeted ODT subinventory remains 99 artifacts under `test/odt`,
  including 29 `.native` expectations, 18 Markdown fixtures, and 52 `.odt`
  inputs. This maps another ODT Native table shape without activating
  OpenDocument ZIP/XML package parsing.
- The copied fixture contributes one table plus a trailing empty `Para []`.
  The table has three `ColWidthDefault` columns, two header rows (`A/B/C` and
  `I/II/II`), three body rows, and six empty body cells.
- NativeReader preserves the two `TableHead` rows and default-width cells;
  NativeWriter read-back keeps `ColWidthDefault`; WordPressBlockWriter emits a
  two-row `<thead>`, a three-row `<tbody>`, no invented `<colgroup>`, and no
  empty paragraph block for the trailing `Para []`.
- Focused evidence: `diff -u <(git -C .upstream-cache/pandoc show
  HEAD:test/odt/native/simpleTableWithMultipleHeaderRows.native)
  lanes/pandoc/fixtures/upstream-native-odt-simple-table-multiple-header-rows.native`
  produced no output; `php -l` passed for `MarkdownReaderTest.php` and
  `examples/wordpress-native-odt-multi-header-table-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-odt-multi-header-table-handoff.php`
  emitted the expected two-header-row WordPress table; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 3,321 assertions,
  0 failures. The focused file now contains 329 behavior tests. No
  no-argument root harness was assigned or started.

## 2026-05-24 Native DOCX Insertion/Deletion Decision Slice

- Focused mapped count is now 1,628 Markdown/HTML/Native/WordPress checks.
- Normalized `benchmarkDenominator.total` to numeric `2276` while retaining
  the prose cloned-inventory summary in `totalDescription`.
- Added bounded NativeReader/NativeWriter/WordPress coverage from upstream
  DOCX Native fixtures `test/docx/track_changes_insertion_accept.native`,
  `test/docx/track_changes_insertion_reject.native`,
  `test/docx/track_changes_deletion_accept.native`, and
  `test/docx/track_changes_deletion_reject.native`.
- The targeted DOCX subinventory remains 233 artifacts under `test/docx`,
  including 112 `.native` expectations, 121 `.docx` inputs/goldens, and 38
  golden `.docx` outputs. This maps applied accept/reject decisions without
  activating DOCX ZIP/OpenXML package parsing.
- The four copied fixtures contribute one plain paragraph each: accepted
  insertion keeps `two exciting`, rejected insertion omits those words,
  accepted deletion omits `an excessively modified`, and rejected deletion
  retains that deleted text.
- NativeReader preserves the four applied paragraph texts; NativeWriter
  read-back emits plain `Para` packets with no residual `Span` constructors;
  WordPressBlockWriter emits plain paragraph blocks with no residual
  `<ins>`/`<del>` review markup.
- Focused evidence: `php -l` passed for `MarkdownReaderTest.php` and
  `examples/wordpress-native-docx-track-changes-decision-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-docx-track-changes-decision-handoff.php`
  emitted the four expected plain WordPress paragraphs; `php
  tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1
  file, 3,306 assertions, 0 failures. The focused file now contains 328
  behavior tests. No no-argument root harness was assigned or started.

## 2026-05-24 Markdown Nested Spanlike Slice

- Focused mapped count is now 1,604 Markdown/HTML/Native/WordPress checks.
- Added bounded MarkdownReader/WordPress coverage from upstream command
  fixture `test/command/nested-spanlike.md`.
- The command subinventory remains 1,155 artifacts under `test/command`, and
  this slice maps the single nested-spanlike command block without invoking
  upstream Pandoc.
- The upstream expected HTML shape is
  `<kbd id="bar"><u><span class="smallcaps">test</span></u></kbd>` for source
  Markdown `[test]{.foo .underline #bar .smallcaps .kbd}`.
- MarkdownReader preserves the source bracketed span id/classes; WordPress
  output consumes the spanlike marker classes (`kbd`, `underline`, and
  `smallcaps`), attaches the id to the outer wrapper, and does not leak the
  pre-spanlike `.foo` class into the rendered wrapper.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:test/command/nested-spanlike.md` matched the expected HTML wrapper;
  `php -l` passed for `WordPressBlockWriter.php`, `MarkdownReaderTest.php`,
  and `examples/wordpress-markdown-spanlike-handoff.php`; `php
  lanes/pandoc/examples/wordpress-markdown-spanlike-handoff.php` emitted the
  expected WordPress paragraph wrapper; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 3,274 assertions,
  0 failures. The focused file now contains 327 behavior tests. No
  no-argument root harness was assigned or started.

## 2026-05-24 Native DOCX Paragraph Split Decision Slice

- Focused mapped count is now 1,598 Markdown/HTML/Native/WordPress checks.
- Added bounded NativeReader/NativeWriter/WordPress coverage from upstream
  DOCX Native fixtures `test/docx/paragraph_insertion_deletion_accept.native`
  and `test/docx/paragraph_insertion_deletion_reject.native`.
- The targeted DOCX subinventory remains 233 artifacts under `test/docx`,
  including 112 `.native` expectations, 121 `.docx` inputs/goldens, and 38
  golden `.docx` outputs. This maps accepted/rejected paragraph split review
  decisions without activating DOCX ZIP/OpenXML package parsing.
- The copied accepted fixture contributes two plain paragraphs in accepted
  split order: `This is a` then `split Paragraph.`. The copied rejected fixture
  contributes two plain paragraphs in rejected order: `This is a split` then
  `Paragraph.`.
- NativeReader preserves both paragraph orders; NativeWriter read-back emits
  plain `Para` packets with no residual paragraph-change `Span` nodes;
  WordPressBlockWriter emits plain paragraph blocks with no residual
  `data-pandoc-paragraph-change` metadata.
- Focused evidence: `diff -u <(git -C .upstream-cache/pandoc show
  HEAD:test/docx/paragraph_insertion_deletion_accept.native)
  lanes/pandoc/fixtures/upstream-native-docx-paragraph-insertion-deletion-accept.native`
  and the equivalent reject-fixture diff both produced no output; `php -l`
  passed for `MarkdownReaderTest.php` and
  `examples/wordpress-native-docx-paragraph-change-decision-handoff.php`;
  `php lanes/pandoc/examples/wordpress-native-docx-paragraph-change-decision-handoff.php`
  emitted accepted and rejected WordPress sections in the expected paragraph
  order; `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  passed 1 file, 3,268 assertions, 0 failures. The focused file now contains
  326 behavior tests. No no-argument root harness was assigned or started.

## 2026-05-24 Native DOCX Moved-Text Decision Slice

- Focused mapped count is now 1,584 Markdown/HTML/Native/WordPress checks.
- Added bounded NativeReader/NativeWriter/WordPress coverage from upstream
  DOCX Native fixtures `test/docx/track_changes_move_accept.native` and
  `test/docx/track_changes_move_reject.native`.
- The targeted DOCX subinventory remains 233 artifacts under `test/docx`,
  including 112 `.native` expectations, 121 `.docx` inputs/goldens, and 38
  golden `.docx` outputs. This maps accepted/rejected review decisions without
  activating DOCX ZIP/OpenXML package parsing.
- The copied accepted fixture contributes three plain paragraphs in context,
  moved-text, later-context order. The copied rejected fixture contributes the
  same three plain paragraphs in context, later-context, moved-text order.
- NativeReader preserves both paragraph orders; NativeWriter read-back emits
  plain `Para` packets with no residual review `Span` nodes; WordPressBlockWriter
  emits plain paragraph blocks with no residual `<ins>` or `<del>` review markup.
- Focused evidence: `diff -u <(git -C .upstream-cache/pandoc show
  HEAD:test/docx/track_changes_move_accept.native)
  lanes/pandoc/fixtures/upstream-native-docx-track-changes-move-accept.native`
  and the equivalent reject-fixture diff both produced no output; `php -l`
  passed for `MarkdownReaderTest.php` and
  `examples/wordpress-native-docx-track-changes-move-decision-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-docx-track-changes-move-decision-handoff.php`
  emitted accepted and rejected WordPress sections in the expected paragraph
  order; `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  passed 1 file, 3,254 assertions, 0 failures. The focused file now contains
  325 behavior tests. No no-argument root harness was assigned or started.

## 2026-05-24 Native DOCX Overlapping Targets Slice

- Focused mapped count is now 1,570 Markdown/HTML/Native/WordPress checks.
- Added bounded NativeReader/NativeWriter/WordPress coverage from upstream
  DOCX Native fixture `test/docx/overlapping_targets.native`.
- The targeted DOCX subinventory remains 233 artifacts under `test/docx`,
  including 112 `.native` expectations, 121 `.docx` inputs/goldens, and 38
  golden `.docx` outputs. This maps a cross-reference anchor packet without
  activating DOCX ZIP/OpenXML package parsing.
- The copied fixture contributes three paragraphs: two links targeting `#Fizz`
  and one empty `Span ("Fizz",["anchor"],[]) []` target before the linked
  paragraph text.
- NativeReader preserves the same-fragment links and empty anchor span;
  NativeWriter round-trips the `Span` anchor packet; WordPressBlockWriter now
  marks empty anchor spans with `data-pandoc-anchor="empty-target"` so DOCX
  cross-reference targets remain visible to migration reviewers. The same
  marker also applies to existing upstream DOCX table-caption anchors.
- Focused evidence: `diff -u <(git -C .upstream-cache/pandoc show
  HEAD:test/docx/overlapping_targets.native)
  lanes/pandoc/fixtures/upstream-native-docx-overlapping-targets.native`
  confirmed the copied fixture matches upstream; `php -l` passed for
  `WordPressBlockWriter.php`, `MarkdownReaderTest.php`, and
  `examples/wordpress-native-docx-overlapping-targets-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-docx-overlapping-targets-handoff.php`
  emitted two `#Fizz` links and an empty anchor target with
  `data-pandoc-anchor="empty-target"`; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 3,240 assertions,
  0 failures. The focused file now contains 324 behavior tests. No
  no-argument root harness was assigned or started.

## 2026-05-24 Native DOCX Scrubbed Metadata Slice

- Focused mapped count is now 1,555 Markdown/HTML/Native/WordPress checks.
- Added bounded NativeReader/NativeWriter/WordPress coverage from upstream
  DOCX Native fixture `test/docx/track_changes_scrubbed_metadata.native`.
- The targeted DOCX subinventory remains 233 artifacts under `test/docx`,
  including 112 `.native` expectations, 121 `.docx` inputs/goldens, and 38
  golden `.docx` outputs. This maps scrubbed review metadata without
  activating DOCX ZIP/OpenXML package parsing.
- The copied fixture contributes one paragraph with four review spans:
  deletion, insertion, comment-start, and comment-end. The deletion,
  insertion, and comment-start spans preserve author metadata while omitting
  date metadata.
- WordPressBlockWriter now marks author-only review metadata as missing-date
  handoff data (`data-pandoc-change-date-status="missing"` or
  `data-pandoc-comment-date-status="missing"`) instead of inventing a
  `datetime` value or leaking raw upstream `author`/`date` attributes.
- Focused evidence: `diff -u <(git -C .upstream-cache/pandoc show
  HEAD:test/docx/track_changes_scrubbed_metadata.native)
  lanes/pandoc/fixtures/upstream-native-docx-track-changes-scrubbed-metadata.native`
  confirmed the copied fixture matches upstream; `php -l` passed for
  `WordPressBlockWriter.php`, `MarkdownReaderTest.php`, and
  `examples/wordpress-native-docx-scrubbed-metadata-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-docx-scrubbed-metadata-handoff.php`
  emitted WordPress review spans with explicit missing-date status; `php
  tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1
  file, 3,225 assertions, 0 failures. The focused file now contains 323
  behavior tests. No no-argument root harness was assigned or started.

## 2026-05-24 Native DOCX Move-Tracking Slice

- Focused mapped count is now 1,541 Markdown/HTML/Native/WordPress checks.
- Added bounded NativeReader/NativeWriter/WordPress coverage from upstream
  DOCX Native fixture `test/docx/track_changes_move_all.native`.
- The targeted DOCX subinventory remains 233 artifacts under `test/docx`,
  including 112 `.native` expectations, 121 `.docx` inputs/goldens, and 38
  golden `.docx` outputs. This maps the moved-text all-changes review shape
  without activating DOCX ZIP/OpenXML package parsing.
- The copied fixture contributes four paragraph packets: normal context before
  and after the moved text, plus paired `Span` nodes carrying `insertion` and
  `deletion` classes with the same `author` and `date` metadata.
- NativeReader preserves the paired moved-text spans; NativeWriter round-trips
  the shared metadata; WordPressBlockWriter renders the handoff as `<ins>` and
  `<del>` review spans with `data-pandoc-change-*` metadata while avoiding raw
  upstream `author` or `date` attributes.
- Focused evidence: `diff -u <(git -C .upstream-cache/pandoc show
  HEAD:test/docx/track_changes_move_all.native)
  lanes/pandoc/fixtures/upstream-native-docx-track-changes-move-all.native`
  confirmed the copied fixture matches upstream; `php -l` passed for
  `MarkdownReaderTest.php` and
  `examples/wordpress-native-docx-track-changes-move-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-docx-track-changes-move-handoff.php`
  emitted WordPress paragraphs with `<ins>`/`<del>` review spans; `php
  tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file,
  3,207 assertions, 0 failures. The focused file now contains 322 behavior
  tests. No no-argument root harness was assigned or started.

## 2026-05-24 Native DOCX Image Textbox Caption Slice

- Focused mapped count is now 1,525 Markdown/HTML/Native/WordPress checks.
- Added bounded NativeReader/NativeWriter/WordPress coverage from upstream
  DOCX Native fixture `test/docx/image_with_textbox_caption.native`.
- The targeted DOCX subinventory remains 233 artifacts under `test/docx`,
  including 112 `.native` expectations, 121 `.docx` inputs/goldens, and 38
  golden `.docx` outputs. This strengthens DOCX Native fixture mapping without
  activating DOCX ZIP/OpenXML package parsing.
- The copied fixture contributes a `Figure` whose caption comes from a DOCX
  image textbox, plus an empty-alt EMF `Image` with source width/height
  attributes and target `media/image1.emf`.
- WordPressBlockWriter now uses a Figure caption as a reviewer-safe image alt
  fallback only when the source Image has empty alt text and no non-image
  figure-body alt fallback. The rendered image is marked with
  `data-pandoc-alt-source="figure-caption"` while keeping the figcaption,
  source dimensions, `data-pandoc-source-format="emf"`, and NativeWriter
  read-back shape intact.
- Focused evidence: `diff -u <(git -C .upstream-cache/pandoc show
  HEAD:test/docx/image_with_textbox_caption.native)
  lanes/pandoc/fixtures/upstream-native-docx-image-textbox-caption.native`
  confirmed the copied fixture matches upstream; `php -l` passed for
  `WordPressBlockWriter.php`, `MarkdownReaderTest.php`, and
  `examples/wordpress-native-docx-image-textbox-caption-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-docx-image-textbox-caption-handoff.php`
  emitted a WordPress image block with `data-pandoc-alt-source="figure-caption"`
  and `data-pandoc-source-format="emf"`; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 3,191 assertions,
  0 failures. The focused file now contains 321 behavior tests. No
  no-argument root harness was assigned or started.

## 2026-05-24 Native DOCX Diagram Placeholder Slice

- Focused mapped count is now 1,512 Markdown/HTML/Native/WordPress checks.
- Added bounded NativeReader/NativeWriter/WordPress coverage from upstream
  DOCX Native fixture `test/docx/diagram.native`.
- The targeted DOCX subinventory remains 233 artifacts under `test/docx`,
  including 112 `.native` expectations, 121 `.docx` inputs/goldens, and 38
  golden `.docx` outputs. This strengthens DOCX Native fixture mapping without
  activating DOCX ZIP/OpenXML package parsing.
- The copied fixture contributes a level-1 heading with id `diagram-after` and
  a paragraph containing `Span ("",["diagram"],[]) [Str "[DIAGRAM]"]`, matching
  Pandoc's unsupported DOCX diagram placeholder handoff.
- WordPressBlockWriter now renders upstream `diagram` spans as explicit review
  placeholders with `data-pandoc-diagram="unsupported-docx-diagram"` while
  preserving the visible `[DIAGRAM]` marker and NativeWriter round-trip shape.
- Focused evidence: `diff -u <(git -C .upstream-cache/pandoc show
  HEAD:test/docx/diagram.native)
  lanes/pandoc/fixtures/upstream-native-docx-diagram.native` confirmed the
  copied fixture matches upstream; `php -l` passed for
  `WordPressBlockWriter.php`, `MarkdownReaderTest.php`, and
  `examples/wordpress-native-docx-diagram-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-docx-diagram-handoff.php` emitted a
  WordPress paragraph containing
  `data-pandoc-diagram="unsupported-docx-diagram"`; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 3,178 assertions,
  0 failures. The focused file now contains 320 behavior tests. No
  no-argument root harness was assigned or started.

## 2026-05-24 Native JATS Figure Alt-Text Slice

- Focused mapped count is now 1,503 Markdown/HTML/Native/WordPress checks.
- Added bounded NativeReader/NativeWriter/WordPress coverage from upstream
  Native fixture region `test/jats-reader.native` lines 853-858.
- The targeted static denominator remains 2,276 inspected upstream
  test/data/benchmark files and artifacts. This slice reuses the existing
  cloned static inventory and does not activate a JATS/XML parser dependency.
- The copied fixture contributes a `Figure` with id `fig-1`, caption `bar`, a
  source body `Plain [ Str "alternative-decription" ]` packet, and an Image
  nested under a paragraph with target `foo.png` and empty alt text.
- WordPressBlockWriter now finds Image nodes nested inside Figure child blocks
  and uses non-image figure body text as an alt fallback when the Image packet
  itself has no alt text. This prevents reviewer handoffs from emitting an
  empty placeholder `src=""` image while keeping the source caption visible as
  a figcaption.
- Focused evidence: `git -C .upstream-cache/pandoc show
  HEAD:test/jats-reader.native | sed -n '853,858p'` matched the copied fixture
  region; `php -l` passed for `WordPressBlockWriter.php`,
  `MarkdownReaderTest.php`, and
  `examples/wordpress-native-jats-figure-alt-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-jats-figure-alt-handoff.php` emitted a
  WordPress image block with `src="foo.png"` and
  `alt="alternative-decription"`; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 3,169 assertions,
  0 failures. The focused file now contains 319 behavior tests. No
  no-argument root harness was assigned or started.

## 2026-05-24 Native DOCX VML/Object Image Slice

- Focused mapped count is now 1,492 Markdown/HTML/Native/WordPress checks.
- Added bounded NativeReader/NativeWriter/WordPress coverage from upstream DOCX
  native fixture `test/docx/image_vml_as_object.native`.
- The targeted DOCX subinventory remains 233 artifacts under `test/docx`,
  including 112 `.native` expectations, 121 `.docx` inputs/goldens, and 38
  golden `.docx` outputs. This strengthens DOCX Native fixture mapping without
  activating DOCX ZIP/OpenXML package parsing.
- The copied fixture contributes a normal paragraph followed by a paragraph
  Image node whose target is `media/image1.emf`, matching Pandoc's VML object
  image handoff.
- NativeReader preserves the EMF Image packet; NativeWriter round-trips the
  `Image ... ( "media/image1.emf" , "" )` shape; WordPress output keeps the
  existing image-block handoff but adds `data-pandoc-source-format="emf"` so
  migration reviewers can identify source media that WordPress/browser paths
  may need conversion. Browser-native image targets are not flagged.
- Focused evidence: `diff -u <(git -C .upstream-cache/pandoc show
  HEAD:test/docx/image_vml_as_object.native)
  lanes/pandoc/fixtures/upstream-native-docx-vml-object-image.native`
  confirmed the copied fixture matches upstream; `php -l` passed for
  `WordPressBlockWriter.php`, `MarkdownReaderTest.php`, and
  `examples/wordpress-native-docx-vml-object-image-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-docx-vml-object-image-handoff.php`
  emitted a WordPress image block with `data-pandoc-source-format="emf"`; `php
  tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file,
  3,154 assertions, 0 failures. The focused file now contains 318 behavior
  tests. No no-argument root harness was assigned or started.

## 2026-05-24 Native HTML Row Header Table Slice

- Focused mapped count is now 1,449 Markdown/HTML/Native/WordPress checks.
- Added bounded NativeReader/NativeWriter/WordPress coverage from upstream
  `test/html-reader.native` for the HTML reader row-header table region.
- The upstream static denominator remains 2,276 inspected upstream
  test/data/benchmark files and artifacts. This slice reuses the existing
  `test/html-reader.native` inventory rather than activating a new dependency.
- The copied lane fixture contributes the upstream-shaped `Row headers`
  paragraph plus a three-column table whose `TableBody` carries
  `RowHeadColumns 1` without per-cell `header` attributes.
- NativeReader preserves the `RowHeadColumns` metadata; NativeWriter
  round-trips the `TableBody ... (RowHeadColumns 1)` packet; WordPress output
  now renders first-column body cells as `<th>` while leaving regular data
  cells as `<td>`. The renderer only upgrades cells fully contained in the
  row-head column range, so existing spanning summary cells remain data cells.
- Focused evidence: targeted upstream-cache reads inspected the row-header
  table region of `test/html-reader.native`; `php -l` passed for
  `WordPressBlockWriter.php`, `MarkdownReaderTest.php`, and
  `examples/wordpress-native-html-row-header-table-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-html-row-header-table-handoff.php`
  emitted a WordPress table with first-column body `<th>` cells; `php
  tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file,
  3,121 assertions, 0 failures. The focused file now contains 314 behavior
  tests. No no-argument root harness was assigned or started.

## 2026-05-24 Native ODT Nested Continued List Slice

- Focused mapped count is now 1,442 Markdown/HTML/Native/WordPress checks.
- Added bounded NativeReader/NativeWriter/WordPress coverage from upstream ODT
  native fixture `test/odt/native/listContinueNumbering2.native`.
- The targeted ODT subinventory remains 99 artifacts under `test/odt`,
  including 29 `.native` expectations, 18 Markdown source fixtures, and 52
  `.odt` inputs. This extends ODT Native fixture mapping without activating
  OpenDocument ZIP/XML package parsing.
- The copied fixture contributes three split top-level ordered lists with
  start values `1`, `2`, and `3`, three nested `LowerAlpha`/`Period` sublists,
  two interleaved text paragraphs, and four empty `Para []` separators.
- NativeReader preserves the continued-list starts, nested list markers, text
  paragraphs, and empty separators for Native read-back; NativeWriter
  round-trips the `LowerAlpha`/`Period` markers; WordPress output omits empty
  paragraph artifacts while preserving top-level `start` attributes and, in
  opt-in reviewer mode, `data-pandoc-list-style` plus
  `data-pandoc-list-delimiter="period"` for the nested alpha lists.
- Focused evidence: `diff -u <(git -C .upstream-cache/pandoc show
  HEAD:test/odt/native/listContinueNumbering2.native)
  lanes/pandoc/fixtures/upstream-native-odt-list-continuation-nested.native`
  confirmed the copied fixture matches upstream; `php -l` passed for
  `WordPressBlockWriter.php`, `MarkdownReaderTest.php`, and
  `examples/wordpress-native-odt-nested-list-continuation-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-odt-nested-list-continuation-handoff.php`
  emitted continued ordered-list blocks with nested lower-alpha source
  metadata; `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  passed 1 file, 3,109 assertions, 0 failures; `git diff --check --
  lanes/pandoc` passed. The focused file now contains 313 behavior tests. No
  no-argument root harness was assigned or started.

## 2026-05-24 Native ODT Table Span Slice

- Focused mapped count is now 1,422 Markdown/HTML/Native/WordPress checks.
- Added bounded NativeReader/NativeWriter/WordPress coverage from upstream ODT
  native fixture `test/odt/native/tableWithSpans.native`.
- The targeted ODT subinventory remains 99 artifacts under `test/odt`,
  including 29 `.native` expectations, 18 Markdown source fixtures, and 52
  `.odt` inputs. This strengthens ODT Native fixture mapping without activating
  OpenDocument ZIP/XML package parsing.
- The copied fixture contributes a two-row table head with a row-spanning
  header and a column-spanning second header row, a body row with a 3-row span,
  a body row with a 2-column span, a body cell with both a 2-row span and a
  2-column span, and a trailing empty `Para []` packet.
- NativeReader preserves those row/column span counts and the trailing empty
  paragraph for Native read-back; NativeWriter round-trips `RowSpan` and
  `ColSpan` markers; WordPress output emits `rowspan`/`colspan` table cells,
  omits the trailing empty paragraph, and does not invent a `colgroup` for
  default-width ODT columns.
- Targeted upstream-cache smoke evidence parsed all 144 selected DOCX, ODT,
  and EPUB `.native` expectations through NativeReader, NativeWriter, and
  WordPressBlockWriter. Focused PHP evidence: `php -l` passed for
  `MarkdownReaderTest.php` and
  `examples/wordpress-native-odt-table-spans-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-odt-table-spans-handoff.php` emitted a
  WordPress table with header/body rowspans and colspans; `php
  tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file,
  3,089 assertions, 0 failures. The focused file now contains 312 behavior
  tests. No no-argument root harness was assigned or started.

## 2026-05-24 Native DOCX Task List Glyph Slice

- Focused mapped count is now 1,402 Markdown/HTML/Native/WordPress checks.
- Added bounded NativeReader/NativeWriter/WordPress coverage from upstream DOCX
  native fixture `test/docx/task_list.native`.
- The targeted DOCX subinventory remains 233 artifacts under `test/docx`,
  including 112 `.native` expectations, 121 `.docx` inputs/goldens, and 38
  golden `.docx` outputs. This strengthens DOCX Native fixture mapping without
  activating DOCX ZIP/OpenXML package parsing.
- The copied fixture contributes top-level unchecked/checked ballot glyph list
  items, a checked item with a continuation paragraph, a nested checked
  sublist, a nested unchecked sub-sublist, and an ordered child list.
- NativeReader preserves the upstream ballot glyphs as Native text nodes and
  NativeWriter round-trips them as numeric string escapes. WordPress output
  remains faithful by default; reviewer handoffs can opt into
  `taskGlyphsAsCheckboxes` to convert those source glyphs into checkbox labels
  without leaving glyphs in the visible labels.
- Targeted upstream-cache smoke evidence parsed all 112 DOCX `.native`
  expectations and all 29 ODT `.native` expectations with NativeReader.
  Focused PHP evidence: `php -l` passed for `WordPressBlockWriter.php`,
  `MarkdownReaderTest.php`, and
  `examples/wordpress-native-docx-task-list-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-docx-task-list-handoff.php` emitted
  top-level, continuation-paragraph, and nested checkbox handoff markup; `php
  tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file,
  3,069 assertions, 0 failures. The focused file now contains 311 behavior
  tests. No no-argument root harness was assigned or started.

## 2026-05-24 Native ODT Mixed List Slice

- Focused mapped count is now 1,392 Markdown/HTML/Native/WordPress checks.
- Added bounded NativeReader/NativeWriter/WordPress coverage from upstream ODT
  native fixture `test/odt/native/orderedListMixed.native`.
- The targeted ODT subinventory remains 99 artifacts under `test/odt`,
  including 29 `.native` expectations, 18 Markdown source fixtures, and 52
  `.odt` inputs. This continues to avoid activating the OpenDocument ZIP/XML
  package dependency gate.
- The copied fixture contributes one top-level Decimal/Period ordered list, a
  nested Decimal/Period ordered list, a nested LowerAlpha/OneParen ordered
  list, an empty paragraph separator, and a start=4 continuation list.
- NativeReader already preserved the list marker tuple; NativeWriter now has
  focused round-trip coverage for the ODT `OneParen` marker, and
  WordPressBlockWriter has an opt-in `preserveListAttributes` handoff mode that
  emits reviewer-facing `data-pandoc-list-style` and
  `data-pandoc-list-delimiter` attributes without changing default WordPress
  list output.
- Targeted upstream-cache smoke evidence parsed the full
  `test/odt/native/orderedListMixed.native` fixture as 3 top-level blocks, 4
  ordered lists, 1 one-paren list, and 1 `OneParen` NativeWriter round-trip
  marker. Focused PHP evidence: `php -l` passed for
  `WordPressBlockWriter.php`, `MarkdownReaderTest.php`, and
  `examples/wordpress-native-odt-mixed-list-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-odt-mixed-list-handoff.php` emitted a
  nested WordPress ordered list with opt-in source list marker metadata; `php
  tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file,
  3,054 assertions, 0 failures. The focused file now contains 310 behavior
  tests. No no-argument root harness was assigned or started.

## 2026-05-24 Native EPUB Default List Style Slice

- Focused mapped count is now 1,383 Markdown/HTML/Native/WordPress checks.
- Added bounded NativeReader/NativeWriter/WordPress coverage from upstream EPUB
  native fixture `test/epub/formatting.native`.
- The targeted EPUB subinventory remains 11 artifacts under `test/epub`,
  including 3 `.native` expectations and 8 `.epub` inputs. This strengthens
  Native read-back for EPUB fixture semantics without activating ZIP/package
  parsing.
- The copied slice contributes EPUB styling title/identifier metadata, one
  source XHTML `Span` marker, one section `Div`, and one `OrderedList` using
  Pandoc Native `( 1 , DefaultStyle , DefaultDelim )`.
- NativeReader now distinguishes `DefaultStyle`/`DefaultDelim` from concrete
  `Decimal`/`Period`; NativeWriter preserves the default markers during
  read-back while keeping hand-authored AST lists with no style/delimiter on
  the existing Decimal/Period output path. WordPress output emits the default
  list as a plain `<ol>` without a misleading concrete `type` attribute.
- Targeted upstream-cache smoke evidence parsed the full
  `test/epub/formatting.native` fixture as 14 top-level blocks with 21 ordered
  lists; all 21 use `DefaultStyle`/`DefaultDelim`, and NativeWriter round-trip
  output preserved 21 default marker pairs. Focused PHP evidence: `php -l`
  passed for `NativeReader.php`, `NativeWriter.php`,
  `MarkdownReaderTest.php`, and
  `examples/wordpress-native-epub-default-list-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-epub-default-list-handoff.php`
  emitted a metadata review block, source XHTML marker, and a section wrapper
  containing `<ol><li>.</li></ol>`; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 3,034 assertions,
  0 failures. The focused file now contains 309 behavior tests. No
  no-argument root harness was assigned or started.

## 2026-05-24 Native EPUB Math Slice

- Focused mapped count is now 1,375 Markdown/HTML/Native/WordPress checks.
- Added bounded NativeReader/NativeWriter/WordPress coverage from upstream EPUB
  native fixture `test/epub/features.native`.
- The targeted EPUB subinventory remains 11 artifacts under `test/epub`,
  including 3 `.native` expectations and 8 `.epub` inputs. This strengthens
  the Native fixture mapping without activating EPUB ZIP/package parsing.
- The copied fixture contributes the EPUB testsuite title/identifier metadata,
  one source XHTML `Span` marker, one MathML section `Div`, one required test
  `Div`, one optional test `Div`, three `Math DisplayMath` packets, and one
  `Math InlineMath` packet.
- NativeWriter now preserves boolean display math parsed by NativeReader
  instead of rewriting `DisplayMath` as `InlineMath` during read-back.
  WordPress output renders the source math as display or inline math spans
  while keeping the source EPUB section ids/classes in reviewer HTML blocks.
- Targeted upstream-cache smoke evidence parsed the full
  `test/epub/features.native` fixture as 6 top-level blocks, 11 math nodes,
  and 6 display math nodes, then round-tripped 6 `DisplayMath` and 5
  `InlineMath` markers through NativeWriter. Focused PHP evidence: `php -l`
  passed for `NativeWriter.php`, `MarkdownReaderTest.php`, and
  `examples/wordpress-native-epub-math-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-epub-math-handoff.php` emitted a
  metadata review block, source XHTML section ids/classes, three display math
  spans, and one inline math span; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 3,018 assertions,
  0 failures. The focused file now contains 308 behavior tests. No
  no-argument root harness was assigned or started.

## 2026-05-24 Native EPUB Section Slice

- Focused mapped count is now 1,363 Markdown/HTML/Native/WordPress checks.
- Added bounded NativeReader/NativeWriter/WordPress coverage from upstream EPUB
  native fixture `test/epub/wasteland.native`.
- The targeted EPUB subinventory is now explicitly counted: 11 artifacts under
  `test/epub`, including 3 `.native` expectations and 8 `.epub` inputs. This
  strengthens the static inventory without activating EPUB ZIP/package parsing.
- The copied fixture contributes `The Waste Land` title/date/author metadata,
  a cover `Image` packet, one source XHTML `Span` marker, and three section
  `Div` packets carrying source XHTML ids plus `section`/`frontmatter`/
  `bodymatter` classes.
- WordPress output now preserves safe Native `Div` ids/classes/data/aria-like
  attributes in reviewer HTML blocks while continuing to map source
  `custom-style` to `data-pandoc-custom-style`. NativeWriter also preserves a
  parsed single-author `MetaInlines` value instead of coercing it through a
  list-string path.
- Targeted upstream cache reads inspected `test/epub/wasteland.native`; `git
  ls-tree` counted `test/epub` artifacts. Focused PHP evidence: `php -l`
  passed for `NativeWriter.php`, `WordPressBlockWriter.php`,
  `MarkdownReaderTest.php`, and
  `examples/wordpress-native-epub-section-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-epub-section-handoff.php` emitted a
  metadata review block, cover image block, source XHTML marker, and nested
  section divs with source ids/classes; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 2,991 assertions,
  0 failures. The focused file now contains 307 behavior tests. No
  no-argument root harness was assigned or started.

## 2026-05-24 Native ODT Image Caption Slice

- Focused mapped count is now 1,344 Markdown/HTML/Native/WordPress checks.
- Added bounded NativeReader/NativeWriter/WordPress coverage from upstream ODT
  native fixture `test/odt/native/imageWithCaption.native`.
- The targeted ODT subinventory remains 99 artifacts under `test/odt`,
  including 29 `.native` expectations, 18 Markdown source fixtures, and 52
  `.odt` inputs. This continues to avoid activating the OpenDocument ZIP/XML
  package dependency gate.
- The copied fixture contributes one `Figure` packet, one `Caption` inline
  list, one `Image` node, the `Pictures/...jpg` source target, ODT-derived
  `width="5.292cm"` and `height="5.292cm"` attributes, and an image alt label
  that includes the source figure number text.
- WordPress output now renders Native figure captions from parsed caption
  inline nodes before falling back to plain caption strings. That keeps the
  visible WordPress figcaption as `Image caption` while preserving the source
  image alt label `Abbildung 1: Image caption` and source dimensions as
  reviewer-safe image metadata.
- Targeted upstream cache reads inspected
  `test/odt/native/imageWithCaption.native`; `git ls-tree` counted `test/odt`
  artifacts. Focused PHP evidence: `php -l` passed for
  `WordPressBlockWriter.php`, `MarkdownReaderTest.php`, and
  `examples/wordpress-native-odt-image-caption-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-odt-image-caption-handoff.php` emitted
  a WordPress image block with source dimensions and figcaption; `php
  tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file,
  2,972 assertions, 0 failures. The focused file now contains 306 behavior
  tests. No no-argument root harness was assigned or started.

## 2026-05-24 Native ODT Reference Anchor Slice

- Focused mapped count is now 1,332 Markdown/HTML/Native/WordPress checks.
- Added bounded NativeReader/NativeWriter/WordPress coverage from upstream ODT
  native fixtures `test/odt/native/referenceToText.native` and
  `test/odt/native/referenceToListItem.native`.
- The targeted ODT subinventory remains 99 artifacts under `test/odt`,
  including 29 `.native` expectations, 18 Markdown source fixtures, and 52
  `.odt` inputs. This extends the Native fixture mapping without activating the
  OpenDocument ZIP/XML package dependency gate.
- The copied fixtures contribute three anchor `Span` packets, two
  same-document `Link` targets, one explicit `LineBreak`, and two empty
  `Para []` separators. One upstream anchor id contains a space (`an anchor`),
  which is preserved in NativeReader/NativeWriter round-trip output.
- WordPress output now normalizes whitespace-containing same-document
  fragments to valid HTML ids/hrefs, while preserving source values as
  `data-pandoc-source-id` and `data-pandoc-source-href` metadata. Plain
  `#anchor` list-item references remain unchanged, and empty separators are
  still omitted from block output.
- Targeted upstream cache reads inspected
  `test/odt/native/referenceToText.native` and
  `test/odt/native/referenceToListItem.native`; `git ls-tree` counted
  `test/odt` artifacts. Focused PHP evidence: `php -l` passed for
  `WordPressBlockWriter.php`, `MarkdownReaderTest.php`, and
  `examples/wordpress-native-odt-reference-anchor-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-odt-reference-anchor-handoff.php`
  emitted normalized same-document fragments with source metadata; `php
  tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file,
  2,957 assertions, 0 failures. The focused file now contains 305 behavior
  tests. No no-argument root harness was assigned or started.

## 2026-05-24 Native ODT Continued List Slice

- Focused mapped count is now 1,316 Markdown/HTML/Native/WordPress checks.
- Added bounded NativeReader/NativeWriter/WordPress coverage from upstream ODT
  native fixture `test/odt/native/listContinueNumbering.native`.
- The targeted ODT subinventory is now explicitly counted: 99 artifacts under
  `test/odt`, including 29 `.native` expectations, 18 Markdown source
  fixtures, and 52 `.odt` inputs. This strengthens the static inventory
  without activating the OpenDocument ZIP/XML package dependency gate.
- The copied fixture contributes five continued ordered-list start values
  (`1`, `2`, `4`, `1`, `2`) separated by empty `Para []` nodes that Pandoc
  preserves in Native output.
- NativeReader preserves the continued-list starts and empty paragraph
  separators, NativeWriter round-trips those empty `Para` packets, and
  WordPress output omits the empty paragraph separators while preserving
  `<ol start="...">` attributes for continued lists. This keeps ODT import
  review blocks from accumulating blank paragraph artifacts.
- Targeted upstream cache reads inspected
  `test/odt/native/listContinueNumbering.native` and `git ls-tree` counted
  `test/odt` artifacts. Focused PHP evidence: `php -l` passed for
  `WordPressBlockWriter.php`, `MarkdownReaderTest.php`, and
  `examples/wordpress-native-odt-list-continuation-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-odt-list-continuation-handoff.php`
  emitted WordPress ordered-list blocks with preserved starts and no empty
  `<p></p>` blocks; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 2,936 assertions,
  0 failures. The focused file now contains 304 behavior tests. No
  no-argument root harness was assigned or started.

## 2026-05-24 Native Citation Metadata Slice

- Focused mapped count is now 1,290 Markdown/HTML/Native/WordPress checks.
- Added bounded NativeReader/NativeWriter/WordPress coverage from upstream
  fixture `test/markdown-citations.native`.
- The copied slice contributes normal, author-in-text, suppress-author,
  grouped, non-ASCII, and note-contained `Cite`/`Citation` records with ids,
  modes, note numbers, prefixes, suffixes, and rendered citation display text.
- WordPress output now preserves visible citation text while wrapping it in a
  `pandoc-citation` span with `data-pandoc-citation-*` metadata. That gives
  import/review tooling a citation handoff without invoking upstream Pandoc or
  citeproc.
- Targeted upstream cache reads inspected `test/markdown-citations.native`.
  A focused probe parsed all 112 `test/docx/*.native` fixtures through
  `NativeReader` without failures; this is parser smoke evidence, not an
  upstream Haskell runner. Focused PHP evidence: `php -l` passed for
  `WordPressBlockWriter.php`, `MarkdownReaderTest.php`, and
  `examples/wordpress-native-citation-metadata-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-citation-metadata-handoff.php`
  emitted WordPress citation metadata spans; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 2,910 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 Native DOCX Table Caption Anchor Slice

- Focused mapped count is now 1,304 Markdown/HTML/Native/WordPress checks.
- Added bounded NativeReader/NativeWriter/WordPress coverage from upstream
  DOCX native fixture `test/docx/table_captions_with_field.native`.
- The copied fixture contributes two table cross-reference links to `#_Ref...`
  targets and two `Caption` packets whose long captions begin with empty
  `Span` nodes carrying the matching `_Ref...` ids and `anchor` classes.
- NativeReader now preserves single-block caption inline nodes in
  `captionInlines` instead of flattening captions to plain text only. That
  keeps hidden caption anchors available for NativeWriter round-trip output and
  WordPress figcaption rendering.
- WordPress output now keeps the source table anchors as
  `<span id="_Ref..." class="anchor"></span>` inside figcaptions while
  preserving the surrounding "See Table" links. This supports DOCX imports
  where Word table fields/cross-references need stable in-page targets after a
  block conversion.
- Targeted upstream cache reads inspected
  `test/docx/table_captions_with_field.native`. Focused PHP evidence:
  `php -l` passed for `NativeReader.php`, `MarkdownReaderTest.php`, and
  `examples/wordpress-native-docx-table-caption-anchor-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-docx-table-caption-anchor-handoff.php`
  emitted WordPress table captions with preserved `_Ref` anchors; `php
  tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file,
  2,924 assertions, 0 failures. The focused file now contains 303 behavior
  tests. No no-argument root harness was assigned or started.

## 2026-05-24 Native DOCX Image Dimension Slice

- Focused mapped count is now 1,262 Markdown/HTML/Native/WordPress checks.
- Added bounded NativeReader/NativeWriter/WordPress coverage from upstream
  DOCX native fixture `test/docx/image_no_embed.native`.
- The copied fixture contributes an `Image` inline with a `media/image1.jpg`
  target, title text, alt inlines, and DOCX-derived `width="6.5in"` plus
  `height="5.508333333333334in"` Pandoc attributes.
- WordPress output now preserves these source dimensions as
  `data-pandoc-width`/`data-pandoc-height` plus sanitized CSS dimensions on
  the rendered image. It intentionally does not emit raw HTML `width`/`height`
  attributes with CSS units.
- Targeted upstream cache reads inspected `test/docx/image_no_embed.native`.
  Focused PHP evidence: `php -l` passed for `WordPressBlockWriter.php`,
  `MarkdownReaderTest.php`, and
  `examples/wordpress-native-docx-image-dimensions-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-docx-image-dimensions-handoff.php`
  emitted a WordPress image block with source image dimensions preserved as
  reviewer-safe metadata; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 2,882 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 Native DOCX Table Header Rowspan Slice

- Focused mapped count is now 1,251 Markdown/HTML/Native/WordPress checks.
- Added bounded NativeReader/NativeWriter/WordPress coverage from upstream
  DOCX native fixture `test/docx/table_header_rowspan.native`.
- The copied slice contributes scientific-notation `ColWidth` literals such as
  `9.707602339181289e-2`, a two-row `TableHead`, row-spanned header cells, a
  grouped `ColSpan 3` header, and an eight-cell body row.
- NativeReader now accepts exponent notation in Native numeric tokens, which
  is required for upstream DOCX table width packets. NativeWriter renders the
  parsed values back as deterministic decimal `ColWidth` values.
- WordPress output renders the upstream-shaped table as a core table with
  `colgroup` widths, `rowspan`, `colspan`, and inherited first-column
  alignment for the second header row.
- Targeted upstream cache reads inspected `test/docx/table_header_rowspan.native`
  for the source expectation. Focused PHP evidence: `php -l` passed for
  `NativeReader.php`, `MarkdownReaderTest.php`, and
  `examples/wordpress-native-docx-table-header-rowspan-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-docx-table-header-rowspan-handoff.php`
  emitted a WordPress table with scientific width-derived colgroup output plus
  spanned header cells; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 2,871 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 Native DOCX Empty Field / Index Reference Slice

- Focused mapped count is now 1,235 Markdown/HTML/Native/WordPress checks.
- Added bounded NativeReader/NativeWriter/WordPress coverage from upstream DOCX
  native fixture `test/docx/empty_field.native`.
- The copied fixture contributes an empty `Span` with class `indexref` and an
  `entry` attribute, two imported external `Link` nodes, and Haskell numeric
  string escapes with `\&` separators in surrounding Unicode text.
- WordPress output now maps `indexref` spans to
  `data-pandoc-index-entry`, so migration reviewers can preserve source index
  terms without emitting raw upstream `entry` attributes.
- Targeted upstream cache reads inspected `test/docx/empty_field.native` for
  the source expectation. Focused PHP evidence: `php -l` passed for
  `WordPressBlockWriter.php`, `MarkdownReaderTest.php`, and
  `examples/wordpress-native-docx-index-field-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-docx-index-field-handoff.php` emitted
  WordPress paragraphs with a `data-pandoc-index-entry` span, decoded text, and
  escaped link URLs; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 2,855 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 Native DOCX Document Properties Slice

- Focused mapped count is now 1,222 Markdown/HTML/Native/WordPress checks.
- Added bounded NativeReader/NativeWriter/WordPress coverage from upstream DOCX
  native fixture `test/docx/document-properties.native`.
- The copied fixture contributes a full `Pandoc (Meta ...)` packet with title,
  author, custom properties, `MetaBlocks` descriptions, keyword `MetaList`
  values, raw HTML metadata in `RawInline (Format "html")` nodes, and nested
  `MetaMap` custom values.
- WordPress output now has an opt-in `includeMetadata` mode that emits a
  reviewer-safe metadata block for document properties while escaping raw HTML
  metadata instead of executing it.
- Targeted upstream cache reads inspected `test/docx/document-properties.native`
  plus adjacent `document-properties-short-desc.native`,
  `metadata.native`, and `metadata_after_normal.native` expectations for
  semantic context. Focused PHP evidence: `php -l` passed for
  `WordPressBlockWriter.php`, `MarkdownReaderTest.php`, and
  `examples/wordpress-native-docx-document-properties-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-docx-document-properties-handoff.php`
  emitted a WordPress metadata review block plus the body paragraph; `php
  tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1
  file, 2,841 assertions, 0 failures. No no-argument root harness was assigned
  or started.

## 2026-05-24 Native DOCX Custom Style Slice

- Focused mapped count is now 1,203 Markdown/HTML/Native/WordPress checks.
- Added bounded NativeReader/NativeWriter/WordPress coverage from upstream DOCX
  native fixture `test/docx/custom_style.native`.
- The copied fixture contributes three `Span` packets and one `Div` packet
  carrying DOCX `custom-style` attributes for inline and block Word styles.
- WordPress output now maps those attributes to
  `data-pandoc-custom-style`, so migration reviewers can see source Word style
  boundaries without emitting raw upstream `custom-style` attributes.
- Targeted upstream cache reads inspected `test/docx/custom_style.native` plus
  adjacent preserve/roundtrip custom-style expectations for semantic context.
  Focused PHP evidence: `php -l` passed for `WordPressBlockWriter.php`,
  `MarkdownReaderTest.php`, and
  `examples/wordpress-native-docx-custom-style-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-docx-custom-style-handoff.php`
  emitted WordPress paragraphs and a reviewer HTML block with
  `data-pandoc-custom-style` attributes; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 2,822
  assertions, 0 failures. No no-argument root harness was assigned or
  started.

## 2026-05-24 Native DOCX Paragraph Change Slice

- Focused mapped count is now 1,191 Markdown/HTML/Native/WordPress checks.
- Added bounded NativeReader/WordPress coverage from upstream DOCX native
  fixture `test/docx/paragraph_insertion_deletion_all.native`.
- The copied fixture contributes one `Span` with class `paragraph-insertion`
  and one `Span` with class `paragraph-deletion`, both carrying DOCX author and
  date metadata on empty paragraph-boundary markers.
- WordPress output now maps those paragraph boundary spans to zero-width
  review markers with `data-pandoc-paragraph-change`, `data-pandoc-change-*`,
  and `datetime` metadata, so paragraph split/merge review state remains
  inspectable without leaking raw upstream `author` or `date` attributes.
- Targeted upstream cache reads inspected
  `test/docx/paragraph_insertion_deletion_all.native` plus the adjacent
  accept/reject expectations for semantic context. Focused PHP evidence:
  `php -l` passed for `WordPressBlockWriter.php`, `MarkdownReaderTest.php`,
  and `examples/wordpress-native-docx-paragraph-change-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-docx-paragraph-change-handoff.php`
  emitted WordPress paragraphs with paragraph insertion/deletion metadata;
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed
  1 file, 2,810 assertions, 0 failures. No no-argument root harness was
  assigned or started.

## 2026-05-24 Native DOCX Raw OpenXML Slice

- Focused mapped count is now 1,177 Markdown/HTML/Native/WordPress checks.
- Added bounded NativeReader/WordPress coverage from upstream DOCX native
  fixtures: `test/docx/raw-bookmarks.native` and
  `test/docx/raw-blocks.native`.
- The copied raw-bookmarks fixture contributes two `RawInline (Format
  "openxml")` bookmark boundary nodes. WordPress output now maps
  `w:bookmarkStart` and `w:bookmarkEnd` to zero-width spans carrying
  `data-pandoc-bookmark-id` and, for starts, `data-pandoc-bookmark-name`.
- The copied raw-blocks fixture contributes three `RawBlock (Format
  "openxml")` table-fragment packets. WordPress output now renders generic raw
  blocks as escaped review code blocks with `data-pandoc-raw-format`, so DOCX
  OpenXML remains visible to migration reviewers without becoming active HTML.
- Focused PHP evidence: `php -l` passed for `WordPressBlockWriter.php`,
  `MarkdownReaderTest.php`, and
  `examples/wordpress-native-docx-raw-openxml-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-docx-raw-openxml-handoff.php` emitted
  WordPress paragraphs with bookmark metadata plus escaped OpenXML code blocks;
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed
  1 file, 2,796 assertions, 0 failures. No no-argument root harness was
  assigned or started.

## 2026-05-24 NativeWriter Table Slice

- Focused mapped count is now 1,114 Markdown/HTML/Native/WordPress checks.
- Added bounded Native writer coverage from upstream native table output:
  `test/pipe-tables.native`, `test/tables.native`,
  `test/markdown-reader-more.native`, and the `Tests.Writers.Native`
  block-list round-trip boundary.
- NativeWriter now emits Pandoc-style `Table` blocks with table Attr tuples,
  `Caption` output, `ColSpec` alignment/width fields, `TableHead`,
  `TableBody` with `RowHeadColumns` and body-local head rows, `TableFoot`,
  and `Cell` Attr/Align/RowSpan/ColSpan fields. Inline cells are represented
  as `Plain` blocks, and block cells preserve nested block lists.
- Focused PHP evidence: `php -l` passed for `NativeWriter.php`,
  `MarkdownReaderTest.php`, and
  `examples/wordpress-native-table-handoff.php`;
  `php lanes/pandoc/examples/wordpress-native-table-handoff.php` emitted a
  standalone Pandoc Native reviewer packet with table metadata, caption,
  colspecs, row-head columns, spanned cells, and nested block-cell content;
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed
  1 file, 2,674 assertions, 0 failures. No no-argument root harness was
  assigned or started.

## 2026-05-24 Native String Numeric Escape Slice

- Focused mapped count is now 1,130 Markdown/HTML/Native/WordPress checks.
- Added targeted Native string coverage from upstream `test/writer.native`,
  which contains the Haskell string escape shape `Str "M.A.\160\&2007"`,
  and from the `Tests.Writers.Native` read/show boundary.
- NativeReader now treats Haskell `\&` as an empty separator instead of a
  literal ampersand, so decimal character escapes immediately followed by
  digits decode correctly. NativeWriter now inserts `\&` after decimal escapes
  when the next character is a digit, preventing ambiguous output such as
  `\16042`.
- WordPress handoff now includes
  `examples/wordpress-native-string-escape-handoff.php`, which reads a Native
  packet containing nonbreaking-space source IDs and emits WordPress paragraph
  blocks without invoking upstream Pandoc.
- Focused PHP evidence: `php -l` passed for `NativeReader.php`,
  `NativeWriter.php`, `MarkdownReaderTest.php`, and
  `examples/wordpress-native-string-escape-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-string-escape-handoff.php` emitted
  WordPress paragraphs with nonbreaking-space source IDs; `php
  tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test
  file, 2,711 assertions, 0 failures. No no-argument root harness was assigned
  or started.

## 2026-05-24 NativeReader Read-Back Slice

- Focused mapped count is now 1,126 Markdown/HTML/Native/WordPress checks.
- Added bounded Native reader coverage for the upstream
  `Tests.Writers.Native` property boundary: `p_write_rt` reads full Pandoc
  Native output back to an AST, and `p_write_blocks_rt` reads blocks-only
  Native output back to a block list. The PHP slice does this for the lane's
  bounded common AST rather than the full upstream arbitrary AST.
- NativeReader now parses the Native syntax emitted by NativeWriter for
  `Pandoc` documents, `Meta { unMeta = fromList ... }`, blocks-only lists,
  Attr tuples, common block and inline constructors, Figures, Cites, Tables,
  captions, citation records, row/column spans, table head/body/foot sections,
  and Haskell-style string escapes.
- WordPress handoff now includes
  `examples/wordpress-native-reader-handoff.php`, which writes a Native packet,
  reads it back through NativeReader, and emits WordPress heading,
  paragraph/link, and table blocks.
- Focused PHP evidence: `php -l` passed for `NativeReader.php`,
  `MarkdownReaderTest.php`, and
  `examples/wordpress-native-reader-handoff.php`; `php
  lanes/pandoc/examples/wordpress-native-reader-handoff.php` emitted WordPress
  block markup from a NativeReader-parsed packet; `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 2,704 assertions,
  0 failures. No no-argument root harness was assigned or started.

## 2026-05-24 NativeWriter Figure/Cite Slice

- Focused mapped count is now 1,105 Markdown/HTML/Native/WordPress checks.
- Added bounded Native writer coverage from upstream native fixture output:
  `test/command/html-read-figure.md`, `test/command/cite-in-inline-note.md`,
  `test/markdown-citations.native`, and the Images section of
  `test/testsuite.native`.
- NativeWriter now emits Pandoc-style `Figure` blocks with Attr tuples,
  `Caption Nothing` or `Caption (Just [...])`, long caption blocks, and
  Plain-wrapped image bodies. The slice intentionally covers the existing
  lane AST's figure/image boundary, not arbitrary nested figures.
- NativeWriter now emits `Cite` inline nodes with `Citation` records,
  prefix/suffix inline lists, `NormalCitation`, `AuthorInText`, and
  `SuppressAuthor` modes, plus note-number/hash fields. Citeproc processing
  and bibliography rendering remain outside this native writer slice.
- Focused PHP evidence: `php tools/run-tests.php
  lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file, 2,666
  assertions, 0 failures. No no-argument root harness was assigned or started.

## Counted Static Denominator

- `test/` files/artifacts: 1,974
- `test/Tests/**/*.hs` Haskell test modules: 62
- Reader test modules: 34
- Writer test modules: 22
- Shared/support test modules: 6
- `test/command` artifacts: 1,155
- `test/command/*.md` command fixture files: 1,064
- `.native` expected artifacts under `test/`: 252
- `test/testsuite.txt` top-level Markdown sections: 14
- `test/testsuite.native` rendered native AST lines: 2,238
- `test/testsuite.native` `BlockQuote` nodes in the full rendered suite: 7
- `test/testsuite.native` `CodeBlock` nodes in the full rendered suite: 11
- `test/testsuite.native` `BulletList`/`OrderedList` nodes in the full rendered
  suite: 36
- `test/testsuite.txt` Lists section slice inspected in this run: 163 Markdown
  lines from `# Lists` through the start of `# Definition Lists`
- `test/testsuite.txt` Definition Lists section slice inspected in this run: 93
  Markdown lines through the start of `# HTML Blocks`
- `test/testsuite.native` Definition Lists rendered native AST slice inspected
  in this run: 163 lines
- `test/testsuite.txt` HTML Blocks section slice inspected in this run: 102
  Markdown lines through the start of `# Inline Markup`
- `test/testsuite.native` HTML Blocks rendered native AST slice inspected in
  this run: 161 lines, including 22 `RawBlock` markers, 9 `Div` markers, and 4
  `CodeBlock` markers
- `test/testsuite.txt` Inline Markup section slice inspected in this run: 30
  Markdown lines through the start of `# Smart quotes, ellipses, dashes`
- `test/testsuite.native` Inline Markup rendered native AST slice inspected in
  this run: 168 lines, including 9 `Emph` markers, 6 `Strong` markers,
  1 `Strikeout` marker, 3 `Superscript` markers, and 3 `Subscript` markers
- `test/testsuite.txt` Smart quotes, ellipses, dashes section slice inspected
  in this run: 21 Markdown lines through the start of `# LaTeX`
- `test/testsuite.native` Smart quotes, ellipses, dashes rendered native AST
  slice inspected in this run: 154 lines, including 14 `Quoted` markers plus
  smart apostrophe, em-dash, en-dash, and ellipsis code points in `Str` nodes
- `test/testsuite.txt` LaTeX section slice inspected in this run: 30 Markdown
  lines through the start of `# Special Characters`
- `test/testsuite.native` LaTeX rendered native AST slice inspected in this
  run: 152 lines, including 6 `InlineMath` markers, 1 `DisplayMath` marker,
  1 TeX `RawInline`, and 1 TeX `RawBlock`
- `test/testsuite.txt` Special Characters section slice inspected in this run:
  54 Markdown lines through the start of `# Links`, including five Unicode
  bullet-list item lines, one HTML entity line, sixteen punctuation
  backslash-escape lines, and a dashed horizontal rule
- `test/testsuite.native` Special Characters rendered native AST slice
  inspected in this run: 86 lines, including one `BulletList`, 45 `Str`
  markers, 22 `Para` markers, and one `HorizontalRule`
- `test/testsuite.txt` Links section slice inspected in this run: 86 Markdown
  lines through the start of `# Images`, covering explicit links, reference
  links, ampersand URL/text cases, URI/email autolinks, and no-autolink code
  contexts
- `test/testsuite.native` Links rendered native AST slice inspected in this
  run: 290 lines, including 25 `Link` nodes plus the code-block and code-span
  cases where autolinks must not fire
- `test/testsuite.txt` Images section slice inspected in this run: 12 Markdown
  lines through the start of `# Footnotes`, covering a standalone collapsed
  reference image with title metadata and an inline image inside a paragraph
- `test/testsuite.native` Images rendered native AST slice inspected in this
  run: 48 lines, including 2 `Image` nodes and 1 `Figure` node
- `test/testsuite.txt` Footnotes section slice inspected in this run: 28
  Markdown lines through end of file, covering reference notes, inline notes,
  quote-contained notes, list-contained notes, multi-block definitions,
  whitespace-separated termination, and an invalid spaced footnote label
- `test/testsuite.native` Footnotes rendered native AST slice inspected in this
  run: 305 lines, including 4 `Note` nodes
- `test/markdown-reader-more.txt` inspected in this run: 366 Markdown lines in
  Pandoc's additional Markdown reader fixture.
- `test/markdown-reader-more.native` inspected in this run: 1,715 rendered
  native AST lines.
- `test/markdown-reader-more.txt` title-block slice inspected in this run:
  six leading metadata lines covering a multiline `%` title, author lines split
  by both line boundaries and semicolons, and a blank separator before the first
  body heading.
- `test/markdown-reader-more.native` corresponding rendered AST slice
  inspected in this run: upstream lines 1-27 show `MetaInlines` title content
  with a `SoftBreak`, a four-entry `MetaList` author field, no date metadata,
  and `# Additional markdown reader tests` as the first body `Header`.
- `test/markdown-reader-more.txt` blank-reference and URL-space slice inspected
  in this run: 44 Markdown lines covering two reference definitions whose
  targets/titles live on following lines, four inline link destinations with
  spaces or multiline spaces, and three reference link destinations with spaces
  plus one parenthesized title.
- `test/markdown-reader-more.native` corresponding rendered AST slice inspected
  in this run: 100 lines showing two split reference-definition links and
  seven space-containing link destinations normalized with `%20`.
- `test/markdown-reader-more.txt` implicit-header-reference slice inspected in
  this run: upstream lines 169-189 cover shortcut, collapsed, and
  case-insensitive implicit references, an explicit reference definition that
  overrides an implicit heading reference, and explicit heading attributes.
- `test/markdown-reader-more.native` corresponding rendered AST slice
  inspected in this run: upstream lines 329-450 show Pandoc's duplicate
  generated heading id behavior (`my-header-1` after an earlier
  `my-header`), `#my-header` links for the implicit forms, `/foo` for the
  explicit override, and `#foobar` plus class/key metadata for the attributed
  heading.
- `test/markdown-reader-more.txt` backslash-newline and code-span slice
  inspected in this run: upstream lines 101-117 cover an explicit trailing
  backslash hard break, a code span ending in a literal backslash, a multiline
  code span, a longer backtick-delimited code span containing four literal
  backticks, and a blank-line-terminated unterminated code span.
- `test/markdown-reader-more.native` corresponding rendered AST slice
  inspected in this run: upstream lines 235-249 show `LineBreak`, three
  `Code` nodes with normalized code text, and two literal paragraph strings for
  the blank-line-terminated unterminated code span.
- `test/markdown-reader-more.txt` multilingual URL and numbered-example slice
  inspected in this run: upstream lines 119-135 cover one Unicode URI
  autolink, one inline link whose destination and title include Unicode source
  text, one Unicode e-mail autolink, two initial numbered examples, two inline
  references to example labels, and a later labeled example.
- `test/markdown-reader-more.native` corresponding rendered AST slice
  inspected in this run: upstream lines 249-295 show three `Link` nodes, an
  `OrderedList (1, Example, TwoParens)` with two items, inline paragraph text
  where `(@foo)` and `(@bar)` have become `(2)` and `(3)`, and a later
  `OrderedList (3, Example, TwoParens)` with one item.
- `test/markdown-reader-more.txt` case-insensitive reference, curly quote, and
  consecutive-list slice inspected in this run: upstream lines 142-167 cover
  three shortcut reference links whose definitions differ by case, two
  paragraphs containing already-curly Unicode quote marks, and three adjacent
  list families where the final one-space-indented `a.`/`b.` list remains a
  separate top-level lower-alpha list.
- `test/markdown-reader-more.native` corresponding rendered AST slice
  inspected in this run: upstream lines 301-328 show three resolved `Link`
  nodes, two literal `Str` nodes containing U+201C/U+201D and U+2018/U+2019,
  followed by separate `BulletList`, decimal `OrderedList`, and lower-alpha
  `OrderedList` blocks.
- `test/markdown-reader-more.txt` line-block slice inspected in this run:
  upstream lines 191-201 cover one pipe-prefixed line block with four
  indentation levels, one empty line entry, and two continuation-line cases
  where indented non-pipe lines fold into the previous line.
- `test/markdown-reader-more.native` corresponding rendered AST slice
  inspected in this run: upstream lines 451-516 show one `LineBlock` with
  seven line entries, including nonbreaking-space indentation counts of 4, 8,
  12, and 2 before the visible text.
- `test/markdown-reader-more.txt` indented-code-at-beginning-of-list slice
  inspected in this run: upstream lines 85-99 cover one bullet item whose first
  child is a code block, two nested ordered-list items whose first children are
  code blocks, one nested bullet item whose first child is a code block, and
  one four-space guard item that stays ordinary prose.
- `test/markdown-reader-more.native` corresponding rendered AST slice
  inspected in this run: upstream lines 207-234 show `CodeBlock` nodes for the
  five-space marker-padding items, an `OrderedList (1, Decimal, Period)` whose
  second item is numbered `12345678`, and a nested `BulletList` where
  `-    no code` remains `Plain [ Str "no" , Space , Str "code" ]`.
- `test/markdown-reader-more.txt` raw TeX environment and macro slices
  inspected in this run: upstream lines 20-37 cover Raw ConTeXt and Raw LaTeX
  environments, and lines 136-140 cover a `\newcommand` macro followed by math
  using the macro.
- `test/markdown-reader-more.native` corresponding rendered AST slices
  inspected in this run: upstream lines 61-94 show one `\placeformula
  \startformula` `RawBlock`, one paragraph ending with a `\stopformula`
  `RawInline`, one nested `\start[a2]`/`\stop[a2]` `RawBlock`, and one nested
  LaTeX `center`/`tikzpicture` `RawBlock`; upstream lines 296-300 show a
  `\newcommand{\tuple}[1]{\langle #1 \rangle}` `RawBlock` and later math
  expanded to `\langle x,y \rangle`.
- `test/markdown-reader-more.txt` `$ in math` slice inspected in this run:
  upstream lines 67-75 cover escaped dollar signs inside inline math, dollars
  inside a TeX `\text{...}` braced argument, and the `$PATH 90 $PATH`
  non-math guard.
- `test/markdown-reader-more.native` corresponding rendered AST slice
  inspected in this run: upstream lines 173-192 show two `Math InlineMath`
  nodes, including `x = \text{the $n$th root of $y$}`, followed by one
  ordinary paragraph containing literal `$PATH 90 $PATH` text.
- `test/markdown-reader-more.txt` horizontal-rule/raw-HTML/commented-list slice
  inspected in this run: upstream lines 55-83 cover two trailing-space
  horizontal-rule forms, one empty raw HTML anchor immediately before a level-3
  heading, and a commented-out list marker shape between two ordinary list
  items.
- `test/markdown-reader-more.native` corresponding rendered AST slice
  inspected in this run: upstream lines 137-206 show two `HorizontalRule`
  nodes, a paragraph with separate raw HTML open/close inline nodes for
  `<a></a>`, a `Header 3` with identifier `my-header`, and one `BulletList`
  whose commented marker lines remain attached to list item text.
- `test/markdown-reader-more.txt` rectangular grid-table slice inspected in
  this run: 74 mapped Markdown lines from the Grid Tables section cover the
  simple headed table, headless table, aligned headed table, aligned headless
  table, trailing-space table, East Asian width table, zero-width German and
  Persian text cases, and empty cells.
- `test/markdown-reader-more.native` corresponding rendered AST slice
  inspected in this run: 642 mapped native AST lines show Pandoc `Table`
  nodes with `ColWidth` values derived from grid widths divided by 72, default
  and right/left/center alignments, `TableHead []` for headless cases,
  `SoftBreak` entries inside multiline scalar cells, Unicode text cells, and
  empty `Cell ... []` bodies.
- `test/markdown-reader-more.txt` grid-table multiple-block cell case
  inspected in this run: upstream lines 252-261 cover a rectangular grid table
  whose cells contain Markdown headings, paragraph-separated text caused by an
  empty interior cell line, bullet-list items, and scalar multiline text.
- `test/markdown-reader-more.native` corresponding rendered AST slice
  inspected in this run: upstream lines 987-1087 show a headless `Table` whose
  first body row contains `Header` plus `Para` block children in each cell, and
  whose second body row contains two `Para` blocks, one `BulletList`, and one
  scalar `Plain` cell with `SoftBreak` entries.
- `test/markdown-reader-more.txt` remaining grid-table span cases inspected in
  this run: upstream lines 290-313 cover a row/column-span table plus a complex
  multi-row header table.
- `test/markdown-reader-more.native` corresponding rendered AST slice
  inspected in this run: upstream lines 1260-1492 show a header `Cell` with
  `ColSpan 2`, a body `Cell` with `RowSpan 3`, and a complex two-row
  `TableHead` whose `Location` header has `RowSpan 2` and whose temperature
  header has `ColSpan 3`.
- `test/markdown-reader-more.txt` post-grid reference-link edge slice inspected
  in this run: upstream lines 337-358 cover a backslash-containing link label,
  an unresolved reference-looking fallback pair, a shortcut reference followed
  by a citation marker, and an empty reference definition before ordinary
  paragraph text.
- `test/markdown-reader-more.native` corresponding rendered AST slice inspected
  in this run: upstream lines 1549-1649 show a `Link` whose label contains
  `Str "*"` plus `RawInline "\\a"`, bracketed fallback text retaining emphasized
  contents, a `Link` immediately followed by a `Cite`, and an empty-destination
  `Link` after the intervening `bar` paragraph.
- `test/markdown-reader-more.txt` wrapping/bracketed-span tail slice inspected
  in this run: upstream lines 360-366 cover one long bullet item ending in
  `2015.` and one bracketed span with `.class`, `#id`, and `key=val`
  attributes.
- `test/markdown-reader-more.native` corresponding rendered AST slice inspected
  in this run: upstream lines 1650-1715 show the heading id
  `wrapping-shouldnt-introduce-new-list-items`, one tight `BulletList` item
  whose `2015.` suffix remains plain text, and one `Span` containing nested
  `Emph` plus a `Link`.
- `test/pipe-tables.txt` pipe-table fixture inspected in this run: 82 Markdown
  lines covering 11 upstream pipe tables, including captioned, uncaptioned,
  headerless, side-less, one-column, no-body, relative-width, and tricky
  escaped-pipe/code-span cell cases
- `test/pipe-tables.native` pipe-table rendered native AST inspected in this
  run: 927 lines, including 11 `Table` nodes, 88 `Cell` nodes, two headerless
  `TableHead []` shapes, one `Code` node containing a literal pipe, and three
  relative-width `ColWidth` entries
- `test/tables.markdown` simple/multiline table fixture inspected in this run:
  76 Markdown lines covering seven gridless tables; all seven simple and
  multiline gridless table cases are now mapped
- `test/tables.native` rendered native AST inspected in this run: 964 lines,
  including seven `Table` nodes and two headerless `TableHead []` shapes
- `test/command/short-caption.md` command fixture inspected in this run: one
  LaTeX reader example whose native output is a `Table` with
  `Caption (Just [Str "short", Space, Str "caption"]) [Plain [...]]`, two
  left-aligned columns, no table head, and one body row.
- `test/command/table-with-cell-align.md` command fixture inspected in this
  run: 105 lines covering a DocBook `informaltable` reader example whose
  native output keeps per-cell `AlignCenter`, `AlignLeft`, `AlignRight`, and
  `AlignDefault` cell alignment while leaving table-level column alignments
  default.
- `test/command/table-with-column-span.md` command fixture inspected in this
  run: 385 lines covering a DocBook `informaltable` reader example with 16
  `colspec` entries, `ColWidth 6.25e-2`, strong emphasis inside spanned cells,
  and `namest`/`nameend` entries that become `ColSpan 8` cells.
- `test/command/rst-writer-gridtable-if-rowspans.md` command fixture inspected
  in this run: 246 lines covering Pandoc native table input rendered to RST
  grid tables. The native AST includes `RowSpan 2` cells in body, head, and
  foot sections; the bounded PHP slice maps those row-span and section shapes
  through DocBook `morerows`, `thead`, `tbody`, and `tfoot` input and
  WordPress `rowspan`/`tfoot` output.
- `test/command/nested-table-to-asciidoc-6942.md` command fixture inspected in
  this run: 82 lines covering HTML input rendered to AsciiDoc, including a
  two-level nested table that Pandoc renders as a nested table and a separate
  three-level case where the AsciiDoc writer warns because that target format
  only supports two table levels. The bounded PHP slice maps both the
  two-level nested-table AST shape and the full HTML document third-level case
  at the WordPress boundary; WordPress output preserves the third nested table
  rather than applying the AsciiDoc-specific downgrade.
- `test/command/tasklist.md` command fixture inspected in this run: 104 lines
  covering Pandoc's HTML writer output for simple task lists, nested task
  lists, mixed task/plain bullet lists, ordered task items, loose task items,
  plus separate LaTeX and Markdown round-trip examples. The bounded PHP slice
  maps the three HTML task-list examples plus the LaTeX and Markdown
  writer-specific examples.
- `src/Text/Pandoc/Writers/Markdown.hs` and `src/Text/Pandoc/Shared.hs`
  ordered-list writer path inspected in this run: Pandoc enables fancy list
  enumerators for Markdown, calls `orderedListMarkers`, preserves
  Decimal/DefaultStyle, upper/lower alpha, upper/lower roman, Period,
  OneParen, and TwoParens attributes, and pads markers shorter than three
  characters before hanging item content at the default tab stop.
- `test/html-reader.html` table section inspected in this run: 366 HTML lines
  from the upstream HTML reader fixture covering table head/body/foot sections,
  omitted section tags, row headers, colspan, rowspan, two tables with multiple
  `<tbody>` sections, plain tables without headers, and empty tables. This run
  used it as bounded reader context without claiming full HTML reader parity.
- `test/html-reader.native` table section inspected in this run: 1,393 native
  AST lines covering 18 `Table` nodes from the upstream HTML reader fixture.
  The inspected HTML slice contains 19 `<table` starts, 47 `<th` cells, 10
  `<thead` starts, 17 `<tbody` starts, 5 `<tfoot` starts, 20 native
  `TableBody` nodes, two native tables with multiple `TableBody` sections, one
  `Cell ... [ Para [ Str "2" ] ]` paragraph-bearing table cell in the second
  multiple-body case, one native `RowHeadColumns 1` body shape, and four native
  `TableBody` nodes with body-local head rows before ordinary body rows. The
  bounded PHP mapping now includes the two native colspan/rowspan table shapes,
  the attribute-carrying table shape, the two multiple-body table shapes, the
  paragraph-bearing cell from the second multiple-body case, the four
  body-local `TableBody` head-row cases, the four plain `Tables without
  Headers` body-only/body-omitted/empty-head/body-plus-foot shapes, plus the two
  empty table inputs omitted from the upstream native output.
- `test/html-reader.html` upstream Attributes table inspected in this run:
  lines 766-786 include table id metadata, `thead` class metadata, a head-row
  class, `tbody` class plus `data-part`, body-row `data-part`, practical cell
  attrs, `tfoot` class metadata, and a foot-row `bgcolor` marker.
- `test/html-reader.native` upstream Attributes table rendered AST inspected in
  this run: lines 3202-3272 show those fields as Pandoc native attributes on
  `Table`, `TableHead`, `Row`, `TableBody`, `TableFoot`, and `Cell` nodes.
- `test/html-reader.html` full-document head, intro, Headers, and Paragraphs
  slice inspected in this run: upstream lines 1-35 cover title/generator
  metadata, the title heading class, intro paragraph, early horizontal rules,
  generated heading identifiers, inline links/emphasis in headings, paragraphs
  immediately following headings with no blank line, a hard-wrapped paragraph
  whose middle sentence looks list-like, a literal bullet-looking paragraph,
  and a hard line break.
- `test/html-reader.native` full-document head, Headers, and Paragraphs rendered
  AST slice inspected in this run: upstream lines 1-230 show two `Meta` fields,
  ten early `Header` nodes including the title heading with class `title`, six
  early `Para` nodes, two `HorizontalRule` nodes before the hard-line-break
  case, one heading `Link`, two heading `Emph` shapes, and the `LineBreak`
  node in the final paragraph.
- `test/html-reader.html` paragraph and inline-quote slice inspected in this
  run: upstream lines 33-86 cover a paragraph hard line break and two `<q>`
  examples, one with a `cite` attribute and one without.
- `test/html-reader.native` paragraph and inline-quote rendered native AST
  slice inspected in this run: upstream lines 213-228 show `LineBreak` between
  text nodes, and lines 360-405 show two `Quoted DoubleQuote` nodes, including
  one citation-bearing `Span` child with the source URL preserved in native
  key-value attributes.
- `test/html-reader.html` inline style slice inspected in this run: upstream
  lines 323-325 cover one `font-variant: small-caps` span, `<u>` and `<ins>`
  underline inputs, and `<s>`, `<strike>`, and `<del>` strikeout inputs.
- `test/html-reader.native` inline style rendered native AST slice inspected in
  this run: upstream lines 922-958 show one `SmallCaps`, two `Underline`, and
  three `Strikeout` nodes for those HTML inputs.
- `test/html-reader.html` Code Blocks slice inspected in this run: upstream
  lines 88-102 cover two standalone `<pre><code>` blocks, one with blank lines
  and one with literal backslash escapes.
- `test/html-reader.native` Code Blocks rendered native AST slice inspected in
  this run: upstream lines 408-420 show those two inputs as `CodeBlock`
  nodes whose text removes the final closing-tag newline while preserving
  internal blank lines, four-space indentation, and literal `\$`, `\\`, `\>`,
  `\[`, and `\{` escapes.
- `test/html-reader.html` Block Quotes slice inspected in this run: upstream
  lines 36-83 cover eight `<blockquote>` containers, including a simple
  paragraph quote, a quote with `<pre><code>` and an ordered list, two nested
  sibling quotes, a box-style code quote, a list-only quote, and a nested quote
  inside another quote.
- `test/html-reader.native` Block Quotes rendered native AST slice inspected in
  this run: upstream lines 231-355 show eight `BlockQuote` nodes, two
  `CodeBlock` nodes inside quotes, and two `OrderedList` nodes inside quotes.
  The bounded PHP mapping now preserves the same quote/container shape and
  keeps HTML text-node apostrophes as straight HTML-reader text rather than
  applying Markdown smart punctuation inside imported HTML.
- `test/html-reader.html` top-level Lists slice inspected in this run:
  upstream lines 104-198 cover the `Lists` heading through the `List styles`
  cases, including six unordered tight/loose list examples, five ordered
  tight/loose/multiple-paragraph examples, and six empty ordered-list style
  metadata examples.
- `test/html-reader.native` top-level Lists rendered native AST slice
  inspected in this run: upstream lines 421-541 show six top-level
  `BulletList` nodes and 11 top-level `OrderedList` nodes. The six style cases
  map to DefaultStyle, LowerRoman via `type="i"`, LowerRoman via class,
  DefaultStyle for bare `style="lower-roman"`, and LowerRoman via
  `list-style` and `list-style-type` declarations.
- `test/html-reader.html` Nested/Tabs/Fancy list slice inspected in this run:
  upstream lines 199-302 cover the `Nested`, `Tabs and spaces`, and
  `Fancy list markers` sections immediately after the top-level list-style
  examples. The slice includes three HTML headings, nested `ul` levels, ordered
  lists with nested unordered children, paragraph-bearing list items, nested
  decimal/lower-roman/upper-alpha/upper-roman/lower-alpha ordered-list styles,
  and a nested default-style autonumbering shape.
- `test/html-reader.native` Nested/Tabs/Fancy list rendered native AST slice
  inspected in this run: upstream lines 542-764 show three `Header` nodes,
  seven `BulletList` nodes, and 11 `OrderedList` nodes. The bounded PHP mapping
  now preserves Pandoc's tight `Plain` shape for list items whose only block
  child is a nested list, keeps paragraph-bearing HTML list items loose, and
  preserves start/style metadata through the nested ordered-list chain.
- `test/html-reader.html` Definition slice inspected in this run: upstream
  lines 303-311 cover one `<dl>` with two term groups, including consecutive
  `<dt>` aliases (`Cello` and `Violoncello`) before a shared definition body.
- `test/html-reader.native` Definition rendered native AST slice inspected in
  this run: upstream lines 765-790 show one `DefinitionList`, two term groups,
  three definition bodies, and a `LineBreak` between the consecutive
  `Cello`/`Violoncello` terms. The bounded PHP mapping now preserves that term
  grouping and emits WordPress-safe `<dl>` output.
- `test/html-reader.html` initial Inline Markup slice inspected in this run:
  upstream lines 313-317 cover the `Inline Markup` heading, two emphasis nodes,
  two strong nodes, an implicitly closed paragraph with empty `<strong>` and
  `<em>` nodes, and an emphasized link paragraph immediately after it.
- `test/html-reader.native` initial Inline Markup rendered native AST slice
  inspected in this run: upstream lines 792-846 show the `inline-markup`
  header, two `Emph` nodes, two non-empty `Strong` nodes, empty `Strong []` and
  `Emph []` nodes, and an `Emph [ Link ... ]` shape. The bounded PHP mapping
  now preserves those nodes and handles the implicit paragraph close without
  swallowing the following emphasized-link paragraph.
- `test/html-reader.html` remaining Inline Markup nested/code slice inspected
  in this run: upstream lines 318-322 cover four nested
  `<strong><em>...</em></strong>` paragraphs plus one paragraph with five
  `<code>` spans containing `>`, `$`, `\`, `\$`, and `<html>`.
- `test/html-reader.native` remaining Inline Markup rendered native AST slice
  inspected in this run: upstream lines 847-921 show four nested
  `Strong [ Emph ... ]` paragraph shapes and five `Code` inline nodes. The
  bounded PHP mapping now preserves the nested strong/emphasis shape and code
  span literal text through WordPress output.
- `test/html-reader.html` Smart quotes, ellipses, dashes slice inspected in
  this run: upstream lines 326-336 cover two bare self-closing `<hr />`
  separators, the section heading, four straight quote/apostrophe paragraphs,
  one quoted HTML `<code>`/`<a>` paragraph, two dash/hyphen paragraphs, and one
  spaced ellipsis paragraph.
- `test/html-reader.native` Smart quotes, ellipses, dashes rendered native AST
  slice inspected in this run: upstream lines 961-1118 show two
  `HorizontalRule` nodes, one `Header`, and eight `Para` nodes. Unlike
  Pandoc's Markdown reader smart-punctuation section, the HTML reader keeps
  straight quotes, apostrophes, dash strings, numeric hyphen ranges, and
  ellipsis dots as literal `Str` text while preserving the quoted code/link
  span boundaries.
- `test/html-reader.html` LaTeX slice inspected in this run: upstream lines
  337-357 cover the `LaTeX` heading, nine TeX/math-looking list items, a
  "These shouldn't be math" paragraph, three not-math list items with
  `<code>` and `<em>` children, a LaTeX table-introduction paragraph, a
  one-line `\begin{tabular}` paragraph, and a self-closing `<hr />` separator.
- `test/html-reader.native` LaTeX rendered native AST slice inspected in this
  run: upstream lines 1119-1297 show the section as literal `Str` text, `Code`
  for the explicit HTML code spans, `Emph` for the explicit HTML emphasis
  spans, and a final `HorizontalRule`. Unlike Pandoc's Markdown reader LaTeX
  section, the HTML reader does not produce `Math` or TeX `RawInline` nodes for
  dollar-delimited or backslash-command source text.
- `test/html-reader.html` Special Characters slice inspected in this run:
  upstream lines 358-388 cover the `Special Characters` heading, one intro
  paragraph, five Unicode list items, five entity/comparison paragraphs,
  sixteen punctuation-token paragraphs, and a self-closing `<hr />` separator.
- `test/html-reader.native` Special Characters rendered native AST slice
  inspected in this run: upstream lines 1298-1385 show one `Header`, one
  `BulletList` with five `Plain` list items, 22 `Para` nodes, and one
  `HorizontalRule`. Unlike the Markdown-reader Special Characters section, the
  HTML reader gets already-decoded text from the HTML parser and does not treat
  `*`, `_`, `[`, `]`, `#`, or other punctuation tokens as Markdown syntax.
- `test/html-reader.html` Links slice inspected in this run: upstream lines
  389-430 cover the `Links` heading, explicit link paragraphs with href/title
  metadata, an empty href, reference-shaped link text that is already HTML,
  ampersand-bearing href/title/text cases, explicit autolink-looking anchors,
  link-looking code spans and code blocks, and mixed HTML flow lines where bare
  text is immediately followed by `<p>` or `<blockquote>`.
- `test/html-reader.native` Links rendered native AST slice inspected in this
  run: upstream lines 1386-1687 show four headers, 24 `Link` nodes, two
  link-free e-mail-text paragraphs, two code contexts where
  `<http://example.com/>` stays literal, one `BlockQuote`, one `BulletList`,
  and the closing `HorizontalRule`. The bounded PHP mapping now keeps the same
  HTML-reader path without invoking Markdown reference or autolink parsing.
- `test/html-reader.html` Images slice inspected in this run: upstream lines
  431-435 cover the `Images` heading, a source-credit paragraph, one
  standalone `<img>` paragraph with `src`, `title`, and `alt`, one inline
  `<img>` paragraph with `src` and `alt`, and a self-closing `<hr />`
  separator.
- `test/html-reader.native` Images rendered native AST slice inspected in this
  run: upstream lines 1688-1728 show one `Header`, two `Para` nodes with
  ordinary text, two `Image` nodes, and one closing `HorizontalRule`. The
  bounded PHP mapping keeps HTML `<img>` nodes on the HTML-reader path as
  image inline AST nodes instead of re-parsing them through Markdown image
  syntax.
- `test/tables/nordics.html5` fixture inspected in this run: 59 HTML lines
  from the upstream table writer artifacts, including caption inline emphasis,
  four `colgroup` widths, a `thead`, one `tbody`, one `tfoot`, row-header
  cells, `<br>` line breaks, and a superscript unit. The bounded PHP slice maps
  this structured HTML table shape into the native table AST and WordPress
  table output.
- `Tests.Readers.Markdown` definition-list cases: 8, all of which are now
  mapped by focused PHP tests
- `Tests.Readers.Markdown` smart apostrophe-after-math regression: 1 focused
  case, now mapped by a PHP test
- `Tests.Readers.Markdown` smart unclosed double-quote regression: 1 focused
  case, now mapped by a PHP test. `**this should "be bold**` stays a `Strong`
  node while the unmatched opening quote becomes Pandoc's left double quote.
- `Tests.Readers.Markdown` footnote edge cases: 3 focused cases, now mapped by
  PHP tests for whitespace-only indented separator termination, indented
  continuation after a blank line, and recursive references left literal inside
  note bodies
- `Tests.Readers.Markdown` MultiMarkdown sub- and superscripts group: 14
  focused cases, now mapped by PHP tests for regular delimited sub/superscripts,
  short digit scripts terminated by spaces, newlines, EOF, punctuation, and
  emphasis, plus the two no-nesting guards
- `Tests.Readers.Markdown` citation and citation-following-boundary cases: 8
  focused cases, now mapped by PHP tests for simple bare citation ids,
  digit-leading ids, citation followed by a footnote, inline link, reference
  link, shortcut reference link, implicit header link, and regular citation
  suffix text
- `Tests.Readers.Markdown` entities group: 3 focused cases, now mapped by PHP
  tests for named character references, decimal and hexadecimal numeric
  references, and entity decoding inside link titles
- `Tests.Readers.Markdown` inline-code attribute cases: 2 focused cases, now
  mapped by PHP tests for immediate attribute attachment and spaced
  attribute-looking text remaining literal
- `Tests.Readers.Markdown` autolink attribute cases: 2 focused cases, now
  mapped by PHP tests for immediate link attribute attachment and spaced
  attribute-looking text remaining literal
- `Tests.Readers.Markdown` bare URI autolink extension cases: all 41 upstream
  `bareLinkTests` cases now mapped by PHP tests, including raw HTML anchor
  pass-through, Greek and long encoded URLs, port/tilde/%20 variants, at-sign
  paths, DOI/Git/file/mailto schemes, and punctuation boundaries
- `Tests.Readers.Markdown` no-links-inside-link-label cases: 3 focused cases,
  now mapped by PHP tests for autolinks, inline links, and bare URI-looking
  text staying literal inside ordinary link labels
- `Tests.Readers.Markdown` raw HTML regression cases: 4 focused cases, now
  mapped by PHP tests for block-start `<del>test</del>` becoming raw-open,
  plain-content, raw-close blocks, invalid tags remaining literal paragraph
  text, technically invalid comments staying raw HTML, and the
  GitHub-flavored split `<`/`a>` case remaining two paragraphs
- `Tests.Readers.Markdown` raw email address cases: 1 focused GitHub-flavored
  Markdown case, now mapped by a PHP test that keeps `**@user**` as strong text
  rather than treating `@user` as link syntax
- `Tests.Readers.Markdown` emoji extension cases: 1 focused GitHub-flavored
  Markdown case, now mapped by a PHP test that converts `:smile:` and `:+1:`
  into emoji `Span` nodes with `class="emoji"` and `data-emoji` metadata
- Focused `# Lists` fancy-marker mappings from `test/testsuite.txt`: 4 local
  checks covering parenthesized decimal starts, lower/upper roman numerals,
  upper/lower alphabetic markers, and Pandoc autonumbering
- Markdown fixture files under `test/`: 1,096
- Office/archive fixtures (`docx`, `odt`, `epub`, `pptx`, `xlsx`, `rtf`): 309
- HTML/XML/JATS fixtures: 29
- `pandoc-lua-engine/test/**/*.hs` modules: 5
- `pandoc-lua-engine/test/` artifacts: 54
- `benchmark/` files: 1
- `data/` files: 247

The lane denominator is now 2,276 inspected upstream test/data/benchmark
files/artifacts: 1,974 under `test/`, all 54 tracked artifacts under
`pandoc-lua-engine/test/`, 247 files under `data/`, and one benchmark file
under `benchmark/`. This replaces the earlier 2,028 count that only included
the main test tree plus Lua-engine test artifacts.

## Runner Blocker

The full upstream suite was not executed in this run. Pandoc's `test-pandoc` and
`test-pandoc-lua-engine` suites must be built as Haskell Tasty executables from
a full checkout before they can run command, golden, HUnit, QuickCheck, and Lua
tests. `ghc` 9.10.3 and `cabal` 3.12.1.0 are now on PATH, while `stack` is not.
The current upstream cache is blob-filtered/no-checkout with mass working-tree
deletions, and a Cabal run would require hydrating the broad checkout plus
downloading and building Pandoc's dependency graph. The defensible denominator
used for this lane is therefore the cloned static `git ls-tree` inventory plus
targeted `git show` reads from the upstream object database, not upstream
runner parity.

## Native PHP Mapping Added

The current PHP slice maps a narrow part of `Tests.Readers.Markdown` semantics:

- ATX and setext headings, including all eight adjacent
  `Tests.Readers.Markdown` Header and Implicit header references cases:
  blank-leading ATX headings, bracketed heading text, closing ATX `#`
  normalization, setext headings, and implicit header references whose labels
  trim surrounding spaces. The existing `test/markdown-reader-more.txt`
  implicit header reference slice remains covered too: generated identifiers,
  duplicate generated-id suffixes, shortcut/collapsed/case-insensitive
  implicit links, explicit heading attributes, and explicit reference
  definitions overriding implicit heading targets.
- Paragraph joining, including the `test/markdown-reader-more.txt`
  backslash-newline slice where an unescaped trailing backslash before a line
  boundary becomes a `LineBreak` node instead of a soft line wrap.
- Pandoc title-block metadata from the start of
  `test/markdown-reader-more.txt`, cross-checked against
  `test/markdown-reader-more.native`: the leading `%` block is consumed before
  body parsing, a multiline title keeps a `SoftBreak` in metadata inlines,
  semicolon- and line-separated authors become four author entries, the empty
  date field stays absent, and the first body heading remains the first block.
- Bullet and ordered list blocks
- Inline emphasis with `*text*`
- Inline strong with `**text**`
- Inline underscore emphasis/strong from the `# Inline Markup` section of
  `test/testsuite.txt`, cross-checked against `test/testsuite.native`: `_is
  this_` maps to `Emph`, `__is this__` maps to `Strong`, and triple `***`/`___`
  delimiters map to Pandoc's `Strong [Emph [...]]` shape.
- Inline strikeout, superscript, and subscript from the same `# Inline Markup`
  slice: `~~This is *strikeout*.~~` maps to a `Strikeout` node containing
  nested emphasis, `a^bc^d`/`a^*hello*^`/`a^hello\ there^` map to
  superscripts with escaped spaces normalized to non-breaking spaces,
  `H~2~O`/`H~23~O`/`H~many\ of\ them~O` map to subscripts, and the upstream
  unescaped-space examples remain plain text rather than script spans.
- MultiMarkdown short script delimiters from `Tests.Readers.Markdown`: `O~2`
  and `x^2` forms become subscript/superscript nodes when followed by a space,
  newline, EOF, punctuation, or emphasis, while `y~*2*` and `y^*2*` keep the
  marker literal and parse only the following emphasis.
- Citation cases from `Tests.Readers.Markdown`: bare `@item1` and
  `@1657:huyghens` become author-in-text citation nodes, `@cita[^note]` leaves
  the following note reference attached, citation plus inline/reference/
  shortcut/implicit-header links keeps the real link separate, and
  `@cita [foo]` becomes one citation node with suffix text when `[foo]` is not
  otherwise a link.
- Inline code spans, including the `test/markdown-reader-more.txt` cases where
  a trailing backslash is literal inside code, embedded newlines normalize to
  spaces, longer backtick delimiters permit literal backticks, and a blank line
  terminates an otherwise unterminated code span into ordinary paragraphs.
- Inline code attributes from `Tests.Readers.Markdown`: immediate
  `{.javascript}` after a closing backtick run attaches class metadata to the
  Code node, while a space before `{.haskell .special x="7"}` keeps that
  attribute-looking text literal.
- Autolink attributes from `Tests.Readers.Markdown`: immediate
  `{#i .j .z k=v}` after `<http://foo.bar>` attaches id/class/key metadata to
  the Link node and replaces the default `uri` class, while a space before the
  attribute spec keeps it as literal text after the autolink.
- Bare URI autolinks from `Tests.Readers.Markdown` with the
  `Ext_autolink_bare_uris` extension: leading http(s) source URLs become
  `uri` links, trailing sentence punctuation stays outside the link, balanced
  parentheses remain part of the destination, uppercase schemes are accepted,
  and square brackets in bare paths are percent-encoded for the link
  destination while remaining visible in the label text.
- Link-label recursion boundaries from `Tests.Readers.Markdown`: autolinks,
  nested inline links, and bare URI-looking text inside an ordinary link label
  remain literal label text, while non-link inline markup such as emphasis still
  parses inside the label.
- Raw HTML regression boundaries from `Tests.Readers.Markdown`: a single-line
  `<del>test</del>` at block start becomes a raw HTML opening block, a plain
  content block, and a raw HTML closing block; malformed tags such as
  `</ div></.div>` stay paragraph text; invalid comments such as
  `<!-- pandoc --help -->` stay raw comment blocks; and GitHub-flavored split
  angle-bracket input remains separate literal paragraphs.
- Multilingual URI/e-mail links from `test/markdown-reader-more.txt`: Unicode
  URI autolinks keep the URL as both text and destination, inline links keep
  Unicode destination text plus title metadata, and Unicode e-mail autolinks
  become `mailto:` links.
- Numbered examples from `test/markdown-reader-more.txt`: `(@)` and
  `(@label)` markers become Pandoc Example-style ordered lists with
  two-parentheses delimiters, and inline `(@label)` references render as the
  visible example numbers.
- Indented code at the beginning of list items from
  `test/markdown-reader-more.txt`: list marker padding of five spaces starts a
  `code_block` child for bullet, decimal ordered, long-decimal ordered, and
  nested bullet items, while the four-space `-    no code` guard stays ordinary
  list-item prose. This matches Pandoc's native shape for the bounded fixture
  without changing ordinary list continuation text.
- Inline links with `[label](url)`
- Indented fenced code blocks from `test/command/indented-fences.md`, including
  Pandoc's opening-fence indentation stripping and both bare language and
  `{.class}` info strings
- Indented code blocks from the `# Code Blocks` section of `test/testsuite.txt`,
  cross-checked against `test/testsuite.native`: blank lines stay inside the
  block, a one-tab indent starts a block with no remaining indent, two leading
  tabs leave one expanded four-space indent in the code text, and literal
  backslashes are preserved rather than unescaped.
- Block quote cases from the `# Block Quotes` section of `test/testsuite.txt`,
  cross-checked against `test/testsuite.native`: simple quoted paragraphs,
  quote-contained indented code, ordered lists, nested block quotes, and the
  lazy-continuation case where `> 1.` stays inside a paragraph instead of
  starting a quote.
- Horizontal rules from the `# Code Blocks` and `# Lists` sections of
  `test/testsuite.txt`, cross-checked against `test/testsuite.native`: the
  underscore divider before the Lists section and the indented spaced-asterisk
  divider after `B. Williams` both become `HorizontalRule` nodes instead of
  paragraphs or bullet lists.
- Tight/loose list item shape and continuation paragraphs from the
  `# Lists` section of `test/testsuite.txt`, cross-checked against
  `test/testsuite.native`: blank lines between list items mark the list loose
  and turn item text into paragraph blocks, tab/space-indented continuation
  lines remain inside the current list item, multi-paragraph ordered items keep
  both paragraphs under the same item, and loose nested lists keep the parent
  item paragraph before the nested `BulletList`.
- Fancy ordered-list markers from the same `# Lists` section: `(2)` and `(3)`
  produce a decimal `OrderedList` starting at 2 with a two-parentheses
  delimiter, `iv.`/`v.` produce a lower-roman nested list starting at 4,
  `(A)`/`(B)` produce an upper-alpha nested list, `A.`/`I.`/`(6)`/`c)` produce
  the nested upper-alpha, upper-roman, decimal, and lower-alpha shape shown in
  `test/testsuite.native`, and `#.` produces Pandoc-style autonumbered lists.
- HTML task-list examples from `test/command/tasklist.md`: leading `[ ]` and
  `[x]` markers at the start of list items are stripped from the first
  paragraph and stored as `taskChecked` metadata, bullet lists whose items are
  all tasks receive `taskList` metadata, mixed task/plain lists intentionally do
  not receive the task-list class, ordered task items still render checkbox
  labels, and loose task items wrap only the first paragraph in the checkbox
  label while preserving later paragraphs as ordinary list content.
- Markdown and LaTeX writer examples from the same `test/command/tasklist.md`
  fixture are now mapped too: native Markdown output round-trips unchecked and
  checked task markers as `- [ ]` and `- [x]`, while native LaTeX output uses
  Pandoc's task labels `\item[$\square$]` and `\item[$\boxtimes$]` for loose
  task-list items.
- Markdown writer fancy ordered-list marker generation is now mapped from
  `Text.Pandoc.Writers.Markdown` and `Text.Pandoc.Shared`: native Markdown
  output emits `(2)`/`(3)`, `iv.`/`v.`, `A.`/`I.`, `(6)`, `c)`, default
  autonumbered decimal markers, and Pandoc-style short-marker padding for
  reviewer handoff lists.
- `test/Tests/Writers/Markdown.hs` inspected for its bounded note/reference
  location group: 20 HUnit cases are present in the module, including four
  note/reference-location cases. The PHP slice maps those four cases for
  `EndOfDocument`, `EndOfBlock`, `EndOfBlock` plus shortcut reference links,
  and `EndOfSection`, including setext headings, block quote prefixing,
  footnote definition placement, and the indented shortcut reference definition
  shape used by Pandoc's Markdown writer.
- Definition-list cases from `Tests.Readers.Markdown`: no blank space,
  blank space before the first definition, lazy continuation lines, indented
  continuation paragraphs, blank space before the second definition, first-line
  marker at column zero, a list inside a definition, and the definition list
  nested inside an HTML div.
- Definition-list cases from the `# Definition Lists` section of
  `test/testsuite.txt`, cross-checked against `test/testsuite.native`: terms can
  contain emphasis, indented continuation blocks can add additional paragraphs,
  eight-space-indented continuation lines become code blocks, continuation
  block quotes remain block quotes inside the definition body, alternate `~`
  markers are accepted after a blank term line, and an indented ordered list
  stays nested inside the `orange` definition body.
- HTML-block cases from the `# HTML Blocks` section of `test/testsuite.txt`,
  cross-checked against `test/testsuite.native`: one-line and nested `<div>`
  containers become `div` AST nodes, raw `<table>` blocks preserve their HTML
  boundary while interpreting Markdown in cell text, `<script>` bodies are kept
  raw without interpreting Markdown, HTML comments become raw HTML blocks with
  tabs expanded as Pandoc does, trailing spaces are trimmed from raw comments and
  `<hr>` tags, and tab-indented HTML remains an indented code block.
- The two-level nested HTML table case from
  `test/command/nested-table-to-asciidoc-6942.md` is now represented for the
  WordPress table boundary: balanced nested `<table>` blocks are parsed into
  native table AST nodes inside `table_cell` children, while simple non-nested
  raw HTML tables continue to use the existing raw HTML block path.
- The same upstream command fixture's full HTML document with a third-level
  nested table is now represented too. Pandoc's AsciiDoc writer warns and
  flattens the third level because that target format only supports two table
  levels; the PHP WordPress writer records a separate target policy and
  preserves the third-level nested table HTML for reviewer inspection.
- Structured HTML table imports from `test/tables/nordics.html5` are now
  represented for the WordPress table boundary: tables with explicit
  `caption`, `colgroup`, `thead`, or `tfoot` parse into table AST nodes,
  caption inline emphasis is preserved, col widths become `ColWidth`-style
  fractions, table head/body/foot sections remain distinct, row-header cells
  stay marked as headers in the AST, `<br>` becomes a hard `linebreak`, and
  `<sup>`/`<sub>` inline content maps to script nodes. Simple non-structured
  raw HTML tables still use the raw HTML path so legacy import-review markup
  is not over-normalized.
- Bounded non-table HTML-reader paragraph cases from `test/html-reader.html`
  and `test/html-reader.native` are now represented: standalone HTML
  paragraphs parse through the native inline path, `<br />` becomes a
  `linebreak` node matching Pandoc's `LineBreak`, `<q>` becomes a double
  `quoted` node, and q `cite` metadata is preserved as a Pandoc-style `span`
  child. The WordPress writer emits `<br/>` for the hard break and preserves
  citation metadata on the rendered inline span.
- Bounded HTML-reader table cases from `test/html-reader.html` and
  `test/html-reader.native` are now represented: a first all-`th` row without
  explicit `<thead>`/`<tbody>` tags is inferred as `table_head`, bodies whose
  rows begin with `<th>` cells record `rowHeadColumns=1`, omitted `</thead>`,
  `</tbody>`, and `</tfoot>` end tags are normalized into distinct
  head/body/foot AST sections, no-header HTML tables with only `colspan`
  metadata parse through the native table AST, headed tables preserve
  `colspan`/`rowspan`, Pandoc-style table, section, row, and cell attributes
  are captured with `data-*` keys normalized to native-style key-value
  attributes, the two upstream multiple-`tbody` tables stay as distinct
  `table_body` AST nodes instead of being flattened, and the second
  multiple-body table's direct `<p>` cell becomes a paragraph block child
  rather than inline text. Four body-local `TableBody` head-row cases now keep
  leading all-`th` rows in `headRows` metadata before ordinary body rows,
  covering explicit tbody plus foot/details, omitted tbody after a promoted
  top-level header, explicit tbody-only body heads, and empty-thead body heads.
  The plain `Tables without Headers` body-only, tbody-omitted, empty-head, and
  explicit body-plus-foot shapes now parse as native table AST nodes too when
  the cells are plain scalar text. The WordPress writer now emits body
  row-header cells as `<th>` instead of flattening them to `<td>`, renders
  body-local head rows inside `<tbody>` before ordinary body rows, preserves
  table identity attributes, emits section and row attrs on `<thead>`, `<tbody>`,
  `<tfoot>`, and `<tr>`, carries practical cell attributes such as `abbr`,
  `valign`, `data-*`, and non-alignment `style` values, emits one `<tbody>` per
  `table_body` node, preserves paragraph cells as `<td><p>...</p></td>`, and
  emits headerless plain import grids as core table blocks. The upstream
  empty-table section is
  mapped too: the empty `<tbody>` table and the fully empty `<table></table>`
  input are consumed and omitted, matching `test/html-reader.native`.
- Smart-punctuation cases from the `# Smart quotes, ellipses, dashes` section
  of `test/testsuite.txt`, cross-checked against `test/testsuite.native`: nested
  single and double quotes become `quoted` AST nodes, apostrophes inside words
  normalize to Pandoc's right single quotation mark, quoted code spans remain
  code, quoted one-line reference links resolve through collected definitions,
  `---` becomes an em dash, numeric `--` ranges become en dashes, and `...`
  becomes an ellipsis while preserving a fourth trailing dot.
- LaTeX cases from the `# LaTeX` section of `test/testsuite.txt`,
  cross-checked against `test/testsuite.native`: raw TeX citation commands
  become `raw_tex` inline nodes, `$...$` spans become inline `math` nodes,
  `$$...$$` spans become display `math` nodes, `$p$-Tree` keeps the trailing
  word text outside math, currency-like dollar examples and escaped dollars stay
  non-math text, and `\begin{tabular}` through the matching `\end{tabular}`
  becomes a raw TeX block.
- The `Tests.Readers.Markdown` apostrophe-after-math regression is mapped:
  `$x$'s` parses as inline math followed by a right apostrophe text node, and
  the trailing possessive apostrophe in `systems' condition` normalizes to
  Pandoc's right single quotation mark.
- The `Tests.Readers.Markdown` unclosed double-quote smart-punctuation
  regression is mapped too: `**this should "be bold**` remains a `Strong` node
  and the unmatched opening quote is normalized to a left double quote instead
  of staying straight source text.
- Special Characters cases from `test/testsuite.txt`, cross-checked against
  `test/testsuite.native`: Unicode list item text stays literal, `AT&amp;T`
  decodes to `AT&T` in the inline text node, literal `&`, `<`, and `>` examples
  stay text rather than HTML, Pandoc's punctuation backslash escapes collapse to
  their literal characters, and the dashed divider remains a `HorizontalRule`.
- Links cases from `test/testsuite.txt`, cross-checked against
  `test/testsuite.native`: explicit links support empty destinations,
  double/single-quoted titles, quote-containing titles, backslash escapes in
  link text, mailto URLs, and pointy-brace destinations; reference links support
  full, collapsed, and shortcut forms, nested brackets in link text, and
  definitions indented by up to three spaces while a four-space definition
  remains an indented code block; ampersands remain intact in URLs, link text,
  and titles; URI and email autolinks work in paragraphs, lists, and block
  quotes; autolinks do not fire inside code spans or indented code blocks.
- Images cases from `test/testsuite.txt`, cross-checked against
  `test/testsuite.native`: `![lalune][]` resolves through an up-to-three-space
  indented reference definition into a standalone `figure` block with image alt,
  source, title, and caption metadata, while `![movie](movie.jpg)` remains an
  inline `image` node inside its paragraph.
- Footnotes cases from `test/testsuite.txt`, cross-checked against
  `test/testsuite.native`: `[^1]` and `[^longnote]` resolve through collected
  footnote definitions into inline `note` nodes, invalid labels containing
  spaces remain literal text, inline notes parse nested emphasis, links, code
  spans containing `]`, and bracketed plain text, notes inside block quotes and
  list items stay attached to their containing block, and multi-block
  definitions preserve paragraphs plus indented code. The three
  `Tests.Readers.Markdown` footnote edge cases are also mapped: whitespace-only
  indented separators terminate a note before flush-left text, indented text
  after a separator remains in the note, and recursive `[^1]` references inside
  their own note body remain literal text.
- All 11 pipe-table cases from `test/pipe-tables.txt`, cross-checked against
  `test/pipe-tables.native`: default and aligned caption/no-caption tables keep
  caption text plus right/left/default/center alignment metadata, headerless
  tables omit the table head, the one-dash `|:-:|` header-less one-column form
  parses as centered, side-less rows split correctly without leading or
  trailing pipes, indented left-column values trim to their intended cells,
  one-column and no-body tables preserve their header/body shape, long
  delimiter rows produce relative column-width metadata, and tricky cells keep
  escaped `\|` pipes as text while `foo` plus a code span containing `bar|baz`
  remains one cell.
- All seven gridless table cases from `test/tables.markdown`, cross-checked
  against `test/tables.native`: captioned and uncaptioned simple tables infer
  right/left/center/default alignment from header spacing, the
  two-space-indented simple-table fixture still parses as a table rather than an
  indented code block, no-column-header simple tables use opening and closing
  delimiter rows with alignment inferred from the first body row, multiline
  header/body rows merge wrapped physical lines into `softbreak`-bearing cell
  content, multiline tables preserve Pandoc's 80-column `ColWidth` fractions,
  multiline captions can span continuation lines, and the no-header multiline
  table preserves the upstream final-column `AlignDefault` distinction while
  the headed multiline examples infer `AlignLeft`.
- Parsed table caption inline content is now mapped for pipe and simple tables:
  the AST keeps the legacy plain caption string but also stores parsed caption
  inline nodes, matching Pandoc's native `Caption ... [Plain [...]]` block
  shape observed in `test/tables.native`, `test/pipe-tables.native`, and the
  short-caption command fixture. WordPress figcaptions now render emphasis,
  links with titles, code spans, and smart punctuation instead of escaping
  Markdown markup as literal caption text.
- The optional short-caption shape from `test/command/short-caption.md` is now
  mapped for a narrow LaTeX table environment slice: `\caption[short
  caption]{long caption}` keeps the long caption as visible table caption
  content and stores the short caption separately on the table AST. The
  WordPress writer preserves that short label as `data-pandoc-short-caption`
  on the table figure while rendering the long caption in the figcaption.
- The DocBook structural-cell shapes from `test/command/table-with-cell-align.md`
  and `test/command/table-with-column-span.md` are now mapped for a narrow
  `informaltable` slice: the native PHP reader uses DOM parsing for bounded
  DocBook table fragments, keeps `colspec` widths, per-cell alignment,
  `namest`/`nameend` column spans, and strong emphasis inside cells. The
  WordPress writer emits core table HTML with escaped `style` and `colspan`
  attributes, so structural cells survive import without shelling out to Pandoc.
- The row-span table-section shape from
  `test/command/rst-writer-gridtable-if-rowspans.md` is now mapped through the
  same bounded table AST: DocBook `morerows="1"` becomes `rowspan=2`,
  `thead`/`tbody`/`tfoot` become `table_head`/`table_body`/`table_foot`, and
  the WordPress writer emits `<thead>`, `<tbody>`, `<tfoot>`, and `rowspan`
  attributes without shelling out to Pandoc.
- The inline style shape from `test/html-reader.html` is now mapped for a
  narrow HTML reader slice: `font-variant: small-caps` spans become
  `small_caps` AST nodes, `<u>` and `<ins>` become `underline`, and `<s>`,
  `<strike>`, and `<del>` become `strikeout`. The WordPress writer renders
  those as safe inline small-caps, underline, and deletion markup without
  invoking Pandoc.
- The standalone pre/code shape from the `test/html-reader.html` Code Blocks
  section is now mapped for a narrow HTML reader slice: `<pre><code>` becomes a
  native `code_block`, internal blank lines and indentation are preserved, the
  closing-tag newline is stripped like Pandoc's native output, and literal
  backslash escapes stay literal instead of being treated as Markdown escapes.
  WordPress output renders the node as a core code block and normalizes
  `language-*` classes for imported legacy snippets.
- The blockquote container shape from the `test/html-reader.html` Block Quotes
  section is now mapped for a narrow HTML reader slice: balanced
  `<blockquote>` blocks become native `blockquote` nodes, nested quotes stay
  nested, paragraph/code/list children stay as block children, ordered lists
  inside quotes stay on the native list path, and HTML text nodes are not
  passed through Markdown smart punctuation.
- The top-level list shape from the `test/html-reader.html` Lists section is
  now mapped for a narrow HTML reader slice: balanced `<ul>` and `<ol>` blocks
  become native list nodes, tight `<li>text</li>` items stay inline/plain-like,
  `<li><p>text</p></li>` items stay paragraph-like, multiple paragraphs remain
  attached to one ordered item, and `type`, class, `list-style`, and
  `list-style-type` metadata preserve ordered-list styles while the upstream
  bare `style="lower-roman"` case remains default. The WordPress writer emits
  safe `type` attributes for roman/alpha ordered lists.
- The next HTML-reader list shape from `test/html-reader.html` is now mapped
  for the `Nested`, `Tabs and spaces`, and `Fancy list markers` sections:
  top-level HTML headings become native heading nodes with generated or
  preserved Pandoc-style identifiers, nested-list-only items remain tight
  `Plain`-shaped list items, paragraph-wrapped items remain paragraph-shaped,
  and decimal/lower-roman/upper-alpha/upper-roman/lower-alpha ordered-list
  styles and starts survive through nested list chains. The WordPress writer
  emits heading anchors and nested ordered-list `start`/`type` attributes
  without invoking Pandoc.
- The HTML-reader definition-list shape from `test/html-reader.html` is now
  mapped for the `Definition` section: balanced `<dl>` blocks become native
  `definition_list` nodes, consecutive `<dt>` terms are joined with a
  Pandoc-style `linebreak`, multiple `<dd>` bodies stay attached to the same
  term, and the WordPress writer emits glossary/FAQ `<dl>` markup without
  invoking Pandoc.
- The HTML-reader Smart quotes, ellipses, dashes shape from
  `test/html-reader.html` is now mapped for a narrow HTML reader slice: bare
  self-closing `<hr />` separators become `horizontal_rule` nodes, the section
  heading gets the Pandoc-style identifier, straight source quotes and
  apostrophes remain literal text, quoted HTML code/link boundaries stay
  semantic, and dash/ellipsis strings are not converted through Markdown smart
  punctuation.
- The HTML-reader LaTeX shape from `test/html-reader.html` is now mapped for a
  narrow HTML reader slice: TeX commands, dollar-delimited math-looking text,
  and `\begin{tabular}` source in HTML text nodes stay literal text, while only
  explicit HTML inline tags such as `<code>` and `<em>` become semantic inline
  nodes. WordPress output preserves the source text without creating math spans
  or raw-TeX spans on the HTML-reader path.
- The HTML-reader Special Characters shape from `test/html-reader.html` is now
  mapped for a narrow HTML reader slice: Unicode list text survives unchanged,
  HTML entities decode once, comparison characters stay ordinary text,
  Markdown-sensitive punctuation tokens remain literal, and the final
  self-closing `<hr />` remains a `horizontal_rule` node.
- The HTML-reader Links shape from `test/html-reader.html` is now mapped for a
  narrow HTML reader slice: explicit anchors preserve href/title metadata,
  empty hrefs stay empty, reference-looking text stays literal, code contexts
  do not autolink, and mixed bare-text-plus-block-tag flow is split before
  block parsing so it stays on the native HTML-reader path.
- The HTML-reader Images shape from `test/html-reader.html` is now mapped for a
  narrow HTML reader slice: `<img>` becomes an `image` inline node with
  `src`/`title`/`alt` metadata, standalone image-only paragraphs retain
  Pandoc's paragraph-image AST shape, and inline image paragraphs keep the
  image between ordinary text nodes.
- The HTML-reader Footnotes shape from `test/html-reader.html` is now mapped
  for a narrow HTML reader slice: the 12-line upstream section and 249-line
  native AST slice preserve four footnote-style anchors as ordinary `Link`
  nodes, keep the invalid space-containing footnote marker as literal text,
  leave footnote-body paragraphs and the pre/code continuation as normal
  paragraph/code blocks, and move leading/trailing whitespace around emphasis
  wrappers outside the emphasis node like Pandoc's native output.
- The early full-document HTML-reader shape from `test/html-reader.html` is now
  mapped for a narrow HTML reader slice: complete `<html>` documents preserve
  title and generator metadata on the document node, body blocks are parsed
  through the native HTML-reader path, source heading classes survive on
  generated heading ids, heading links/emphasis remain inline nodes, and
  HTML-reader paragraphs keep list-marker-looking text literal instead of being
  re-parsed as Markdown lists.
- The post-grid Markdown reader shape from `test/markdown-reader-more.txt` is
  now mapped for a narrow link slice: upstream lines 315-335 and native AST
  lines 1493-1548 cover four entity-decoded link/title cases plus three
  parenthesized URL cases. Inline/reference link destinations decode
  `&uuml;`, titles decode `&ouml;`, URI/e-mail autolinks decode both href and
  visible label text, balanced parentheses stay inside inline URLs, escaped
  closing parentheses survive, and nested parenthesized reference destinations
  remain intact.
- The next post-grid Markdown reader shape from `test/markdown-reader-more.txt`
  is now mapped for a narrow reference-link edge slice: upstream lines 337-358
  and native AST lines 1549-1649 cover backslash/TeX content in link labels,
  unresolved reference-link fallback text, a shortcut reference followed by a
  citation marker, and empty reference definitions. The native PHP reader keeps
  escaped label punctuation and a bare `\a` TeX command inside the link label,
  falls back to bracketed emphasized text when the reference is undefined,
  preserves the `[@mapreduce]` marker as a citation inline adjacent to the
  resolved `Google` link, and leaves the paragraph after `[foo2]:` intact
  before emitting the later empty-destination shortcut link.
- The final `test/markdown-reader-more.txt` tail slice is now mapped too:
  upstream lines 360-366 and native AST lines 1650-1715 cover the wrapping
  regression and bracketed-span extension. The native PHP reader now generates
  Pandoc's apostrophe-free heading id for `shouldn't`, keeps the long bullet
  item as one tight list item instead of treating `2015.` as an ordered marker,
  and builds a `span` AST node preserving id, class, and key/value attributes
  around parsed emphasis and link children. The WordPress writer emits safe
  span id/class/data/title attrs for the fixture's migration-review marker.
- The mid-fixture `test/markdown-reader-more.txt` reference/quote/list slice is
  now mapped too: reference labels normalize case for shortcut lookup, curly
  quote code points stay literal text rather than becoming Markdown smart quote
  nodes, and a one-space-indented lower-alpha list after a decimal list is kept
  as a sibling list. The nested-list guard still keeps column-zero initials such
  as `B. Williams` as paragraphs and preserves existing two-column nested list
  behavior from the indented-code-at-beginning-of-list slice.
- The `Tests.Readers.Markdown` inline-code attribute slice is now mapped too:
  immediate inline code attributes become AST id/class/key-value metadata, and
  the spaced attribute-looking form stays literal text instead of being parsed
  or smart-quoted. The WordPress writer emits safe inline `<code>` attributes
  for reviewer/source tokens.
- The `Tests.Readers.Markdown` autolink attribute slice is now mapped too:
  immediate autolink attributes become AST id/class/key-value metadata on the
  Link node, and the spaced attribute-looking form stays literal text. The
  WordPress writer emits safe link id/class/data/title attrs for reviewer
  source links while keeping ordinary URI/e-mail autolinks visually unchanged.
- The `Tests.Readers.Markdown` bare URI autolink extension slice is now mapped
  against all 41 upstream `bareLinkTests` cases: leading http(s) URLs, raw HTML
  anchor pass-through without nested autolinking, query URLs followed by
  sentence punctuation, parenthesized URLs, uppercase schemes, Greek URLs,
  balanced parenthesized paths, bracketed and braced destinations with safe
  percent-encoding, `doi:`, `git://`, `file://`, and `mailto:` source URIs, the
  `Use http:` non-link guard, long encoded HTTP URLs, port/tilde/%20 variants,
  at-sign archive paths, semicolon/query/fragment/plus URL shapes, repeated
  plain HTTP inputs, and both trailing-hyphen forms.
- The `Tests.Readers.Markdown` no-links-inside-link-label slice is now mapped
  too: `[<https://example.org>](url)`, `[[a](url2)](url)`, and
  `[https://example.org(](url)` each produce one outer Link whose label content
  stays literal text. The helper used for link and image labels keeps recursive
  link parsing disabled while preserving non-link inline markup such as
  emphasis.
- The adjacent `Tests.Readers.Markdown` raw email, emoji, and GitHub wiki-link
  extension slice is now mapped too: `**@user**` stays a `Strong` node with
  literal `@user` text, GitHub-flavored `:smile: and :+1:` becomes two emoji
  `Span` nodes with `class="emoji"`, `data-emoji` metadata, and the expected
  glyph text, and the six `Github wiki links` cases become classed Link nodes.
  The mapped wiki cases cover bare URL links, title-before-pipe links,
  non-URL page targets, page names with spaces, page names containing a literal
  `]`, and labels containing backticks/asterisks that stay literal text.
  Unknown emoji aliases remain literal text.
- The adjacent `Tests.Readers.Markdown` MultiMarkdown short sub/superscript
  slice is now mapped too: the 14-case group covers the regular delimited
  `H~2~` and `x^3^` cases, short digit scripts before whitespace/newline/EOF,
  punctuation, and emphasis, and the no-nesting guards where `y~*2*` and
  `y^*2*` remain literal marker text followed by emphasis.
- The adjacent `Tests.Readers.Markdown` citation and
  citation-following-boundary slice is now mapped too: the 8-case group covers
  simple author-in-text ids, digit-leading ids, footnote/link boundaries after
  `@cita`, reference and shortcut reference disambiguation, implicit header
  links, and the regular citation suffix case. Bare citation parsing is
  deliberately kept out of nested emphasis so GitHub-flavored `**@user**`
  remains strong literal text in the earlier raw-email slice.
- The adjacent `Tests.Readers.Markdown` figures slice is now mapped for the
  `latex placement` case: `![caption](img.jpg){latex-placement="htbp"
  alt="alt text"}` becomes a standalone Figure with `latex-placement` metadata,
  while the image's alt text is overridden to `alt text` and the visible caption
  remains `caption`.
- The adjacent `Tests.Readers.Markdown` emph/strong delimiter slice is now
  mapped for two upstream cases from the `emph and strong` group:
  `*x **xx** x*` and `***a**b **c**d*`. The native PHP reader keeps the outer
  emphasis open across inner strong delimiter runs, yielding `Emph` nodes with
  nested `Strong` children instead of prematurely closing at the first `**`
  run.
- The same upstream `emph and strong` group is now mapped for the alternating
  softbreak case too: `*xxx* ***xxx*** xxx` followed by another physical
  paragraph line keeps the newline as a `SoftBreak` inline node between the two
  emphasized runs. Paragraph `text` attributes remain space-normalized for
  existing callers, while the AST and WordPress output preserve the line
  boundary. The full upstream group has four cases; three are now explicitly
  mapped in this focused slice.
- The adjacent `Tests.Readers.Markdown` smart-punctuation unclosed double quote
  case is now mapped too: the native PHP reader keeps
  `**this should "be bold**` as strong content and converts the unmatched
  opening quote to a left double quote, matching Pandoc's smart reader.
- The same upstream `Tests.Readers.Markdown` smart-punctuation group is now
  fully mapped for its seven named cases across upstream lines 362-383. The
  latest slice covers quote-before-ellipsis (`'...hi'`), apostrophe before
  emphasis (`D'oh! A l'*aide*!`), and the French guillemet case
  (`l'«impossibilité...`). Smart apostrophe boundaries now use Unicode
  letter/number checks, while issue #11613 inline-note quote delimiters inside
  `^[...]` notes still stay inside the note instead of closing the surrounding
  single or double quoted span.
- The adjacent `Tests.Readers.Markdown` list issue #1154 case is now mapped:
  a list item beginning with `<div>` keeps the following div, single-line
  `<button>...</button>` raw HTML container, and second div as block children
  of the same list item. This prevents the native PHP reader from splitting a
  migration review list into a stray paragraph plus top-level HTML blocks.
- The adjacent `Tests.Readers.Markdown` `lhs` extension case is now mapped for
  the bounded bird-track shape: when `MarkdownReader` is constructed with
  `literateHaskell => true`, `> ` lines become Haskell literate code blocks and
  `< ` inverse-bird lines become Haskell code blocks, while the default reader
  still treats `> ` as Markdown block quotes.
- The upstream `test/lhs-test.markdown+lhs` fixture boundary is now mapped too:
  column-zero bird-track lines become `["haskell","literate"]` code blocks,
  the indented ordinary code block remains unclassed code, and the fixture's
  one-space-indented ` > foo bar` line remains a block quote when
  `literateHaskell => true`.
- The adjacent `Tests.Readers.Markdown` unbalanced-bracket and backslash-escape
  cases are now explicitly mapped: a long unmatched bracket run remains literal
  paragraph text, inline-link `\)` becomes a literal `)` inside the URL,
  escaped quotes inside inline titles survive, and escaped punctuation in
  reference-link URLs/titles is unescaped through the same native path. The
  reader now narrows Markdown backslash escapes to Pandoc/CommonMark-style
  ASCII punctuation instead of treating any non-alphanumeric byte as escapable.
- The adjacent `Tests.Readers.Markdown` intraword underscore and raw-LaTeX URL
  guard cases are now explicitly mapped from upstream lines 228-233:
  `_foot_ball_` becomes a single `Emph` node whose text is `foot_ball`, while a
  bare `\begin` line stays paragraph text instead of becoming a raw TeX inline.
  The native reader now names both guard paths directly: intraword underscores
  cannot close or open a delimiter run, and bare LaTeX environment commands
  require an argument before they are treated as raw TeX.
- The adjacent `Tests.Readers.Markdown` entities group is now explicitly mapped
  from upstream lines 515-523: `&lang; &ouml;` decodes to text, decimal and
  lowercase/uppercase hexadecimal numeric references decode to `,DD`, and
  entity references inside link titles are decoded before WordPress escaping.

The WordPress writer emits block comments and escaped HTML for the same AST
without calling the upstream `pandoc` binary.

Focused local verification on 2026-05-23: the pandoc-local test file passed
with 181 behavior tests, 2,043 assertions, and 0 failures after this slice.
Root verification for this batch was started after the required duplicate-root
gate returned clear. `php tools/run-tests.php` exited 1 with 192 test files,
20,864 assertions, and 1 failure in `lanes/quadrable/tests/QuadbStoreTest.php`
(`native quadb store imports and merges proof-backed heads across reopen`;
expected `RuntimeException` was not thrown). Pandoc tests passed inside that
root run.

Focused local verification on 2026-05-23 after the raw-HTML-in-list slice:
`php -l` passed for `MarkdownReader.php`, `WordPressBlockWriter.php`, and
`MarkdownReaderTest.php`; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,056
assertions, and 0 failures. The first required root verification gate found
active root PID `1534970` owned by `claude` (`php tools/run-tests.php`), so no
duplicate root run was started then. A later exact gate was clear, and
`php tools/run-tests.php` passed 196 test files, 21,368 assertions, and 0
failures.

Focused local verification on 2026-05-23 after the literate-Haskell slice:
`php -l` passed for `MarkdownReader.php`, `MarkdownReaderTest.php`, and
`examples/wordpress-literate-haskell.php`; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,073
assertions, and 0 failures.

Required root verification gate on 2026-05-23 found an active exact root
harness, so this lane did not start a duplicate run: PID `1766434`, owner
`claude`, PPID `1604183`, elapsed `00:41`, command `php tools/run-tests.php`.
Root result remains pending for the supervisor/integrator.

Focused local verification on 2026-05-23 after the lhs-test boundary slice:
`php -l` passed for `MarkdownReader.php`, `MarkdownReaderTest.php`, and
`examples/wordpress-literate-haskell.php`; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,083
assertions, and 0 failures.

Required root verification on 2026-05-23: the duplicate-root gate returned
clear, so `php tools/run-tests.php` was run once and passed 196 test files,
21,585 assertions, and 0 failures.

Focused local verification on 2026-05-23 after the unbalanced-bracket and
backslash-escape slice: `php -l` passed for `MarkdownReader.php` and
`MarkdownReaderTest.php`; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,097
assertions, and 0 failures.

Required root verification on 2026-05-23 after the same slice: the duplicate
root gate returned clear, so `php tools/run-tests.php` was run once and passed
198 test files, 21,767 assertions, and 0 failures.

Focused local verification on 2026-05-23 after the intraword-underscore and
raw-LaTeX URL guard slice: `php -l` passed for `MarkdownReader.php` and
`MarkdownReaderTest.php`; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,105
assertions, and 0 failures.

Required root verification on 2026-05-23 after the same slice was left pending:
the duplicate-root gate returned active root harness PID `2089975` owned by
`claude` (`php tools/run-tests.php`, parent `2009714`, elapsed `00:10`, state
`Rs`), so this lane did not start a duplicate root run.

Focused local verification on 2026-05-23 after the entity-reference slice:
`php -l` passed for `MarkdownReader.php` and `MarkdownReaderTest.php`;
`php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed
1 test file, 2,114 assertions, and 0 failures.

Required root verification on 2026-05-23 after the entity-reference slice:
the duplicate-root gate returned clear, so `php tools/run-tests.php` was run
once. It exited 1 with 198 test files, 21,846 assertions, and 45 failures.
Pandoc tests passed inside that root run; the failures were outside this lane,
concentrated in `lanes/lightningcss/tests/TransitionPrefixerTest.php` because
`PortLibs\LightningCSS\TransitionPrefixer::rewriteDisplayFlexPrefixEntries()`
is missing, plus one `lanes/syncthing/tests/FileInfoScannerTest.php` scanner
checkpoint condition failure.

Focused local verification on 2026-05-23 after the task-list slice: `php -l`
passed for `MarkdownReader.php`, `WordPressBlockWriter.php`, and
`MarkdownReaderTest.php`; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,139
assertions, and 0 failures.

Required root verification on 2026-05-23 after the task-list slice was left
pending: the duplicate-root gate returned active root harness PID `2399793`
owned by `claude` (`php tools/run-tests.php`, parent `2264530`, elapsed
`00:19`, state `Rs`), so this lane did not start a duplicate root run.

Focused local verification on 2026-05-23 after the title-block metadata slice:
`php -l` passed for `MarkdownReader.php` and `MarkdownReaderTest.php`;
`php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed
1 test file, 2,151 assertions, and 0 failures. The focused file now contains
191 behavior tests.

Required root verification on 2026-05-23 after the title-block metadata slice
was left pending: the duplicate-root gate returned active root harness PID
`2479573` owned by `claude` (`php tools/run-tests.php`, parent `2479572`,
elapsed `00:14`, state `R`), so this lane did not start a duplicate root run.

Focused local verification on 2026-05-23 after the nested-dollar inline math
slice: `php -l` passed for `MarkdownReader.php` and `MarkdownReaderTest.php`;
`php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed
1 test file, 2,176 assertions, and 0 failures. The focused file now contains
194 behavior tests.

Required root verification on 2026-05-23 after the nested-dollar inline math
slice was left pending: the duplicate-root gate returned active root harness
PID `2613382` owned by `claude` (`php tools/run-tests.php`, parent `2613380`,
elapsed `00:19`, state `R`), so this lane did not start a duplicate root run.

Focused local verification on 2026-05-23 after the raw-HTML-before-header and
commented-list slice: `php -l` passed for `MarkdownReader.php` and
`MarkdownReaderTest.php`; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,199
assertions, and 0 failures. The focused file now contains 196 behavior tests.

Required root verification on 2026-05-23 after the raw-HTML-before-header and
commented-list slice: the duplicate-root gate returned clear, so
`php tools/run-tests.php` was run once. It exited 1 with 202 test files,
23,114 assertions, and 2 failures. Pandoc tests passed inside that root run;
the visible failure was outside this lane in
`lanes/readability/tests/ArticleExtractorTest.php`, where the
`firefox-nightly-blog` byline fixture expected `Mike Conley` and got `NULL`.

Focused local verification on 2026-05-23 after the task-list writer slice:
`php -l` passed for `MarkdownReader.php`, `WordPressBlockWriter.php`,
`MarkdownWriter.php`, `LatexWriter.php`, and `MarkdownReaderTest.php`;
`php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed
1 test file, 2,234 assertions, and 0 failures. The focused file now contains
198 behavior tests.

Required root verification on 2026-05-23 after the task-list writer slice: the
duplicate-root gate returned clear before the final root run, and
`php tools/run-tests.php` passed 204 test files, 23,553 assertions, and
0 failures.

Focused local verification on 2026-05-23 after the Markdown writer
note/reference-location slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and `examples/wordpress-markdown-review-handoff.php`;
`php lanes/pandoc/examples/wordpress-markdown-review-handoff.php` emitted the
expected setext-heading handoff Markdown with block-local footnotes and
shortcut reference definitions; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,242
assertions, and 0 failures. The focused file now contains 200 behavior tests.

Required root verification on 2026-05-23 after the Markdown writer
note/reference-location slice eventually ran after earlier active-root lock
races and passed: `php tools/run-tests.php` reported 209 test files, 24,067
assertions, and 0 failures.

`test/Tests/Writers/Markdown.hs` was inspected again for the bounded
`shortcutLinkRefsTests` group. The PHP Markdown writer now maps all 12 cases:
shortcutable simple links, adjacent links, space-plus-link boundaries,
repeated labels with numbered references, bracket-following text escaping,
raw markdown inline boundaries with and without a leading space, and citation
boundaries with and without a leading space. Consecutive reference definitions
are emitted as adjacent definition lines to match Pandoc's `refsToMarkdown`
shape instead of becoming separate paragraphs.

Focused local verification on 2026-05-23 after the Markdown writer shortcut
reference-link boundary slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and `examples/wordpress-markdown-review-handoff.php`;
`php lanes/pandoc/examples/wordpress-markdown-review-handoff.php` emitted a
handoff packet with duplicate adjacent source links, numbered reference labels,
escaped bracketed reviewer text, and citation-adjacent references; `php
tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test
file, 2,254 assertions, and 0 failures. The focused file now contains 201
behavior tests.

Required root verification on 2026-05-23 after the Markdown writer shortcut
reference-link boundary slice was left pending: the duplicate-root gate
returned active exact-root PID `2994382` owned by `claude` (`php
tools/run-tests.php`, parent `2994380`, elapsed `00:07`, state `R`), so this
lane did not start a duplicate root run.

`test/Tests/Writers/Markdown.hs` was inspected again for its three top-level
tests. The PHP Markdown writer now maps all three: an ordered list with a
second paragraph followed by an indented code block emits Pandoc's raw HTML
`<!-- -->` separator before the code block, tight nested bullet lists remain
compact (`- foo` followed by an indented `- bar` without a blank loose-list
gap), and delimiter-adjacent whitespace is moved outside nested strong/emphasis
markers for the upstream `#10696` case.

Focused local verification on 2026-05-23 after the Markdown writer top-level
slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and `examples/wordpress-markdown-review-handoff.php`;
`php lanes/pandoc/examples/wordpress-markdown-review-handoff.php` emitted the
expected reviewer handoff packet; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,257
assertions, and 0 failures. The focused file now contains 202 behavior tests.

Required root verification on 2026-05-23 after the Markdown writer top-level
slice was left pending: the duplicate-root gate returned active exact-root PID
`3087737` owned by `claude` (`php tools/run-tests.php`, parent `3087673`,
elapsed `00:18`, state `R`), so this lane did not start a duplicate root run.

`src/Text/Pandoc/Writers/Markdown/Inline.hs` was inspected for the bounded
`escapeText` and `getReference` paths. The PHP Markdown writer now maps 21
focused checks from that source boundary: ATX-looking leading `#` text,
smart dash and ellipsis escapes, fenced-div colon-run escapes, image and
strikeout delimiter guards, intraword underscore passthrough, Markdown
formatting/math/table punctuation escapes, angle bracket escapes under
Pandoc's all-symbols-escapable extension, character-reference ampersand
escaping, raw-TeX backslash escaping, generated numeric labels for
bracket-containing reference labels, same-target reference definition reuse,
and numbered disambiguation for duplicate human labels.

This also normalizes the `Tests.Writers.Markdown` leaf-test inventory count:
the upstream module has 19 behavior tests, not 20 (three top-level cases,
four note/reference-location cases, and 12 shortcut-reference cases).

Focused local verification on 2026-05-23 after the Markdown writer inline
escaping/reference-definition slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and `examples/wordpress-markdown-review-handoff.php`;
`php lanes/pandoc/examples/wordpress-markdown-review-handoff.php` emitted the
reviewer handoff packet with block-local notes plus literal audit tokens
escaped for Pandoc-compatible Markdown; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,258
assertions, and 0 failures. The focused file now contains 203 behavior tests.

Required root verification on 2026-05-23 after the Markdown writer inline
escaping/reference-definition slice first found active exact-root PID `3110747`
owned by `claude` (`php tools/run-tests.php`, parent `3096285`, elapsed
`00:18`, state `Rs`), so this lane did not start a duplicate root run at that
point. A later duplicate-root gate was clear, so this lane ran
`php tools/run-tests.php` once. It exited red with 214 test files, 24,638
assertions, and 1 failure. Pandoc tests passed inside the root run, but the
retained tool-output chunks did not include the failing `FAIL ...` line, so the
failing non-pandoc test name is not known from this lane run. A post-run gate
found active exact-root PID `3168962` owned by `claude` (`php
tools/run-tests.php`, parent `3093040`, elapsed `00:13`, state `Rs`), so no
second root run was started. A final duplicate-root sample still found active
exact-root PID `3174787` owned by `claude` (`php tools/run-tests.php`, parent
`3105286`, elapsed `00:27`, state `Rs`). After the exact-root gate cleared
again, a final filtered root capture ran `php tools/run-tests.php` and passed
214 test files, 24,677 assertions, and 0 failures.

`src/Text/Pandoc/Writers/Markdown/Inline.hs` was inspected again for the
bounded `Link` emission branch. The PHP Markdown writer now maps eight focused
checks from that source boundary: URI autolinks render as `<url>` when the
label matches the target, `mailto:` targets render as `<address>` without the
scheme, autolinks bypass reference-link mode, inline links preserve quoted
titles, inline links append id/class/key-value attributes with Pandoc's
`attrsToMarkdown` shape, reference definitions append link attributes, targets
that differ only by attributes get distinct reference labels, and repeated
attributed targets reuse the same reference definition.

Focused local verification on 2026-05-23 after the Markdown writer
URI/e-mail autolink and link-attribute slice: `php -l` passed for
`MarkdownWriter.php`, `MarkdownReaderTest.php`, and
`examples/wordpress-markdown-review-handoff.php`; `php
lanes/pandoc/examples/wordpress-markdown-review-handoff.php` emitted angle
bracket URI/e-mail autolinks plus an attributed reviewer packet reference
definition; `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
passed 1 test file, 2,260 assertions, and 0 failures. The focused file now
contains 205 behavior tests.

Required root verification on 2026-05-23 after the Markdown writer URI/e-mail
autolink and link-attribute slice: the duplicate-root gate returned clear, so
`php tools/run-tests.php` was run once. It passed 216 test files, 24,927
assertions, and 0 failures.

`src/Text/Pandoc/Writers/Markdown/Inline.hs` was inspected again for the
bounded `Image` emission branch, and `src/Text/Pandoc/Writers/Markdown.hs` was
checked for the single-image `Figure` implicit-figure path. The PHP Markdown
writer now maps five focused checks from that boundary: a testsuite-style
single-image figure writes `![lalune](lalune.jpg "Voyage dans la Lune")`, an
inline movie image writes inside paragraph text, an image whose alt text equals
its URI target writes an empty label to avoid `!<uri>` autolink output, image
titles/id/classes/key-value attrs serialize with Pandoc's Markdown attribute
shape, and distinct stored alt text is preserved as an `alt="..."` image
attribute when the visible caption differs.

Focused local verification on 2026-05-23 after the Markdown writer image
emission slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and `examples/wordpress-markdown-review-handoff.php`;
`php lanes/pandoc/examples/wordpress-markdown-review-handoff.php` emitted a
reference-style reviewer image definition carrying id/class/alt/data-source
metadata; `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
passed 1 test file, 2,262 assertions, and 0 failures. The focused file now
contains 207 behavior tests.

Required root verification on 2026-05-23 after the Markdown writer image
emission slice: the duplicate-root gate returned clear, so
`php tools/run-tests.php` was run once. It passed 223 test files, 25,545
assertions, and 0 failures.

`src/Text/Pandoc/Writers/Markdown/Inline.hs` was inspected again for the
bounded `Code` and `Span` emission branches. `src/Text/Pandoc/Writers/Markdown.hs`,
`test/Tests/Writers/Markdown.hs`, `test/testsuite.txt`,
`test/testsuite.native`, `test/markdown-reader-more.txt`, and
`test/markdown-reader-more.native` were also checked as the targeted static
inventory for this slice, for seven upstream paths total. The PHP Markdown
writer now maps five focused checks from that boundary: code attrs serialize
with Pandoc's `attrsToMarkdown` shape, code text containing backticks is
wrapped in a longer backtick marker with interior spaces, attributed spans
write `[content]{#id .class key="value"}`, emoji spans with `data-emoji` write
`:alias:`, and literal text ending in `!` before a following link/span is
escaped so writer output does not accidentally become image syntax.

Focused local verification on 2026-05-23 after the Markdown writer Code/Span
emission slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and `examples/wordpress-markdown-review-handoff.php`;
`php lanes/pandoc/examples/wordpress-markdown-review-handoff.php` emitted a
bracketed reviewer span carrying id/class/data/title metadata, an attributed
inline code token, and an emoji alias; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,264
assertions, and 0 failures. The focused file now contains 209 behavior tests.

Required root verification on 2026-05-23 after the Markdown writer Code/Span
emission slice: the duplicate-root gate returned no active exact root harness
process, so `php tools/run-tests.php` was run once. Pandoc tests passed inside
the root run, but the root harness exited red with 224 test files, 25,731
assertions, and 1 failure outside this lane:
`lanes/libsqlite/tests/SQLiteHeaderTest.php` test `plans wordpress replacement
by merging a non-root composite index parent under a multi-child root` expected
IDs `[1, 2, 3, 4, 6, 7]` and got `[1, 2, 3, 4, 5, 6, 7]`.

`src/Text/Pandoc/Writers/Markdown/Inline.hs` was inspected again for the
bounded Strikeout, Superscript, Subscript, Math, and RawInline branches. The PHP
Markdown writer now maps eight focused checks from that source boundary:
strikeout delimiter output, superscript delimiter output, subscript delimiter
output, script-space escaping, inline TeX math dollar output, display TeX math
dollar output, the upstream math-followed-by-digit `<!-- -->` guard from
`inlineListToMarkdown`, raw TeX raw-attribute output, and raw-attribute fallback
syntax for unsupported raw inline formats.

Focused local verification on 2026-05-23 after the Markdown writer
strike/script/math/raw inline slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and `examples/wordpress-markdown-review-handoff.php`;
`php lanes/pandoc/examples/wordpress-markdown-review-handoff.php` emitted
subscript, superscript, strikeout, inline math with a digit guard, raw TeX
raw-attribute output, and raw-attribute fallback output; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,265
assertions, and 0 failures. The focused file now contains 210 behavior tests.

Required root verification on 2026-05-23 after the Markdown writer
strike/script/math/raw inline slice: the duplicate-root gate returned no active
exact root harness process, so `php tools/run-tests.php` was run once. The root
harness passed 224 test files, 25,874 assertions, and 0 failures.

`src/Text/Pandoc/Writers/Markdown/Inline.hs` was inspected again for the
bounded `Quoted`, `Underline`, and `SmallCaps` writer branches. The PHP
Markdown writer now maps five focused checks from that source boundary: smart
single-quote delimiter output, smart double-quote delimiter output with nested
link content, bracketed underline span output, nested emphasis inside an
underline span, and small-caps output through Pandoc's `.smallcaps` span class.

Focused local verification on 2026-05-23 after the Markdown writer
quoted/underline/small-caps slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and `examples/wordpress-markdown-review-handoff.php`;
`php lanes/pandoc/examples/wordpress-markdown-review-handoff.php` emitted
`"source excerpt"`, `[manual underlines]{.underline}`, and
`[source glossary]{.smallcaps}` in the native reviewer handoff packet; `php
tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test
file, 2,266 assertions, and 0 failures. The focused file now contains 211
behavior tests.

Required root verification on 2026-05-23 after the Markdown writer
quoted/underline/small-caps slice: the duplicate-root gate returned no active
exact root harness process, so `php tools/run-tests.php` was run once. The root
harness passed 226 test files, 26,113 assertions, and 0 failures.

`src/Text/Pandoc/Writers/Markdown/Inline.hs` was inspected again for the
bounded `Cite` writer branch. The PHP Markdown writer now maps eight focused
checks from that source boundary: author-in-text citations with suffix/rest
brackets, bracketed normal citation lists, suppress-author `-@` citations,
prefix/suffix inline rendering with Pandoc's punctuation spacing rule,
semicolon-separated multi-citation joins, reader-compatible string suffixes,
digit-leading cite keys, and braced invalid cite keys.

Focused local verification on 2026-05-23 after the Markdown writer citation
rendering slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and `examples/wordpress-markdown-review-handoff.php`;
`php lanes/pandoc/examples/wordpress-markdown-review-handoff.php` emitted
`@migration-audit [p. 12; see @source-log ch. 4]` and
`[-@{legacy key}, appendix]`; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,268
assertions, and 0 failures. The focused file now contains 212 behavior tests.

Required root verification on 2026-05-23 after the Markdown writer citation
rendering slice: the duplicate-root gate returned no active exact root harness
process, so `php tools/run-tests.php` was run once. The root harness passed
226 test files, 26,288 assertions, and 0 failures.

`src/Text/Pandoc/Writers/Markdown/Inline.hs` was inspected again for the
bounded `Span ("",["mark"],[])` branch and the matching inline escaping branch
for literal `==` text. The PHP Markdown writer now maps three focused checks
from that source boundary: exact `.mark` spans emit Pandoc-style
`==...==` highlight syntax, literal `==` source text is escaped so reviewer
copy does not become accidental highlight markup, and `.mark` spans with an
id or other attributes remain on the normal bracketed-span attribute path.

Focused local verification on 2026-05-23 after the Markdown writer mark-span
slice: `php -l` passed for `MarkdownWriter.php`, `MarkdownReaderTest.php`, and
`examples/wordpress-markdown-review-handoff.php`; `php
lanes/pandoc/examples/wordpress-markdown-review-handoff.php` emitted
`==verify source caption==` while escaping literal `==audit tokens==` as
review text; `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
passed 1 test file, 2,269 assertions, and 0 failures. The focused file now
contains 213 behavior tests.

Required root verification on 2026-05-23 after the Markdown writer mark-span
slice: the duplicate-root gate returned no active exact root harness process,
so `php tools/run-tests.php` was run once and did not emit a lock-wait message.
The pandoc tests passed inside the root run, but the root harness exited red
with 226 test files, 25,266 assertions, and 204 failures outside this lane.
The visible failures were in `lanes/difftastic/tests/TokenDifferTest.php`, all
showing `Call to undefined method PortLibs\Difftastic\TokenDiffer::isNixLanguage()`.

`src/Text/Pandoc/Writers/Markdown/Inline.hs` was inspected again for the
bounded raw-HTML fallback branch on `Link` and `Image`, and
`src/Text/Pandoc/Writers/HTML.hs` was inspected for the exact HTML writer
boundary that Pandoc delegates to when `Ext_raw_html` is enabled but
`Ext_link_attributes`/`Ext_attributes` are disabled. The native PHP writer now
maps five focused checks from that source boundary: attributed inline links
fall back to raw `<a>` HTML, attributed images fall back to raw `<img />` HTML,
nonstandard HTML attribute keys such as `source` are emitted with Pandoc-style
`data-` prefixes, `rawHtml=false` drops Markdown attributes instead of using
the HTML fallback, and the reference-link path omits reference-definition
attributes when Markdown link attributes are disabled.

Focused local verification on 2026-05-23 after the Markdown writer raw-HTML
fallback slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and `examples/wordpress-markdown-raw-html-fallback.php`;
`php lanes/pandoc/examples/wordpress-markdown-raw-html-fallback.php` emitted
an attributed reviewer edit link and media image as raw HTML with escaped
attributes and `data-source` metadata; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,272
assertions, and 0 failures. The focused file now contains 214 behavior tests.

Required root verification on 2026-05-23 after the Markdown writer raw-HTML
fallback slice: the duplicate-root gate returned no active exact root harness
process, so `php tools/run-tests.php` was run once through the normal locked
root harness. It passed 226 test files, 26,723 assertions, and 0 failures.

`src/Text/Pandoc/Writers/Markdown/Inline.hs` was inspected again for the
generic `Span attrs ils` branch. The PHP Markdown writer now maps four focused
checks from that source boundary: attributed spans fall back to a raw
`<span ...>` wrapper around Markdown-rendered inline content when
`bracketedSpans=false` and raw HTML is enabled, the same wrapper is emitted
when `nativeSpans=true` and raw HTML is disabled, the span wrapper is omitted
when both raw HTML and native spans are disabled, and null-attribute spans
remain plain content instead of creating an empty wrapper.

Focused local verification on 2026-05-23 after the Markdown writer generic
Span fallback slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and `examples/wordpress-markdown-raw-html-fallback.php`;
`php lanes/pandoc/examples/wordpress-markdown-raw-html-fallback.php` emitted
an attributed reviewer edit link, scoped review span, and media image as raw
HTML with escaped attributes and `data-source` metadata; `php
tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test
file, 2,276 assertions, and 0 failures. The focused file now contains 215
behavior tests.

Required root verification on 2026-05-23 after the Markdown writer generic
Span fallback slice was left pending. The duplicate-root gate returned no
active exact `php tools/run-tests.php` process, but broad upstream runners were
active: Dolt BATS PID `237222` with child BATS processes and SQLite
`testrunner.tcl --jobs` PIDs `3854382`/`3854383`. Per lane instructions, no
additional no-argument root harness was started because focused pandoc tests
were already green.

`src/Text/Pandoc/Writers/Markdown/Inline.hs` was inspected again for the
bounded `Underline` and `SmallCaps` writer branches. `src/Text/Pandoc/Shared.hs`
was checked for `capitalize`, which uppercases `Str` nodes recursively, and
`src/Text/Pandoc/Writers/HTML.hs` was checked for the corresponding HTML
`<u>` and `smallcaps` span semantics. The PHP Markdown writer now maps eight
focused checks from that source boundary: underline raw-HTML fallback to
`<u>`, underline native-span fallback, underline emphasis fallback when raw
HTML/native spans are disabled, small-caps raw span output, small-caps native
span output, small-caps uppercase fallback, uppercasing of nested link label
text, and preserving code-span text while ordinary `Str` text is uppercased.

Focused local verification on 2026-05-23 after the Markdown writer
underline/small-caps fallback slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and `examples/wordpress-markdown-raw-html-fallback.php`;
`php lanes/pandoc/examples/wordpress-markdown-raw-html-fallback.php` emitted an
attributed reviewer edit link, scoped review span, raw `<u>` underline,
smallcaps span, and media image as raw HTML-compatible Markdown with escaped
attributes and `data-source` metadata; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,280
assertions, and 0 failures. The focused file now contains 216 behavior tests.

Required root verification on 2026-05-23 after the Markdown writer
underline/small-caps fallback slice was left pending. The duplicate-root gate
returned no active exact `php tools/run-tests.php` process, but broad upstream
runners were active: Dolt BATS PID `237222` with child BATS processes and
SQLite `testrunner.tcl --jobs` PIDs `3854382`/`3854383`. Per lane
instructions, no additional no-argument root harness was started because
focused pandoc tests were already green.

`src/Text/Pandoc/Writers/Markdown/Inline.hs` was inspected again for the
bounded `Emph` and `Strong` writer branches. The PHP Markdown writer now maps
three focused checks from that source boundary: `Emph [Emph ils]` collapses to
the inner rendered content instead of becoming double emphasis, empty `Emph`
emits no delimiters, and empty `Strong` emits no delimiters.

Focused local verification on 2026-05-23 after the Markdown writer
nested/empty emphasis slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and `examples/wordpress-markdown-review-handoff.php`;
`php lanes/pandoc/examples/wordpress-markdown-review-handoff.php` emitted
`Reviewer emphasis normalization: source flag and empty source marks drop
before handoff.`; `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
passed 1 test file, 2,283 assertions, and 0 failures. The focused file now
contains 217 behavior tests.

Required root verification on 2026-05-23 after the Markdown writer nested/empty
emphasis slice was left pending. The first duplicate-root gate sample found an
active focused PHP lane process, PID `497483`
(`php tools/run-tests.php lanes/rclone/tests lanes/syncthing/tests`), which
exited before owner sampling. A later exact-root sample was clear, but broad
SQLite `testrunner.tcl --jobs` processes were active: PIDs `3854382` and
`3854383`, owned by `claude`. Per lane instructions, no additional
no-argument root harness was started because focused pandoc tests were already
green.

`src/Text/Pandoc/Writers/Markdown/Inline.hs` was inspected again for the
bounded `Strikeout` writer branch when `Ext_strikeout` is disabled. The PHP
Markdown writer now maps two additional focused checks from that source
boundary: `Strikeout` falls back to raw `<s>...</s>` output when raw HTML is
enabled, and falls back to plain rendered inline content when raw HTML is also
disabled. The existing enabled-extension `~~...~~` branch remains covered.

Focused local verification on 2026-05-23 after the Markdown writer
Strikeout disabled-extension fallback slice: `php -l` passed for
`MarkdownWriter.php`, `MarkdownReaderTest.php`, and
`examples/wordpress-markdown-raw-html-fallback.php`; `php
lanes/pandoc/examples/wordpress-markdown-raw-html-fallback.php` emitted
`<s>legacy caption</s>` for a deleted source caption; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,286
assertions, and 0 failures. The focused file now contains 218 behavior tests.

Required root verification on 2026-05-23 after the Markdown writer Strikeout
disabled-extension fallback slice was left pending. The duplicate-root gate
returned no active exact `php tools/run-tests.php` process, but broad upstream
runners were active: Dolt BATS PIDs `575005`, `575036`, and `575043`, plus
SQLite `testrunner.tcl --jobs` PIDs `3854382` and `3854383`, all owned by
`claude`. Per lane instructions, no additional no-argument root harness was
started because focused pandoc tests were already green.

`src/Text/Pandoc/Writers/Markdown/Inline.hs` was inspected again for the
bounded `Superscript` and `Subscript` writer branches when `Ext_superscript`
or `Ext_subscript` is disabled, and `src/Text/Pandoc/Writers/Shared.hs` was
checked for the `toSuperscript`, `toSubscript`, `toSuperscriptInline`, and
`toSubscriptInline` conversion tables. The PHP Markdown writer now maps four
additional focused checks from that source boundary: disabled script
extensions fall back to raw `<sup>`/`<sub>` output when raw HTML is enabled,
disabled raw HTML falls back to Unicode digit/symbol scripts when the
upstream conversion table can represent the content, `preferAscii` follows
Pandoc's later superscript-character fallback for digit content, and
non-convertible content falls back to `^(...)` or `_(...)` parenthesized text.
The mapped denominator is now 674 focused Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the Markdown writer
Superscript/Subscript disabled-extension fallback slice: `php -l` passed for
`MarkdownWriter.php`, `MarkdownReaderTest.php`, and
`examples/wordpress-markdown-raw-html-fallback.php`; `php
lanes/pandoc/examples/wordpress-markdown-raw-html-fallback.php` emitted
`H<sub>2</sub>` and `x<sup>2</sup>` for disabled script syntax in a reviewer
fallback packet; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,289
assertions, and 0 failures. The focused file now contains 219 behavior tests.

Required root verification on 2026-05-23 after the Markdown writer
Superscript/Subscript disabled-extension fallback slice was left pending. The
duplicate-root gate returned no active exact `php tools/run-tests.php`
process, but broad upstream runners were active: Dolt BATS PIDs `575005`,
`575036`, and `575043`; Syncthing Go test PIDs `744251` and `744254`; and
Gitoxide Cargo test PIDs `744335` and `744338`, all owned by `claude`. Per
lane instructions, no additional no-argument root harness was started because
focused pandoc tests were already green.

`src/Text/Pandoc/Writers/Markdown/Inline.hs` was inspected again for the
bounded `Quoted SingleQuote` and `Quoted DoubleQuote` writer branches. The PHP
Markdown writer now maps four additional focused checks from that source
boundary: smart-enabled output keeps Pandoc's straight single and double quote
delimiters, `smart=false` emits Unicode curly quote delimiters, `smart=false`
with `preferAscii=true` emits `&lsquo;`/`&rsquo;` and `&ldquo;`/`&rdquo;`
entity delimiters, and smart-disabled text stops escaping ordinary quote,
dash, and ellipsis punctuation as if `Ext_smart` were still enabled. The mapped
denominator is now 678 focused Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the Markdown writer Quoted
smart-disabled fallback slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and
`examples/wordpress-markdown-raw-html-fallback.php`; `php
lanes/pandoc/examples/wordpress-markdown-raw-html-fallback.php` emitted
`&lsquo;legacy reviewer quote&rsquo;` and `&ldquo;migration excerpt&rdquo;`
for disabled smart quote syntax in a reviewer fallback packet; `php
tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test
file, 2,293 assertions, and 0 failures. The focused file now contains 220
behavior tests.

Required root verification on 2026-05-23 after the Markdown writer Quoted
smart-disabled fallback slice was left pending. The duplicate-root gate found
active exact root harness PID `938813`, owned by `claude`, command `php
tools/run-tests.php`. Per lane instructions, no additional no-argument root
harness was started because focused pandoc tests were already green.

`src/Text/Pandoc/Writers/Markdown/Inline.hs` was inspected again for the
bounded `Str` writer branch, and `src/Text/Pandoc/XML.hs` plus the upstream
`tagsoup-0.14.8` `Text.HTML.TagSoup.Entity` table were checked for Pandoc's
`toHtml5Entities` behavior. The PHP Markdown writer now maps four additional
focused checks from that source boundary: `preferAscii` emits named HTML5
entities for mapped non-ASCII `Str` characters, falls back to decimal numeric
entities for unmapped code points, applies smart-enabled `unsmartify` before
entity conversion for typographic quotes/dashes/ellipses, and preserves the
same punctuation as HTML5 entities when smart punctuation is disabled. The
mapped denominator is now 682 focused Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the Markdown writer Str
preferAscii slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and
`examples/wordpress-markdown-raw-html-fallback.php`; `php
lanes/pandoc/examples/wordpress-markdown-raw-html-fallback.php` emitted
`R&eacute;sum&eacute;`, `&COPY;`, `&in;`, `&#128512;`,
`&ldquo;curly excerpt&rdquo;`, and `&mldr;` for a non-ASCII reviewer fallback
packet; `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
passed 1 test file, 2,295 assertions, and 0 failures. The focused file now
contains 221 behavior tests.

Required root verification on 2026-05-23 after the Markdown writer Str
preferAscii slice was left pending. The duplicate-root gate found active exact
root harness PID `1035563`, owned by `claude`, command `php
tools/run-tests.php`; broad Dolt Go runners were also active. Per lane
instructions, no additional no-argument root harness was started because
focused pandoc tests were already green.

`src/Text/Pandoc/Writers/Markdown/Inline.hs` was inspected again for the
bounded `LineBreak` writer branch, and `src/Text/Pandoc/Extensions.hs` was
checked for the `pandocExtensions` defaults that enable
`Ext_escaped_line_breaks`. The PHP Markdown writer now maps three additional
focused checks from that source boundary: default Markdown output emits an
escaped line break (`\` before newline), disabling escaped line breaks emits
Pandoc's two-space hard-break form, and enabling hard line breaks emits a plain
newline. The mapped denominator is now 685 focused Markdown/HTML/WordPress
checks.

Focused local verification on 2026-05-23 after the Markdown writer LineBreak
option slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and
`examples/wordpress-markdown-review-handoff.php`; `php
lanes/pandoc/examples/wordpress-markdown-review-handoff.php` emitted the
reviewer line-break paragraph with a trailing escaped-line-break backslash;
`php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1
test file, 2,298 assertions, and 0 failures. The focused file now contains 222
behavior tests.

Required root verification on 2026-05-23 after the Markdown writer LineBreak
option slice was left pending. The duplicate-root gate returned no active exact
`php tools/run-tests.php` process, but broad upstream runners were active:
Dolt BATS PIDs `1036684`, `1036713`, `1036720`, `1036721`, and `1036722`, and
SQLite `testrunner.tcl --jobs` PIDs `1057838` and `1057839`, all owned by
`claude`. Per lane instructions, no additional no-argument root harness was
started because focused pandoc tests were already green.

`src/Text/Pandoc/Writers/Markdown/Inline.hs` was inspected again for the
bounded `RawInline` writer branch. The PHP Markdown writer now maps eight
additional focused checks from that source boundary: `raw_attribute` takes
precedence for HTML raw inlines, TeX raw inlines, and unknown raw formats;
disabling `raw_attribute` lets enabled `raw_html` and `raw_tex` formats pass
through literally; disabling those target raw extensions omits the inlines as
Pandoc's `InlineNotRendered` path does; unsupported non-target raw formats are
omitted when raw attributes are disabled; and markdown-family raw formats still
render literally even when all raw preservation toggles are disabled. The
mapped denominator is now 693 focused Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the Markdown writer raw-inline
extension fallback slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and
`examples/wordpress-markdown-review-handoff.php`; `php
lanes/pandoc/examples/wordpress-markdown-review-handoff.php` emitted raw TeX,
OPML, and HTML reviewer markers as Pandoc raw-attribute Markdown; `php
tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test
file, 2,307 assertions, and 0 failures. The focused file now contains 223
behavior tests.

Required root verification on 2026-05-23 after the Markdown writer raw-inline
extension fallback slice was left pending. The duplicate-root gate returned no
active exact `php tools/run-tests.php` process, but broad upstream Dolt BATS
runners were active: PIDs `1036684`, `1036713`, `1036720`, `1036721`,
`1036722`, and `1434434`, all owned by `claude`. Per lane instructions, no
additional no-argument root harness was started because focused pandoc tests
were already green.

`src/Text/Pandoc/Writers/Markdown.hs` was inspected again for the bounded
block-level `RawBlock` and `Div` writer branches, and
`src/Text/Pandoc/Extensions.hs` was checked for Pandoc's default
`Ext_raw_html`, `Ext_raw_tex`, `Ext_raw_attribute`, `Ext_native_divs`, and
`Ext_fenced_divs` settings. The PHP Markdown writer now maps 12 additional
focused checks from that source boundary: markdown-family raw blocks pass
through literally, HTML and TeX raw blocks pass through while their target raw
extensions are enabled, disabled HTML/TeX raw blocks and unknown raw formats
fall back to fenced raw-attribute blocks when `raw_attribute` is enabled,
unsupported raw blocks are omitted when raw attributes are disabled, fenced
Div output uses Pandoc-style nesting-aware colon counts, disabled fenced Divs
fall back to native/raw HTML wrappers, and disabling fenced/native/raw HTML
Div output preserves the inner rendered content. The mapped denominator is now
705 focused Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the Markdown writer RawBlock/Div
fallback slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and
`examples/wordpress-markdown-review-handoff.php`; `php
lanes/pandoc/examples/wordpress-markdown-review-handoff.php` emitted a fenced
WordPress review Div, an OPML fenced raw block, and a literal TeX review
environment for native reviewer handoff; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,319
assertions, and 0 failures. The focused file now contains 225 behavior tests.

Required root verification on 2026-05-23 after the Markdown writer RawBlock/Div
fallback slice was left pending because this lane was not assigned
no-argument root verification. Per lane instructions, focused pandoc evidence
was recorded and root aggregate verification remains pending for the
supervisor/integrator.

`src/Text/Pandoc/Writers/Markdown.hs` was inspected again for the bounded
block-level `Figure` writer branch, including the `combinedAttr` implicit
figure guard, the `fig:` title stripping path, the raw-HTML `figureToMarkdown`
fallback, and the `figureDiv` fallback from `src/Text/Pandoc/Shared.hs`. The
PHP Markdown writer now maps the focused checks from that source boundary:
implicit single-image figures render with caption text while moving
allowed figure ids to the image attrs, `fig:` title prefixes are stripped,
distinct image alt text is preserved as an `alt` attribute, attributed figures
fall back to raw HTML when `raw_html` is enabled, raw-disabled figures use
Pandoc-style fenced figure Divs including caption Divs, disabled implicit
figures take the same Div fallback, and constrained profiles can emit only the
figure body when raw HTML and Div output are unavailable. The mapped
denominator is now 711 focused Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the Markdown writer Figure
fallback slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, `examples/wordpress-markdown-review-handoff.php`, and
`examples/wordpress-markdown-raw-html-fallback.php`; `php
lanes/pandoc/examples/wordpress-markdown-raw-html-fallback.php` emitted an
attributed source-review `<figure>` with `data-source` and `figcaption`
metadata; `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
passed 1 test file, 2,324 assertions, and 0 failures. The focused file now
contains 226 behavior tests.

Required root verification on 2026-05-23 after the Markdown writer Figure
fallback slice was left pending because this lane was not assigned
no-argument root verification. Per lane instructions, focused pandoc evidence
was recorded and root aggregate verification remains pending for the
supervisor/integrator.

`src/Text/Pandoc/Writers/Markdown.hs` and
`src/Text/Pandoc/Writers/Markdown/Table.hs` were inspected again for the
bounded table writer branch. The static source boundary covers Pandoc's order:
simple tables can use pipe/simple table output, complex tables with spans,
block cells, or footers fall back to HTML while `raw_html` is enabled, simple
spanned tables can degrade to approximate pipe tables when raw HTML is
disabled, and otherwise the writer reports an unrendered table as `[TABLE]`
with the caption boundary preserved. `src/Text/Pandoc/Writers/HTML.hs` was
checked for the corresponding HTML fallback structure: caption, colgroup,
thead/tbody/tfoot, colspan/rowspan, and text-alignment styles. The PHP writer
now maps five additional focused checks from that source boundary: pipe-table
rendering with alignment delimiters and caption inline content, raw HTML
fallback for spanned tables, raw HTML cell colspan/alignment preservation,
raw-disabled approximate pipe-table fallback, and disabled pipe/raw placeholder
output with caption attributes. The mapped denominator is now 716 focused
Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the Markdown writer table
fallback slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and
`examples/wordpress-markdown-raw-html-fallback.php`; `php
lanes/pandoc/examples/wordpress-markdown-raw-html-fallback.php` emitted a
source-review `<table>` with `data-source`, caption, colgroup, colspan, and
alignment metadata; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,329
assertions, and 0 failures. The focused file now contains 227 behavior tests.

Required root verification on 2026-05-23 after the Markdown writer table
fallback slice was left pending because this lane was not assigned
no-argument root verification. Per lane instructions, focused pandoc evidence
was recorded and root aggregate verification remains pending for the
supervisor/integrator.

`src/Text/Pandoc/Writers/Markdown.hs`,
`src/Text/Pandoc/Writers/Markdown/Table.hs`, and
`src/Text/Pandoc/Writers/Shared.hs` were inspected again for the bounded
grid-table writer branch. The static source boundary covers Pandoc's table
selection order where grid-table output is chosen before raw HTML fallback when
`grid_tables` can represent a non-spanned table with block-rich cells or a
table footer. The PHP writer now maps six additional focused checks from that
source boundary: width-driven grid column sizing, header alignment markers,
block-level cell content, line-break rendering inside grid cells, table-footer
double borders, caption/attribute preservation, and the no-raw-HTML fallback
boundary for this representable grid-table shape. The mapped denominator is
now 722 focused Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the Markdown writer grid-table
branch slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, `examples/wordpress-markdown-grid-table-handoff.php`,
and `examples/wordpress-markdown-raw-html-fallback.php`; `php
lanes/pandoc/examples/wordpress-markdown-grid-table-handoff.php` emitted a
Pandoc-style grid table with block-level cell content, footer totals,
width-driven columns, alignment markers, and caption/source attrs; `php
lanes/pandoc/examples/wordpress-markdown-raw-html-fallback.php` still emitted a
source-review raw HTML table for a spanned-table fallback; `php
tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test
file, 2,339 assertions, and 0 failures. The focused file now contains 228
behavior tests.

Required root verification on 2026-05-23 after the Markdown writer grid-table
branch slice was left pending because this lane was not assigned no-argument
root verification. Per lane instructions, focused pandoc evidence was recorded
and root aggregate verification remains pending for the supervisor/integrator.

`src/Text/Pandoc/Writers/Markdown.hs` and
`src/Text/Pandoc/Writers/Markdown/Table.hs` were inspected again for the
bounded multiline-table writer branch. The static source boundary covers
Pandoc's table selection order where `Ext_multiline_tables` is chosen for
width-bearing simple-cell tables before grid-table or raw HTML fallback, plus
the `pandocTable` headed and headless rule shapes. The PHP writer now maps six
additional focused checks from that source boundary: multiline-table selection
before pipe syntax, headed full-border output, headless per-column rule output,
width-derived cell alignment, multiline cell text preservation, caption/attr
preservation, and the pipe fallback when multiline tables are disabled. The
mapped denominator is now 728 focused Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the Markdown writer
multiline-table branch slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and
`examples/wordpress-markdown-multiline-table-handoff.php`; `php
lanes/pandoc/examples/wordpress-markdown-multiline-table-handoff.php` emitted a
Pandoc-style multiline table with wrapped WordPress reviewer notes,
width-derived columns, caption attrs, and no raw HTML fallback; `php
tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test
file, 2,352 assertions, and 0 failures. The focused file now contains 229
behavior tests.

Required root verification on 2026-05-23 after the Markdown writer
multiline-table branch slice was left pending because this lane was not
assigned no-argument root verification. Per lane instructions, focused pandoc
evidence was recorded and root aggregate verification remains pending for the
supervisor/integrator.

`src/Text/Pandoc/Writers/Markdown.hs`,
`src/Text/Pandoc/Writers/Shared.hs`, and
`test/command/rst-writer-gridtable-if-rowspans.md` were inspected again for
the bounded row/column-span grid-table writer branch. The static source
boundary covers Pandoc's table selection order where `Ext_grid_tables` is
chosen before raw HTML fallback whenever `hasColRowSpans` is true, plus the
shared `gridTable` behavior that inserts row-span dummy cells, suppresses only
the covered horizontal-rule segment with `NoLine`, preserves double
head/footer boundaries, and gives colspanned cells a combined width without
interior separators. The PHP writer now maps eight additional focused checks
from that source boundary: spanned-table grid selection before raw HTML,
row-span continuation dummy cells, row-span border gaps, double header
boundaries, double footer boundaries, colspanned separator collapse, raw HTML
fallback only when `gridTables` is disabled, and the approximate pipe-table
fallback when grid and raw output are disabled. The mapped denominator is now
736 focused Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the Markdown writer
grid-table span branch slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and
`examples/wordpress-markdown-spanned-grid-table-handoff.php`; `php
lanes/pandoc/examples/wordpress-markdown-spanned-grid-table-handoff.php`
emitted a Pandoc-style spanned grid table with row-spanned review areas,
colspanned remediation summaries, caption/classes/source attrs, and no raw
HTML fallback; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,361
assertions, and 0 failures. The focused file now contains 230 behavior tests.

Required root verification on 2026-05-23 after the Markdown writer
grid-table span branch slice was left pending because this lane was not
assigned no-argument root verification. Per lane instructions, focused pandoc
evidence was recorded and root aggregate verification remains pending for the
supervisor/integrator.

`src/Text/Pandoc/Writers/Markdown.hs` and
`src/Text/Pandoc/Writers/Markdown/Table.hs` were inspected again for the
bounded simple-table writer branch. The static source boundary covers
Pandoc's table selection order where `isSimple && Ext_simple_tables` chooses
`pandocTable opts False` before `pipeTable`, plus the `pandocTable` headed and
headless simple-table rule shapes for widthless simple-cell tables. The PHP
writer now maps eight additional focused checks from that source boundary:
simple-table selection before pipe syntax, headed simple-table output,
headless simple-table output, right/left/center/default alignment padding,
caption/attribute preservation under the two-space Pandoc table indent, pipe
fallback when `simple_tables` is disabled, multiline fallback when both
`simple_tables` and `pipe_tables` are disabled, and no raw HTML fallback for
that bounded Markdown-native shape. The mapped denominator is now 744 focused
Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the Markdown writer
simple-table branch slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and
`examples/wordpress-markdown-simple-table-handoff.php`; `php
lanes/pandoc/examples/wordpress-markdown-simple-table-handoff.php` emitted a
Pandoc-style simple table with widthless WordPress import totals, alignment
padding, caption/classes/source attrs, and no pipe/raw HTML fallback; `php
tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test
file, 2,370 assertions, and 0 failures. The focused file now contains 231
behavior tests.

Required root verification on 2026-05-23 after the Markdown writer
simple-table branch slice was left pending because this lane was not assigned
no-argument root verification. Per lane instructions, focused pandoc evidence
was recorded and root aggregate verification remains pending for the
supervisor/integrator.

`src/Text/Pandoc/Writers/Markdown/Table.hs` and `test/pipe-tables.txt` were
inspected again for the bounded pipe-table width-constrained writer branch.
The static source boundary covers Pandoc's `pipeTable` behavior where content
widths drive cell padding only while they fit inside `writerColumns`, relative
`ColWidth` hints scale delimiter widths when content plus pipe separators would
overflow the configured column budget, and over-wide rows stop receiving
alignment padding. The PHP writer now maps eight additional focused checks
from that source boundary: relative delimiter-width selection, floor-based
writerColumns scaling, continued parsing of the upstream long relative-width
pipe-table fixture, unpadded over-wide rows, two-character delimiter fallback
for over-wide widthless tables, stable left/right/default delimiter markers in
narrow output, caption/attribute preservation in the new WordPress handoff
example, and no raw HTML fallback for that bounded pipe-table shape. The
mapped denominator is now 752 focused Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the Markdown writer pipe-table
width branch slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and
`examples/wordpress-markdown-pipe-width-handoff.php`; `php
lanes/pandoc/examples/wordpress-markdown-pipe-width-handoff.php` emitted a
narrow Pandoc-style pipe table with relative delimiter widths, over-wide review
notes without alignment padding, caption/classes/source attrs, and no raw HTML
fallback; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,374
assertions, and 0 failures. The focused file now contains 232 behavior tests.

Required root verification on 2026-05-23 after the Markdown writer pipe-table
width branch slice was left pending because this lane was not assigned
no-argument root verification. Per lane instructions, focused pandoc evidence
was recorded and root aggregate verification remains pending for the
supervisor/integrator.

`src/Text/Pandoc/Writers/Markdown/Table.hs` was inspected again for the
bounded multiline-table `writerWrapText`/`minOffset` branch. The static source
boundary covers Pandoc's `pandocTable` `relWidth` behavior where non-simple
column widths use the greater of the relative `ColWidth` budget and
`minNumChars` under `WrapAuto`, so a long unbreakable word is not split while
ordinary text can wrap inside a narrow multiline-table column. The PHP writer
now maps nine additional focused checks from that source boundary:
relative-width expansion for an unbreakable source token, preservation of the
long token on one line, narrow reviewer-note wrapping at word boundaries,
wrapped header text, wrapped body text, multiline alignment padding after
wrapping, caption/classes/source attribute preservation, no raw HTML fallback,
and the WordPress import-token review handoff example. The mapped denominator
is now 761 focused Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the Markdown writer multiline
WrapAuto/minOffset branch slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and
`examples/wordpress-markdown-multiline-wrap-handoff.php`; `php
lanes/pandoc/examples/wordpress-markdown-multiline-wrap-handoff.php` emitted a
Pandoc-style multiline table with an unbroken long WordPress source token,
word-wrapped reviewer notes, caption/classes/source attrs, and no raw HTML
fallback; `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
passed 1 test file, 2,383 assertions, and 0 failures. The focused file now
contains 233 behavior tests.

Required root verification on 2026-05-23 after the Markdown writer multiline
WrapAuto/minOffset branch slice was left pending because this lane was not
assigned no-argument root verification. Per lane instructions, focused pandoc
evidence was recorded and root aggregate verification remains pending for the
supervisor/integrator.

`src/Text/Pandoc/Options.hs`, `src/Text/Pandoc/Writers/Markdown.hs`, and
`src/Text/Pandoc/Writers/Markdown/Table.hs` were inspected for the bounded
multiline-table `writerWrapText`/`WrapNone` branch. The static source boundary
covers Pandoc's `WrapOption` names, the `writeMarkdown` guard that forces
`WrapNone` when hard line breaks are enabled, and `pandocTable` `relWidth`
behavior where non-`WrapAuto` modes use `numChars` instead of `minNumChars` for
relative-width columns. The PHP writer now maps eight additional focused checks
from that source boundary: full-width source-token sizing, full-width
reviewer-note sizing, no word-boundary soft wrapping under `WrapNone`,
hard-line-breaks forcing no-wrap table sizing, full row preservation for a
WordPress import review note, caption/classes/source attribute preservation,
native Markdown output without raw HTML fallback, and the
`wordpress-markdown-multiline-nowrap-handoff.php` example. The mapped
denominator is now 769 focused Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the Markdown writer multiline
WrapNone branch slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and
`examples/wordpress-markdown-multiline-nowrap-handoff.php`; `php
lanes/pandoc/examples/wordpress-markdown-multiline-nowrap-handoff.php` emitted
a Pandoc-style multiline table with full-width WordPress source and reviewer
note columns, caption/classes/source attrs, and no raw HTML fallback; `php
tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test
file, 2,391 assertions, and 0 failures. The focused file now contains 234
behavior tests.

Required root verification on 2026-05-23 after the Markdown writer multiline
WrapNone branch slice was left pending because this lane was not assigned
no-argument root verification. Per lane instructions, focused pandoc evidence
was recorded and root aggregate verification remains pending for the
supervisor/integrator.

`src/Text/Pandoc/Writers/Markdown/Inline.hs`,
`src/Text/Pandoc/Writers/Markdown.hs`, and `src/Text/Pandoc/Options.hs` were
inspected again for the bounded Markdown writer `SoftBreak` wrap-option
branch. The static source boundary covers `inlineToMarkdown opts SoftBreak`,
where `WrapAuto` and `WrapNone` render a breaking space, `WrapPreserve`
renders a carriage return, `Options.FromJSON` accepts both `preserve` and
`wrap-preserve`, and `writeMarkdown` forces `WrapNone` when
`Ext_hard_line_breaks` is enabled. The PHP writer now maps five additional
focused checks from that source boundary: default `WrapAuto` spacing, explicit
`WrapNone` spacing, `WrapPreserve` newline preservation, the `wrap-preserve`
alias, and the hard-line-break guard forcing `WrapNone`. The mapped denominator
is now 774 focused Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the Markdown writer SoftBreak
wrap-option slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and
`examples/wordpress-markdown-wrap-preserve-handoff.php`; `php
lanes/pandoc/examples/wordpress-markdown-wrap-preserve-handoff.php` emitted a
Pandoc-style reviewer handoff preserving source paragraph line boundaries
under `wrap-preserve`; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,396
assertions, and 0 failures. The focused file now contains 235 behavior tests.

Required root verification on 2026-05-23 after the Markdown writer SoftBreak
wrap-option slice was left pending because this lane was not assigned
no-argument root verification. Per lane instructions, focused pandoc evidence
was recorded and root aggregate verification remains pending for the
supervisor/integrator.

`src/Text/Pandoc/Writers/Markdown.hs` and
`src/Text/Pandoc/Writers/Markdown/Inline.hs` were inspected for the bounded
Markdown writer `Header` attribute branch. The static source boundary covers
the `blockToMarkdown' opts (Header level attr inlines)` path where Pandoc
computes the auto identifier, elides an id-only attr tuple when it matches that
auto id, appends `attrsToMarkdown` to ATX headings, appends the same tuple to
the setext heading text before the underline, and omits heading attributes when
the header/attributes extension path is disabled. The PHP writer now maps six
additional focused checks from that source boundary: ATX heading attrs with id,
class, and key/value pairs; setext heading attr placement while keeping
underline width based on the heading text; custom id-only headings; inline
heading content with a custom id; auto-id and duplicate auto-id elision; and
disabled heading-attribute output. The mapped denominator is now 780 focused
Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the Markdown writer Header
attribute slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and
`examples/wordpress-markdown-heading-anchors-handoff.php`; `php
lanes/pandoc/examples/wordpress-markdown-heading-anchors-handoff.php` emitted
custom Pandoc setext/ATX heading attrs for a WordPress review packet and
elided duplicate imported auto ids; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,400
assertions, and 0 failures. The focused file now contains 236 behavior tests.

Required root verification on 2026-05-23 after the Markdown writer Header
attribute slice was left pending because this lane was not assigned
no-argument root verification. Per lane instructions, focused pandoc evidence
was recorded and root aggregate verification remains pending for the
supervisor/integrator.

`src/Text/Pandoc/Writers/Markdown.hs`, `src/Text/Pandoc/Extensions.hs`, and
`src/Text/Pandoc/Readers/Markdown.hs` were inspected for the bounded Markdown
writer `CodeBlock` attribute branch. The static source boundary covers
`classOrAttrsToMarkdown`, the `blockToMarkdown' opts (CodeBlock attribs str)`
path, `Ext_backtick_code_blocks`, `Ext_fenced_code_blocks`,
`Ext_fenced_code_attributes`, `getLangFromClasses`, and the block-list
separator guard that only inserts `<!-- -->` before indented code after a list.
The PHP writer now maps six additional focused checks from that source
boundary: class-only code block info strings with Pandoc's leading space;
full id/classes/key-value attr tuples; fence length growth when code contains
an all-backtick fence line; disabled fenced-code-attributes language fallback;
disabled fence-extension indented fallback; and list/code separator behavior
for fenced versus indented code. The mapped denominator is now 786 focused
Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the Markdown writer CodeBlock
attribute slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and
`examples/wordpress-markdown-fenced-code-handoff.php`; `php
lanes/pandoc/examples/wordpress-markdown-fenced-code-handoff.php` emitted a
Pandoc-style fenced PHP shortcode cleanup snippet with id, classes, start
line, and source-batch attrs; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,406
assertions, and 0 failures. The focused file now contains 237 behavior tests.

Required root verification on 2026-05-23 after the Markdown writer CodeBlock
attribute slice was left pending because this lane was not assigned
no-argument root verification. Per lane instructions, focused pandoc evidence
was recorded and root aggregate verification remains pending for the
supervisor/integrator.

`src/Text/Pandoc/Writers/Markdown.hs` was inspected for the bounded Markdown
writer `DefinitionList` branch. The static source boundary covers
`blockToMarkdown' opts (DefinitionList items)`, `definitionListItemToMarkdown`,
`Ext_definition_lists`, `writerTabStop`, the tight-vs-loose split where
`Plain` definitions use `vcat` and `Para` definitions use `vsep`, and the
disabled-extension fallback that emits label/body content without definition
markers. The PHP writer now maps seven additional focused checks from that
source boundary: tight `Plain` definitions render with repeated `:   `
markers, loose `Para` definitions keep a blank line after the term, multiple
definitions keep repeated markers, nested `CodeBlock` bodies are indented under
the marker, nested `BlockQuote` bodies remain block quotes under the marker,
`tabStop` controls marker spacing, and disabled `definitionLists` fallback
keeps term/body content instead of dropping the node. The mapped denominator is
now 793 focused Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the Markdown writer
DefinitionList slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and
`examples/wordpress-markdown-definition-list-handoff.php`; `php
lanes/pandoc/examples/wordpress-markdown-definition-list-handoff.php` emitted a
Pandoc-style WordPress import glossary/checklist with repeated definition
markers, a loose nested shortcode snippet body, and source attrs; `php
tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test
file, 2,409 assertions, and 0 failures. The focused file now contains 238
behavior tests.

Required root verification on 2026-05-23 after the Markdown writer
DefinitionList slice was left pending because this lane was not assigned
no-argument root verification. Per lane instructions, focused pandoc evidence
was recorded and root aggregate verification remains pending for the
supervisor/integrator.

`src/Text/Pandoc/Writers/Markdown.hs` was inspected for the adjacent
`DefinitionList` branch inside `blockListToMarkdown` `fixBlocks`. The static
source boundary covers the upstream guard that inserts `commentSep` between
consecutive `DefinitionList` blocks and the fallback choice where `commentSep`
is a raw HTML `<!-- -->` block when raw HTML is enabled, otherwise a raw
markdown `&nbsp;` block. The PHP writer now maps two additional focused checks:
adjacent definition lists stay separate with `<!-- -->`, and the raw-HTML-off
path uses `&nbsp;`. The mapped denominator is now 795 focused
Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the adjacent DefinitionList
separator slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and
`examples/wordpress-markdown-definition-list-handoff.php`; `php
lanes/pandoc/examples/wordpress-markdown-definition-list-handoff.php` emitted a
Pandoc-style WordPress import glossary/checklist plus an adjacent reviewer
packet separated by `<!-- -->`; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,411
assertions, and 0 failures. The focused file still contains 238 behavior tests.

Required root verification on 2026-05-23 after the adjacent DefinitionList
separator slice was left pending because this lane was not assigned
no-argument root verification. Per lane instructions, focused pandoc evidence
was recorded and root aggregate verification remains pending for the
supervisor/integrator.

`src/Text/Pandoc/Writers/Markdown.hs` was inspected for the full adjacent
same-type list branch inside `blockListToMarkdown` `fixBlocks`. The static
source boundary covers the 53-line upstream `fixBlocks` region, including the
three guards that insert `commentSep` between consecutive `BulletList`,
`OrderedList`, and `DefinitionList` blocks plus the fallback choice where
`commentSep` is a raw HTML `<!-- -->` block when raw HTML is enabled,
otherwise a raw markdown `&nbsp;` block. The cloned static inventory was
revalidated with `git ls-tree` counts: 2,276 inspected upstream
test/data/benchmark artifacts, split as 1,974 under `test/`, 54 under
`pandoc-lua-engine/test/`, 247 under `data/`, and 1 under `benchmark/`. The
PHP writer now maps four additional focused checks from that source boundary:
adjacent bullet lists stay separate with `<!-- -->`, adjacent bullet lists use
`&nbsp;` when raw HTML is disabled, adjacent ordered lists stay separate with
`<!-- -->`, and adjacent ordered lists use `&nbsp;` when raw HTML is disabled.
The mapped denominator is now 799 focused Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the adjacent same-type list
separator slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and
`examples/wordpress-markdown-adjacent-list-handoff.php`; `php
lanes/pandoc/examples/wordpress-markdown-adjacent-list-handoff.php` emitted
Pandoc-style WordPress reviewer bullet and ordered queues separated by
`<!-- -->`; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,415
assertions, and 0 failures. The focused file now contains 239 behavior tests.

Required root verification on 2026-05-23 after the adjacent same-type list
separator slice was left pending because this lane was not assigned
no-argument root verification. Per lane instructions, focused pandoc evidence
was recorded and root aggregate verification remains pending for the
supervisor/integrator.

`src/Text/Pandoc/Writers/Markdown.hs` was inspected for the remaining
Plain/RawBlock portion of `blockListToMarkdown` `fixBlocks`. The static source
boundary was counted directly from the cached upstream commit at 42 lines and
15 Plain/RawBlock/Div/comment/code guard mentions. The cloned static inventory
was revalidated again with `git ls-tree` counts: 2,276 inspected upstream
test/data/benchmark artifacts, split as 1,974 under `test/`, 54 under
`pandoc-lua-engine/test/`, 247 under `data/`, and 1 under `benchmark/`. The
PHP writer now maps four additional focused checks from that source boundary:
Plain followed by RawBlock remains tight with no injected blank block, RawBlock
followed by Plain remains tight, consecutive RawBlock nodes remain tight, and
RawBlock followed by ordinary block content still receives normal blank block
separation. The mapped denominator is now 803 focused
Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the raw/plain `fixBlocks`
boundary slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and
`examples/wordpress-markdown-raw-boundary-handoff.php`; `php
lanes/pandoc/examples/wordpress-markdown-raw-boundary-handoff.php` emitted a
WordPress reviewer handoff where plain source notes stay adjacent to raw HTML
review cards and the next heading remains separated; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,419
assertions, and 0 failures. The focused file now contains 240 behavior tests.

Required root verification on 2026-05-23 after the raw/plain `fixBlocks`
boundary slice was left pending because this lane was not assigned
no-argument root verification. Per lane instructions, focused pandoc evidence
was recorded and root aggregate verification remains pending for the
supervisor/integrator.

`src/Text/Pandoc/Writers/Markdown.hs` was inspected for the in-list
Plain-before-Div branch inside `blockListToMarkdown` `fixBlocks`. The targeted
source boundary is upstream lines 900-918 at cached commit
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`: `Plain` before `Div` is promoted
to `Para` when `Ext_fenced_divs` is enabled before the later `inlist`
Plain fallback can keep it tight. The cloned static inventory was revalidated
with `git ls-tree` counts: 2,276 inspected upstream test/data/benchmark
artifacts, split as 1,974 under `test/`, 54 under
`pandoc-lua-engine/test/`, 247 under `data/`, and 1 under `benchmark/`. The
PHP writer now maps two additional focused checks from that source boundary:
list-item `Plain` before a fenced `Div` renders as a real list item followed
by a separated fenced Div, and disabled fenced Div output remains on the
in-list Plain fallback. The mapped denominator is now 805 focused
Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the in-list Plain-before-Div
`fixBlocks` slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and
`examples/wordpress-markdown-list-div-handoff.php`; `php
lanes/pandoc/examples/wordpress-markdown-list-div-handoff.php` emitted a
WordPress reviewer list item followed by a Pandoc-style separated fenced Div;
`php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1
test file, 2,421 assertions, and 0 failures. The focused file now contains
241 behavior tests.

Required root verification on 2026-05-23 after the in-list Plain-before-Div
`fixBlocks` slice was left pending because this lane was not assigned
no-argument root verification. Per lane instructions, focused pandoc evidence
was recorded and root aggregate verification remains pending for the
supervisor/integrator.

`src/Text/Pandoc/Writers/Markdown.hs` was inspected for the Plain/Para marker
escaping branch. The targeted source boundary is upstream lines 318-333 for
`olMarker`/`beginsWithOrderedListMarker` and lines 430-443 for
`blockToMarkdown' opts (Plain inlines)` at cached commit
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`: ordered-list-looking first
`Str` nodes are emitted as raw markdown with escaped marker punctuation, and
single-token `+`, `-`, and `%` starts receive an escape guard before normal
inline rendering. The cloned static inventory was revalidated with `git
ls-tree` counts: 2,276 inspected upstream test/data/benchmark artifacts, split
as 1,974 under `test/`, 54 under `pandoc-lua-engine/test/`, 247 under
`data/`, and 1 under `benchmark/`. The PHP writer now maps ten additional
focused checks from that source boundary: decimal marker escaping,
parenthesized decimal marker escaping, roman marker escaping, autonumber marker
escaping, dash marker escaping, plus marker escaping, percent/title-block-style
body escaping, and nested list-item paragraph marker escaping. The mapped
denominator is now 813 focused Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the Plain/Para marker-escaping
slice: `php -l` passed for `MarkdownWriter.php`, `MarkdownReaderTest.php`, and
`examples/wordpress-markdown-plain-marker-handoff.php`; `php
lanes/pandoc/examples/wordpress-markdown-plain-marker-handoff.php` emitted
escaped WordPress reviewer source markers including `1\.`, `\(2\)`, `\-`, and
`\%`; `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
passed 1 test file, 2,431 assertions, and 0 failures. The focused file now
contains 242 behavior tests.

Required root verification on 2026-05-23 after the Plain/Para marker-escaping
slice was left pending because this lane was not assigned no-argument root
verification. Per lane instructions, focused pandoc evidence was recorded and
root aggregate verification remains pending for the supervisor/integrator.

`src/Text/Pandoc/Writers/Markdown/Table.hs` was inspected for the bounded
`pandocTable` `numChars`/`minNumChars` branch. The targeted source boundary is
upstream lines 93-118 at cached commit
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`: widthless simple tables use
`numChars = offset + 2`, while width-bearing multiline tables use
`minOffset` for `WrapAuto` and `offset` for no-wrap output. The cloned static
inventory was revalidated with `git ls-tree` counts: 2,276 inspected upstream
test/data/benchmark artifacts, split as 1,974 under `test/`, 54 under
`pandoc-lua-engine/test/`, 247 under `data/`, and 1 under `benchmark/`. The
PHP writer now maps four additional focused checks from that source boundary:
East Asian wide characters count as two display columns for widthless table
rules, zero-width joiner/non-joiner and combining marks do not inflate table
padding, the simple-table rule keeps Pandoc's `+ 2` alignment padding, and a
WordPress Unicode reviewer table handoff stays native Markdown. The mapped
denominator is now 817 focused Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the pandocTable display-width
`numChars` slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and
`examples/wordpress-markdown-unicode-table-width-handoff.php`; `php
lanes/pandoc/examples/wordpress-markdown-unicode-table-width-handoff.php`
emitted a native Pandoc simple table with CJK source labels and zero-width
import tokens aligned by display width; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,436
assertions, and 0 failures. The focused file now contains 243 behavior tests.

Required root verification on 2026-05-23 after the pandocTable display-width
`numChars` slice was left pending because this lane was not assigned
no-argument root verification. Per lane instructions, focused pandoc evidence
was recorded and root aggregate verification remains pending for the
supervisor/integrator.

`src/Text/Pandoc/Writers/Markdown.hs` was inspected for the ordered-list
paragraph-before-fenced-Div boundary. The targeted source boundary is
`blockListToMarkdown` `fixBlocks` plus `orderedListItemToMarkdown` at cached
commit `0640c4c9859aa5a3ede082c190fcd5883c24ac83`: paragraph blocks before a
following fenced `Div` keep a blank block boundary, and ordered-list item
continuation indentation is derived from the rendered marker width. The cloned
static inventory was revalidated with `git ls-tree` counts: 2,276 inspected
upstream test/data/benchmark artifacts, split as 1,974 under `test/`, 54 under
`pandoc-lua-engine/test/`, 247 under `data/`, and 1 under `benchmark/`. The PHP
writer now maps four additional focused assertions from that source boundary,
covering:
paragraph-before-Div blank separation, fenced Div retention inside the list
item, three-digit ordered marker continuation indentation, and a WordPress
reviewer checklist handoff example. The mapped denominator is now 821 focused
Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the ordered-list
paragraph-before-fenced-Div slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and
`examples/wordpress-markdown-ordered-list-div-handoff.php`; `php
lanes/pandoc/examples/wordpress-markdown-ordered-list-div-handoff.php` emitted
a native Pandoc Markdown ordered checklist item with a separated, marker-width
indented fenced Div; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,439
assertions, and 0 failures. The focused file now contains 244 behavior tests.

Required root verification on 2026-05-23 after the ordered-list
paragraph-before-fenced-Div slice was left pending because this lane was not
assigned no-argument root verification. Per lane instructions, focused pandoc
evidence was recorded and root aggregate verification remains pending for the
supervisor/integrator.

`src/Text/Pandoc/Writers/Markdown/Table.hs` was inspected for the bounded
`pipeTable` positional width branch. The targeted source boundary is upstream
lines 37-74 at cached commit `0640c4c9859aa5a3ede082c190fcd5883c24ac83`:
`pipeWidths` checks `not (all (== 0) widths)` and maps the original `widths`
list directly, so default-width zero columns keep their slots instead of being
compacted before relative delimiter sizing. The cloned static inventory was
revalidated with `git ls-tree` counts: 2,276 inspected upstream
test/data/benchmark artifacts, split as 1,974 under `test/`, 54 under
`pandoc-lua-engine/test/`, 247 under `data/`, and 1 under `benchmark/`. The
PHP writer now maps four additional focused checks from that source boundary:
mixed zero/relative width hints are preserved positionally, the first
default-width pipe delimiter stays minimal, later relative-width delimiters
keep their own 25 percent and 75 percent slots, and a multilingual WordPress
reviewer handoff example stays Markdown-native. The mapped denominator is now
825 focused Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the pipe default-width slice:
`php -l` passed for `MarkdownWriter.php`, `MarkdownReaderTest.php`, and
`examples/wordpress-markdown-pipe-default-width-handoff.php`; `php
lanes/pandoc/examples/wordpress-markdown-pipe-default-width-handoff.php`
emitted a narrow WordPress reviewer pipe table whose first default-width column
kept a minimal `--` delimiter and whose later weighted review columns kept
their relative delimiter widths; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,444
assertions, and 0 failures. The focused file now contains 245 behavior tests.

Required root verification on 2026-05-23 after the pipe default-width slice was
left pending because this lane was not assigned no-argument root verification.
Per lane instructions, focused pandoc evidence was recorded and root aggregate
verification remains pending for the supervisor/integrator.

`src/Text/Pandoc/Writers/Markdown.hs`, `src/Text/Pandoc/Options.hs`, and
`src/Text/Pandoc/Writers/Markdown/Table.hs` were inspected for the bounded
table-caption wrapping branch. The targeted source boundary is upstream
`pandocToMarkdown` lines 213-216, where `WrapAuto` sets the DocLayout render
width to `writerColumns`, plus the `Table` writer lines 628-636 and 657-679 at
cached commit `0640c4c9859aa5a3ede082c190fcd5883c24ac83`, where caption
inlines and table attributes are combined into `caption'''` and emitted after
simple, pipe, multiline, and grid table output. `Text.Pandoc.Options` lines
267-280 and 412-413 were also checked for the `WrapAuto`, `WrapNone`, and
`WrapPreserve` option semantics and default `writerColumns = 72`. The cloned
static inventory was revalidated with `git ls-tree` counts: 2,276 inspected
upstream test/data/benchmark artifacts, split as 1,974 under `test/`, 54 under
`pandoc-lua-engine/test/`, 247 under `data/`, and 1 under `benchmark/`. The
PHP writer now maps six additional focused checks from that source boundary:
auto-wrapped pipe-table captions respect configured columns, continuation
caption lines remain inside the table-caption block, explicit `wrap=none`
keeps captions unwrapped, `hardLineBreaks` forces the same no-wrap behavior,
long inline-link captions wrap in simple and pipe table output, and a
WordPress pipe-table caption handoff example stays Markdown-native. The mapped
denominator is now 831 focused Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the pipe caption-wrap slice:
`php -l` passed for `MarkdownWriter.php`, `MarkdownReaderTest.php`, and
`examples/wordpress-markdown-pipe-caption-wrap-handoff.php`; `php
lanes/pandoc/examples/wordpress-markdown-pipe-caption-wrap-handoff.php`
emitted a narrow WordPress reviewer pipe table whose caption wrapped before the
caption attribute tuple; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,450
assertions, and 0 failures. The focused file now contains 246 behavior tests.

Required root verification on 2026-05-23 after the pipe caption-wrap slice was
left pending because this lane was not assigned no-argument root verification.
Per lane instructions, focused pandoc evidence was recorded and root aggregate
verification remains pending for the supervisor/integrator.

`src/Text/Pandoc/Writers/Markdown.hs`, `src/Text/Pandoc/Writers/Markdown/Table.hs`,
`src/Text/Pandoc/Extensions.hs`, and `src/Text/Pandoc/Options.hs` were inspected
for the disabled `Ext_table_captions` / CommonMark table-caption boundary. The
targeted source boundary is the `caption'''` branch in the Markdown writer:
caption text plus table attrs are still emitted after table output, but the
Pandoc `: ` caption marker is omitted when `Ext_table_captions` is disabled.
The `writeCommonMark` path and `commonmark`/`commonmark_x` extension defaults
were also checked: CommonMark profiles do not include `Ext_table_captions`,
while `WrapAuto`, `WrapNone`, and `hard_line_breaks` still follow the same
writer wrapping controls. The cloned static inventory was revalidated with
`git ls-tree` counts: 2,276 inspected upstream test/data/benchmark artifacts,
split as 1,974 under `test/`, 54 under `pandoc-lua-engine/test/`, 247 under
`data/`, and 1 under `benchmark/`. The PHP writer now maps five additional
focused checks from that source boundary: explicit disabled `tableCaptions`
keeps simple-table caption text without `: `, explicit disabled
`tableCaptions` keeps pipe-table caption text without `: `, caption attrs
remain attached to the no-colon caption text, `variant => commonmark` follows
the same no-colon path, and a WordPress CommonMark-flavored reviewer handoff
example stays Markdown-native. The mapped denominator is now 836 focused
Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the disabled table-caption /
CommonMark slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and
`examples/wordpress-markdown-commonmark-caption-handoff.php`; `php
lanes/pandoc/examples/wordpress-markdown-commonmark-caption-handoff.php`
emitted a CommonMark-flavored WordPress reviewer pipe table whose caption text
remained visible without a Pandoc colon marker while keeping caption attrs;
`php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1
test file, 2,456 assertions, and 0 failures. The focused file now contains
247 behavior tests.

Required root verification on 2026-05-23 after the disabled table-caption /
CommonMark slice was left pending because this lane was not assigned
no-argument root verification. Per lane instructions, focused pandoc evidence
was recorded and root aggregate verification remains pending for the
supervisor/integrator.

`src/Text/Pandoc/Writers/Markdown/Inline.hs`,
`src/Text/Pandoc/Writers/Markdown.hs`, and `src/Text/Pandoc/Extensions.hs`
were inspected again for the CommonMark writer variant around `RawInline` and
`LineBreak`. The targeted upstream boundary is the `Commonmark` branch where
only `gfm`, `commonmark`, `commonmark_x`, and `markdown` raw inline formats
render literally, while other raw formats continue through raw-attribute,
raw-HTML, raw-TeX, or not-rendered fallbacks. The `LineBreak` branch also
forces backslash hard-break output for CommonMark unless `Ext_hard_line_breaks`
is enabled. The CommonMark extension defaults were checked: `commonmark`
defaults raw HTML on but raw attributes/raw TeX off, while `commonmark_x`
includes raw attributes but still not raw TeX. The cloned static inventory was
revalidated with `git ls-tree` counts: 2,276 inspected upstream
test/data/benchmark artifacts, split as 1,974 under `test/`, 54 under
`pandoc-lua-engine/test/`, 247 under `data/`, and 1 under `benchmark/`. The
PHP writer now maps nine additional focused checks from that source boundary:
CommonMark hard breaks ignore disabled `escapedLineBreaks`, `hardLineBreaks`
still emits a plain newline, `commonmark_x` and `gfm` raw inline formats render
literally, `markdown_github` is omitted by default under `commonmark`, raw HTML
renders literally, raw TeX is omitted by default, explicit `rawAttribute`
renders raw TeX as raw-attribute Markdown, and `variant=commonmark_x` defaults
raw attributes on for non-CommonMark raw inline formats. The mapped denominator
is now 845 focused Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the CommonMark raw-inline /
linebreak slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and
`examples/wordpress-markdown-commonmark-raw-handoff.php`; `php
lanes/pandoc/examples/wordpress-markdown-commonmark-raw-handoff.php` emitted
raw CommonMark/HTML reviewer spans and a backslash hard break despite
`escapedLineBreaks=false`; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,465
assertions, and 0 failures. The focused file now contains 248 behavior tests.

Required root verification on 2026-05-23 after the CommonMark raw-inline /
linebreak slice was left pending because this lane was not assigned
no-argument root verification. Per lane instructions, focused pandoc evidence
was recorded and root aggregate verification remains pending for the
supervisor/integrator.

`src/Text/Pandoc/Writers/Markdown.hs`,
`src/Text/Pandoc/Writers/Markdown/Inline.hs`, and
`src/Text/Pandoc/Extensions.hs` were inspected again for the CommonMark writer
variant around block-level `RawBlock` output. The targeted upstream boundary
is the `Commonmark` branch in `blockToMarkdown'`, where only `gfm`,
`commonmark`, `commonmark_x`, and `markdown` raw block formats render
literally; raw HTML renders literally through `removeBlankLinesInHTML`;
unsupported raw formats continue through raw-attribute or not-rendered
fallbacks; and CommonMark defaults raw HTML on while `commonmark_x` also
defaults raw attributes on. The cloned static inventory was revalidated with
`git ls-tree` counts: 2,276 inspected upstream test/data/benchmark artifacts,
split as 1,974 under `test/`, 54 under `pandoc-lua-engine/test/`, 247 under
`data/`, and 1 under `benchmark/`. The PHP writer now maps eight additional
focused checks from that source boundary: CommonMark/commonmark_x/GFM/markdown
raw block formats render literally, `markdown_github` is omitted by default
under `commonmark`, explicit `rawAttribute` and `commonmark_x` fence
unsupported raw block formats, raw HTML remains literal even when generic
`rawHtml` is disabled and converts blank source lines to `&#10;`, raw TeX is
omitted by default under `commonmark`, and raw TeX is fenced under
`commonmark_x`. The mapped denominator is now 855 focused
Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the CommonMark raw-block slice:
`php -l` passed for `MarkdownWriter.php`, `MarkdownReaderTest.php`, and
`examples/wordpress-markdown-commonmark-raw-block-handoff.php`; `php
lanes/pandoc/examples/wordpress-markdown-commonmark-raw-block-handoff.php`
emitted block-level CommonMark-compatible source HTML, Pandoc-style raw HTML
blank-line escaping, and no GitHub-only raw Markdown promotion under strict
CommonMark; `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
passed 1 test file, 2,475 assertions, and 0 failures. The focused file now
contains 249 behavior tests.

Required root verification on 2026-05-23 after the CommonMark raw-block slice
was left pending because this lane was not assigned no-argument root
verification. Per lane instructions, focused pandoc evidence was recorded and
root aggregate verification remains pending for the supervisor/integrator.

`src/Text/Pandoc/Writers/Markdown.hs`, `src/Text/Pandoc/Shared.hs`, and
`src/Text/Pandoc/Extensions.hs` were inspected again for the Markdown writer
`LineBlock` branch. The targeted upstream boundary is `blockToMarkdown' opts
(LineBlock lns)`: when `Ext_line_blocks` is enabled, each rendered line is
emitted with a `| ` marker through a hanging indent; when the extension is
disabled, Pandoc falls back through `linesToPara`, which combines line entries
with hard `LineBreak` nodes. The CommonMark/default-extension boundary was also
checked: Pandoc markdown enables `Ext_line_blocks`, while `commonmark` and
`commonmark_x` default extension sets do not include it. The cloned static
inventory was revalidated with `git ls-tree` counts: 2,276 inspected upstream
test/data/benchmark artifacts, split as 1,974 under `test/`, 54 under
`pandoc-lua-engine/test/`, 247 under `data/`, and 1 under `benchmark/`. The PHP
writer now maps four additional focused checks from that source boundary:
pipe-prefixed line-block output, bare pipe output for empty line entries,
nonbreaking indentation preservation, disabled-lineBlocks/CommonMark
hard-break fallback, and an explicit CommonMark `lineBlocks` opt-in. The mapped
denominator is now 859 focused Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the Markdown writer LineBlock
slice: `php -l` passed for `MarkdownWriter.php`, `MarkdownReaderTest.php`, and
`examples/wordpress-markdown-line-block-handoff.php`; `php
lanes/pandoc/examples/wordpress-markdown-line-block-handoff.php` emitted a
pipe-prefixed WordPress reviewer stanza with nonbreaking source indentation and
an empty line entry; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,479
assertions, and 0 failures. The focused file now contains 250 behavior tests.

Required root verification on 2026-05-23 after the Markdown writer LineBlock
slice was left pending because this lane was not assigned no-argument root
verification. Per lane instructions, focused pandoc evidence was recorded and
root aggregate verification remains pending for the supervisor/integrator.

`src/Text/Pandoc/Writers/Markdown.hs` and
`src/Text/Pandoc/Writers/Markdown/Inline.hs` were inspected again for the
PlainText writer boundary used by `writePlain`. The targeted upstream branch is
`blockToMarkdown' opts (LineBlock lns)`, where `PlainText` is handled before
`Ext_line_blocks`: each line is rendered through `inlineListToMarkdown`, empty
line entries become blank lines, and no `| ` markers are emitted. Targeted
inline reads covered the matching PlainText branches for spans/emphasis,
strong text, code, `Str`, and links: formatting is stripped, code is emitted as
literal text, and URI/e-mail autolinks render as text. The cloned static
inventory was revalidated with `git ls-tree` counts: 2,276 inspected upstream
test/data/benchmark artifacts, split as 1,974 under `test/`, 54 under
`pandoc-lua-engine/test/`, 247 under `data/`, and 1 under `benchmark/`. The
PHP writer now maps six additional focused checks from that source boundary:
PlainText line blocks omit pipe markers, preserve empty line entries as blank
lines, preserve nonbreaking indentation, strip emphasis/link Markdown around
inline content, keep code spans literal, emit URI autolinks as text, and ignore
`lineBlocks` extension toggles. The mapped denominator is now 865 focused
Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the PlainText line-block slice:
`php -l` passed for `MarkdownWriter.php`, `MarkdownReaderTest.php`, and
`examples/wordpress-plain-line-block-handoff.php`; `php
lanes/pandoc/examples/wordpress-plain-line-block-handoff.php` emitted a
plain-text WordPress reviewer stanza without Pandoc pipe markers while
preserving nonbreaking source indentation and an empty line entry; `php
tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test
file, 2,481 assertions, and 0 failures. The focused file now contains 251
behavior tests.

Required root verification on 2026-05-23 after the PlainText line-block slice
was left pending because this lane was not assigned no-argument root
verification. Per lane instructions, focused pandoc evidence was recorded and
root aggregate verification remains pending for the supervisor/integrator.

`src/Text/Pandoc/Writers/Markdown.hs` was inspected again for the PlainText
block branches used by `writePlain`. The targeted upstream boundaries are
`blockToMarkdown' opts (Plain inlines)`, `RawBlock`, `HorizontalRule`,
`Header`, and `BlockQuote`, plus the PlainText `commentSep` branch in
`blockListToMarkdown`: plain/paragraph blocks render through PlainText inline
semantics, `Header` emits text without ATX/setext markers or attrs,
`BlockQuote` uses a two-space leader, `RawBlock (Format "plain")` renders
literally while other raw formats do not render in PlainText, horizontal rules
use a dash run sized by `writerColumns`, and adjacent same-type lists do not
receive raw HTML comments. The cloned static inventory was revalidated with
`git ls-tree` counts: 2,276 inspected upstream test/data/benchmark artifacts,
split as 1,974 under `test/`, 54 under `pandoc-lua-engine/test/`, 247 under
`data/`, and 1 under `benchmark/`. The PHP writer now maps eight additional
focused checks from that source boundary. The mapped denominator is now 873
focused Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the PlainText block slice:
`php -l` passed for `MarkdownWriter.php`, `MarkdownReaderTest.php`, and
`examples/wordpress-plain-review-blocks-handoff.php`; `php
lanes/pandoc/examples/wordpress-plain-review-blocks-handoff.php` emitted
unmarked plain headings, source paragraph label text without Markdown link
markup, a two-space-indented quote note, a literal plain raw review packet, and
a writer-column dash separator; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,482
assertions, and 0 failures. The focused file now contains 252 behavior tests.

Required root verification on 2026-05-23 after the PlainText block slice was
left pending because this lane was not assigned no-argument root verification.
Per lane instructions, focused pandoc evidence was recorded and root aggregate
verification remains pending for the supervisor/integrator.

`src/Text/Pandoc/Writers/Markdown.hs`,
`src/Text/Pandoc/Writers/Markdown/Inline.hs`, and `test/writer.plain` were
inspected again for the PlainText writer list and DefinitionList boundary used
by `writePlain`. The targeted upstream branches are
`bulletListItemToMarkdown`, `orderedListItemToMarkdown`, and
`definitionListItemToMarkdown`: list item contents are rendered through
`blockListToMarkdown`, so inline emphasis/links/code use PlainText inline
semantics; DefinitionList labels are rendered as `Plain` blocks, the
PlainText branch uses a space leader instead of `:`, the effective leader is
two spaces in the upstream writer.plain fixture, loose definitions preserve
the blank term/body boundary, and nested code/blockquote bodies are indented
under that two-space definition leader. The cloned static inventory was
revalidated with `git ls-tree` counts: 2,276 inspected upstream
test/data/benchmark artifacts, split as 1,974 under `test/`, 54 under
`pandoc-lua-engine/test/`, 247 under `data/`, and 1 under `benchmark/`. The
PHP writer now maps eight additional focused checks from that source boundary:
PlainText bullet-list inline formatting stripping, ordered-list paragraph
inline formatting stripping, DefinitionList term formatting stripping,
two-space definition leaders without Markdown `:` markers, tight multiple
definition output, loose definition term/body spacing, nested code indentation,
and nested blockquote indentation without Markdown quote markers. The mapped
denominator is now 881 focused Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the PlainText
list/DefinitionList slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and
`examples/wordpress-plain-definition-list-handoff.php`; `php
lanes/pandoc/examples/wordpress-plain-definition-list-handoff.php` emitted a
plain import glossary with stripped term/link/emphasis text, two-space
definition leaders, a nested shortcode code block, and a visibly indented
plain quote note; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,485
assertions, and 0 failures. The focused file now contains 253 behavior tests.

Required root verification on 2026-05-23 after the PlainText
list/DefinitionList slice was left pending because this lane was not assigned
no-argument root verification. Per lane instructions, focused pandoc evidence
was recorded and root aggregate verification remains pending for the
supervisor/integrator.

`src/Text/Pandoc/Writers/Markdown/Inline.hs`,
`src/Text/Pandoc/Writers/Markdown.hs`, and `test/writer.plain` were inspected
again for the PlainText Image and Note boundary used by `writePlain`. The
targeted upstream branches are `inlineToMarkdown` for `Image`, `Link`, and
`Note`, `notesToMarkdown`/`noteToMarkdown`, and the `Figure` implicit-image
branch: PlainText images render the link text inside square brackets, source
URL labels collapse to an empty bracket guard, note references render as
numeric bracket labels, and plain note definitions use bracket labels without
Markdown footnote caret/colon syntax. The cloned static inventory was
revalidated with `git ls-tree` counts: 2,276 inspected upstream
test/data/benchmark artifacts, split as 1,974 under `test/`, 54 under
`pandoc-lua-engine/test/`, 247 under `data/`, and 1 under `benchmark/`. The
PHP writer now maps six additional focused checks from that source boundary:
implicit figure caption brackets, image source-label guard brackets, numeric
inline note references, numeric plain note definitions without Markdown
footnote markers, PlainText inline stripping inside note bodies, and
multi-block note body paragraph/code boundaries. The mapped denominator is now
887 focused Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the PlainText image/note slice:
`php -l` passed for `MarkdownWriter.php`, `MarkdownReaderTest.php`, and
`examples/wordpress-plain-media-note-handoff.php`; `php
lanes/pandoc/examples/wordpress-plain-media-note-handoff.php` emitted an
unmarked plain heading, a bracketed image caption, a numeric note reference, a
stripped source edit link label, and an indented code note body; `php
tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test
file, 2,488 assertions, and 0 failures. The focused file now contains 254
behavior tests.

Required root verification on 2026-05-23 after the PlainText image/note slice
was left pending because this lane was not assigned no-argument root
verification. Per lane instructions, focused pandoc evidence was recorded and
root aggregate verification remains pending for the supervisor/integrator.

`test/Tests/Writers/Plain.hs`, `src/Text/Pandoc/Writers/Markdown/Inline.hs`,
and `src/Text/Pandoc/Shared.hs` were inspected again for the PlainText
Gutenberg inline boundary used by `writePlain`. The targeted upstream test is
`strongly emphasized text to uppercase`, which enables `Ext_gutenberg` on the
plain writer and expects `Strong [Str "Straße"]` to render as `STRASSE`.
Targeted source reads covered the PlainText `Emph` and `Strong` branches:
`Emph` emits underscore delimiters only when Gutenberg is enabled, nested
`Emph [Emph ...]` collapses before delimiter output, and `Strong` renders
`inlineListToMarkdown` over `capitalize`d inline children. `capitalize`
uppercases only regular `Str` nodes recursively, expanding lowercase characters
such as German sharp-s correctly while preserving code-span source tokens. The
cloned static inventory was revalidated with `git ls-tree` counts: 2,276
inspected upstream test/data/benchmark artifacts, split as 1,974 under
`test/`, 54 under `pandoc-lua-engine/test/`, 247 under `data/`, and 1 under
`benchmark/`. The PHP writer now maps four additional focused checks from
that source boundary: Unicode-safe strong uppercase with `Straße` -> `STRASSE`,
recursive uppercase of link-label/text content inside strong while code tokens
stay literal, Gutenberg-only underscore output for PlainText emphasis, and
nested PlainText emphasis collapse. The mapped denominator is now 891 focused
Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the PlainText Gutenberg inline
slice: `php -l` passed for `MarkdownWriter.php`, `MarkdownReaderTest.php`, and
`examples/wordpress-plain-gutenberg-handoff.php`; `php
lanes/pandoc/examples/wordpress-plain-gutenberg-handoff.php` emitted an
unmarked plain heading, uppercase `MEDIA PRÜFUNG` and `SOURCE EDITOR VIA`
review text, preserved `wp_update_post`, and underscore-delimited
`review-only emphasis`; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,493
assertions, and 0 failures. The focused file now contains 255 behavior tests.

Required root verification on 2026-05-23 after the PlainText Gutenberg inline
slice was left pending because this lane was not assigned no-argument root
verification. Per lane instructions, focused pandoc evidence was recorded and
root aggregate verification remains pending for the supervisor/integrator.

`src/Text/Pandoc/Writers/Markdown.hs`,
`src/Text/Pandoc/Writers/Markdown/Inline.hs`,
`src/Text/Pandoc/Writers/Markdown/Table.hs`, and
`src/Text/Pandoc/Extensions.hs` were inspected again for the PlainText table
boundary used by `writePlain`. The targeted upstream branch is
`blockToMarkdown'` for `Table`: it computes `caption'` with
`inlineListToMarkdown`, appends table attrs through `attrsToMarkdown`, then
reuses that caption after simple, pipe, multiline, grid, approximate spanned
pipe, and `[TABLE]` fallback output. Under the PlainText writer environment,
`inlineListToMarkdown` strips strong/link/code Markdown syntax and link
destinations from cells and captions while preserving table attrs. The cloned
static inventory was revalidated with `git ls-tree` counts: 2,276 inspected
upstream test/data/benchmark artifacts, split as 1,974 under `test/`, 54 under
`pandoc-lua-engine/test/`, 247 under `data/`, and 1 under `benchmark/`. The
PHP writer now maps eight additional focused checks from that source boundary:
plain simple-table cell stripping, plain caption stripping, caption attrs,
stripped link destinations, stripped code ticks, approximate pipe fallback for
spanned cells, `[TABLE]` fallback caption emission, and unsupported table
handoff without raw HTML. The mapped denominator is now 899 focused
Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the PlainText table fallback
slice: `php -l` passed for `MarkdownWriter.php`, `MarkdownReaderTest.php`, and
`examples/wordpress-plain-table-fallback-handoff.php`; `php
lanes/pandoc/examples/wordpress-plain-table-fallback-handoff.php` emitted a
plain paragraph, `[TABLE]`, and a plain source caption with attrs while
omitting Markdown link/code syntax and admin URLs; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,501
assertions, and 0 failures. The focused file now contains 256 behavior tests.

Required root verification on 2026-05-23 after the PlainText table fallback
slice was left pending because this lane was not assigned no-argument root
verification. Per lane instructions, focused pandoc evidence was recorded and
root aggregate verification remains pending for the supervisor/integrator.

`src/Text/Pandoc/Writers/Markdown.hs`, `data/templates/default.plain`, and
`test/writer.plain` were inspected again for the PlainText template titleblock
boundary. The targeted upstream path is `pandocToMarkdown`: when
`writerTemplate` is present and the writer variant is `PlainText`, Pandoc uses
`plainTitleBlock` to render title, semicolon-joined authors, and date into the
template's `titleblock` field before body output. The corresponding default
plain template emits `titleblock`, a blank line, then `body`; `test/writer.plain`
starts with `Pandoc Test Suite`, `John MacFarlane; Anonymous`, and the date
before the suite body. The cloned static inventory was revalidated with
`git ls-tree` counts: 2,276 inspected upstream test/data/benchmark artifacts,
split as 1,974 under `test/`, 54 under `pandoc-lua-engine/test/`, 247 under
`data/`, and 1 under `benchmark/`. The PHP writer now maps six additional
focused checks from that source boundary: template-only plain titleblock
emission, unchanged body-only writePlain output without a template, title inline
PlainText stripping, semicolon author joining with link/emphasis stripping,
date code-span stripping, and `standalone` as an alias for template output. The
mapped denominator is now 905 focused Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the PlainText template
titleblock slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and `examples/wordpress-plain-titleblock-handoff.php`;
`php lanes/pandoc/examples/wordpress-plain-titleblock-handoff.php` emitted a
plain metadata header with title, semicolon-joined authors, date, and body text
while omitting Markdown link/code syntax and admin URLs; `php
tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test
file, 2,507 assertions, and 0 failures. The focused file now contains 257
behavior tests.

Required root verification on 2026-05-23 after the PlainText template
titleblock slice was left pending because this lane was not assigned
no-argument root verification. Per lane instructions, focused pandoc evidence
was recorded and root aggregate verification remains pending for the
supervisor/integrator.

`src/Text/Pandoc/Writers/Markdown.hs`,
`src/Text/Pandoc/Writers/Shared.hs`, `src/Text/Pandoc/Options.hs`, and
`data/templates/default.plain` were inspected again for the PlainText template
include-variable boundary. The targeted upstream path is `pandocToMarkdown`:
when `writerTemplate` is present it builds a template context with `body`,
`toc`/`table-of-contents`, `titleblock`, writer variables, and metadata.
`addVariablesToContext` gives writer variables precedence over metadata, while
metadata values are rendered through the same block/inline writer. The default
plain template is 21 lines and emits `header-includes`, `include-before`,
`table-of-contents`, `body`, and `include-after` in that order. The cloned
static inventory was revalidated with `git ls-tree` counts: 2,276 inspected
upstream test/data/benchmark artifacts, split as 1,974 under `test/`, 54 under
`pandoc-lua-engine/test/`, 247 under `data/`, and 1 under `benchmark/`. The
PHP writer now maps seven additional focused checks from that source boundary:
header-includes before body, multiple include-before entries before body,
include-after after body notes/references, writer-variable precedence over
metadata, raw writer-variable string output, metadata include-after rendering
through PlainText inline semantics, and unchanged body-only writePlain output
when no template is enabled. The mapped denominator is now 912 focused
Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the PlainText template
include-variable slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and
`examples/wordpress-plain-template-include-handoff.php`; `php
lanes/pandoc/examples/wordpress-plain-template-include-handoff.php` emitted a
plain WordPress import audit packet with header-includes, two include-before
entries, plain body text, and a metadata-derived include-after footer while
omitting admin URLs and Markdown code ticks from metadata-rendered text; `php
tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test
file, 2,513 assertions, and 0 failures. The focused file now contains 258
behavior tests.

Root aggregate verification on 2026-05-23 after the PlainText template
include-variable slice was not assigned to this lane worker, so no
no-argument root harness was started. Per lane instructions, focused pandoc
evidence was recorded and root aggregate verification remains pending for the
supervisor/integrator.

`src/Text/Pandoc/Writers/Markdown.hs`,
`src/Text/Pandoc/Writers/Shared.hs`, `src/Text/Pandoc/Chunks.hs`,
`src/Text/Pandoc/Options.hs`, `data/templates/default.plain`, and
`test/command/toc.md` were inspected again for the PlainText template
table-of-contents boundary. The targeted upstream path is `pandocToMarkdown`:
when `writerTableOfContents` is enabled it builds `toc` and
`table-of-contents` context fields from `toTableOfContents`; the default
plain template emits that field before `body`; `writerTOCDepth` bounds nested
entries; `tocEntryToLink` wraps heading text in links with `toc-...` anchors;
and PlainText output strips those link destinations and attrs while retaining
the heading labels. The cloned static inventory was revalidated with
`git ls-tree` counts: 2,276 inspected upstream test/data/benchmark artifacts,
split as 1,974 under `test/`, 54 under `pandoc-lua-engine/test/`, 247 under
`data/`, and 1 under `benchmark/`. The PHP writer now maps eight additional
focused checks from that source boundary: template-only TOC emission before
body, unchanged body-only writePlain output when no template is enabled,
TOC-depth limiting, heading-link destination stripping, generated TOC-anchor
stripping, source link attribute stripping, code tick stripping in TOC labels,
and unlisted heading omission when no section number is present. The mapped
denominator is now 920 focused Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the PlainText template TOC
slice: `php -l` passed for `MarkdownWriter.php`, `MarkdownReaderTest.php`, and
`examples/wordpress-plain-toc-handoff.php`; `php
lanes/pandoc/examples/wordpress-plain-toc-handoff.php` emitted a plain
WordPress import audit packet with a nested TOC before body text while omitting
source edit URLs, generated TOC anchors, and code ticks from TOC labels; `php
tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test
file, 2,520 assertions, and 0 failures. The focused file now contains 259
behavior tests.

Root aggregate verification on 2026-05-23 after the PlainText template TOC
slice was not assigned to this lane worker, so no no-argument root harness was
started. Per lane instructions, focused pandoc evidence was recorded and root
aggregate verification remains pending for the supervisor/integrator.

`src/Text/Pandoc/Writers/Markdown.hs`,
`src/Text/Pandoc/Writers/Shared.hs`, `src/Text/Pandoc/Chunks.hs`,
`src/Text/Pandoc/Shared.hs`, `src/Text/Pandoc/Options.hs`,
`data/templates/default.plain`, and `test/command/toc.md` were inspected again
for the PlainText numbered table-of-contents boundary. The targeted upstream
path is `toTableOfContents`: it calls `makeSections writerNumberSections`,
then `toTOCTree`, then `tocToList`. `makeSections` adds `number` attributes
when numbering is enabled and the heading is not `unnumbered`; explicit
`number` attrs are preserved; unnumbered headings do not advance the section
counter; and `toTOCTree` excludes `unlisted` headings only when no section
number is present. `tocEntryToLink` emits the `toc-section-number` span only
when numbering is requested, so plain output includes labels such as `1.1`
only in numbered TOCs.

The PHP writer now maps six additional focused checks from that source
boundary: generated section numbers render in PlainText TOC labels when
`numberSections` is enabled, generated section numbers keep `unlisted` headings
visible, `unnumbered` headings stay visible without advancing counters,
explicit heading `number` attrs render when numbering is enabled,
explicit-number `unlisted` headings stay in the TOC when numbering labels are
disabled, and body heading output remains unnumbered/plain. The mapped
denominator is now 926 focused Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the PlainText numbered template
TOC slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and
`examples/wordpress-plain-numbered-toc-handoff.php`; `php
lanes/pandoc/examples/wordpress-plain-numbered-toc-handoff.php` emitted a
plain WordPress import audit packet with generated section numbers, a numbered
unlisted audit heading, an unnumbered appendix heading, and an explicit legacy
section number; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,526
assertions, and 0 failures. The focused file now contains 260 behavior tests.

Root aggregate verification on 2026-05-23 after the PlainText numbered
template TOC slice was not assigned to this lane worker, so no no-argument root
harness was started. Per lane instructions, focused pandoc evidence was
recorded and root aggregate verification remains pending for the
supervisor/integrator.

`src/Text/Pandoc/Writers/Markdown.hs`,
`src/Text/Pandoc/Writers/Shared.hs`, `data/templates/default.plain`, and
`MANUAL.txt` were inspected again for the PlainText template body context
boundary. The targeted upstream path is `pandocToMarkdown`: it builds `main`
from the rendered body plus notes/references, then inserts that value with
`defField "body"` after `addVariablesToContext`. Because `defField` only
fills missing fields, writer variables and metadata fields named `body` can
override the automatic body. The Pandoc manual also documents `body` as an
automatic variable that users can modify, and the default plain template emits
`$body$` between `table-of-contents` and `include-after`.

The PHP writer now maps seven additional focused checks from that source
boundary: metadata `body` fields replace automatic body output in PlainText
template rendering, metadata body block values render through PlainText
link/code/list semantics, writer `body` variables take precedence over metadata
body fields, writer body variables are emitted as raw template values,
`include-after` remains after the overridden body, the original document body
is suppressed when a body context override is present, and the shared context
lookup now also applies variable/option/metadata precedence to `titleblock`.
The titleblock path is covered by two focused checks for raw writer-variable
output and precedence over generated metadata titleblocks. The mapped
denominator is now 935 focused Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the PlainText template body
context override slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and
`examples/wordpress-plain-body-override-handoff.php`; `php
lanes/pandoc/examples/wordpress-plain-body-override-handoff.php` emitted a
plain WordPress import audit packet with a template-provided redacted body and
a metadata-derived footer; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,535
assertions, and 0 failures. The focused file now contains 261 behavior tests.

Root aggregate verification on 2026-05-23 after the PlainText template body
context override slice was not assigned to this lane worker, so no no-argument
root harness was started. Per lane instructions, focused pandoc evidence was
recorded and root aggregate verification remains pending for the
supervisor/integrator.

`src/Text/Pandoc/Writers/Markdown.hs`,
`src/Text/Pandoc/Writers/Shared.hs`, `data/templates/default.plain`, and
`MANUAL.txt` were inspected again for the PlainText custom template
`meta-json` boundary. The targeted upstream path is `pandocToMarkdown` plus
`addVariablesToContext`: Pandoc builds metadata context first, inserts a
metadata-only `meta-json` value, overlays writer variables so they override
ordinary metadata fields, then supplies default `toc`, `table-of-contents`,
`body`, and `titleblock` fields with `defField`. The native PHP slice maps the
bounded custom-template subset needed by WordPress plain audit packets:
`$if(...)$`, `$for(...)$`, and `$name$` substitutions, metadata-only JSON,
PlainText rendering of metadata block values inside that JSON, generated
titleblock/body defaults, and writer-variable precedence over metadata in the
rendered custom template. The mapped denominator is now 944 focused
Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the PlainText custom template
`meta-json` slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and
`examples/wordpress-plain-meta-json-template-handoff.php`; `php
lanes/pandoc/examples/wordpress-plain-meta-json-template-handoff.php` emitted a
custom plain WordPress import audit packet with metadata JSON, a writer-variable
preface override, generated titleblock text, and a PlainText-rendered body;
`php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1
test file, 2,544 assertions, and 0 failures. The focused file now contains 262
behavior tests.

Root aggregate verification on 2026-05-23 after the PlainText custom template
`meta-json` slice was not assigned to this lane worker, so no no-argument root
harness was started. Per lane instructions, focused pandoc evidence was
recorded and root aggregate verification remains pending for the
supervisor/integrator.

`MANUAL.txt` Template syntax plus the existing
`src/Text/Pandoc/Writers/Markdown.hs`,
`src/Text/Pandoc/Writers/Shared.hs`, and `data/templates/default.plain`
PlainText context slices were inspected again for bounded doctemplate branch
behavior. The upstream manual documents dotted variables for structured
values, matched `${...}` delimiters with optional whitespace, `$if$` blocks
with `$else$`, `$for$` loops, `$sep$` loop separators, and the anaphoric
`it` keyword inside loops. The native PHP slice maps ten additional focused
checks from that boundary: dotted metadata lookup, `${...}` variable
delimiters, `$if$ true branches, absent-variable `$else$ branches, `$for$`
iteration over list values, `$sep$ output only between loop values,
`$it.field$` lookup inside loops, map truthiness, map values rendering as
`true` when interpolated directly, and PlainText body substitution inside a
custom template. The mapped denominator is now 954 focused
Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the PlainText custom template
branch slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and
`examples/wordpress-plain-template-branching-handoff.php`; `php
lanes/pandoc/examples/wordpress-plain-template-branching-handoff.php` emitted
a custom plain WordPress branch packet with dotted workflow metadata, an else
fallback, comma-separated reviewer recipients, and PlainText-rendered body
text; `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
passed 1 test file, 2,550 assertions, and 0 failures. The focused file now
contains 263 behavior tests.

Root aggregate verification on 2026-05-23 after the PlainText custom template
branch slice was not assigned to this lane worker, so no no-argument root
harness was started. Per lane instructions, focused pandoc evidence was
recorded and root aggregate verification remains pending for the
supervisor/integrator.

`MANUAL.txt` Template syntax plus the existing
`src/Text/Pandoc/Writers/Markdown.hs`,
`src/Text/Pandoc/Writers/Shared.hs`, and `data/templates/default.plain`
PlainText context slices were inspected again for bounded doctemplate nested
control behavior. The upstream manual documents matching `$...$` or `${...}`
delimiters, `$$` literal dollar output, `$--` comments through the end of a
line, `$if$`/`$elseif$`/`$else$`/`$endif$` conditionals, `$for$ loops,
top-level `$sep$` loop separators, dotted variables, and the anaphoric `it`
keyword. The native PHP slice maps thirteen additional focused checks from that
boundary: nested `for` blocks match their own `endfor` instead of an inner
loop terminator, outer loop separators ignore nested loop separators, nested
loop separators render only between inner values, scalar `it` values render
inside loops, true `if` branches inside loops render normally, `elseif`
fallback branches render when the initial conditional is false, final `else`
branches are suppressed after a true `elseif`, empty reviewer arrays select
the false branch, `$$` renders one
literal dollar, `$--` comments are omitted through end of line, template tokens
inside comments do not render, underscore-bearing variable names work in
conditionals, and braced whitespace-delimited variables still interpolate. The
mapped denominator is now 967 focused Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the PlainText custom template
nested-control slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and
`examples/wordpress-plain-template-nested-handoff.php`; `php
lanes/pandoc/examples/wordpress-plain-template-nested-handoff.php` emitted a
plain WordPress nested template packet with scalar labels through `it`,
phase/reviewer loops, an `elseif` fallback, literal dollar output, omitted
template comments, and a PlainText
body; `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
passed 1 test file, 2,561 assertions, and 0 failures. The focused file now
contains 265 behavior tests.

Root aggregate verification on 2026-05-23 after the PlainText custom template
nested-control slice was not assigned to this lane worker, so no no-argument
root harness was started. Per lane instructions, focused pandoc evidence was
recorded and root aggregate verification remains pending for the
supervisor/integrator.

`MANUAL.txt` Template syntax was inspected again for bounded doctemplate
partial behavior. The upstream manual documents partial calls as
`${ partial() }`, variable-applied partials as `${ variable:partial() }`,
bracket separators after variables or partials, the anaphoric `it` keyword
inside applied partials, omission of final newlines from included partials, and
partials including other partials. The native PHP slice maps seven additional
focused checks from that boundary: direct partial inclusion, variable-applied
reviewer partials, bracket separators for applied partial output, bracket
separators for array variables, map values rendered through `it` inside an
applied partial, nested partial inclusion, and final newline omission before
following template content. The mapped denominator is now 974 focused
Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the PlainText custom template
partial slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and
`examples/wordpress-plain-template-partial-handoff.php`; `php
lanes/pandoc/examples/wordpress-plain-template-partial-handoff.php` emitted a
custom plain WordPress partial template packet with variable-applied reviewer
partials, a nested footer partial, bracket separators, workflow metadata, and a
PlainText-rendered body; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,568
assertions, and 0 failures. The focused file now contains 266 behavior tests.

Root aggregate verification on 2026-05-23 after the PlainText custom template
partial slice was not assigned to this lane worker, so no no-argument root
harness was started. Per lane instructions, focused pandoc evidence was
recorded and root aggregate verification remains pending for the
supervisor/integrator.

`MANUAL.txt` Template syntax was inspected again for bounded doctemplate pipe
behavior. The upstream manual documents slash pipes after variables and
partials, use of pipes in `for` loop expressions such as
`$for(metadata/pairs)$`, chained pipes such as `$it.key/alpha/uppercase$`,
and predefined no-parameter pipes including `pairs`, `uppercase`,
`lowercase`, `length`, `reverse`, `first`, `last`, `rest`, `allbutlast`,
`chomp`, `alpha`, and `roman`. `src/Text/Pandoc/Templates.hs` was also
rechecked to confirm Pandoc delegates template compilation/rendering to the
`doctemplates` package, so this slice is MANUAL/doctemplates-semantics
inventory rather than an upstream Haskell runner pass. The native PHP slice
maps fifteen additional focused checks from that boundary: slash pipes parse
on variables, loop expressions, conditionals, and applied partial output;
`pairs` converts reviewer arrays to key/value maps with one-based list keys;
`uppercase` and `lowercase` transform text; `length` counts array values;
`first` and `last` select boundary values; `rest` and `allbutlast` can be
chained before a loop; `reverse` preserves transformed-array separator
rendering; `chomp` trims trailing newlines from body substitution; `alpha`
and `roman` convert integer-like text; chained `alpha/uppercase` labels
render as reviewer letters; and piped body values still use PlainText link
semantics. The mapped denominator is now 989 focused Markdown/HTML/WordPress
checks.

Focused local verification on 2026-05-23 after the PlainText custom template
pipe slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and
`examples/wordpress-plain-template-pipes-handoff.php`; `php
lanes/pandoc/examples/wordpress-plain-template-pipes-handoff.php` emitted a
custom plain WordPress pipe template packet with lower/uppercased workflow
metadata, label count/first-last/reverse output, alphabetic reviewer pair
labels, and a PlainText-rendered body; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,573
assertions, and 0 failures. The focused file now contains 267 behavior tests.

Root aggregate verification on 2026-05-23 after the PlainText custom template
pipe slice was not assigned to this lane worker, so no no-argument root
harness was started. Per lane instructions, focused pandoc evidence was
recorded and root aggregate verification remains pending for the
supervisor/integrator.

`MANUAL.txt` Template syntax was inspected again for bounded doctemplate
parameterized pipe behavior. The upstream manual documents
`left n "leftborder" "rightborder"`, `right n "leftborder" "rightborder"`,
and `center n "leftborder" "rightborder"` as textual-value pipes with
positive integer widths and optional quoted borders; it also states that
these pipes do not affect other values. `src/Text/Pandoc/Templates.hs` was
rechecked to confirm Pandoc delegates template compilation/rendering to the
`doctemplates` package, so this remains MANUAL/doctemplates-semantics
inventory rather than an upstream Haskell runner pass. The native PHP slice
maps twelve additional focused checks from that boundary: left alignment with
right padding, right alignment with left padding, centered padding, optional
left/right borders, quoted border escape handling, no truncation for over-wide
text, no effect on non-text map values, alignment after an `uppercase` chain,
fixed-width loop-row formatting, parameterized pipe parsing after variables,
PlainText body substitution before alignment, and reviewer-safe link stripping
inside aligned body output. The mapped denominator is now 1001 focused
Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the PlainText custom template
alignment-pipe slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and
`examples/wordpress-plain-template-align-handoff.php`; `php
lanes/pandoc/examples/wordpress-plain-template-align-handoff.php` emitted a
custom plain WordPress alignment template packet with padded batch metadata,
a centered workflow queue, fixed-width reviewer rows using left/right/center
pipes, and a PlainText-rendered body; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,580
assertions, and 0 failures. The focused file now contains 268 behavior tests.

Root aggregate verification on 2026-05-23 after the PlainText custom template
alignment-pipe slice was not assigned to this lane worker, so no no-argument
root harness was started. Per lane instructions, focused pandoc evidence was
recorded and root aggregate verification remains pending for the
supervisor/integrator.

`MANUAL.txt` Template syntax was inspected again for breakable-space and
`nowrap` pipe behavior. The upstream manual documents `$~$...$~$` regions as
template text whose spaces can break when a document is rendered with a short
line length, `chomp` as removing trailing newlines and breakable space, and
`nowrap` as disabling line wrapping on breakable spaces.
`src/Text/Pandoc/Templates.hs` was rechecked to confirm Pandoc delegates
template rendering to the `doctemplates` package, while
`src/Text/Pandoc/Writers/Markdown.hs`
confirms `writerColumns` is passed to DocLayout rendering only when
`writerWrapText` is `WrapAuto`. The native PHP slice maps eight additional
focused checks from that boundary: `$~$` breakable-space toggles parse without
leaking directives, breakable template spaces wrap at `writerColumns`,
ordinary template spaces remain nonbreakable, partial output can carry
breakable spaces, `/nowrap` disables wrapping on those breakable spaces,
`/chomp` removes trailing newlines plus breakable space, custom PlainText body
substitution remains reviewer-safe, and source admin URLs are stripped from
body output. The mapped denominator is now 1009 focused
Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the PlainText custom template
breakable-space/nowrap slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and
`examples/wordpress-plain-template-nowrap-handoff.php`; `php
lanes/pandoc/examples/wordpress-plain-template-nowrap-handoff.php` emitted a
custom plain WordPress packet with a wrapped breakable reviewer summary, a
protected no-wrap legal-hold line, a chomped readiness marker, and a
PlainText-rendered body; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,587
assertions, and 0 failures. The focused file now contains 269 behavior tests.

Root aggregate verification on 2026-05-23 after the PlainText custom template
breakable-space/nowrap slice was not assigned to this lane worker, so no
no-argument root harness was started. Per lane instructions, focused pandoc
evidence was recorded and root aggregate verification remains pending for the
supervisor/integrator.

`MANUAL.txt` Template syntax was inspected again for custom-template nesting.
The upstream manual documents the `$^$` directive as the way to nest subsequent
lines to the current output column, including multiline variable values and
following template lines aligned with the directive, and it documents automatic
nesting when a variable occurs by itself on an indented line and its value
contains multiple lines. `src/Text/Pandoc/Templates.hs` was rechecked to
confirm Pandoc delegates template rendering to the `doctemplates` package, so
this remains MANUAL/doctemplates-semantics inventory rather than an upstream
Haskell runner pass. The native PHP slice maps nine additional focused checks
from that boundary: `$^$` directives parse without leaking markers, multiline
variables indent continuation lines to the current output column, following
template lines aligned with the directive keep that nesting level, multiline
partials nest the same way, indented variables alone on a line nest
automatically, body substitution remains PlainText-rendered inside nested
templates, source admin URLs are stripped from body output, the WordPress
plain nesting handoff example emits aligned reviewer packet text, and the
static cloned denominator remains 2,276 upstream test/data/benchmark artifacts.
The mapped denominator is now 1018 focused Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the PlainText custom template
nesting slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and
`examples/wordpress-plain-template-nesting-handoff.php`; `php
lanes/pandoc/examples/wordpress-plain-template-nesting-handoff.php` emitted a
custom plain WordPress packet with a nested multiline review description,
aligned owner continuation, automatically nested summary variable, nested
legal-hold partial, and PlainText-rendered body; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,593
assertions, and 0 failures. The focused file now contains 270 behavior tests.

Root aggregate verification on 2026-05-23 after the PlainText custom template
nesting slice was not assigned to this lane worker, so no no-argument root
harness was started. Per lane instructions, focused pandoc evidence was
recorded and root aggregate verification remains pending for the
supervisor/integrator.

`MANUAL.txt` Template syntax and Pandoc's
`src/Text/Pandoc/Templates.hs` were inspected again at upstream pandoc commit
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`; Pandoc delegates compiled
template rendering to the `doctemplates >= 0.11 && < 0.12` package. A shallow
supplemental clone of `jgm/doctemplates` at
`2485bcbdb297d54d732006ca545a2a9e426f3817` was inspected for the dependency
boundary: 32 files under `test/`, including 22 `.test` fixture files, and
`test/nest.test` has 67 lines covering `$^$` nesting, automatic nesting,
nested control directives, blank lines, and indentation reset. The native PHP
slice maps eight additional focused checks from that fixture: automatic
nesting for whitespace-preceded multiline variables, explicit `$^$` multiline
variable nesting, aligned following template lines at the `$^$` level,
nested `if` directives inside a nested loop, loop output without doubled
source indentation, blank template lines that do not terminate nesting,
blank output lines without indentation-only spaces, and nesting termination
when the next nonblank template line is less indented. The mapped denominator
is now 1,026 focused Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the PlainText custom template
blank-line nesting slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and
`examples/wordpress-plain-template-nesting-handoff.php`; `php
lanes/pandoc/examples/wordpress-plain-template-nesting-handoff.php` emitted a
custom plain WordPress packet with a nested multiline review description that
contains an internal blank line, aligned owner continuation, automatically
nested summary variable, nested legal-hold partial, blank-line separated
legal-hold conditional, and PlainText-rendered body; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,598
assertions, and 0 failures. The focused file now contains 271 behavior tests.

Root aggregate verification on 2026-05-23 after the PlainText custom template
blank-line nesting slice was not assigned to this lane worker, so no
no-argument root harness was started. Per lane instructions, focused pandoc
evidence was recorded and root aggregate verification remains pending for the
supervisor/integrator.

`MANUAL.txt` Template syntax and Pandoc's `src/Text/Pandoc/Templates.hs` were
inspected again at upstream pandoc commit
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`; Pandoc still delegates template
rendering to the `doctemplates >= 0.11 && < 0.12` package. The supplemental
`jgm/doctemplates` clone at `2485bcbdb297d54d732006ca545a2a9e426f3817` was
inspected for `test/conditionals.test` (37 lines) and `test/elseif.test`
(30 lines), plus the parser branches where `pConditional` passes its
multiline flag into `pElse` and `pElseIf` consumes a following line ending
for its own branch body. The native PHP slice maps six additional focused
checks from that boundary: standalone `$else$` after a multiline `$if$`
swallows its following newline, a selected false branch does not gain a
leading blank line, empty false conditions omit their block without adding
extra blank output, standalone `$elseif$` swallows its following newline,
a selected first-elseif branch preserves the intentional blank between
adjacent conditionals, and chained false `$elseif$` branches fall through to
the final `$else$` without a leading blank. The mapped denominator is now
1,032 focused Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the PlainText custom template
standalone branch newline slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and
`examples/wordpress-plain-template-branching-handoff.php`; `php
lanes/pandoc/examples/wordpress-plain-template-branching-handoff.php` emitted
a custom plain WordPress branch packet with a standalone `$elseif$` escalation
block selecting the workflow queue without a leading blank line, plus a
PlainText-rendered body; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,602
assertions, and 0 failures. The focused file now contains 272 behavior tests.

Root aggregate verification on 2026-05-23 after the PlainText custom template
standalone branch newline slice was not assigned to this lane worker, so no
no-argument root harness was started. Per lane instructions, focused pandoc
evidence was recorded and root aggregate verification remains pending for the
supervisor/integrator.

`MANUAL.txt` Template syntax and Pandoc's `src/Text/Pandoc/Templates.hs` were
inspected again at upstream pandoc commit
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`; Pandoc still delegates template
rendering to the `doctemplates >= 0.11 && < 0.12` package. The supplemental
`jgm/doctemplates` clone at `2485bcbdb297d54d732006ca545a2a9e426f3817` was
inspected for `test/final-newline.test` (15 lines) and `test/boolean.test`
(13 lines). The native PHP slice maps seven additional focused checks from
that boundary: a scalar ending with one newline does not double the following
template line ending, a scalar ending with two newlines preserves one
intentional blank line, final custom PlainText output still omits a trailing
output newline, direct `true` renders as `true`, direct `false` renders as
`false`, truthy boolean conditionals select the true branch, and false boolean
conditionals select the false branch. The mapped denominator is now 1,039
focused Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the PlainText custom template
final-newline/boolean scalar slice: `php -l` passed for
`MarkdownWriter.php`, `MarkdownReaderTest.php`, and
`examples/wordpress-plain-template-final-newline-handoff.php`; `php
lanes/pandoc/examples/wordpress-plain-template-final-newline-handoff.php`
emitted a custom plain WordPress packet with newline-terminated review fields
without extra blank lines, one intentional blank from a double-newline field,
visible true/false metadata, and PlainText-rendered body output; `php
tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test
file, 2,609 assertions, and 0 failures. The focused file now contains 274
behavior tests.

Root aggregate verification on 2026-05-23 after the PlainText custom template
final-newline/boolean scalar slice was not assigned to this lane worker, so no
no-argument root harness was started. Per lane instructions, focused pandoc
evidence was recorded and root aggregate verification remains pending for the
supervisor/integrator.

`MANUAL.txt` Template syntax and Pandoc's `src/Text/Pandoc/Templates.hs` were
inspected again at upstream pandoc commit
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`; Pandoc still delegates template
rendering to the `doctemplates >= 0.11 && < 0.12` package. The supplemental
`jgm/doctemplates` clone at `2485bcbdb297d54d732006ca545a2a9e426f3817` was
inspected for `test/space-in-loop.test` (27 lines) and `test/values.test`
(20 lines). The native PHP slice maps six additional focused checks from that
boundary: a loop body with a blank line after the interpolated value preserves
one blank line between rendered list values, an empty loop with a blank body
emits no whitespace, direct numeric/null interpolation follows the fixture's
text/empty-line behavior, direct scalar-list interpolation concatenates items
without implicit paragraph separators, direct map interpolation renders
`true`, and direct lists of maps concatenate those `true` markers. The mapped
denominator is now 1,045 focused
Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the PlainText custom template
space-in-loop/direct-list slice: `php -l` passed for `MarkdownWriter.php` and
`MarkdownReaderTest.php`; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,615
assertions, and 0 failures. The focused file now contains 275 behavior tests.

Root aggregate verification on 2026-05-23 after the PlainText custom template
space-in-loop/direct-list slice was not assigned to this lane worker, so no
no-argument root harness was started. Per lane instructions, focused pandoc
evidence was recorded and root aggregate verification remains pending for the
supervisor/integrator.

`MANUAL.txt` Template syntax and Pandoc's `src/Text/Pandoc/Templates.hs` were
inspected again at upstream pandoc commit
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`; Pandoc still delegates template
rendering to the `doctemplates >= 0.11 && < 0.12` package. The supplemental
`jgm/doctemplates` clone at `2485bcbdb297d54d732006ca545a2a9e426f3817` was
inspected for `test/loop-in-partial.test` (6 lines) plus its two partials
`test/loop1.txt` and `test/loop2.txt`. The native PHP slice maps two
additional focused checks from that boundary: mutually recursive partials now
emit Pandoc's literal `(loop)` sentinel instead of recursing forever or
disappearing, and a WordPress reviewer-packet partial loop keeps
PlainText-rendered body output without leaking source admin URLs. The mapped
denominator is now 1,047 focused Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the PlainText custom template
partial-recursion slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and
`examples/wordpress-plain-template-loop-guard-handoff.php`; `php
lanes/pandoc/examples/wordpress-plain-template-loop-guard-handoff.php` emitted
a custom plain WordPress packet with `Guard: (loop)` and PlainText-rendered
body output; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,619
assertions, and 0 failures. The focused file now contains 276 behavior tests.

Root aggregate verification on 2026-05-23 after the PlainText custom template
partial-recursion slice was not assigned to this lane worker, so no
no-argument root harness was started. Per lane instructions, focused pandoc
evidence was recorded and root aggregate verification remains pending for the
supervisor/integrator.

`MANUAL.txt` Template syntax and Pandoc's `src/Text/Pandoc/Templates.hs` were
inspected again at upstream pandoc commit
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`; Pandoc still delegates template
rendering to the `doctemplates >= 0.11 && < 0.12` package. The supplemental
`jgm/doctemplates` clone at `2485bcbdb297d54d732006ca545a2a9e426f3817` was
inspected for `test/pad.test` (53 lines), plus `Text.DocTemplates.Internal`
`Block` pipe handling where `left`, `right`, and `center` are rendered through
DocLayout block constructors with vertical-fill borders and `NullVal` aligned
as an empty simple value. The native PHP slice maps eight additional focused
checks from that boundary: multiline right/center/left alignment composes
adjacent block values line-by-line, trailing block padding is not emitted at
the end of output lines, `$^$` nesting still uses the untrimmed aligned column
width, `pairs/alpha/right` keeps multiline values nested under the list label,
bordered key/value blocks vertically fill shorter key columns, left-aligned
multiline value cells keep right borders on every row, missing salary fields
render as padded empty cells, and over-wide aligned fields are not truncated.
The mapped denominator is now 1,055 focused Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the PlainText custom template
pad-alignment slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and
`examples/wordpress-plain-template-pad-handoff.php`; `php
lanes/pandoc/examples/wordpress-plain-template-pad-handoff.php` emitted a
custom plain WordPress reviewer packet with multiline review notes, vertically
filled blank cells for missing metadata, over-wide note preservation, and
PlainText-rendered body output; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,622
assertions, and 0 failures. The focused file now contains 277 behavior tests.

Root aggregate verification on 2026-05-23 after the PlainText custom template
pad-alignment slice was not assigned to this lane worker, so no no-argument
root harness was started. Per lane instructions, focused pandoc evidence was
recorded and root aggregate verification remains pending for the
supervisor/integrator.

`MANUAL.txt` Template syntax and Pandoc's `src/Text/Pandoc/Templates.hs` were
inspected again at upstream pandoc commit
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`; Pandoc still delegates template
rendering to the `doctemplates >= 0.11 && < 0.12` package. The supplemental
`jgm/doctemplates` clone at `2485bcbdb297d54d732006ca545a2a9e426f3817` was
inspected for `test/loop-in-object.test` (19 lines), `test/loop-in-partial.test`
(6 lines), and `src/Text/DocTemplates/Parser.hs` lines 317-318 where partial
resolution returns the literal `(loop)` only when nesting exceeds 50. The native
PHP slice maps six additional focused checks from that boundary: dotted object
loops iterate `worksite.workers`, anaphoric `it.name.last` and `it.name.first`
resolve inside that nested object loop, the three worker rows render in upstream
order, partial nesting through level 50 still resolves, level 51 emits the
Pandoc `(loop)` sentinel, and a WordPress reviewer-packet object loop keeps
PlainText-rendered body output without leaking source admin URLs. The mapped
denominator is now 1,061 focused Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the PlainText custom template
object-loop/partial-depth slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and
`examples/wordpress-plain-template-object-loop-handoff.php`; `php
lanes/pandoc/examples/wordpress-plain-template-object-loop-handoff.php` emitted
a custom plain WordPress reviewer packet with nested reviewer names/queues and
PlainText-rendered body output; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,626
assertions, and 0 failures. The focused file now contains 278 behavior tests.

Root aggregate verification on 2026-05-23 after the PlainText custom template
object-loop/partial-depth slice was not assigned to this lane worker, so no
no-argument root harness was started. Per lane instructions, focused pandoc
evidence was recorded and root aggregate verification remains pending for the
supervisor/integrator.

`MANUAL.txt` Template syntax, Pandoc's `src/Text/Pandoc/Templates.hs`, and the
supplemental `jgm/doctemplates` clone at
`2485bcbdb297d54d732006ca545a2a9e426f3817` were inspected again for
`test/test.hs` compile-failure unit cases and `src/Text/DocTemplates/Parser.hs`
error boundaries. The native PHP slice maps six additional focused checks from
that boundary: malformed `$if(...)$` syntax reports the unexpected closing
delimiter, the reserved `$sep$` keyword fails as a variable, unknown pipes report
the offending pipe name, `left`/`right`/`center` pipes require integer
parameters, invalid pipe arguments report the bad argument column, and syntax
errors inside partials report the derived partial path plus line/column. The
mapped denominator is now 1,067 focused Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the PlainText custom template
compile-diagnostics slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and
`examples/wordpress-plain-template-diagnostics-handoff.php`; `php
lanes/pandoc/examples/wordpress-plain-template-diagnostics-handoff.php` emitted
a `templates/reviewer-card.txt` line/column diagnostic before rendering the
source body; `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
passed 1 test file, 2,632 assertions, and 0 failures. The focused file now
contains 279 behavior tests.

Root aggregate verification on 2026-05-23 after the PlainText custom template
compile-diagnostics slice was not assigned to this lane worker, so no
no-argument root harness was started. Per lane instructions, focused pandoc
evidence was recorded and root aggregate verification remains pending for the
supervisor/integrator.

`MANUAL.txt` Template syntax, Pandoc's `src/Text/Pandoc/Templates.hs`, and the
supplemental `jgm/doctemplates` clone at
`2485bcbdb297d54d732006ca545a2a9e426f3817` were inspected again for
`test/basic-with-braces.test` (16 lines), `test/partials.test` (39 lines), and
`src/Text/DocTemplates/Parser.hs` delimiter/partial nesting behavior. The
native PHP slice maps eight additional focused checks from that boundary:
`${...}` delimiters drive the employee loop, braced variable interpolation
reads dotted fields, braced conditionals select missing-salary else branches,
`$$` followed by a braced variable emits a literal dollar plus the salary,
applied partials render employee rows through anaphoric `it`, bracket
separators render only between applied partial results, indented bare partials
nest every rendered line under the source indentation, and a WordPress reviewer
packet keeps PlainText-rendered body output without leaking source admin URLs.
The mapped denominator is now 1,075 focused Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-23 after the PlainText custom template
brace-delimiter/indented-partial slice: `php -l` passed for
`MarkdownWriter.php`, `MarkdownReaderTest.php`, and
`examples/wordpress-plain-template-brace-partial-handoff.php`; `php
lanes/pandoc/examples/wordpress-plain-template-brace-partial-handoff.php`
emitted a custom plain WordPress reviewer packet with braced delimiter reviewer
rows, literal dollar budget output, nested checklist partial output, and no
source admin URL; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,640
assertions, and 0 failures. The focused file now contains 281 behavior tests.

Root aggregate verification on 2026-05-23 after the PlainText custom template
brace-delimiter/indented-partial slice was not assigned to this lane worker, so
no no-argument root harness was started. Per lane instructions, focused pandoc
evidence was recorded and root aggregate verification remains pending for the
supervisor/integrator.

`MANUAL.txt` Template syntax, Pandoc's `src/Text/Pandoc/Templates.hs`, and the
supplemental `jgm/doctemplates` clone at
`2485bcbdb297d54d732006ca545a2a9e426f3817` were inspected again for
`test/pipes.test` (109 lines), `test/partials.test` (39 lines), and the
`enum.txt`, `boilerplate.txt`, and `partial_foo.txt` partial fixtures. The
native PHP slice maps eight additional focused checks from that boundary:
applied partial source expressions apply `pairs/reverse` before rendering the
partial, enum-style applied partials preserve the remaining newline from a
double-final-newline partial, recursive `chomp` trims map/list values before
`pairs/uppercase`, list values render through `roman` and `alpha` pipes before
bracket separators, and bare partial names resolve upstream `.txt` partial
files while direct partial inclusion still omits final partial newlines before
following template content. A local comparison against the supplemental
doctemplates `.test` corpus now reports zero fixture mismatches. The mapped
denominator is now 1,083 focused Markdown/HTML/WordPress checks.

Focused local verification on 2026-05-24 after the PlainText custom template
pipes/partial-resolution slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and
`examples/wordpress-plain-template-pipe-partial-fixture-handoff.php`; `php
lanes/pandoc/examples/wordpress-plain-template-pipe-partial-fixture-handoff.php`
emitted a custom plain WordPress reviewer packet with `pairs/reverse` check
rows rendered through a `.txt` partial, chomped uppercase owner metadata, roman
milestone lists, and no source admin URL; the local supplemental doctemplates
fixture comparison reported `doctemplates failures: 0`; `php
tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test
file, 2,646 assertions, and 0 failures. The focused file now contains 282
behavior tests.

Root aggregate verification on 2026-05-24 after the PlainText custom template
pipes/partial-resolution slice was not assigned to this lane worker, so no
no-argument root harness was started. Per lane instructions, focused pandoc
evidence was recorded and root aggregate verification remains pending for the
supervisor/integrator.

`test/Tests/Writers/Native.hs`, `test/writer.native`,
`test/lhs-test-markdown.native`, and the leading `test/testsuite.native`
constructor shape were inspected for Pandoc Native writer behavior. The
upstream property boundary is small but broad: `p_write_rt` requires full
`Pandoc` native output to read back into the same document when a template is
present, while `p_write_blocks_rt` requires block-list output to read back into
the same bounded block list. The native PHP slice maps fourteen additional
focused checks from that boundary: full-document `Pandoc` output with `Meta
{ unMeta = fromList ... }`, block-list output without the `Pandoc` wrapper,
title/author/date `MetaInlines` and `MetaList` values, `MetaBool`/`MetaString`
values, `Header`, `Para`, `Plain`, `HorizontalRule`, `BulletList`,
`OrderedList`, `BlockQuote`, `LineBlock`, and `CodeBlock` constructors, attr
tuples, `Strong`, `Link`, and `Code` inlines, and Haskell-style string escaping
for code-block newlines.

Focused local verification on 2026-05-24 after the NativeWriter common-AST
slice: `php -l` passed for `NativeWriter.php`, `MarkdownReaderTest.php`, and
`examples/wordpress-native-review-packet-handoff.php`; `php
lanes/pandoc/examples/wordpress-native-review-packet-handoff.php` emitted a
standalone Pandoc Native reviewer packet with metadata, a source link,
checklist blocks, and escaped PHP code-block fixture text; `php
tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test
file, 2,663 assertions, and 0 failures. The focused file now contains 284
behavior tests.

Root aggregate verification on 2026-05-24 after the NativeWriter common-AST
slice was not assigned to this lane worker, so no no-argument root harness was
started. Per lane instructions, focused pandoc evidence was recorded and root
aggregate verification remains pending for the supervisor/integrator.

`test/command/html-read-figure.md`, `test/command/cite-in-inline-note.md`,
`test/markdown-citations.native`, and the Images section of
`test/testsuite.native` were inspected for Pandoc Native figure/citation
constructor behavior. The native PHP slice maps eight additional focused
checks from those fixtures: `Figure` Attr tuples, `Caption (Just [...])` short
captions, long caption block lists, Plain-wrapped `Image` figure bodies,
`Cite` wrappers, `Citation` prefix/suffix inline lists, `AuthorInText` and
`SuppressAuthor` modes, and note-number/hash fields. The mapped denominator is
now 1,105 focused Markdown/HTML/Native/WordPress checks.

Focused local verification on 2026-05-24 after the NativeWriter Figure/Cite
slice: `php -l` passed for `NativeWriter.php`, `MarkdownReaderTest.php`,
`examples/wordpress-native-citation-figure-handoff.php`, and
`examples/wordpress-native-review-packet-handoff.php`; `php
lanes/pandoc/examples/wordpress-native-citation-figure-handoff.php` emitted a
standalone Pandoc Native reviewer packet with metadata, `Figure`/`Image`
constructors, short/long captions, and `Cite`/`Citation` records; `php
tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test
file, 2,666 assertions, and 0 failures. The focused file now contains 285
behavior tests.

Root aggregate verification on 2026-05-24 after the NativeWriter Figure/Cite
slice was not assigned to this lane worker, so no no-argument root harness was
started. Per lane instructions, focused pandoc evidence was recorded and root
aggregate verification remains pending for the supervisor/integrator.

`test/pipe-tables.native`, `test/tables.native`,
`test/markdown-reader-more.native`, and `test/Tests/Writers/Native.hs` were
inspected again for Pandoc Native table constructor behavior. The native PHP
slice maps nine additional focused checks from those fixtures: table Attr
tuples, `Caption` short/long blocks, `ColSpec` alignment and width fields,
`TableHead` row lists, `TableBody` `RowHeadColumns` and body-local head-row
lists, `TableFoot` row lists, `Cell` Attr/Align/RowSpan/ColSpan fields,
inline cell content as `Plain` blocks, and block-cell content preserving
`Para`/`BulletList` children. The mapped denominator is now 1,114 focused
Markdown/HTML/Native/WordPress checks.

Focused local verification on 2026-05-24 after the NativeWriter Table slice:
`php -l` passed for `NativeWriter.php`, `MarkdownReaderTest.php`, and
`examples/wordpress-native-table-handoff.php`; `php
lanes/pandoc/examples/wordpress-native-table-handoff.php` emitted a standalone
Pandoc Native reviewer packet with table metadata, caption, colspecs,
row-head columns, spanned cells, and nested block-cell content; `php
tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test
file, 2,674 assertions, and 0 failures. The focused file now contains 286
behavior tests.

Root aggregate verification on 2026-05-24 after the NativeWriter Table slice
was not assigned to this lane worker, so no no-argument root harness was
started. Per lane instructions, focused pandoc evidence was recorded and root
aggregate verification remains pending for the supervisor/integrator.

`test/Tests/Writers/Native.hs` was inspected again for the upstream Native
writer read-back boundary. The native PHP slice maps twelve additional
focused checks: full-document Native read-back, blocks-only list read-back,
title/author/date/bool/list metadata parsing, Attr tuple parsing, Header/Para
and list/code-block parsing, Figure/Image read-back, Cite/Citation read-back,
Table colspec/head/body/foot read-back, body-local head rows, row/column spans,
and WordPress block emission from a NativeReader-parsed packet. The mapped
denominator is now 1,126 focused Markdown/HTML/Native/WordPress checks.

Focused local verification on 2026-05-24 after the NativeReader read-back
slice: `php -l` passed for `NativeReader.php`, `MarkdownReaderTest.php`, and
`examples/wordpress-native-reader-handoff.php`; `php
lanes/pandoc/examples/wordpress-native-reader-handoff.php` emitted WordPress
heading, paragraph/link, and table blocks from a NativeReader-parsed packet;
`php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1
test file, 2,704 assertions, and 0 failures. The focused file now contains 288
behavior tests.

Root aggregate verification on 2026-05-24 after the NativeReader read-back
slice was not assigned to this lane worker, so no no-argument root harness was
started. Per lane instructions, focused pandoc evidence was recorded and root
aggregate verification remains pending for the supervisor/integrator.

## 2026-05-24 NativeReader Structural Fixture Slice

`test/testsuite.native`, `test/html-reader.native`, and
`test/docx/tables_separated_with_rawblock.native` were inspected again for
upstream Native structural fixture shapes. The new copied lane fixture
`fixtures/upstream-native-structure-slice.native` focuses on the bounded
Native reader/writer surface needed for WordPress review packets:
`DefinitionList` terms and multi-block definitions, `CodeBlock` and
`BlockQuote` children inside a definition body, nested `Div` blocks, `RawBlock`
passthrough for `html` and `openxml` formats, and the parenthesized
`(TableHead ...)` / `(TableFoot ...)` constructor shape used by upstream table
fixtures. The mapped denominator is now 1,144 focused
Markdown/HTML/Native/WordPress checks.

NativeReader now accepts both the unwrapped table section form emitted by prior
lane code and the upstream parenthesized forms. NativeWriter now emits
parenthesized `TableHead` and `TableFoot` constructors in the upstream table
shape. WordPressBlockWriter now keeps non-inline blocks inside definition-list
definitions as block HTML, so Native definition bodies preserve code blocks
and block quotes instead of flattening them to plain text.

Focused local verification on 2026-05-24 after the NativeReader structural
fixture slice: `php -l` passed for `NativeReader.php`, `NativeWriter.php`,
`WordPressBlockWriter.php`, `MarkdownReaderTest.php`, and
`examples/wordpress-native-upstream-structure-handoff.php`; `php
lanes/pandoc/examples/wordpress-native-upstream-structure-handoff.php` emitted
WordPress definition-list HTML, grouped raw HTML, and a table block from a
NativeReader-parsed fixture; targeted upstream-cache smoke checks parsed
`test/html-reader.native` as 245 blocks,
`test/docx/tables_separated_with_rawblock.native` as 3 blocks, and
`test/testsuite.native` as 239 blocks via `git show`; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 2,730
assertions, and 0 failures. The focused file now contains 290 behavior tests.

Root aggregate verification on 2026-05-24 after the NativeReader structural
fixture slice was not assigned to this lane worker, so no no-argument root
harness was started. Per lane instructions, focused pandoc evidence was
recorded and root aggregate verification remains pending for the
supervisor/integrator.

## 2026-05-24 NativeReader DOCX Inline Formatting Wrapper Slice

`test/docx/inline_formatting.native` was inspected from the upstream cache for
the DOCX reader's Native wrapper and inline-formatting output. The copied lane
fixture `fixtures/upstream-native-docx-inline-formatting.native` keeps the
upstream `Pandoc (Meta {unMeta = fromList []}) [...]` shape that previously
failed the bounded NativeReader because the meta constructor is parenthesized.
The mapped slice covers six focused checks: parenthesized full-document meta
wrapper parsing, five top-level DOCX formatting paragraphs, nested
`Emph`/`Strong` inlines, `SmallCaps` and `Strikeout`, `Underline`,
`Superscript`/`Subscript`, and `LineBreak` emission to WordPress block markup.
The mapped denominator is now 1,150 focused Markdown/HTML/Native/WordPress
checks.

NativeReader now accepts both unwrapped `Pandoc Meta ...` packets emitted by
the lane's NativeWriter and upstream-style `Pandoc (Meta ...)` packets. The
new WordPress example
`examples/wordpress-native-docx-inline-formatting-handoff.php` reads the
copied DOCX Native fixture and emits WordPress paragraphs that preserve
emphasis, strong/emphasis nesting, small caps, strikeout, underline,
superscript/subscript, and hard line breaks without invoking upstream Pandoc.

Focused local verification on 2026-05-24 after the NativeReader DOCX inline
formatting wrapper slice: `php -l` passed for `NativeReader.php`,
`MarkdownReaderTest.php`, and
`examples/wordpress-native-docx-inline-formatting-handoff.php`; `php
lanes/pandoc/examples/wordpress-native-docx-inline-formatting-handoff.php`
emitted the expected WordPress paragraph markup from a NativeReader-parsed
DOCX fixture; a targeted upstream-cache smoke check parsed
`test/docx/inline_formatting.native` as 5 blocks via `git show`; `php
tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test
file, 2,749 assertions, and 0 failures. The focused file now contains 291
behavior tests.

Root aggregate verification on 2026-05-24 after the NativeReader DOCX inline
formatting wrapper slice was not assigned to this lane worker, so no
no-argument root harness was started. Per lane instructions, focused pandoc
evidence was recorded and root aggregate verification remains pending for the
supervisor/integrator.

## 2026-05-24 NativeReader DOCX Review Span Slice

The upstream DOCX fixture subinventory was counted with `git ls-tree` from
`.upstream-cache/pandoc` at `0640c4c9859aa5a3ede082c190fcd5883c24ac83`:
233 artifacts under `test/docx`, including 112 `.native` expectations, 121
`.docx` inputs/goldens, and 38 `test/docx/golden` outputs. The targeted
review-span fixture set includes `test/docx/comments.native`,
`test/docx/comments_no_comments.native`, `test/docx/raw-blocks.native`,
`test/docx/raw-bookmarks.native`, and ten track-change `.native` fixtures.

This slice maps `test/docx/comments.native`,
`test/docx/track_changes_insertion_all.native`, and
`test/docx/track_changes_deletion_all.native`. The copied fixtures cover five
`comment-start` spans, five `comment-end` spans, one tracked insertion span,
one tracked deletion span, comment id/author/date attrs, tracked-change
author/date attrs, and a `LineBreak` inside a comment body. That slice brought
the mapped denominator to 1,162 focused Markdown/HTML/Native/WordPress checks.

WordPressBlockWriter now recognizes upstream DOCX review-span classes. Comment
spans render with `data-pandoc-comment-id`, `data-pandoc-comment-author`, and
`data-pandoc-comment-date` instead of raw upstream `author`/`date` attributes.
Tracked insertions render as `<ins>` and deletions as `<del>`, preserving
`data-pandoc-change-author`, `data-pandoc-change-date`, and `datetime`
metadata for reviewer-facing WordPress handoffs. At that point the mapped
denominator was 1,162 focused Markdown/HTML/Native/WordPress checks; the later
raw OpenXML, paragraph-change, and custom-style slices above raise it to 1,203.

Focused local verification on 2026-05-24 after the NativeReader DOCX review
span slice: `php -l` passed for `WordPressBlockWriter.php`,
`MarkdownReaderTest.php`, and
`examples/wordpress-native-docx-review-spans-handoff.php`; `php
lanes/pandoc/examples/wordpress-native-docx-review-spans-handoff.php` emitted
WordPress paragraph markup with comment metadata and tracked ins/del spans; a
targeted upstream-cache smoke check parsed `test/docx/comments.native` as 4
blocks, `test/docx/track_changes_insertion_all.native` as 1 block, and
`test/docx/track_changes_deletion_all.native` as 1 block via `git show`;
`php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1
test file, 2,774 assertions, and 0 failures. The focused file then contained
293 behavior tests; the later raw OpenXML, paragraph-change, and custom-style
slices above raise it to 297 tests and 2,822 assertions.

Root aggregate verification on 2026-05-24 after the NativeReader DOCX review
span slice was not assigned to this lane worker, so no no-argument root
harness was started. Per lane instructions, focused pandoc evidence was
recorded and root aggregate verification remains pending for the
supervisor/integrator.

## 2026-05-24 MarkdownWriter Wikilink Writer Slice

`test/command/wikilinks_title_after_pipe.md` and
`test/command/wikilinks_title_before_pipe.md` were inspected from the upstream
cache for the Markdown/CommonMark writer examples. The new native PHP slice
maps ten focused Markdown/WordPress checks: four upstream title-after-pipe
writer outputs, four upstream title-before-pipe writer outputs, one
class-preservation fallback guard for links that are not a pure `.wikilink`,
and one WordPress reviewer-packet handoff for migration runbook/checklist
shortcuts. The mapped denominator is now 1,459 focused
Markdown/HTML/Native/WordPress checks.

MarkdownWriter now renders class-only `wikilink` links as Pandoc wiki-link
shortcuts when `wikilinksTitleAfterPipe`, `wikilinksTitleBeforePipe`, or a
matching variant string is enabled. It keeps same-label links compact,
percent-encodes target spaces as `%20`, supports both `[[target|title]]` and
`[[title|target]]`, and falls back to ordinary Markdown link rendering when
extra classes or attributes would otherwise be dropped. The new WordPress
example `examples/wordpress-markdown-wikilink-writer-handoff.php` emits
reviewer Markdown for legacy wiki shortcuts without shelling out to Pandoc.

Focused local verification on 2026-05-24 after the MarkdownWriter wikilink
writer slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and
`examples/wordpress-markdown-wikilink-writer-handoff.php`; `php
lanes/pandoc/examples/wordpress-markdown-wikilink-writer-handoff.php` emitted
title-after-pipe and title-before-pipe wiki-link reviewer Markdown; `php
tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test
file, 3,125 assertions, and 0 failures. The focused file now contains 315
behavior tests.

Root aggregate verification on 2026-05-24 after the MarkdownWriter wikilink
writer slice was not assigned to this lane worker, so no no-argument root
harness was started. Per lane instructions, focused pandoc evidence was
recorded and root aggregate verification remains pending for the
supervisor/integrator.

## 2026-05-24 NativeReader DOCX Notes Slice

`test/docx/notes.native` and `test/docx/link_in_notes.native` were inspected
from the upstream cache for bounded DOCX note/endnote behavior. The copied
lane fixtures `fixtures/upstream-native-docx-notes.native` and
`fixtures/upstream-native-docx-link-in-notes.native` map twelve focused
checks: two DOCX note nodes, one endnote node, the source heading id, one link
inside a note, NativeWriter round-trip guards for `Note` and `Link`
constructors, and WordPress endnote output with backlinks. The mapped
denominator is now 1,471 focused Markdown/HTML/Native/WordPress checks.

NativeReader already supports Pandoc Native `Note` and `Link` constructors;
this slice pins that behavior against upstream DOCX Native fixtures and
exercises the WordPress endnote handoff without invoking upstream Pandoc or
activating DOCX ZIP/OpenXML parsing. The new WordPress example
`examples/wordpress-native-docx-notes-handoff.php` reads the copied fixtures,
combines them into one Native AST document, and emits reviewer-safe WordPress
endnotes while preserving the link inside the final note.

Focused local verification on 2026-05-24 after the NativeReader DOCX notes
slice: `php -l` passed for `MarkdownReaderTest.php` and
`examples/wordpress-native-docx-notes-handoff.php`; `php
lanes/pandoc/examples/wordpress-native-docx-notes-handoff.php` emitted
WordPress endnotes with backlinks and a preserved note link; `diff -u` checks
against `git -C .upstream-cache/pandoc show HEAD:test/docx/notes.native` and
`HEAD:test/docx/link_in_notes.native` confirmed both copied fixtures match
upstream exactly; the first focused test run exposed one overly strict
NativeWriter line-wrapping assertion, which was corrected; `php
tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` then passed 1
test file, 3,141 assertions, and 0 failures. The focused file now contains 316
behavior tests.

Root aggregate verification on 2026-05-24 after the NativeReader DOCX notes
slice was not assigned to this lane worker, so no no-argument root harness was
started. Per lane instructions, focused pandoc evidence was recorded and root
aggregate verification remains pending for the supervisor/integrator.

## 2026-05-24 MarkdownWriter Standalone TOC Slice

`test/command/toc.md` was inspected from the upstream cache for Pandoc's
standalone Markdown writer TOC behavior. The mapped slice covers fourteen
focused checks: standalone TOC insertion, six rendered TOC entries, generated
duplicate heading fragments such as `b-1` and `b-2`, TOC link ids such as
`toc-a`, exclusion of divs with multiple minimum-level headings, inclusion of
divs with one minimum-level heading, and a WordPress reviewer-packet example.
The mapped denominator is now 1,485 focused Markdown/HTML/Native/WordPress
checks.

MarkdownWriter now uses the existing TOC candidate logic for standalone
Markdown output when the template/standalone and TOC options are enabled. It
keeps the existing PlainText template TOC behavior, while Markdown output emits
Pandoc-style link attributes in the TOC and uses a local duplicate-id counter
so generated fragments match the body heading order without consuming the
body-writer heading-id state. The new WordPress example
`examples/wordpress-markdown-toc-handoff.php` emits a standalone Markdown
review packet for migration batches, keeping interior scratch sections in the
body but out of the TOC.

Focused local verification on 2026-05-24 after the MarkdownWriter standalone
TOC slice: `php -l` passed for `MarkdownWriter.php`,
`MarkdownReaderTest.php`, and
`examples/wordpress-markdown-toc-handoff.php`; `php
lanes/pandoc/examples/wordpress-markdown-toc-handoff.php` emitted a standalone
reviewer Markdown TOC with source/media/publish sections and omitted interior
scratch headings; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 3,147
assertions, and 0 failures; `git diff --check` passed for the touched Pandoc
files. The focused file now contains 317 behavior tests.

Root aggregate verification on 2026-05-24 after the MarkdownWriter standalone
TOC slice was not assigned to this lane worker, so no no-argument root harness
was started. Per lane instructions, focused pandoc evidence was recorded and
root aggregate verification remains pending for the supervisor/integrator.

## 2026-05-24 HTML Writer Styled Inline Constructor Slice

`src/Text/Pandoc/Writers/HTML.hs` was inspected from the upstream cache at
`0640c4c9859aa5a3ede082c190fcd5883c24ac83` for the bounded inline constructor
branches around `Underline`, `Strikeout`, `SmallCaps`, `Superscript`, and
`Subscript`. This slice maps twelve focused checks: five upstream HTML inline
constructor branches, nested inline/escaping behavior inside those wrappers,
link output inside superscript, and a WordPress review-preview handoff. The
mapped denominator is now 2,039 focused Markdown/HTML/Native/WordPress checks.

HtmlWriter now renders those constructors as native HTML: underline as `<u>`,
strikeout as `<del>`, small caps as `<span class="smallcaps">`, superscript as
`<sup>`, and subscript as `<sub>`. The nodes are also treated as inline nodes
when nested in figure/table/list contexts. This is a writer-only behavior slice:
it does not invoke upstream Pandoc, activate package ZIP/OpenXML/OpenDocument
parsing, fetch media, run external PDF/TeX engines, or open citation/CSL,
PlainMath/MathML, or syntax-highlighting dependency gates.

The new WordPress example
`examples/wordpress-html-writer-styled-inline-handoff.php` emits an HTML
preview block for editorial underline/deletion/small-caps marks plus H2O-style
subscript and source-note superscript links, then wraps that preview in a
WordPress HTML block for reviewer handoff.

Focused local verification on 2026-05-24 after the HTML writer styled-inline
slice: `php -l` passed for `HtmlWriter.php`, `MarkdownReaderTest.php`, and
`examples/wordpress-html-writer-styled-inline-handoff.php`; `php
lanes/pandoc/examples/wordpress-html-writer-styled-inline-handoff.php` emitted
the expected HTML preview and WordPress review block; `php tools/run-tests.php
lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 test file, 3,798
assertions, and 0 failures. The focused file now contains 376 behavior tests.

Root aggregate verification on 2026-05-24 after this slice was not assigned to
this lane worker, so no no-argument root harness was started. Per lane
instructions, focused pandoc evidence was recorded and root aggregate
verification remains pending for the supervisor/integrator.

## 2026-06-25 EPUB3 OPF Package Link Refines Sanitation Slice

The existing compact EPUB package coverage already records OPF collection
authoring direction and language (`collection dir/xml:lang`), collection link
direction and language (`link dir/xml:lang`), and package-level
`belongs-to-collection` metadata direction. This slice adds direct collection
metadata meta coverage inside the OPF refines sanitation fixture:
`<meta id="series-title-script" refines="#series-title" xml:lang="pl"
dir="ltr">` is asserted as collection metadata and participates in refinement
target resolution.

The closed gap is compact native PHP OPF package-link/refines sanitation.
`EpubPackage` now reports package metadata links, collection links, and
collection metadata meta entries as refinement sources. Package and collection
link ids are checked as XML NCName-style identifiers, duplicate link ids are
diagnosed within the package-link or collection-link scope, and invalid link
ids are not admitted into the local refinement target inventory. The focused
fixture covers local package metadata targets, collection metadata targets,
package-document self-reference, malformed fragment subjects, missing local
resource targets, missing local refinement subjects, external link targets,
duplicate link ids, invalid link ids, and package-relative cross-package
`refines` values that should remain package-relative instead of local.

Focused local verification on 2026-06-25: `php -l` passed for
`lanes/pandoc/src/EpubPackage.php` and
`lanes/pandoc/tests/EpubPackageTest.php`; `php tools/run-tests.php
lanes/pandoc/tests/EpubPackageTest.php` passed 1 file, 4,176 assertions, and
0 failures; `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php
lanes/pandoc/tests/EpubWriterTest.php` passed 2 files, 219 assertions, and
0 failures. `php tools/run-tests.php lanes/pandoc/tests` was executed after
rebasing on current `origin/main` and is not green for unrelated backlog:
276 test files, 105,632 assertions, and 10,830 failures, with failures spread across
Markdown surge tests, Citation/CSL/BibTeX tests, `UnicodeTextTest.php`,
`PandocJsonNativeAstTest.php`, `YamlMetadataReviewTest.php`, and other
non-EPUB areas.
