# Pandoc Syntax Highlighting Graphviz DOT Core Slice

Micro-slice: `pandoc-syntax-highlighting-core-current-base-20260605T165248Z`
Accepted base: `fadf77aa1b4dc0d53d0669b0c1e860bdb06c579c`

## Scope

This slice owns one bounded syntax-highlighting support cluster for Graphviz DOT
workflow snippets in WordPress import review packets. It does not render
graphs, execute Graphviz/dot, shell out to Pandoc, run Skylighting, or use an
external highlighter.

## Source Truth

Pandoc delegates code-block highlighting through Skylighting and passes
code-block classes, `startFrom`, `numberLines`, line anchors, and style options
through `Text.Pandoc.Highlighting`:

- `https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/Highlighting.hs`
- `https://raw.githubusercontent.com/jgm/skylighting/master/skylighting-core/xml/dot.xml`

The Skylighting DOT syntax definition exposes `dot` as the syntax name with
`.dot` extension coverage, Graphviz keywords, attributes, strings, comments,
numbers, identifiers, and edge/operator symbols. This PHP slice ports a bounded
token handoff, not the full KDE/Skylighting XML state machine.

## Behavior Added

- `dot`, `graphviz`, and `gv` aliases normalize into canonical `dot`
  highlighting.
- DOT snippets now tokenize graph keywords, common Graphviz attributes,
  constants such as `LR`/`box`, quoted labels and URLs, comments, node
  identifiers, directed and undirected edge operators, numbers, and delimiters.
- The WordPress syntax-highlighting fixture and handoff example now include a
  numbered DOT workflow graph with WordPress raw HTML style metadata.

## Evidence

Baseline focused test before the implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 714 assertions, 0 failures
```

Focused test after the implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 738 assertions, 0 failures
```

Example smoke after the implementation:

```text
php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test
syntax highlighting handoff self-test ok
```

Additional required checks:

```text
php -l lanes/pandoc/src/SyntaxHighlighter.php
No syntax errors detected in lanes/pandoc/src/SyntaxHighlighter.php

php -l lanes/pandoc/tests/SyntaxHighlighterTest.php
No syntax errors detected in lanes/pandoc/tests/SyntaxHighlighterTest.php

php -l lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php

php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " json ok\n"; }'
lanes/pandoc/lane-status.json json ok
lanes/pandoc/UPSTREAM_TEST_MANIFEST.json json ok

git diff --check -- lanes/pandoc
passed with no output
```

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused SyntaxHighlighter coverage: `714 -> 738` assertions.
- Focused PASS cases: `37 -> 38`.
- Lane `phpPass`: `1005 -> 1006`.
- Manifest mapped denominator: `1460 -> 1461`.

## Dependency Closure

No new support component is required. This slice reuses native PHP
`SyntaxHighlighter`, `MarkdownReader`, `AstNode`, and `WordPressBlockWriter`
support rows. Full upstream runner parity remains blocked on hydrating Pandoc
at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` and creating a Cabal plan for
`test-pandoc` and `test-pandoc-lua-engine`.

## Non-Overlap

This slice does not repeat accepted syntax-highlighting coverage for PHP, JSON,
YAML, HTML, SQL, Haskell, TeX, diff, Markdown, Ruby, Lua, TypeScript, JSX, R,
Python, C/C++, Dockerfile, Makefile, INI, TOML, Perl, Java, XML/XSLT, Bash,
CSS, Rust, Nix, SCSS/Sass, Go, PowerShell, token-title metadata, custom theme
JSON, or WordPress writer opt-in. It owns only bounded Graphviz DOT alias and
token handoff.

## Follow-Up

Keep full Skylighting XML state-machine parity, nested HTML-like DOT label
state, embedded-language highlighting, Graphviz renderer integration, SVG
output import policy, and writer-wide default highlighting policy as separate
bounded slices.
