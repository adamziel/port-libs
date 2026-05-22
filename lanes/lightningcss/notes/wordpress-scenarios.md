# LightningCSS WordPress Scenario

Shared-hosting CSS transforms for themes, blocks, Playground, and plugin build-free delivery.

## Current Native Slice

Native CSS comment/whitespace minifier, declaration block parser, value-level color keyword shortening, math-function operator spacing, a small stylesheet parser that distinguishes style rules, at-rule statements/blocks, declarations, selectors, and nested WordPress rules, plus selector specificity packing/comparison for override ordering.

`examples/wordpress-stylesheet-parser.php` parses the block-theme CSS fixture and reports the top-level rule count, first selector list, and declaration count. The specificity helper covers WordPress override selectors such as block-button hover styles and ID-scoped navigation rules. This gives shared-hosting and Playground tooling a native AST boundary before adding prefixing, bundling, or transformer semantics.

## Next Task

Expand parser coverage toward media query values, broader shorthand CSSOM families, background/flex CSSOM access, or prefixing and transformer semantics.
