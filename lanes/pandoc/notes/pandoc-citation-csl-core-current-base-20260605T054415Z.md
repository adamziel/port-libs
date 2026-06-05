# Pandoc Citation CSL Core Current Base

Slice: `pandoc-citation-csl-core-current-base-20260605T054415Z`

Accepted base: `cb79afe9d9b92601db4bf86db2612b0edb2ce2fb`

## Behavior

- Added bounded native CSL `cs:name-part` parsing and rendering.
- `CslStyle` now records `name-part` formatting under `cs:name` for the
  supported `family` and `given` parts only.
- `CitationCslProcessor` now applies those local name-part options when
  rendering citation and bibliography personal names:
  - `prefix` and `suffix`;
  - `text-case`;
  - `strip-periods`;
  - `quotes`.
- Literal names remain unchanged, and name-part formatting is scoped to the
  declaring `cs:name` element so a manuscript-specific bibliography branch does
  not leak uppercase family-name handling into unrelated entries.
- Updated the WordPress citation CSL handoff example so a reviewer bibliography
  branch can render formatted family/given names without invoking citeproc.

## Source Truth

- CSL 1.0.2 specification, `Name` / `Name-part` rendering:
  https://docs.citationstyles.org/en/v1.0.2/specification.html
- This slice implements a bounded native PHP contract for existing local CSL
  name rendering. It does not implement full font/display formatting,
  punctuation-in-quote locale rules, name disambiguation, et-al-subsequent
  variants, year-suffix collapsing, note-style output, or full citeproc parity.

## Evidence

- No current Pandoc rework note was present in
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Baseline before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 637 assertions, 0 failures`.
- After implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 654 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-citation-csl-handoff.php --self-test`
  - Result: `wordpress-citation-csl-handoff self-test passed`.
- Final verification:
  - `php -l lanes/pandoc/src/CslStyle.php`
  - `php -l lanes/pandoc/src/CitationCslProcessor.php`
  - `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `php -l lanes/pandoc/examples/wordpress-citation-csl-handoff.php`
  - Result: no syntax errors.
  - `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " OK\n"; }'`
  - Result: both JSON files decode.
  - `git diff --check -- lanes/pandoc`
  - Result: clean.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted CSL JSON item normalization, source-access
date/name metadata, BibTeX/BibLaTeX parsing, crossref/xdata/set/related/
translation/legal/date-range/title/publication-detail/role metadata, bracketed
citation cluster parsing, missing citation preservation, CSL locale terms,
bibliography layout affixes, sort keys, global name rendering options, direct
text/date/group/names rendering, text-case transforms, macro references, choose
conditionals, locator/page label rendering, number rendering, citation position
conditionals, quotes/strip-periods for text/label elements, DOCX/ODT/EPUB
package parsing, table geometry, ZIP/OPC package primitives, doctemplate,
YAML, archive compression, math/TeX, legacy DOC/CFB, charset helpers, PDF
handoff planning, or upstream-runner dependency audit work.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`CslStyle`, `CitationCslProcessor`, `MarkdownReader`, `MarkdownWriter`, and
`WordPressBlockWriter`. Remaining citation closure is bounded follow-up work:
punctuation-in-quote locale behavior, richer date-part/month terms,
disambiguation and year suffixes, near-note behavior, note-style output,
broader style catalogs, and full upstream runner hydration.
