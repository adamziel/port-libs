# URL/Refspec Push Match Parity

Micro-slice: `gitoxide-wrap-up-url-refspec-and-transport-edge-parity-current-base-20260601T2353Z`

## Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-refspec/src/match_group/mod.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-refspec/src/match_group/util.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-refspec/src/spec.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-refspec/src/instruction.rs`

## Behavior

`RefSpec::matchPushLocalRefs()` ports the push-side `MatchGroup` matching shape for receive-pack planning:

- push refspecs match local refs with the refspec left-hand side;
- explicit destinations and one-sided source-only destinations are materialized as remote names;
- full object-id sources map without requiring an advertised local ref;
- negative push refspecs remove matching local-ref mappings;
- duplicate same local/remote mappings collapse like upstream mapping hash identity.

Delete and all-matching push instructions have no local source, so this local-source matcher leaves them to parse/instruction APIs.

## Evidence

- Before slice: `php tools/run-tests.php lanes/gitoxide/tests/UrlRefSpecTest.php` passed `1 test files, 940 assertions, 0 failures`.
- After slice: `php tools/run-tests.php lanes/gitoxide/tests/UrlRefSpecTest.php` passed `1 test files, 954 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-url-refspec-normalize.php --self-test` exited `0`.
- Proposed `phpPass`: `10848 -> 10862` if accepted.
- Proposed mapped coverage: `1819 -> 1820 / 2886` if accepted.

Full Gitoxide PHP lane and full upstream Cargo workspace were not run in this isolated micro-slice.

## Non-Overlap

This does not repeat accepted protocol v2 fetch stop-packet rejection, Cargo workspace evidence, URL parse/serialization guards, fetch-side LHS/RHS refspec matching, validated fetch conflict handling, or receive-pack transport proxy/cookie edges.

## Dependency Closure

No new support component is needed. The slice reuses the existing native `RefSpec` parser/matcher, URL/refspec fixture, example smoke, and PHP TestRunner. No live network, git process, provider credential, or Cargo workspace execution is required for this behavior.
