# Pandoc Syntax Highlighting Core Current Base

Slice: `pandoc-syntax-highlighting-core-current-base-20260605T045546Z`

Base accepted HEAD: `d10c41aa298504539e2d705554266ce62d95aed3`

## Behavior Added

- Extended bounded native `SyntaxHighlighter` Python review handoff:
  - normalizes `py3` and `python3` code-block classes to canonical `python`
    alongside the existing `py`/`python` aliases;
  - tokenizes bounded Python 3 review snippets for decorators, imports,
    classes, type annotations, builtin datatypes, variables, constants,
    function and method calls, modern operators such as `->`, and comments
    using the existing Pandoc/Skylighting-style short classes;
  - preserves Pandoc numbered-source wrappers, `startFrom` counters, and
    WordPress raw HTML style metadata for Python migration review packets.
- Updated the WordPress syntax-highlighting fixture and example self-test with
  a numbered Python import-cleanup snippet so reviewers can inspect Python
  source without invoking external highlighters.

## Source Truth

- Existing accepted syntax-highlighting slices established the local
  Pandoc/Skylighting handoff contract: normalize code-block language aliases,
  use Pandoc built-in style names, emit `sourceCode` / `numberSource` wrappers,
  and preserve short token classes such as `kw`, `dt`, `fu`, `va`, `cn`,
  `st`, `co`, `dv`, `ot`, and `op`.
- Pandoc `Text.Pandoc.Highlighting` at
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83`
  delegates code-block highlighting to Skylighting syntax lookup by code-block
  classes and emits the same high-level HTML handoff options. This slice ports
  a bounded Python 3 token handoff, not the full Skylighting XML syntax engine.
- No Pandoc binary, Cabal solver/build/test command, Haskell runner,
  Skylighting runtime, Python interpreter, external highlighter, browser
  renderer, online sanitizer, office tool, archive tool, TeX/PDF engine,
  Typst, roff, or online service was executed.

## Verification

- Baseline before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 239 assertions, 0 failures`
- Red check before implementation:
  - `php -r 'require "tools/bootstrap.php"; $h=new PortLibs\\Pandoc\\SyntaxHighlighter(); var_export([PortLibs\\Pandoc\\SyntaxHighlighter::normalizeLanguage("python3"), $h->highlight("@dataclass\\nclass ReviewPacket:\\n    source_id: int\\n", "python3")["language"]]); echo "\\n";'`
  - Result: `array ( 0 => NULL, 1 => '', )`
- Focused behavior after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 265 assertions, 0 failures`
- Focused lane directory after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `20 test files, 7224 assertions, 0 failures`
- PASS-line count check:
  - `php tools/run-tests.php lanes/pandoc/tests | rg -c '^PASS '`
  - Result: `632`
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
TeX/LaTeX, diff/patch, Markdown-family, Ruby/Rake, Lua/Pandoc-Lua, TypeScript,
or Pandoc JSON `.theme` support. It also avoids Markdown/HTML reader coverage,
XML/HTML5 DOM support, EPUB3 package handoff, DOCX/ODT parsing, ZIP/OPC,
archive compression, PDF engine diagnostics, BibTeX/CSL, YAML, doctemplate,
table geometry, math/TeX conversion, charset/Unicode, and legacy DOC/CFB
slices. It owns only bounded Python 3 syntax-highlighting alias and token
handoff.

## Follow-Up

Keep full Skylighting XML syntax-definition parity, embedded-language
highlighting inside fenced Markdown code, token title attributes, incremental
lexer state, line-number color parity, writer-wide default highlighting
policy, Python semantic indentation state, and additional language grammars as
separate bounded slices.
