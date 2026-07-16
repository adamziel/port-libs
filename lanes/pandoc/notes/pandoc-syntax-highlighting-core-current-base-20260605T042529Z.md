# Pandoc Syntax Highlighting Core Current Base

Slice: `pandoc-syntax-highlighting-core-current-base-20260605T042529Z`

Base accepted HEAD: `79f7b37d233bf2b4c9e836c5623f91107e0a407a`

## Behavior Added

- Extended bounded native `SyntaxHighlighter` TypeScript handoff:
  - normalizes `ts` and `typescript` code-block classes to canonical
    `typescript`;
  - tokenizes bounded TypeScript review snippets for comments, keywords,
    primitive and named datatypes, type aliases, generics, async functions,
    optional chaining, nullish coalescing, template literals, variables,
    functions, numeric literals, decorators, and operators using existing
    Pandoc/Skylighting-style short token classes;
  - preserves Pandoc numbered-source wrappers, `startFrom` counters, and
    WordPress raw HTML style metadata for TypeScript migration review packets.
- Updated the WordPress syntax-highlighting fixture and example self-test with
  a numbered TypeScript Gutenberg/block-editor migration snippet so import
  reviewers can inspect typed migration source without invoking external
  highlighters.

## Source Truth

- Pandoc `Text.Pandoc.Highlighting` at
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83`
  (`https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/Highlighting.hs`)
  delegates code-block highlighting to Skylighting syntax lookup, carries the
  same built-in styles, and emits `sourceCode` / numbered-source format
  options.
- Skylighting's bundled TypeScript syntax definition
  (`https://raw.githubusercontent.com/jgm/skylighting/master/skylighting-core/xml/typescript.xml`)
  extends JavaScript token sets with TypeScript-specific declarations,
  readonly/abstract/constructor/get/set keywords, namespace/module keywords,
  and primitive type names. This slice ports the bounded token handoff, not the
  full XML syntax engine.
- No Pandoc binary, Cabal solver/build/test command, Haskell runner,
  Skylighting runtime, Node, TypeScript, external highlighter, browser
  renderer, online sanitizer, office tool, archive tool, TeX/PDF engine,
  Typst, roff, or online service was executed.

## Verification

- Baseline before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 216 assertions, 0 failures`
- Focused behavior after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 239 assertions, 0 failures`
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test`
  - Result: `syntax highlighting handoff self-test ok`

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing `AstNode`,
`MarkdownReader`, `WordPressBlockWriter`, and bounded native
`SyntaxHighlighter` support row. Full upstream runner parity remains gated on
hydrating the Pandoc checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83`
and producing a Cabal solver/build plan for `test-pandoc` and
`test-pandoc-lua-engine`.

## Non-Overlap

This patch does not repeat accepted syntax-highlighting coverage for base
language/style/token support, line anchors, WordPress writer opt-in, Haskell,
TeX/LaTeX, diff/patch, Markdown-family, Ruby/Rake, Lua/Pandoc-Lua, or Pandoc
JSON `.theme` support. It also avoids Markdown/HTML reader coverage,
XML/HTML5 DOM support, EPUB3 package handoff, DOCX/ODT parsing, ZIP/OPC,
archive compression, PDF engine diagnostics, BibTeX/CSL, YAML, doctemplate,
table geometry, math/TeX conversion, charset/Unicode, and legacy DOC/CFB
slices. It owns only bounded TypeScript syntax-highlighting alias and token
handoff.

## Follow-Up

Keep full Skylighting XML syntax-definition parity, TSX/JSX grammars,
embedded-language highlighting inside fenced Markdown code, token title
attributes, incremental lexer state, line-number color parity, writer-wide
default highlighting policy, and additional language grammars as separate
bounded slices.
