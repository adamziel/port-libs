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

## Remaining DocBook Gaps

- Full Pandoc DocBook reader parity remains open.
- DocBook namespace/version edge cases beyond the bounded structural roots still need upstream fixture mapping.
- Full bibliography/citation semantics are not complete; grouped citations, affixes, locator parsing, and CSL bibliography output remain open, while current bibliography entries are represented as definition lists with structural diagnostics preserved in metadata.
- Full media object resolution, resource packaging, generated callout numbering/link resolution, full glossary/set/refentry option semantics, and exact Pandoc block/inline constructor parity remain open.
- Dedicated `docbook4`/`docbook5` input aliases are not upstream input tokens in the current registry; output aliases remain unchanged.

## Format Plan Update

1. XML/JATS/BITS direct reader: complete as a bounded partial reader.
2. DocBook direct reader: complete as a bounded partial reader in this slice.
3. Next highest-reuse format remains HTML: split `html` input from `MarkdownReader` into a dedicated HTML DOM reader while preserving current raw HTML/native-div/table/link/list behavior.
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
- Invoice hardcode guard: no source/test/audit/status hits for invoice-specific text, and the local problematic PDF still reports `tables=10 geometry=10 rects=896 mode=geometry`.
