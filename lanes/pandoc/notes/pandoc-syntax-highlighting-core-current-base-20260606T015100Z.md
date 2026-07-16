# Pandoc Syntax Highlighting TSX Alias/Token Slice

Date: 2026-06-06 UTC

Base: `990b499dc4e79bebbdeb8a6bdf28afd6ba5b9674`

Micro-slice: `pandoc-syntax-highlighting-core-current-base-20260606T015100Z`

## Source Truth

- Used the lane's accepted static syntax-highlighting inventory and prior notes as source truth.
- Prior JSX syntax notes map Pandoc/Skylighting React language handling and left TSX as a separate follow-up. The TypeScript syntax note also keeps TSX/JSX grammar parity as distinct future work.
- The hydrated Pandoc checkout was not available in this isolated worktree/source-truth pass, so no upstream Haskell runner, Pandoc executable, Cabal build, Skylighting runtime, TypeScript compiler, Node tool, browser renderer, JavaScript execution, online sanitizer, online service, or live provider test was executed.

## Behavior Added

- `SyntaxHighlighter::normalizeLanguage()` now maps `tsx`, `language-tsx`, `typescript-react`, and `typescriptreact` to the native `tsx` tokenizer.
- Native TSX highlighting now covers bounded TypeScript React review snippets:
  - typed imports and `import type`
  - type aliases and primitive datatypes
  - generic type arguments
  - JSX component tags and attributes
  - template strings
  - nullish coalescing
  - typed callbacks
  - numbered source-line handoff
  - WordPress raw HTML block style metadata
- The WordPress syntax-highlighting fixture and example now include a Gutenberg typed component code block.

## Red-First Evidence

Before implementation:

```sh
php -r 'require "tools/bootstrap.php"; $h = new \PortLibs\Pandoc\SyntaxHighlighter(); $code = "type Props = { title?: string };\nexport const Edit = () => <PanelBody title=\"Import\" />;"; $result = $h->highlight($code, "tsx"); var_export([\PortLibs\Pandoc\SyntaxHighlighter::normalizeLanguage("tsx"), $result["language"], $result["diagnostics"]]); echo "\n";'
```

Result: `normalizeLanguage("tsx")` returned `NULL`, the highlighted language was empty, and diagnostics contained `unsupported-language`.

Baseline focused test before the new assertions:

```sh
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
```

Result: `1 test files, 947 assertions, 0 failures`.

## Verification

```sh
php -l lanes/pandoc/src/SyntaxHighlighter.php
php -l lanes/pandoc/tests/SyntaxHighlighterTest.php
php -l lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php
```

Result: no syntax errors detected.

```sh
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
```

Result: `1 test files, 977 assertions, 0 failures`.

```sh
php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test
```

Result: `syntax highlighting handoff self-test ok`.

```sh
git diff --check -- lanes/pandoc
```

Result: passed with no output.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This slice does not repeat accepted syntax-highlighting slices for CSS, Rust, Nix, SCSS, Go, PowerShell, DOT, JavaScript, C#, SQL, PostgreSQL, Apache, Lua long-bracket strings, PHP heredoc/nowdoc, reStructuredText, TypeScript, or JSX. It owns only bounded TSX/TypeScript React alias and token handoff.

## Dependency Closure

No new support component is needed. The implementation reuses native PHP `SyntaxHighlighter`, `MarkdownReader`, `AstNode`, the WordPress block writer handoff, and the existing syntax fixture/example path.

Follow-up remains bounded: full Skylighting XML syntax-definition parity, richer TSX type-operator edge cases, TSX fragments, embedded CSS/template-language spans, and full source-location diagnostics.
