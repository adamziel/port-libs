# Math/TeX Conversion Core Current-Base Slice

Micro-slice: `pandoc-math-tex-conversion-core-current-base-20260609T025347Z`

Base accepted HEAD: `9cd15b979dbce56dff062c3edd6bb41ff92b9b7e`

## Behavior

- `MarkdownReader` now captures one-line raw TeX macro declarations with balanced nested template bodies for bounded `\newcommand`, `\renewcommand`, `\providecommand`, `\DeclareMathOperator`, `\DeclarePairedDelimiter`, `\DeclarePairedDelimiterX`, and `\DeclarePairedDelimiterXPP` forms.
- The focused case preserves Markdown-imported nested operator and paired-delimiter templates through `raw_tex` blocks, reader-side math source expansion, `MathTexConverter::macroDefinitionsFromDocument()`, MathML semantics annotations, and the WordPress math handoff example.

## Evidence

- Baseline before this patch: `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php` passed with `1 test files, 1124 assertions, 0 failures`.
- Final focused verification: `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php` passed with `1 test files, 1143 assertions, 0 failures`.
- Reader-family verification: `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed with `1 test files, 4456 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test` passed with `math tex handoff self-test ok`.
- Syntax checks passed for:
  - `php -l lanes/pandoc/src/MarkdownReader.php`
  - `php -l lanes/pandoc/tests/MathTexConverterTest.php`
  - `php -l lanes/pandoc/examples/wordpress-math-tex-handoff.php`

## Dependency Closure

No new native support component is needed. This slice reuses lane-local `MarkdownReader` raw TeX block handoff, `MathTexConverter` bounded macro expansion, `WordPressBlockWriter` math spans, and the focused PHP TestRunner. Pandoc, Cabal/Haskell runners, Word, LibreOffice, zip/unzip, external converters, online services, MathJax, KaTeX, and TeX/PDF engines were not executed.

## Non-Overlap

This does not repeat the accepted flat macro expansion, declared operator, paired delimiter, X/XPP template, star/size invocation, source annotation, or accessibility MathML cases. The new mapped case is specifically Markdown import of nested balanced declaration bodies that the prior flat regex declaration reader could not capture as a raw TeX macro definition.

## Next

A reasonable next math/TeX slice is a non-overlapping bounded TeX behavior such as additional texmath macro aliases, MathML accessibility annotations for newer constructs, or malformed macro-definition diagnostics.
