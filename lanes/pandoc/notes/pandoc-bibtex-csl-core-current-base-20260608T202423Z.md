# Pandoc BibTeX/CSL Named Name Annotation Suffix Handoff

Slice: `pandoc-bibtex-csl-core-current-base-20260608T202423Z`
Base: `e804d88dd32d5db061bbd8258db113c523e8f8c3`

## Implemented

- Added bounded BibLaTeX name-list annotation support for named `+an:<part>`
  fields such as `author+an:source` and `editor+an:role`.
- `BibtexCslParser` now collects both exact `field+an` and named
  `field+an:<part>` annotations for name fields, using the field suffix as the
  default annotation part while preserving per-entry overrides such as
  `2:given=...`.
- Added focused coverage for parsed CSL items, normalized processor items,
  `name-annotation-summary` CSL variable rendering, bibliography text, and
  WordPress block output.
- Added `wordpress-bibtex-csl-name-annotation-suffix-handoff.php` as the
  lane-local WordPress review smoke.

## Source Truth And Non-Overlap

BibLaTeX name annotations are part of the existing bounded BibTeX/CSL handoff
contract. This slice only owns named name-annotation suffixes and does not
repeat accepted exact `author+an`/`editor+an`, non-name `field+an` annotations,
entry options, related options, language options, gender, thesis type, date
addenda, event-place lists, URL labels, custom user/list/name fields,
article-number/eid, pagination, library call-number, author-type, keywords,
related/xref, or Citation/CSL name/date rendering slices.

No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell
runner, external bibliography manager, online service, live provider test, or
live-service provider test was executed.

## Evidence

- Rework notes:
  - `ls -1 /home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md 2>/dev/null || true`
  - Result: no current Pandoc rework notes.
- Baseline focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 2793 assertions, 0 failures`.
- Red-first probe before implementation:
  - `php -r 'require "tools/bootstrap.php"; $items = \PortLibs\Pandoc\CitationCslProcessor::bibtexItems("@book{name-suffix, author={Smith, Ada and Ng, Nia}, author+an:source={1=OCR family verified; 2:given=review desk confirmed}, title={Named Annotation}, date={2026}} "); var_export($items[0]["author"] ?? null); echo "\n";'`
  - Result: parsed author entries had no `annotations` metadata.
- Focused test after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 2810 assertions, 0 failures`.
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-bibtex-csl-name-annotation-suffix-handoff.php --self-test`
  - Result: `wordpress-bibtex-csl-name-annotation-suffix-handoff self-test passed`.
- Syntax checks:
  - `php -l lanes/pandoc/src/BibtexCslParser.php`
  - `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `php -l lanes/pandoc/examples/wordpress-bibtex-csl-name-annotation-suffix-handoff.php`
  - Result: no syntax errors detected.
- Whitespace check:
  - `git diff --check -- lanes/pandoc`
  - Result: passed.

## Status Delta

- Added `+1` focused PHP PASS case.
- Focused `CitationCslProcessorTest.php` assertions: `2793 -> 2810`.
- `lane-status.json` `phpPass`: `1802 -> 1803`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2225 -> 2226`.
- `mappedBibtexCslCoreCases`: `7 -> 8`.
- `bibtexCslCoreAssertions`: `121 -> 138`.

## Dependency Closure

No new support component is needed. The slice reuses native PHP
`BibtexCslParser`, `CitationCslProcessor`, `MarkdownReader`,
`WordPressBlockWriter`, and focused PHP tests/examples.

## Follow-Up

Next BibTeX/CSL work should stay non-overlapping: additional safe BibLaTeX
datamodel name/list aliases, bibliography-driver review variables, or CSL
variable handoff gaps not already covered by name annotations, field
annotations, entry options, language options, event-place lists, author types,
keywords, related/xref, custom fields/lists/names, pagination, article numbers,
or URL labels.
