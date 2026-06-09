# ODF OpenDocument Creation Time Fallback

Slice: `pandoc-odf-open-document-core-current-base-20260609T041826Z`
Base: `8545b79dd7a73e9ae0947d693d1f23920ee07f78`

## Implementation

Native `OdfReader` metadata handling now derives an ODF duration-style
`creationTime` value from `meta:creation-date` timestamps and uses it to fill
empty `text:creation-time` fields. The generated review span preserves
`meta.xml` provenance through `fieldMetadata`, Markdown attributes, and
WordPress block attributes.

This stays metadata-only. The reader does not execute office applications,
expand templates, run Pandoc, run Haskell tests, or shell out to zip/unzip.

## Red-First Evidence

After adding the focused test and before implementing the fallback:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 2939 assertions, 1 failures
```

The new case failed because an empty `text:creation-time` rendered as blank
instead of `PT09H30M15S`.

## Final Verification

Focused ODF reader test:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php
1 test files, 2951 assertions, 0 failures
```

WordPress ODF metadata-field smoke:

```text
php lanes/pandoc/examples/wordpress-odf-metadata-field-handoff.php --self-test
odf metadata field handoff self-test ok
```

Syntax and JSON checks:

```text
php -l lanes/pandoc/src/OdfReader.php
No syntax errors detected in lanes/pandoc/src/OdfReader.php

php -l lanes/pandoc/tests/OdfReaderTest.php
No syntax errors detected in lanes/pandoc/tests/OdfReaderTest.php

php -l lanes/pandoc/examples/wordpress-odf-metadata-field-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-odf-metadata-field-handoff.php

php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'
pandoc json ok
```

## Status Delta

- `phpPass`: `2286 -> 2287`
- `benchmarkDenominator.mapped`: `2687 -> 2688`
- `odfOpenDocumentCoreCases`: `13 -> 14`
- `mappedOdfOpenDocumentCoreCases`: `13 -> 14`
- `odfOpenDocumentCoreAssertions`: `295 -> 308`
- New focused assertion delta in `OdfReaderTest.php`: `+13`

## Dependency Closure

No new support component is needed. This slice reuses native PHP `OdfReader`
DOM parsing, `ZipPackage` fixture construction, `MarkdownWriter`,
`WordPressBlockWriter`, the focused ODF reader suite, and the existing
WordPress ODF metadata-field handoff example.

Full upstream Pandoc runner parity remains a separate upstream-runner
dependency task requiring a hydrated Pandoc checkout and Haskell test
executables.

## Non-Overlap

This does not repeat accepted ODF explicit creation date/time field handling,
empty source metadata fallbacks for title/author/keywords/template fields,
sender fields, page/chapter/statistic fields, dropdown fields, database range
metadata, data-pilot tables, named expressions, calculation settings, tracked
table changes, or table scenarios.

Follow-up ODF slices should target a non-overlapping native package/content
gap such as print ranges, additional data-pilot source edge metadata, or
style-driven table cell semantics.

## Root Harness

Not run - isolated micro-slice.
