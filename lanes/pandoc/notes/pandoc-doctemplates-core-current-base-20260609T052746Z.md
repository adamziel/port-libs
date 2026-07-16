# Pandoc Doctemplates Current-Base Slice 2026-06-09T052746Z

## Source Truth

- Upstream doctemplates 0.11.0.1 `Parser.hs` parses bare partials through `pBarePartial`, calls `handleNesting True`, and consumes the following source line ending when the partial directive begins a standalone line.
- Upstream `test/partials.test` exercises the bounded case with:
  - a standalone indented `$boilerplate()$` partial;
  - `boilerplate.txt` ending with two newlines;
  - expected output containing `HERE\n---`, not `HERE\n\n---`.

Primary source references:

- `https://github.com/jgm/doctemplates/blob/0.11.0.1/src/Text/DocTemplates/Parser.hs`
- `https://github.com/jgm/doctemplates/blob/0.11.0.1/test/partials.test`

## Implementation

- `DocTemplate` now drops the template source line ending after a standalone bare partial only when the rendered partial already ends in a line ending after the existing one-final-newline partial stripping.
- This preserves ordinary one-newline partial loops, where the template newline still separates loop rows, while fixing newline-terminated partials that intentionally retain one final newline.
- The WordPress review packet smoke now expects the retained newline from `components/trailing-note.html` to flow directly into `</header>` without an extra blank line.

## Evidence

Baseline focused command before edits:

```text
php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php
1 test files, 1139 assertions, 0 failures
```

Red-first focused command after adding the fixture assertion:

```text
php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php
1 test files, 1140 assertions, 1 failures
```

The failure showed the old extra blank line:

```text
Actual: "---
  BOILERPLATE
  HERE

---"
```

Final focused command:

```text
php tools/run-tests.php lanes/pandoc/tests/DocTemplateTest.php
1 test files, 1140 assertions, 0 failures
```

Additional fixture spot-check:

```text
partials fixture parity: pass
```

## Status Delta

- `lane-status.json` `phpPass`: `2375 -> 2376`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2769 -> 2770`.
- Added inventory rows:
  - `mappedDoctemplateStandalonePartialLineEndingCases`: `1`
  - `doctemplateStandalonePartialLineEndingAssertions`: `1`

## Non-Overlap

This slice does not repeat accepted doctemplate coverage for comments, delimiter trimming, pipes, applied partial separator ordering, partial recursion, missing partial diagnostics, path partials, extension-qualified/default partial fallbacks, empty standalone partial swallowing, CR-only nesting, digit-leading child metadata keys, default Pandoc templates, or explicit nested breakable-space wrapping. It only covers standalone bare partial source line-ending suppression when the included partial still ends in a line ending.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP doctemplate parser/renderer, resource-map partial resolver, and Unicode/display-column nesting helpers. No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, ZipArchive, tar, gzip, lz4, TeX/PDF engine, Typst, browser renderer, external converter, online service, live provider test, or live-service provider test was executed.

## Follow-Up

Remaining non-overlapping doctemplate fixture gaps observed during local native fixture comparison are nested loop indentation in `nest.test` and block-pipe trailing-output parity in `pad.test`.
