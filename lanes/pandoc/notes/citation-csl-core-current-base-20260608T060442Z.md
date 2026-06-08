# Citation/CSL Current-Base Rendering Formatting Handoff

Slice: `pandoc-citation-csl-core-current-base-20260608T060442Z`
Base accepted HEAD: `cfec77028507d7bdc4213fc9124ee422079c0937`

## Behavior

- Added bounded CSL rendering-format metadata parsing for `font-style`, `font-variant`, `font-weight`, `text-decoration`, and `vertical-align` on rendering elements.
- Preserved that metadata on bibliography `display` parts produced by `CitationCslProcessor`.
- Rendered WordPress `csl-entry` display parts with stable CSL formatting classes and safe inline styles while keeping plain bibliography and Markdown output text-only.
- Invalid bounded formatting values now raise `InvalidArgumentException` during native CSL style parsing.

## Verification

- Baseline focused run before implementation:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 2281 assertions, 0 failures`.
- Final focused run:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 2296 assertions, 0 failures`.
- Added `+1` PHP PASS case and `+15` focused assertions.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-citation-csl-display-handoff.php --self-test`
  passed with `wordpress-citation-csl-display-handoff self-test passed`.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `CslStyle`, `CitationCslProcessor`, `WordPressBlockWriter`, the focused CSL tests, and the existing WordPress CSL display example.

No Pandoc, citeproc, BibTeX, Biber, Cabal/Haskell runner, external bibliography manager, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted Citation/CSL date-part precision, participant-name, institution short-parts, et-al, subsequent-author, conditionals, numbering, punctuation-in-quote, or display-part layout behavior. It only adds bounded rendering-format metadata handoff for existing bibliography display parts.

## Follow-Up

Next Citation/CSL work should choose a separate native gap, such as name-part rich formatting or additional locale-option propagation, and keep external citation processors out of scope.
