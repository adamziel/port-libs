# Pandoc Citation CSL Core Current Base

Slice: `pandoc-citation-csl-core-current-base-20260605T051357Z`

Accepted base: `93b256936da5d72073f711cd6c0ee9e97576fae9`

## Behavior

- Added bounded native CSL `quotes` and `strip-periods` rendering support.
- `CslStyle` now records:
  - `quotes` and `stripPeriods` on bounded `cs:text` rendering elements;
  - `stripPeriods` on bounded `cs:label` rendering elements;
  - default `open-quote` and `close-quote` locale terms.
- `CitationCslProcessor` now strips periods, applies existing text-case
  handling, wraps localized quote terms, then applies affixes so prefix/suffix
  text remains outside the quoted value.
- Updated the WordPress citation CSL handoff example so dataset-style source
  titles can render as localized quoted bibliography titles without invoking
  citeproc.

## Source Truth

- CSL 1.0.2 specification, style behavior for rendering attributes:
  https://docs.citationstyles.org/en/v1.0.2/specification.html
- This slice implements a bounded PHP contract for existing local CSL style
  rendering. It does not implement punctuation-in-quote locale options,
  `cs:name-part` formatting, rich date-part forms/month terms,
  disambiguation, note-style output, near-note distance, display/font
  formatting, or full citeproc parity.

## Evidence

- No current Pandoc rework note was present in
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Baseline before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 601 assertions, 0 failures`.
- Red-first after adding expectations:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 603 assertions, 1 failures`; failure showed
    missing `quotes` metadata in `CslStyle`.
- After implementation:
  - `php -l lanes/pandoc/src/CslStyle.php`
  - `php -l lanes/pandoc/src/CitationCslProcessor.php`
  - `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `php -l lanes/pandoc/examples/wordpress-citation-csl-handoff.php`
  - Result: no syntax errors.
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 617 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-citation-csl-handoff.php --self-test`
  - Result: `wordpress-citation-csl-handoff self-test passed`.
  - `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " OK\n"; }'`
  - Result: both JSON files decode.
  - `git diff --check -- lanes/pandoc`
  - Result: clean.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted CSL JSON item normalization, date/name metadata,
BibTeX/BibLaTeX parsing, crossref/xdata/set/related/translation/legal/date
range/title/publication-detail/role metadata, bracketed citation cluster
parsing, missing citation preservation, CSL locale terms, bibliography layout
affixes, sort keys, name rendering options, direct text/date/group/names
rendering, text-case transforms, macro references, choose conditionals,
locator/page label rendering, number rendering, citation position conditionals,
DOCX/ODT/EPUB package parsing, table geometry, ZIP/OPC package primitives,
doctemplate, YAML, archive compression, math/TeX, legacy DOC/CFB, charset
helpers, PDF handoff planning, or upstream-runner dependency audit work.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`CslStyle`, `CitationCslProcessor`, `MarkdownReader`, `MarkdownWriter`, and
`WordPressBlockWriter`. Remaining citation closure is bounded follow-up work:
`cs:name-part` formatting, punctuation-in-quote locale options, richer
date-part forms/month terms, disambiguation, near-note position behavior,
note-style output, broader style catalogs, and full upstream runner hydration.
