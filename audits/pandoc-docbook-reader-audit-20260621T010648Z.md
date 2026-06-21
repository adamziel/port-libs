# Pandoc DocBook Reader Audit - 2026-06-21T01:06:48Z

## Registry Snapshot

- Upstream Pandoc input formats tracked: 51.
- Native PHP upstream input readers registered as partial: 28.
- Upstream input formats still unsupported: 23.
- Project-local non-upstream inputs remain `pdf` and `doc`.
- `docbook` now dispatches to `PortLibs\Pandoc\DocBookReader` instead of the Markdown reader.

Unsupported upstream inputs after this slice remain:

`asciidoc`, `creole`, `djot`, `dokuwiki`, `fb2`, `haddock`, `jira`, `mediawiki`, `man`, `mdoc`, `muse`, `opml`, `org`, `pod`, `pptx`, `rst`, `t2t`, `textile`, `tikiwiki`, `twiki`, `typst`, `vimwiki`, `xlsx`.

## New DocBook Coverage

- Safe XML loading through `XmlHtmlDom::loadXmlDocument`.
- Root validation for DocBook structural roots, plus compatibility parsing for standalone `informaltable` and `table` fragments used by existing command fixtures.
- Document metadata handoff from existing DocBook structure, review, and bibliography packets.
- AST mapping for document title, subtitle, abstract, sections, nested headings, paragraphs, inline emphasis/code/links/xrefs, itemized and ordered lists, procedures, variable lists, block quotes, admonitions, literal/code blocks, figures/media objects, CALS tables, and bibliography entries.
- CALS table preservation for `colspec` widths, `namest`/`nameend` column spans, `morerows` row spans, header/body/footer sections, and cell alignments.
- Follow-up parity slice maps `refentry` names/purpose, `refsect1`-`refsect3`, `simplelist`, `segmentedlist`, `calloutlist`, `glossary`/`glosslist`, `qandaset`, and inline/display equations into existing shared AST nodes.
- Follow-up parity slice accepts DocBook `set` roots consistently across the reader and review helpers, maps nested `set`/`book`/section heading levels, preserves inline `anchor`, `indexterm`, and `co` markers as AST spans, and attaches `programlistingco` `areaspec` entries to code-block metadata.
- Follow-up parity slice maps single-key DocBook `citation` text and `biblioref` targets into native citation AST nodes while preserving freeform citation text as DocBook-marked spans.
- Follow-up parity slice maps semicolon-separated grouped DocBook citations with prefix, suffix, and suppress-author forms into structured citation payloads.
- Follow-up parity slice resolves supplied DocBook media resources through `MediaBag`, maps extracted image URLs with provenance metadata, preserves selected `imagedata` dimensions/format attributes, and records missing media without exposing raw bytes in document metadata.
- Follow-up parity slice generates DocBook callout labels for `co` and `area` markers, carries those labels into `programlistingco` area metadata, and annotates linked `calloutlist` entries with their resolved labels.
- Follow-up parity slice resolves empty DocBook `xref`/`link` labels from target titles, labels, and `endterm` text while preserving target provenance in WordPress-safe `data-docbook-xref-*` attributes.
- Follow-up parity slice honors DocBook `xreflabel` for cross-reference labels, records the selected `imageobject` alternative, preserves `textobject`/`alt`/`caption` media fallbacks, and degrades audio/video/object data references to provenance-marked links instead of dropping them.

## Remaining DocBook Gaps

- Full Pandoc DocBook reader parity remains open.
- DocBook namespace/version edge cases beyond the bounded structural roots still need upstream fixture mapping.
- Full bibliography/citation semantics are not complete; full locator taxonomy, author-in-text forms, nested markup in affixes, and CSL bibliography output remain open, while current bibliography entries are represented as definition lists with structural diagnostics preserved in metadata.
- Full media object semantics remain open for package/entity catalogs, richer object selection policies beyond first image/text fallback, embedded object metadata, full DocBook cross-reference style/numbering semantics, full glossary/set/refentry option semantics, and exact Pandoc block/inline constructor parity.
- Dedicated `docbook4`/`docbook5` input aliases are not upstream input tokens in the current registry; output aliases remain unchanged.

