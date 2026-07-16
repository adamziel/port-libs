# Pandoc Syntax Highlighting PowerShell Core Slice

Micro-slice: `pandoc-syntax-highlighting-core-current-base-20260605T162029Z`
Accepted base: `82510787197b5e33a19fbcf1cb8655979c1e6bf1`

## Scope

This slice owns one bounded syntax-highlighting support cluster for PowerShell
review snippets in WordPress migration/import packets. It does not shell out to
Pandoc, Skylighting, PowerShell, external highlighters, browser renderers,
JavaScript, online sanitizers, or online services.

## Source Truth

Pandoc delegates code-block highlighting through Skylighting at the pinned
Pandoc source surface. `Text.Pandoc.Highlighting` selects syntax definitions
from code-block classes, carries `startFrom`, `numberLines`, and line-anchor
format options, and uses Skylighting token classes for HTML output.

- `https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/Highlighting.hs`
- `https://raw.githubusercontent.com/jgm/skylighting/master/skylighting-core/xml/powershell.xml`

Skylighting's PowerShell XML syntax definition carries bounded categories used
here, including keywords, primitive types, cmdlets such as `Get-Content`,
`ConvertFrom-Json`, `ForEach-Object`, and `Set-Content`, scoped variables,
automatic variables, comment help fields, and parameters. This PHP slice ports
the reviewer-visible token handoff, not the full KDE XML state machine.

## Behavior Added

- `powershell`, `ps1`, `psm1`, `psd1`, `pwsh`, and `posh` aliases normalize
  into canonical `powershell` highlighting.
- PowerShell snippets now tokenize block/line comments, bracket attributes,
  primitive type annotations, variables including scoped variables, cmdlets,
  parameters, comparison/logical operators, strings, arrays, hashtables, and
  method calls.
- The WordPress syntax-highlighting fixture and handoff example now include a
  numbered PowerShell Windows import-review block with WordPress raw HTML style
  metadata.

## Evidence

Baseline focused test before the implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 681 assertions, 0 failures
```

Red-first unsupported-language probe before the implementation:

```text
php -r 'require "tools/bootstrap.php"; $h = new \PortLibs\Pandoc\SyntaxHighlighter(); $code = "# WordPress Windows import review\nparam([string]\$sourcePath)\nGet-Content -LiteralPath \$sourcePath | ConvertFrom-Json\n"; $result = $h->highlight($code, "ps1"); var_export([\PortLibs\Pandoc\SyntaxHighlighter::normalizeLanguage("ps1"), \PortLibs\Pandoc\SyntaxHighlighter::normalizeLanguage("powershell"), $result["language"], $result["diagnostics"]]); echo "\n";'
array (
  0 => NULL,
  1 => NULL,
  2 => '',
  3 =>
  array (
    0 =>
    array (
      'code' => 'unsupported-language',
      'message' => 'No bounded native syntax definition is available for \'ps1\'',
    ),
  ),
)
```

Focused test after the implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
1 test files, 714 assertions, 0 failures
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

- Focused SyntaxHighlighter coverage: `681 -> 714` assertions.
- Focused PASS cases: `36 -> 37`.
- Lane `phpPass`: `993 -> 994`.
- Manifest mapped denominator: `1448 -> 1449`.

## Dependency Closure

No new support component is required. This slice reuses native PHP
`SyntaxHighlighter`, `MarkdownReader`, `AstNode`, and `WordPressBlockWriter`
support rows. Full upstream runner parity remains gated on hydrated Pandoc and
Cabal runner setup, and no Haskell runner was executed.

## Non-Overlap

This slice does not repeat accepted syntax-highlighting coverage for PHP, JSON,
YAML, HTML, SQL, Haskell, TeX, diff, Markdown, Ruby, Lua, TypeScript, JSX, R,
Python, C/C++, Dockerfile, Makefile, INI, TOML, Perl, Java, XML/XSLT, Bash,
CSS, Rust, Nix, SCSS/Sass, Go, token-title metadata, custom theme JSON, or
WordPress writer opt-in. It owns only bounded PowerShell alias and token
handoff.

## Follow-Up

Full Skylighting XML state-machine parity, here-string edge cases,
parser-state-aware embedded language highlighting, writer-wide default
highlighting policy, comment-help field state, and full upstream runner
dependency planning remain separate bounded follow-up slices.

Root harness: not run - isolated micro-slice.
