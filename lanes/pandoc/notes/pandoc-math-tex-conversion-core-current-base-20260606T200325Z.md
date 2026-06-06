## Pandoc Math/TeX modular command slice

Slice: `pandoc-math-tex-conversion-core-current-base-20260606T200325Z`
Base: `a213d12bcad4e5ead54f882edb566fd2d7e1093c`

### Source Truth

Upstream texmath TeX reader command handling maps modular arithmetic commands in `Text/TeXMath/Readers/TeX.hs`:

- `https://raw.githubusercontent.com/jgm/texmath/master/src/Text/TeXMath/Readers/TeX.hs`
- `\mod` and `\bmod` render as a math operator `mod` with bounded spacing around the required operand.
- `\pmod` renders a parenthesized `mod` expression, and `\pod` renders a parenthesized operand without the `mod` operator.

No local Pandoc or texmath checkout was available in this isolated worktree/upstream cache. The source check used the upstream raw source only. No Pandoc, texmath runner, MathJax, KaTeX, TeX/PDF engine, Cabal, Haskell runner, or external converter was executed.

### Red-First Evidence

Before the mapping, a direct PHP probe showed modular commands fell through as literal identifier tokens:

- `a \mod n` rendered `<mi>\mod</mi>`
- `a \bmod n` rendered `<mi>\bmod</mi>`
- `a \pmod n` rendered `<mi>\pmod</mi>`
- `a \pod n` rendered `<mi>\pod</mi>`

Baseline focused test:

`php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`

passed with `1 test files, 546 assertions, 0 failures`.

### Patch Summary

- Added bounded native `MathTexConverter` handling for `\mod`, `\bmod`, `\pmod`, and `\pod`.
- Preserved texmath-style operator spacing and parenthesized forms in MathML without shelling out to TeX or external renderers.
- Added scripted-operand parsing for this command family so `\bmod m_i` binds `_i` to `m` rather than to the modular phrase.
- Added focused MathML/accessibility/error tests plus a WordPress math handoff smoke for the same formula.
- Updated lane status and upstream-test manifest by one mapped Math/TeX support case, `+1` PHP PASS case, and `+13` focused assertions.

### Verification

- `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - `1 test files, 559 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  - `math tex handoff self-test ok`
- `php -l lanes/pandoc/src/MathTexConverter.php`
  - `No syntax errors detected in lanes/pandoc/src/MathTexConverter.php`
- `php -l lanes/pandoc/tests/MathTexConverterTest.php`
  - `No syntax errors detected in lanes/pandoc/tests/MathTexConverterTest.php`
- `php -l lanes/pandoc/examples/wordpress-math-tex-handoff.php`
  - `No syntax errors detected in lanes/pandoc/examples/wordpress-math-tex-handoff.php`
- `git diff --check -- lanes/pandoc`
  - passed with no output

### Non-Overlap And Dependency Closure

This slice does not overlap recent Math/TeX accepted work for alignedat, multline/multlined, array width columns/hooks/multicolumns, bangle infix fractions, color/phantom/cancel/smash/overlap wrappers, math variants/classes, arrow accents, compact matrices/subarrays, `\stackrel`, `\ensuremath`, or `\surd`.

No new support component is needed. The slice reuses native PHP `MathTexConverter` parsing/rendering, the existing MathML source-annotation wrapper, the WordPress math handoff example, and the focused lane test harness.

Follow-up remains bounded Math/TeX parity for additional texmath reader commands and MathML handoff diagnostics. Full Pandoc/texmath runner parity, TeX rendering, MathJax/KaTeX rendering, Cabal/Haskell test execution, and external conversion services remain out of scope for this micro-slice.
