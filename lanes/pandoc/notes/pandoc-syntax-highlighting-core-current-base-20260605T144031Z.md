# Pandoc Syntax Highlighting Nix Core Slice

## Scope

- Micro-slice: `pandoc-syntax-highlighting-core-current-base-20260605T144031Z`
- Accepted base: `9bea7b4c06e1f594835627b0cfa11df5c9346166`
- Ownership: bounded syntax-highlighting language alias/style/token handoff under `lanes/pandoc/**`.

This slice adds native PHP support for Nix code-block highlighting in the existing
`SyntaxHighlighter` component. It maps `nix`, `nix-expr`, and `nix-shell` aliases
and preserves bounded Nix deployment-expression tokens used by WordPress import
review packets: comments, `import`, angle paths, `let`/`in`/`inherit`/conditional
keywords, constants, attributes, relative paths, indented strings, numbered source
wrappers, and raw HTML style metadata.

The implementation intentionally does not attempt full KDE Skylighting XML syntax
definition parity, nested interpolation state, Nix evaluation, or external
highlighter behavior.

## Source Truth And Non-Overlap

- Source truth for this worker is the lane contract for
  `pandoc-syntax-highlighting-core-*`: fixture-backed code language alias/style/token
  handoff.
- Existing accepted syntax-highlighting clusters avoided: CSS at-rule/selector
  handoff and Rust/rs alias/token handoff.
- No Pandoc, Cabal, Haskell test binary, Skylighting runtime, Nix command,
  browser renderer, JavaScript, online sanitizer, or online service was executed.

## Verification

Baseline before the implementation:

```text
$ php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 600 assertions, 0 failures
```

Red-first probe before the implementation:

```text
$ php -r 'require "tools/bootstrap.php"; $h = new \PortLibs\Pandoc\SyntaxHighlighter(); $result = $h->highlight("{ pkgs ? import <nixpkgs> {} }: pkgs.writeText \"review\" \"ok\"", "nix"); var_export([\PortLibs\Pandoc\SyntaxHighlighter::normalizeLanguage("nix"), $result["language"], $result["diagnostics"]]); echo "\n";'
array (
  0 => NULL,
  1 => '',
  2 =>
  array (
    0 =>
    array (
      'type' => 'unsupported-language',
      'message' => 'Language is not supported by the bounded native highlighter.',
      'language' => 'nix',
    ),
  ),
)
```

Focused verification after the implementation:

```text
$ php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
34 PASS
1 test files, 626 assertions, 0 failures
```

Example smoke:

```text
$ php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test
syntax highlighting handoff self-test ok
```

Lint and metadata checks:

```text
$ php -l lanes/pandoc/src/SyntaxHighlighter.php
No syntax errors detected in lanes/pandoc/src/SyntaxHighlighter.php

$ php -l lanes/pandoc/tests/SyntaxHighlighterTest.php
No syntax errors detected in lanes/pandoc/tests/SyntaxHighlighterTest.php

$ php -l lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php

$ php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " json ok\n"; }'
lanes/pandoc/lane-status.json json ok
lanes/pandoc/UPSTREAM_TEST_MANIFEST.json json ok

$ git diff --check -- lanes/pandoc
```

`git diff --check -- lanes/pandoc` completed with no output.

Root harness status: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `949` -> `950`.
- Focused `SyntaxHighlighterTest` coverage: `600` -> `626` assertions.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1404` -> `1405`.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
`SyntaxHighlighter` support component. Full upstream Pandoc runner parity remains
blocked on hydrating and building upstream `test-pandoc` / `test-pandoc-lua-engine`
from the pinned Pandoc checkout; that blocker is unchanged by this support-library
slice.

## Follow-Up

Keep these as separate bounded slices: full Skylighting definition parity,
state-aware nested Nix string/interpolation parsing, Nix identifier apostrophe
coverage, and writer-wide default highlighting policy.
