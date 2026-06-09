# Pandoc Table Geometry Header Abbreviation Writer Diagnostics

Slice: `pandoc-table-geometry-core-current-base-20260609T035525Z`
Base accepted HEAD: `4cca1c57da8720c140326c22572dbfb45205f318`

## Behavior

This slice adds table-geometry writer downgrade diagnostics for source table header abbreviations. WordPress can preserve source `abbr` metadata as safe `th[abbr]` attributes, but Markdown pipe tables, AsciiDoc tables, and LaTeX tables cannot carry the source abbreviation metadata natively. `TableGeometry::writerDowngradeDiagnostics()` now emits explicit `header-abbreviation` diagnostics for those non-HTML writers, including the affected header cell ids, scopes, text, abbreviations, caption, and required writer feature.

The WordPress table-geometry handoff example now exercises the same review packet shape and verifies that WordPress has no abbreviation downgrade while Markdown, AsciiDoc, and LaTeX do.

## Source Truth And Non-Overlap

The source truth is the existing lane-local Pandoc-like table AST contract and accepted table-geometry behavior for HTML/source header metadata, WordPress table attribute emission, and non-HTML writer downgrade review packets. No hydrated Pandoc upstream checkout was available for this worktree, and no upstream runner was executed.

This does not overlap the accepted table geometry cases for section boundary rowspans, multiple body writer downgrade metadata, declared-column overflow diagnostics, rowHeadColumns WordPress row headers, or generic source-header attribute writer diagnostics. The new behavior is specifically the `abbr`-metadata handoff for source table header cells.

## Evidence

- Rework notes: none found under `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php` passed with `1 test files, 1862 assertions, 0 failures`.
- Red-first: after adding the focused test, `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php` failed with `1 test files, 1863 assertions, 1 failures`; the table only reported the generic `markdown-table-source-attributes-require-raw-html` diagnostic, not the abbreviation-specific writer diagnostic.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php` passed with `1 test files, 1888 assertions, 0 failures`.
- Adjacent table-family check: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php` passed with `1 test files, 1009 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test` passed with `table geometry handoff self-test ok`.
- PHP lint passed for `lanes/pandoc/src/TableGeometry.php`, `lanes/pandoc/tests/TableGeometryTest.php`, and `lanes/pandoc/examples/wordpress-table-geometry-handoff.php`.
- JSON validation passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed with no output.

## Status Delta

- `lane-status.json` `phpPass`: `2260 -> 2261`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2665 -> 2666`.
- `mappedTableGeometryCoreCases`: `9 -> 10`.
- `tableGeometryCoreAssertions`: `155 -> 181`.
- Focused assertion delta: `+26`.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `TableGeometry` header association metadata, writer downgrade diagnostics, `WordPressBlockWriter` table attribute serialization, and focused PHP test/example coverage. Full upstream Pandoc table writer parity remains a separate upstream-runner dependency task requiring hydrated pinned upstream sources and Haskell test executables.

Root harness: not run - isolated micro-slice.
