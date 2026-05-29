# SQLite btree vacuum pointer-map freeblock current-source next295-298

- Adds next295 through next298 plan factories over the existing freelist splice variant.
- Reuses the next261 vacuum/freelist splice dependency chain already admitted for next263-266.
- Validates trunk anchors, ordered leaf slots, write offsets, tail-page rejection, link integrity, and per-slice receipt tokens with focused tests and an example.
