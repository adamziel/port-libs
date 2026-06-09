# ODF OpenDocument Variable Field State

Slice: `pandoc-odf-open-document-core-current-base-20260609T022710Z`

Accepted base: `ad0b29726a9f952ccc81c677e4a1cb6fc0f76215`

## Behavior

Native `OdfReader` now resolves empty `text:variable-get` fields from the
current value established by earlier `text:variable-set` or
`text:variable-input` elements. Matching `text:variable-decl` metadata is
preserved on resolved variable fields so WordPress review packets keep the
declared value type and inert `data-odf-field-declared` provenance.

The WordPress ODF handoff example now includes a declared variable fallback
paragraph and self-test assertion for the rendered `variable-get` review span.

## Evidence

No current `port-pandoc-*.needs-lane-rework.md` note existed for this lane
before the slice.

Focused reader verification:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 2750 assertions, 0 failures
```

WordPress example smoke:

```text
php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test
odf open document handoff self-test ok
```

This adds one focused PHP PASS case and 34 focused assertions in
`OdfReaderTest.php`. Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP ODF DOM parser,
existing content declaration storage, existing field-span AST metadata,
`MarkdownWriter`, `WordPressBlockWriter`, and the existing ZIP-backed ODF
fixture builder.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, external converter, online service, live provider test, or
live-service provider test was executed.

## Non-Overlap

This does not repeat accepted ODF dropdown fields, page-variable get/set
fields, chapter/file/statistic fields, conditional/hidden fields, DDE fields,
database fields, database ranges, data-pilot metadata, named expressions,
table annotations, table tracked changes, drawing layers, chart/object
metadata, list continuation, list image metadata, note configuration, or table
caption slices. It is limited to variable field state handoff for empty
`text:variable-get` and declaration metadata.

## Next

A non-overlapping ODF follow-up could cover variable expression edge metadata,
additional tracked-change provenance, or another style-driven table/list
semantic gap.
