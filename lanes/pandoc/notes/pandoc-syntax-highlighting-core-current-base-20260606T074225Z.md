# Pandoc Syntax Highlighting Core Current Base - Mermaid

Slice: `pandoc-syntax-highlighting-core-current-base-20260606T074225Z`
Base accepted HEAD: `e9ae20f5b9827255fc6c5ece376150c0bc8003d6`

## Summary

- Added bounded native Mermaid syntax highlighting with `mermaid`,
  `mermaid-js`, and `mermaidjs` aliases.
- Tokenizes diagram review snippets for directives, `%%` comments, diagram
  declarations, directions, node identifiers, labels, flowchart arrows,
  `classDef` style keys, class assignments, and sequence-diagram participant
  messages.
- Updated the WordPress syntax-highlighting fixture and self-test example so
  imported Mermaid workflow diagrams keep Pandoc-style numbered source lines,
  style metadata, and raw HTML handoff output.

## Source Truth

- Pandoc syntax highlighting is driven by code-block language names and the
  Skylighting highlighter contract:
  `https://pandoc.org/demo/example33/15-syntax-highlighting.html`.
- Mermaid flowchart syntax covers `flowchart LR`, links between nodes, dotted
  links with text, `%%` comments, and `classDef`/`class` styling:
  `https://mermaid.js.org/syntax/flowchart.html`.
- Mermaid sequence diagrams cover `sequenceDiagram`, `participant` and actor
  declarations, message arrows, and `Actor->>Actor: message` syntax:
  `https://mermaid.js.org/syntax/sequenceDiagram.html`.

This slice ports a bounded token handoff for reviewable code blocks. It does
not attempt full Mermaid parsing, diagram rendering, JavaScript execution, or
full Skylighting/KDE XML syntax-definition parity.

## Pre-Edit Probe

```text
php -r 'require "tools/bootstrap.php"; $h = new \PortLibs\Pandoc\SyntaxHighlighter(); $result = $h->highlight("flowchart LR\n  A --> B\n", "mermaid"); var_export([\PortLibs\Pandoc\SyntaxHighlighter::normalizeLanguage("mermaid"), $result["language"], $result["diagnostics"]]); echo "\n";'
array ( 0 => NULL, 1 => '', 2 => array ( 0 => array ( 'severity' => 'warning', 'message' => 'unsupported-language', 'language' => 'mermaid', ), ), )
```

## Verification

- Baseline before edit:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 1092 assertions, 0 failures`
- After implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php`
  - Result: `1 test files, 1121 assertions, 0 failures`
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
  - `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`
- Diff hygiene:
  - `git diff --check -- lanes/pandoc`
  - Result: no whitespace errors.

Root harness not run - isolated micro-slice.

## Status Delta

- Focused SyntaxHighlighter coverage: `1092 -> 1121` assertions.
- Focused PASS cases: `51 -> 52`.
- Lane `phpPass`: `1244 -> 1245`.
- Manifest mapped denominator: `1687 -> 1688`.
- Added `syntaxHighlightingMermaidDiagramCases: 1`,
  `mappedSyntaxHighlightingMermaidDiagramCases: 1`, and
  `syntaxHighlightingMermaidDiagramAssertions: 29`.

## Dependency Closure

No new support component is needed. This reuses the native PHP
`SyntaxHighlighter`, `MarkdownReader`, `AstNode`, and `WordPressBlockWriter`
support rows plus the existing syntax-highlighting fixture and handoff example.
Full upstream runner parity remains gated on hydrating the Pandoc checkout at
`0640c4c9859aa5a3ede082c190fcd5883c24ac83` and producing a Cabal solver/build
plan for `test-pandoc` and `test-pandoc-lua-engine`.

No Pandoc, Cabal solver/build/test command, Haskell runner, Skylighting
runtime, Mermaid renderer, browser renderer, JavaScript runtime, external
highlighter, online sanitizer, online service, or live provider test was
executed.

## Non-Overlap

This slice does not repeat accepted syntax-highlighting coverage for CSS,
Rust, Nix, SCSS/Sass, Go, PowerShell, DOT, JavaScript, C#, TSX, CMake, Nginx,
Twig, Mustache/Handlebars, HTML, SQL/PostgreSQL, Apache, RST, Haskell,
TeX/LaTeX, diff/patch, Markdown-family, Ruby/Rake, Lua/Pandoc-Lua, TypeScript,
R, Python, C/C++, Dockerfile/Containerfile, Makefile, INI/config, TOML, Perl,
Java, XML/XSLT, Bash heredoc state, PHP heredoc/nowdoc, or Pandoc JSON theme
support. It owns only bounded Mermaid diagram alias and token handoff.

## Follow-Up

Keep full Mermaid grammar parity, diagram rendering, embedded HTML labels,
theme-variable parsing, Mermaid JS runtime behavior, sequence activation state,
parser-state-aware nested labels, full KDE/Skylighting XML syntax-definition
parity, and writer-wide default highlighting policy as separate bounded
slices.
