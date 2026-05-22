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

## Runner Status

The full upstream runner was not executed in this worker slice. `make test` runs Go unit tests and vet plus Node-based source-map, end-to-end, JS API, plugin, register, node-unref, and decorator tests. `make test-all` adds Deno, WASM browser/node, typecheck, and Yarn PnP coverage. This environment has no `go` binary and no `deno` binary, and running the full target would also require hydrating the checkout and installing Node dependencies under `scripts/node_modules`. The current inventory is static upstream coverage, not upstream runner parity.

## Current Native Mapping

- `TestComment`: unterminated block comments now raise a native PHP lexer error.
- `TestHashbang`: file-start hashbangs produce a `hashbang` token.
- `TestNumericLiteral`: binary, octal, hexadecimal-with-separators, decimal-leading-dot, exponent, and trailing-dot number literals produce `number` tokens with numeric values.
- WordPress fixture: a block view script using `@wordpress/dom-ready` tokenizes without Node/npm.
