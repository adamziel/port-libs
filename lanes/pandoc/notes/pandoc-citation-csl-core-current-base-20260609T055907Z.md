# Pandoc Citation/CSL Core Current-Base Handoff

Slice: `pandoc-citation-csl-core-current-base-20260609T055907Z`

Base: `7ed2f69b027c00a8c9af1b63d2dfcdebbab97ac6`

## Scope

Implemented one bounded Citation/CSL behavior cluster: page-labelled citation
locators now reuse the existing CSL `page-range-format` formatter, while
section and other non-page locators stay on the existing en-dash-only range
delimiter path.

This updates:

- `CitationCslProcessor::renderVariableValue()` for `locator`.
- The existing page-range WordPress handoff smoke expectation.
- Focused Citation/CSL tests for page locators versus section locators.

Source truth:

- CSL 1.0.2 page-range-format defines expanded, minimal, minimal-two, and
  Chicago range forms:
  <https://docs.citationstyles.org/en/v1.0.2/specification.html#page-ranges>
- CSL 1.0.2 range delimiter behavior keeps locator hyphen replacement as a
  locator behavior:
  <https://docs.citationstyles.org/en/v1.0.2/specification.html#range-delimiters>
- CSL development discussion records the intended distinction: page-labelled
  locators can use page-range formatting, while later section-locator
  discussion notes section ranges are not truncated by that option:
  <https://discourse.citationstyles.org/t/page-range-format-for-locators/1178>
  and
  <https://discourse.citationstyles.org/t/section-range-truncation/1723>

## Evidence

- Baseline focused test before adding this slice:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 3807 assertions, 0 failures`.
- Red-first focused test after adding the new test but before implementation:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  failed with `1 test files, 3809 assertions, 1 failures`; the page locator
  rendered `321–328` instead of the expected `321–28`.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 3812 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-citation-csl-page-range-format-handoff.php --self-test`
  passed.

No Pandoc, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, external
template engine, TeX/PDF engine, browser renderer, online service, live
provider, or live-service provider command was run.

## Non-Overlap

This slice does not repeat accepted CSL page-variable range formatting,
locator label rendering, uncommon locator vocabulary, contextual locator
labels, sort-key name-list overrides, term forms, note sorting, numeric
sorting, source/date sorting, BibTeX/BibLaTeX metadata mapping, OPC/XML
relationship work, or PDF engine handoff work. It owns only the page-labelled
citation locator page-range-format handoff.

## Dependency Closure

No new support component is required. The patch reuses the existing native PHP
CSL style parser, citation locator inference, page-range formatter, Markdown
reader, WordPress block writer, and focused TestRunner path. Full upstream
Pandoc/citeproc runner parity remains a separate upstream-runner dependency
task.

## Next Task

Continue with non-overlapping Citation/CSL gaps such as additional locator
edge cases, substitution behavior, or macro/date/name rendering gaps only
where focused native PHP tests can show a real conversion contract improvement.
