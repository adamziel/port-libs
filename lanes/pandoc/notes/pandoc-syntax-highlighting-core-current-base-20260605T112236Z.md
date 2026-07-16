# Pandoc Syntax Highlighting Core Current Base

Slice: `pandoc-syntax-highlighting-core-current-base-20260605T112236Z`

Base accepted HEAD: `e4da9ea12fd685abfa3a5046c9f4283f3dcf1004`

## Behavior Added

- Extended bounded native `SyntaxHighlighter` Bash/sh shell handoff:
  - normalizes `bash`, `sh`, `shell`, `console`, and `language-sh` code-block
    aliases into canonical `bash` highlighting;
  - tokenizes bounded shell review snippets for shebangs, comments, strings,
    variables, assignment names, long options, short options, command words,
    command substitution, `[[ ... ]]` tests, operators, numbers, booleans, and
    heredoc delimiters using the existing Pandoc/Skylighting-style short
    classes;
  - preserves heredoc body lines as string spans until the matching delimiter,
    so embedded WordPress block HTML in shell review packets is not re-tokenized
    as shell operators/functions;
  - preserves Pandoc numbered-source wrappers, `startFrom` counters, and
    WordPress raw HTML style metadata for shell migration review packets.
- Updated the WordPress syntax-highlighting fixture and example self-test with
  a numbered `sh` review block containing a wp-cli pipeline and WordPress HTML
  heredoc payload. No shell interpreter, Pandoc, Skylighting runtime, external
  highlighter, browser renderer, online sanitizer, or online service is needed.

## Source Truth

- Pandoc `Text.Pandoc.Highlighting` at
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83` delegates code-block
  highlighting to Skylighting syntax lookup by code-block classes and carries
  `startFrom`, `numberLines`, `lineAnchors`, `lineIdPrefix`, source code
  classes, and built-in styles through formatter options.
- Skylighting's Bash syntax definition treats shell scripts as a supported
  syntax and exposes categories for shebangs, comments, strings, variables,
  command words, control-flow words, tests/operators, and here-document text.
  This slice ports a bounded token handoff, not the full KDE XML syntax engine
  or a shell parser.
- No Pandoc binary, Cabal solver/build/test command, Haskell runner,
  Skylighting runtime, shell interpreter, external highlighter, browser
  renderer, online sanitizer, office tool, archive tool, TeX/PDF engine, Typst,
  roff, or online conversion service was executed.

## Verification

- Baseline before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 512 assertions, 0 failures`
- Red-first focused probe before source implementation:
  - Direct Bash heredoc highlight probe showed the heredoc body tokenized as
    shell operators/functions and the delimiter as a function token instead of
    string/region handoff.
- Focused behavior after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 539 assertions, 0 failures`
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
- JSON status/manifest validity:
  - `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " ok\n"; }'`
  - Result: both lane JSON files decoded successfully.
- Diff hygiene:
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
TypeScript, Python, C/C++, Dockerfile/Containerfile, Makefile, JSX/React, R,
INI/config, TOML/Cargo.lock, Perl, Java, XML/XSLT, or Pandoc JSON `.theme`
support. It also avoids Markdown/HTML reader coverage, XML/HTML5 DOM
parser/sanitizer support, EPUB3 package handoff, DOCX/ODT parsing, ZIP/OPC,
archive compression, PDF engine diagnostics, BibTeX/CSL, YAML, doctemplate,
table geometry, math/TeX conversion, charset/Unicode, and legacy DOC/CFB
slices. It owns only bounded Bash/sh heredoc token-state syntax-highlighting
handoff.

## Follow-Up

Keep full shell parser parity, heredoc indentation/chained multiple-heredoc
state, arithmetic expansion, process substitution, shell parameter expansion
operators, token title attributes, parser-state-aware embedded-language
highlighting, full KDE/Skylighting XML syntax-definition parity, and
writer-wide default highlighting policy as separate bounded slices.
