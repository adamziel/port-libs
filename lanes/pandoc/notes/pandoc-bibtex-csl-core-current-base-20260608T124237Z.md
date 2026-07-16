# BibTeX/CSL Field Annotation Handoff

Slice: `pandoc-bibtex-csl-core-current-base-20260608T124237Z`
Base accepted HEAD: `b6b4875d02ae4786542ed2436bf47e7f8fe62fb2`

## Behavior

This slice maps bounded BibLaTeX non-name field annotations into native CSL handoff records:

- `field+an = {=default note; source=source note}` becomes `biblatex-field-annotations[field]`.
- `field+an:name = {=note}` uses the suffix as the annotation name.
- Name annotations such as `author+an`, `editor+an`, and custom name-field annotations remain owned by the existing name-annotation path.
- Normalized CSL items expose `biblatexFieldAnnotations` and `biblatexFieldAnnotationSummary`.
- Default bibliography output keeps field annotation summaries visible for review.
- CSL text variables `biblatex-field-annotations`, `biblatex-field-annotation-summary`, and `field-annotation-summary` render the bounded summary.
- The WordPress smoke confirms the metadata survives Markdown citation resolution and bibliography block rendering.

## Focused Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 2458 assertions, 0 failures`.
- Red check: after adding the field-annotation test, `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` failed with `1 test files, 2461 assertions, 1 failures` because `biblatex-field-annotations` was missing.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed with `1 test files, 2475 assertions, 0 failures`.
- Focused delta: `+1` PHP PASS case and `+17` focused assertions in `CitationCslProcessorTest.php`.
- Example smoke: `php lanes/pandoc/examples/wordpress-bibtex-csl-field-annotations-handoff.php --self-test` passed.

## Dependency Closure

No new support component is needed. This reuses the native PHP `BibtexCslParser`, `CitationCslProcessor`, `MarkdownReader`, and `WordPressBlockWriter` paths. No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell runner, external bibliography manager, online service, live provider test, or live-service provider test was executed.

## Next Task

Choose a non-overlapping bounded BibTeX/CSL follow-up such as additional BibLaTeX entry metadata, CSL style conditionals, or bibliography review variables. Keep the lane native PHP only and do not run Pandoc, citeproc, BibTeX, Biber, Cabal/Haskell runners, external bibliography managers, online services, live provider tests, or live-service provider tests.

## Non-Overlap

This does not repeat the accepted BibTeX/CSL entry options, name annotations, gender, thesis type, date addenda, event-place list, URL label, custom user/list/name fields, or article-number/eid slices. The bounded behavior is specific to non-name BibLaTeX `field+an` annotation metadata and CSL/WordPress review handoff.
