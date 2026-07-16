# ODF OpenDocument Settings XML Handoff

Slice: `pandoc-odf-open-document-core-current-base-20260608T211028Z`
Base accepted HEAD: `26bbd2b7e4199c593e970e19e2909436056056d0`

## Behavior

- Added native `settings.xml` parsing to `OdfReader` for ODF packages with `office:document-settings`.
- Preserves direct `config:config-item-set` records, typed `config:config-item` values, indexed and named `config:config-item-map-*` entries, and aggregate item/map-entry counts.
- Exposes the parsed settings through the returned package result, document attributes, and `importReport['settings']`.
- Keeps `settings.xml` out of the media byte handoff.

## Source Truth

- This ports the bounded ODF package contract for `settings.xml` metadata handoff under the existing OpenDocument reader support-library lane.
- No upstream Pandoc runner checkout was present for this worker. The slice relies on existing pinned ODF package source notes and ODF package fixture semantics.
- Non-overlap: this does not repeat ODF text:tab normalization, heading anchors, paragraph blockquote mapping, field handoff, conditional/hidden text, subtotal rules, table style semantics, or any external office conversion path.

## Evidence

- Rework notes: no `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md` files existed for this lane.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` passed with `1 test files, 2326 assertions, 0 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` passed with `1 test files, 2353 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test` passed with `odf open document handoff self-test ok`.
- Syntax checks passed for `lanes/pandoc/src/OdfReader.php`, `lanes/pandoc/tests/OdfReaderTest.php`, and `lanes/pandoc/examples/wordpress-odf-open-document-handoff.php`.
- JSON validation passed for `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` and `lanes/pandoc/lane-status.json`.
- Whitespace check passed: `git diff --check -- lanes/pandoc`.

## Dependency Closure

No new support component is needed. This reuses `ZipPackage`, native DOM XML parsing, and existing `OdfReader` package/import-report plumbing. No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external converter, online service, live provider test, or live-service provider test was executed.

## Next

A useful follow-up would be a non-overlapping ODF package/content gap such as `draw:layer` metadata, script/event metadata, additional data-pilot metadata, or bounded consumers for parsed settings metadata.
