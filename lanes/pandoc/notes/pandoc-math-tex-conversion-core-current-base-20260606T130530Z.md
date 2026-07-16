# Pandoc Math/TeX Boxed Handoff

Micro-slice: `pandoc-math-tex-conversion-core-current-base-20260606T130530Z`

Accepted base: `d7dd35e193e433506c4031446b30b2cc5f04e717`

## Source Truth

Upstream texmath treats `\boxed` as a boxed expression in the TeX reader and writes it as MathML `menclose` with `notation="box"`:

- https://raw.githubusercontent.com/jgm/texmath/master/src/Text/TeXMath/Readers/TeX.hs
- https://raw.githubusercontent.com/jgm/texmath/master/src/Text/TeXMath/Writers/MathML.hs

No upstream runner, Pandoc executable, texmath executable, TeX engine, MathJax, KaTeX, browser renderer, online service, or live provider test was executed.

## Behavior

`MathTexConverter` now maps bounded `\boxed{...}` groups to:

```xml
<menclose notation="box">...</menclose>
```

The implementation preserves existing source-TeX annotations, supports scripts on boxed groups, keeps accessibility alt text/intent through the existing `menclose` path, and rejects missing or empty boxed content instead of treating `\boxed` as an identifier.

## Red First

Before implementation, this probe failed because output contained `<mi>\boxed</mi>` and no boxed `menclose`:

```bash
php -r 'require "tools/bootstrap.php"; $c = new PortLibs\Pandoc\MathTexConverter(); $m = $c->texToMathMl("\\boxed{p_i + m_i}"); if (!str_contains($m, "<menclose notation=\"box\">")) { fwrite(STDERR, "missing boxed menclose\n" . $m . "\n"); exit(1); }'
```

## Verification

```bash
php -l lanes/pandoc/src/MathTexConverter.php
# No syntax errors detected in lanes/pandoc/src/MathTexConverter.php

php -l lanes/pandoc/tests/MathTexConverterTest.php
# No syntax errors detected in lanes/pandoc/tests/MathTexConverterTest.php

php -l lanes/pandoc/examples/wordpress-math-tex-handoff.php
# No syntax errors detected in lanes/pandoc/examples/wordpress-math-tex-handoff.php

php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php
# 1 test files, 483 assertions, 0 failures

php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test
# math tex handoff self-test ok
```

Final whitespace verification:

```bash
git diff --check -- lanes/pandoc
# passed
```

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP `MathTexConverter`, `MarkdownReader`, `LatexWriter`, and `WordPressBlockWriter` handoff path.

Follow-up remains bounded: additional TeX enclosure/package-adjacent commands, full texmath/Pandoc runner parity, MathJax/KaTeX/browser rendering, and TeX/PDF engines stay out of scope until explicitly assigned.
