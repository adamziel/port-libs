# Pandoc Syntax Highlighting Core Current Base

Slice: `pandoc-syntax-highlighting-core-current-base-20260605T080740Z`

Base accepted HEAD: `3bf14b9b638fe9c5e4ab82c6137cc41701a89b21`

## Behavior Added

- Extended bounded native `SyntaxHighlighter` INI/config handoff:
  - normalizes `ini`, `cfg`, `gitconfig`, `gitmodules`, `editorconfig`,
    `kcfgc`, and `pls` code-block aliases into canonical `ini` highlighting;
  - tokenizes bounded php.ini-style review snippets for first-non-space
    comments, section headers, assignment operators, setting keys, string
    values, boolean/PHP constant values, negated PHP error constants, and
    numeric values using the existing Pandoc/Skylighting-style short classes;
  - preserves Pandoc numbered-source wrappers, `startFrom` counters, and
    WordPress raw HTML style metadata for hosting configuration review
    packets.
- Updated the WordPress syntax-highlighting fixture and example self-test with
  a numbered php.ini review snippet so migration reviewers can inspect hosting
  configuration without invoking Pandoc, Skylighting, external highlighters,
  browser renderers, or online conversion services.

## Source Truth

- Pandoc `Text.Pandoc.Highlighting` at
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83` delegates code-block
  highlighting to Skylighting syntax lookup by code-block classes, carries the
  built-in style names, and applies `numberLines`, `lineAnchors`, and
  `startFrom` from code-block attributes.
- Skylighting's INI XML syntax definition declares `INI Files`, extensions
  including `*.ini`, `*.cfg`, `.gitconfig*`, `.gitmodules*`, and
  `.editorconfig*`, plus categories for section headers, first-non-space
  comments, assignment, values, floats, integers, and case-insensitive keyword
  values such as `On`, `Off`, `True`, `False`, and PHP `E_*` constants. This
  slice ports a bounded token handoff, not the full KDE XML state machine.
- Sources checked:
  - `https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/Highlighting.hs`
  - `https://raw.githubusercontent.com/jgm/skylighting/master/skylighting-core/xml/ini.xml`
- No Pandoc binary, Cabal solver/build/test command, Haskell runner,
  Skylighting runtime, external highlighter, browser renderer, online
  sanitizer, office tool, archive tool, TeX/PDF engine, Typst, roff, or online
  conversion service was executed.

## Verification

- Baseline before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 384 assertions, 0 failures`
- Red-first focused probe:
  - `php -r 'require "tools/bootstrap.php"; $h = new \PortLibs\Pandoc\SyntaxHighlighter(); $result = $h->highlight("[PHP]\nmemory_limit = 256M\n", "ini"); var_export([\PortLibs\Pandoc\SyntaxHighlighter::normalizeLanguage("ini"), $result["language"], $result["diagnostics"]]); echo "\n";'`
  - Result: `normalizeLanguage("ini")` returned `NULL`, language was empty,
    and diagnostics contained `unsupported-language`.
- Focused behavior after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 408 assertions, 0 failures`
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test`
  - Result: `syntax highlighting handoff self-test ok`
- Syntax:
  - `php -l lanes/pandoc/src/SyntaxHighlighter.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php`
  - Result: no syntax errors.
- JSON validity:
  - `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " ok\n"; }'`
  - Result: both JSON files decoded successfully.
- Diff hygiene:
  - `git diff --check -- lanes/pandoc`
  - Result: no output.

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
TypeScript, Python, C/C++, Dockerfile/Containerfile, Makefile, JSX/React, R,
or Pandoc JSON `.theme` support. It also avoids Markdown/HTML reader coverage,
XML/HTML5 DOM support, EPUB3 package handoff, DOCX/ODT parsing, ZIP/OPC,
archive compression, PDF engine diagnostics, BibTeX/CSL, YAML, doctemplate,
table geometry, math/TeX conversion, charset/Unicode, and legacy DOC/CFB
slices. It owns only bounded INI/config syntax-highlighting alias and token
handoff.

## Follow-Up

Keep TOML, Perl, Java, XML-specific highlighting, shell parser state, inline
comment edge parity, token title attributes, parser-state-aware embedded
language highlighting, full KDE/Skylighting XML syntax-definition parity,
incremental lexer state, writer-wide default highlighting policy, and
additional language grammars as separate bounded slices.
