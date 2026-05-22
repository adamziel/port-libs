# LightningCSS WordPress Scenario

Shared-hosting CSS transforms for themes, blocks, Playground, and plugin build-free delivery.

## Current Native Slice

Native CSS comment/whitespace minifier, declaration block parser, value-level color keyword shortening, math-function operator spacing, and a small stylesheet parser that distinguishes style rules, at-rule statements/blocks, declarations, selectors, and nested WordPress rules.

`examples/wordpress-stylesheet-parser.php` parses the block-theme CSS fixture and reports the top-level rule count, first selector list, and declaration count. This gives shared-hosting and Playground tooling a native AST boundary before adding prefixing, bundling, or transformer semantics.

## Next Task

Expand parser coverage toward media query values, selector specificity, and CSSOM-style declaration access before adding prefixing or transformer semantics.
