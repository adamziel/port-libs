# Citation/CSL Current-Base Default Name-Variable Terms

Slice: `pandoc-citation-csl-core-current-base-20260609T023900Z`
Base accepted HEAD: `cff2757f3c2ce59e8912b5b48a787409562aacb3`

## Behavior

- Added bounded default CSL en-US terms for name-variable labels that were falling back to raw role names.
- Preserves blank long labels for `author`, `composer`, `container-author`, and `original-author`.
- Preserves verb labels for `composer`, `container-author`, and `original-author`, so `cs:label form="verb"` renders `composed by` or `by` instead of raw variable names.
- Keeps locale overrides authoritative; a style-local `author` term still renders custom single/multiple labels.
- Covered citation clusters, bibliography entries, `cslStyleSummary()` metadata, and WordPress block handoff.

Source truth:

- CSL locale en-US terms: https://raw.githubusercontent.com/citation-style-language/locales/master/locales-en-US.xml
- CSL specification: https://docs.citationstyles.org/en/v1.0.2/specification.html

## Verification

- Red-first focused run before implementation:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  failed with `1 test files, 3387 assertions, 1 failures` because role labels rendered raw `author`, `composer`, `container-author`, and `original-author` fallback terms.
- Final focused run:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 3397 assertions, 0 failures`.
- Added `+1` PHP PASS case; lane `phpPass` moved from `2162` to `2163`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-citation-csl-default-name-variable-terms-handoff.php --self-test`
  passed with `wordpress-citation-csl-default-name-variable-terms-handoff self-test passed`.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `CslStyle` default locale terms, `CitationCslProcessor`, `MarkdownReader`, `WordPressBlockWriter`, the focused Citation/CSL tests, and the lane-local WordPress example.

No Pandoc, citeproc, BibTeX, Biber, Cabal/Haskell runner, external bibliography manager, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted CSL name-label rendering mechanics, quote labels, name form short/count rendering, participant creator parsing, audiovisual/editorial labels, abbreviation lookup, date/number labels, or bibliography option behavior. It only maps the missing default locale terms that make existing `cs:label` rendering match CSL en-US defaults.

## Follow-Up

Future Citation/CSL work should choose a separate native gap, such as remaining locale role terms, note-style bibliography state, or cite-group disambiguation.
