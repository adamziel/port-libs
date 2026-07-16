# Math/TeX TexToken Argument Handoff

Slice: `pandoc-math-tex-conversion-core-current-base-20260609T011120Z`
Base: `09109401d59cee7a589aaf8125432abbe4aef718`

## Upstream Source Truth

Pinned texmath reader behavior treats many command arguments as a single `texToken` rather than a required braced group. The relevant source path is `Text/TeXMath/Readers/TeX.hs`: `texToken = texSymbol <|> inbraces <|> texChar`, and `root`, `binary`, `phantom`, `boxed`, and `cancel` consume those tokens for `\sqrt`, `\frac`/style fractions, `\binom`, `\overset`/`\underset`, `\phantom`, `\boxed`, and cancel commands.

## Implementation

- Added a bounded `parseRequiredTexToken()` helper in `MathTexConverter`.
- Switched `\sqrt`, `\frac`/`\dfrac`/`\tfrac`, `\binom`/`\tbinom`/`\dbinom`, `\overset`, `\underset`, `\boxed`, `\phantom`/`\hphantom`/`\vphantom`, and `\cancel`/`\bcancel`/`\xcancel` to accept either an existing braced group or one unbraced TeX token.
- Preserved existing empty-wrapper guards for boxed/phantom/cancel/above-below/binomial cases and script-marker, closing-brace, and alignment-marker rejection for malformed token positions.
- Preserved existing empty-group behavior for `\sqrt{}` and `\frac{}{...}` by allowing empty braced groups only where the existing converter already allowed them.

## Evidence

Baseline:

```bash
php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php
# 1 test files, 1036 assertions, 0 failures
```

Red probe before implementation:

```bash
php -r 'require "tools/bootstrap.php"; $c=new PortLibs\Pandoc\MathTexConverter(); foreach(["\\sqrt x_i","\\sqrt[3]x_i","\\frac12","\\dfrac a b","\\binom n k","\\boxed x_i","\\phantom x_i","\\hphantom x_i","\\vphantom x_i","\\cancel x_i","\\bcancel y","\\xcancel z","\\overset\\alpha x","\\underset 0 x"] as $tex){try{$c->texToMathMl($tex,true); echo "OK $tex\n";}catch(Throwable $e){echo "ERR $tex :: ".$e->getMessage()."\n";}}'
# All listed unbraced-token cases failed with Expected TeX group/content diagnostics.
```

Final focused test:

```bash
php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php
# 1 test files, 1059 assertions, 0 failures
```

Example smoke:

```bash
php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test
# math tex handoff self-test ok
```

Additional required checks were run after the final dirty tree was assembled:

```bash
php -l lanes/pandoc/src/MathTexConverter.php
php -l lanes/pandoc/tests/MathTexConverterTest.php
php -l lanes/pandoc/examples/wordpress-math-tex-handoff.php
git diff --check -- lanes/pandoc
```

## Non-Overlap

This does not repeat recent Math/TeX slices for unbraced `\operatorname`, TeX comments, modular commands, `\bangle`, array width columns, array preamble hooks, starred matrix environments, large operator aliases, or explicit limits/displaylimits. This slice is limited to texmath-style one-token command arguments.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP `MathTexConverter`, existing Markdown math span handoff, WordPress block writer output, and MathML semantics annotations. It did not run Pandoc, texmath, MathJax, KaTeX, TeX/PDF engines, Cabal, Haskell runners, external converters, online services, live provider tests, or live-service provider tests.

## Follow-Up

A next non-overlapping Math/TeX slice could cover bounded color command texToken content, additional one-token accent/wrapper parity, or MathML handoff metadata that is not already covered by accepted math slices.
