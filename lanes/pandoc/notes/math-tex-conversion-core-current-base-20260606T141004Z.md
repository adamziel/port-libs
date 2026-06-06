# Math/TeX Array Preamble Hooks - 2026-06-06

Slice: `pandoc-math-tex-conversion-core-current-base-20260606T141004Z`

Accepted base: `be6f8e132ff60635ee5054a4f29f12b44a650b22`

## Behavior

Added bounded native PHP Math/TeX handoff support for inert TeX `array`
preamble hooks:

- `>{...}` is preserved as `pre-N:<source>` metadata for the following column.
- `<{...}` is preserved as `post-N:<source>` metadata for the previous column.
- `@{...}` is preserved as `gap-before-1:<source>` or `gap-after-N:<source>`
  inter-column metadata.

The hooks are not executed as TeX. They are exposed on the generated MathML
`mtable` as `data-tex-column-hooks` so WordPress and reviewer tooling can see
the source preamble intent without invoking texmath, MathJax, KaTeX, or a TeX
engine.

The allow-list is intentionally narrow: literal text plus bounded spacing/text
commands such as `\,`, `\quad`, `\hspace{.25em}`, `\mspace{2mu}`, `\text{...}`,
and `\mbox{...}`. Active declarations and malformed hooks remain rejected,
including `\bfseries`, `\input{...}`, bad spacing dimensions, empty hook groups,
subarray hooks, and unmatched pre-column hooks.

## Evidence

Red-first probe before implementation:

```text
php -r 'require "tools/bootstrap.php"; $c = new PortLibs\Pandoc\MathTexConverter(); echo $c->texToMathMl("\\begin{array}{>{\\text{src}}l<{\\hspace{.25em}}@{\\,}c}p_i & m_i\\\\q_i & n_i\\end{array}", true), "\n";'
```

Result: fatal `InvalidArgumentException: Unsupported TeX array column specifier > at offset 0`.

Focused baseline:

```text
php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php
```

Result before this slice: `1 test files, 490 assertions, 0 failures`.

Focused verification after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php
```

Result after this slice: `1 test files, 501 assertions, 0 failures`.

WordPress example smoke:

```text
php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test
```

Result: `math tex handoff self-test ok`.

## Dependency Closure

No new support component is needed. This slice reuses the existing
`MathTexConverter`, `MarkdownReader`, `LatexWriter`, and `WordPressBlockWriter`
handoff path. Full texmath/Pandoc math parity, arbitrary TeX preamble execution,
TeX engines, MathJax/KaTeX rendering, Cabal/Haskell runners, and the full
upstream test suite remain intentionally out of scope.

## Next

Non-overlapping follow-up candidates: bounded `!{...}` separator metadata,
`\multicolumn` cell-span handoff, or richer MathML accessibility annotations.
