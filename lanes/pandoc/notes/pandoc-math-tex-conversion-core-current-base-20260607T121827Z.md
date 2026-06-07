# Pandoc Math/TeX current-base hyperref wrapper slice

Session: `port-dev-pandoc-math-tex-20260607T121827Z`
Micro-slice: `pandoc-math-tex-conversion-core-current-base-20260607T121827Z`
Accepted base: `8e9f32e829402726c458eef0af30498c4e6ff1de`

## Behavior

Added bounded native Math/TeX support for texmath-style `\hyperref[optional-target]{formula}` wrappers.

The optional bracket target is consumed and ignored for rendered math, and the required braced formula content is parsed through the normal MathML path. Source TeX annotations still retain the original `\hyperref[...]` text, equation references inside the wrapper still resolve through the existing label map, and accessibility alt text/intent still derive from the rendered formula rather than the hyperlink target syntax.

Source truth: upstream texmath's TeX reader includes `hyperref` in `expr1`; its parser consumes `\hyperref`, ignores an optional bracket argument, and returns the braced expression content. Reference: https://raw.githubusercontent.com/jgm/texmath/master/src/Text/TeXMath/Readers/TeX.hs

## Evidence

Baseline focused test before implementation:

`php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`

Result: `1 test files, 603 assertions, 0 failures`.

Red-first probe before implementation:

`\hyperref[eq:review]{p_i + m_i}` rendered literal `\hyperref` and optional label tokens in MathML.

Final focused test:

`php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`

Result: `1 test files, 615 assertions, 0 failures`.

Example smoke:

`php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`

Result: `math tex handoff self-test ok`.

## Status Delta

- Added 1 mapped native Math/TeX support case.
- Added 12 focused MathTexConverter assertions.
- Updated lane `phpPass` from 1495 to 1496.
- Updated `benchmarkDenominator.mapped` from 1915 to 1916.
- Updated Math/TeX inventory from 14 mapped cases / 85 assertions to 15 mapped cases / 97 assertions.

## Dependency Closure

No new support component is needed. The slice reuses native `MathTexConverter` parsing, balanced optional-bracket readers, source TeX annotations, equation-reference handoff, accessibility metadata, and the existing WordPress Math/TeX example.

Pandoc, texmath, MathJax, KaTeX, TeX/PDF engines, Cabal/Haskell runners, external converters, online services, live provider tests, and live-service provider tests were not executed.

## Non-overlap

This does not repeat the accepted Math/TeX alignedat, multline, array width-column, bangle infix fraction, modular command, or TeX comment slices. It is limited to the upstream texmath `\hyperref` wrapper behavior.

## Follow-up

Potential next Math/TeX gaps remain bounded wrapper or parser-handoff work such as additional no-op wrappers, matched delimiter preflight, `\mathchoice`, or accessibility refinements, all without invoking external TeX or Pandoc tooling.
