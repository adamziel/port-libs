# ODF/OpenDocument Line Numbering Configuration Metadata

Slice: `pandoc-odf-open-document-core-current-base-20260609T001731Z`
Base accepted HEAD: `e681d9cd3726e0b2d0a8b66aaf879a79d22125f0`

## Behavior

Native `OdfReader` now preserves `text:linenumbering-configuration` package
metadata in content declarations and import reports. The handoff records
whether line numbering is enabled, the source style, offset, number position,
increment, empty-line/text-box/page-restart policy, numbering format, and the
`text:linenumbering-separator` increment/text.

The WordPress ODF handoff example includes the same source fixture and
self-test coverage. Rendered WordPress blocks intentionally do not synthesize
line numbers; the ODF line-numbering data stays review metadata.

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note existed for this lane.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  passed with `1 test files, 2566 assertions, 0 failures`.
- Red-first: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  failed with `1 test files, 2567 assertions, 1 failures` because
  `lineNumberingConfiguration` metadata was absent.
- Final focused reader run: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  passed with `1 test files, 2589 assertions, 0 failures`, adding 23 focused
  assertions and one new PASS case.
- Example smoke: `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`
  passed with `odf open document handoff self-test ok`.

`php -l` for changed PHP files and `git diff --check -- lanes/pandoc` are part
of the final verification for this handoff. Root harness status:
not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP ODF package
reader, DOM namespace helpers, content declaration/import-report flow,
`ZipPackage` fixtures, and the existing Markdown/WordPress writers. No Pandoc,
Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip,
external converter, online service, live provider test, or live-service
provider test was executed.

## Non-Overlap

This does not touch accepted ODF notes configuration, footnote separator,
tracked changes, table tracked changes, fields, headings, sections, lists,
tables, validations, data-pilot metadata, database ranges, embedded objects,
forms, charts, or drawing layers.

## Next

A next non-overlapping ODF slice could cover presentation-page metadata or
additional style-driven table/cell semantics.
