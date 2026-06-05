# Pandoc Syntax Highlighting Core Current Base

Slice: `pandoc-syntax-highlighting-core-current-base-20260605T130032Z`

Base accepted HEAD: `7432b93e43b53e78103e7d38c8e49c883684735d`

## Behavior Added

- Extended bounded native `SyntaxHighlighter` Rust handoff:
  - normalizes `rust`, `rs`, and `language-rs` code-block classes into
    canonical `rust` highlighting;
  - tokenizes bounded Rust migration-review snippets for comments, `use`
    paths, attributes, lifetimes, structs, impl blocks, functions, primitive
    and common datatypes, macros, strings, numbers, constants, method calls,
    and operators using the existing Pandoc/Skylighting-style short classes;
  - preserves Pandoc numbered-source wrappers, `startFrom` counters, and
    WordPress raw HTML style metadata for Rust import-helper review packets.
- Updated the WordPress syntax-highlighting fixture and example self-test with
  a numbered Rust review snippet so migration reviewers can inspect Rust helper
  code without invoking Pandoc, Skylighting, Rust compilers/runtimes, external
  highlighters, browser renderers, JavaScript, online sanitizers, or online
  services.

## Source Truth

- Pandoc `Text.Pandoc.Highlighting` at
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83` delegates code-block
  highlighting to Skylighting syntax lookup by code-block classes and carries
  `startFrom`, `numberLines`, `lineAnchors`, `lineIdPrefix`, source-code
  classes, and built-in/custom styles through formatter options.
- Skylighting's Rust syntax definition exposes Rust as a supported syntax with
  alias/extension coverage for `.rs` source and token categories for comments,
  attributes, lifetimes, keywords, datatypes, macros, strings, numeric
  literals, constants, paths, function calls, and operators. This slice ports a
  bounded token handoff, not the full KDE XML syntax-definition engine or a
  Rust parser/compiler.
- No Pandoc binary, Cabal solver/build/test command, Haskell runner,
  Skylighting runtime, Rust compiler/runtime, external highlighter, browser
  renderer, JavaScript, online sanitizer, office tool, archive tool,
  TeX/PDF engine, Typst, roff, or online service was executed.

## Verification

- Baseline before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 573 assertions, 0 failures`
- Red-first focused probe:
  - `php -r 'require "tools/bootstrap.php"; $h = new \PortLibs\Pandoc\SyntaxHighlighter(); $result = $h->highlight("pub fn render_title(title: &str) -> String { title.to_string() }", "rs"); var_export([\PortLibs\Pandoc\SyntaxHighlighter::normalizeLanguage("rust"), \PortLibs\Pandoc\SyntaxHighlighter::normalizeLanguage("rs"), $result["language"], $result["diagnostics"]]); echo "\n";'`
  - Result: `rust` and `rs` normalized to `NULL`, language was empty, and
    diagnostics contained `unsupported-language`.
- Focused behavior after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 600 assertions, 0 failures`
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

## Non-overlap

This patch does not repeat accepted syntax-highlighting coverage for base
language/style/token support, line anchors, WordPress writer opt-in, Haskell,
TeX, diff, Markdown, Ruby, Lua, TypeScript, JSX, R, Python, C/C++, Dockerfile,
Makefile, INI, TOML, Perl, Java, XML/XSLT, Bash heredoc state, token-title
metadata, custom Pandoc JSON themes, or CSS at-rule/selector/custom-property
handoff. It owns only bounded Rust/rs alias and token handoff.

## Follow-up

Keep Rust raw identifiers, nested block-comment state, proc-macro token trees,
embedded language highlighting, parser-state-aware token metadata,
writer-wide default highlighting policy, and full KDE/Skylighting XML
syntax-definition parity as separate bounded slices.
