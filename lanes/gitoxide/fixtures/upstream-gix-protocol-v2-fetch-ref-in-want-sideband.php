<?php

declare(strict_types=1);

$decode = static function (string $encoded): string {
    $bytes = base64_decode(preg_replace('/\s+/', '', $encoded) ?? '', true);
    if ($bytes === false) {
        throw new RuntimeException('invalid upstream fetch ref-in-want response fixture encoding');
    }

    return $bytes;
};

return [
    'source' => [
        'repository' => 'GitoxideLabs/gitoxide',
        'commit' => '87433ed33eee9ba974111d20b854f6acb07cd4a6',
        'paths' => [
            'gix-protocol/tests/fixtures/v2/clone-ref-in-want.response',
            'gix-protocol/tests/protocol/fetch/v2.rs::ref_in_want',
            'gix-protocol/src/fetch/response/blocking_io.rs',
            'gix-protocol/src/fetch/response/mod.rs',
            'gix-packetline/src/blocking_io/sidebands.rs',
        ],
    ],
    'refInWant' => [
        'response' => $decode(<<<'BASE64'
MDAxMHdhbnRlZC1yZWZzCjAwM2Q5ZTMyMGI5MTgwZTBiNTU4MGFmNjhmYTMyNTViN2YzZDllY2Q1
YWYwIHJlZnMvaGVhZHMvbWFpbgowMDAxMDAwZHBhY2tmaWxlCjAyODUBUEFDSwAAAAIAAAADlBx4
nJWQy26CQABF9/MVs2+svJGmNh0QkapUQBTYMcPDCeUhDKL9+qZt0lXTpHd5krs4h3VZBhWCsTjD
Gk8ETcJYUHOV8LyUikKmJDJJ8lRRME5ykAzs1HRwTSuI3hitWdPCx5JWz1lSJLS+79kT5BVBmqmy
NJPhHSdwHCBNVVHGsv/+irboaQEnn9NNy3bgztpB37YctA8884sDCCBdBSbSIxvp3NF2/SEeiXX0
XZtVQ+8Unhx4rR8Y9Ri6gRE51UlCaG14+g8HMAxRidzFgktPFxFNyWGMxXg/buwrch1OHd41i8hn
sllhrXHkyrRGE7Xj8jy9dkZZMBXAXSSWETUPjIQv4bKIuWN78LlNb9vh9nXczgGcW3J5A982prP4
zQV4GRm6nl4yAHxa1Fk6afJ8gm8Pf5YDH45Tif2uAXicMzQwMDc1VcjNZlijUeR0e4r9ccaJdpf2
nHS5Infp7QYAnkQM9LMVeJxljk1PwzAMhu/+FWZMu4V0O7eTpql8CFAR9D5lrdtGS5Mocbv9fNIL
CHHwxc/r5/X9nZxikGdtJdkZzyoOEIlRkJtu6LWnTmkDQDfvAuPTS32qPw/Hclds/+1OH4fja1n/
krr8Sqiq3orV+rl6L2UMjZzJti7IXrNkOZDxFCRTZMHOmRVAAtg42+keJ2+car1qLg/KGHcN1Gl7
VZaRw0QA658C9BcWRlvCJY17bTHPRVk94mYDjRtHZduiI24GyLJsC9YJH1wfKEZYhCKpMU1MH6k2
ylFpC62ztMQzSKI/bZHCTGLeoRCRFZNJHhF8g3k6+waNyG9MtVBkqVRQwVXoZttYzR6MpU6v9DAw
MDYBfzAwMDAK
BASE64),
        'wantedRefs' => [
            [
                'object' => '9e320b9180e0b5580af68fa3255b7f3d9ecd5af0',
                'path' => 'refs/heads/main',
            ],
        ],
        'hasPack' => true,
        'packBytes' => 641,
        'packTrailer' => 'b55064a95450c155e866db58cd1e8ca54eaff47f',
        'progressCount' => 0,
        'responseBytesAfterCapabilityFlush' => 750,
    ],
];
