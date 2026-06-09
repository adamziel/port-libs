# Pandoc Syntax Highlighting Core Current Base

Slice: `pandoc-syntax-highlighting-core-current-base-20260608T235841Z`

Base accepted HEAD: `98e36d1bfbcd2aff359b39b4120999431e5e0fde`

## Behavior Added

- Added bounded native Meson and Justfile syntax-highlighting support to
  `SyntaxHighlighter`.
- Meson code fences now normalize `meson` and `meson.build`, then classify
  comments, strings, project/build function calls, keyword arguments, booleans,
  variables, and operators.
- Justfile code fences now normalize `just`, `justfile`, `Justfile`, and
  `just-file`, then classify settings, exports, recipe headers, interpolation
  variables, shell/wp/php command names, options, strings, and operators.
- Extended the WordPress syntax-highlighting fixture and example smoke with
  numbered Meson and Justfile review snippets for native-helper build/runbook
  imports.

## Source Truth

- Pandoc delegates fenced-code highlighting through its syntax-highlighting
  layer and preserves code-block classes, style selection, `numberLines`, and
  `startFrom` metadata through the formatter contract.
- Skylighting/KDE syntax definitions include build-system syntaxes such as
  Meson and Justfile; this ports a bounded token handoff for review packets,
  not full parser-state parity for either build tool.
- No Pandoc binary, Cabal solver/build/test command, Haskell runner,
  Skylighting runtime, Meson, just, compiler, external highlighter, browser
  renderer, JavaScript runtime, online sanitizer, online service, live provider
  test, or live-service provider test was executed.

## Verification

- Rework notes:
  - `find /home/claude/port-libs/.tmux-team/tmp/handoff-candidates -maxdepth 1 -type f -name 'port-pandoc-*.needs-lane-rework.md' -print`
  - Result: no current Pandoc rework notes.
- Red-first probe before implementation:
  - Direct `SyntaxHighlighter` probe showed `meson`, `meson.build`, `just`,
    `justfile`, and `Justfile` all returned `unsupported-language`.
- Baseline before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 2159 assertions, 0 failures`
- Focused behavior after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 2203 assertions, 0 failures`
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test`
  - Result: `syntax highlighting handoff self-test ok`

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native PHP `AstNode`,
`MarkdownReader`, `SyntaxHighlighter`, `WordPressBlockWriter`, the existing
syntax-highlighting fixture, and the existing WordPress raw HTML handoff
example. Full upstream Pandoc/Skylighting runner parity remains gated on a
hydrated pinned Pandoc checkout and a reviewed non-mutating Cabal plan.

## Non-Overlap

This does not repeat accepted syntax-highlighting cases for base language/style
wrappers, line anchors, token-title attributes, WordPress writer opt-in,
Haskell, TeX/LaTeX, diff/patch, Markdown-family, Ruby/Rake, Lua/Pandoc-Lua,
TypeScript, Python, C/C++, Dockerfile/Containerfile, Makefile, JS/JSX/TSX,
R, INI/config, TOML/Cargo.lock, Perl, Java, XML/XSLT, Bash/sh heredoc state,
CSS, Rust, Nix, SCSS/Sass, Go, PowerShell, Graphviz DOT, C#, SQL/PostgreSQL,
Apache, RST, CMake, Nginx, Twig, Handlebars, Mermaid, embedded HTML CSS/
JavaScript/PHP, GraphQL, PHP 8 attributes/enums, PHPDoc annotations, AsciiDoc,
HCL/Terraform, Liquid, Elm, JSONC, Less, Typst, Kotlin, Dart, Swift, Clojure/
EDN, Scala, Elixir, Vue, OCaml, Julia, AWK, Batch, Matlab/Octave, Fish, Sed,
BibTeX, Vimscript, Scheme/Racket, CSV/TSV, Erlang, Objective-C, Raku, or
Fennel. It owns only bounded Meson and Justfile alias/token handoff.

## Follow-Up

Keep full Meson/Just parser-state parity, Meson multiline format strings,
Justfile backtick command bodies, nested interpolation expressions, embedded
shell highlighting in recipe bodies, and full KDE/Skylighting XML syntax
definition parity as separate bounded slices.
