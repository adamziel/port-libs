# esbuild async generator object-property slice

- Date: 2026-05-23T03:34:08Z
- Lane: `lanes/esbuild`
- Scope: unsupported-target async-generator runtime lowering for nested object-literal property values.

## Change Summary

- Extended the native TypeScript module lowerer nested-expression gate so `stream: async function* (...) { ... }` object-literal property values lower through the existing `__asyncGenerator` runtime helper path.
- Added focused upstream-shaped coverage for plain object properties, computed object properties, and object-literal callback arguments.
- Extended the WordPress async-generator registry fixture with a `previewStreamMap` keyed by `metadata.name`; the property-value stream uses `await using` and lowers without Node/npm.
- Did not broaden await-expression parsing in this slice. Multiple async-generator expressions inside one statement remain a future parser/printer slice.

## Verification

- `php <<'PHP' ... lanes/esbuild/tests/*Test.php ... PHP`: 4 test files, 1085 assertions, 0 failures.
- `php lanes/esbuild/examples/wordpress-asset-preflight.php`: passed; async-generator registry helper bytes reported as 5298.
- `php -r 'foreach (["lanes/esbuild/UPSTREAM_TEST_MANIFEST.json", "lanes/esbuild/lane-status.json"] as $f) { json_decode(file_get_contents($f), true, 512, JSON_THROW_ON_ERROR); echo $f, " OK\n"; }'`: both JSON files valid.
- `git diff --check -- lanes/esbuild`: passed.
- `php tools/run-tests.php`: 178 test files, 17211 assertions, 0 failures.

## Remaining Gaps

- Release-extra upstream `make test-all` remains separate: Deno, WASM browser/node, typecheck, Yarn PnP, and upstream custom Go/browser/WASM build paths.
- Async-generator runtime lowering still needs broader await-expression parsing, multiple async-generator expressions in one statement, fuller helper hygiene parity, and complete unsupported-target async-function lowering.
