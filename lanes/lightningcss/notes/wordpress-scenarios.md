# LightningCSS WordPress Scenario

Shared-hosting CSS transforms for themes, blocks, Playground, and plugin build-free delivery.

## Current Native Slice

Native CSS comment/whitespace minifier, declaration block parser, value-level color keyword shortening, math-function operator spacing, a small stylesheet parser that distinguishes style rules, at-rule statements/blocks, declarations, selectors, and nested WordPress rules, selector specificity packing/comparison for override ordering, and media query range/feature/list normalization.

`examples/wordpress-stylesheet-parser.php` parses the block-theme CSS fixture and reports the top-level rule count, first selector list, and declaration count. The specificity helper covers WordPress override selectors such as block-button hover styles and ID-scoped navigation rules. The media query helper normalizes responsive block-theme breakpoints such as min/max width aliases, comparison ranges, hover/update features, ratios, and simple same-unit calc values. This gives shared-hosting and Playground tooling a native AST boundary before adding prefixing, bundling, or transformer semantics.

## Next Task

Expand parser coverage toward broader shorthand CSSOM families, background/flex CSSOM access, custom media, prefixing, or transformer semantics.
