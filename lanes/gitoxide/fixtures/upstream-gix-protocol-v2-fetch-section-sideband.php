<?php

declare(strict_types=1);

$decode = static function (string $encoded): string {
    $bytes = base64_decode(preg_replace('/\s+/', '', $encoded) ?? '', true);
    if ($bytes === false) {
        throw new RuntimeException('invalid upstream fetch response fixture encoding');
    }

    return $bytes;
};

return [
    'source' => [
        'repository' => 'GitoxideLabs/gitoxide',
        'commit' => '87433ed33eee9ba974111d20b854f6acb07cd4a6',
        'paths' => [
            'gix-protocol/tests/fixtures/v2/fetch-unshallow.response',
            'gix-protocol/tests/fixtures/v2/clone-deepen-1.response',
            'gix-protocol/tests/fixtures/v2/clone-deepen-5.response',
            'gix-protocol/tests/fixtures/v2/fetch-no-pack.response',
            'gix-protocol/tests/fixtures/v2/fetch-err-line.response',
            'gix-protocol/tests/protocol/fetch/response.rs::v2::from_line_reader',
            'gix-packetline/src/blocking_io/sidebands.rs',
        ],
    ],
    'fetchUnshallow' => [
        'response' => $decode(<<<'BASE64'
MDAxNGFja25vd2xlZGdtZW50cwowMDMxQUNLIGY5OTc3MWZlNmExYjUzNTc4M2FmMzE2M2ViYTk1
YTkyN2FhZTIxZDUKMDAzMUFDSyAyZDlkMTM2ZmIwNzY1ZjJlMjRjNDRhMGY5MTk4NDMxOGQ1ODBk
MDNiCjAwMzFBQ0sgZGZkMDk1NGRhYmVmM2I2NGY0NTgzMjFlZjE1NTcxY2MxYTQ2ZDU1MgowMDBh
cmVhZHkKMDAwMTAwMTFzaGFsbG93LWluZm8KMDAzNnVuc2hhbGxvdyAyZDlkMTM2ZmIwNzY1ZjJl
MjRjNDRhMGY5MTk4NDMxOGQ1ODBkMDNiMDAzNnVuc2hhbGxvdyBkZmQwOTU0ZGFiZWYzYjY0ZjQ1
ODMyMWVmMTU1NzFjYzFhNDZkNTUyMDAwMTAwMGRwYWNrZmlsZQowMDI0AkVudW1lcmF0aW5nIG9i
amVjdHM6IDI3LCBkb25lLgowMDIzAkNvdW50aW5nIG9iamVjdHM6ICAgMyUgKDEvMjcpDTAwNDEC
Q291bnRpbmcgb2JqZWN0czogICA3JSAoMi8yNykNQ291bnRpbmcgb2JqZWN0czogIDExJSAoMy8y
NykNMDA0MQJDb3VudGluZyBvYmplY3RzOiAgMTQlICg0LzI3KQ1Db3VudGluZyBvYmplY3RzOiAg
MTglICg1LzI3KQ0wMDQxAkNvdW50aW5nIG9iamVjdHM6ICAyMiUgKDYvMjcpDUNvdW50aW5nIG9i
amVjdHM6ICAyNSUgKDcvMjcpDTAwNDECQ291bnRpbmcgb2JqZWN0czogIDI5JSAoOC8yNykNQ291
bnRpbmcgb2JqZWN0czogIDMzJSAoOS8yNykNMDA0MwJDb3VudGluZyBvYmplY3RzOiAgMzclICgx
MC8yNykNQ291bnRpbmcgb2JqZWN0czogIDQwJSAoMTEvMjcpDTAwNDMCQ291bnRpbmcgb2JqZWN0
czogIDQ0JSAoMTIvMjcpDUNvdW50aW5nIG9iamVjdHM6ICA0OCUgKDEzLzI3KQ0wMDQzAkNvdW50
aW5nIG9iamVjdHM6ICA1MSUgKDE0LzI3KQ1Db3VudGluZyBvYmplY3RzOiAgNTUlICgxNS8yNykN
MDA0MwJDb3VudGluZyBvYmplY3RzOiAgNTklICgxNi8yNykNQ291bnRpbmcgb2JqZWN0czogIDYy
JSAoMTcvMjcpDTAwNDMCQ291bnRpbmcgb2JqZWN0czogIDY2JSAoMTgvMjcpDUNvdW50aW5nIG9i
amVjdHM6ICA3MCUgKDE5LzI3KQ0wMDQzAkNvdW50aW5nIG9iamVjdHM6ICA3NCUgKDIwLzI3KQ1D
b3VudGluZyBvYmplY3RzOiAgNzclICgyMS8yNykNMDA0MwJDb3VudGluZyBvYmplY3RzOiAgODEl
ICgyMi8yNykNQ291bnRpbmcgb2JqZWN0czogIDg1JSAoMjMvMjcpDTAwNDMCQ291bnRpbmcgb2Jq
ZWN0czogIDg4JSAoMjQvMjcpDUNvdW50aW5nIG9iamVjdHM6ICA5MiUgKDI1LzI3KQ0wMDQzAkNv
dW50aW5nIG9iamVjdHM6ICA5NiUgKDI2LzI3KQ1Db3VudGluZyBvYmplY3RzOiAxMDAlICgyNy8y
NykNMDAyYgJDb3VudGluZyBvYmplY3RzOiAxMDAlICgyNy8yNyksIGRvbmUuCjAwMjUCQ29tcHJl
c3Npbmcgb2JqZWN0czogIDE0JSAoMS83KQ0wMDg1AkNvbXByZXNzaW5nIG9iamVjdHM6ICAyOCUg
KDIvNykNQ29tcHJlc3Npbmcgb2JqZWN0czogIDQyJSAoMy83KQ1Db21wcmVzc2luZyBvYmplY3Rz
OiAgNTclICg0LzcpDUNvbXByZXNzaW5nIG9iamVjdHM6ICA3MSUgKDUvNykNMDA0NQJDb21wcmVz
c2luZyBvYmplY3RzOiAgODUlICg2LzcpDUNvbXByZXNzaW5nIG9iamVjdHM6IDEwMCUgKDcvNykN
MDAyYwJDb21wcmVzc2luZyBvYmplY3RzOiAxMDAlICg3LzcpLCBkb25lLgowNTk2AVBBQ0sAAAAC
AAAAFZUKeJyFy0EKwjAQRuF9TjEXUGamdkygiEVcihs9QIx/UTC0hAgeX6Xg1t3jg1cLQMqDiYeH
sSAhpObaalQbxHjlQzSfVO0CF5/1Nhbq6Uynb3QzbPGKeXpgmca8IRHRIGptQwteM7uP5nutKLSj
Ix3m7H7653Z79wYO4DChlRB4nIWLwUpDMRBF9/mK+QElk8kkEyjSIrgrbvQDkvQGBZ8tjxT6+bYU
3Qp3cTicO1eAuEqWWHpHYo2jZiTzkJGZNUVmY0UTC+5UV3xP0ixVMMDXpWYiNiDg7nGI6hmpjWDd
Dr99Qi9JuxmGxthyjknDqG1wKbEPg6pFU+/qeX4cV9rRO73dYHMXW1zqcvrCYz8uT8TMoXDQIvTg
s/fuapfPObHSM73S/o6bP/vP2724H6XsS76VCnicKylKTVWwtDA2NzFJTUsySDIwTzWzSLJIMkg0
MLQ0SUk0Sk0zTUxJM7IwN05O5UosLcnIL1JwVAhVCAExbCACDqkVibkFOal6yfm5dgqGhoZGloZG
psbGCroG5gYGXEDR3MySktQiBWcFfwVfCNMGLkpAN5cXFwBVITEwlQp4nCspSk1VMDcwTUw2t0xL
TbNMMbBIMjMwMTFPBDISU5KTTVOTEy1MLA1SLVJMuRJLSzLyixQcFUIVQkAMG4iAQ2pFYm5BTqpe
cn6unYKhoaGRpaGRibmxgq6BuYEBF1A0N7OkJLVIwVnBX8EXwrSBixLQzeXJBQB6PjFslRB4nIWN
wUpDMRBF9/mK+QElmUwyGSii6LZ0Yz8gyUxQ8NnyiODn+0qp2+4Oh3u4czUDSeJjjUZakpFwbyWY
lGEeM0keFnvLWps719W+J1hVTbUNJhncB6rHEmJkbU16Lj0ydTHW256yH4TMSfOWpeZHMtlu+lC0
VAhLTRkDufozP04rvMAR3i+wu4pn+63L+cse+2l5ghACSkAKER48e+82u3zOaSu8wgH2V9z92zu1
e3N/qcBM1ZUKeJwrKUpNVUg2M00zN05MMzEyS7a0NEg0N0pMMjJKMjE1tDAzNTe1SEwzskw0SEzm
SiwtycgvUnBUCFUIATFsIAIOqRWJuQU5qXrJ+bl2CoaGhkaWhkbGpsYKugbmBgZcQNHczJKS1CIF
ZwV/BV8I0wYuSkA3lwcXAAvLMJqXC3ichctBCsIwEEDR/ZxiLqAkGTMxUIriwpW40QNM46CBxkob
weOrFNy6+zz4dVTFpMkGx54CJRLmENfCukqGxEf2JjF1F9sZkGe9DSNu8YynbzQzbPQl5dHrMg2l
RWuti9a5SLgwwRj4aMm16og7POJhzuanf27YA2C+55qlx6LTJFeFN5E/No6gAnicMzQwMDMxUUjL
zElluMl681bTyaKU1fmeCo+YnB50tPK8BAC9LQ0tMnicS+UCAADWAHCgAnicMzQwMDMxUUjLzEll
eHt81iPTqmLLfevKyy1yf0/I0Eq2BwDE9Q05Nnicy+TK4krjAgAFSgFYoAJ4nDM0MDAzMVFIy8xJ
ZfAJnV+xKW/p1MvnTL8155WePdOz9x8AwJMPFDJ4nMviAgAA4AB1oAJ4nDM0MDAzMVFIy8xJZeC9
r72u8EKbckXuRoWZea8Ytsc3mQMArb0MFTJ4nMvkAgAA3gB0oAJ4nDM0MDAzMVFIy8xJZfhkySbm
oLm4r8a+q8Y/6VL1kudPwwGiGgxXNnicS+fK4ErhAgAFMgFSoAJ4nDM0MDAzMVFIy8xJZcibz7vQ
XnKLyxurObZ5lVGXY1hDFgEApKsLZTJ4nMvgAgAA3ABzoAJ4nDM0MDAzMVFIy8xJZWBk7W3xmnFp
8uINVm2ii90ZnrzVfgwApIsMPzJ4nEvnAgAA2gByw5YHVbfXkMfQRJJsR+1uHPXbuTAwM2MCVG90
YWwgMjEgKGRlbHRhIDApLCByZXVzZWQgMjEgKGRlbHRhIDApLCBwYWNrLXJldXNlZCAwCjAwMDYB
GTAwMDA=
BASE64),
        'acknowledgements' => [
            ['kind' => 'common', 'object' => 'f99771fe6a1b535783af3163eba95a927aae21d5'],
            ['kind' => 'common', 'object' => '2d9d136fb0765f2e24c44a0f91984318d580d03b'],
            ['kind' => 'common', 'object' => 'dfd0954dabef3b64f458321ef15571cc1a46d552'],
            ['kind' => 'ready', 'object' => null],
        ],
        'shallowUpdates' => [
            ['kind' => 'unshallow', 'object' => '2d9d136fb0765f2e24c44a0f91984318d580d03b'],
            ['kind' => 'unshallow', 'object' => 'dfd0954dabef3b64f458321ef15571cc1a46d552'],
        ],
        'hasPack' => true,
        'packBytes' => 1426,
        'packTrailer' => 'c3960755b7d790c7d044926c47ed6e1cf5dbb919',
        'progressCount' => 21,
    ],
    'cloneDeepen1' => [
        'response' => $decode(<<<'BASE64'
MDAxMXNoYWxsb3ctaW5mbwowMDM0c2hhbGxvdyA4MDhlNTBkNzI0ZjYwNGY2OWFiOTNjNmRhMjkx
OWMwMTQ2NjdiZWRiMDAwMTAwMGRwYWNrZmlsZQowMDIzAkVudW1lcmF0aW5nIG9iamVjdHM6IDQs
IGRvbmUuCjAwMjICQ291bnRpbmcgb2JqZWN0czogIDI1JSAoMS80KQ0wMDVjAkNvdW50aW5nIG9i
amVjdHM6ICA1MCUgKDIvNCkNQ291bnRpbmcgb2JqZWN0czogIDc1JSAoMy80KQ1Db3VudGluZyBv
YmplY3RzOiAxMDAlICg0LzQpDTAwMjkCQ291bnRpbmcgb2JqZWN0czogMTAwJSAoNC80KSwgZG9u
ZS4KMDAyNQJDb21wcmVzc2luZyBvYmplY3RzOiAgNTAlICgxLzIpDTAwMjUCQ29tcHJlc3Npbmcg
b2JqZWN0czogMTAwJSAoMi8yKQ0wMDJjAkNvbXByZXNzaW5nIG9iamVjdHM6IDEwMCUgKDIvMiks
IGRvbmUuCjA2NmYBUEFDSwAAAAIAAAAEkkJ4nKWTya6bWABE93zF3aMOYMBcpKTVzDNmMIO9YwYz
G2wwX5+XjrLrXdfyLEqqks76LAqQ0DDJaYphGYifyYxJceKUnk8lUxBsSZV0mZI0zJkSSV5rPT6B
X6TJsjbJAK51U3Tg+/IHfFt/gX+arBtf+bds7P8GBM0yJE4xkAEoDnEc+aJ9s67F/+2ppmppKvDX
r/CSotnAURzga4rNXQNP+pcjAAGNqx08x/ECx+VyI0k6DC4l0eoemw2XwS1qr7MESbtXVitzHcQZ
NuOEqnX/cARUVveSNwyLN+Mxz+8jiT1d1cpzD0nWrS3cGWcWo5vX9SlL0mYzk5IPxvzJ2k9dWoaE
ISBnrgbEjMBSxfTD8PIHM/1wl6KKW2OO3XbFDSiT0GsRbYvZxEYTtfjcr3m3Cic8CSACPO4B1SZH
H0JiOZKgE6XRiO9nAt1FhE70dq0s9RVVKULDq7uhi0yfFCUsZrRaLd+lgQAiurWRJ0ty4XkHe92b
Z4Z9qGU7CVI+Ybt+b7MgY1E8TSn+8OhYe2ciTehGGZIT0VVfP4TPST8oR+EoiZYceU5Spt9H/Cl4
slM5F77GfWkthqevnS6jekMZhiYt4daHR63JijwjoFjuBF3ZB+2xbFCGoRaF/ZIOeiF3Imu2rxP3
LpSBV/gNs6C1K5O9s6KBkSTPh/CIvlZEt0BwzDjV/MdWoNElJu2lDJ9UtpcEga6W2mej0zZs5b5O
n8vG8hHvdbwtWnXkaIdzICCdenXKz7mfqpfYWtmjKodjaOmY7ciL1IxDbctDrBCfTp+CFXcOaSdu
Rslc8XWKoflGAOfFLLuho8WpTWjWzi2tE81Gx+W+YoMXXEy5OypGxrNKEEbxwftROlvLMmtUs2VQ
cxFgWCQvzgTqBQOlvbBt7pmgLI5OOF9tGK+U0OvS+y4rQxQ99j4JCONl3E/l/T5ZtdKr8lfDWFLT
KtHQTfI9eu3a1TxBmRuZg5vjeBNUIcIu9sgrFo21rs+cPfgDAT/OgeAjv52RbPG/jEHWugDjUIDf
niI/ASrVTOvPPHicbZNJz5tIAETv/AruKAHMYlqaRAHMvtiAMYYb0M1iMGC7zeJfP/lmlFtKKpX0
pDq+sbihEpMSIyGBgfsdX4nM74K8AFwpwnwHWFAyLC+K+wLBgsDbhMhyvN9bTOC8Jqtx/NoaPckI
FfkLt/lAnpsW9eQ/rz/gO/4Cv9qyH9/w++/3T5IVgAR4HrAiSTESwxDEq62Jb19RNMPyyZNxIiPL
8OVzHGr/cYJoA+ujyLKiyjLUW02zpfhYsZ0dgnI4DgFqwt5TNSurvU6Xe+CDqZbVugv+cKL2elqu
aTpW7H3NVJ9iGtcmGeeosvACwwnKR3fqQIkDj5u9lLe1Zu/XmUh12VVI5oGQhlSHUt4O3nXUfdaK
zMiG1PGS1fYbPBLM9n7Qju6Wh85acL2V6miHL1p6UAYTn58Dkbgv7vPoRcugKRSlkQ8KQdFQ/uJ9
PsZLLpzC5yiabRzFDijMiKK6d2rgxr+rOvPaMgK16ZZNm3qAe817WFbovqTTVHvX25xmZ+akWzkn
0JbPHRQ5OvZPJp0/IuNT8d2B0pHJCclx5zmhtSye+9AzC6R3iaLOkwznEi+rcnOjJt0OOd+PaHVB
8uHKWGT3oFfpdr03DjGxh3hJutCltL0607cyrgZJmrPahGZgrLbVXEvlpq366zi7HaSfl0Wr3XU9
aPIVR8+CEPg1KdXt8kT5pREYMYluaqeViu61GueoirfAgr/AW/0woiAX7mZlAr1b+J1Qoc0FDpGI
gZH3hryGkoCER9GxN7hSFQjZ+CWN4B4ORfbkWhxsaW4igz8L9KC045TGqk6vlEos7zcG+WU7cbsw
EzDcg2o8fHxRoROe6y7J6F5ai37ehp3kqzZaq0f0iRt8OqvGvQaJQgAm5J39bnj0Kkwtcxipc6OG
zWx09BW3Vmo11fXsAJttr5zxfriCNFIZXN4UbqDNbTHh4+P5+HrYb8E0kZMEgqUblRo7s0ppQ9uz
hgeYxZon1D5ZZXfJwmH5QfzwhXD9XxLNP/xNkX8BSDw3QqECeJwzNDAwMzFRyEjNyclnKOhWVyts
n7XE5eSZpbKHJvL/WvPNGgC89Q22OXicS88sya/ITEnlAgASsgNo80yb5+DD7yw+18Ysx3kdv23F
7DAwM2ECVG90YWwgNCAoZGVsdGEgMCksIHJldXNlZCA0IChkZWx0YSAwKSwgcGFjay1yZXVzZWQg
MAowMDA2AZowMDAw
BASE64),
        'acknowledgements' => [],
        'shallowUpdates' => [
            ['kind' => 'shallow', 'object' => '808e50d724f604f69ab93c6da2919c014667bedb'],
        ],
        'hasPack' => true,
        'packBytes' => 1643,
        'packTrailer' => 'f34c9be7e0c3ef2c3ed7c62cc7791dbf6dc5ec9a',
        'progressCount' => 8,
    ],
    'cloneDeepen5' => [
        'response' => $decode(<<<'BASE64'
MDAxMXNoYWxsb3ctaW5mbwowMDAxMDAwZHBhY2tmaWxlCjAwNDACRW51bWVyYXRpbmcgb2JqZWN0
czogNCwgZG9uZS4KQ291bnRpbmcgb2JqZWN0czogIDI1JSAoMS80KQ0wMDgwAkNvdW50aW5nIG9i
amVjdHM6ICA1MCUgKDIvNCkNQ291bnRpbmcgb2JqZWN0czogIDc1JSAoMy80KQ1Db3VudGluZyBv
YmplY3RzOiAxMDAlICg0LzQpDUNvdW50aW5nIG9iamVjdHM6IDEwMCUgKDQvNCksIGRvbmUuCjAw
MjUCQ29tcHJlc3Npbmcgb2JqZWN0czogIDUwJSAoMS8yKQ0wMDI1AkNvbXByZXNzaW5nIG9iamVj
dHM6IDEwMCUgKDIvMikNMDAyYwJDb21wcmVzc2luZyBvYmplY3RzOiAxMDAlICgyLzIpLCBkb25l
LgowNjZmAVBBQ0sAAAACAAAABJJCeJylk8mum1gARPd8xd2jDmDAXKSk1cwzZjCDvWMGMxtsMF+f
l46y613X8ixKqpLO+iwKkNAwyWmKYRmIn8mMSXHilJ5PJVMQbEmVdJmSNMyZEkleaz0+gV+kybI2
yQCudVN04PvyB3xbf4F/mqwbX/m3bOz/BgTNMiROMZABKA5xHPmifbOuxf/tqaZqaSrw16/wkqLZ
wFEc4GuKzV0DT/qXIwABjasdPMfxAsflciNJOgwuJdHqHpsNl8Etaq+zBEm7V1Yrcx3EGTbjhKp1
/3AEVFb3kjcMizfjMc/vI4k9XdXKcw9J1q0t3BlnFqOb1/UpS9JmM5OSD8b8ydpPXVqGhCEgZ64G
xIzAUsX0w/DyBzP9cJeiiltjjt12xQ0ok9BrEW2L2cRGE7X43K95twonPAkgAjzuAdUmRx9CYjmS
oBOl0YjvZwLdRYRO9HatLPUVVSlCw6u7oYtMnxQlLGa0Wi3fpYEAIrq1kSdLcuF5B3vdm2eGfahl
OwlSPmG7fm+zIGNRPE0p/vDoWHtnIk3oRhmSE9FVXz+Ez0k/KEfhKImWHHlOUqbfR/wpeLJTORe+
xn1pLYanr50uo3pDGYYmLeHWh0etyYo8I6BY7gRd2QftsWxQhqEWhf2SDnohdyJrtq8T9y6UgVf4
DbOgtSuTvbOigZEkz4fwiL5WRLdAcMw41fzHVqDRJSbtpQyfVLaXBIGultpno9M2bOW+Tp/LxvIR
73W8LVp15GiHcyAgnXp1ys+5n6qX2FrZoyqHY2jpmO3Ii9SMQ23LQ6wQn06fghV3DmknbkbJXPF1
iqH5RgDnxSy7oaPFqU1o1s4trRPNRsflvmKDF1xMuTsqRsazShBG8cH7UTpbyzJrVLNlUHMRYFgk
L84E6gUDpb2wbe6ZoCyOTjhfbRivlNDr0vsuK0MUPfY+CQjjZdxP5f0+WbXSq/JXw1hS0yrR0E3y
PXrt2tU8QZkbmYOb43gTVCHCLvbIKxaNta7PnD34AwE/zoHgI7+dkWzxv4xB1roA41CA354iPwEq
1Uzrzzx4nG2TSc+bSABE7/wK7igBzGJamkQBzL7YgDGGG9DNYjBgu83iXz/5ZpRbSiqV9KQ6vrG4
oRKTEiMhgYH7HV+JzO+CvABcKcJ8B1hQMiwvivsCwYLA24TIcrzfW0zgvCarcfzaGj3JCBX5C7f5
QJ6bFvXkP68/4Dv+Ar/ash/f8Pvv90+SFYAEeB6wIkkxEsMQxKutiW9fUTTD8smTcSIjy/Dlcxxq
/3GCaAPro8iyosoy1FtNs6X4WLGdHYJyOA4BasLeUzUrq71Ol3vgg6mW1boL/nCi9nparmk6Vux9
zVSfYhrXJhnnqLLwAsMJykd36kCJA4+bvZS3tWbv15lIddlVSOaBkIZUh1LeDt511H3WiszIhtTx
ktX2GzwSzPZ+0I7ulofOWnC9lepohy9aelAGE5+fA5G4L+7z6EXLoCkUpZEPCkHRUP7ifT7GSy6c
wucomm0cxQ4ozIiiundq4Ma/qzrz2jICtemWTZt6gHvNe1hW6L6k01R719ucZmfmpFs5J9CWzx0U
OTr2TyadPyLjU/HdgdKRyQnJcec5obUsnvvQMwukd4mizpMM5xIvq3JzoybdDjnfj2h1QfLhylhk
96BX6Xa9Nw4xsYd4SbrQpbS9OtO3Mq4GSZqz2oRmYKy21VxL5aat+us4ux2kn5dFq911PWjyFUfP
ghD4NSnV7fJE+aURGDGJbmqnlYrutRrnqIq3wIK/wFv9MKIgF+5mZQK9W/idUKHNBQ6RiIGR94a8
hpKAhEfRsTe4UhUI2fgljeAeDkX25FocbGluIoM/C/SgtOOUxqpOr5RKLO83BvllO3G7MBMw3INq
PHx8UaETnusuyeheWot+3oad5Ks2WqtH9IkbfDqrxr0GiUIAJuSd/W549CpMLXMYqXOjhs1sdPQV
t1ZqNdX17ACbba+c8X64gjRSGVzeFG6gzW0x4ePj+fh62G/BNJGTBIKlG5UaO7NKaUPbs4YHmMWa
J9Q+WWV3ycJh+UH88IVw/V8SzT/8TZF/AUg8N0KhAnicMzQwMDMxUchIzcnJZyjoVlcrbJ+1xOXk
maWyhyby/1rzzRoAvPUNtjl4nEvPLMmvyExJ5QIAErIDaPNMm+fgw+8sPtfGLMd5Hb9txewwMDNh
AlRvdGFsIDQgKGRlbHRhIDApLCByZXVzZWQgNCAoZGVsdGEgMCksIHBhY2stcmV1c2VkIDAKMDAw
NgGaMDAwMA==
BASE64),
        'acknowledgements' => [],
        'shallowUpdates' => [],
        'hasPack' => true,
        'packBytes' => 1643,
        'packTrailer' => 'f34c9be7e0c3ef2c3ed7c62cc7791dbf6dc5ec9a',
        'progressCount' => 6,
    ],
    'fetchNoPack' => [
        'response' => $decode(<<<'BASE64'
MDAxNGFja25vd2xlZGdtZW50cwowMDA4TkFLCjAwMDA=
BASE64),
        'acknowledgements' => [
            ['kind' => 'nak', 'object' => null],
        ],
        'shallowUpdates' => [],
        'hasPack' => false,
        'packBytes' => 0,
        'packTrailer' => '',
        'progressCount' => 0,
    ],
    'fetchErrLine' => [
        'response' => $decode(<<<'BASE64'
MDAxYkVSUiBzZWdtZW50YXRpb24gZmF1bHQK
BASE64),
        'errorMessage' => 'fetch response: upload-pack error segmentation fault',
    ],
];
