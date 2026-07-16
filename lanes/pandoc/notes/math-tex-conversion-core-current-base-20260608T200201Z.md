# Math/TeX Large Operator Aliases

Slice: `pandoc-math-tex-conversion-core-current-base-20260608T200201Z`

Base accepted HEAD: `e4416a27234df3582c58620f35f477531567f5a3`

## Behavior

Native `MathTexConverter` now maps bounded texmath large-operator aliases to semantic MathML operators instead of raw command identifiers:

- `\bigcup`, `\bigcap`, `\coprod`
- `\bigvee`, `\bigwedge`, `\bigsqcup`
- `\bigoplus`, `\bigotimes`, `\bigodot`
- `\iint`, `\iiint`, `\oint`, `\oiint`, `\oiiint`

The slice reuses the existing script, `\limits`/`\nolimits`, source annotation, WordPress math span, and accessible MathML alt/intent handoff paths.

## Evidence

No `port-pandoc-*.needs-lane-rework.md` note existed before this slice.

Baseline focused test:

```text
php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php
1 test files, 825 assertions, 0 failures
```

Red-first probe:

```text
MathTexConverter::texToMathMl('\bigcup_{i=1}^{n} A_i + \iint_D f(x,y) dx dy', true)
```

Before the patch, the probe emitted raw `<mi>\bigcup</mi>` and `<mi>\iint</mi>` command identifiers.

Final focused verification:

```text
php -l lanes/pandoc/src/MathTexConverter.php
No syntax errors detected in lanes/pandoc/src/MathTexConverter.php

php -l lanes/pandoc/tests/MathTexConverterTest.php
No syntax errors detected in lanes/pandoc/tests/MathTexConverterTest.php

php -l lanes/pandoc/examples/wordpress-math-tex-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-math-tex-handoff.php

php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php
1 test files, 839 assertions, 0 failures

php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test
math tex handoff self-test ok
```

Expected mapping movement:

- `benchmarkDenominator.mapped`: `2196 -> 2197`
- `mathTexConversionCoreCases`: `14 -> 15`
- `mappedMathTexConversionCoreCases`: `14 -> 15`
- `mathTexConversionCoreAssertions`: `85 -> 99`

## Dependency Closure

No new support component is needed. This slice reuses lane-local PHP support only: `MathTexConverter`, `MarkdownReader`, `LatexWriter`, and `WordPressBlockWriter`.

No Pandoc, Cabal solver/build/test command, Haskell runner, texmath, MathJax, KaTeX, TeX/PDF engine, browser renderer, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This slice avoids the already accepted Math/TeX array width-column, bangle infix fraction, modular command, TeX comment, alignedat, and multline/multlined handoffs. It is limited to bounded large-operator alias conversion and the directly coupled WordPress math handoff example.

## Next

Choose a non-overlapping native texmath reader gap such as bounded delimiter variants, additional relation/operator class aliases, or renderer-neutral accessibility metadata. Keep external renderers and Pandoc/Haskell runners out of scope.
