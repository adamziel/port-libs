# Pandoc Syntax Highlighting Core Current Base

Slice: `pandoc-syntax-highlighting-core-current-base-20260605T060025Z`

Base accepted HEAD: `bf89d6de785957e3a6e4d0b25b2fd50108f73ec5`

## Behavior Added

- Extended bounded native `SyntaxHighlighter` Dockerfile review handoff:
  - normalizes `Dockerfile`, `Containerfile`, and `docker` code-block aliases
    into canonical `dockerfile` highlighting;
  - tokenizes bounded Dockerfile review snippets for syntax/escape directives,
    comments, instructions, option flags, assignment keys, quoted strings,
    variables, numbers, shell-form command words, operators, and line
    continuations using the existing Pandoc/Skylighting-style short classes;
  - preserves Pandoc numbered-source wrappers, `startFrom` counters, and
    WordPress raw HTML style metadata for container-build migration review
    packets.
- Updated the WordPress syntax-highlighting fixture and example self-test with
  a numbered Dockerfile review snippet so import reviewers can inspect
  container configuration source without invoking Docker, external highlighters,
  Pandoc, or Skylighting.

## Source Truth

- Existing accepted syntax-highlighting slices established the local
  Pandoc/Skylighting handoff contract: normalize code-block language aliases,
  use Pandoc built-in style names, emit `sourceCode` / `numberSource` wrappers,
  and preserve short token classes such as `kw`, `fu`, `ot`, `st`, `co`,
  `dv`, `op`, and `va`.
- Pandoc `Text.Pandoc.Highlighting` at
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83` delegates code-block
  highlighting to Skylighting syntax lookup by code-block classes and emits the
  same high-level HTML handoff options. This slice ports a bounded Dockerfile
  token handoff, not the full Skylighting XML syntax engine.
- Skylighting's Dockerfile XML syntax definition treats Dockerfile and
  Containerfile source as a supported syntax and defines instruction keywords,
  comments/directives, option flags, strings, shell-form instructions, and line
  continuations as visible token categories for highlighting.
- No Pandoc binary, Cabal solver/build/test command, Haskell runner,
  Skylighting runtime, Docker/container tool, external highlighter, browser
  renderer, online sanitizer, office tool, archive tool, TeX/PDF engine,
  Typst, roff, or online service was executed.

## Verification

- Baseline before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 293 assertions, 0 failures`
- Red checks before final implementation:
  - First focused run after adding Dockerfile expectations failed because the
    initial shell-word heuristic tagged image tags and stage names as
    functions.
  - Second focused run failed because Dockerfile path separators were
    over-tokenized as operators.
- Focused behavior after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 314 assertions, 0 failures`
- Focused lane directory after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `20 test files, 7862 assertions, 0 failures`
- PASS-line count check:
  - `php tools/run-tests.php lanes/pandoc/tests | rg -c '^PASS '`
  - Result: `675`
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
TeX/LaTeX, diff/patch, Markdown-family, Ruby/Rake, Lua/Pandoc-Lua,
TypeScript, Python, C/C++, or Pandoc JSON `.theme` support. It also avoids
Markdown/HTML reader coverage, XML/HTML5 DOM support, EPUB3 package handoff,
DOCX/ODT parsing, ZIP/OPC, archive compression, PDF engine diagnostics,
BibTeX/CSL, YAML, doctemplate, table geometry, math/TeX conversion,
charset/Unicode, and legacy DOC/CFB slices. It owns only bounded Dockerfile /
Containerfile syntax-highlighting alias and token handoff.

## Follow-Up

Keep full Skylighting XML syntax-definition parity, parser-state-aware
Dockerfile heredocs, JSON-array instruction embedding, Dockerfile variable
expansion state, incremental lexer state, token title attributes, line-number
color parity, writer-wide default highlighting policy, and additional language
grammars as separate bounded slices.
