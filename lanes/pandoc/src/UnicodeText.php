<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class UnicodeText
{
    private const REPLACEMENT = "\xEF\xBF\xBD";

    /** @var array<string, string> */
    private const CANONICAL_DECOMPOSITIONS = [
        "\u{00C0}" => "A\u{0300}",
        "\u{00C1}" => "A\u{0301}",
        "\u{00C2}" => "A\u{0302}",
        "\u{00C3}" => "A\u{0303}",
        "\u{00C4}" => "A\u{0308}",
        "\u{00C5}" => "A\u{030A}",
        "\u{00C7}" => "C\u{0327}",
        "\u{00C9}" => "E\u{0301}",
        "\u{00D1}" => "N\u{0303}",
        "\u{00D3}" => "O\u{0301}",
        "\u{00D6}" => "O\u{0308}",
        "\u{00DC}" => "U\u{0308}",
        "\u{00DD}" => "Y\u{0301}",
        "\u{00E0}" => "a\u{0300}",
        "\u{00E1}" => "a\u{0301}",
        "\u{00E2}" => "a\u{0302}",
        "\u{00E3}" => "a\u{0303}",
        "\u{00E4}" => "a\u{0308}",
        "\u{00E5}" => "a\u{030A}",
        "\u{00E7}" => "c\u{0327}",
        "\u{00E9}" => "e\u{0301}",
        "\u{00F1}" => "n\u{0303}",
        "\u{00F3}" => "o\u{0301}",
        "\u{00F6}" => "o\u{0308}",
        "\u{00FC}" => "u\u{0308}",
        "\u{00FD}" => "y\u{0301}",
        "\u{0104}" => "A\u{0328}",
        "\u{0105}" => "a\u{0328}",
        "\u{0106}" => "C\u{0301}",
        "\u{0107}" => "c\u{0301}",
        "\u{010C}" => "C\u{030C}",
        "\u{010D}" => "c\u{030C}",
        "\u{010E}" => "D\u{030C}",
        "\u{010F}" => "d\u{030C}",
        "\u{0118}" => "E\u{0328}",
        "\u{0119}" => "e\u{0328}",
        "\u{011A}" => "E\u{030C}",
        "\u{011B}" => "e\u{030C}",
        "\u{0143}" => "N\u{0301}",
        "\u{0144}" => "n\u{0301}",
        "\u{0147}" => "N\u{030C}",
        "\u{0148}" => "n\u{030C}",
        "\u{0150}" => "O\u{030B}",
        "\u{0151}" => "o\u{030B}",
        "\u{0158}" => "R\u{030C}",
        "\u{0159}" => "r\u{030C}",
        "\u{015A}" => "S\u{0301}",
        "\u{015B}" => "s\u{0301}",
        "\u{0160}" => "S\u{030C}",
        "\u{0161}" => "s\u{030C}",
        "\u{016E}" => "U\u{030A}",
        "\u{016F}" => "u\u{030A}",
        "\u{0170}" => "U\u{030B}",
        "\u{0171}" => "u\u{030B}",
        "\u{0179}" => "Z\u{0301}",
        "\u{017A}" => "z\u{0301}",
        "\u{017B}" => "Z\u{0307}",
        "\u{017C}" => "z\u{0307}",
        "\u{017D}" => "Z\u{030C}",
        "\u{017E}" => "z\u{030C}",
        "\u{0218}" => "S\u{0326}",
        "\u{0219}" => "s\u{0326}",
        "\u{021A}" => "T\u{0326}",
        "\u{021B}" => "t\u{0326}",
        "\u{212B}" => "A\u{030A}",
        "\u{1E0B}" => "d\u{0307}",
        "\u{1E0C}" => "D\u{0323}",
        "\u{1E0D}" => "d\u{0323}",
    ];

    /** @var array<string, string> */
    private const COMPOSITIONS = [
        "A\u{0300}" => "\u{00C0}",
        "A\u{0301}" => "\u{00C1}",
        "A\u{0302}" => "\u{00C2}",
        "A\u{0303}" => "\u{00C3}",
        "A\u{0308}" => "\u{00C4}",
        "A\u{030A}" => "\u{00C5}",
        "A\u{0328}" => "\u{0104}",
        "C\u{0301}" => "\u{0106}",
        "C\u{030C}" => "\u{010C}",
        "C\u{0327}" => "\u{00C7}",
        "D\u{030C}" => "\u{010E}",
        "D\u{0323}" => "\u{1E0C}",
        "E\u{0301}" => "\u{00C9}",
        "E\u{030C}" => "\u{011A}",
        "E\u{0328}" => "\u{0118}",
        "N\u{0301}" => "\u{0143}",
        "N\u{030C}" => "\u{0147}",
        "N\u{0303}" => "\u{00D1}",
        "O\u{0301}" => "\u{00D3}",
        "O\u{0308}" => "\u{00D6}",
        "O\u{030B}" => "\u{0150}",
        "R\u{030C}" => "\u{0158}",
        "S\u{0301}" => "\u{015A}",
        "S\u{030C}" => "\u{0160}",
        "S\u{0326}" => "\u{0218}",
        "T\u{0326}" => "\u{021A}",
        "U\u{030A}" => "\u{016E}",
        "U\u{0308}" => "\u{00DC}",
        "U\u{030B}" => "\u{0170}",
        "Y\u{0301}" => "\u{00DD}",
        "Z\u{0301}" => "\u{0179}",
        "Z\u{0307}" => "\u{017B}",
        "Z\u{030C}" => "\u{017D}",
        "a\u{0300}" => "\u{00E0}",
        "a\u{0301}" => "\u{00E1}",
        "a\u{0302}" => "\u{00E2}",
        "a\u{0303}" => "\u{00E3}",
        "a\u{0308}" => "\u{00E4}",
        "a\u{030A}" => "\u{00E5}",
        "a\u{0328}" => "\u{0105}",
        "c\u{0301}" => "\u{0107}",
        "c\u{030C}" => "\u{010D}",
        "c\u{0327}" => "\u{00E7}",
        "d\u{030C}" => "\u{010F}",
        "d\u{0307}" => "\u{1E0B}",
        "d\u{0323}" => "\u{1E0D}",
        "e\u{0301}" => "\u{00E9}",
        "e\u{030C}" => "\u{011B}",
        "e\u{0328}" => "\u{0119}",
        "n\u{0301}" => "\u{0144}",
        "n\u{030C}" => "\u{0148}",
        "n\u{0303}" => "\u{00F1}",
        "o\u{0301}" => "\u{00F3}",
        "o\u{0308}" => "\u{00F6}",
        "o\u{030B}" => "\u{0151}",
        "r\u{030C}" => "\u{0159}",
        "s\u{0301}" => "\u{015B}",
        "s\u{030C}" => "\u{0161}",
        "s\u{0326}" => "\u{0219}",
        "t\u{0326}" => "\u{021B}",
        "u\u{030A}" => "\u{016F}",
        "u\u{0308}" => "\u{00FC}",
        "u\u{030B}" => "\u{0171}",
        "y\u{0301}" => "\u{00FD}",
        "z\u{0301}" => "\u{017A}",
        "z\u{0307}" => "\u{017C}",
        "z\u{030C}" => "\u{017E}",
    ];

    /** @var array<string, string> */
    private const COMPATIBILITY_DECOMPOSITIONS = [
        "\u{00A0}" => ' ',
        "\u{212B}" => "A\u{030A}",
        "\u{2460}" => '1',
        "\u{2461}" => '2',
        "\u{2462}" => '3',
        "\u{FB00}" => 'ff',
        "\u{FB01}" => 'fi',
        "\u{FB02}" => 'fl',
        "\u{FB03}" => 'ffi',
        "\u{FB04}" => 'ffl',
    ];

    /** @var array<int, int> */
    private const WINDOWS_1252_CONTROLS = [
        0x80 => 0x20ac,
        0x82 => 0x201a,
        0x83 => 0x0192,
        0x84 => 0x201e,
        0x85 => 0x2026,
        0x86 => 0x2020,
        0x87 => 0x2021,
        0x88 => 0x02c6,
        0x89 => 0x2030,
        0x8a => 0x0160,
        0x8b => 0x2039,
        0x8c => 0x0152,
        0x8e => 0x017d,
        0x91 => 0x2018,
        0x92 => 0x2019,
        0x93 => 0x201c,
        0x94 => 0x201d,
        0x95 => 0x2022,
        0x96 => 0x2013,
        0x97 => 0x2014,
        0x98 => 0x02dc,
        0x99 => 0x2122,
        0x9a => 0x0161,
        0x9b => 0x203a,
        0x9c => 0x0153,
        0x9e => 0x017e,
        0x9f => 0x0178,
    ];

    /** @var array<int, int> */
    private const ISO_8859_15_REPLACEMENTS = [
        0xa4 => 0x20ac,
        0xa6 => 0x0160,
        0xa8 => 0x0161,
        0xb4 => 0x017d,
        0xb8 => 0x017e,
        0xbc => 0x0152,
        0xbd => 0x0153,
        0xbe => 0x0178,
    ];

    /** @var array<int, int> */
    private const WINDOWS_1250_REPLACEMENTS = [
        0x80 => 0x20ac,
        0x82 => 0x201a,
        0x84 => 0x201e,
        0x85 => 0x2026,
        0x86 => 0x2020,
        0x87 => 0x2021,
        0x89 => 0x2030,
        0x8a => 0x0160,
        0x8b => 0x2039,
        0x8c => 0x015a,
        0x8d => 0x0164,
        0x8e => 0x017d,
        0x8f => 0x0179,
        0x91 => 0x2018,
        0x92 => 0x2019,
        0x93 => 0x201c,
        0x94 => 0x201d,
        0x95 => 0x2022,
        0x96 => 0x2013,
        0x97 => 0x2014,
        0x99 => 0x2122,
        0x9a => 0x0161,
        0x9b => 0x203a,
        0x9c => 0x015b,
        0x9d => 0x0165,
        0x9e => 0x017e,
        0x9f => 0x017a,
        0xa1 => 0x02c7,
        0xa2 => 0x02d8,
        0xa3 => 0x0141,
        0xa5 => 0x0104,
        0xaa => 0x015e,
        0xaf => 0x017b,
        0xb2 => 0x02db,
        0xb3 => 0x0142,
        0xb9 => 0x0105,
        0xba => 0x015f,
        0xbc => 0x013d,
        0xbd => 0x02dd,
        0xbe => 0x013e,
        0xbf => 0x017c,
        0xc0 => 0x0154,
        0xc3 => 0x0102,
        0xc5 => 0x0139,
        0xc6 => 0x0106,
        0xc8 => 0x010c,
        0xca => 0x0118,
        0xcc => 0x011a,
        0xcf => 0x010e,
        0xd0 => 0x0110,
        0xd1 => 0x0143,
        0xd2 => 0x0147,
        0xd5 => 0x0150,
        0xd8 => 0x0158,
        0xd9 => 0x016e,
        0xdb => 0x0170,
        0xde => 0x0162,
        0xe0 => 0x0155,
        0xe3 => 0x0103,
        0xe5 => 0x013a,
        0xe6 => 0x0107,
        0xe8 => 0x010d,
        0xea => 0x0119,
        0xec => 0x011b,
        0xef => 0x010f,
        0xf0 => 0x0111,
        0xf1 => 0x0144,
        0xf2 => 0x0148,
        0xf5 => 0x0151,
        0xf8 => 0x0159,
        0xf9 => 0x016f,
        0xfb => 0x0171,
        0xfe => 0x0163,
        0xff => 0x02d9,
    ];

    /** @var array<int, int> */
    private const ISO_8859_2_REPLACEMENTS = [
        0xa1 => 0x0104,
        0xa2 => 0x02d8,
        0xa3 => 0x0141,
        0xa5 => 0x013d,
        0xa6 => 0x015a,
        0xa9 => 0x0160,
        0xaa => 0x015e,
        0xab => 0x0164,
        0xac => 0x0179,
        0xae => 0x017d,
        0xaf => 0x017b,
        0xb1 => 0x0105,
        0xb2 => 0x02db,
        0xb3 => 0x0142,
        0xb5 => 0x013e,
        0xb6 => 0x015b,
        0xb7 => 0x02c7,
        0xb9 => 0x0161,
        0xba => 0x015f,
        0xbb => 0x0165,
        0xbc => 0x017a,
        0xbd => 0x02dd,
        0xbe => 0x017e,
        0xbf => 0x017c,
        0xc0 => 0x0154,
        0xc3 => 0x0102,
        0xc5 => 0x0139,
        0xc6 => 0x0106,
        0xc8 => 0x010c,
        0xca => 0x0118,
        0xcc => 0x011a,
        0xcf => 0x010e,
        0xd0 => 0x0110,
        0xd1 => 0x0143,
        0xd2 => 0x0147,
        0xd5 => 0x0150,
        0xd8 => 0x0158,
        0xd9 => 0x016e,
        0xdb => 0x0170,
        0xde => 0x0162,
        0xe0 => 0x0155,
        0xe3 => 0x0103,
        0xe5 => 0x013a,
        0xe6 => 0x0107,
        0xe8 => 0x010d,
        0xea => 0x0119,
        0xec => 0x011b,
        0xef => 0x010f,
        0xf0 => 0x0111,
        0xf1 => 0x0144,
        0xf2 => 0x0148,
        0xf5 => 0x0151,
        0xf8 => 0x0159,
        0xf9 => 0x016f,
        0xfb => 0x0171,
        0xfe => 0x0163,
        0xff => 0x02d9,
    ];

    /** @var array<int, int> */
    private const MAC_ROMAN_REPLACEMENTS = [
        0x80 => 0x00c4,
        0x81 => 0x00c5,
        0x82 => 0x00c7,
        0x83 => 0x00c9,
        0x84 => 0x00d1,
        0x85 => 0x00d6,
        0x86 => 0x00dc,
        0x87 => 0x00e1,
        0x88 => 0x00e0,
        0x89 => 0x00e2,
        0x8a => 0x00e4,
        0x8b => 0x00e3,
        0x8c => 0x00e5,
        0x8d => 0x00e7,
        0x8e => 0x00e9,
        0x8f => 0x00e8,
        0x90 => 0x00ea,
        0x91 => 0x00eb,
        0x92 => 0x00ed,
        0x93 => 0x00ec,
        0x94 => 0x00ee,
        0x95 => 0x00ef,
        0x96 => 0x00f1,
        0x97 => 0x00f3,
        0x98 => 0x00f2,
        0x99 => 0x00f4,
        0x9a => 0x00f6,
        0x9b => 0x00f5,
        0x9c => 0x00fa,
        0x9d => 0x00f9,
        0x9e => 0x00fb,
        0x9f => 0x00fc,
        0xa0 => 0x2020,
        0xa1 => 0x00b0,
        0xa2 => 0x00a2,
        0xa3 => 0x00a3,
        0xa4 => 0x00a7,
        0xa5 => 0x2022,
        0xa6 => 0x00b6,
        0xa7 => 0x00df,
        0xa8 => 0x00ae,
        0xa9 => 0x00a9,
        0xaa => 0x2122,
        0xab => 0x00b4,
        0xac => 0x00a8,
        0xad => 0x2260,
        0xae => 0x00c6,
        0xaf => 0x00d8,
        0xb0 => 0x221e,
        0xb1 => 0x00b1,
        0xb2 => 0x2264,
        0xb3 => 0x2265,
        0xb4 => 0x00a5,
        0xb5 => 0x00b5,
        0xb6 => 0x2202,
        0xb7 => 0x2211,
        0xb8 => 0x220f,
        0xb9 => 0x03c0,
        0xba => 0x222b,
        0xbb => 0x00aa,
        0xbc => 0x00ba,
        0xbd => 0x03a9,
        0xbe => 0x00e6,
        0xbf => 0x00f8,
        0xc0 => 0x00bf,
        0xc1 => 0x00a1,
        0xc2 => 0x00ac,
        0xc3 => 0x221a,
        0xc4 => 0x0192,
        0xc5 => 0x2248,
        0xc6 => 0x2206,
        0xc7 => 0x00ab,
        0xc8 => 0x00bb,
        0xc9 => 0x2026,
        0xca => 0x00a0,
        0xcb => 0x00c0,
        0xcc => 0x00c3,
        0xcd => 0x00d5,
        0xce => 0x0152,
        0xcf => 0x0153,
        0xd0 => 0x2013,
        0xd1 => 0x2014,
        0xd2 => 0x201c,
        0xd3 => 0x201d,
        0xd4 => 0x2018,
        0xd5 => 0x2019,
        0xd6 => 0x00f7,
        0xd7 => 0x25ca,
        0xd8 => 0x00ff,
        0xd9 => 0x0178,
        0xda => 0x2044,
        0xdb => 0x20ac,
        0xdc => 0x2039,
        0xdd => 0x203a,
        0xde => 0xfb01,
        0xdf => 0xfb02,
        0xe0 => 0x2021,
        0xe1 => 0x00b7,
        0xe2 => 0x201a,
        0xe3 => 0x201e,
        0xe4 => 0x2030,
        0xe5 => 0x00c2,
        0xe6 => 0x00ca,
        0xe7 => 0x00c1,
        0xe8 => 0x00cb,
        0xe9 => 0x00c8,
        0xea => 0x00cd,
        0xeb => 0x00ce,
        0xec => 0x00cf,
        0xed => 0x00cc,
        0xee => 0x00d3,
        0xef => 0x00d4,
        0xf0 => 0xf8ff,
        0xf1 => 0x00d2,
        0xf2 => 0x00da,
        0xf3 => 0x00db,
        0xf4 => 0x00d9,
        0xf5 => 0x0131,
        0xf6 => 0x02c6,
        0xf7 => 0x02dc,
        0xf8 => 0x00af,
        0xf9 => 0x02d8,
        0xfa => 0x02d9,
        0xfb => 0x02da,
        0xfc => 0x00b8,
        0xfd => 0x02dd,
        0xfe => 0x02db,
        0xff => 0x02c7,
    ];

    /** @var array<int, int> */
    private const JIS0208_POINTERS = [
        1 => 0x3001,
        2 => 0x3002,
        28 => 0x2015,
        32 => 0xff5e,
        60 => 0xff0d,
        78 => 0xffe5,
        321 => 0x3068,
        1128 => 0x2460,
        1669 => 0x753b,
        1740 => 0x89d2,
        1846 => 0x4e38,
        2122 => 0x8a08,
        2423 => 0x5d0e,
        3611 => 0x6ce2,
        3695 => 0x534a,
        3877 => 0x6587,
        4007 => 0x672c,
        11091 => 0x9ad9,
    ];

    /** @var array<int, int> */
    private const BIG5_PAIRS = [
        0xa141 => 0xff0c,
        0xa143 => 0x3002,
        0xa4a4 => 0x4e2d,
        0xa4e5 => 0x6587,
        0xadbb => 0x9999,
        0xb4e4 => 0x6e2f,
        0xb4fa => 0x6e2c,
        0xb8d5 => 0x8a66,
    ];

    /** @var array<int, int> */
    private const GBK_PAIRS = [
        0xa1a3 => 0x3002,
        0xa3ac => 0xff0c,
        0xb1b1 => 0x5317,
        0xb2e2 => 0x6d4b,
        0xbcf2 => 0x7b80,
        0xbea9 => 0x4eac,
        0xcad4 => 0x8bd5,
        0xcce5 => 0x4f53,
        0xcec4 => 0x6587,
        0xd6d0 => 0x4e2d,
    ];

    /** @var array<int, int> */
    private const EUC_KR_PAIRS = [
        0xb1db => 0xae00,
        0xbcad => 0xc11c,
        0xbdba => 0xc2a4,
        0xbfef => 0xc6b8,
        0xc5d7 => 0xd14c,
        0xc6ae => 0xd2b8,
        0xc7d1 => 0xd55c,
    ];

    /** @var list<int> */
    private const EAST_ASIAN_AMBIGUOUS_SINGLE_CODEPOINTS = [
        0x00a1, 0x00a4, 0x00aa, 0x00c6, 0x00d0, 0x00d7, 0x00d8, 0x00e6,
        0x00f0, 0x00fc, 0x00fe, 0x0101, 0x0111, 0x0113, 0x011b, 0x012b,
        0x0138, 0x0144, 0x014d, 0x016b, 0x01ce, 0x01d0, 0x01d2, 0x01d4,
        0x01d6, 0x01d8, 0x01da, 0x01dc, 0x0251, 0x0261, 0x02c4, 0x02c7,
        0x02cd, 0x02d0, 0x02dd, 0x02df, 0x0401, 0x0451, 0x2010, 0x2030,
        0x2035, 0x203b, 0x203e, 0x2074, 0x207f, 0x20ac, 0x2103, 0x2105,
        0x2109, 0x2113, 0x2116, 0x2126, 0x212b, 0x2189, 0x21d2, 0x21d4,
        0x21e7, 0x2200, 0x220b, 0x220f, 0x2211, 0x2215, 0x221a, 0x2223,
        0x2225, 0x222e, 0x2248, 0x224c, 0x2252, 0x2295, 0x2299, 0x22a5,
        0x22bf, 0x2312, 0x25cb, 0x25ef, 0x2609, 0x261e, 0x2642, 0x266f,
        0x26e3, 0x26fe, 0x2776, 0x2777,
    ];

    /** @var list<array{0:int, 1:int}> */
    private const EAST_ASIAN_AMBIGUOUS_RANGES = [
        [0x00a7, 0x00a8], [0x00ad, 0x00ae], [0x00b0, 0x00b4], [0x00b6, 0x00ba],
        [0x00bc, 0x00bf], [0x00de, 0x00e1], [0x00e8, 0x00ea], [0x00ec, 0x00ed],
        [0x00f2, 0x00f3], [0x00f7, 0x00fa], [0x0126, 0x0127], [0x0131, 0x0133],
        [0x013f, 0x0142], [0x0148, 0x014b], [0x0152, 0x0153], [0x0166, 0x0167],
        [0x02c9, 0x02cb], [0x02d8, 0x02db], [0x0391, 0x03a1], [0x03a3, 0x03a9],
        [0x03b1, 0x03c1], [0x03c3, 0x03c9], [0x0410, 0x044f], [0x2013, 0x2016],
        [0x2018, 0x2019], [0x201c, 0x201d], [0x2020, 0x2022], [0x2024, 0x2027],
        [0x2032, 0x2033], [0x2081, 0x2084], [0x2121, 0x2122], [0x2153, 0x2154],
        [0x215b, 0x215e], [0x2160, 0x216b], [0x2170, 0x2179], [0x2190, 0x2199],
        [0x21b8, 0x21b9], [0x2202, 0x2203], [0x2207, 0x2208], [0x221d, 0x2220],
        [0x2227, 0x222c], [0x2234, 0x2237], [0x223c, 0x223d], [0x2260, 0x2261],
        [0x2264, 0x2267], [0x226a, 0x226b], [0x226e, 0x226f], [0x2282, 0x2283],
        [0x2286, 0x2287], [0x2460, 0x24e9], [0x24eb, 0x254b], [0x2550, 0x2573],
        [0x2580, 0x258f], [0x2592, 0x2595], [0x25a0, 0x25a1], [0x25a3, 0x25a9],
        [0x25b2, 0x25b3], [0x25b6, 0x25b7], [0x25bc, 0x25bd], [0x25c0, 0x25c1],
        [0x25c6, 0x25c8], [0x25ce, 0x25d1], [0x25e2, 0x25e5], [0x2605, 0x2606],
        [0x260e, 0x260f], [0x261c, 0x261c], [0x2640, 0x2640], [0x2660, 0x2661],
        [0x2663, 0x2665], [0x2667, 0x266a], [0x266c, 0x266d], [0x269e, 0x269f],
        [0x26bf, 0x26bf], [0x26c6, 0x26cd], [0x26cf, 0x26d3], [0x26d5, 0x26e1],
        [0x26e8, 0x26e9], [0x26eb, 0x26f1], [0x26f4, 0x26f4], [0x26f6, 0x26f9],
        [0x26fb, 0x26fc], [0x273d, 0x273d], [0x2778, 0x277f], [0x2b56, 0x2b59],
        [0xe000, 0xf8ff], [0xfffd, 0xfffd],
    ];

    /**
     * @return array{text:string, encoding:string, bom:string|null, repairs:int, lineEndings:array{normalized:bool, crlf:int, cr:int, conversions:int}, normalization?:array{form:string, changed:bool, implementation:string}}
     */
    public static function decodeBytes(string $bytes, ?string $encoding = null, ?string $normalizationForm = null): array
    {
        $normalized = self::normalizeEncoding($encoding);
        $bom = null;
        if (str_starts_with($bytes, "\xFF\xFE\x00\x00")) {
            $bom = 'utf-32le';
            $bytes = substr($bytes, 4);
            $normalized = 'utf-32le';
        } elseif (str_starts_with($bytes, "\x00\x00\xFE\xFF")) {
            $bom = 'utf-32be';
            $bytes = substr($bytes, 4);
            $normalized = 'utf-32be';
        } elseif (str_starts_with($bytes, "\xEF\xBB\xBF")) {
            $bom = 'utf-8';
            $bytes = substr($bytes, 3);
            $normalized = 'utf-8';
        } elseif (str_starts_with($bytes, "\xFF\xFE")) {
            $bom = 'utf-16le';
            $bytes = substr($bytes, 2);
            $normalized = 'utf-16le';
        } elseif (str_starts_with($bytes, "\xFE\xFF")) {
            $bom = 'utf-16be';
            $bytes = substr($bytes, 2);
            $normalized = 'utf-16be';
        }

        $normalized ??= 'utf-8';
        if ($normalized === 'utf-16') {
            $normalized = $bom === 'utf-16be' ? 'utf-16be' : 'utf-16le';
        }
        if ($normalized === 'utf-32') {
            $normalized = $bom === 'utf-32le' ? 'utf-32le' : 'utf-32be';
        }

        if ($normalized === 'utf-32le' || $normalized === 'utf-32be') {
            [$text, $repairs] = self::decodeUtf32($bytes, $normalized === 'utf-32le');

            return self::decodedResult($text, $normalized, $bom, $repairs, $normalizationForm);
        }

        if ($normalized === 'utf-16le' || $normalized === 'utf-16be') {
            [$text, $repairs] = self::decodeUtf16($bytes, $normalized === 'utf-16le');

            return self::decodedResult($text, $normalized, $bom, $repairs, $normalizationForm);
        }

        if ($normalized === 'windows-1252'
            || $normalized === 'windows-1250'
            || $normalized === 'iso-8859-1'
            || $normalized === 'iso-8859-2'
            || $normalized === 'iso-8859-15'
            || $normalized === 'macintosh'
        ) {
            [$text, $repairs] = self::decodeSingleByte($bytes, $normalized);

            return self::decodedResult($text, $normalized, $bom, $repairs, $normalizationForm);
        }
        if ($normalized === 'shift_jis'
            || $normalized === 'euc-jp'
            || $normalized === 'iso-2022-jp'
            || $normalized === 'big5'
            || $normalized === 'gbk'
            || $normalized === 'euc-kr'
            || $normalized === 'hz-gb-2312'
        ) {
            [$text, $repairs] = match ($normalized) {
                'shift_jis' => self::decodeShiftJis($bytes),
                'euc-jp' => self::decodeEucJp($bytes),
                'iso-2022-jp' => self::decodeIso2022Jp($bytes),
                'big5' => self::decodeBig5($bytes),
                'gbk' => self::decodeGbk($bytes),
                'euc-kr' => self::decodeEucKr($bytes),
                default => self::decodeHzGb2312($bytes),
            };

            return self::decodedResult($text, $normalized, $bom, $repairs, $normalizationForm);
        }

        [$text, $repairs] = self::repairUtf8($bytes);

        return self::decodedResult($text, $repairs === 0 ? 'utf-8' : 'utf-8-repaired', $bom, $repairs, $normalizationForm);
    }

    public static function repair(string $text): string
    {
        return self::repairUtf8($text)[0];
    }

    /**
     * @return array{text:string, form:string, changed:bool, implementation:string}
     */
    public static function normalize(string $text, string $form = 'nfc', string $implementation = 'auto'): array
    {
        $form = self::normalizeNormalizationForm($form);
        $implementation = self::normalizeNormalizationImplementation($implementation);
        $text = self::repair($text);
        $normalized = null;
        $usedImplementation = 'fallback';
        if ($implementation !== 'fallback') {
            $normalized = self::normalizeWithIntl($text, $form);
            if ($normalized !== null) {
                $usedImplementation = 'intl';
            }
        }
        if ($normalized === null) {
            $normalized = self::normalizeWithFallback($text, $form);
        }

        return [
            'text' => $normalized,
            'form' => $form,
            'changed' => $normalized !== $text,
            'implementation' => $usedImplementation,
        ];
    }

    /**
     * @return list<string>
     */
    public static function characters(string $text): array
    {
        $text = self::repair($text);
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);

        return $chars === false ? str_split($text) : $chars;
    }

    /**
     * @return list<string>
     */
    public static function graphemes(string $text): array
    {
        $clusters = [];
        $joinNext = false;
        $indicViramaJoinNext = false;
        $regionalIndicatorRun = 0;
        foreach (self::characters($text) as $char) {
            $codepoint = self::codepoint($char);
            $combiningOrZeroWidth = self::isCombiningOrZeroWidth($codepoint);
            $emojiSkinToneModifier = self::isEmojiSkinToneModifier($codepoint);
            $clusterExtender = $combiningOrZeroWidth || self::isBoundedGraphemeSpacingMark($codepoint);
            $regionalIndicator = self::isRegionalIndicator($codepoint);
            $indicConsonant = self::isBoundedIndicConsonant($codepoint);
            $append = $clusters !== []
                && (
                    $joinNext
                    || $clusterExtender
                    || ($emojiSkinToneModifier && self::isEmojiModifierClusterBase($clusters[count($clusters) - 1]))
                    || ($indicViramaJoinNext && $indicConsonant)
                    || ($regionalIndicator && $regionalIndicatorRun === 1)
                );
            $hadIndicViramaJoinNext = $indicViramaJoinNext;

            if (!$append) {
                $clusters[] = $char;
                $regionalIndicatorRun = $regionalIndicator ? 1 : 0;
            } else {
                $clusters[count($clusters) - 1] .= $char;
                if ($regionalIndicator) {
                    $regionalIndicatorRun = min(2, $regionalIndicatorRun + 1);
                } elseif (!$clusterExtender && $codepoint !== 0x200d) {
                    $regionalIndicatorRun = 0;
                }
            }
            $joinNext = $codepoint === 0x200d;
            $indicViramaJoinNext = self::isBoundedIndicVirama($codepoint)
                || ($codepoint === 0x200d && $hadIndicViramaJoinNext);
        }

        return $clusters;
    }

    public static function displayWidth(string $text, string $ambiguousWidth = 'narrow'): int
    {
        $ambiguousColumns = self::ambiguousWidthColumns($ambiguousWidth);
        $width = 0;
        foreach (self::graphemes($text) as $cluster) {
            $width += self::graphemeDisplayWidth($cluster, $ambiguousColumns);
        }

        return $width;
    }

    /**
     * @return array{0:string, 1:string}
     */
    public static function splitAtDisplayWidth(string $text, int $width, string $ambiguousWidth = 'narrow'): array
    {
        $ambiguousColumns = self::ambiguousWidthColumns($ambiguousWidth);
        $text = self::repair($text);
        if ($width <= 0 || $text === '') {
            return ['', $text];
        }

        $head = '';
        $usedWidth = 0;
        foreach (self::graphemes($text) as $cluster) {
            $clusterWidth = self::graphemeDisplayWidth($cluster, $ambiguousColumns);
            $head .= $cluster;
            $usedWidth += $clusterWidth;

            if ($usedWidth >= $width) {
                return [$head, substr($text, strlen($head))];
            }
        }

        return [$text, ''];
    }

    /**
     * Split text at absolute display-width breakpoints.
     *
     * @param list<int> $breakpoints
     * @return list<string>
     */
    public static function splitByDisplayBreakpoints(string $text, array $breakpoints, string $ambiguousWidth = 'narrow'): array
    {
        $segments = [];
        $remaining = self::repair($text);
        $previous = 0;

        foreach ($breakpoints as $breakpoint) {
            $relativeWidth = max(0, $breakpoint - $previous);
            [$segment, $remaining] = self::splitAtDisplayWidth($remaining, $relativeWidth, $ambiguousWidth);
            $segments[] = $segment;
            $previous = $breakpoint;
        }

        $segments[] = $remaining;

        return $segments;
    }

    /**
     * Wrap text to display columns without splitting grapheme clusters.
     *
     * This is intentionally bounded: horizontal whitespace and selected
     * Unicode space separators are soft break opportunities, while Unicode hard
     * line separators reset indentation.
     *
     * @return list<string>
     */
    public static function wrapByDisplayWidth(string $text, int $width, string $subsequentIndent = '', string $ambiguousWidth = 'narrow'): array
    {
        [$text] = self::normalizeLineEndings(self::repair($text));
        if ($width <= 0) {
            return explode("\n", $text);
        }

        $physicalLines = preg_split('/\R/u', $text);
        if ($physicalLines === false) {
            $physicalLines = explode("\n", $text);
        }

        $wrapped = [];
        foreach ($physicalLines as $line) {
            foreach (self::wrapDisplayLine($line, $width, $subsequentIndent, $ambiguousWidth) as $wrappedLine) {
                $wrapped[] = $wrappedLine;
            }
        }

        return $wrapped;
    }

    public static function padDisplay(string $text, int $width, string $alignment = 'left', string $ambiguousWidth = 'narrow'): string
    {
        $padding = max(0, $width - self::displayWidth($text, $ambiguousWidth));

        return match ($alignment) {
            'right' => str_repeat(' ', $padding) . $text,
            'center' => str_repeat(' ', intdiv($padding, 2)) . $text . str_repeat(' ', $padding - intdiv($padding, 2)),
            default => $text . str_repeat(' ', $padding),
        };
    }

    /**
     * @return array{0:string, 1:array{normalized:bool, crlf:int, cr:int, conversions:int}}
     */
    public static function normalizeLineEndings(string $text): array
    {
        $crlf = substr_count($text, "\r\n");
        $normalized = str_replace("\r\n", "\n", $text);
        $cr = substr_count($normalized, "\r");
        if ($cr > 0) {
            $normalized = str_replace("\r", "\n", $normalized);
        }

        return [
            $normalized,
            [
                'normalized' => $crlf > 0 || $cr > 0,
                'crlf' => $crlf,
                'cr' => $cr,
                'conversions' => $crlf + $cr,
            ],
        ];
    }

    /**
     * @return array{text:string, encoding:string, bom:string|null, repairs:int, lineEndings:array{normalized:bool, crlf:int, cr:int, conversions:int}, normalization?:array{form:string, changed:bool, implementation:string}}
     */
    private static function decodedResult(string $text, string $encoding, ?string $bom, int $repairs, ?string $normalizationForm): array
    {
        [$text, $lineEndings] = self::normalizeLineEndings($text);
        $result = [
            'text' => $text,
            'encoding' => $encoding,
            'bom' => $bom,
            'repairs' => $repairs,
            'lineEndings' => $lineEndings,
        ];

        if ($normalizationForm !== null && trim($normalizationForm) !== '') {
            $normalization = self::normalize($text, $normalizationForm);
            $result['text'] = $normalization['text'];
            $result['normalization'] = [
                'form' => $normalization['form'],
                'changed' => $normalization['changed'],
                'implementation' => $normalization['implementation'],
            ];
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    private static function wrapDisplayLine(string $line, int $width, string $subsequentIndent, string $ambiguousWidth): array
    {
        $fragments = self::wrapFragments($line);
        if ($fragments === []) {
            return [''];
        }

        $lines = [];
        $current = '';
        foreach ($fragments as $fragment) {
            $text = $fragment['text'];
            if ($current === '') {
                [$lines, $current] = self::startWrappedToken($lines, $text, $width, $subsequentIndent, $ambiguousWidth);
                continue;
            }

            $candidate = $current . self::wrapSeparatorText($fragment['separator'], $fragment['separatorText']) . $text;
            if (self::displayWidth($candidate, $ambiguousWidth) <= self::wrapContentWidth(count($lines), $width, $subsequentIndent, $ambiguousWidth)) {
                $current = $candidate;
                continue;
            }

            $lines[] = self::wrapLinePrefix(count($lines), $subsequentIndent)
                . self::wrapBreakLineText($current, $fragment['separator'], count($lines), $width, $subsequentIndent, $ambiguousWidth);
            [$lines, $current] = self::startWrappedToken($lines, $text, $width, $subsequentIndent, $ambiguousWidth);
        }

        if ($current !== '') {
            $lines[] = self::wrapLinePrefix(count($lines), $subsequentIndent) . $current;
        }

        return $lines === [] ? [''] : $lines;
    }

    /**
     * @return list<array{text:string, separator:string, separatorText:string}>
     */
    private static function wrapFragments(string $line): array
    {
        $line = trim($line);
        if ($line === '') {
            return [];
        }

        $fragments = [];
        $buffer = '';
        $separator = 'none';
        $separatorText = '';
        foreach (self::characters($line) as $char) {
            $codepoint = self::codepoint($char);
            if (self::isWrapWhitespace($codepoint)) {
                self::appendWrapFragment($fragments, $separator, $separatorText, $buffer);
                $buffer = '';
                $separator = 'space';
                $separatorText = self::wrapWhitespaceSeparatorText($codepoint, $char);
                continue;
            }
            if ($codepoint === 0x200b) {
                self::appendWrapFragment($fragments, $separator, $separatorText, $buffer);
                $buffer = '';
                $separator = 'soft';
                $separatorText = '';
                continue;
            }
            if ($codepoint === 0x00ad) {
                self::appendWrapFragment($fragments, $separator, $separatorText, $buffer);
                $buffer = '';
                $separator = 'soft-hyphen';
                $separatorText = '';
                continue;
            }

            $buffer .= $char;
        }
        self::appendWrapFragment($fragments, $separator, $separatorText, $buffer);

        return $fragments;
    }

    /**
     * @param list<array{text:string, separator:string, separatorText:string}> $fragments
     */
    private static function appendWrapFragment(array &$fragments, string $separator, string $separatorText, string $buffer): void
    {
        if ($buffer === '') {
            return;
        }

        $fragments[] = [
            'text' => $buffer,
            'separator' => $separator,
            'separatorText' => $separatorText,
        ];
    }

    private static function wrapSeparatorText(string $separator, string $separatorText): string
    {
        return $separator === 'space' ? $separatorText : '';
    }

    private static function wrapWhitespaceSeparatorText(int $codepoint, string $char): string
    {
        return $codepoint <= 0x20 ? ' ' : $char;
    }

    private static function wrapBreakLineText(
        string $line,
        string $separator,
        int $lineIndex,
        int $width,
        string $subsequentIndent,
        string $ambiguousWidth
    ): string {
        if ($separator !== 'soft-hyphen') {
            return $line;
        }

        $hyphenated = $line . '-';
        if (self::displayWidth($hyphenated, $ambiguousWidth) <= self::wrapContentWidth($lineIndex, $width, $subsequentIndent, $ambiguousWidth)) {
            return $hyphenated;
        }

        return $line;
    }

    private static function isWrapWhitespace(int $codepoint): bool
    {
        return $codepoint === 0x20
            || $codepoint === 0x09
            || $codepoint === 0x0c
            || $codepoint === 0x0b
            || $codepoint === 0x1680
            || ($codepoint >= 0x2000 && $codepoint <= 0x200a && $codepoint !== 0x2007)
            || $codepoint === 0x205f
            || $codepoint === 0x3000;
    }

    /**
     * @param list<string> $lines
     * @return array{0:list<string>, 1:string}
     */
    private static function startWrappedToken(array $lines, string $token, int $width, string $subsequentIndent, string $ambiguousWidth): array
    {
        while ($token !== '') {
            $limit = self::wrapContentWidth(count($lines), $width, $subsequentIndent, $ambiguousWidth);
            if (self::displayWidth($token, $ambiguousWidth) <= $limit) {
                return [$lines, $token];
            }

            [$segment, $token] = self::splitAtDisplayWidth($token, $limit, $ambiguousWidth);
            if ($segment === '') {
                [$segment, $token] = self::splitAtDisplayWidth($token, 1, $ambiguousWidth);
            }
            $lines[] = self::wrapLinePrefix(count($lines), $subsequentIndent) . $segment;
        }

        return [$lines, ''];
    }

    private static function wrapContentWidth(int $lineIndex, int $width, string $subsequentIndent, string $ambiguousWidth): int
    {
        if ($lineIndex === 0) {
            return max(1, $width);
        }

        return max(1, $width - self::displayWidth($subsequentIndent, $ambiguousWidth));
    }

    private static function wrapLinePrefix(int $lineIndex, string $subsequentIndent): string
    {
        return $lineIndex === 0 ? '' : $subsequentIndent;
    }

    private static function normalizeEncoding(?string $encoding): ?string
    {
        if ($encoding === null || trim($encoding) === '') {
            return null;
        }

        $key = strtolower(str_replace(['-', '_', ' '], '', trim($encoding)));

        return match ($key) {
            'utf8' => 'utf-8',
            'utf16' => 'utf-16',
            'utf16le' => 'utf-16le',
            'utf16be' => 'utf-16be',
            'utf32', 'ucs4' => 'utf-32',
            'utf32le', 'ucs4le' => 'utf-32le',
            'utf32be', 'ucs4be' => 'utf-32be',
            'windows1252', 'cp1252', 'msansi' => 'windows-1252',
            'windows1250', 'cp1250', 'microsoftcp1250' => 'windows-1250',
            'iso88591', 'latin1', 'latin-1' => 'iso-8859-1',
            'iso88592', 'iso88592:1987', 'latin2', 'latin-2', 'l2', 'isoir101', 'csisolatin2' => 'iso-8859-2',
            'iso885915', 'iso8859151999', 'latin9', 'latin-9' => 'iso-8859-15',
            'macintosh', 'macroman', 'mac-roman', 'xmacroman', 'x-mac-roman', 'mac' => 'macintosh',
            'csshiftjis', 'ms932', 'mskanji', 'shiftjis', 'sjis', 'windows31j', 'xsjis', 'cp932' => 'shift_jis',
            'cseucpkdfmtjapanese', 'eucjp', 'xeucjp' => 'euc-jp',
            'iso2022jp', 'csiso2022jp' => 'iso-2022-jp',
            'big5', 'big5hkscs', 'big5hk', 'cnbig5', 'csbig5', 'xxbig5' => 'big5',
            'gbk', 'gb18030', 'gb2312', 'gb2312:1980', 'csgb2312', 'csiso58gb231280',
            'cp936', 'ms936', 'windows936', 'xgbk', 'xcp936', 'euccn' => 'gbk',
            'euckr', 'cseuckr', 'csksc56011987', 'korean', 'isoir149', 'ksc5601', 'ksc56011987',
            'ksc56011989', 'windows949', 'cp949', 'ms949', 'uhc' => 'euc-kr',
            'hzgb2312', 'hz' => 'hz-gb-2312',
            default => 'utf-8',
        };
    }

    private static function normalizeNormalizationForm(string $form): string
    {
        $key = strtolower(str_replace(['-', '_', ' '], '', trim($form)));

        return match ($key) {
            'nfc', 'c', 'formc', 'composed', 'canonicalcomposition' => 'nfc',
            'nfd', 'd', 'formd', 'decomposed', 'canonicaldecomposition' => 'nfd',
            'nfkc', 'kc', 'formkc', 'compatibilitycomposition' => 'nfkc',
            'nfkd', 'kd', 'formkd', 'compatibilitydecomposition' => 'nfkd',
            default => throw new \InvalidArgumentException("Unsupported Unicode normalization form: {$form}"),
        };
    }

    private static function normalizeNormalizationImplementation(string $implementation): string
    {
        $key = strtolower(str_replace(['-', '_', ' '], '', trim($implementation)));

        return match ($key) {
            '', 'auto', 'native', 'default' => 'auto',
            'intl', 'normalizer' => 'intl',
            'fallback', 'php', 'boundedphp' => 'fallback',
            default => throw new \InvalidArgumentException("Unsupported Unicode normalization implementation: {$implementation}"),
        };
    }

    private static function normalizeWithIntl(string $text, string $form): ?string
    {
        if (!class_exists(\Normalizer::class)) {
            return null;
        }

        $constant = match ($form) {
            'nfc' => \Normalizer::FORM_C,
            'nfd' => \Normalizer::FORM_D,
            'nfkc' => \Normalizer::FORM_KC,
            'nfkd' => \Normalizer::FORM_KD,
        };
        $normalized = \Normalizer::normalize($text, $constant);

        return $normalized === false ? null : $normalized;
    }

    private static function normalizeWithFallback(string $text, string $form): string
    {
        $compatibility = $form === 'nfkc' || $form === 'nfkd';
        $decomposed = self::decomposeFallback($text, $compatibility);
        if ($form === 'nfd' || $form === 'nfkd') {
            return $decomposed;
        }

        return self::composeFallback($decomposed);
    }

    private static function decomposeFallback(string $text, bool $compatibility): string
    {
        $decomposed = '';
        foreach (self::characters($text) as $char) {
            if ($compatibility && isset(self::COMPATIBILITY_DECOMPOSITIONS[$char])) {
                $decomposed .= self::decomposeFallback(self::COMPATIBILITY_DECOMPOSITIONS[$char], true);
                continue;
            }

            $decomposed .= self::CANONICAL_DECOMPOSITIONS[$char] ?? $char;
        }

        return self::orderCanonicalCombiningMarks($decomposed);
    }

    private static function composeFallback(string $text): string
    {
        $out = [];
        $starterIndex = null;
        $lastCombiningClass = 0;
        foreach (self::characters($text) as $char) {
            $combiningClass = self::canonicalCombiningClass(self::codepoint($char));
            if ($starterIndex !== null && $combiningClass > 0) {
                $candidate = $out[$starterIndex] . $char;
                if (isset(self::COMPOSITIONS[$candidate]) && ($lastCombiningClass === 0 || $lastCombiningClass < $combiningClass)) {
                    $out[$starterIndex] = self::COMPOSITIONS[$candidate];
                    continue;
                }
            }

            $out[] = $char;
            if ($combiningClass === 0) {
                $starterIndex = count($out) - 1;
                $lastCombiningClass = 0;
            } else {
                $lastCombiningClass = $combiningClass;
            }
        }

        return implode('', $out);
    }

    private static function orderCanonicalCombiningMarks(string $text): string
    {
        $out = '';
        $starter = '';
        $marks = [];
        foreach (self::characters($text) as $char) {
            $combiningClass = self::canonicalCombiningClass(self::codepoint($char));
            if ($combiningClass === 0) {
                $out .= self::orderedCanonicalCluster($starter, $marks);
                $starter = $char;
                $marks = [];
                continue;
            }

            $marks[] = ['char' => $char, 'class' => $combiningClass, 'order' => count($marks)];
        }

        return $out . self::orderedCanonicalCluster($starter, $marks);
    }

    /**
     * @param list<array{char:string, class:int, order:int}> $marks
     */
    private static function orderedCanonicalCluster(string $starter, array $marks): string
    {
        if ($marks === []) {
            return $starter;
        }

        usort($marks, static fn (array $a, array $b): int => $a['class'] <=> $b['class'] ?: $a['order'] <=> $b['order']);
        $cluster = $starter;
        foreach ($marks as $mark) {
            $cluster .= $mark['char'];
        }

        return $cluster;
    }

    private static function canonicalCombiningClass(int $codepoint): int
    {
        return match ($codepoint) {
            0x0315, 0x031B, 0x0321, 0x0322, 0x0327, 0x0328 => 202,
            0x0316, 0x0317, 0x0318, 0x0319, 0x031C, 0x031D, 0x031E, 0x031F,
            0x0320, 0x0323, 0x0324, 0x0325, 0x0326, 0x0329, 0x032A, 0x032B, 0x032C,
            0x032D, 0x032E, 0x032F, 0x0330, 0x0331, 0x0332, 0x0333, 0x0339,
            0x033A, 0x033B, 0x033C, 0x0345 => 220,
            0x0300, 0x0301, 0x0302, 0x0303, 0x0304, 0x0305, 0x0306, 0x0307,
            0x0308, 0x0309, 0x030A, 0x030B, 0x030C, 0x030D, 0x030E, 0x030F,
            0x0310, 0x0311, 0x0312, 0x0313, 0x0314, 0x033D, 0x033E, 0x033F,
            0x0340, 0x0341, 0x0342, 0x0343, 0x0344, 0x0346, 0x034A, 0x034B,
            0x034C, 0x0350, 0x0351, 0x0352, 0x0357 => 230,
            default => 0,
        };
    }

    /**
     * @return array{0:string, 1:int}
     */
    private static function repairUtf8(string $bytes): array
    {
        if ($bytes === '' || preg_match('//u', $bytes) === 1) {
            return [$bytes, 0];
        }

        $out = '';
        $repairs = 0;
        $length = strlen($bytes);
        for ($offset = 0; $offset < $length;) {
            $first = ord($bytes[$offset]);
            if ($first <= 0x7f) {
                $out .= $bytes[$offset];
                $offset++;
                continue;
            }

            $sequenceLength = match (true) {
                $first >= 0xc2 && $first <= 0xdf => 2,
                $first >= 0xe0 && $first <= 0xef => 3,
                $first >= 0xf0 && $first <= 0xf4 => 4,
                default => 0,
            };

            if ($sequenceLength === 0 || $offset + $sequenceLength > $length) {
                $out .= self::REPLACEMENT;
                $repairs++;
                $offset++;
                continue;
            }

            $bytesAt = [];
            for ($index = 0; $index < $sequenceLength; $index++) {
                $bytesAt[] = ord($bytes[$offset + $index]);
            }

            if (!self::isValidUtf8Sequence($bytesAt)) {
                $out .= self::REPLACEMENT;
                $repairs++;
                $offset++;
                continue;
            }

            $out .= substr($bytes, $offset, $sequenceLength);
            $offset += $sequenceLength;
        }

        return [$out, $repairs];
    }

    /**
     * @param list<int> $bytes
     */
    private static function isValidUtf8Sequence(array $bytes): bool
    {
        foreach (array_slice($bytes, 1) as $byte) {
            if ($byte < 0x80 || $byte > 0xbf) {
                return false;
            }
        }

        $first = $bytes[0];
        if (count($bytes) === 3) {
            $second = $bytes[1];
            if ($first === 0xe0 && $second < 0xa0) {
                return false;
            }
            if ($first === 0xed && $second > 0x9f) {
                return false;
            }
        }
        if (count($bytes) === 4) {
            $second = $bytes[1];
            if ($first === 0xf0 && $second < 0x90) {
                return false;
            }
            if ($first === 0xf4 && $second > 0x8f) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{0:string, 1:int}
     */
    private static function decodeUtf32(string $bytes, bool $littleEndian): array
    {
        $out = '';
        $repairs = 0;
        $length = strlen($bytes);
        for ($offset = 0; $offset < $length; $offset += 4) {
            if ($offset + 3 >= $length) {
                $out .= self::REPLACEMENT;
                $repairs++;
                break;
            }

            $codepoint = self::u32($bytes, $offset, $littleEndian);
            if ($codepoint > 0x10ffff || ($codepoint >= 0xd800 && $codepoint <= 0xdfff)) {
                $out .= self::REPLACEMENT;
                $repairs++;
                continue;
            }

            $out .= self::fromCodepoint($codepoint);
        }

        return [$out, $repairs];
    }

    /**
     * @return array{0:string, 1:int}
     */
    private static function decodeUtf16(string $bytes, bool $littleEndian): array
    {
        $out = '';
        $repairs = 0;
        $length = strlen($bytes);
        for ($offset = 0; $offset < $length; $offset += 2) {
            if ($offset + 1 >= $length) {
                $out .= self::REPLACEMENT;
                $repairs++;
                break;
            }

            $unit = self::u16($bytes, $offset, $littleEndian);
            if ($unit >= 0xd800 && $unit <= 0xdbff) {
                if ($offset + 3 >= $length) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    continue;
                }
                $trail = self::u16($bytes, $offset + 2, $littleEndian);
                if ($trail < 0xdc00 || $trail > 0xdfff) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    continue;
                }
                $out .= self::fromCodepoint(0x10000 + (($unit - 0xd800) << 10) + ($trail - 0xdc00));
                $offset += 2;
                continue;
            }

            if ($unit >= 0xdc00 && $unit <= 0xdfff) {
                $out .= self::REPLACEMENT;
                $repairs++;
                continue;
            }

            $out .= self::fromCodepoint($unit);
        }

        return [$out, $repairs];
    }

    private static function u16(string $bytes, int $offset, bool $littleEndian): int
    {
        $first = ord($bytes[$offset]);
        $second = ord($bytes[$offset + 1]);

        return $littleEndian ? ($first | ($second << 8)) : (($first << 8) | $second);
    }

    private static function u32(string $bytes, int $offset, bool $littleEndian): int
    {
        $first = ord($bytes[$offset]);
        $second = ord($bytes[$offset + 1]);
        $third = ord($bytes[$offset + 2]);
        $fourth = ord($bytes[$offset + 3]);

        if ($littleEndian) {
            return $first | ($second << 8) | ($third << 16) | ($fourth << 24);
        }

        return ($first << 24) | ($second << 16) | ($third << 8) | $fourth;
    }

    /**
     * @return array{0:string, 1:int}
     */
    private static function decodeSingleByte(string $bytes, string $encoding): array
    {
        $out = '';
        $repairs = 0;
        for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset++) {
            $byte = ord($bytes[$offset]);
            if ($encoding === 'windows-1252' && $byte >= 0x80 && $byte <= 0x9f) {
                if (isset(self::WINDOWS_1252_CONTROLS[$byte])) {
                    $out .= self::fromCodepoint(self::WINDOWS_1252_CONTROLS[$byte]);
                } else {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                }
                continue;
            }
            if ($encoding === 'iso-8859-15' && isset(self::ISO_8859_15_REPLACEMENTS[$byte])) {
                $out .= self::fromCodepoint(self::ISO_8859_15_REPLACEMENTS[$byte]);
                continue;
            }
            if ($encoding === 'windows-1250' && isset(self::WINDOWS_1250_REPLACEMENTS[$byte])) {
                $out .= self::fromCodepoint(self::WINDOWS_1250_REPLACEMENTS[$byte]);
                continue;
            }
            if ($encoding === 'iso-8859-2' && isset(self::ISO_8859_2_REPLACEMENTS[$byte])) {
                $out .= self::fromCodepoint(self::ISO_8859_2_REPLACEMENTS[$byte]);
                continue;
            }
            if ($encoding === 'macintosh' && $byte >= 0x80) {
                $out .= self::fromCodepoint(self::MAC_ROMAN_REPLACEMENTS[$byte]);
                continue;
            }

            $out .= self::fromCodepoint($byte);
        }

        return [$out, $repairs];
    }

    /**
     * @return array{0:string, 1:int}
     */
    private static function decodeShiftJis(string $bytes): array
    {
        $out = '';
        $repairs = 0;
        $length = strlen($bytes);
        for ($offset = 0; $offset < $length; $offset++) {
            $byte = ord($bytes[$offset]);
            if ($byte <= 0x7f || $byte === 0x80) {
                $out .= self::fromCodepoint($byte);
                continue;
            }
            if ($byte >= 0xa1 && $byte <= 0xdf) {
                $out .= self::fromCodepoint(0xff61 + $byte - 0xa1);
                continue;
            }
            if (($byte >= 0x81 && $byte <= 0x9f) || ($byte >= 0xe0 && $byte <= 0xfc)) {
                if ($offset + 1 >= $length) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    continue;
                }

                $trail = ord($bytes[$offset + 1]);
                $pointer = self::shiftJisPointer($byte, $trail);
                if ($pointer === null) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    if ($trail > 0x7f) {
                        $offset++;
                    }
                    continue;
                }
                if (!isset(self::JIS0208_POINTERS[$pointer])) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    $offset++;
                    continue;
                }

                $out .= self::fromCodepoint(self::JIS0208_POINTERS[$pointer]);
                $offset++;
                continue;
            }

            $out .= self::REPLACEMENT;
            $repairs++;
        }

        return [$out, $repairs];
    }

    private static function shiftJisPointer(int $leading, int $trailing): ?int
    {
        if (!(($trailing >= 0x40 && $trailing <= 0x7e) || ($trailing >= 0x80 && $trailing <= 0xfc))) {
            return null;
        }

        $offset = $trailing < 0x7f ? 0x40 : 0x41;
        $leadingOffset = $leading < 0xa0 ? 0x81 : 0xc1;

        return (($leading - $leadingOffset) * 188) + $trailing - $offset;
    }

    /**
     * @return array{0:string, 1:int}
     */
    private static function decodeEucJp(string $bytes): array
    {
        $out = '';
        $repairs = 0;
        $length = strlen($bytes);
        for ($offset = 0; $offset < $length; $offset++) {
            $byte = ord($bytes[$offset]);
            if ($byte <= 0x7f) {
                $out .= self::fromCodepoint($byte);
                continue;
            }
            if ($byte === 0x8e) {
                if ($offset + 1 >= $length) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    continue;
                }

                $trail = ord($bytes[$offset + 1]);
                if ($trail < 0xa1 || $trail > 0xdf) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    if ($trail > 0x7f) {
                        $offset++;
                    }
                    continue;
                }

                $out .= self::fromCodepoint(0xff61 + $trail - 0xa1);
                $offset++;
                continue;
            }
            if ($byte >= 0xa1 && $byte <= 0xfe) {
                if ($offset + 1 >= $length) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    continue;
                }

                $trail = ord($bytes[$offset + 1]);
                if ($trail < 0xa1 || $trail > 0xfe) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    if ($trail > 0x7f) {
                        $offset++;
                    }
                    continue;
                }

                $pointer = (($byte - 0xa1) * 94) + $trail - 0xa1;
                if (!isset(self::JIS0208_POINTERS[$pointer])) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    $offset++;
                    continue;
                }

                $out .= self::fromCodepoint(self::JIS0208_POINTERS[$pointer]);
                $offset++;
                continue;
            }

            $out .= self::REPLACEMENT;
            $repairs++;
        }

        return [$out, $repairs];
    }

    /**
     * @return array{0:string, 1:int}
     */
    private static function decodeIso2022Jp(string $bytes): array
    {
        $out = '';
        $repairs = 0;
        $state = 'ascii';
        $length = strlen($bytes);
        for ($offset = 0; $offset < $length; $offset++) {
            $byte = ord($bytes[$offset]);
            if ($byte === 0x1b) {
                $escape = self::iso2022JpEscapeState($bytes, $offset);
                if ($escape === null) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    $offset += min(2, $length - $offset - 1);
                    $state = 'ascii';
                    continue;
                }

                $state = $escape['state'];
                $offset += 2;
                continue;
            }

            if ($state === 'ascii') {
                if ($byte <= 0x7f) {
                    $out .= self::fromCodepoint($byte);
                } else {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                }
                continue;
            }

            if ($state === 'roman') {
                if ($byte <= 0x7f) {
                    $out .= match ($byte) {
                        0x5c => "\u{00A5}",
                        0x7e => "\u{203E}",
                        default => self::fromCodepoint($byte),
                    };
                } else {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                }
                continue;
            }

            if ($state === 'katakana') {
                if ($byte >= 0x21 && $byte <= 0x5f) {
                    $out .= self::fromCodepoint(0xff61 + $byte - 0x21);
                } elseif ($byte <= 0x20) {
                    $out .= self::fromCodepoint($byte);
                } else {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    if ($byte <= 0x7f) {
                        $state = 'ascii';
                    }
                }
                continue;
            }

            if ($byte <= 0x20) {
                $out .= self::fromCodepoint($byte);
                continue;
            }
            if ($byte < 0x21 || $byte > 0x7e || $offset + 1 >= $length) {
                $out .= self::REPLACEMENT;
                $repairs++;
                continue;
            }

            $trail = ord($bytes[$offset + 1]);
            if ($trail < 0x21 || $trail > 0x7e) {
                $out .= self::REPLACEMENT;
                $repairs++;
                if ($trail > 0x7f) {
                    $offset++;
                } else {
                    $state = 'ascii';
                }
                continue;
            }

            $pointer = (($byte - 0x21) * 94) + $trail - 0x21;
            if (!isset(self::JIS0208_POINTERS[$pointer])) {
                $out .= self::REPLACEMENT;
                $repairs++;
                $offset++;
                continue;
            }

            $out .= self::fromCodepoint(self::JIS0208_POINTERS[$pointer]);
            $offset++;
        }

        return [$out, $repairs];
    }

    /**
     * @return array{0:string, 1:int}
     */
    private static function decodeBig5(string $bytes): array
    {
        $out = '';
        $repairs = 0;
        $length = strlen($bytes);
        for ($offset = 0; $offset < $length; $offset++) {
            $byte = ord($bytes[$offset]);
            if ($byte <= 0x7f) {
                $out .= self::fromCodepoint($byte);
                continue;
            }

            if ($byte < 0x81 || $byte > 0xfe || $offset + 1 >= $length) {
                $out .= self::REPLACEMENT;
                $repairs++;
                continue;
            }

            $trail = ord($bytes[$offset + 1]);
            if (!(($trail >= 0x40 && $trail <= 0x7e) || ($trail >= 0xa1 && $trail <= 0xfe))) {
                $out .= self::REPLACEMENT;
                $repairs++;
                if ($trail > 0x7f) {
                    $offset++;
                }
                continue;
            }

            $pair = ($byte << 8) | $trail;
            if (!isset(self::BIG5_PAIRS[$pair])) {
                $out .= self::REPLACEMENT;
                $repairs++;
                $offset++;
                continue;
            }

            $out .= self::fromCodepoint(self::BIG5_PAIRS[$pair]);
            $offset++;
        }

        return [$out, $repairs];
    }

    /**
     * @return array{0:string, 1:int}
     */
    private static function decodeGbk(string $bytes): array
    {
        $out = '';
        $repairs = 0;
        $length = strlen($bytes);
        for ($offset = 0; $offset < $length; $offset++) {
            $byte = ord($bytes[$offset]);
            if ($byte <= 0x7f) {
                $out .= self::fromCodepoint($byte);
                continue;
            }

            if ($byte < 0x81 || $byte > 0xfe || $offset + 1 >= $length) {
                $out .= self::REPLACEMENT;
                $repairs++;
                continue;
            }

            $trail = ord($bytes[$offset + 1]);
            if (!(($trail >= 0x40 && $trail <= 0x7e) || ($trail >= 0x80 && $trail <= 0xfe))) {
                $out .= self::REPLACEMENT;
                $repairs++;
                if ($trail > 0x7f) {
                    $offset++;
                }
                continue;
            }

            $pair = ($byte << 8) | $trail;
            if (!isset(self::GBK_PAIRS[$pair])) {
                $out .= self::REPLACEMENT;
                $repairs++;
                $offset++;
                continue;
            }

            $out .= self::fromCodepoint(self::GBK_PAIRS[$pair]);
            $offset++;
        }

        return [$out, $repairs];
    }

    /**
     * @return array{0:string, 1:int}
     */
    private static function decodeEucKr(string $bytes): array
    {
        $out = '';
        $repairs = 0;
        $length = strlen($bytes);
        for ($offset = 0; $offset < $length; $offset++) {
            $byte = ord($bytes[$offset]);
            if ($byte <= 0x7f) {
                $out .= self::fromCodepoint($byte);
                continue;
            }

            if ($byte < 0x81 || $byte > 0xfe || $offset + 1 >= $length) {
                $out .= self::REPLACEMENT;
                $repairs++;
                continue;
            }

            $trail = ord($bytes[$offset + 1]);
            if ($trail < 0x41 || $trail === 0x7f || $trail > 0xfe) {
                $out .= self::REPLACEMENT;
                $repairs++;
                if ($trail > 0x7f) {
                    $offset++;
                }
                continue;
            }

            $pair = ($byte << 8) | $trail;
            if (!isset(self::EUC_KR_PAIRS[$pair])) {
                $out .= self::REPLACEMENT;
                $repairs++;
                $offset++;
                continue;
            }

            $out .= self::fromCodepoint(self::EUC_KR_PAIRS[$pair]);
            $offset++;
        }

        return [$out, $repairs];
    }

    /**
     * @return array{0:string, 1:int}
     */
    private static function decodeHzGb2312(string $bytes): array
    {
        $out = '';
        $repairs = 0;
        $state = 'ascii';
        $length = strlen($bytes);
        for ($offset = 0; $offset < $length; $offset++) {
            $byte = ord($bytes[$offset]);
            if ($byte === 0x7e) {
                if ($offset + 1 >= $length) {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                    continue;
                }

                $next = ord($bytes[$offset + 1]);
                if ($next === 0x7b) {
                    $state = 'gb';
                    $offset++;
                    continue;
                }
                if ($next === 0x7d) {
                    $state = 'ascii';
                    $offset++;
                    continue;
                }
                if ($next === 0x7e) {
                    $out .= '~';
                    $offset++;
                    continue;
                }
                if ($next === 0x0a) {
                    $offset++;
                    continue;
                }
                if ($next === 0x0d) {
                    if ($offset + 2 < $length && ord($bytes[$offset + 2]) === 0x0a) {
                        $offset += 2;
                        continue;
                    }
                    $offset++;
                    continue;
                }

                $out .= self::REPLACEMENT;
                $repairs++;
                $offset++;
                continue;
            }

            if ($state === 'ascii') {
                if ($byte <= 0x7f) {
                    $out .= self::fromCodepoint($byte);
                } else {
                    $out .= self::REPLACEMENT;
                    $repairs++;
                }
                continue;
            }

            if ($byte <= 0x20) {
                $out .= self::fromCodepoint($byte);
                continue;
            }
            if ($byte < 0x21 || $byte > 0x7e || $offset + 1 >= $length) {
                $out .= self::REPLACEMENT;
                $repairs++;
                continue;
            }

            $trail = ord($bytes[$offset + 1]);
            if ($trail === 0x7e && $offset + 2 < $length && ord($bytes[$offset + 2]) === 0x7d) {
                $out .= self::REPLACEMENT;
                $repairs++;
                continue;
            }
            if ($trail < 0x21 || $trail > 0x7e) {
                $out .= self::REPLACEMENT;
                $repairs++;
                continue;
            }

            $pair = (($byte + 0x80) << 8) | ($trail + 0x80);
            if (!isset(self::GBK_PAIRS[$pair])) {
                $out .= self::REPLACEMENT;
                $repairs++;
                $offset++;
                continue;
            }

            $out .= self::fromCodepoint(self::GBK_PAIRS[$pair]);
            $offset++;
        }

        return [$out, $repairs];
    }

    /**
     * @return array{state:string}|null
     */
    private static function iso2022JpEscapeState(string $bytes, int $offset): ?array
    {
        if ($offset + 2 >= strlen($bytes)) {
            return null;
        }

        $first = ord($bytes[$offset + 1]);
        $second = ord($bytes[$offset + 2]);

        if ($first === 0x24 && ($second === 0x40 || $second === 0x42)) {
            return ['state' => 'jis0208'];
        }
        if ($first !== 0x28) {
            return null;
        }

        return match ($second) {
            0x42 => ['state' => 'ascii'],
            0x49 => ['state' => 'katakana'],
            0x4a => ['state' => 'roman'],
            default => null,
        };
    }

    private static function fromCodepoint(int $codepoint): string
    {
        if ($codepoint < 0 || $codepoint > 0x10ffff || ($codepoint >= 0xd800 && $codepoint <= 0xdfff)) {
            return self::REPLACEMENT;
        }

        if ($codepoint <= 0x7f) {
            return chr($codepoint);
        }
        if ($codepoint <= 0x7ff) {
            return chr(0xc0 | ($codepoint >> 6))
                . chr(0x80 | ($codepoint & 0x3f));
        }
        if ($codepoint <= 0xffff) {
            return chr(0xe0 | ($codepoint >> 12))
                . chr(0x80 | (($codepoint >> 6) & 0x3f))
                . chr(0x80 | ($codepoint & 0x3f));
        }

        return chr(0xf0 | ($codepoint >> 18))
            . chr(0x80 | (($codepoint >> 12) & 0x3f))
            . chr(0x80 | (($codepoint >> 6) & 0x3f))
            . chr(0x80 | ($codepoint & 0x3f));
    }

    private static function codepoint(string $char): int
    {
        $bytes = array_values(unpack('C*', $char) ?: []);
        $first = $bytes[0] ?? 0;
        if ($first <= 0x7f) {
            return $first;
        }
        if ($first >= 0xc2 && $first <= 0xdf && isset($bytes[1])) {
            return (($first & 0x1f) << 6) | ($bytes[1] & 0x3f);
        }
        if ($first >= 0xe0 && $first <= 0xef && isset($bytes[2])) {
            return (($first & 0x0f) << 12) | (($bytes[1] & 0x3f) << 6) | ($bytes[2] & 0x3f);
        }
        if ($first >= 0xf0 && $first <= 0xf4 && isset($bytes[3])) {
            return (($first & 0x07) << 18) | (($bytes[1] & 0x3f) << 12) | (($bytes[2] & 0x3f) << 6) | ($bytes[3] & 0x3f);
        }

        return 0xfffd;
    }

    private static function ambiguousWidthColumns(string $ambiguousWidth): int
    {
        $key = strtolower(str_replace(['-', '_', ' '], '', trim($ambiguousWidth)));

        return match ($key) {
            'narrow', 'na', 'neutral', 'single', 'singlecolumn', '1' => 1,
            'wide', 'w', 'cjk', 'eastasian', 'eastasianwide', 'double', 'doublecolumn', '2' => 2,
            default => throw new \InvalidArgumentException("Unsupported East Asian ambiguous-width policy: {$ambiguousWidth}"),
        };
    }

    private static function codepointDisplayWidth(int $codepoint, int $ambiguousColumns): int
    {
        if ($codepoint === 0 || $codepoint < 32 || ($codepoint >= 0x7f && $codepoint < 0xa0)) {
            return 0;
        }
        if (self::isEmojiSkinToneModifier($codepoint)) {
            return 2;
        }
        if (self::isCombiningOrZeroWidth($codepoint)) {
            return 0;
        }
        if (self::isWideCodepoint($codepoint)) {
            return 2;
        }
        if (self::isAmbiguousWidthCodepoint($codepoint)) {
            return $ambiguousColumns;
        }

        return 1;
    }

    private static function graphemeDisplayWidth(string $cluster, int $ambiguousColumns): int
    {
        $width = 0;
        $hasJoiner = false;
        $hasWide = false;
        $hasEmojiVariation = false;
        $hasEmojiVariationBase = false;
        $hasEmojiSkinToneModifier = false;
        $hasEmojiModifierBase = false;
        $hasKeycap = false;
        $hasKeycapBase = false;
        foreach (self::characters($cluster) as $char) {
            $codepoint = self::codepoint($char);
            $charWidth = self::codepointDisplayWidth($codepoint, $ambiguousColumns);
            $width += $charWidth;
            $hasJoiner = $hasJoiner || $codepoint === 0x200d;
            $hasWide = $hasWide || $charWidth === 2;
            $hasEmojiVariation = $hasEmojiVariation || self::isEmojiVariationSelector($codepoint);
            $hasEmojiVariationBase = $hasEmojiVariationBase || self::isEmojiVariationBase($codepoint);
            $hasEmojiSkinToneModifier = $hasEmojiSkinToneModifier || self::isEmojiSkinToneModifier($codepoint);
            $hasEmojiModifierBase = $hasEmojiModifierBase || self::isEmojiModifierBase($codepoint);
            $hasKeycap = $hasKeycap || $codepoint === 0x20e3;
            $hasKeycapBase = $hasKeycapBase || self::isKeycapBase($codepoint);
        }

        if ($hasKeycap && $hasKeycapBase) {
            return 2;
        }
        if ($hasEmojiSkinToneModifier && $hasEmojiModifierBase) {
            return 2;
        }
        if ($hasJoiner && ($hasWide || $hasEmojiVariation || $hasEmojiVariationBase)) {
            return 2;
        }
        if ($hasEmojiVariation && $hasEmojiVariationBase) {
            return max(2, $width);
        }
        if (self::isBoundedIndicViramaCluster($cluster)) {
            return 1;
        }

        return $width;
    }

    private static function isCombiningOrZeroWidth(int $codepoint): bool
    {
        return self::isUnicodeCombiningMark($codepoint)
            || self::isUnicodeFormatControl($codepoint)
            || self::isBoundedIndicVirama($codepoint)
            || ($codepoint >= 0x0300 && $codepoint <= 0x036f)
            || $codepoint === 0x00ad
            || ($codepoint >= 0x0483 && $codepoint <= 0x0489)
            || ($codepoint >= 0x0591 && $codepoint <= 0x05bd)
            || $codepoint === 0x05bf
            || ($codepoint >= 0x05c1 && $codepoint <= 0x05c2)
            || ($codepoint >= 0x05c4 && $codepoint <= 0x05c5)
            || $codepoint === 0x05c7
            || ($codepoint >= 0x0610 && $codepoint <= 0x061a)
            || ($codepoint >= 0x064b && $codepoint <= 0x065f)
            || $codepoint === 0x0670
            || ($codepoint >= 0x06d6 && $codepoint <= 0x06dc)
            || ($codepoint >= 0x06df && $codepoint <= 0x06e4)
            || ($codepoint >= 0x06e7 && $codepoint <= 0x06e8)
            || ($codepoint >= 0x06ea && $codepoint <= 0x06ed)
            || ($codepoint >= 0x0711 && $codepoint <= 0x0711)
            || ($codepoint >= 0x0730 && $codepoint <= 0x074a)
            || ($codepoint >= 0x07a6 && $codepoint <= 0x07b0)
            || ($codepoint >= 0x07eb && $codepoint <= 0x07f3)
            || ($codepoint >= 0x0816 && $codepoint <= 0x0819)
            || ($codepoint >= 0x081b && $codepoint <= 0x0823)
            || ($codepoint >= 0x0825 && $codepoint <= 0x0827)
            || ($codepoint >= 0x0829 && $codepoint <= 0x082d)
            || ($codepoint >= 0x0859 && $codepoint <= 0x085b)
            || ($codepoint >= 0x08d3 && $codepoint <= 0x08ff)
            || ($codepoint >= 0x0900 && $codepoint <= 0x0902)
            || $codepoint === 0x093a
            || $codepoint === 0x093c
            || ($codepoint >= 0x0941 && $codepoint <= 0x0948)
            || $codepoint === 0x094d
            || ($codepoint >= 0x0951 && $codepoint <= 0x0957)
            || ($codepoint >= 0x0962 && $codepoint <= 0x0963)
            || ($codepoint >= 0x0981 && $codepoint <= 0x0981)
            || $codepoint === 0x09bc
            || ($codepoint >= 0x09c1 && $codepoint <= 0x09c4)
            || $codepoint === 0x09cd
            || ($codepoint >= 0x09e2 && $codepoint <= 0x09e3)
            || ($codepoint >= 0x0e31 && $codepoint <= 0x0e31)
            || ($codepoint >= 0x0e34 && $codepoint <= 0x0e3a)
            || ($codepoint >= 0x0e47 && $codepoint <= 0x0e4e)
            || ($codepoint >= 0x1160 && $codepoint <= 0x11ff)
            || ($codepoint >= 0x1ab0 && $codepoint <= 0x1aff)
            || ($codepoint >= 0x1dc0 && $codepoint <= 0x1dff)
            || ($codepoint >= 0x200b && $codepoint <= 0x200f)
            || ($codepoint >= 0x202a && $codepoint <= 0x202e)
            || ($codepoint >= 0x2060 && $codepoint <= 0x206f)
            || ($codepoint >= 0x20d0 && $codepoint <= 0x20ff)
            || ($codepoint >= 0xd7b0 && $codepoint <= 0xd7c6)
            || ($codepoint >= 0xd7cb && $codepoint <= 0xd7fb)
            || ($codepoint >= 0xe0020 && $codepoint <= 0xe007f)
            || ($codepoint >= 0xfe00 && $codepoint <= 0xfe0f)
            || ($codepoint >= 0xfe20 && $codepoint <= 0xfe2f)
            || $codepoint === 0xfeff
            || ($codepoint >= 0xe0100 && $codepoint <= 0xe01ef);
    }

    private static function isUnicodeCombiningMark(int $codepoint): bool
    {
        if (class_exists(\IntlChar::class)) {
            $category = \IntlChar::charType($codepoint);

            return $category === \IntlChar::CHAR_CATEGORY_NON_SPACING_MARK
                || $category === \IntlChar::CHAR_CATEGORY_COMBINING_SPACING_MARK
                || $category === \IntlChar::CHAR_CATEGORY_ENCLOSING_MARK;
        }

        return self::isBoundedIndicSpacingMark($codepoint);
    }

    private static function isBoundedGraphemeSpacingMark(int $codepoint): bool
    {
        return $codepoint === 0x0e33
            || $codepoint === 0x0eb3;
    }

    private static function isBoundedIndicViramaCluster(string $cluster): bool
    {
        $hasVirama = false;
        $consonants = 0;
        foreach (self::characters($cluster) as $char) {
            $codepoint = self::codepoint($char);
            if (self::isBoundedIndicConsonant($codepoint)) {
                $consonants++;
            }
            if (self::isBoundedIndicVirama($codepoint)) {
                $hasVirama = true;
            }
        }

        return $hasVirama && $consonants >= 2;
    }

    private static function isBoundedIndicVirama(int $codepoint): bool
    {
        return $codepoint === 0x094d
            || $codepoint === 0x09cd
            || $codepoint === 0x0a4d
            || $codepoint === 0x0acd
            || $codepoint === 0x0b4d
            || $codepoint === 0x0bcd
            || $codepoint === 0x0c4d
            || $codepoint === 0x0ccd
            || $codepoint === 0x0d4d
            || $codepoint === 0x0dca;
    }

    private static function isBoundedIndicConsonant(int $codepoint): bool
    {
        return ($codepoint >= 0x0915 && $codepoint <= 0x0939)
            || ($codepoint >= 0x0958 && $codepoint <= 0x095f)
            || ($codepoint >= 0x0995 && $codepoint <= 0x09b9)
            || ($codepoint >= 0x0a15 && $codepoint <= 0x0a39)
            || ($codepoint >= 0x0a59 && $codepoint <= 0x0a5e)
            || ($codepoint >= 0x0a95 && $codepoint <= 0x0ab9)
            || ($codepoint >= 0x0b15 && $codepoint <= 0x0b39)
            || ($codepoint >= 0x0b5c && $codepoint <= 0x0b5f)
            || ($codepoint >= 0x0b95 && $codepoint <= 0x0bb9)
            || ($codepoint >= 0x0c15 && $codepoint <= 0x0c39)
            || ($codepoint >= 0x0c95 && $codepoint <= 0x0cb9)
            || ($codepoint >= 0x0d15 && $codepoint <= 0x0d39)
            || ($codepoint >= 0x0d9a && $codepoint <= 0x0dc6);
    }

    private static function isUnicodeFormatControl(int $codepoint): bool
    {
        if (class_exists(\IntlChar::class)) {
            return \IntlChar::charType($codepoint) === \IntlChar::CHAR_CATEGORY_FORMAT_CHAR;
        }

        return self::isBoundedZeroWidthFormatControl($codepoint);
    }

    private static function isBoundedZeroWidthFormatControl(int $codepoint): bool
    {
        return ($codepoint >= 0x0600 && $codepoint <= 0x0605)
            || $codepoint === 0x061c
            || $codepoint === 0x06dd
            || $codepoint === 0x070f
            || ($codepoint >= 0x0890 && $codepoint <= 0x0891)
            || $codepoint === 0x08e2
            || $codepoint === 0x180e
            || ($codepoint >= 0x200b && $codepoint <= 0x200f)
            || ($codepoint >= 0x202a && $codepoint <= 0x202e)
            || ($codepoint >= 0x2060 && $codepoint <= 0x206f)
            || $codepoint === 0xfeff
            || $codepoint === 0x110bd
            || $codepoint === 0x110cd
            || ($codepoint >= 0x13430 && $codepoint <= 0x1343f);
    }

    private static function isBoundedIndicSpacingMark(int $codepoint): bool
    {
        return $codepoint === 0x0903
            || $codepoint === 0x093b
            || ($codepoint >= 0x093e && $codepoint <= 0x0940)
            || ($codepoint >= 0x0949 && $codepoint <= 0x094c)
            || ($codepoint >= 0x094e && $codepoint <= 0x094f)
            || ($codepoint >= 0x0982 && $codepoint <= 0x0983)
            || ($codepoint >= 0x09be && $codepoint <= 0x09c0)
            || ($codepoint >= 0x09c7 && $codepoint <= 0x09c8)
            || ($codepoint >= 0x09cb && $codepoint <= 0x09cc)
            || $codepoint === 0x09d7
            || $codepoint === 0x0a03
            || ($codepoint >= 0x0a3e && $codepoint <= 0x0a40)
            || ($codepoint >= 0x0a83 && $codepoint <= 0x0a83)
            || ($codepoint >= 0x0abe && $codepoint <= 0x0ac0)
            || $codepoint === 0x0ac9
            || ($codepoint >= 0x0acb && $codepoint <= 0x0acc)
            || ($codepoint >= 0x0b02 && $codepoint <= 0x0b03)
            || $codepoint === 0x0b3e
            || $codepoint === 0x0b40
            || ($codepoint >= 0x0b47 && $codepoint <= 0x0b48)
            || ($codepoint >= 0x0b4b && $codepoint <= 0x0b4c)
            || $codepoint === 0x0b57
            || ($codepoint >= 0x0bbe && $codepoint <= 0x0bbf)
            || ($codepoint >= 0x0bc1 && $codepoint <= 0x0bc2)
            || ($codepoint >= 0x0bc6 && $codepoint <= 0x0bc8)
            || ($codepoint >= 0x0bca && $codepoint <= 0x0bcc)
            || $codepoint === 0x0bd7;
    }

    private static function isAmbiguousWidthCodepoint(int $codepoint): bool
    {
        if (in_array($codepoint, self::EAST_ASIAN_AMBIGUOUS_SINGLE_CODEPOINTS, true)) {
            return true;
        }

        foreach (self::EAST_ASIAN_AMBIGUOUS_RANGES as [$start, $end]) {
            if ($codepoint >= $start && $codepoint <= $end) {
                return true;
            }
        }

        return false;
    }

    private static function isWideCodepoint(int $codepoint): bool
    {
        return ($codepoint >= 0x1100 && $codepoint <= 0x115f)
            || ($codepoint >= 0x231a && $codepoint <= 0x231b)
            || $codepoint === 0x2329
            || $codepoint === 0x232a
            || ($codepoint >= 0x23e9 && $codepoint <= 0x23ec)
            || $codepoint === 0x23f0
            || $codepoint === 0x23f3
            || ($codepoint >= 0x25fd && $codepoint <= 0x25fe)
            || ($codepoint >= 0x2614 && $codepoint <= 0x2615)
            || ($codepoint >= 0x2648 && $codepoint <= 0x2653)
            || $codepoint === 0x267f
            || $codepoint === 0x2693
            || $codepoint === 0x26a1
            || ($codepoint >= 0x26aa && $codepoint <= 0x26ab)
            || ($codepoint >= 0x26bd && $codepoint <= 0x26be)
            || ($codepoint >= 0x26c4 && $codepoint <= 0x26c5)
            || $codepoint === 0x26ce
            || $codepoint === 0x26d4
            || $codepoint === 0x26ea
            || ($codepoint >= 0x26f2 && $codepoint <= 0x26f3)
            || $codepoint === 0x26f5
            || $codepoint === 0x26fa
            || $codepoint === 0x26fd
            || $codepoint === 0x2705
            || ($codepoint >= 0x270a && $codepoint <= 0x270b)
            || $codepoint === 0x2728
            || $codepoint === 0x274c
            || $codepoint === 0x274e
            || ($codepoint >= 0x2753 && $codepoint <= 0x2755)
            || $codepoint === 0x2757
            || ($codepoint >= 0x2795 && $codepoint <= 0x2797)
            || $codepoint === 0x27b0
            || $codepoint === 0x27bf
            || ($codepoint >= 0x2e80 && $codepoint <= 0xa4cf && $codepoint !== 0x303f)
            || ($codepoint >= 0x2b1b && $codepoint <= 0x2b1c)
            || $codepoint === 0x2b50
            || $codepoint === 0x2b55
            || ($codepoint >= 0xa960 && $codepoint <= 0xa97c)
            || ($codepoint >= 0xac00 && $codepoint <= 0xd7a3)
            || ($codepoint >= 0xf900 && $codepoint <= 0xfaff)
            || ($codepoint >= 0xfe10 && $codepoint <= 0xfe19)
            || ($codepoint >= 0xfe30 && $codepoint <= 0xfe6f)
            || ($codepoint >= 0xff00 && $codepoint <= 0xff60)
            || ($codepoint >= 0xffe0 && $codepoint <= 0xffe6)
            || ($codepoint >= 0x16fe0 && $codepoint <= 0x16fe4)
            || ($codepoint >= 0x16ff0 && $codepoint <= 0x16ff1)
            || ($codepoint >= 0x1aff0 && $codepoint <= 0x1afff)
            || ($codepoint >= 0x17000 && $codepoint <= 0x187f7)
            || ($codepoint >= 0x18800 && $codepoint <= 0x18aff)
            || ($codepoint >= 0x18b00 && $codepoint <= 0x18cff)
            || ($codepoint >= 0x18d00 && $codepoint <= 0x18d8f)
            || ($codepoint >= 0x1b000 && $codepoint <= 0x1b122)
            || $codepoint === 0x1b132
            || ($codepoint >= 0x1b150 && $codepoint <= 0x1b152)
            || $codepoint === 0x1b155
            || ($codepoint >= 0x1b164 && $codepoint <= 0x1b167)
            || ($codepoint >= 0x1b170 && $codepoint <= 0x1b2fb)
            || $codepoint === 0x1f004
            || $codepoint === 0x1f0cf
            || $codepoint === 0x1f18e
            || ($codepoint >= 0x1f191 && $codepoint <= 0x1f19a)
            || ($codepoint >= 0x1f200 && $codepoint <= 0x1f202)
            || ($codepoint >= 0x1f210 && $codepoint <= 0x1f23b)
            || ($codepoint >= 0x1f240 && $codepoint <= 0x1f248)
            || ($codepoint >= 0x1f250 && $codepoint <= 0x1f251)
            || ($codepoint >= 0x1f260 && $codepoint <= 0x1f265)
            || ($codepoint >= 0x1f300 && $codepoint <= 0x1f64f)
            || ($codepoint >= 0x1f680 && $codepoint <= 0x1f6ff)
            || ($codepoint >= 0x1f7e0 && $codepoint <= 0x1f7eb)
            || $codepoint === 0x1f7f0
            || ($codepoint >= 0x1fa70 && $codepoint <= 0x1faff)
            || ($codepoint >= 0x1f900 && $codepoint <= 0x1f9ff)
            || ($codepoint >= 0x20000 && $codepoint <= 0x3fffd);
    }

    private static function isRegionalIndicator(int $codepoint): bool
    {
        return $codepoint >= 0x1f1e6 && $codepoint <= 0x1f1ff;
    }

    private static function isKeycapBase(int $codepoint): bool
    {
        return ($codepoint >= 0x30 && $codepoint <= 0x39)
            || $codepoint === 0x23
            || $codepoint === 0x2a;
    }

    private static function isEmojiVariationSelector(int $codepoint): bool
    {
        return $codepoint === 0xfe0e || $codepoint === 0xfe0f;
    }

    private static function isEmojiVariationBase(int $codepoint): bool
    {
        return $codepoint === 0x00a9
            || $codepoint === 0x00ae
            || $codepoint === 0x203c
            || $codepoint === 0x2049
            || $codepoint === 0x2122
            || $codepoint === 0x2139
            || ($codepoint >= 0x2194 && $codepoint <= 0x21aa)
            || ($codepoint >= 0x231a && $codepoint <= 0x231b)
            || $codepoint === 0x2328
            || $codepoint === 0x23cf
            || ($codepoint >= 0x23e9 && $codepoint <= 0x23f3)
            || ($codepoint >= 0x23f8 && $codepoint <= 0x23fa)
            || $codepoint === 0x24c2
            || ($codepoint >= 0x25aa && $codepoint <= 0x25ab)
            || $codepoint === 0x25b6
            || $codepoint === 0x25c0
            || ($codepoint >= 0x25fb && $codepoint <= 0x25fe)
            || ($codepoint >= 0x2600 && $codepoint <= 0x27bf)
            || ($codepoint >= 0x2934 && $codepoint <= 0x2935)
            || ($codepoint >= 0x2b05 && $codepoint <= 0x2b55)
            || $codepoint === 0x3030
            || $codepoint === 0x303d
            || $codepoint === 0x3297
            || $codepoint === 0x3299;
    }

    private static function isEmojiSkinToneModifier(int $codepoint): bool
    {
        return $codepoint >= 0x1f3fb && $codepoint <= 0x1f3ff;
    }

    private static function isEmojiModifierClusterBase(string $cluster): bool
    {
        $hasModifierBase = false;
        foreach (self::characters($cluster) as $char) {
            $codepoint = self::codepoint($char);
            if (self::isEmojiSkinToneModifier($codepoint)) {
                return false;
            }
            if (self::isEmojiModifierBase($codepoint)) {
                $hasModifierBase = true;
            }
        }

        return $hasModifierBase;
    }

    private static function isEmojiModifierBase(int $codepoint): bool
    {
        return $codepoint === 0x261d
            || ($codepoint >= 0x270a && $codepoint <= 0x270d)
            || $codepoint === 0x1f385
            || ($codepoint >= 0x1f3c2 && $codepoint <= 0x1f3c4)
            || $codepoint === 0x1f3c7
            || ($codepoint >= 0x1f3ca && $codepoint <= 0x1f3cc)
            || ($codepoint >= 0x1f442 && $codepoint <= 0x1f443)
            || ($codepoint >= 0x1f446 && $codepoint <= 0x1f450)
            || ($codepoint >= 0x1f466 && $codepoint <= 0x1f469)
            || $codepoint === 0x1f46e
            || ($codepoint >= 0x1f470 && $codepoint <= 0x1f478)
            || $codepoint === 0x1f47c
            || ($codepoint >= 0x1f481 && $codepoint <= 0x1f483)
            || ($codepoint >= 0x1f485 && $codepoint <= 0x1f487)
            || $codepoint === 0x1f4aa
            || ($codepoint >= 0x1f574 && $codepoint <= 0x1f575)
            || $codepoint === 0x1f57a
            || $codepoint === 0x1f590
            || ($codepoint >= 0x1f595 && $codepoint <= 0x1f596)
            || ($codepoint >= 0x1f645 && $codepoint <= 0x1f647)
            || ($codepoint >= 0x1f64b && $codepoint <= 0x1f64f)
            || $codepoint === 0x1f6a3
            || ($codepoint >= 0x1f6b4 && $codepoint <= 0x1f6b6)
            || $codepoint === 0x1f6c0
            || $codepoint === 0x1f6cc
            || ($codepoint >= 0x1f90c && $codepoint <= 0x1f93a)
            || ($codepoint >= 0x1f93c && $codepoint <= 0x1f945)
            || ($codepoint >= 0x1f9b5 && $codepoint <= 0x1f9b6)
            || ($codepoint >= 0x1f9b8 && $codepoint <= 0x1f9b9)
            || ($codepoint >= 0x1f9cd && $codepoint <= 0x1f9cf)
            || ($codepoint >= 0x1f9d1 && $codepoint <= 0x1f9dd)
            || ($codepoint >= 0x1fac3 && $codepoint <= 0x1fac5)
            || ($codepoint >= 0x1faf0 && $codepoint <= 0x1faf8);
    }
}
