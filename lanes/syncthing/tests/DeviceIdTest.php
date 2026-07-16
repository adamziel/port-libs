<?php

declare(strict_types=1);

use PortLibs\Syncthing\DeviceId;

return [
    'formats upstream canonical device id variants' => static function (TestRunner $t): void {
        $formatted = 'P56IOI7-MZJNU2Y-IQGDREY-DM2MGTI-MGL3BXN-PQ6W5BM-TBBZ4TJ-XZWICQ2';
        $cases = [
            'P56IOI-7MZJNU-2IQGDR-EYDM2M-GTMGL3-BXNPQ6-W5BTBB-Z4TJXZ-WICQ',
            'P56IOI-7MZJNU2Y-IQGDR-EYDM2M-GTI-MGL3-BXNPQ6-W5BM-TBB-Z4TJXZ-WICQ2',
            'P56IOI7 MZJNU2I QGDREYD M2MGTMGL 3BXNPQ6W 5BTB BZ4T JXZWICQ',
            'P56IOI7 MZJNU2Y IQGDREY DM2MGTI MGL3BXN PQ6W5BM TBBZ4TJ XZWICQ2',
            'P56IOI7MZJNU2IQGDREYDM2MGTMGL3BXNPQ6W5BTBBZ4TJXZWICQ',
            'p56ioi7mzjnu2iqgdreydm2mgtmgl3bxnpq6w5btbbz4tjxzwicq',
            'P56IOI7MZJNU2YIQGDREYDM2MGTIMGL3BXNPQ6W5BMTBBZ4TJXZWICQ2',
            'P561017MZJNU2YIQGDREYDM2MGTIMGL3BXNPQ6W5BMT88Z4TJXZWICQ2',
            'p56ioi7mzjnu2yiqgdreydm2mgtimgl3bxnpq6w5bmtbbz4tjxzwicq2',
            'p561017mzjnu2yiqgdreydm2mgtimgl3bxnpq6w5bmt88z4tjxzwicq2',
        ];

        foreach ($cases as $case) {
            $t->same($formatted, DeviceId::fromString($case)->toString(), $case);
        }
    },
    'validates upstream device id lengths typo replacements and check digits' => static function (TestRunner $t): void {
        $formatted = 'P56IOI7-MZJNU2Y-IQGDREY-DM2MGTI-MGL3BXN-PQ6W5BM-TBBZ4TJ-XZWICQ2';

        $empty = DeviceId::fromString('');
        $t->true($empty->isEmpty());
        $t->same('', $empty->toString());
        $t->same('', $empty->shortString());

        foreach ([
            $formatted,
            'P56IOI7-MZJNU2-IQGDREY-DM2MGT-MGL3BXN-PQ6W5B-TBBZ4TJ-XZWICQ',
            'P56IOI7 MZJNU2I QGDREYD M2MGTMGL 3BXNPQ6W 5BTB BZ4T JXZWICQ',
            'P56IOI7MZJNU2IQGDREYDM2MGTMGL3BXNPQ6W5BTBBZ4TJXZWICQ',
            'p56ioi7mzjnu2iqgdreydm2mgtmgl3bxnpq6w5btbbz4tjxzwicq',
        ] as $valid) {
            $t->same($formatted, DeviceId::fromString($valid)->toString(), $valid);
        }

        $t->throws(InvalidArgumentException::class, static fn () => DeviceId::fromString('a'));
        $t->throws(InvalidArgumentException::class, static fn () => DeviceId::fromString('P56IOI7MZJNU2IQGDREYDM2MGTMGL3BXNPQ6W5BTBBZ4TJXZWICQCCCC'));
        $t->throws(InvalidArgumentException::class, static fn () => DeviceId::fromString(substr($formatted, 0, -1) . '3'));
    },
    'maps upstream device id bytes comparison and short id behavior' => static function (TestRunner $t): void {
        $formatted = 'P56IOI7-MZJNU2Y-IQGDREY-DM2MGTI-MGL3BXN-PQ6W5BM-TBBZ4TJ-XZWICQ2';
        $id = DeviceId::fromString($formatted);
        $fromBytes = DeviceId::fromBytes($id->bytes());

        $t->same($formatted, $fromBytes->toString());
        $t->same($formatted, (string) $fromBytes);
        $t->true($fromBytes->equals($id));
        $t->same(0, $fromBytes->compare($id));
        $t->same(substr($formatted, 0, DeviceId::SHORT_STRING_LENGTH), $id->shortString());

        $other = DeviceId::fromBytes(str_repeat("\xff", DeviceId::LENGTH));
        $t->same(-1, $id->compare($other));
        $t->same(1, $other->compare($id));
        $t->same(str_repeat('ff', DeviceId::LENGTH), $other->hex());
        $t->throws(InvalidArgumentException::class, static fn () => DeviceId::fromBytes('too short'));
    },
    'maps upstream device id marshalling and certificate hashing boundary' => static function (TestRunner $t): void {
        $bytes = '';
        foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 10, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32] as $byte) {
            $bytes .= chr($byte);
        }

        $first = DeviceId::fromBytes($bytes);
        $second = DeviceId::fromString($first->toString());
        $third = DeviceId::fromString($second->toString());

        $t->same($first->toString(), $third->toString());
        $t->true($third->equals($first));
        $t->same(0, $third->compare($first));

        $rawCertificate = "-----BEGIN CERTIFICATE-----\nwordpress playground peer\n-----END CERTIFICATE-----";
        $derived = DeviceId::fromRawCertificateBytes($rawCertificate);
        $t->same(hash('sha256', $rawCertificate), $derived->hex());
        $t->same(DeviceId::LENGTH, strlen($derived->bytes()));
    },
    'maps upstream luhn32 check digit behavior' => static function (TestRunner $t): void {
        $t->same('G', DeviceId::luhn32CheckDigit('AB725E4GHIQPL3ZFGT'));
        $t->throws(InvalidArgumentException::class, static fn () => DeviceId::luhn32CheckDigit('3734EJEKMRHWPZQTWYQ1'));
    },
];
