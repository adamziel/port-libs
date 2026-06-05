# Pandoc Citation CSL Core Current Base

Slice: `pandoc-citation-csl-core-current-base-20260605T044339Z`

Accepted base: `65530467850a2f179b5e97d0f0d14d580fe10713`

## Behavior

- Added bounded native CSL `text-case` parsing and rendering.
- `CslStyle` now validates and records `text-case` on supported rendering
  elements used by the bounded renderer: `text`, `date`, `number`, and
  `label`.
- `CitationCslProcessor` now applies text-case before affixes for rendered
  values, terms, variables, macros, labels, dates, and numbers:
  - `lowercase`;
  - `uppercase`;
  - `capitalize-first`;
  - `capitalize-all`;
  - `sentence`;
  - English-only `title` case with bounded stop-word handling and non-English
    item language bypass.
- Updated the WordPress citation CSL handoff example so a lower-case review
  packet title renders as title case in bibliography output without invoking
  citeproc.

## Source Truth

- CSL 1.0.2 specification, `Text-case`:
  https://docs.citationstyles.org/en/v1.0.2/specification.html#text-case
- This slice implements a bounded PHP text-case contract for the existing
  local renderer. It does not implement `cs:name-part` text-case, display or
  font formatting attributes, quote localization, strip-periods,
  disambiguation, note-style output, or full citeproc parity.

## Evidence

- No current Pandoc rework note was present in
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Baseline before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 564 assertions, 0 failures`.
- Red-first after adding expectations:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 566 assertions, 1 failures`; failure showed
    missing `textCase` metadata in `CslStyle`.
- After implementation:
  - `php -l lanes/pandoc/src/CslStyle.php`
  - `php -l lanes/pandoc/src/CitationCslProcessor.php`
  - `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `php -l lanes/pandoc/examples/wordpress-citation-csl-handoff.php`
  - Result: no syntax errors.
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 578 assertions, 0 failures`.
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php | rg -c '^PASS '`
  - Result: `28`.
  - `php lanes/pandoc/examples/wordpress-citation-csl-handoff.php --self-test`
  - Result: `wordpress-citation-csl-handoff self-test passed`.
  - `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " OK\n"; }'`
  - Result: both JSON files decode.
  - `git diff --check -- lanes/pandoc`
  - Result: clean.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted CSL JSON item normalization, source-access
date/name metadata, CSL locale terms, bibliography layout affixes, sort keys,
name rendering options, direct text/date/group/names rendering, macro
references, variable/type/position choose conditionals, locator/page label
rendering, number rendering, BibTeX/BibLaTeX parsing, crossref/xdata/set/
related/translation metadata, bracketed citation cluster parsing, missing
citation preservation, DOCX/ODT/EPUB package parsing, table geometry, ZIP/OPC
package primitives, doctemplate, YAML, archive compression, math/TeX, legacy
DOC/CFB, charset helpers, PDF handoff planning, or upstream-runner dependency
audit work.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`CslStyle`, `CitationCslProcessor`, `MarkdownReader`, `MarkdownWriter`, and
`WordPressBlockWriter`. Remaining citation closure is bounded follow-up work:
`cs:name-part` formatting, richer date forms, disambiguation, near-note
position behavior, note-style output, broader style catalogs, and full
upstream runner hydration.
