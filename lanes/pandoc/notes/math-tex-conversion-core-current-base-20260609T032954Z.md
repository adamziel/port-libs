# Math/TeX SI Unit Alias Handoff

Slice: `pandoc-math-tex-conversion-core-current-base-20260609T032954Z`

Base accepted HEAD: `507b06f9840603abbb77bf4b360c0377f959830e`

## Source Truth

Upstream Pandoc math conversion delegates TeX math parsing to texmath. Texmath
keeps bounded siunitx command tables for unit macros such as `\km`,
`\newton`, `\joule`, and `\kelvin` in:

- `https://raw.githubusercontent.com/jgm/texmath/master/src/Text/TeXMath/Readers/TeX/Commands.hs`

This slice ports the bounded format contract into native PHP without running
Pandoc, texmath, MathJax, KaTeX, TeX engines, Cabal, Haskell runners, browser
renderers, external converters, online services, live provider tests, or
live-service provider tests.

## Behavior

- `MathTexConverter` now recognizes additional common siunitx/texmath unit
  command aliases including `\km`, `\hour`, `\newton`, `\joule`, `\mole`,
  `\kelvin`, `\pascal`, `\liter`, `\minute`, `\angstrom`, and related SI
  names.
- Existing `\per`, `\squared`, and `\cubed` handling composes with those
  aliases, so unit-only forms and quantities produce MathML text units such as
  `km/h`, `N m`, `J/mol/K`, `Pa s`, and angstrom powers.
- Accessibility handoff keeps visible unit alt text (`K`) while intent
  normalization remains lowercase (`k`), matching the existing intent
  tokenizer.
- Unknown siunitx unit commands remain rejected before handoff.
- The WordPress math handoff example now covers these SI unit aliases in an
  editable inline math source span and native MathML review packet.

## Red-First Evidence

Before the implementation:

```text
php -r 'require "tools/bootstrap.php"; $c=new PortLibs\Pandoc\MathTexConverter(); foreach(["\\si{\\km\\per\\hour}","\\SI{12}{\\newton\\metre}","\\qty{273}{\\kelvin}","\\unit{\\joule\\per\\mole}"] as $tex){try{echo "OK $tex\n".$c->texToMathMl($tex)."\n";}catch(Throwable $e){echo "ERR $tex :: ".$e->getMessage()."\n";}}'
ERR \si{\km\per\hour} :: Unsupported TeX siunitx unit \km
ERR \SI{12}{\newton\metre} :: Unsupported TeX siunitx unit \newton
ERR \qty{273}{\kelvin} :: Unsupported TeX siunitx unit \kelvin
ERR \unit{\joule\per\mole} :: Unsupported TeX siunitx unit \joule
```

Focused baseline:

```text
php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php
1 test files, 1175 assertions, 0 failures
```

## Verification

```text
php -l lanes/pandoc/src/MathTexConverter.php
No syntax errors detected in lanes/pandoc/src/MathTexConverter.php

php -l lanes/pandoc/tests/MathTexConverterTest.php
No syntax errors detected in lanes/pandoc/tests/MathTexConverterTest.php

php -l lanes/pandoc/examples/wordpress-math-tex-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-math-tex-handoff.php

php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php
1 test files, 1187 assertions, 0 failures

php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test
math tex handoff self-test ok
```

`git diff --check -- lanes/pandoc` passed after the final edits.

Root harness: not run - isolated micro-slice.

## Mapping Delta

- `phpPass`: `2233 -> 2234`
- `benchmarkDenominator.mapped`: `2642 -> 2643`
- `mappedMathTexConversionCoreCases`: `14 -> 15`
- Focused MathTexConverter assertions: `1175 -> 1187`

## Dependency Closure

No new support component is needed. This slice reuses the native PHP
`MathTexConverter`, existing source annotations, accessibility metadata,
`MarkdownReader` math spans, and `WordPressBlockWriter` handoff path. Full
upstream Pandoc/texmath runner parity remains a separate upstream-runner
dependency task requiring a hydrated pinned checkout and Haskell test
executables.

## Non-Overlap

This does not repeat accepted Math/TeX slices for TeX-token roots/fractions,
color operands, layout wrappers, arrow labels, prime canonicalization, macro
declaration capture, equation metadata, array metadata, braced or unbraced
text-mode aliases, or the existing siunitx scalar/range/list wrappers. The new
behavior is specifically common texmath/siunitx unit command alias parity.
