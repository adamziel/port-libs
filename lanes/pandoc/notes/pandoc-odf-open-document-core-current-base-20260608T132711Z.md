# pandoc-odf-open-document-core-current-base-20260608T132711Z

## Scope

Implemented bounded ODF/OpenDocument content-field support for `text:user-defined` inside `OdfReader`. The reader now routes user-defined content fields through the existing field-span path so visible custom-field text and metadata survive AST, Markdown, and WordPress handoff.

This is distinct from document-level `meta:user-defined` metadata and from previously accepted ODF field slices such as conditional text, hidden paragraphs, drop-down fields, page variables, chapter/file/statistic fields, and table subtotal metadata.

## Source Truth

The pinned Pandoc upstream checkout was not present under `/home/claude/port-libs/.upstream-cache/pandoc` in this environment, so this slice used the accepted Pandoc lane ODF support-library contract and existing native ODF fixture behavior as local source truth. No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external converter, online service, live provider test, or live-service provider test was executed.

## Behavior

- `text:user-defined` content fields now render through `OdfReader::fieldSpan()` with `fieldType` set to `user-defined`.
- Field metadata preserves `text:name`, `office:value-type`, `office:string-value`, `office:boolean-value`, `office:date-value`, and `text:fixed` through AST attributes.
- Empty user-defined fields fall back to available metadata values, so boolean/date/string fields remain visible instead of disappearing from paragraph text.
- WordPress output preserves safe `data-odf-field-*` attributes for reviewer audit.

## Verification

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` passed with `1 test files, 1961 assertions, 0 failures`.
- Red-first: after adding the user-defined field expectations, `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` failed with `1 test files, 1962 assertions, 1 failures` because unsupported `text:user-defined` fields were skipped and paragraph text rendered as `Custom source id , review state  on .`.
- Final: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` passed with `1 test files, 1993 assertions, 0 failures`.
- WordPress smoke: `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test` passed.

Focused movement: +1 PHP PASS case and +32 focused assertions. Lane `phpPass` moves from 1654 to 1655. `benchmarkDenominator.mapped` moves from 2074 to 2075. `mappedOdfOpenDocumentCoreCases` moves from 13 to 14, and `odfOpenDocumentCoreAssertions` moves from 295 to 327.

## Dependency Closure

No new native PHP support component is needed. This slice reuses:

- `OdfReader` field-span and field-metadata plumbing.
- `MarkdownWriter` bracketed-span attribute output.
- `WordPressBlockWriter` safe span/data-attribute handling.
- In-process ODT package fixtures used by existing focused tests.

Follow-up ODF work should stay non-overlapping, for example numeric/date field formatting metadata, additional reference/index field metadata, or package relationship provenance.
