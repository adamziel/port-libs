# Pandoc BibTeX/CSL Media Type Handoff

Slice: `pandoc-bibtex-csl-core-current-base-20260608T221130Z`
Base accepted HEAD: `b5a355b21ceda7875c2975dde96ac65abe5fde9b`

## Behavior

- Added bounded native BibLaTeX media entry-type normalization in
  `BibtexCslParser`.
- `@movie` and `@video` now map to CSL `motion_picture`.
- `@music` now maps to CSL `song`.
- `@image` now maps to CSL `graphic`.
- Added focused CSL type-conditional coverage proving those normalized types
  route through `cs:choose` branches and WordPress bibliography handoff output.
- Added a WordPress smoke for BibTeX media sources without invoking Pandoc,
  citeproc, BibTeX, Biber, external bibliography managers, Cabal, Haskell
  runners, online services, live provider tests, or live-service provider
  tests.

Source truth: legacy pandoc-citeproc BibTeX mapping in
`Text.CSL.Input.Bibtex` maps `movie` and `video` to `MotionPicture`, `music`
to `Song`, and `image` to `Graphic`:
https://hackage.haskell.org/package/pandoc-citeproc-0.5/docs/src/Text-CSL-Input-Bibtex.html

## Evidence

- Rework notes: no `port-pandoc-*.needs-lane-rework.md` files existed before
  editing.
- Baseline focused test:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  -> `1 test files, 2999 assertions, 0 failures`.
- Red-first focused test after adding media type expectations:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  -> `1 test files, 3001 assertions, 1 failures` because `@movie` remained
  raw `movie`.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  -> `1 test files, 3015 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-bibtex-csl-media-type-handoff.php --self-test`
  -> `wordpress-bibtex-csl-media-type-handoff self-test passed`.
- PHP lint:
  `php -l lanes/pandoc/src/BibtexCslParser.php` -> no syntax errors;
  `php -l lanes/pandoc/tests/CitationCslProcessorTest.php` -> no syntax
  errors;
  `php -l lanes/pandoc/examples/wordpress-bibtex-csl-media-type-handoff.php`
  -> no syntax errors.
- JSON validation:
  `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`
  -> `json ok`.
- Whitespace check: `git diff --check -- lanes/pandoc` -> passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1909` -> `1910`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2331` -> `2332`.
- `mappedBibtexCslCoreCases`: `7` -> `8`.
- `bibtexCslCoreAssertions`: `121` -> `137`.
- Focused assertion delta: `+16` assertions over the accepted-base
  Citation/CSL baseline.

## Dependency Closure

No new support component is needed. This reuses the native PHP
`BibtexCslParser`, `CitationCslProcessor` CSL choose/type conditional support,
`MarkdownReader`, and `WordPressBlockWriter`. Full Pandoc/citeproc runner
parity remains gated on the upstream Haskell/Cabal runner path; no external
runner or bibliography tool was executed.

## Non-Overlap

This slice does not repeat accepted media identifier preservation, entry
subtype metadata, call numbers, article numbers, shorthand-list output,
abbreviation JSON handoff, pagination/bookpagination, event-place lists,
related/xref metadata, role mappings, or CSL creator variable coverage. It
only owns bounded BibLaTeX media entry-type normalization for CSL item types.

## Follow-Up

Possible follow-ups should stay non-overlapping: additional pandoc-citeproc
entry-type aliases, bibliography disambiguation behavior, note-style citation
behavior, or another safe BibLaTeX datamodel field not already mapped.
