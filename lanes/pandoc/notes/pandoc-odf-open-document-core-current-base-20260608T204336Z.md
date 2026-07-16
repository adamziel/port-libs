# ODF Inline Metadata Span Handoff

Slice: `pandoc-odf-open-document-core-current-base-20260608T204336Z`
Base: `6479f65c1465d77f871d7146aaaa2d022aa27e3f`

## Behavior

Implemented bounded native ODT inline metadata span handoff for `text:meta` and
`text:meta-field` in `OdfReader`. The reader now preserves those elements as
inert review spans with `odf-meta` / `odf-meta-field` classes,
`metaMetadata`, `data-odf-meta-*` attributes, fallback display text from typed
ODF values, Markdown/WordPress writer handoff, and separate
`importReport.content.metaSpanCount` reporting.

This intentionally does not count inline metadata spans as ODF fields, so
existing `fieldCount` behavior for document metadata fields and
`text:user-defined` remains stable.

## Source Truth And Non-Overlap

The local upstream Pandoc checkout was unavailable in this isolated worktree, so
source truth is the accepted lane ODF support-library contract and existing
native reader behavior around inert review spans. This slice is distinct from
recent accepted ODF work for package `meta.xml` policy/modification metadata,
source metadata fields, `text:user-defined`, script/macro/DDE fields,
drop-down fields, table-cell style properties, tracked table changes,
data-pilot tables, named expressions, and database subtotal rules.

## Verification

Baseline focused check before adding the behavior:

`php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`

Result: `1 test files, 2232 assertions, 0 failures`.

Red-first probe after adding the test and before implementation:

`php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`

Result: failed the new `maps ODT inline meta spans into review metadata spans`
case. The paragraph rendered as `Reviewed  with  confidence and .`, proving
`text:meta` / `text:meta-field` content was dropped.

Final focused checks:

`php -l lanes/pandoc/src/OdfReader.php && php -l lanes/pandoc/tests/OdfReaderTest.php && php -l lanes/pandoc/examples/wordpress-odf-open-document-handoff.php`

Result: no syntax errors detected in all changed PHP files.

`php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`

Result: `1 test files, 2268 assertions, 0 failures`.

`php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`

Result: `odf open document handoff self-test ok`.

`git diff --check -- lanes/pandoc`

Result: passed with no output.

Root harness: not run - isolated micro-slice.

## Counters

- `phpPass`: `1824 -> 1825`
- focused ODF assertions: `2232 -> 2268` (`+36`)
- `benchmarkDenominator.mapped`: `2248 -> 2249`
- `odfOpenDocumentCoreCases`: `13 -> 14`
- `mappedOdfOpenDocumentCoreCases`: `13 -> 14`
- `odfOpenDocumentCoreAssertions`: `295 -> 331`

## Dependency Closure

No new support component is needed. The patch reuses native `OdfReader` inline
parsing, `AstNode` spans, `MarkdownWriter`, `WordPressBlockWriter`,
`OdfReaderTest.php`, and the lane-local WordPress ODF handoff example. Pandoc,
Cabal/Haskell runners, Word, LibreOffice, zip/unzip, external converters,
external template engines, online services, live provider tests, and
live-service provider tests were not executed.

## Follow-Up

For the next ODF slice, choose a non-overlapping native ODT handoff gap such as
text sender metadata fields, data-validity/input-message metadata, tracked
table dependency metadata, or drawing caption/anchor metadata not already
covered by this inline metadata span handoff.
