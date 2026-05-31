<?php

declare(strict_types=1);

$decode = static function (string $encoded): string {
    $bytes = base64_decode(preg_replace('/\s+/', '', $encoded) ?? '', true);
    if ($bytes === false) {
        throw new RuntimeException('invalid upstream smart HTTP fetch response fixture encoding');
    }

    return $bytes;
};

$httpResponse = $decode(<<<'BASE64'
SFRUUC8xLjEgMjAwIE9LDQpTZXJ2ZXI6IEdpdEh1YiBCYWJlbCAyLjANCkNvbnRlbnQtVHlwZTog
YXBwbGljYXRpb24veC1naXQtdXBsb2FkLXBhY2stcmVzdWx0DQpDb250ZW50LUxlbmd0aDogMTEz
NQ0KRXhwaXJlczogRnJpLCAwMSBKYW4gMTk4MCAwMDowMDowMCBHTVQNClByYWdtYTogbm8tY2Fj
aGUNCkNhY2hlLUNvbnRyb2w6IG5vLWNhY2hlLCBtYXgtYWdlPTAsIG11c3QtcmV2YWxpZGF0ZQ0K
VmFyeTogQWNjZXB0LUVuY29kaW5nDQpYLUZyYW1lLU9wdGlvbnM6IERFTlkNClgtR2l0SHViLVJl
cXVlc3QtSWQ6IEM0Q0Q6NzI2Nzo3NDU4RDg6OUU5MUQxOjVGNDdBRkZCDQoNCjAwMGRwYWNrZmls
ZQowMDIzAkVudW1lcmF0aW5nIG9iamVjdHM6IDMsIGRvbmUuCjAwM2YCQ291bnRpbmcgb2JqZWN0
czogIDMzJSAoMS8zKQ1Db3VudGluZyBvYmplY3RzOiAgNjYlICgyLzMpDTAwMjICQ291bnRpbmcg
b2JqZWN0czogMTAwJSAoMy8zKQ0wMDI5AkNvdW50aW5nIG9iamVjdHM6IDEwMCUgKDMvMyksIGRv
bmUuCjAzNzABUEFDSwAAAAIAAAADkkJ4nKWTya6bWABE93zF3aMOYMBcpKTVzDNmMIO9YwYzG2ww
X5+XjrLrXdfyLEqqks76LAqQ0DDJaYphGYifyYxJceKUnk8lUxBsSZV0mZI0zJkSSV5rPT6BX6TJ
sjbJAK51U3Tg+/IHfFt/gX+arBtf+bds7P8GBM0yJE4xkAEoDnEc+aJ9s67F/+2ppmppKvDXr/CS
otnAURzga4rNXQNP+pcjAAGNqx08x/ECx+VyI0k6DC4l0eoemw2XwS1qr7MESbtXVitzHcQZNuOE
qnX/cARUVveSNwyLN+Mxz+8jiT1d1cpzD0nWrS3cGWcWo5vX9SlL0mYzk5IPxvzJ2k9dWoaEISBn
rgbEjMBSxfTD8PIHM/1wl6KKW2OO3XbFDSiT0GsRbYvZxEYTtfjcr3m3Cic8CSACPO4B1SZHH0Ji
OZKgE6XRiO9nAt1FhE70dq0s9RVVKULDq7uhi0yfFCUsZrRaLd+lgQAiurWRJ0ty4XkHe92bZ4Z9
qGU7CVI+Ybt+b7MgY1E8TSn+8OhYe2ciTehGGZIT0VVfP4TPST8oR+EoiZYceU5Spt9H/Cl4slM5
F77GfWkthqevnS6jekMZhiYt4daHR63JijwjoFjuBF3ZB+2xbFCGoRaF/ZIOeiF3Imu2rxP3LpSB
V/gNs6C1K5O9s6KBkSTPh/CIvlZEt0BwzDjV/MdWoNElJu2lDJ9UtpcEga6W2mej0zZs5b5On8vG
8hHvdbwtWnXkaIdzICCdenXKz7mfqpfYWtmjKodjaOmY7ciL1IxDbctDrBCfTp+CFXcOaSduRslc
8XWKoflGAOfFLLuho8WpTWjWzi2tE81Gx+W+YoMXXEy5OypGxrNKEEbxwftROlvLMmtUs2VQcxFg
WCQvzgTqBQOlvbBt7pmgLI5OOF9tGK+U0OvS+y4rQxQ99j4JCONl3E/l/T5ZtdKr8lfDWFLTKtHQ
TfI9eu3a1TxBmRuZg5vjeBNUIcIu9sgrFo21rs+cPfgDAT/OgeAjv52RbPG/jEHWugDjUIDfniI/
ASrVTOuhAnicMzQwMDMxUchIzcnJZyjoVlcrbJ+1xOXkmaWyhyby/1rzzRoAvPUNtjl4nEvPLMmv
yExJ5QIAErIDaBUKEEXwTcD8Lb9yMTaZ/aaWv0EwMDA2ASYwMDNhAlRvdGFsIDMgKGRlbHRhIDAp
LCByZXVzZWQgMyAoZGVsdGEgMCksIHBhY2stcmV1c2VkIDAKMDAwMAo=
BASE64);

return [
    'source' => [
        'repository' => 'GitoxideLabs/gitoxide',
        'commit' => '87433ed33eee9ba974111d20b854f6acb07cd4a6',
        'paths' => [
            'gix-transport/tests/fixtures/v2/http-fetch.response',
            'gix-transport/tests/client/git.rs::http_v2::fetch',
            'gix-protocol/src/fetch/response/blocking_io.rs',
            'gix-packetline/src/blocking_io/sidebands.rs',
        ],
    ],
    'httpResponse' => $httpResponse,
    'httpBytes' => strlen($httpResponse),
    'bodyBytes' => 1135,
    'packBytes' => 876,
    'packTrailer' => '150a1045f04dc0fc2dbf72313699fda696bf4126',
    'progressCount' => 5,
    'remoteProgressCount' => 4,
    'firstProgress' => 'Enumerating objects: 3, done.',
    'combinedCountingProgress' => "Counting objects:  33% (1/3)\rCounting objects:  66% (2/3)\r",
    'finalProgress' => 'Total 3 (delta 0), reused 3 (delta 0), pack-reused 0',
];
