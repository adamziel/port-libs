# Pandoc Legacy DOC/CFB Current-Base DOCPROPERTY/INFO Field Slice

Date: 2026-06-08 UTC
Lane: pandoc
Micro-slice: pandoc-legacy-doc-cfb-core-current-base-20260608T221455Z
Accepted base: 238c756134d68ede9072631361599c436a2f8d32

## Scope

This slice maps one bounded legacy Word DOC field handoff behavior: cached `DOCPROPERTY` and `INFO` field results are preserved as inert data-field spans for Markdown and WordPress review, while the field instructions and property names remain hidden from visible body text.

The implementation is intentionally narrow. It reuses the existing native CFB fixture builder, LegacyDocReader field-tokenization path, Markdown writer span serialization, and WordPress block writer handoff. No Pandoc, Cabal, Haskell runner, Word, LibreOffice, zip/unzip, external office tool, online service, live provider test, or live-service provider test was run.

## Behavior

`LegacyDocReader::dataFieldAttrs()` now classifies:

- `DOCPROPERTY` as `document-property`
- `INFO` as `document-info`

The cached displayed field result stays visible. The original field instruction, document-property/info name, and formatting switch are preserved as inert `data-legacy-doc-*` metadata on the span so WordPress import reviewers can inspect source provenance without exposing hidden field-code text as body content.

## Evidence

No `port-pandoc` rework note existed for this slice before implementation.

Red-first focused test:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 1698 assertions, 1 failures
```

The new case failed because `DOCPROPERTY`/`INFO` cached results were emitted as plain text instead of inert data-field spans.

Final focused test:

```text
php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php
1 test files, 1722 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-legacy-doc-info-field-handoff.php --self-test
legacy doc info-field handoff self-test ok
```

PHP lint:

```text
php -l lanes/pandoc/src/LegacyDocReader.php
php -l lanes/pandoc/tests/LegacyDocReaderTest.php
php -l lanes/pandoc/examples/wordpress-legacy-doc-info-field-handoff.php
```

All changed PHP files reported no syntax errors.

## Status Delta

- `lane-status.json` `phpPass`: 1912 -> 1913
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: 2335 -> 2336
- `mappedLegacyDocCfbCoreCases`: 7 -> 8
- `legacyDocCfbCoreAssertions`: 64 -> 88
- Focused assertion growth inside `LegacyDocReaderTest.php`: +24 assertions

## Dependency Closure

No new support component is needed. The slice reuses existing native CFB parsing, legacy DOC field parsing, AST span handoff, Markdown serialization, and WordPress block serialization. External converters and upstream runners remain out of scope for this isolated support-library slice.

## Non-Overlap

This patch does not touch accepted CFB/FIB/DIFAT/MiniFAT/CLX/DOP/ObjectPool parsing, macro/action fields, pictures, bookmarks, notes/comments, sections, styles, list tables, PlcfldEdn/textbox Plcfld metadata, field-end flags, hyperlink fields, form fields, merge/docvariable fields, SET, ASK/FILLIN, SYMBOL, generated TOC/index fields, numbering/AUTONUM fields, INCLUDE fields, nested-field handling, or OLE reserved hyperlink properties.

Useful follow-up remains a non-overlapping native CFB/DOC gap such as OLE link metadata follow-up, another Plcf table family, or list-table cross-references for automatic numbering.
