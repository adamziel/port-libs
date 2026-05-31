<?php

declare(strict_types=1);

$decode = static function (string $encoded): string {
    $bytes = base64_decode(preg_replace('/\s+/', '', $encoded) ?? '', true);
    if ($bytes === false) {
        throw new RuntimeException('invalid upstream fetch sideband fixture encoding');
    }

    return $bytes;
};

return [
    'source' => [
        'repository' => 'GitoxideLabs/gitoxide',
        'commit' => '87433ed33eee9ba974111d20b854f6acb07cd4a6',
        'paths' => [
            'gix-protocol/tests/fixtures/v2/clone-only-with-keepalive.response',
            'gix-protocol/tests/fixtures/v2/clone-only-2.response',
            'gix-protocol/tests/protocol/fetch/response.rs::v2::from_line_reader::{clone,clone_with_sidebands}',
            'gix-protocol/src/remote_progress.rs',
        ],
    ],
    'cloneOnlyWithKeepalive' => [
        'response' => $decode(<<<'BASE64'
MDAwZHBhY2tmaWxlCjAwMDUBMDA0MAJFbnVtZXJhdGluZyBvYmplY3RzOiAzLCBkb25lLgpDb3Vu
dGluZyBvYmplY3RzOiAgMzMlICgxLzMpDTAwMjICQ291bnRpbmcgb2JqZWN0czogIDY2JSAoMi8z
KQ0wMDQ2AkNvdW50aW5nIG9iamVjdHM6IDEwMCUgKDMvMykNQ291bnRpbmcgb2JqZWN0czogMTAw
JSAoMy8zKSwgZG9uZS4KMDM1YwFQQUNLAAAAAgAAAAOSQnicpZPJrptYAET3fMXdow5gwFykpNXM
M2Ywg71jBjMbbDBfn5eOsutd1/IsSqqSzvosCpDQMMlpimEZiJ/JjElx4pSeTyVTEGxJlXSZkjTM
mRJJXms9PoFfpMmyNskArnVTdOD78gd8W3+Bf5qsG1/5t2zs/wYEzTIkTjGQASgOcRz5on2zrsX/
7ammamkq8Nev8JKi2cBRHOBris1dA0/6lyMAAY2rHTzH8QLH5XIjSToMLiXR6h6bDZfBLWqvswRJ
u1dWK3MdxBk244Sqdf9wBFRW95I3DIs34zHP7yOJPV3VynMPSdatLdwZZxajm9f1KUvSZjOTkg/G
/MnaT11ahoQhIGeuBsSMwFLF9MPw8gcz/XCXoopbY47ddsUNKJPQaxFti9nERhO1+NyvebcKJzwJ
IAI87gHVJkcfQmI5kqATpdGI72cC3UWETvR2rSz1FVUpQsOru6GLTJ8UJSxmtFot36WBACK6tZEn
S3LheQd73Ztnhn2oZTsJUj5hu35vsyBjUTxNKf7w6Fh7ZyJN6EYZkhPRVV8/hM9JPyhH4SiJlhx5
TlKm30f8KXiyUzkXvsZ9aS2Gp6+dLqN6QxmGJi3h1odHrcmKPCOgWO4EXdkH7bFsUIahFoX9kg56
IXcia7avE/culIFX+A2zoLUrk72zooGRJM+H8Ii+VkS3QHDMONX8x1ag0SUm7aUMn1S2lwSBrpba
Z6PTNmzlvk6fy8byEe91vC1adeRoh3MgIJ16dcrPuZ+ql9ha2aMqh2No6ZjtyIvUjENty0OsEJ9O
n4IVdw5pJ25GyVzxdYqh+UYA58Usu6GjxalNaNbOLa0TzUbH5b5igxdcTLk7KkbGs0oQRvHB+1E6
W8sya1SzZVBzEWBYJC/OBOoFA6W9sG3umaAsjk44X20Yr5TQ69L7LitDFD32PgkI42XcT+X9Plm1
0qvyV8NYUtMq0dBN8j167drVPEGZG5mDm+N4E1Qhwi72yCsWjbWuz5w9+AMBP86B4CO/nZFs8b+M
Qda6AONQgN+eIj8BKtVM66ECeJwzNDAwMzFRyEjNyclnKOhWVytsn7XE5eSZpbKHJvL/WvPNGgC8
9Q22OXicS88sya/ITEnlAgASsgMwMDE5AWgVChBF8E3A/C2/cjE2mf2mlr9BMDAzYQJUb3RhbCAz
IChkZWx0YSAwKSwgcmV1c2VkIDAgKGRlbHRhIDApLCBwYWNrLXJldXNlZCAwCjAwMDYBJjAwMDAK
BASE64),
        'packBytes' => 876,
        'packTrailer' => '150a1045f04dc0fc2dbf72313699fda696bf4126',
        'progressCount' => 4,
    ],
    'cloneOnly2' => [
        'response' => $decode(<<<'BASE64'
MDAwZHBhY2tmaWxlCjAwMjMCRW51bWVyYXRpbmcgb2JqZWN0czogNCwgZG9uZS4KMDA3OQJDb3Vu
dGluZyBvYmplY3RzOiAgMjUlICgxLzQpDUNvdW50aW5nIG9iamVjdHM6ICA1MCUgKDIvNCkNQ291
bnRpbmcgb2JqZWN0czogIDc1JSAoMy80KQ1Db3VudGluZyBvYmplY3RzOiAxMDAlICg0LzQpDTAw
MjkCQ291bnRpbmcgb2JqZWN0czogMTAwJSAoNC80KSwgZG9uZS4KMDAyNQJDb21wcmVzc2luZyBv
YmplY3RzOiAgNTAlICgxLzIpDTAwMjUCQ29tcHJlc3Npbmcgb2JqZWN0czogMTAwJSAoMi8yKQ0w
MDJjAkNvbXByZXNzaW5nIG9iamVjdHM6IDEwMCUgKDIvMiksIGRvbmUuCjA2NmYBUEFDSwAAAAIA
AAAEkkJ4nKWTya6bWABE93zF3aMOYMBcpKTVzDNmMIO9YwYzG2wwX5+XjrLrXdfyLEqqks76LAqQ
0DDJaYphGYifyYxJceKUnk8lUxBsSZV0mZI0zJkSSV5rPT6BX6TJsjbJAK51U3Tg+/IHfFt/gX+a
rBtf+bds7P8GBM0yJE4xkAEoDnEc+aJ9s67F/+2ppmppKvDXr/CSotnAURzga4rNXQNP+pcjAAGN
qx08x/ECx+VyI0k6DC4l0eoemw2XwS1qr7MESbtXVitzHcQZNuOEqnX/cARUVveSNwyLN+Mxz+8j
iT1d1cpzD0nWrS3cGWcWo5vX9SlL0mYzk5IPxvzJ2k9dWoaEISBnrgbEjMBSxfTD8PIHM/1wl6KK
W2OO3XbFDSiT0GsRbYvZxEYTtfjcr3m3Cic8CSACPO4B1SZHH0JiOZKgE6XRiO9nAt1FhE70dq0s
9RVVKULDq7uhi0yfFCUsZrRaLd+lgQAiurWRJ0ty4XkHe92bZ4Z9qGU7CVI+Ybt+b7MgY1E8TSn+
8OhYe2ciTehGGZIT0VVfP4TPST8oR+EoiZYceU5Spt9H/Cl4slM5F77GfWkthqevnS6jekMZhiYt
4daHR63JijwjoFjuBF3ZB+2xbFCGoRaF/ZIOeiF3Imu2rxP3LpSBV/gNs6C1K5O9s6KBkSTPh/CI
vlZEt0BwzDjV/MdWoNElJu2lDJ9UtpcEga6W2mej0zZs5b5On8vG8hHvdbwtWnXkaIdzICCdenXK
z7mfqpfYWtmjKodjaOmY7ciL1IxDbctDrBCfTp+CFXcOaSduRslc8XWKoflGAOfFLLuho8WpTWjW
zi2tE81Gx+W+YoMXXEy5OypGxrNKEEbxwftROlvLMmtUs2VQcxFgWCQvzgTqBQOlvbBt7pmgLI5O
OF9tGK+U0OvS+y4rQxQ99j4JCONl3E/l/T5ZtdKr8lfDWFLTKtHQTfI9eu3a1TxBmRuZg5vjeBNU
IcIu9sgrFo21rs+cPfgDAT/OgeAjv52RbPG/jEHWugDjUIDfniI/ASrVTOvPPHicbZNJz5tIAETv
/AruKAHMYlqaRAHMvtiAMYYb0M1iMGC7zeJfP/lmlFtKKpX0pDq+sbihEpMSIyGBgfsdX4nM74K8
AFwpwnwHWFAyLC+K+wLBgsDbhMhyvN9bTOC8Jqtx/NoaPckIFfkLt/lAnpsW9eQ/rz/gO/4Cv9qy
H9/w++/3T5IVgAR4HrAiSTESwxDEq62Jb19RNMPyyZNxIiPL8OVzHGr/cYJoA+ujyLKiyjLUW02z
pfhYsZ0dgnI4DgFqwt5TNSurvU6Xe+CDqZbVugv+cKL2elquaTpW7H3NVJ9iGtcmGeeosvACwwnK
R3fqQIkDj5u9lLe1Zu/XmUh12VVI5oGQhlSHUt4O3nXUfdaKzMiG1PGS1fYbPBLM9n7Qju6Wh85a
cL2V6miHL1p6UAYTn58Dkbgv7vPoRcugKRSlkQ8KQdFQ/uJ9PsZLLpzC5yiabRzFDijMiKK6d2rg
xr+rOvPaMgK16ZZNm3qAe817WFbovqTTVHvX25xmZ+akWzkn0JbPHRQ5OvZPJp0/IuNT8d2B0pHJ
Cclx5zmhtSye+9AzC6R3iaLOkwznEi+rcnOjJt0OOd+PaHVB8uHKWGT3oFfpdr03DjGxh3hJutCl
tL0607cyrgZJmrPahGZgrLbVXEvlpq366zi7HaSfl0Wr3XU9aPIVR8+CEPg1KdXt8kT5pREYMYlu
aqeViu61GueoirfAgr/AW/0woiAX7mZlAr1b+J1Qoc0FDpGIgZH3hryGkoCER9GxN7hSFQjZ+CWN
4B4ORfbkWhxsaW4igz8L9KC045TGqk6vlEos7zcG+WU7cbswEzDcg2o8fHxRoROe6y7J6F5ai37e
hp3kqzZaq0f0iRt8OqvGvQaJQgAm5J39bnj0Kkwtcxipc6OGzWx09BW3Vmo11fXsAJttr5zxfriC
NFIZXN4UbqDNbTHh4+P5+HrYb8E0kZMEgqUblRo7s0ppQ9uzhgeYxZon1D5ZZXfJwmH5QfzwhXD9
XxLNP/xNkX8BSDw3QqECeJwzNDAwMzFRyEjNyclnKOhWVytsn7XE5eSZpbKHJvL/WvPNGgC89Q22
OXicS88sya/ITEnlAgASsgNo80yb5+DD7yw+18Ysx3kdv23F7DAwMDYBmjAwM2ECVG90YWwgNCAo
ZGVsdGEgMCksIHJldXNlZCA0IChkZWx0YSAwKSwgcGFjay1yZXVzZWQgMAowMDAw
BASE64),
        'packBytes' => 1643,
        'packTrailer' => 'f34c9be7e0c3ef2c3ed7c62cc7791dbf6dc5ec9a',
        'progressCount' => 7,
    ],
];
