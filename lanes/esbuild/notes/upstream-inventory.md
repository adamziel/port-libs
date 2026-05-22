# esbuild Upstream Inventory

- Upstream: `evanw/esbuild`
- Commit: `6a794dff68e6a43539f6da671e3080efdf11ca70`
- License: MIT
- Inventory method: shallow blob-filtered clone in `.upstream-cache/esbuild` with no checkout; counted paths with `git ls-tree` and hydrated only targeted metadata/test files through `git show`.

## Counted Denominator

- 349 repository paths.
- 63 test-related paths.
- 33 Go `_test.go` files.
- 1,391 top-level Go `func Test*` functions.
- 9,950 Go helper invocation subcases such as lexer/parser/printer expectations.
- 1,059 bundler `expectBundled` cases.
- 14 bundler snapshot files.
- 12 Node/browser test scripts.
- 331 `scripts/js-api-tests.js` async API cases.
- 97 `scripts/plugin-tests.js` async plugin cases.
- 748 `scripts/end-to-end-tests.js` CLI cases.

The lane denominator is the 2,567 counted upstream test entry points made from 1,391 Go tests, 331 JS API cases, 97 plugin cases, and 748 end-to-end CLI cases. The larger helper and snapshot counts are tracked as subcase coverage targets, not runner pass parity.

## Runner Evidence

The original inventory cache remains a blob-filtered/no-checkout cache with deletion status, so the runner was executed from a separate detached worktree instead of destructively restoring that cache:

```text
git worktree add --detach ../esbuild-runner HEAD
```

The first runner pass installed the missing Go/Node toolchain through Nix and let the Makefile install its locked `scripts/node_modules` dependencies:

```text
nix-shell -p go nodejs --run 'go version && node -v && npm -v && make test'
```

That passed, but `scripts/end-to-end-tests.js` skipped HTTPS cases because `openssl` was not on PATH. The runner was then rerun with OpenSSL available:

```text
nix-shell -p go nodejs openssl --run 'go version && node -v && openssl version && make test'
```

Result: passed. The run covered Go tests and vet plus the Node source-map, end-to-end, JS API, plugin, register, node-unref, and decorator targets. Environment evidence:

- Go: `go1.24.10`
- Node: `v22.20.0`
- npm: `10.9.3`
- OpenSSL: `OpenSSL 3.4.3`

Deno is also available through Nix for the later release-extra lane:

```text
nix-shell -p deno --run 'deno --version'
deno 2.2.12
```

`make test-all` was not executed in this slice. It adds Deno tests, WASM node/browser tests, TypeScript typechecks, and Yarn PnP coverage and would also build upstream's custom Go 1.26.1 compiler plus browser/WASM artifacts. Treat the current evidence as core upstream `make test` parity, not release-extra coverage.

## Current Native Mapping

