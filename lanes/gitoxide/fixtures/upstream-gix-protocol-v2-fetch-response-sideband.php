<?php

declare(strict_types=1);

$decode = static function (string $encoded): string {
    $bytes = base64_decode(preg_replace('/\s+/', '', $encoded) ?? '', true);
    if ($bytes === false) {
        throw new RuntimeException('invalid upstream fetch response sideband fixture encoding');
    }

    return $bytes;
};

return [
    'source' => [
        'repository' => 'GitoxideLabs/gitoxide',
        'commit' => '87433ed33eee9ba974111d20b854f6acb07cd4a6',
        'paths' => [
            'gix-protocol/tests/fixtures/v2/fetch.response',
            'gix-protocol/tests/protocol/fetch/response.rs::v2::from_line_reader::fetch_acks_and_pack',
            'gix-packetline/src/blocking_io/sidebands.rs',
            'gix-protocol/src/remote_progress.rs',
        ],
    ],
    'fetchAcksAndPack' => [
        'response' => $decode(<<<'BASE64'
MDAxNGFja25vd2xlZGdtZW50cwowMDMxQUNLIDE5MGMzZjZiMjMxOWMxZjRlYzg1NDIxNTUzM2Nh
Zjg2MjNmOGY4NzAKMDAzMUFDSyA5N2M1YTkzMmIzOTQwYTA5NjgzZTkyNGVmNmE5MmIzMWE2Zjdj
NmRlCjAwMGFyZWFkeQowMDAxMDAwZHBhY2tmaWxlCjAwMjQCRW51bWVyYXRpbmcgb2JqZWN0czog
MTMsIGRvbmUuCjAwMjMCQ291bnRpbmcgb2JqZWN0czogICA3JSAoMS8xMykNMDA4NQJDb3VudGlu
ZyBvYmplY3RzOiAgMTUlICgyLzEzKQ1Db3VudGluZyBvYmplY3RzOiAgMjMlICgzLzEzKQ1Db3Vu
dGluZyBvYmplY3RzOiAgMzAlICg0LzEzKQ1Db3VudGluZyBvYmplY3RzOiAgMzglICg1LzEzKQ1D
b3VudGluZzAwODUCIG9iamVjdHM6ICA0NiUgKDYvMTMpDUNvdW50aW5nIG9iamVjdHM6ICA1MyUg
KDcvMTMpDUNvdW50aW5nIG9iamVjdHM6ICA2MSUgKDgvMTMpDUNvdW50aW5nIG9iamVjdHM6ICA2
OSUgKDkvMTMpDUNvdW50aW5nIG9iamVjdHMwMDg1AjogIDc2JSAoMTAvMTMpDUNvdW50aW5nIG9i
amVjdHM6ICA4NCUgKDExLzEzKQ1Db3VudGluZyBvYmplY3RzOiAgOTIlICgxMi8xMykNQ291bnRp
bmcgb2JqZWN0czogMTAwJSAoMTMvMTMpDUNvdW50aW5nIG9iamVjdHM6IDEwMDAxNwIwJSAoMTMv
MTMpLCBkb25lLgowMDQ1AkNvbXByZXNzaW5nIG9iamVjdHM6ICAxNCUgKDEvNykNQ29tcHJlc3Np
bmcgb2JqZWN0czogIDI4JSAoMi83KQ0wMDY1AkNvbXByZXNzaW5nIG9iamVjdHM6ICA0MiUgKDMv
NykNQ29tcHJlc3Npbmcgb2JqZWN0czogIDU3JSAoNC83KQ1Db21wcmVzc2luZyBvYmplY3RzOiAg
NzElICg1LzcpDTAwMjUCQ29tcHJlc3Npbmcgb2JqZWN0czogIDg1JSAoNi83KQ0wMDI1AkNvbXBy
ZXNzaW5nIG9iamVjdHM6IDEwMCUgKDcvNykNMDAyYwJDb21wcmVzc2luZyBvYmplY3RzOiAxMDAl
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
BASE64),
        'acknowledgements' => [
            ['kind' => 'common', 'object' => '190c3f6b2319c1f4ec854215533caf8623f8f870'],
            ['kind' => 'common', 'object' => '97c5a932b3940a09683e924ef6a92b31a6f7c6de'],
            ['kind' => 'ready', 'object' => null],
        ],
        'hasPack' => true,
        'packBytes' => 5360,
        'packTrailer' => '7699593d62b1a50764036e7ebb48f4e3ed111268',
        'progressCount' => 12,
        'remoteProgressCount' => 9,
        'fragmentedProgressSamples' => [
            'firstCountingFragment' => "Counting objects:  15% (2/13)\rCounting objects:  23% (3/13)\r",
            'continuedCountingFragmentPrefix' => " objects:  46% (6/13)\r",
            'splitDoneSuffix' => '0% (13/13), done.',
        ],
        'packetLineCounts' => [
            'data' => 19,
            'delimiter' => 1,
            'flush' => 2,
        ],
    ],
];
