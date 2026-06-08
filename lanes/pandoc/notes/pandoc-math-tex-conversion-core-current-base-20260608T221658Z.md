# Math/TeX Buildrel Relation Slice

Slice: `pandoc-math-tex-conversion-core-current-base-20260608T221658Z`
Base accepted HEAD: `238c756134d68ede9072631361599c436a2f8d32`
Date: 2026-06-08 UTC

## Source Truth

This slice targets a bounded plain-TeX relation-placement gap in the native
Math/TeX support library: `\buildrel <above> \over <relation>` should behave
like a relation mover, not like an infix fraction. The local isolated worktree
does not contain a hydrated Pandoc or texmath checkout, so the source truth is
the lane's accepted texmath-style Math/TeX contract, the existing native
`\overset`/`\stackrel` and infix `\over` behavior, and the red-first PHP probe
that exposed the current fallback.

No Pandoc, texmath executable, MathJax, KaTeX, TeX/PDF engine, Cabal solver,
Cabal build/test command, Haskell runner, external converter, online service,
live provider test, or live-service provider test was executed.

## Implementation

- Added native `\buildrel` parsing before the infix-fraction reader sees the
  required `\over` token.
- Reused `parseRequiredScriptedAtomOrGroup()` for bounded above labels and
  relation bases, so braced labels, operator-name labels, relation commands,
  and normal outer scripts keep the existing MathTexConverter script semantics.
- Kept malformed forms fail-closed: missing above content, empty above groups,
  missing `\over`, wrong infix command, missing base relation, and base script
  markers without a base all throw without invoking external TeX machinery.
- Updated the WordPress Math/TeX handoff smoke so reviewer-editable source TeX
  is preserved while native MathML emits semantic `<mover>` relations.

## Verification

Focused baseline before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 912 assertions, 0 failures
```

Red-first probe before implementation:

```text
php -r 'require "tools/bootstrap.php"; $c = new PortLibs\Pandoc\MathTexConverter(); echo $c->texToMathMl("\\buildrel{\\text{def}}\\over=", true), PHP_EOL;'
<math xmlns="http://www.w3.org/1998/Math/MathML" display="block"><semantics><mfrac><mrow><mi>\buildrel</mi><mtext>def</mtext></mrow><mo>=</mo></mfrac><annotation encoding="application/x-tex">\buildrel{\text{def}}\over=</annotation></semantics></math>
```

Final focused verification:

```text
php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 926 assertions, 0 failures

php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test
math tex handoff self-test ok
```

## Dependency Closure

No new support component is needed. This reuses native PHP MathTexConverter
parsing, MathML source annotations, accessibility alt/intent metadata,
MarkdownReader math spans, and WordPressBlockWriter handoff. Full upstream
Pandoc/texmath runner parity remains out of scope until a hydrated upstream
checkout and an explicitly authorized non-mutating runner plan are available.

## Non-Overlap

This does not repeat accepted Math/TeX work for alignedat, multline/multlined,
starred matrix aliases, array width/preamble hooks, bangle infix fractions,
modular commands, TeX comments, hyperref wrappers, siunitx, mathchoice,
prescript, sideset, large/operator/relation aliases, overbracket/underbracket,
or color/phantom/cancel/math-variant wrappers. The new mapped case is only
bounded plain-TeX `\buildrel ... \over ...` relation placement.

## Follow-Up

A non-overlapping follow-up can handle another bounded texmath reader gap such
as relation placement variants, additional operator accent aliases, or guarded
environment metadata, still without invoking Pandoc, texmath, TeX engines,
MathJax, KaTeX, Cabal/Haskell runners, online services, live provider tests,
or live-service provider tests.
