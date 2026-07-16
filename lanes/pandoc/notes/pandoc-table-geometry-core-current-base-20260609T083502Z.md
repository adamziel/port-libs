# Pandoc Table Geometry Caption-Side Review

Slice: `pandoc-table-geometry-core-current-base-20260609T083502Z`
Base accepted HEAD: `436db66ac9717cbf75ff2ec29905ae0ddef22b3a`

## Source Truth

Pandoc table conversion has to preserve caption intent and table writer limitations without silently converting unsupported placement semantics. HTML imports can carry CSS `caption-side` values beyond the top/bottom positions that the current native table handoff can safely map across Markdown, AsciiDoc, LaTeX, and WordPress. This slice keeps those source values visible as review metadata while preserving the existing safe WordPress after-table caption fallback.

## Implementation

- `TableGeometry` now records `captionSideSupported`, `captionSideReviewRequired`, and `captionPlacementFallback` for non-top/bottom caption-side values.
- Markdown, AsciiDoc, and LaTeX downgrade diagnostics now include explicit caption-side review records with the original side and fallback placement.
- The HTML-reader handoff keeps `caption-side: left` source attributes in review packets and WordPress output while sanitizing unsafe event attributes.
- The WordPress table geometry example now exercises a side-caption import and confirms the sanitized after-table figcaption fallback.

## Focused Evidence

- Red-first: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php` failed before implementation with `2 test files, 3292 assertions, 2 failures` on missing `captionSideSupported` metadata.
- Final focused table verification: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php` passed with `2 test files, 3335 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test` passed with `table geometry handoff self-test ok`.
- PHP lint passed for changed PHP files: `TableGeometry.php`, `TableGeometryTest.php`, `TableGeometryReaderHandoffTest.php`, and `wordpress-table-geometry-handoff.php`.
- JSON validation passed for `UPSTREAM_TEST_MANIFEST.json` and `lane-status.json`.
- `git diff --check -- lanes/pandoc` passed with no output.

## Status Delta

- `lane-status.json` `phpPass`: `2530 -> 2532`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2898 -> 2899`.
- `mappedTableGeometryCoreCases`: `9 -> 10`.
- `tableGeometryCoreAssertions`: `155 -> 204`.
- Added `mappedTableGeometrySideCaptionReviewCases: 1`.
- Added `tableGeometrySideCaptionReviewAssertions: 49`.

## Dependency Closure

No new native support component is needed. This slice reuses `MarkdownReader` HTML caption-source extraction, `TableGeometry` review packets and writer downgrade diagnostics, and `WordPressBlockWriter` caption output. Pandoc, Cabal/Haskell runners, Word, LibreOffice, zip/unzip, external template engines, TeX/PDF engines, browser renderers, external converters, online services, live provider tests, and live-service provider tests were not executed.

## Non-Overlap

This slice avoids the already mapped table span, alignment, colgroup, row-head, row/section boundary, source-attribute, summary, top/bottom caption placement, short-caption, decimal-alignment, localization, and presentation-metadata cases. It owns only unsupported non-top/bottom `caption-side` review metadata plus the existing WordPress after-table fallback behavior.

Root harness: not run - isolated micro-slice.
