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
- WordPress fixture: a block view script using `@wordpress/dom-ready` and a `block.json` import with `with { type: "json" }` tokenizes and analyzes without Node/npm.
