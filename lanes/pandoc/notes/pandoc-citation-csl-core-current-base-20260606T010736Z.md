# Pandoc Citation CSL Core Current Base

Slice: `pandoc-citation-csl-core-current-base-20260606T010736Z`

Accepted base: `5ede50989da7cee0d4a9a04198c44e074eacbb0b`

## Behavior

- Added bounded native CSL `cs:choose` `is-numeric` condition support.
- `CslStyle` now preserves `is-numeric` branch metadata in citation and
  bibliography layouts, alongside the existing `variable`, `type`, and
  `position` predicates.
- `CitationCslProcessor` now evaluates `is-numeric` variables through the
  existing bounded CSL variable renderer and number recognizer, so locator,
  number, and page variables can route to numeric or non-numeric branches.
- Added a WordPress citation handoff example showing numeric locators and
  issue ranges using numeric branches while alphanumeric locators stay in the
  fallback branch.

## Source Truth

- CSL 1.0.2 conditional rendering supports `is-numeric` predicates in
  `cs:if` / `cs:else-if` branches.
- This slice implements only the bounded native condition contract. It does not
  implement full citeproc disambiguation, note-style citation output,
  citation-number sequencing, page-range collapsing, `is-uncertain-date`, or
  full locale/style parity.

## Evidence

- No current Pandoc rework note was present in
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Baseline before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 1436 assertions, 0 failures`.
- After implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 1447 assertions, 0 failures`.
  - Focused delta: `+1` PASS case and `+11` assertions.
  - `php lanes/pandoc/examples/wordpress-citation-csl-is-numeric-handoff.php --self-test`
  - Result: `wordpress-citation-csl-is-numeric-handoff self-test passed`.
  - `php -l lanes/pandoc/src/CslStyle.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/src/CitationCslProcessor.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-citation-csl-is-numeric-handoff.php`
  - Result: no syntax errors.
  - `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true); if (json_last_error()) { fwrite(STDERR, json_last_error_msg() . PHP_EOL); exit(1); } echo "lane-status json ok\n";'`
  - Result: `lane-status json ok`.
  - `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true); if (json_last_error()) { fwrite(STDERR, json_last_error_msg() . PHP_EOL); exit(1); } echo "manifest json ok\n";'`
  - Result: `manifest json ok`.
  - `git diff --check -- lanes/pandoc`
  - Result: clean.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted CSL JSON item normalization, source-access
date/name metadata, CSL locale terms, bibliography layout affixes, sort keys,
name rendering options, direct text/date/group/names rendering, macro
references, variable/type/position choose conditionals, locator/page label
rendering, CSL number element rendering, et-al rendering, subsequent-author
substitution, BibTeX/BibLaTeX parsing, crossref/xdata/set/related/translation
metadata, bracketed citation cluster parsing, missing citation preservation,
DOCX/ODT/EPUB package parsing, table geometry, ZIP/OPC package primitives,
doctemplate, YAML, archive compression, math/TeX, legacy DOC/CFB, charset
helpers, PDF handoff planning, syntax-highlighting, or upstream-runner
dependency audit work.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`CslStyle`, `CitationCslProcessor`, `MarkdownReader`, `AstNode`,
`WordPressBlockWriter`, and the bounded CSL number recognizer. Remaining
citation closure is bounded follow-up work: disambiguation, note-style output,
additional conditional predicates such as `is-uncertain-date`, citation-number
sequencing, page-range collapsing, broader locale/style coverage, and full
upstream runner hydration.

No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell
runner, external bibliography manager, online sanitizer, online service, or
live provider test was executed.
