# Pandoc Syntax Highlighting GraphQL Slice

Date: 2026-06-06 UTC
Lane: pandoc
Micro-slice: pandoc-syntax-highlighting-core-current-base-20260606T092021Z
Accepted base: 75e47bda11781d9e0c3af4331acfa9e1a02264e1

## Scope

This slice adds bounded native PHP syntax-highlighting support for GraphQL
review snippets. It maps `graphql`, `gql`, `graphqls`, `graphql-schema`, and
`graphql-query` aliases to a tokenizer and carries highlighted output through
Markdown fenced-code attributes and the WordPress syntax-highlighting handoff
example.

The tokenizer covers the conversion-relevant WPGraphQL review contract:

- operation and schema keywords including `query`, `fragment`, `on`, `type`,
  and `implements`
- variables, directives, arguments, aliases, built-in scalar datatypes, custom
  type names, comments, strings, numbers, and selection-set punctuation
- source wrappers, line numbering, style metadata, and WordPress block
  attributes already used by the syntax-highlighting handoff

## Source Truth

Pandoc syntax highlighting is driven by code-block language classes and emits
HTML spans/styles for highlighted source. Pandoc delegates grammar handling to
Skylighting; the bounded source contract here follows the Skylighting GraphQL
keyword surface for operation/schema keywords and scalar datatypes.

Skylighting source consulted:
`https://raw.githubusercontent.com/jgm/skylighting/master/skylighting-core/xml/graphql.xml`

No local hydrated Pandoc checkout was available in this isolated worktree or
the shared upstream cache. No Pandoc, Cabal build/test command, Haskell runner,
Skylighting runtime, GraphQL server, external highlighter, browser renderer,
JavaScript runtime, online sanitizer, online service, live provider test, or
live-service provider test was executed.

## Verification

Baseline before this slice:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 1161 assertions, 0 failures
```

Red-first focused run after adding GraphQL expectations:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 1179 assertions, 1 failures
```

The red-first failure showed a bounded expectation mismatch: a GraphQL field
without argument parentheses is tokenized as a variable field, not a function
call. The focused expectation was aligned to that contract.

Focused verification after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 1186 assertions, 0 failures
```

WordPress example smoke:

```text
php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test
syntax highlighting handoff self-test ok
```

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused SyntaxHighlighter coverage: 1161 -> 1186 assertions.
- Focused PHP PASS cases: +1.
- `lanes/pandoc/lane-status.json` `phpPass`: 1258 -> 1259.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped checks: 1702 -> 1703.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`SyntaxHighlighter`, `MarkdownReader`, `WordPressBlockWriter`, the existing
syntax-highlighting fixture, the WordPress handoff example, and lane-local
manifest/status machinery.

Full upstream runner parity remains gated on hydrating a local Pandoc checkout
at 0640c4c9859aa5a3ede082c190fcd5883c24ac83 with `cabal.project`,
`pandoc.cabal`, and `pandoc-lua-engine/pandoc-lua-engine.cabal` present before
any bounded non-mutating Cabal/Haskell runner plan can be marked ready.

## Non-Overlap

This slice does not repeat accepted syntax-highlighting coverage for standalone
HTML attributes, embedded CSS/JavaScript/PHP islands, CSS, Rust, Nix, SCSS/Sass,
Go, PowerShell, DOT, JavaScript, C#, TSX, CMake, Nginx, Twig,
Mustache/Handlebars, Mermaid, SQL/PostgreSQL, Apache, RST, Haskell, TeX/LaTeX,
diff/patch, Markdown-family, Ruby/Rake, Lua/Pandoc-Lua, TypeScript, R, Python,
C/C++, Dockerfile/Containerfile, Makefile, INI/config, TOML, Perl, Java,
XML/XSLT, Bash heredoc state, PHP heredoc/nowdoc, or Pandoc JSON theme support.

It owns only bounded GraphQL/WPGraphQL alias and token handoff.

## Follow-Up

Keep full Skylighting XML parity, richer GraphQL block-string/source-location
handling, parser-state-aware GraphQL validation, embedded language delegation,
complete theme coverage, and upstream Haskell runner comparison as separate
bounded slices.
