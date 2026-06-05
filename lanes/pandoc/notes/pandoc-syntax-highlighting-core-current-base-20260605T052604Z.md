# Pandoc Syntax Highlighting Core Current Base

Slice: `pandoc-syntax-highlighting-core-current-base-20260605T052604Z`

Base accepted HEAD: `ae6a05572a514b4de77950463d79f6efe31c6d0a`

## Behavior Added

- Extended bounded native `SyntaxHighlighter` C/C++ review handoff:
  - normalizes `c`, `h`, `c++`, `cpp`, `cxx`, `cc`, `hpp`, `hh`, and `hxx`
    code-block classes into canonical `c` or `cpp` language handoff;
  - tokenizes bounded C-family review snippets for comments, preprocessor
    directives, strings and character literals, keywords, datatypes,
    constants, numbers, functions, variables, and operators using the existing
    Pandoc/Skylighting-style short classes;
  - preserves Pandoc numbered-source wrappers, `startFrom` counters, and
    WordPress raw HTML style metadata for C++ migration review packets.
- Updated the WordPress syntax-highlighting fixture and example self-test with
  a numbered C++ extension-review snippet so reviewers can inspect native code
  without invoking external highlighters or compilers.

## Source Truth

- Existing accepted syntax-highlighting slices established the local
  Pandoc/Skylighting handoff contract: normalize code-block language aliases,
  use Pandoc built-in style names, emit `sourceCode` / `numberSource` wrappers,
  and preserve short token classes such as `kw`, `dt`, `fu`, `va`, `cn`, `st`,
  `co`, `dv`, `ot`, `op`, and `pp`.
- Pandoc `Text.Pandoc.Highlighting` at
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83` delegates code-block
  highlighting to Skylighting syntax lookup by code-block classes and emits the
  same high-level HTML handoff options. This slice ports a bounded C/C++ token
  handoff, not the full Skylighting XML syntax engine.
- No Pandoc binary, Cabal solver/build/test command, Haskell runner,
  Skylighting runtime, C/C++ compiler, external highlighter, browser renderer,
  online sanitizer, office tool, archive tool, TeX/PDF engine, Typst, roff, or
  online service was executed.

## Verification

- Baseline before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 265 assertions, 0 failures`
- Red check before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result after adding the C/C++ expectations and before source changes:
    `1 test files, 246 assertions, 2 failures`
- Focused behavior after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 293 assertions, 0 failures`
- Focused lane directory after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `20 test files, 7508 assertions, 0 failures`
- PASS-line count check:
  - `php tools/run-tests.php lanes/pandoc/tests | rg -c '^PASS '`
  - Result: `652`
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test`
  - Result: `syntax highlighting handoff self-test ok`
- Syntax and metadata checks:
  - `php -l lanes/pandoc/src/SyntaxHighlighter.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php`
  - Result: no syntax errors.
  - `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`
  - Result: `json ok`
  - `git diff --check -- lanes/pandoc`
  - Result: no whitespace errors.

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
TypeScript, Python, or Pandoc JSON `.theme` support. It also avoids
Markdown/HTML reader coverage, XML/HTML5 DOM support, EPUB3 package handoff,
DOCX/ODT parsing, ZIP/OPC, archive compression, PDF engine diagnostics,
BibTeX/CSL, YAML, doctemplate, table geometry, math/TeX conversion,
charset/Unicode, and legacy DOC/CFB slices. It owns only bounded C/C++
syntax-highlighting alias and token handoff.

## Follow-Up

Keep full Skylighting XML syntax-definition parity, embedded-language
highlighting inside fenced Markdown code, token title attributes, incremental
lexer state, line-number color parity, writer-wide default highlighting
policy, C/C++ preprocessor continuation state, C++ templates/concepts semantic
state, and additional language grammars as separate bounded slices.
