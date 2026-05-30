# real-upstream-corpus-window-functions-dynamic-20260530T203710Z-0

- Base accepted HEAD: `d5feb4b8c9f51e52c1a4ee4e369261ca23aa819e`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test`.
- Ported upstream scenarios: `window4.test` `1.1-1.19` `ntile()` bucket distribution, `2.1-2.4` `nth_value()`/`lead()`/`lag()` row-value behavior, and `3.5-3.6` empty/sliding value frames.
- New PHP evidence: `lanes/libsqlite/tests/SQLiteRealUpstreamWindow4ValueDynamicTest.php`.
- Focused assertion growth: `2352` assertions, `2351` focused PASS lines, `0` failures.
- Non-overlap: existing accepted window corpus already covers `window1`, `window3`, `window5`, `window7`, `window8`, `window9`, `windowA`, `windowB`, `windowC`, `windowD`, group-concat, JSON window ranking, and groups/range guards. This slice adds `window4.test` value-function and `ntile()` dynamics only.
- Dependency closure: no new support component is needed; the slice reuses the native `SQLiteWindowFunction` helper already present in `lanes/libsqlite/src`.
- Root harness: not run - isolated micro-slice.