- `TestComment`: unterminated block comments now raise a native PHP lexer error.
- `TestHashbang`: file-start hashbangs produce a `hashbang` token.
- `TestNumericLiteral`: binary, octal, hexadecimal-with-separators, decimal-leading-dot, exponent, and trailing-dot number literals produce `number` tokens with numeric values.
- `TestImport`/export forms: selected static imports, dynamic direct-string imports, named/default/namespace imports, and named/star/default exports are analyzed.
- `TestImportAssertions`: selected static import/export `assert { type: "json" }` clauses are preserved as module metadata.
- `TestImportAttributes`: selected static import/export `with { ... }` clauses and direct-string dynamic import options are preserved, with duplicate keys and non-string values rejected.
- `TestES6Syntax`/`TestImportMeta*`: `import.meta` is tracked as an ESM marker, and `import.meta.url`/`import.meta.path` property reads are preserved as native metadata.
- `TestNew`/`TestCall`: `new URL("path", import.meta.url)` references are mapped for plain asset references, `new Worker(new URL(...))`, and `import(new URL(...))`.
- Dynamic imports with expression arguments are accepted without being counted as direct string import records, while malformed `import()` and `import(...a)` calls are rejected.
- `TestTSImportEquals`/`TestTSExportEquals`: `import x = require("...")`, qualified `import x = foo.bar`, and `export = value` are classified as TypeScript/CommonJS forms and do not mark a file as ESM by themselves.
- `TestTSTypeOnlyImport`: top-level `import type`, mixed `import { value, type Type }`, runtime `import type from "bar"` ambiguity, and malformed combined type default imports are distinguished.
- `TestTSTypeOnlyExport`: `export type { ... } from`, mixed `export { value, type Type }`, type-only star exports, and invalid local `export type { default }` are distinguished.
- `TestTSNamespaceExports`: namespace declarations, `export namespace`, exported namespace members, declared namespace exports, nested namespace names, and namespace-local import-equals aliases are recorded separately from top-level module exports.
- `TestTSImport`: selected TypeScript import pruning behavior is mapped as analyzer metadata: unused value imports are omitted, live default/named/namespace specifiers are retained, and imports referenced only from dead `if (false)` control flow are downgraded to side-effect-only imports.
- `TestTSImportEquals`: selected fixed-point pruning behavior is mapped: unused qualified-reference aliases are removed, `require(...)` aliases are retained for side effects, and chains such as `a -> b -> c` are retained when a later live use depends on them.
- `TestTSImportEqualsInNamespace`: selected printer behavior is mapped by a native namespace lowerer for namespace-local import-equals elision, runtime `const` aliases, exported namespace property assignments, exported alias reference rewriting, and invalid nested/import-form rejection.
- `TestTSNamespace`/`TestTSNamespaceExports`: selected printer behavior is mapped by the native namespace lowerer for exported `var`/`let`/`const` namespace property assignments, initialized multi-declarator exports, later exported value reference rewriting, and namespace parameter renaming when an exported value shares the namespace name.
- `TestTSNamespaceExports`: selected printer behavior is mapped by the native namespace lowerer for exported `function` and `class` declarations inside namespaces, including local declaration emission, namespace property assignment, and namespace parameter renaming when the export shares the namespace name.
- `TestTSNamespaceExports`: selected nested namespace printer behavior is mapped for exported nested namespace property initializers, non-exported local nested namespaces, exported namespace functions, namespace-local runtime enums, and simple `export declare const` variable reference rewrites.
- `TestTSNamespace`: dot nested namespace syntax is mapped for `namespace foo.bar { ... }`, `module foo.bar { ... }`, and namespace/module alias combinations, emitting nested namespace IIFEs with `bar = foo.bar || (foo.bar = {})` initialization. Analyzer metadata now records the parent namespace plus exported child namespace instead of flattening the qualified name.
- `TestTSNamespaceExports`: a bounded declared binding-pattern case is mapped for `export declare let [[L2 = x, { [y]: L3 }]]`, recording `L2` and `L3` as declared namespace members and rewriting later value references without treating default-initializer or computed-key identifiers as exports.
- `TestTSExportEquals`: selected top-level printer behavior is mapped by a native module lowerer that lowers `export = value` to `module.exports = value` and appends the assignment after ordinary statements.
- `TestTSImportEquals`: selected top-level printer behavior is mapped by the module lowerer for `import x = require("...")`, `import x = foo.bar`, and `export import x = ...` to runtime `const`/`export const` declarations, with malformed targets rejected.
- `TestTSTypes`: selected printer behavior is mapped by the module lowerer for variable annotations, function parameter/return annotations, arrow callback annotations, `as` casts, `satisfies` checks, and type-only imports.
- `TestTSEnum`: selected printer behavior is mapped by the module lowerer for normal and exported runtime enum objects, including numeric auto-increment, string literal members, `void 0` after non-numeric members, upstream-style missing separator errors, use-before-declaration direct member substitution, and same-file dot/bracket enum member inlining while leaving optional enum access un-inlined.
- Same-file non-exported `const enum` behavior is mapped for a bounded slice: the declaration is erased and direct dot/bracket member accesses are substituted with numeric comments.
- WordPress fixtures: a block view script using `@wordpress/dom-ready`, a `block.json` import with `with { type: "json" }`, and relative stylesheet/worker `new URL(..., import.meta.url)` references tokenizes and analyzes without Node/npm. A TypeScript block fixture additionally separates pruned runtime `@wordpress/dom-ready`, `./block.json`, and legacy `wp.blocks` dependencies from erased `@wordpress/blocks`/`@wordpress/element` type imports plus namespace-backed block registration metadata. WordPress namespace-lowering scenarios map `export import blocks = wp.blocks`, exported namespace block settings, exported registration functions/classes, nested exported namespace enum settings, and a dot-qualified `PortLibs.CardBlock` runtime namespace to namespace property assignments/IIFEs with later reference rewrites, a CommonJS-style WordPress block runtime maps `export = registerBlock` to `module.exports = registerBlock`, a typed block edit callback fixture erases `BlockEditProps`, `WPElement`, and `satisfies BlockConfiguration` syntax, a runtime enum block configuration fixture lowers a `DisplayMode` enum, and a const enum block configuration fixture erases non-exported display-mode enum declarations while preserving registration runtime code without invoking Node.
