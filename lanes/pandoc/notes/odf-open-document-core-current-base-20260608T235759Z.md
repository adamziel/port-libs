# ODF/OpenDocument Footnote Separator Metadata

Slice: `pandoc-odf-open-document-core-current-base-20260608T235759Z`
Base accepted HEAD: `98e36d1bfbcd2aff359b39b4120999431e5e0fde`

## Behavior

Native `OdfReader` now preserves `style:footnote-sep` children under
`text:notes-configuration`. The handoff records separator width, before/after
spacing, line style, adjustment, relative width, and color in content
declarations, import reports, and the parsed footnote node's
`noteConfiguration` metadata.

The WordPress ODF handoff example includes the same separator fixture and
self-test coverage while rendering footnote body content normally.

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note existed for this lane.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  passed with `1 test files, 2549 assertions, 0 failures`.
- Red-first: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  failed with `1 test files, 2551 assertions, 1 failures` because
  `footnoteSeparator` and `noteConfigurationSeparatorCount` were absent.
- Final focused reader run: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  passed with `1 test files, 2566 assertions, 0 failures`, adding 17 focused
  assertions and one new PASS case.
- Example smoke: `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`
  passed with `odf open document handoff self-test ok`.

`php -l` for changed PHP files and `git diff --check -- lanes/pandoc` are part
of the final verification for this handoff. Root harness status:
not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP ODT package
reader, DOM namespace helpers, content declaration/import-report flow, existing
footnote AST metadata handoff, and Markdown/WordPress writers. No Pandoc,
Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip,
external converter, online service, live provider test, or live-service
provider test was executed.

## Non-Overlap

This does not touch accepted ODF subtotal rules, data-pilot metadata, typed
field/meta values, settings XML, link-event metadata, named list continuation,
dropdown fields, table value/formula metadata, content validations, or table
style-map slices.

## Next

A next non-overlapping ODF slice could cover line-numbering configuration,
presentation-page metadata, or additional style-driven table/cell semantics.
