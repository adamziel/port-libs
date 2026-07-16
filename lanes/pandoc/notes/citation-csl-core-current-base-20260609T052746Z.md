# Citation CSL Current-Base Slice

Micro-slice: `pandoc-citation-csl-core-current-base-20260609T052746Z`

Base accepted HEAD: `003cd766d197b04fb23d7e77772dd1e8b0ccc6a3`

## Scope

Mapped bounded CSL multi-variable creator names with child labels. A single
`<names variable="founder continuator reviser collaborator">` element now
renders each populated creator role as its own labeled group when it has a
child `<label>`, while no-label multi-variable names keep the existing
first-available fallback and identical `editor translator` names keep the
combined `editortranslator` term path.

This is intentionally separate from the already accepted secondary creator
role term/plural-label slice, static authority-name slice, and inline/display
formatting slices.

## Evidence

Baseline before adding the new case:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 3747 assertions, 0 failures
```

Red-first focused run after adding the new case and before the processor
change:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
FAIL renders bounded csl multi variable names with labels for extended creator roles
Expected: '(founded by Roe; continued by Ng and Park; revised by Revision Desk; with Source Review Desk and Iqbal | 2026)'
Actual: '(founded by Roe | 2026)'
1 test files, 3756 assertions, 1 failures
```

Final focused verification:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 3759 assertions, 0 failures

php lanes/pandoc/examples/wordpress-citation-csl-extended-creator-list-handoff.php --self-test
wordpress-citation-csl-extended-creator-list-handoff self-test passed
```

Added 1 focused PHP PASS line, 12 focused assertions over the baseline, and 1
mapped native CSL support case.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `CslStyle`,
`CitationCslProcessor`, `MarkdownReader`, `WordPressBlockWriter`, and the
existing lane `TestRunner`. No Pandoc binary, citeproc, bibliography manager,
Haskell runner, office tool, archive tool, TeX/PDF engine, browser renderer,
online service, live provider test, or live-service provider test was run.

Full upstream Pandoc/citeproc runner parity remains a separate upstream-runner
dependency task requiring a hydrated Pandoc checkout and Haskell test
executables.

## Next

A non-overlapping CSL follow-up could target substitution interaction with
multi-variable names, role-specific delimiters through macros, or additional
conditionals that feed WordPress bibliography review.
