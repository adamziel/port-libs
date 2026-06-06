## Pandoc Math/TeX bangle infix fraction slice

Slice: `pandoc-math-tex-conversion-core-current-base-20260606T190350Z`
Base: `05f7c529bb0252dd89e85dabbaacf5c39c827fd9`

### Source truth

Upstream texmath TeX reader command tables map `\bangle` as a binomial-style infix command using angle delimiters and a no-line fraction:

- `https://raw.githubusercontent.com/jgm/texmath/master/src/Text/TeXMath/Readers/TeX.hs`
- `binomCmds` includes `\bangle` -> `EDelimited "⟨" "⟩" [Right (EFraction NoLineFrac ...)]`

No local Pandoc or texmath checkout was available in this isolated worktree/upstream cache, so the source check used the upstream raw source only. No Pandoc, texmath runner, MathJax, KaTeX, TeX/PDF engine, Cabal, Haskell runner, or external converter was executed.

### Red-first evidence

Before the mapping, a direct PHP probe of `$converter->texToMathMl('{n \\bangle k}', true)` rendered `\bangle` as an identifier token:

`<mrow><mi>n</mi><mi>\bangle</mi><mi>k</mi></mrow>`

Baseline focused test:

`php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`

passed with `1 test files, 540 assertions, 0 failures`.

### Patch summary

- Added `bangle` to `MathTexConverter::INFIX_FRACTION_COMMANDS` with `linethickness="0"` and `⟨` / `⟩` fence metadata.
- Added a focused MathML test covering display output, source annotation preservation, subscript operands, and malformed empty-side rejection.
- Updated the WordPress math handoff example and self-test so the review packet carries `{n \bangle k}` through Markdown, WordPress block output, and MathML summary output.
- Updated lane status and upstream-test manifest by one mapped Math/TeX support case, `+1` PHP PASS case, and `+6` focused assertions.

### Verification

- `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - `1 test files, 546 assertions, 0 failures`
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

### Non-overlap and dependency closure

This slice does not overlap recent Math/TeX accepted work for alignedat, multline/multlined, array width columns, multicolumn arrays, boxed expressions, accent aliases, class wrappers, `\stackrel`, `\ensuremath`, or `\surd`. It reuses the existing native PHP infix-fraction renderer and adds no support component.

Follow-up remains bounded Math/TeX parity for additional texmath reader commands and MathML handoff diagnostics. Full Pandoc/texmath runner parity, TeX rendering, MathJax/KaTeX rendering, Cabal/Haskell test execution, and external conversion services remain out of scope for this micro-slice.
