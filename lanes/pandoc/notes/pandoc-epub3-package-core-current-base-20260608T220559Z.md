# pandoc-epub3-package-core-current-base-20260608T220559Z

Accepted base: `05f9a3a8e35fc02e991a153a7d3df79b03d4dae4`

## Behavior

This slice adds bounded native EPUB3 navigation item semantic-type provenance. `EpubReader` now preserves `epub:type` values from both the navigation label element (`a`/`span`) and the containing `li`, keeping the existing label-first primary type while falling back to list-item types when the label is untyped.

The handoff fields now include `itemTypes`, `labelTypes`, `typeSource`, and `typeSources` on parsed nav items, primary navigation target-policy items, page-break items, and general navigation target reports.

## Evidence

- Rework notes: no `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md` file existed for this lane before editing.
- Baseline focused test: `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php` passed with `1 test files, 2960 assertions, 0 failures`.
- Red-first check: the new nav semantic-source test failed before the reader change with `1 test files, 2964 assertions, 1 failures`; the first failing assertion showed the list-item-only `bodymatter` type was not propagated.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php` passed with `1 test files, 3011 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test` passed.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses native EPUB OCF/OPF/XHTML nav parsing, package reference resolution, existing ZIP package fixture construction, navigation/page-list reports, and the existing WordPress EPUB package handoff example. No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external archive/office tool, online service, live provider test, or live-service provider test was run.

## Non-Overlap

This does not overlap recent EPUB3 package work on OPF metadata, fixed-layout/rendition metadata, nav section provenance, target coverage, page-list/NCX fallback, XHTML resource scanning, CSS resource metadata, form/ping side effects, media overlays, or OCF sidecars. The covered gap is specifically nav item `epub:type` source fallback and report propagation.
