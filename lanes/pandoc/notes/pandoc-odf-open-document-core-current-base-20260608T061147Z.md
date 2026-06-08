# Pandoc ODF OpenDocument Core Slice

## Scope

Extended native `OdfReader` form-control handoff for option-bearing OpenDocument controls:

- parses `form:option` and `form:item` children on `form:combobox` and `form:listbox`;
- preserves labels, values, selected flags, list-source/list-source-type, bound-column, dropdown, multiple, and autocomplete metadata;
- exposes selected option labels and values through AST and `data-odf-control-*` attributes;
- counts total and selected form-control options in the import report;
- updates the WordPress ODF smoke with a selected combobox.

## Source Truth

No hydrated local Pandoc checkout exists at `/home/claude/port-libs/.upstream-cache/pandoc`, matching the lane status. This maps the existing ODF/OpenDocument support row for native ODT content/forms XML handoff. It does not run Pandoc, Cabal/Haskell runners, Word, LibreOffice, zip/unzip, external converters, online services, live provider tests, or live-service provider tests.

## Evidence

- No `port-pandoc-*.needs-lane-rework.md` note existed for this slice.
- Red-first `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` failed as expected: listbox controls fell back to the control name instead of selected option labels.
- Final focused `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` passed with `1 test files, 1818 assertions, 0 failures`.
- WordPress smoke `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test` passed with `odf open document handoff self-test ok`.
- PHP lint passed for changed PHP files.
- Lane JSON validation passed.
- `git diff --check -- lanes/pandoc` passed.
- Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1548 -> 1549`.
- `benchmarkDenominator.mapped`: `1969 -> 1970`.
- `odfOpenDocumentCoreCases`: `12 -> 13`.
- `mappedOdfOpenDocumentCoreCases`: `12 -> 13`.
- `odfOpenDocumentCoreAssertions`: `276 -> 333`.
- Focused ODF coverage: `+1` PHP PASS case and `+57` assertions.

## Dependency Closure

No new support component is needed for this slice. It reuses the existing native `OdfReader`, `ZipPackage`, AST, `MarkdownWriter`, `WordPressBlockWriter`, focused `OdfReaderTest.php`, and the lane-local WordPress ODF handoff example.

## Non-Overlap

This avoids accepted ODF text:tab, heading auto/source IDs, bookmark/reference marks, sequence fields, drop-down text fields, conditional/hidden text fields, basic form submission metadata, charts, OLE, table/list/media, and related non-ODF support slices. It owns only child option metadata for OpenDocument form controls and import-report option counters.
