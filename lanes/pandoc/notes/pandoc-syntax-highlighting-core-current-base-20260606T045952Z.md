# Pandoc Syntax Highlighting Nginx Slice

Date: 2026-06-06 UTC
Lane: pandoc
Micro-slice: pandoc-syntax-highlighting-core-current-base-20260606T045952Z
Accepted base: d0a134019583244d26aaca02c539b68c5c2f018e

## Scope

This slice adds bounded native PHP syntax-highlighting support for Nginx review snippets. It maps `nginx`, `nginxconf`, `nginx-conf`, `nginx-config`, and `language-nginx` aliases to a tokenizer and carries highlighted HTML through Markdown fenced-code attributes and the WordPress syntax-highlighting handoff example.

The tokenizer covers the conversion-relevant contract for WordPress hosting review blocks:

- server, location, listen, server_name, root, try_files, fastcgi, add_header, rewrite, and related directive tokens
- line comments, quoted strings, socket paths, absolute paths, named locations, regex location selectors, numeric port/unit tokens, constants, variables, and operators
- source wrappers, line numbering, style metadata, and WordPress block attributes already used by the existing syntax-highlighting handoff

## Source Truth

Pandoc's highlighting path delegates language definitions to Skylighting. The bounded format contract here follows the Skylighting/Pandoc handoff shape for Nginx configuration code blocks and ports only the PHP support-library behavior needed by the local Markdown/WordPress syntax-highlighting lane.

No local hydrated Pandoc checkout was available in this isolated worktree or the shared upstream cache, and no external Nginx parser/highlighter was executed. No Pandoc, Cabal build, Haskell runner, Skylighting runner, Nginx, PHP-FPM, external highlighter, browser renderer, JavaScript, online sanitizer, online service, or live provider test was executed.

## Red-First Evidence

Before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 1005 assertions, 0 failures
```

Unsupported-language probe:

```text
normalizeLanguage(nginx) => NULL
language => ''
diagnostic => 'unsupported-language'
```

After adding the focused fixture/test, the first implementation failed:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 1027 assertions, 1 failures
```

The failure showed `location ~ \.php$` left `~` unclassified and split the regex selector. The scanner now classifies Nginx `~`/`~*` as operators and keeps escaped dot regex selectors as one string token.

## Verification

Focused test after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 1037 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test
syntax highlighting handoff self-test ok
```

Syntax and diff checks were run before handoff:

```text
php -l lanes/pandoc/src/SyntaxHighlighter.php
php -l lanes/pandoc/tests/SyntaxHighlighterTest.php
php -l lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php
git diff --check -- lanes/pandoc
```

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused SyntaxHighlighter assertions: 1005 -> 1037.
- Focused PHP PASS cases: +1.
- `lanes/pandoc/lane-status.json` `phpPass`: 1198 -> 1199.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped checks: 1644 -> 1645.

## Non-Overlap

This slice does not repeat existing syntax-highlighting coverage for CSS, Rust, Nix, SCSS/Sass, Go, PowerShell, DOT, JavaScript, C#, SQL/Postgres, Apache/htaccess, Lua long brackets, PHP heredoc, RST, TSX, or CMake. It owns only bounded Nginx/nginxconf server and PHP-FPM reverse-proxy review-packet behavior.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP `SyntaxHighlighter`, `MarkdownReader`, and `WordPressBlockWriter` handoff paths.

The upstream-runner blocker remains unchanged: full upstream Pandoc runner parity still needs a hydrated Pandoc checkout at 0640c4c9859aa5a3ede082c190fcd5883c24ac83 plus Cabal project/package files and Haskell Tasty executable builds for `test-pandoc` and `test-pandoc-lua-engine`.

## Follow-Up

Keep full Skylighting XML parity, broader Nginx directive maps, richer `map`/`upstream` context handling, embedded language highlighting in quoted strings, complete theme coverage, and upstream Haskell runner comparison as separate bounded syntax-highlighting slices.
