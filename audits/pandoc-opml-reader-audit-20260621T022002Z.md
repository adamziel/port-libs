# Pandoc OPML Reader Audit - 2026-06-21T02:20:02Z

## Registry Snapshot

- Upstream Pandoc input formats tracked: 51.
- Native PHP upstream input readers registered as partial: 29.
- Upstream input formats still unsupported: 22.
- Upstream Pandoc output formats tracked: 75.
- Native PHP upstream output writers registered as partial: 15.
- Upstream output formats still unsupported: 60.
- Project-local non-upstream inputs remain `pdf` and `doc`.
- `opml` now dispatches to `PortLibs\Pandoc\OpmlReader`.
- `opml` output now dispatches to `PortLibs\Pandoc\OpmlWriter`.

Unsupported upstream inputs after this slice remain:

`asciidoc`, `creole`, `djot`, `dokuwiki`, `fb2`, `haddock`, `jira`, `mediawiki`, `man`, `mdoc`, `muse`, `org`, `pod`, `pptx`, `rst`, `t2t`, `textile`, `tikiwiki`, `twiki`, `typst`, `vimwiki`, `xlsx`.

## Upstream Basis

- Source mapped from pinned Pandoc OPML reader behavior: `https://raw.githubusercontent.com/jgm/pandoc/912bfa5e2e3f5c74eb125dfc19404f67c61ca58b/src/Text/Pandoc/Readers/OPML.hs`.
- Source mapped from pinned Pandoc OPML writer behavior: `https://raw.githubusercontent.com/jgm/pandoc/912bfa5e2e3f5c74eb125dfc19404f67c61ca58b/src/Text/Pandoc/Writers/OPML.hs`.
- OPML `head/title`, `ownerName`, and `dateModified` become document metadata.
- Nested `outline` elements become heading blocks by depth.
- `type="link"` outlines become linked heading text through the `url` attribute.
- `_note` attributes are parsed through the existing Markdown reader into following blocks.
- Outline `text` is parsed as bounded HTML inline markup rather than treated as literal escaped text.
- Writer sections become nested `outline` elements; section body blocks become `_note` Markdown; heading inlines become escaped HTML in the `text` attribute; metadata fills the default OPML header.

## New OPML Coverage

- Added `OpmlReader` with safe XML parsing, source hash metadata, root attributes, outline counts, link-outline counts, note counts, and payload exposure policy metadata.
- Added bounded HTML inline fragment handling for OPML outline text, including emphasis, strong, code, links, images, line breaks, subscript, superscript, underline, mark, and strikeout.
- Added converter dispatch and registry support for `opml` as a partial upstream input implementation.
- Added regression coverage for metadata mapping, nested headings, link headings, Markdown notes, WordPress block output, Markdown output, malformed XML rejection, the upstream `opml-reader.opml` reader fixture structure, and canonical native semantic parity against the pinned upstream `opml-reader.native` fixture.
- Added `OpmlWriter`, converter dispatch, and registry output support for `opml`.
- Added writer regression coverage for default metadata, nested sections, escaped HTML heading text, Markdown `_note` body attributes, body-only output, OPML writer-to-reader round trip, Pandoc-compatible horizontal-rule markers, tight list items, definition-list markers, empty Div attributes, raw TeX inline nodes, empty link destinations, link titles with quotes, OPML note wrapping, display-math softbreaks, footnote definition wrapping, and nested empty-attribute Div fence width.

## Remaining OPML Gaps

- The upstream OPML reader fixture is covered with canonical native semantic parity. The pinned upstream `testsuite.native` to `writer.opml` writer fixture with `columns => 80` is byte-for-byte exact.
- The reader does not yet claim exact Pandoc behavior for every HTML inline edge, malformed outline recovery edge, nonstandard attribute extension, or command-line option interaction.
- The writer does not yet claim exact Pandoc behavior for every template, metadata, ASCII-entity, option interaction, or future upstream fixture edge beyond the pinned old-suite `writer.opml` comparison.
- Package/container handling is not relevant to OPML in this slice.

## Verification

- `php -l lanes/pandoc/src/OpmlReader.php`: passed.
- `php -l lanes/pandoc/src/PandocConverter.php`: passed.
- `php -l lanes/pandoc/src/PandocFormatRegistry.php`: passed.
- `php -l lanes/pandoc/tests/OpmlReaderTest.php`: passed.
- `php -l lanes/pandoc/src/OpmlWriter.php`: passed.
- `php -l lanes/pandoc/tests/OpmlWriterTest.php`: passed.
- `php -l lanes/pandoc/tests/PandocConverterTest.php`: passed.
- `php -l lanes/pandoc/tests/PandocFormatRegistryTest.php`: passed.
- Direct pinned upstream reader fixture canonical native semantic comparison, `opml-reader.opml` to `opml-reader.native`: `actual_bytes=4139`, `expected_bytes=4139`, `same=yes`, Levenshtein `0` after stripping port-libs provenance metadata and rendering both sides through the local canonical native writer.
- Direct pinned upstream writer fixture comparison, `testsuite.native` to `writer.opml` with `columns => 80`: `actual_bytes=14075`, `expected_bytes=14075`, `same=yes`, Levenshtein `0`.
- `php tools/run-tests.php lanes/pandoc/tests/OpmlReaderTest.php lanes/pandoc/tests/OpmlWriterTest.php lanes/pandoc/tests/PandocConverterTest.php lanes/pandoc/tests/PandocFormatRegistryTest.php`: 4 files, 277 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/DocBookReaderTest.php lanes/pandoc/tests/XmlReaderTest.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/BibliographyReaderTest.php lanes/pandoc/tests/DelimitedTextReaderTest.php lanes/pandoc/tests/IpynbReaderTest.php lanes/pandoc/tests/RtfReaderTest.php lanes/pandoc/tests/PlainWriterTest.php lanes/pandoc/tests/LegacyDocReaderTest.php lanes/pandoc/tests/PandocConverterTest.php lanes/pandoc/tests/PandocFormatRegistryTest.php lanes/pandoc/tests/RichPackageUnsupportedFormatRegistryTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/pandoc/tests/DocxReaderTest.php lanes/pandoc/tests/EpubPackageMetadataReaderTest.php lanes/pandoc/tests/EpubReaderTest.php lanes/pandoc/tests/JsonReaderWriterTest.php lanes/pandoc/tests/MarkdownReaderTest.php lanes/pandoc/tests/NativeReaderEscapeTest.php lanes/pandoc/tests/OdtReaderTest.php lanes/pandoc/tests/OpmlReaderTest.php lanes/pandoc/tests/OpmlWriterTest.php lanes/pandoc/tests/PdfReaderTest.php`: 23 files, 18,285 assertions, 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/pandoc/tests/PdfReaderTest.php`: 2 files, 3,051 assertions, 0 failures.
- Direct OPML writer ASCII-entity smoke: `<outline text="Za&#380;&#243;&#322;&#263;">`.
- Direct upstream reader fixture comparison: pinned old-suite `opml-reader.native` is canonical-native equal for `opml-reader.opml` after stripping local provenance metadata.
- Direct upstream writer fixture comparison: pinned old-suite `writer.opml` is byte-for-byte equal for `testsuite.native` with `columns => 80`.
- Local problematic PDF smoke: `tables=10 geometry=10 rects=896 mode=geometry`.
- Exact-string guard for the local problematic PDF path/content terms across current code, tests, status, and audits before this audit: 0 hits.
