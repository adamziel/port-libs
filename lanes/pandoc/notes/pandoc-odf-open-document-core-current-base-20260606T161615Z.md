# ODF OpenDocument Page Variable And Statistic Fields

Slice: `pandoc-odf-open-document-core-current-base-20260606T161615Z`
Base accepted HEAD: `75fa47f3fd4a092265a672a9ef4ebfe9b906474c`

## Behavior

Ported one bounded ODF/OpenDocument field handoff cluster into the native PHP reader. `OdfReader` now treats these `text:*` elements as reviewable field spans instead of dropping their source text:

- `text:page-variable-set`
- `text:page-variable-get`
- `text:chapter`
- `text:file-name`
- `text:word-count`
- `text:sentence-count`
- `text:paragraph-count`
- `text:character-count`
- `text:table-count`
- `text:image-count`
- `text:object-count`

The field metadata path also carries `text:current-value`, `text:ref-name`, `text:display`, `text:outline-level`, `style:num-format`, and `text:format-source` into `fieldMetadata` and `data-odf-field-*` attributes for Markdown and WordPress review output.

This reuses the existing native ODT package reader, `odf-field` AST span, `MarkdownWriter`, and `WordPressBlockWriter` support. No new support component was needed.

## Evidence

Baseline focused test before the new case:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 1251 assertions, 0 failures
```

Red-first focused test after adding the case and before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 1252 assertions, 1 failures
Expected: 'Review page 4 of 4, chapter 2 Source review, file source/review.odt, counts 128/6/7/640/2/1/3.'
Actual: 'Review page  of , chapter , file , counts //////.'
```

Final focused test after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 1285 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test
odf open document handoff self-test ok
```

Expected lane movement:

- `phpPass`: `1362 -> 1363`
- `benchmarkDenominator.mapped`: `1775 -> 1776`
- `odfOpenDocumentCoreCases`: `10 -> 11`
- `mappedOdfOpenDocumentCoreCases`: `10 -> 11`
- `odfOpenDocumentCoreAssertions`: `217 -> 251`

## Dependency Closure

No new dependency row is needed. The slice uses existing bounded native PHP components: `OdfReader`, `ZipPackage`, `MarkdownWriter`, and `WordPressBlockWriter`.

Excluded by supervisor scope: Pandoc execution, Cabal solver/build/test commands, Haskell runners, Word, LibreOffice, zip/unzip, external converters, online services, live provider tests, and live-service provider tests.

## Follow-Up

Non-overlapping ODF work remains for database fields, richer generated-index entry layout metadata, tab-stop position metadata, and additional draw/control handoffs.
