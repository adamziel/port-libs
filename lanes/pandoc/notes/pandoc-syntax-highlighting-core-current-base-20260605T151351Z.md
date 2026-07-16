# Pandoc Syntax Highlighting SCSS/Sass Core Slice

Micro-slice: `pandoc-syntax-highlighting-core-current-base-20260605T151351Z`
Accepted base: `5b7b0019c317588ee2abf0acee527fa17fea0987`

## Scope

This slice owns one bounded syntax-highlighting support cluster for WordPress
block-theme SCSS/Sass review snippets. It does not shell out to Pandoc,
Skylighting, Sass, browser renderers, JavaScript, online sanitizers, or online
services.

## Source Truth

Pandoc delegates syntax highlighting through Skylighting at the pinned Pandoc
source surface, and Skylighting carries SCSS/Sass XML syntax definitions:

- `https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/Highlighting.hs`
- `https://raw.githubusercontent.com/jgm/skylighting/master/skylighting-core/xml/scss.xml`
- `https://raw.githubusercontent.com/jgm/skylighting/master/skylighting-core/xml/sass.xml`

The PHP port now maps that contract into a bounded native token handoff rather
than a full Skylighting runtime.

## Behavior Added

- `scss`, `sass`, and `language-scss` aliases normalize into supported
  languages.
- SCSS/Sass snippets now tokenize Sass variables, map-like values,
  mixin/include at-rules, interpolation, parent selectors, color literals,
  percent units, comments, strings, and common functions.
- The WordPress syntax-highlighting fixture and handoff example now include an
  SCSS review block with numbered-source metadata and WordPress raw HTML style
  metadata.

## Evidence

Baseline focused test before the implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 626 assertions, 0 failures
```

Red-first unsupported-language probe before the implementation:

```text
php -r 'require "tools/bootstrap.php"; $h = new \PortLibs\Pandoc\SyntaxHighlighter(); $code = "\$accent: #005cc5;\n.wp-block { color: \$accent; }"; $result = $h->highlight($code, "scss"); var_export([\PortLibs\Pandoc\SyntaxHighlighter::normalizeLanguage("scss"), $result["language"], $result["diagnostics"]]); echo "\n";'
array ( 0 => NULL, 1 => '', 2 => array ( 0 => array ( 'severity' => 'warning', 'message' => 'unsupported-language', 'language' => 'scss', ), ), )
```

Focused test after the implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 652 assertions, 0 failures
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

## Status Delta

- Focused SyntaxHighlighter coverage: `626 -> 652` assertions.
- Focused PASS cases: `34 -> 35`.
- Lane `phpPass`: `967 -> 968`.
- Manifest mapped denominator: `1422 -> 1423`.

## Dependency Closure

No new support component is required. This slice reuses the existing native PHP
`SyntaxHighlighter`, `MarkdownReader`, `AstNode`, and `WordPressBlockWriter`
support rows. Full upstream runner parity remains blocked on hydrated Pandoc
and Cabal runner setup, and no Haskell runner was executed.

## Non-Overlap

This slice does not repeat existing CSS, Rust, Nix, TOML, XML, HTML, SQL,
Haskell, TeX, Markdown, Ruby, Lua, TypeScript, Python, C/C++, Dockerfile,
Makefile, INI, Perl, Java, or custom-theme syntax-highlighting handoffs. It
owns only bounded SCSS/Sass alias and token handoff.

## Follow-Up

State-aware nested Sass interpolation, full SCSS/Sass parser parity,
source-map-aware Sass diagnostics, richer CSS module highlighting,
embedded-language highlighting, and full upstream runner dependency planning
remain separate bounded follow-up slices.

Root harness: not run - isolated micro-slice.
