# ODF OpenDocument Core Current Base: Cross-Reference Fields

Slice: `pandoc-odf-open-document-core-current-base-20260609T055907Z`
Base: `7ed2f69b027c00a8c9af1b63d2dfcdebbab97ac6`

## Behavior

Native `OdfReader` inline parsing now preserves bounded OpenDocument
cross-reference fields:

- `text:sequence-ref` visible labels, `text:ref-name`, and
  `text:reference-format`.
- `text:note-ref` visible labels, `text:ref-name`, `text:note-class`, and
  `text:reference-format`.

The fields are emitted as inert `odf-field` spans for review handoff. This
slice does not evaluate references, renumber sequence labels, renumber notes,
resolve office-suite cross-reference targets, or run external converters.

## Focused Evidence

Baseline before the patch:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 3110 assertions, 0 failures
```

Red-first after adding the focused expectations:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 3111 assertions, 1 failures
```

The failing expectation showed the inline labels were dropped:
`References  and footnote  stay reviewable.`

Final focused checks:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 3134 assertions, 0 failures

php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test
odf open document handoff self-test ok
```

Lane JSON was updated to mapped denominator `2801`.
`odfOpenDocumentCoreCases` and `mappedOdfOpenDocumentCoreCases` are now `14`.
`odfOpenDocumentCoreAssertions` is now `319`.
`phpPass` moves from `2411` to `2412`.

## Dependency Closure

No new native PHP support component is needed. The slice reuses existing
`OdfReader` DOM parsing, in-memory `ZipPackage` ODT fixtures, Pandoc-like AST
span metadata, `MarkdownWriter`, and `WordPressBlockWriter` attribute output.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, external converter, online service, live provider test, or
live-service provider test was executed.

## Non-Overlap And Follow-Up

This is additive to existing ODF field support and does not repeat recent
data-pilot, database-range, dropdown, page/statistic field, page-variable,
chapter/file-name, metadata field, note-body, bookmark, or label-range slices.

Good follow-up ODF slices: page-continuation fields, RDF/metadata extraction,
or remaining style-driven table cell semantics.
