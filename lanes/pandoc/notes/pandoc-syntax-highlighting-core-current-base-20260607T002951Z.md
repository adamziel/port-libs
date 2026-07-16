# Pandoc Syntax Highlighting HCL/Terraform Slice

Date: 2026-06-07 UTC
Lane: pandoc
Micro-slice: pandoc-syntax-highlighting-core-current-base-20260607T002951Z
Accepted base: 3a8058f7395669b2624b4a95a60e0fcfd8045b07

## Scope

This slice adds one bounded native PHP syntax-highlighting support cluster for
HCL/Terraform review snippets. It maps `hcl`, `hcl2`, `terraform`, `tf`, and
`tfvars` code-block aliases to canonical `hcl` highlighting and carries the
highlighted HTML through Markdown fenced-code attributes and the WordPress
syntax-highlighting handoff example.

The tokenizer covers the conversion-relevant review contract for Terraform
infrastructure snippets: block keywords, quoted labels, attributes, string and
heredoc values, booleans/null, numeric values, Terraform datatype names,
common functions, dotted references such as `var.source_id` and
`aws_s3_bucket.media.bucket`, operators, numbered source wrappers, and
WordPress raw HTML style metadata.

## Source Truth

Pandoc delegates code-block highlighting through Skylighting and preserves
language lookup, token classes, source wrappers, line numbering, and style
metadata in highlighted HTML. The lane has no hydrated local Pandoc or
Skylighting checkout available for this isolated worktree, so this slice uses
the accepted static syntax-highlighting inventory and prior bounded
Skylighting-style language handoffs as source truth.

No Pandoc, Cabal build, Haskell runner, Skylighting runner, Terraform
executable, external highlighter, browser renderer, JavaScript runtime, online
sanitizer, online service, live provider test, or live-service provider test
was executed.

## Evidence

Baseline before this slice:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 1260 assertions, 0 failures
```

Final focused run:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 1295 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test
syntax highlighting handoff self-test ok
```

Syntax and diff checks:

```text
php -l lanes/pandoc/src/SyntaxHighlighter.php
php -l lanes/pandoc/tests/SyntaxHighlighterTest.php
php -l lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php
php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); } echo "pandoc json ok\n";'
git diff --check -- lanes/pandoc
```

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused SyntaxHighlighter assertions: 1260 -> 1295.
- Focused PHP PASS cases: +1.
- `lanes/pandoc/lane-status.json` `phpPass`: 1421 -> 1422.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped checks: 1835 -> 1836.

## Non-Overlap

This slice does not repeat accepted syntax-highlighting coverage for PHP,
JSON, YAML, CSS, Rust, Nix, SCSS/Sass, Go, PowerShell, DOT, JavaScript, C#,
SQL/Postgres, Apache/htaccess, Lua long brackets, PHP heredoc/PHPDoc, RST,
TSX, CMake, Nginx, Twig, Handlebars/Mustache, Mermaid, GraphQL, HTML embedded
CSS/JavaScript/PHP islands, or AsciiDoc. It owns only bounded HCL/Terraform
alias and token handoff behavior.

## Dependency Closure

No new support component is needed. The implementation reuses native PHP
`SyntaxHighlighter`, `MarkdownReader`, `AstNode`, `WordPressBlockWriter`, and
the existing syntax fixture/example path.

The upstream-runner blocker remains unchanged: full upstream Pandoc runner
parity still needs a hydrated Pandoc checkout at
0640c4c9859aa5a3ede082c190fcd5883c24ac83 plus Cabal project/package files and
Haskell Tasty executable builds for `test-pandoc` and
`test-pandoc-lua-engine`.

## Follow-Up

Keep full Skylighting XML parity, broader Terraform expression parsing,
provider-specific block metadata, interpolation state inside quoted strings,
formatter-wide default highlighting policy, and upstream Haskell runner
comparison as separate bounded syntax-highlighting slices.
