# Pandoc HTML Reader Dispatch Audit - 2026-06-21T02:12:46Z

## Registry Snapshot

- Upstream Pandoc input formats tracked: 51.
- Native PHP upstream input readers registered as partial: 28.
- Upstream input formats still unsupported: 23.
- Project-local non-upstream inputs remain `pdf` and `doc`.
- `html` now dispatches to `PortLibs\Pandoc\HtmlReader` instead of being registered directly to `MarkdownReader`.

Unsupported upstream inputs after this slice remain:

`asciidoc`, `creole`, `djot`, `dokuwiki`, `fb2`, `haddock`, `jira`, `mediawiki`, `man`, `mdoc`, `muse`, `opml`, `org`, `pod`, `pptx`, `rst`, `t2t`, `textile`, `tikiwiki`, `twiki`, `typst`, `vimwiki`, `xlsx`.

## New HTML Coverage

- Added dedicated `HtmlReader` dispatch for the `html` input token.
- Preserves the existing HTML-capable reader bridge with `htmlNativeDivs` enabled by default, matching the previous converter behavior.
- Records document-level HTML provenance through `sourceFormat` and metadata keys for reader class, reader scope, delegate class, native-div setting, source byte count, source hash, and payload exposure policy.
- Added converter coverage proving `PandocConverter::read(..., 'html')` goes through the registered `HtmlReader` path while keeping title metadata and WordPress block output intact.
- Added registry coverage proving `html` is no longer registered directly to `MarkdownReader`.

## Remaining HTML Gaps

- Full Pandoc HTML reader parity remains open.
- The current `HtmlReader` intentionally preserves behavior through the existing HTML-capable bridge; more parsing internals still need to move behind HTML-owned methods.
- Full HTML5 tree-construction parity, malformed document recovery, full raw/native-div option matrix, and exact upstream command fixture parity remain open.
- XHTML/package-reader handoff from EPUB and related package inputs should continue to share safe HTML behavior without claiming complete browser-parser parity.

## Verification

- `php -l lanes/pandoc/src/HtmlReader.php`: passed.
- `php -l lanes/pandoc/src/PandocFormatRegistry.php`: passed.
- `php -l lanes/pandoc/src/PandocConverter.php`: passed.
- `php -l lanes/pandoc/tests/PandocFormatRegistryTest.php`: passed.
- `php -l lanes/pandoc/tests/PandocConverterTest.php`: passed.
- `php tools/run-tests.php lanes/pandoc/tests/PandocFormatRegistryTest.php lanes/pandoc/tests/PandocConverterTest.php`: 2 files, 195 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/Html5DomTest.php lanes/pandoc/tests/Html5DomFragmentTest.php`: 4 files, 13,708 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/DocBookReaderTest.php lanes/pandoc/tests/XmlReaderTest.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/BibliographyReaderTest.php lanes/pandoc/tests/DelimitedTextReaderTest.php lanes/pandoc/tests/IpynbReaderTest.php lanes/pandoc/tests/RtfReaderTest.php lanes/pandoc/tests/PlainWriterTest.php lanes/pandoc/tests/LegacyDocReaderTest.php lanes/pandoc/tests/PandocConverterTest.php lanes/pandoc/tests/PandocFormatRegistryTest.php lanes/pandoc/tests/RichPackageUnsupportedFormatRegistryTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/pandoc/tests/DocxReaderTest.php lanes/pandoc/tests/EpubPackageMetadataReaderTest.php lanes/pandoc/tests/EpubReaderTest.php lanes/pandoc/tests/JsonReaderWriterTest.php lanes/pandoc/tests/MarkdownReaderTest.php lanes/pandoc/tests/NativeReaderEscapeTest.php lanes/pandoc/tests/OdtReaderTest.php lanes/pandoc/tests/PdfReaderTest.php`: 21 files, 18,203 assertions, 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/pandoc/tests/PdfReaderTest.php`: 2 files, 3,051 assertions, 0 failures.
- Local problematic PDF smoke: `tables=10 geometry=10 rects=896 mode=geometry`.
- Exact-string guard for problematic PDF path/content terms across this checkout: 0 hits.
