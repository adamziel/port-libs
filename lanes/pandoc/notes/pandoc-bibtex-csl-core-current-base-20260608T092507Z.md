# Pandoc BibTeX CSL Core Current Base 2026-06-08 09:25 UTC

Slice: `pandoc-bibtex-csl-core-current-base-20260608T092507Z`

Accepted base: `76dc0ae478cf17b9d4471313469197e6c70ed1d9`

## Behavior

- Added bounded CSL text-variable rendering for scalar BibLaTeX original imprint metadata:
  `original-publisher`, `origpublisher`, `original-publisher-place`,
  `origlocation`, and `origaddress`.
- Added direct CSL item normalization aliases for `originalPublisher` and
  `originalPublisherPlace`, preserving the existing parsed
  `original-publisher-list` and `original-publisher-place-list` handoff.
- Added a WordPress-facing smoke example that renders a custom CSL style using
  the scalar original publisher/place variables and the existing list variables.

## Evidence

- Rework scan:
  `find /home/claude/port-libs/.tmux-team/tmp/handoff-candidates -maxdepth 1 -name 'port-pandoc-*.needs-lane-rework.md'`
  found no current pandoc rework note.
- Baseline:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 2366 assertions, 0 failures`.
- Red-first:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  failed as expected with `1 test files, 2376 assertions, 1 failures` because
  scalar `origpublisher` / `origlocation` variables rendered as empty while the
  list variables rendered.
- Final:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 2381 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-bibtex-csl-original-publisher-handoff.php --self-test`
  passed.

Focused assertion delta: `+15`.

Lane counter delta: `phpPass +1` and mapped BibTeX/CSL core case `+1`.

## Non-Overlap

This slice does not repeat prior original-date, original-language, publisher-list,
original-publisher-list, gender, reprint-title, custom-field/list/name, or PDF
engine handoffs. It only exposes already parsed original publisher/place scalar
metadata to bounded CSL style variables and direct CSL item aliases.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
BibTeX parser, bounded CSL style renderer, Markdown reader, and WordPress block
writer. No Pandoc, citeproc binary, Haskell test binary, external template
engine, Word, LibreOffice, TeX/PDF engine, online service, or live provider test
was executed.
