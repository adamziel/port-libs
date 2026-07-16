# URL/refspec instruction identity parity

Micro-slice: `gitoxide-url-refspec-parse-normalize-parity-20260601T100545Z`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-refspec/tests/refspec/impls.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-refspec/src/spec.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-refspec/src/write.rs`

The upstream `gix-refspec` equality, hashing, and ordering tests compare `RefSpec` values through the derived instruction, not the raw parser shape. This makes a source-only push such as `refs/heads/foo` equal to the explicit `refs/heads/foo:refs/heads/foo`, ignores a leading force marker for delete and fetch-only instructions, and keeps force mode significant for all-matching push instructions.

Native delta:

- Added `RefSpec::instructionIdentity()`, `instructionKey()`, and `equivalentTo()`.
- Materialized implicit same-name push destinations in the instruction identity while preserving the parsed destination as `null`.
- Kept upstream-equivalent force handling across push matching, push delete, fetch-only, and push all-matching instructions.
- Extended the WordPress URL/refspec normalization fixture and example smoke to report instruction identity dedup/equivalence outcomes.

Evidence:

- Red-first probe before implementation: `php -r 'require "tools/bootstrap.php"; $r=PortLibs\Gitoxide\RefSpec::parsePush("refs/heads/foo"); echo method_exists($r, "instructionIdentity") ? "has\n" : "missing\n";'` printed `missing`.
- Focused before implementation: `php tools/run-tests.php lanes/gitoxide/tests/UrlRefSpecTest.php` passed `1 test files, 709 assertions, 0 failures`.
- Focused after implementation: `php tools/run-tests.php lanes/gitoxide/tests/UrlRefSpecTest.php` passed `1 test files, 738 assertions, 0 failures`.
- Full lane after implementation: `php tools/run-tests.php lanes/gitoxide/tests` passed `40 test files, 8705 assertions, 0 failures`.
- Example smoke: `php lanes/gitoxide/examples/wordpress-url-refspec-normalize.php --self-test` exited `0`.
- PHP lint: changed PHP files passed `php -l`.
- Diff whitespace: `git diff --check -- lanes/gitoxide` exited `0`.

Dependency closure:

No new support component is needed. The slice reuses the native PHP `RefSpec` parser/writer, JSON identity keys, lane fixture, example, and PHP test harness. No Git binary, network provider, credential store, or live service is required.

Non-overlap:

This extends URL/refspec parse-normalize parity with instruction-level identity and equality behavior. It does not repeat URL parsing, path/root safety, UTF-8 writer behavior, from-parts construction, one-sided push writer normalization, short-hex prefix expansion, transport/protocol, object database, reference transaction, tree-merge, pathspec, or merge-base work.
