# Pandoc Table Geometry WordPress Colgroup Provenance

Slice: `pandoc-table-geometry-core-current-base-20260608T025048Z`

Base accepted HEAD: `02ca21f0a770f96178de4e85f83f87d2bf977c2c`

## Source Truth

This slice stays within the existing native PHP Pandoc support-library contract for table geometry. It reuses the accepted HTML table reader, `TableGeometry` column-source metadata, and WordPress table writer behavior already mapped from upstream table fixtures. No Pandoc executable, Cabal/Haskell runner, external writer, browser renderer, online service, live provider test, or live-service provider test was executed.

No rework note existed for `port-pandoc` before this slice.

## Behavior Added

`WordPressBlockWriter` now preserves safe source `<colgroup>` and `<col>` provenance when every visual output column has complete source-column metadata from the table geometry packet.

The writer now:

- emits one normalized `<col>` per visual output column while grouping consecutive columns by original source colgroup;
- keeps normalized geometry widths authoritative instead of replaying raw `width`, `align`, `span`, or `style` source attributes;
- preserves safe source provenance attributes such as `id`, `class`, `data-*`, `aria-*`, `title`, and `valign`;
- strips unsafe event handler attributes such as `onclick`;
- falls back to the previous synthetic width-only colgroup behavior when source provenance is incomplete.

The focused WordPress handoff test covers a source table with a safe attributed colgroup, a spanning col, an explicit status col, normalized width output, right alignment inherited from source col style, vertical alignment handoff, and unsafe event stripping.

## Status Delta

- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1956 -> 1957`.
- Table geometry mapped core cases: `8 -> 9`.
- Table geometry core assertions: `143 -> 155`.
- `lane-status.json` phpPass: `1537 -> 1538`.
- Focused reader handoff delta: `+1` PHP PASS case and `+12` assertions.

## Verification

Focused baseline before adding the new case:

```text
php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php
1 test files, 364 assertions, 0 failures
```

Initial focused probe after adding the new case:

```text
php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php
1 test files, 373 assertions, 1 failures
```

The probe failed because the expected body-cell `valign` text did not match the existing normalized `vertical-align` style output; the final expectation was corrected without changing the table geometry vertical-align behavior.

Final focused verification:

```text
php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php
1 test files, 376 assertions, 0 failures

php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php
2 test files, 1777 assertions, 0 failures

php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test
passed
```

Syntax and diff checks:

```text
php -l lanes/pandoc/src/WordPressBlockWriter.php
php -l lanes/pandoc/tests/TableGeometryReaderHandoffTest.php
php -l lanes/pandoc/examples/wordpress-table-geometry-handoff.php
No syntax errors detected

git diff --check -- lanes/pandoc
passed
```

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The implementation uses the existing native HTML table reader, `TableGeometry` column source packet, WordPress block writer, focused table-geometry PHP tests, and the lane-local WordPress table geometry handoff example.

## Non-Overlap

This does not duplicate existing table geometry span layout, row-head column output, section-boundary rowspans, multiple body writer output, global row coordinates, header abbreviation audit, block-cell handoff, footer-section writer diagnostics, declared-column overflow diagnostics, or existing source metadata packet extraction. The new behavior is the WordPress writer handoff of safe `<colgroup>`/`<col>` provenance that was already present in the geometry packet but previously discarded during output.

## Follow-Up

Potential next table-geometry gaps are DOCX/ODT source column provenance, additional Markdown/AsciiDoc downgrade diagnostics for source colgroups, or accessibility review metadata that connects source column provenance to header-association packets.
