# Pandoc OPML Reader Audit - 2026-06-21T02:20:02Z

## Registry Snapshot

- Upstream Pandoc input formats tracked: 51.
- Native PHP upstream input readers registered as partial: 29.
- Upstream input formats still unsupported: 22.
- Project-local non-upstream inputs remain `pdf` and `doc`.
- `opml` now dispatches to `PortLibs\Pandoc\OpmlReader`.

Unsupported upstream inputs after this slice remain:

`asciidoc`, `creole`, `djot`, `dokuwiki`, `fb2`, `haddock`, `jira`, `mediawiki`, `man`, `mdoc`, `muse`, `org`, `pod`, `pptx`, `rst`, `t2t`, `textile`, `tikiwiki`, `twiki`, `typst`, `vimwiki`, `xlsx`.

## Upstream Basis

- Source mapped from pinned Pandoc OPML reader behavior: `https://raw.githubusercontent.com/jgm/pandoc/912bfa5e2e3f5c74eb125dfc19404f67c61ca58b/src/Text/Pandoc/Readers/OPML.hs`.
- OPML `head/title`, `ownerName`, and `dateModified` become document metadata.
- Nested `outline` elements become heading blocks by depth.
- `type="link"` outlines become linked heading text through the `url` attribute.
- `_note` attributes are parsed through the existing Markdown reader into following blocks.
- Outline `text` is parsed as bounded HTML inline markup rather than treated as literal escaped text.

## New OPML Coverage

- Added `OpmlReader` with safe XML parsing, source hash metadata, root attributes, outline counts, link-outline counts, note counts, and payload exposure policy metadata.
- Added bounded HTML inline fragment handling for OPML outline text, including emphasis, strong, code, links, images, line breaks, subscript, superscript, underline, mark, and strikeout.
- Added converter dispatch and registry support for `opml` as a partial upstream input implementation.
- Added regression coverage for metadata mapping, nested headings, link headings, Markdown notes, WordPress block output, Markdown output, and malformed XML rejection.

## Remaining OPML Gaps

- Full Pandoc OPML fixture parity remains open.
- The reader does not yet claim exact Pandoc behavior for every HTML inline edge, malformed outline recovery edge, nonstandard attribute extension, or command-line option interaction.
- Package/container handling is not relevant to OPML in this slice.

## Verification

- `php -l lanes/pandoc/src/OpmlReader.php`: passed.
- `php -l lanes/pandoc/src/PandocConverter.php`: passed.
- `php -l lanes/pandoc/src/PandocFormatRegistry.php`: passed.
- `php -l lanes/pandoc/tests/OpmlReaderTest.php`: passed.
- `php -l lanes/pandoc/tests/PandocConverterTest.php`: passed.
- `php -l lanes/pandoc/tests/PandocFormatRegistryTest.php`: passed.
- `php tools/run-tests.php lanes/pandoc/tests/OpmlReaderTest.php lanes/pandoc/tests/PandocConverterTest.php lanes/pandoc/tests/PandocFormatRegistryTest.php`: 3 files, 226 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/DocBookReaderTest.php lanes/pandoc/tests/XmlReaderTest.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/BibliographyReaderTest.php lanes/pandoc/tests/DelimitedTextReaderTest.php lanes/pandoc/tests/IpynbReaderTest.php lanes/pandoc/tests/RtfReaderTest.php lanes/pandoc/tests/PlainWriterTest.php lanes/pandoc/tests/LegacyDocReaderTest.php lanes/pandoc/tests/PandocConverterTest.php lanes/pandoc/tests/PandocFormatRegistryTest.php lanes/pandoc/tests/RichPackageUnsupportedFormatRegistryTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/pandoc/tests/DocxReaderTest.php lanes/pandoc/tests/EpubPackageMetadataReaderTest.php lanes/pandoc/tests/EpubReaderTest.php lanes/pandoc/tests/JsonReaderWriterTest.php lanes/pandoc/tests/MarkdownReaderTest.php lanes/pandoc/tests/NativeReaderEscapeTest.php lanes/pandoc/tests/OdtReaderTest.php lanes/pandoc/tests/OpmlReaderTest.php lanes/pandoc/tests/PdfReaderTest.php`: 22 files, 18,234 assertions, 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/pandoc/tests/PdfReaderTest.php`: 2 files, 3,051 assertions, 0 failures.
- Local problematic PDF smoke: `tables=10 geometry=10 rects=896 mode=geometry`.
- Exact-string guard for the local problematic PDF path/content terms across current code, tests, status, and audits before this audit: 0 hits.