## Format Plan Update

1. XML/JATS/BITS direct reader: complete as a bounded partial reader.
2. DocBook direct reader: complete as a bounded partial reader in this slice.
3. HTML follow-up has started: `html` input now dispatches through `PortLibs\Pandoc\HtmlReader`, preserving current raw HTML/native-div/table/link/list behavior through the existing bridge while full HTML5 tree construction remains open.
4. Continue PDF work in parallel when prioritized: multi-page table continuation, stricter grid inference, background propagation, and tagged-vs-geometry reconciliation.

## Verification

- `php -l lanes/pandoc/src/DocBookReader.php`: passed.
- `php tools/run-tests.php lanes/pandoc/tests/DocBookReaderTest.php lanes/pandoc/tests/PandocConverterTest.php lanes/pandoc/tests/PandocFormatRegistryTest.php`: 3 files, 226 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/DocBookReaderTest.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/tests/MarkdownReaderTest.php lanes/pandoc/tests/PandocConverterTest.php lanes/pandoc/tests/PandocFormatRegistryTest.php`: 5 files, 10,945 assertions, 0 failures.
- Broad smoke with DocBook/XML/Markdown/package/PDF readers: 21 files, 18,064 assertions, 0 failures.
- Follow-up focused DocBook reader test: 1 file, 55 assertions, 0 failures.
- Current focused DocBook reader test: 1 file, 92 assertions, 0 failures.
- Current focused DocBook/XML/Markdown/registry suite: 5 files, 10,982 assertions, 0 failures.
- Current broad smoke with DocBook/XML/Markdown/package/PDF readers: 21 files, 18,101 assertions, 0 failures.
- Latest focused DocBook reader test: 1 file, 107 assertions, 0 failures.
- Latest focused DocBook/XML/Markdown/registry suite: 5 files, 10,997 assertions, 0 failures.
- Latest broad smoke with DocBook/XML/Markdown/package/PDF readers: 21 files, 18,116 assertions, 0 failures.
- Media-resource focused DocBook reader test: 1 file, 132 assertions, 0 failures.
- Media-resource focused DocBook/XML/Markdown/registry suite: 5 files, 11,022 assertions, 0 failures.
- Media-resource PDF/markerPDF guard suite: 2 files, 3,051 assertions, 0 failures.
- Media-resource broad smoke with DocBook/XML/Markdown/package/PDF readers: 21 files, 18,141 assertions, 0 failures.
- Callout-label focused DocBook reader test: 1 file, 140 assertions, 0 failures.
- Callout-label focused DocBook/XML/Markdown/registry suite: 5 files, 11,030 assertions, 0 failures.
- Callout-label broad smoke with DocBook/XML/Markdown/package/PDF readers: 21 files, 18,149 assertions, 0 failures.
- Xref-title focused DocBook reader test: 1 file, 160 assertions, 0 failures.
- Xref-title focused DocBook/XML/Markdown/registry suite: 5 files, 11,050 assertions, 0 failures.
- Xref-title broad smoke with DocBook/XML/Markdown/package/PDF readers: 21 files, 18,169 assertions, 0 failures.
- Media-alternative/xreflabel focused DocBook reader test: 1 file, 184 assertions, 0 failures.
- Media-alternative/xreflabel focused DocBook/XML/Markdown/registry suite: 5 files, 11,074 assertions, 0 failures.
- Media-alternative/xreflabel PDF/markerPDF guard suite: 2 files, 3,051 assertions, 0 failures.
- Media-alternative/xreflabel broad smoke with DocBook/XML/Markdown/package/PDF readers: 21 files, 18,193 assertions, 0 failures.
- Local PDF hardcode guard: no source or test hits for problematic-document text, and the local problematic PDF still reports `tables=10 geometry=10 rects=896 mode=geometry`.
