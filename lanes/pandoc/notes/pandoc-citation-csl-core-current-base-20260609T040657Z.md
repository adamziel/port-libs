# Pandoc Citation/CSL Note Bibliography Handoff

Slice: `pandoc-citation-csl-core-current-base-20260609T040657Z`
Base accepted HEAD: `39b1c5d5b6751a4cd8edd906dabeef64d6d0fc2e`
Lane: `pandoc`

## Scope

- Implemented one bounded Citation/CSL support cluster for note-style bibliography rendering of `first-reference-note-number`.
- Reused existing note-position annotation from processed citation nodes, collected canonical first-note numbers from the processed document, and carried them into appended bibliography item rendering.
- Updated `first-reference-note-number` variable presence and rendering so bibliography layouts can resolve the variable from item context while citation layouts still prefer direct citation context.
- Added a WordPress handoff smoke showing footnote citations and appended bibliography entries that preserve first-note ordinal/raw values.

Source truth: CSL note styles expose `first-reference-note-number` as a rendering variable for note-based citation state; this bounded PHP handoff ports the format contract into appended bibliography output without invoking external cite processors.

## Evidence

Rework notes checked first:

- No current `port-pandoc-*.needs-lane-rework.md` note was present for this lane in `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.

Baseline focused test before the patch:

- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
- Result: `1 test files, 3614 assertions, 0 failures`

Red probe after adding the focused test:

- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
- Result: `1 test files, 3624 assertions, 1 failures`
- The output confirmed citation-side `first-reference-note-number` rendering was already present and showed appended bibliography entries still omitted first-note output. The first failing assertion in that probe was a citation locator expectation, which was corrected to match the existing renderer before implementing the bibliography-context fix.

Final focused tests after the patch:

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- Result: no syntax errors detected
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- Result: no syntax errors detected
- `php -l lanes/pandoc/examples/wordpress-citation-csl-note-bibliography-handoff.php`
- Result: no syntax errors detected
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
- Result: `1 test files, 3627 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-citation-csl-note-bibliography-handoff.php --self-test`
- Result: `wordpress-citation-csl-note-bibliography-handoff self-test passed`

JSON validation:

- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
- Result: `lane-status json ok`
- `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "manifest json ok\n";'`
- Result: `manifest json ok`

Diff hygiene:

- `git diff --check -- lanes/pandoc`
- Result: clean, no output

Root harness:

- not run - isolated micro-slice

## Status Delta

- `lane-status.json` `phpPass`: `2275 -> 2276`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2677 -> 2678`
- `UPSTREAM_TEST_MANIFEST.json` `inventory.mappedCitationCslCoreCases`: `12 -> 13`
- Focused Citation/CSL assertions: `3614 -> 3627`

## Non-Overlap

This slice does not repeat CSL number variables such as `printing-number`, `supplement-number`, `part`, `version`, or `section`; count-label pluralization; source variables; source/date sort keys; authority creator variables; BibTeX/BibLaTeX metadata mapping; or citation-side first-reference note rendering.

The new behavior is specifically appended bibliography rendering for note-style `first-reference-note-number` values derived from the already processed document.

## Dependency Closure

No new support component is needed. The implementation reuses native PHP `CitationCslProcessor`, existing note-position annotation, `CslStyle` rendering metadata, `MarkdownReader` footnote parsing, `WordPressBlockWriter` bibliography output, focused `CitationCslProcessorTest.php` coverage, and the WordPress note bibliography example.

Full upstream Pandoc/citeproc runner parity remains a separate upstream-runner dependency task requiring a hydrated Pandoc checkout and Haskell test executables.

No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, zip/unzip, external converter, renderer, online service, live provider test, or live-service provider test was executed.

## Follow-Up

Good non-overlapping Citation/CSL follow-ups are remaining note-style bibliography state, locator edge formatting in note styles, or locale-dependent term forms that can be covered with focused native PHP tests and WordPress handoff examples.
