# pandoc-citation-csl-core-current-base-20260608T224656Z

## Scope

- Lane: pandoc
- Micro-slice: `pandoc-citation-csl-core-current-base-20260608T224656Z`
- Accepted base: `04adeb6b91676270f6437f5cfacf8e51c718ec44`
- Behavior cluster: bounded CSL `cs:names` label defaults for already-normalized editorial and review creator roles.

## Source Truth

The official CSL en-US locale defines default role terms for `compiler`,
`curator`, `director`, `illustrator`, `chair`, `collection-editor`,
`editorial-director`, `contributor`, `interviewer`, `reviewed-author`, and
`recipient`. The native lane already normalizes these variables and already
routes `cs:names` child labels through `CslStyle::term()`, but missing defaults
made styles fall back to raw variable identifiers such as `compiler Roe` or
`reviewed-author Reviewed`.

This slice adds those bounded locale defaults in native PHP and verifies
citation, bibliography, and WordPress handoff rendering. It does not introduce
an external citeproc runner.

## Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note existed before editing.
- Baseline focused command:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 3059 assertions, 0 failures`
- Red-first focused command after adding the new case:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 3069 assertions, 1 failures`
  - Failure reason: the new editorial-label case rendered raw role labels such
    as `compiler`, `curator`, `director`, `interviewer`,
    `reviewed-author`, and `recipient`.
- Final focused command:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - Result: `1 test files, 3074 assertions, 0 failures`
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-citation-csl-editorial-label-handoff.php --self-test`
  - Result: `wordpress-citation-csl-editorial-label-handoff self-test passed`
- PHP syntax:
  - `php -l lanes/pandoc/src/CslStyle.php`
  - `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `php -l lanes/pandoc/examples/wordpress-citation-csl-editorial-label-handoff.php`
  - Result: all reported no syntax errors.
- JSON syntax:
  - `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`
  - Result: `json ok`

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `1936 -> 1937`
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2357 -> 2358`
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` `inventory.mappedCitationCslCoreCases`: `12 -> 13`

## Dependency Closure

No new support component is needed. This reuses `CslStyle` default terms,
`CitationCslProcessor` name-label rendering, `CitationCslProcessorTest.php`,
`MarkdownReader`, and `WordPressBlockWriter`.

No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell
runner, external bibliography manager, external converter, online service, live
provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat the accepted audiovisual creator-variable mapping slice,
audiovisual creator-label slice, editor/translator label slice,
`editortranslator` term slice, participant-role names slice, redactor text
variable slice, `is-creator` conditional slices, subsequent-author slices,
near-note slice, first-reference-note-number slice, part/version/section number
label slices, or upstream-runner dependency audit slices. It is limited to
default labels for already-supported editorial/review creator variables.

## Follow-Up

A next non-overlapping Citation/CSL slice could cover a distinct conditional,
sort, or rendering gap rather than another default-term-only role batch.

## Root Harness

Not run - isolated micro-slice.
