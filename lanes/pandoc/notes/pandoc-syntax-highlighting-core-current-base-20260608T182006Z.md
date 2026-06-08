# Pandoc syntax highlighting current-base Vue custom blocks

Slice: `pandoc-syntax-highlighting-core-current-base-20260608T182006Z`

Base accepted HEAD: `74e2e1d508ba035b714146936835879271d84645`

## Behavior

This slice adds bounded native Vue SFC raw-text handling for custom metadata blocks:

- `<i18n>` bodies delegate to existing JSON, JSONC, or YAML tokenizers from the `lang` attribute, defaulting to JSON.
- `<route>` bodies delegate to existing YAML, JSONC, or JSON tokenizers from the `lang` attribute, defaulting to JSON.
- `<docs>` bodies delegate to existing Markdown, YAML, JSON, or JSONC tokenizers from the `lang` attribute, defaulting to Markdown.

The scanner remains Vue-only: ordinary HTML raw-text scanning still recognizes only `script` and `style`.

## Source Truth

The bounded contract comes from the lane's existing syntax-highlighting support-library ownership: fixture-backed code language alias, style, and token handoff under `lanes/pandoc/**`. The concrete gap was observed locally in the accepted-base Vue highlighter: `script` and `style` bodies already delegated to embedded tokenizers, while Vue custom metadata blocks were scanned as plain Vue text.

Red-first probe before the patch:

```bash
php -r 'require "tools/bootstrap.php"; $h = new \PortLibs\Pandoc\SyntaxHighlighter(); $r = $h->highlight("<i18n>{\"en\":{\"title\":\"Import\"}}</i18n>\n<route lang=\"yaml\">meta:\n  requiresReview: true\n</route>", "vue"); echo $r["html"], "\n";'
```

The probe rendered the custom-block bodies without JSON/YAML key or boolean token classes. The final fixture-backed test now proves JSON object keys and booleans, YAML keys and booleans, and Markdown headings/task links inside Vue custom blocks.

## Verification

Focused baseline before the patch:

```bash
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
# 1 test files, 1751 assertions, 0 failures
```

Focused verification after the patch:

```bash
php tools/run-tests.php lanes/pandoc/tests/SyntaxHighlighterTest.php
# 1 test files, 1774 assertions, 0 failures
```

Updated example smoke:

```bash
php lanes/pandoc/examples/wordpress-syntax-highlighting-handoff.php --self-test
# syntax highlighting handoff self-test ok
```

## Dependency Closure

No new native PHP support component is needed. The patch reuses the existing `SyntaxHighlighter` Vue scanner, JSON/JSONC tokenizer, YAML tokenizer, Markdown tokenizer, and WordPress HTML block writer. No Pandoc, Cabal/Haskell runner, Skylighting executable, external highlighter, browser renderer, JavaScript runtime, online service, live provider test, or live-service provider test was executed.

## Non-overlap

This avoids the recent legacy DOC/CFB, ODF/OpenDocument, charset, XML/HTML5 DOM, math/TeX, table-geometry, archive, DOCX, ZIP/OPC, and existing syntax-highlighting language slices. It does not repeat the accepted Vue SFC template/script/style handoff; it only adds custom metadata block body token delegation.

## Next Task

A useful follow-up would be another non-overlapping syntax fixture such as Vue custom-block language variants beyond JSON/YAML/Markdown, a bounded remaining Skylighting-compatible language alias, or additional WordPress-visible token metadata. Keep it native PHP and external-tool free.
