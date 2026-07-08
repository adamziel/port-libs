<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class PdfTextExtractor
{
    private const DEFAULT_MAX_DECODED_STREAM_BYTES = 16_777_216;
    private const DEFAULT_MAX_TOKENIZED_CONTENT_STREAM_BYTES = 8_388_608;
    private const DEFAULT_MAX_CONTENT_TOKENS = 250_000;
    private const DEFAULT_MAX_POSITIONED_TEXT_RUNS = 5_000;
    private const POSITIONED_TEXT_WORD_GAP = 12.0;
    private const POSITIONED_TEXT_LINE_TOLERANCE = 2.0;
    private const SIMPLE_TEXT_ADVANCE_RATIO = 0.5;
    private const PDF_PASSWORD_PADDING = "\x28\xBF\x4E\x5E\x4E\x75\x8A\x41\x64\x00\x4E\x56\xFF\xFA\x01\x08\x2E\x2E\x00\xB6\xD0\x68\x3E\x80\x2F\x0C\xA9\xFE\x64\x53\x69\x7A";
    private const GLYPH_NAME_ENCODING = 'GlyphNameEncoding';
    private const SUPPORTED_STREAM_FILTERS = [
        'ASCIIHexDecode',
        'AHx',
        'ASCII85Decode',
        'A85',
        'RunLengthDecode',
        'RL',
        'FlateDecode',
        'Fl',
        'LZWDecode',
        'LZW',
        'Crypt',
    ];

    private const MAC_ROMAN_HIGH_BYTES = [
        0x80 => "\u{00C4}", 0x81 => "\u{00C5}", 0x82 => "\u{00C7}", 0x83 => "\u{00C9}",
        0x84 => "\u{00D1}", 0x85 => "\u{00D6}", 0x86 => "\u{00DC}", 0x87 => "\u{00E1}",
        0x88 => "\u{00E0}", 0x89 => "\u{00E2}", 0x8A => "\u{00E4}", 0x8B => "\u{00E3}",
        0x8C => "\u{00E5}", 0x8D => "\u{00E7}", 0x8E => "\u{00E9}", 0x8F => "\u{00E8}",
        0x90 => "\u{00EA}", 0x91 => "\u{00EB}", 0x92 => "\u{00ED}", 0x93 => "\u{00EC}",
        0x94 => "\u{00EE}", 0x95 => "\u{00EF}", 0x96 => "\u{00F1}", 0x97 => "\u{00F3}",
        0x98 => "\u{00F2}", 0x99 => "\u{00F4}", 0x9A => "\u{00F6}", 0x9B => "\u{00F5}",
        0x9C => "\u{00FA}", 0x9D => "\u{00F9}", 0x9E => "\u{00FB}", 0x9F => "\u{00FC}",
        0xA0 => "\u{2020}", 0xA1 => "\u{00B0}", 0xA2 => "\u{00A2}", 0xA3 => "\u{00A3}",
        0xA4 => "\u{00A7}", 0xA5 => "\u{2022}", 0xA6 => "\u{00B6}", 0xA7 => "\u{00DF}",
        0xA8 => "\u{00AE}", 0xA9 => "\u{00A9}", 0xAA => "\u{2122}", 0xAB => "\u{00B4}",
        0xAC => "\u{00A8}", 0xAD => "\u{2260}", 0xAE => "\u{00C6}", 0xAF => "\u{00D8}",
        0xB0 => "\u{221E}", 0xB1 => "\u{00B1}", 0xB2 => "\u{2264}", 0xB3 => "\u{2265}",
        0xB4 => "\u{00A5}", 0xB5 => "\u{00B5}", 0xB6 => "\u{2202}", 0xB7 => "\u{2211}",
        0xB8 => "\u{220F}", 0xB9 => "\u{03C0}", 0xBA => "\u{222B}", 0xBB => "\u{00AA}",
        0xBC => "\u{00BA}", 0xBD => "\u{03A9}", 0xBE => "\u{00E6}", 0xBF => "\u{00F8}",
        0xC0 => "\u{00BF}", 0xC1 => "\u{00A1}", 0xC2 => "\u{00AC}", 0xC3 => "\u{221A}",
        0xC4 => "\u{0192}", 0xC5 => "\u{2248}", 0xC6 => "\u{2206}", 0xC7 => "\u{00AB}",
        0xC8 => "\u{00BB}", 0xC9 => "\u{2026}", 0xCA => "\u{00A0}", 0xCB => "\u{00C0}",
        0xCC => "\u{00C3}", 0xCD => "\u{00D5}", 0xCE => "\u{0152}", 0xCF => "\u{0153}",
        0xD0 => "\u{2013}", 0xD1 => "\u{2014}", 0xD2 => "\u{201C}", 0xD3 => "\u{201D}",
        0xD4 => "\u{2018}", 0xD5 => "\u{2019}", 0xD6 => "\u{00F7}", 0xD7 => "\u{25CA}",
        0xD8 => "\u{00FF}", 0xD9 => "\u{0178}", 0xDA => "\u{2044}", 0xDB => "\u{20AC}",
        0xDC => "\u{2039}", 0xDD => "\u{203A}", 0xDE => "\u{FB01}", 0xDF => "\u{FB02}",
        0xE0 => "\u{2021}", 0xE1 => "\u{00B7}", 0xE2 => "\u{201A}", 0xE3 => "\u{201E}",
        0xE4 => "\u{2030}", 0xE5 => "\u{00C2}", 0xE6 => "\u{00CA}", 0xE7 => "\u{00C1}",
        0xE8 => "\u{00CB}", 0xE9 => "\u{00C8}", 0xEA => "\u{00CD}", 0xEB => "\u{00CE}",
        0xEC => "\u{00CF}", 0xED => "\u{00CC}", 0xEE => "\u{00D3}", 0xEF => "\u{00D4}",
        0xF0 => "\u{F8FF}", 0xF1 => "\u{00D2}", 0xF2 => "\u{00DA}", 0xF3 => "\u{00DB}",
        0xF4 => "\u{00D9}", 0xF5 => "\u{0131}", 0xF6 => "\u{02C6}", 0xF7 => "\u{02DC}",
        0xF8 => "\u{00AF}", 0xF9 => "\u{02D8}", 0xFA => "\u{02D9}", 0xFB => "\u{02DA}",
        0xFC => "\u{00B8}", 0xFD => "\u{02DD}", 0xFE => "\u{02DB}", 0xFF => "\u{02C7}",
    ];
    private const SYMBOL_ENCODING_GLYPHS = [
        0x20 => 'space', 0x21 => 'exclam', 0x22 => 'universal', 0x23 => 'numbersign',
        0x24 => 'existential', 0x25 => 'percent', 0x26 => 'ampersand', 0x27 => 'suchthat',
        0x28 => 'parenleft', 0x29 => 'parenright', 0x2A => 'asteriskmath', 0x2B => 'plus',
        0x2C => 'comma', 0x2D => 'minus', 0x2E => 'period', 0x2F => 'slash',
        0x30 => 'zero', 0x31 => 'one', 0x32 => 'two', 0x33 => 'three',
        0x34 => 'four', 0x35 => 'five', 0x36 => 'six', 0x37 => 'seven',
        0x38 => 'eight', 0x39 => 'nine', 0x3A => 'colon', 0x3B => 'semicolon',
        0x3C => 'less', 0x3D => 'equal', 0x3E => 'greater', 0x3F => 'question',
        0x40 => 'congruent', 0x41 => 'Alpha', 0x42 => 'Beta', 0x43 => 'Chi',
        0x44 => 'Delta', 0x45 => 'Epsilon', 0x46 => 'Phi', 0x47 => 'Gamma',
        0x48 => 'Eta', 0x49 => 'Iota', 0x4A => 'theta1', 0x4B => 'Kappa',
        0x4C => 'Lambda', 0x4D => 'Mu', 0x4E => 'Nu', 0x4F => 'Omicron',
        0x50 => 'Pi', 0x51 => 'Theta', 0x52 => 'Rho', 0x53 => 'Sigma',
        0x54 => 'Tau', 0x55 => 'Upsilon', 0x56 => 'sigma1', 0x57 => 'Omega',
        0x58 => 'Xi', 0x59 => 'Psi', 0x5A => 'Zeta', 0x5B => 'bracketleft',
        0x5C => 'therefore', 0x5D => 'bracketright', 0x5E => 'perpendicular', 0x5F => 'underscore',
        0x60 => 'radicalex', 0x61 => 'alpha', 0x62 => 'beta', 0x63 => 'chi',
        0x64 => 'delta', 0x65 => 'epsilon', 0x66 => 'phi', 0x67 => 'gamma',
        0x68 => 'eta', 0x69 => 'iota', 0x6A => 'phi1', 0x6B => 'kappa',
        0x6C => 'lambda', 0x6D => 'mu', 0x6E => 'nu', 0x6F => 'omicron',
        0x70 => 'pi', 0x71 => 'theta', 0x72 => 'rho', 0x73 => 'sigma',
        0x74 => 'tau', 0x75 => 'upsilon', 0x76 => 'omega1', 0x77 => 'omega',
        0x78 => 'xi', 0x79 => 'psi', 0x7A => 'zeta', 0x7B => 'braceleft',
        0x7C => 'bar', 0x7D => 'braceright', 0x7E => 'similar',
        0xA0 => 'Euro', 0xA1 => 'Upsilon1', 0xA2 => 'minute', 0xA3 => 'lessequal',
        0xA4 => 'fraction', 0xA5 => 'infinity', 0xA6 => 'florin', 0xA7 => 'club',
        0xA8 => 'diamond', 0xA9 => 'heart', 0xAA => 'spade', 0xAB => 'arrowboth',
        0xAC => 'arrowleft', 0xAD => 'arrowup', 0xAE => 'arrowright', 0xAF => 'arrowdown',
        0xB0 => 'degree', 0xB1 => 'plusminus', 0xB2 => 'second', 0xB3 => 'greaterequal',
        0xB4 => 'multiply', 0xB5 => 'proportional', 0xB6 => 'partialdiff', 0xB7 => 'bullet',
        0xB8 => 'divide', 0xB9 => 'notequal', 0xBA => 'equivalence', 0xBB => 'approxequal',
        0xBC => 'ellipsis', 0xBD => 'arrowvertex', 0xBE => 'arrowhorizex', 0xBF => 'carriagereturn',
        0xC0 => 'aleph', 0xC1 => 'Ifraktur', 0xC2 => 'Rfraktur', 0xC3 => 'weierstrass',
        0xC4 => 'circlemultiply', 0xC5 => 'circleplus', 0xC6 => 'emptyset', 0xC7 => 'intersection',
        0xC8 => 'union', 0xC9 => 'propersuperset', 0xCA => 'reflexsuperset', 0xCB => 'notsubset',
        0xCC => 'propersubset', 0xCD => 'reflexsubset', 0xCE => 'element', 0xCF => 'notelement',
        0xD0 => 'angle', 0xD1 => 'gradient', 0xD2 => 'registerserif', 0xD3 => 'copyrightserif',
        0xD4 => 'trademarkserif', 0xD5 => 'product', 0xD6 => 'radical', 0xD7 => 'dotmath',
        0xD8 => 'logicalnot', 0xD9 => 'logicaland', 0xDA => 'logicalor', 0xDB => 'arrowdblboth',
        0xDC => 'arrowdblleft', 0xDD => 'arrowdblup', 0xDE => 'arrowdblright', 0xDF => 'arrowdbldown',
        0xE0 => 'lozenge', 0xE1 => 'angleleft', 0xE2 => 'registersans', 0xE3 => 'copyrightsans',
        0xE4 => 'trademarksans', 0xE5 => 'summation', 0xE6 => 'parenlefttp', 0xE7 => 'parenleftex',
        0xE8 => 'parenleftbt', 0xE9 => 'bracketlefttp', 0xEA => 'bracketleftex', 0xEB => 'bracketleftbt',
        0xEC => 'bracelefttp', 0xED => 'braceleftmid', 0xEE => 'braceleftbt', 0xEF => 'braceex',
        0xF1 => 'angleright', 0xF2 => 'integral', 0xF3 => 'integraltp', 0xF4 => 'integralex',
        0xF5 => 'integralbt', 0xF6 => 'parenrighttp', 0xF7 => 'parenrightex', 0xF8 => 'parenrightbt',
        0xF9 => 'bracketrighttp', 0xFA => 'bracketrightex', 0xFB => 'bracketrightbt',
        0xFC => 'bracerighttp', 0xFD => 'bracerightmid', 0xFE => 'bracerightbt',
    ];
    /** @var array<string, array<int, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}>> */
    private array $fontObjectToUnicodeMapsCache = [];

    /** @var array<string, array<int, array{base: string, differences: array<int, string>, suppressUnmapped: bool}>> */
    private array $fontObjectEncodingsCache = [];

    /** @var array<string, list<array{body: string, objectNumber: int}>> */
    private array $structTreeRootNodesCache = [];

    /** @var array<string, list<array{body: string, objectNumber: int|null}>> */
    private array $parentTreeRootNodesCache = [];
    private const ZAPF_DINGBATS_ENCODING_GLYPHS = [
        0x20 => 'space', 0x21 => 'a1', 0x22 => 'a2', 0x23 => 'a202',
        0x24 => 'a3', 0x25 => 'a4', 0x26 => 'a5', 0x27 => 'a119',
        0x28 => 'a118', 0x29 => 'a117', 0x2A => 'a11', 0x2B => 'a12',
        0x2C => 'a13', 0x2D => 'a14', 0x2E => 'a15', 0x2F => 'a16',
        0x30 => 'a105', 0x31 => 'a17', 0x32 => 'a18', 0x33 => 'a19',
        0x34 => 'a20', 0x35 => 'a21', 0x36 => 'a22', 0x37 => 'a23',
        0x38 => 'a24', 0x39 => 'a25', 0x3A => 'a26', 0x3B => 'a27',
        0x3C => 'a28', 0x3D => 'a6', 0x3E => 'a7', 0x3F => 'a8',
        0x40 => 'a9', 0x41 => 'a10', 0x42 => 'a29', 0x43 => 'a30',
        0x44 => 'a31', 0x45 => 'a32', 0x46 => 'a33', 0x47 => 'a34',
        0x48 => 'a35', 0x49 => 'a36', 0x4A => 'a37', 0x4B => 'a38',
        0x4C => 'a39', 0x4D => 'a40', 0x4E => 'a41', 0x4F => 'a42',
        0x50 => 'a43', 0x51 => 'a44', 0x52 => 'a45', 0x53 => 'a46',
        0x54 => 'a47', 0x55 => 'a48', 0x56 => 'a49', 0x57 => 'a50',
        0x58 => 'a51', 0x59 => 'a52', 0x5A => 'a53', 0x5B => 'a54',
        0x5C => 'a55', 0x5D => 'a56', 0x5E => 'a57', 0x5F => 'a58',
        0x60 => 'a59', 0x61 => 'a60', 0x62 => 'a61', 0x63 => 'a62',
        0x64 => 'a63', 0x65 => 'a64', 0x66 => 'a65', 0x67 => 'a66',
        0x68 => 'a67', 0x69 => 'a68', 0x6A => 'a69', 0x6B => 'a70',
        0x6C => 'a71', 0x6D => 'a72', 0x6E => 'a73', 0x6F => 'a74',
        0x70 => 'a203', 0x71 => 'a75', 0x72 => 'a204', 0x73 => 'a76',
        0x74 => 'a77', 0x75 => 'a78', 0x76 => 'a79', 0x77 => 'a81',
        0x78 => 'a82', 0x79 => 'a83', 0x7A => 'a84', 0x7B => 'a97',
        0x7C => 'a98', 0x7D => 'a99', 0x7E => 'a100',
        0xA1 => 'a101', 0xA2 => 'a102', 0xA3 => 'a103', 0xA4 => 'a104',
        0xA5 => 'a106', 0xA6 => 'a107', 0xA7 => 'a108', 0xA8 => 'a112',
        0xA9 => 'a111', 0xAA => 'a110', 0xAB => 'a109', 0xAC => 'a120',
        0xAD => 'a121', 0xAE => 'a122', 0xAF => 'a123', 0xB0 => 'a124',
        0xB1 => 'a125', 0xB2 => 'a126', 0xB3 => 'a127', 0xB4 => 'a128',
        0xB5 => 'a129', 0xB6 => 'a130', 0xB7 => 'a131', 0xB8 => 'a132',
        0xB9 => 'a133', 0xBA => 'a134', 0xBB => 'a135', 0xBC => 'a136',
        0xBD => 'a137', 0xBE => 'a138', 0xBF => 'a139', 0xC0 => 'a140',
        0xC1 => 'a141', 0xC2 => 'a142', 0xC3 => 'a143', 0xC4 => 'a144',
        0xC5 => 'a145', 0xC6 => 'a146', 0xC7 => 'a147', 0xC8 => 'a148',
        0xC9 => 'a149', 0xCA => 'a150', 0xCB => 'a151', 0xCC => 'a152',
        0xCD => 'a153', 0xCE => 'a154', 0xCF => 'a155', 0xD0 => 'a156',
        0xD1 => 'a157', 0xD2 => 'a158', 0xD3 => 'a159', 0xD4 => 'a160',
        0xD5 => 'a161', 0xD6 => 'a163', 0xD7 => 'a164', 0xD8 => 'a196',
        0xD9 => 'a165', 0xDA => 'a192', 0xDB => 'a166', 0xDC => 'a167',
        0xDD => 'a168', 0xDE => 'a169', 0xDF => 'a170', 0xE0 => 'a171',
        0xE1 => 'a172', 0xE2 => 'a173', 0xE3 => 'a162', 0xE4 => 'a174',
        0xE5 => 'a175', 0xE6 => 'a176', 0xE7 => 'a177', 0xE8 => 'a178',
        0xE9 => 'a179', 0xEA => 'a193', 0xEB => 'a180', 0xEC => 'a199',
        0xED => 'a181', 0xEE => 'a200', 0xEF => 'a182', 0xF1 => 'a201',
        0xF2 => 'a183', 0xF3 => 'a184', 0xF4 => 'a197', 0xF5 => 'a185',
        0xF6 => 'a194', 0xF7 => 'a198', 0xF8 => 'a186', 0xF9 => 'a195',
        0xFA => 'a187', 0xFB => 'a188', 0xFC => 'a189', 0xFD => 'a190',
        0xFE => 'a191',
    ];
    private const CFF_EXPERT_ENCODING_SIDS = [
        32 => 1, 33 => 229, 34 => 230, 36 => 231, 37 => 232, 38 => 233, 39 => 234,
        40 => 235, 41 => 236, 42 => 237, 43 => 238, 44 => 13, 45 => 14, 46 => 15, 47 => 99,
        48 => 239, 49 => 240, 50 => 241, 51 => 242, 52 => 243, 53 => 244, 54 => 245, 55 => 246,
        56 => 247, 57 => 248, 58 => 27, 59 => 28, 60 => 249, 61 => 250, 62 => 251, 63 => 252,
        65 => 253, 66 => 254, 67 => 255, 68 => 256, 69 => 257, 73 => 258, 76 => 259, 77 => 260,
        78 => 261, 79 => 262, 82 => 263, 83 => 264, 84 => 265, 86 => 266, 87 => 109, 88 => 110,
        89 => 267, 90 => 268, 91 => 269, 93 => 270, 94 => 271, 95 => 272, 96 => 273, 97 => 274,
        98 => 275, 99 => 276, 100 => 277, 101 => 278, 102 => 279, 103 => 280, 104 => 281,
        105 => 282, 106 => 283, 107 => 284, 108 => 285, 109 => 286, 110 => 287, 111 => 288,
        112 => 289, 113 => 290, 114 => 291, 115 => 292, 116 => 293, 117 => 294, 118 => 295,
        119 => 296, 120 => 297, 121 => 298, 122 => 299, 123 => 300, 124 => 301, 125 => 302,
        126 => 303, 161 => 304, 162 => 305, 163 => 306, 166 => 307, 167 => 308, 168 => 309,
        169 => 310, 170 => 311, 172 => 312, 175 => 313, 178 => 314, 179 => 315, 182 => 316,
        183 => 317, 184 => 318, 188 => 158, 189 => 155, 190 => 163, 191 => 319, 192 => 320,
        193 => 321, 194 => 322, 195 => 323, 196 => 324, 197 => 325, 200 => 326, 201 => 150,
        202 => 164, 203 => 169, 204 => 327, 205 => 328, 206 => 329, 207 => 330, 208 => 331,
        209 => 332, 210 => 333, 211 => 334, 212 => 335, 213 => 336, 214 => 337, 215 => 338,
        216 => 339, 217 => 340, 218 => 341, 219 => 342, 220 => 343, 221 => 344, 222 => 345,
        223 => 346, 224 => 347, 225 => 348, 226 => 349, 227 => 350, 228 => 351, 229 => 352,
        230 => 353, 231 => 354, 232 => 355, 233 => 356, 234 => 357, 235 => 358, 236 => 359,
        237 => 360, 238 => 361, 239 => 362, 240 => 363, 241 => 364, 242 => 365, 243 => 366,
        244 => 367, 245 => 368, 246 => 369, 247 => 370, 248 => 371, 249 => 372, 250 => 373,
        251 => 374, 252 => 375, 253 => 376, 254 => 377, 255 => 378,
    ];
    private const CFF_EXPERT_CHARSET_SIDS = [
        1, 229, 230, 231, 232, 233, 234, 235, 236, 237, 238, 13, 14, 15, 99,
        239, 240, 241, 242, 243, 244, 245, 246, 247, 248, 27, 28, 249, 250, 251, 252,
        253, 254, 255, 256, 257, 258, 259, 260, 261, 262, 263, 264, 265, 266, 109, 110,
        267, 268, 269, 270, 271, 272, 273, 274, 275, 276, 277, 278, 279, 280, 281, 282,
        283, 284, 285, 286, 287, 288, 289, 290, 291, 292, 293, 294, 295, 296, 297, 298,
        299, 300, 301, 302, 303, 304, 305, 306, 307, 308, 309, 310, 311, 312, 313, 314,
        315, 316, 317, 318, 158, 155, 163, 319, 320, 321, 322, 323, 324, 325, 326, 150,
        164, 169, 327, 328, 329, 330, 331, 332, 333, 334, 335, 336, 337, 338, 339, 340,
        341, 342, 343, 344, 345, 346, 347, 348, 349, 350, 351, 352, 353, 354, 355, 356,
        357, 358, 359, 360, 361, 362, 363, 364, 365, 366, 367, 368, 369, 370, 371, 372,
        373, 374, 375, 376, 377, 378,
    ];
    private const CFF_EXPERT_SUBSET_CHARSET_SIDS = [
        1, 231, 232, 235, 236, 237, 238, 13, 14, 15, 99, 239, 240, 241, 242, 243,
        244, 245, 246, 247, 248, 27, 28, 249, 250, 251, 253, 254, 255, 256, 257, 258,
        259, 260, 261, 262, 263, 264, 265, 266, 109, 110, 267, 268, 269, 270, 272, 300,
        301, 302, 305, 314, 315, 158, 155, 163, 320, 321, 322, 323, 324, 325, 326, 150,
        164, 169, 327, 328, 329, 330, 331, 332, 333, 334, 335, 336, 337, 338, 339, 340,
        341, 342, 343, 344, 345, 346,
    ];
    private const CFF_STANDARD_STRING_NAMES = [
        96 => 'exclamdown', 97 => 'cent', 98 => 'sterling', 99 => 'fraction', 100 => 'yen',
        101 => 'florin', 102 => 'section', 103 => 'currency', 104 => 'quotesingle',
        105 => 'quotedblleft', 106 => 'guillemotleft', 107 => 'guilsinglleft', 108 => 'guilsinglright',
        109 => 'fi', 110 => 'fl', 111 => 'endash', 112 => 'dagger', 113 => 'daggerdbl',
        114 => 'periodcentered', 115 => 'paragraph', 116 => 'bullet', 117 => 'quotesinglbase',
        118 => 'quotedblbase', 119 => 'quotedblright', 120 => 'guillemotright', 121 => 'ellipsis',
        122 => 'perthousand', 123 => 'questiondown', 124 => 'grave', 125 => 'acute',
        126 => 'circumflex', 127 => 'tilde', 128 => 'macron', 129 => 'breve', 130 => 'dotaccent',
        131 => 'dieresis', 132 => 'ring', 133 => 'cedilla', 134 => 'hungarumlaut', 135 => 'ogonek',
        136 => 'caron', 137 => 'emdash', 138 => 'AE', 139 => 'ordfeminine', 140 => 'Lslash',
        141 => 'Oslash', 142 => 'OE', 143 => 'ordmasculine', 144 => 'ae', 145 => 'dotlessi',
        146 => 'lslash', 147 => 'oslash', 148 => 'oe', 149 => 'germandbls', 150 => 'onesuperior',
        151 => 'logicalnot', 152 => 'mu', 153 => 'trademark', 154 => 'Eth', 155 => 'onehalf',
        156 => 'plusminus', 157 => 'Thorn', 158 => 'onequarter', 159 => 'divide', 160 => 'brokenbar',
        161 => 'degree', 162 => 'thorn', 163 => 'threequarters', 164 => 'twosuperior',
        165 => 'registered', 166 => 'minus', 167 => 'eth', 168 => 'multiply', 169 => 'threesuperior',
        170 => 'copyright', 171 => 'Aacute', 172 => 'Acircumflex', 173 => 'Adieresis',
        174 => 'Agrave', 175 => 'Aring', 176 => 'Atilde', 177 => 'Ccedilla', 178 => 'Eacute',
        179 => 'Ecircumflex', 180 => 'Edieresis', 181 => 'Egrave', 182 => 'Iacute',
        183 => 'Icircumflex', 184 => 'Idieresis', 185 => 'Igrave', 186 => 'Ntilde',
        187 => 'Oacute', 188 => 'Ocircumflex', 189 => 'Odieresis', 190 => 'Ograve',
        191 => 'Otilde', 192 => 'Scaron', 193 => 'Uacute', 194 => 'Ucircumflex',
        195 => 'Udieresis', 196 => 'Ugrave', 197 => 'Yacute', 198 => 'Ydieresis',
        199 => 'Zcaron', 200 => 'aacute', 201 => 'acircumflex', 202 => 'adieresis',
        203 => 'agrave', 204 => 'aring', 205 => 'atilde', 206 => 'ccedilla', 207 => 'eacute',
        208 => 'ecircumflex', 209 => 'edieresis', 210 => 'egrave', 211 => 'iacute',
        212 => 'icircumflex', 213 => 'idieresis', 214 => 'igrave', 215 => 'ntilde',
        216 => 'oacute', 217 => 'ocircumflex', 218 => 'odieresis', 219 => 'ograve',
        220 => 'otilde', 221 => 'scaron', 222 => 'uacute', 223 => 'ucircumflex',
        224 => 'udieresis', 225 => 'ugrave', 226 => 'yacute', 227 => 'ydieresis',
        228 => 'zcaron', 229 => 'exclamsmall', 230 => 'Hungarumlautsmall',
        231 => 'dollaroldstyle', 232 => 'dollarsuperior', 233 => 'ampersandsmall',
        234 => 'Acutesmall', 235 => 'parenleftsuperior', 236 => 'parenrightsuperior',
        237 => 'twodotenleader', 238 => 'onedotenleader', 239 => 'zerooldstyle',
        240 => 'oneoldstyle', 241 => 'twooldstyle', 242 => 'threeoldstyle',
        243 => 'fouroldstyle', 244 => 'fiveoldstyle', 245 => 'sixoldstyle',
        246 => 'sevenoldstyle', 247 => 'eightoldstyle', 248 => 'nineoldstyle',
        249 => 'commasuperior', 250 => 'threequartersemdash', 251 => 'periodsuperior',
        252 => 'questionsmall', 253 => 'asuperior', 254 => 'bsuperior',
        255 => 'centsuperior', 256 => 'dsuperior', 257 => 'esuperior', 258 => 'isuperior',
        259 => 'lsuperior', 260 => 'msuperior', 261 => 'nsuperior', 262 => 'osuperior',
        263 => 'rsuperior', 264 => 'ssuperior', 265 => 'tsuperior', 266 => 'ff',
        267 => 'ffi', 268 => 'ffl', 269 => 'parenleftinferior', 270 => 'parenrightinferior',
        271 => 'Circumflexsmall', 272 => 'hyphensuperior', 273 => 'Gravesmall',
        274 => 'Asmall', 275 => 'Bsmall', 276 => 'Csmall', 277 => 'Dsmall', 278 => 'Esmall',
        279 => 'Fsmall', 280 => 'Gsmall', 281 => 'Hsmall', 282 => 'Ismall', 283 => 'Jsmall',
        284 => 'Ksmall', 285 => 'Lsmall', 286 => 'Msmall', 287 => 'Nsmall', 288 => 'Osmall',
        289 => 'Psmall', 290 => 'Qsmall', 291 => 'Rsmall', 292 => 'Ssmall', 293 => 'Tsmall',
        294 => 'Usmall', 295 => 'Vsmall', 296 => 'Wsmall', 297 => 'Xsmall', 298 => 'Ysmall',
        299 => 'Zsmall', 300 => 'colonmonetary', 301 => 'onefitted', 302 => 'rupiah',
        303 => 'Tildesmall', 304 => 'exclamdownsmall', 305 => 'centoldstyle',
        306 => 'Lslashsmall', 307 => 'Scaronsmall', 308 => 'Zcaronsmall',
        309 => 'Dieresissmall', 310 => 'Brevesmall', 311 => 'Caronsmall',
        312 => 'Dotaccentsmall', 313 => 'Macronsmall', 314 => 'figuredash',
        315 => 'hypheninferior', 316 => 'Ogoneksmall', 317 => 'Ringsmall',
        318 => 'Cedillasmall', 319 => 'questiondownsmall', 320 => 'oneeighth',
        321 => 'threeeighths', 322 => 'fiveeighths', 323 => 'seveneighths',
        324 => 'onethird', 325 => 'twothirds', 326 => 'zerosuperior',
        327 => 'foursuperior', 328 => 'fivesuperior', 329 => 'sixsuperior',
        330 => 'sevensuperior', 331 => 'eightsuperior', 332 => 'ninesuperior',
        333 => 'zeroinferior', 334 => 'oneinferior', 335 => 'twoinferior',
        336 => 'threeinferior', 337 => 'fourinferior', 338 => 'fiveinferior',
        339 => 'sixinferior', 340 => 'seveninferior', 341 => 'eightinferior',
        342 => 'nineinferior', 343 => 'centinferior', 344 => 'dollarinferior',
        345 => 'periodinferior', 346 => 'commainferior', 347 => 'Agravesmall',
        348 => 'Aacutesmall', 349 => 'Acircumflexsmall', 350 => 'Atildesmall',
        351 => 'Adieresissmall', 352 => 'Aringsmall', 353 => 'AEsmall',
        354 => 'Ccedillasmall', 355 => 'Egravesmall', 356 => 'Eacutesmall',
        357 => 'Ecircumflexsmall', 358 => 'Edieresissmall', 359 => 'Igravesmall',
        360 => 'Iacutesmall', 361 => 'Icircumflexsmall', 362 => 'Idieresissmall',
        363 => 'Ethsmall', 364 => 'Ntildesmall', 365 => 'Ogravesmall',
        366 => 'Oacutesmall', 367 => 'Ocircumflexsmall', 368 => 'Otildesmall',
        369 => 'Odieresissmall', 370 => 'OEsmall', 371 => 'Oslashsmall',
        372 => 'Ugravesmall', 373 => 'Uacutesmall', 374 => 'Ucircumflexsmall',
        375 => 'Udieresissmall', 376 => 'Yacutesmall', 377 => 'Thornsmall',
        378 => 'Ydieresissmall',
    ];
    private const STANDARD_GLYPH_NAME_MAP = [
        'Alpha' => "\u{0391}", 'Beta' => "\u{0392}", 'Chi' => "\u{03A7}", 'Delta' => "\u{2206}",
        'Epsilon' => "\u{0395}", 'Eta' => "\u{0397}", 'Gamma' => "\u{0393}", 'Ifraktur' => "\u{2111}",
        'Iota' => "\u{0399}", 'Kappa' => "\u{039A}", 'Lambda' => "\u{039B}", 'Mu' => "\u{039C}",
        'Nu' => "\u{039D}", 'Omega' => "\u{2126}", 'Omicron' => "\u{039F}", 'Phi' => "\u{03A6}",
        'Pi' => "\u{03A0}", 'Psi' => "\u{03A8}", 'Rfraktur' => "\u{211C}", 'Rho' => "\u{03A1}",
        'Sigma' => "\u{03A3}", 'Tau' => "\u{03A4}", 'Theta' => "\u{0398}", 'Upsilon' => "\u{03A5}",
        'Upsilon1' => "\u{03D2}", 'Xi' => "\u{039E}", 'Zeta' => "\u{0396}",
        'a1' => "\u{2701}", 'a2' => "\u{2702}", 'a3' => "\u{2704}", 'a4' => "\u{260E}",
        'a5' => "\u{2706}", 'a6' => "\u{271D}", 'a7' => "\u{271E}", 'a8' => "\u{271F}",
        'a9' => "\u{2720}", 'a10' => "\u{2721}", 'a11' => "\u{261B}", 'a12' => "\u{261E}",
        'a13' => "\u{270C}", 'a14' => "\u{270D}", 'a15' => "\u{270E}", 'a16' => "\u{270F}",
        'a17' => "\u{2711}", 'a18' => "\u{2712}", 'a19' => "\u{2713}", 'a20' => "\u{2714}",
        'a21' => "\u{2715}", 'a22' => "\u{2716}", 'a23' => "\u{2717}", 'a24' => "\u{2718}",
        'a25' => "\u{2719}", 'a26' => "\u{271A}", 'a27' => "\u{271B}", 'a28' => "\u{271C}",
        'a29' => "\u{2722}", 'a30' => "\u{2723}", 'a31' => "\u{2724}", 'a32' => "\u{2725}",
        'a33' => "\u{2726}", 'a34' => "\u{2727}", 'a35' => "\u{2605}", 'a36' => "\u{2729}",
        'a37' => "\u{272A}", 'a38' => "\u{272B}", 'a39' => "\u{272C}", 'a40' => "\u{272D}",
        'a41' => "\u{272E}", 'a42' => "\u{272F}", 'a43' => "\u{2730}", 'a44' => "\u{2731}",
        'a45' => "\u{2732}", 'a46' => "\u{2733}", 'a47' => "\u{2734}", 'a48' => "\u{2735}",
        'a49' => "\u{2736}", 'a50' => "\u{2737}", 'a51' => "\u{2738}", 'a52' => "\u{2739}",
        'a53' => "\u{273A}", 'a54' => "\u{273B}", 'a55' => "\u{273C}", 'a56' => "\u{273D}",
        'a57' => "\u{273E}", 'a58' => "\u{273F}", 'a59' => "\u{2740}", 'a60' => "\u{2741}",
        'a61' => "\u{2742}", 'a62' => "\u{2743}", 'a63' => "\u{2744}", 'a64' => "\u{2745}",
        'a65' => "\u{2746}", 'a66' => "\u{2747}", 'a67' => "\u{2748}", 'a68' => "\u{2749}",
        'a69' => "\u{274A}", 'a70' => "\u{274B}", 'a71' => "\u{25CF}", 'a72' => "\u{274D}",
        'a73' => "\u{25A0}", 'a74' => "\u{274F}", 'a75' => "\u{2751}", 'a76' => "\u{25B2}",
        'a77' => "\u{25BC}", 'a78' => "\u{25C6}", 'a79' => "\u{2756}", 'a81' => "\u{25D7}",
        'a82' => "\u{2758}", 'a83' => "\u{2759}", 'a84' => "\u{275A}", 'a97' => "\u{275B}",
        'a98' => "\u{275C}", 'a99' => "\u{275D}", 'a100' => "\u{275E}", 'a101' => "\u{2761}",
        'a102' => "\u{2762}", 'a103' => "\u{2763}", 'a104' => "\u{2764}", 'a105' => "\u{2710}",
        'a106' => "\u{2765}", 'a107' => "\u{2766}", 'a108' => "\u{2767}", 'a109' => "\u{2660}",
        'a110' => "\u{2665}", 'a111' => "\u{2666}", 'a112' => "\u{2663}", 'a117' => "\u{2709}",
        'a118' => "\u{2708}", 'a119' => "\u{2707}", 'a120' => "\u{2460}", 'a121' => "\u{2461}",
        'a122' => "\u{2462}", 'a123' => "\u{2463}", 'a124' => "\u{2464}", 'a125' => "\u{2465}",
        'a126' => "\u{2466}", 'a127' => "\u{2467}", 'a128' => "\u{2468}", 'a129' => "\u{2469}",
        'a130' => "\u{2776}", 'a131' => "\u{2777}", 'a132' => "\u{2778}", 'a133' => "\u{2779}",
        'a134' => "\u{277A}", 'a135' => "\u{277B}", 'a136' => "\u{277C}", 'a137' => "\u{277D}",
        'a138' => "\u{277E}", 'a139' => "\u{277F}", 'a140' => "\u{2780}", 'a141' => "\u{2781}",
        'a142' => "\u{2782}", 'a143' => "\u{2783}", 'a144' => "\u{2784}", 'a145' => "\u{2785}",
        'a146' => "\u{2786}", 'a147' => "\u{2787}", 'a148' => "\u{2788}", 'a149' => "\u{2789}",
        'a150' => "\u{278A}", 'a151' => "\u{278B}", 'a152' => "\u{278C}", 'a153' => "\u{278D}",
        'a154' => "\u{278E}", 'a155' => "\u{278F}", 'a156' => "\u{2790}", 'a157' => "\u{2791}",
        'a158' => "\u{2792}", 'a159' => "\u{2793}", 'a160' => "\u{2794}", 'a161' => "\u{2192}",
        'a162' => "\u{27A3}", 'a163' => "\u{2194}", 'a164' => "\u{2195}", 'a165' => "\u{2799}",
        'a166' => "\u{279B}", 'a167' => "\u{279C}", 'a168' => "\u{279D}", 'a169' => "\u{279E}",
        'a170' => "\u{279F}", 'a171' => "\u{27A0}", 'a172' => "\u{27A1}", 'a173' => "\u{27A2}",
        'a174' => "\u{27A4}", 'a175' => "\u{27A5}", 'a176' => "\u{27A6}", 'a177' => "\u{27A7}",
        'a178' => "\u{27A8}", 'a179' => "\u{27A9}", 'a180' => "\u{27AB}", 'a181' => "\u{27AD}",
        'a182' => "\u{27AF}", 'a183' => "\u{27B2}", 'a184' => "\u{27B3}", 'a185' => "\u{27B5}",
        'a186' => "\u{27B8}", 'a187' => "\u{27BA}", 'a188' => "\u{27BB}", 'a189' => "\u{27BC}",
        'a190' => "\u{27BD}", 'a191' => "\u{27BE}", 'a192' => "\u{279A}", 'a193' => "\u{27AA}",
        'a194' => "\u{27B6}", 'a195' => "\u{27B9}", 'a196' => "\u{2798}", 'a197' => "\u{27B4}",
        'a198' => "\u{27B7}", 'a199' => "\u{27AC}", 'a200' => "\u{27AE}", 'a201' => "\u{27B1}",
        'a202' => "\u{2703}", 'a203' => "\u{2750}", 'a204' => "\u{2752}",
        'aleph' => "\u{2135}", 'alpha' => "\u{03B1}", 'ampersand' => "\u{0026}", 'angle' => "\u{2220}",
        'angleleft' => "\u{2329}", 'angleright' => "\u{232A}", 'approxequal' => "\u{2248}",
        'arrowboth' => "\u{2194}", 'arrowdblboth' => "\u{21D4}", 'arrowdbldown' => "\u{21D3}",
        'arrowdblleft' => "\u{21D0}", 'arrowdblright' => "\u{21D2}", 'arrowdblup' => "\u{21D1}",
        'arrowdown' => "\u{2193}", 'arrowhorizex' => "\u{F8E7}", 'arrowleft' => "\u{2190}",
        'arrowright' => "\u{2192}", 'arrowup' => "\u{2191}", 'arrowvertex' => "\u{F8E6}",
        'asteriskmath' => "\u{2217}", 'bar' => "\u{007C}", 'beta' => "\u{03B2}",
        'braceex' => "\u{F8F4}", 'braceleft' => "\u{007B}", 'braceleftbt' => "\u{F8F3}",
        'braceleftmid' => "\u{F8F2}", 'bracelefttp' => "\u{F8F1}", 'braceright' => "\u{007D}",
        'bracerightbt' => "\u{F8FE}", 'bracerightmid' => "\u{F8FD}", 'bracerighttp' => "\u{F8FC}",
        'bracketleft' => "\u{005B}", 'bracketleftbt' => "\u{F8F0}", 'bracketleftex' => "\u{F8EF}",
        'bracketlefttp' => "\u{F8EE}", 'bracketright' => "\u{005D}", 'bracketrightbt' => "\u{F8FB}",
        'bracketrightex' => "\u{F8FA}", 'bracketrighttp' => "\u{F8F9}", 'carriagereturn' => "\u{21B5}",
        'chi' => "\u{03C7}", 'circlemultiply' => "\u{2297}", 'circleplus' => "\u{2295}",
        'club' => "\u{2663}", 'colon' => "\u{003A}", 'comma' => "\u{002C}", 'congruent' => "\u{2245}",
        'copyrightsans' => "\u{F8E9}", 'copyrightserif' => "\u{F6D9}", 'delta' => "\u{03B4}",
        'degree' => "\u{00B0}", 'diamond' => "\u{2666}", 'divide' => "\u{00F7}",
        'dotmath' => "\u{22C5}", 'eight' => "\u{0038}", 'element' => "\u{2208}",
        'emptyset' => "\u{2205}", 'epsilon' => "\u{03B5}", 'equal' => "\u{003D}",
        'equivalence' => "\u{2261}", 'eta' => "\u{03B7}",
        'exclam' => "\u{0021}", 'existential' => "\u{2203}", 'florin' => "\u{0192}", 'fraction' => "\u{2044}",
        'five' => "\u{0035}", 'four' => "\u{0034}", 'gamma' => "\u{03B3}", 'gradient' => "\u{2207}",
        'greater' => "\u{003E}", 'greaterequal' => "\u{2265}", 'heart' => "\u{2665}",
        'infinity' => "\u{221E}", 'integral' => "\u{222B}", 'integralbt' => "\u{2321}",
        'integralex' => "\u{F8F5}", 'integraltp' => "\u{2320}", 'intersection' => "\u{2229}",
        'iota' => "\u{03B9}", 'kappa' => "\u{03BA}", 'lambda' => "\u{03BB}", 'less' => "\u{003C}",
        'lessequal' => "\u{2264}", 'logicaland' => "\u{2227}", 'logicalnot' => "\u{00AC}",
        'logicalor' => "\u{2228}", 'lozenge' => "\u{25CA}", 'mu' => "\u{00B5}",
        'minute' => "\u{2032}", 'multiply' => "\u{00D7}", 'nine' => "\u{0039}",
        'notelement' => "\u{2209}", 'notequal' => "\u{2260}", 'notsubset' => "\u{2284}",
        'nu' => "\u{03BD}", 'numbersign' => "\u{0023}",
        'omega' => "\u{03C9}", 'omega1' => "\u{03D6}", 'omicron' => "\u{03BF}", 'one' => "\u{0031}",
        'parenleft' => "\u{0028}", 'parenleftbt' => "\u{F8ED}",
        'parenleftex' => "\u{F8EC}", 'parenlefttp' => "\u{F8EB}", 'parenrightbt' => "\u{F8F8}",
        'parenrightex' => "\u{F8F7}", 'parenright' => "\u{0029}", 'parenrighttp' => "\u{F8F6}",
        'partialdiff' => "\u{2202}", 'percent' => "\u{0025}", 'period' => "\u{002E}",
        'perpendicular' => "\u{22A5}", 'phi' => "\u{03C6}", 'phi1' => "\u{03D5}",
        'pi' => "\u{03C0}", 'plus' => "\u{002B}", 'plusminus' => "\u{00B1}",
        'product' => "\u{220F}", 'propersubset' => "\u{2282}", 'propersuperset' => "\u{2283}",
        'proportional' => "\u{221D}", 'psi' => "\u{03C8}", 'question' => "\u{003F}",
        'radical' => "\u{221A}", 'radicalex' => "\u{F8E5}", 'reflexsubset' => "\u{2286}", 'reflexsuperset' => "\u{2287}",
        'registersans' => "\u{F8E8}", 'registerserif' => "\u{F6DA}", 'rho' => "\u{03C1}",
        'second' => "\u{2033}", 'semicolon' => "\u{003B}", 'seven' => "\u{0037}",
        'sigma' => "\u{03C3}", 'sigma1' => "\u{03C2}", 'similar' => "\u{223C}",
        'six' => "\u{0036}", 'slash' => "\u{002F}", 'spade' => "\u{2660}", 'suchthat' => "\u{220B}",
        'summation' => "\u{2211}", 'tau' => "\u{03C4}", 'therefore' => "\u{2234}", 'three' => "\u{0033}",
        'theta' => "\u{03B8}", 'theta1' => "\u{03D1}", 'trademarksans' => "\u{F8EA}",
        'trademarkserif' => "\u{F6DB}", 'two' => "\u{0032}", 'underscore' => "\u{005F}",
        'union' => "\u{222A}", 'universal' => "\u{2200}", 'upsilon' => "\u{03C5}",
        'weierstrass' => "\u{2118}", 'xi' => "\u{03BE}", 'zero' => "\u{0030}", 'zeta' => "\u{03B6}",
    ];
    private const GLYPH_NAME_MAP = [
        'space' => ' ',
        'nbspace' => "\u{00A0}",
        'Euro' => "\u{20AC}",
        'bullet' => "\u{2022}",
        'ellipsis' => "\u{2026}",
        'emdash' => "\u{2014}",
        'endash' => "\u{2013}",
        'hyphen' => '-',
        'minus' => "\u{2212}",
        'dollar' => '$',
        'backslash' => '\\',
        'asciicircum' => '^',
        'grave' => '`',
        'asciitilde' => '~',
        'asterisk' => '*',
        'cent' => "\u{00A2}",
        'sterling' => "\u{00A3}",
        'currency' => "\u{00A4}",
        'yen' => "\u{00A5}",
        'brokenbar' => "\u{00A6}",
        'section' => "\u{00A7}",
        'dieresis' => "\u{00A8}",
        'copyright' => "\u{00A9}",
        'ordfeminine' => "\u{00AA}",
        'guillemotleft' => "\u{00AB}",
        'logicalnot' => "\u{00AC}",
        'registered' => "\u{00AE}",
        'macron' => "\u{00AF}",
        'degree' => "\u{00B0}",
        'plusminus' => "\u{00B1}",
        'acute' => "\u{00B4}",
        'mu' => "\u{00B5}",
        'paragraph' => "\u{00B6}",
        'periodcentered' => "\u{00B7}",
        'cedilla' => "\u{00B8}",
        'ordmasculine' => "\u{00BA}",
        'guillemotright' => "\u{00BB}",
        'onequarter' => "\u{00BC}",
        'onehalf' => "\u{00BD}",
        'threequarters' => "\u{00BE}",
        'questiondown' => "\u{00BF}",
        'AE' => "\u{00C6}",
        'Eth' => "\u{00D0}",
        'multiply' => "\u{00D7}",
        'Oslash' => "\u{00D8}",
        'Thorn' => "\u{00DE}",
        'germandbls' => "\u{00DF}",
        'ae' => "\u{00E6}",
        'eth' => "\u{00F0}",
        'divide' => "\u{00F7}",
        'oslash' => "\u{00F8}",
        'thorn' => "\u{00FE}",
        'dotlessi' => "\u{0131}",
        'Lslash' => "\u{0141}",
        'lslash' => "\u{0142}",
        'OE' => "\u{0152}",
        'oe' => "\u{0153}",
        'Scaron' => "\u{0160}",
        'scaron' => "\u{0161}",
        'Ydieresis' => "\u{0178}",
        'Zcaron' => "\u{017D}",
        'zcaron' => "\u{017E}",
        'florin' => "\u{0192}",
        'circumflex' => "\u{02C6}",
        'caron' => "\u{02C7}",
        'breve' => "\u{02D8}",
        'dotaccent' => "\u{02D9}",
        'ring' => "\u{02DA}",
        'ogonek' => "\u{02DB}",
        'tilde' => "\u{02DC}",
        'hungarumlaut' => "\u{02DD}",
        'oneeighth' => "\u{215B}",
        'threeeighths' => "\u{215C}",
        'fiveeighths' => "\u{215D}",
        'seveneighths' => "\u{215E}",
        'onethird' => "\u{2153}",
        'twothirds' => "\u{2154}",
        'figuredash' => "\u{2012}",
        'threequartersemdash' => "\u{2014}",
        'twodotenleader' => '..',
        'onedotenleader' => '.',
        'colonmonetary' => "\u{20A1}",
        'onefitted' => '1',
        'rupiah' => 'Rp',
        'ff' => 'ff',
        'ffi' => 'ffi',
        'ffl' => 'ffl',
        'Aacute' => "\u{00C1}",
        'aacute' => "\u{00E1}",
        'Acircumflex' => "\u{00C2}",
        'acircumflex' => "\u{00E2}",
        'Adieresis' => "\u{00C4}",
        'adieresis' => "\u{00E4}",
        'Agrave' => "\u{00C0}",
        'agrave' => "\u{00E0}",
        'Aring' => "\u{00C5}",
        'aring' => "\u{00E5}",
        'Atilde' => "\u{00C3}",
        'atilde' => "\u{00E3}",
        'Ccedilla' => "\u{00C7}",
        'ccedilla' => "\u{00E7}",
        'Eacute' => "\u{00C9}",
        'eacute' => "\u{00E9}",
        'Ecircumflex' => "\u{00CA}",
        'ecircumflex' => "\u{00EA}",
        'Edieresis' => "\u{00CB}",
        'edieresis' => "\u{00EB}",
        'Egrave' => "\u{00C8}",
        'egrave' => "\u{00E8}",
        'Iacute' => "\u{00CD}",
        'iacute' => "\u{00ED}",
        'Icircumflex' => "\u{00CE}",
        'icircumflex' => "\u{00EE}",
        'Idieresis' => "\u{00CF}",
        'idieresis' => "\u{00EF}",
        'Igrave' => "\u{00CC}",
        'igrave' => "\u{00EC}",
        'Ntilde' => "\u{00D1}",
        'ntilde' => "\u{00F1}",
        'Oacute' => "\u{00D3}",
        'oacute' => "\u{00F3}",
        'Ocircumflex' => "\u{00D4}",
        'ocircumflex' => "\u{00F4}",
        'Odieresis' => "\u{00D6}",
        'odieresis' => "\u{00F6}",
        'Ograve' => "\u{00D2}",
        'ograve' => "\u{00F2}",
        'Otilde' => "\u{00D5}",
        'otilde' => "\u{00F5}",
        'Uacute' => "\u{00DA}",
        'uacute' => "\u{00FA}",
        'Ucircumflex' => "\u{00DB}",
        'ucircumflex' => "\u{00FB}",
        'Udieresis' => "\u{00DC}",
        'udieresis' => "\u{00FC}",
        'Ugrave' => "\u{00D9}",
        'ugrave' => "\u{00F9}",
        'quotesingle' => "'",
        'quotedbl' => '"',
        'quoteleft' => "\u{2018}",
        'quoteright' => "\u{2019}",
        'quotedblleft' => "\u{201C}",
        'quotedblright' => "\u{201D}",
        'fi' => 'fi',
        'fl' => 'fl',
    ];

    /**
     * @param array{password?: string, pdfPassword?: string, maxPages?: int, pdfMaxPages?: int, max_pages?: int, maxDecodedStreamBytes?: int, pdfMaxDecodedStreamBytes?: int, maxTokenizedContentStreamBytes?: int, pdfMaxTokenizedContentStreamBytes?: int, maxContentTokens?: int, pdfMaxContentTokens?: int, maxPositionedTextRuns?: int, pdfMaxPositionedTextRuns?: int} $options
     */
    public function __construct(private readonly array $options = [])
    {
    }

    private function pdfPassword(): string
    {
        return (string) ($this->options['password'] ?? $this->options['pdfPassword'] ?? '');
    }

    /**
     * @return list<string>
     */
    public function extractTextRuns(string $pdfBytes): array
    {
        if ($this->isEncrypted($pdfBytes) && $this->pdfPassword() === '') {
            return [];
        }

        $runs = [];
        foreach ($this->limitedStreamContexts($pdfBytes) as $context) {
            foreach ($this->textRunsFromContentStream($context['stream'], $context['fontToUnicodeMaps'], $context['fontEncodings'], $context['propertyActualTexts'], $context['mcidActualTexts'], $context['propertyMcids']) as $run) {
                if ($run !== '') {
                    $runs[] = $run;
                }
            }
        }

        return $runs;
    }

    /**
     * @return list<array{page: int, stream: int, text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float, pageObject?: int}>
     */
    public function extractPositionedTextRuns(string $pdfBytes): array
    {
        if ($this->isEncrypted($pdfBytes) && $this->pdfPassword() === '') {
            return [];
        }

        $runs = [];
        $streamNumber = 0;
        $maxRuns = $this->maxPositionedTextRuns();
        foreach ($this->limitedStreamContexts($pdfBytes) as $context) {
            $streamNumber++;
            $page = is_int($context['page'] ?? null) ? $context['page'] : $streamNumber;
            foreach ($this->positionedTextRunsFromContentStream($context['stream'], $context['fontToUnicodeMaps'], $context['fontEncodings'], $context['propertyActualTexts'], $context['mcidActualTexts'], $context['propertyMcids']) as $run) {
                if ($run['text'] === '') {
                    continue;
                }

                $positionedRun = [
                    'page' => $page,
                    'stream' => $streamNumber,
                ] + $run;
                if (is_int($context['pageObject'] ?? null)) {
                    $positionedRun['pageObject'] = $context['pageObject'];
                }

                $runs[] = $positionedRun;
                if (count($runs) >= $maxRuns) {
                    return $runs;
                }
            }
        }

        return $runs;
    }

    /**
     * @return list<array{page: int, stream: int, x1: float, y1: float, x2: float, y2: float, fillColor: string, pageObject?: int}>
     */
    public function extractFilledRectangles(string $pdfBytes): array
    {
        if ($this->isEncrypted($pdfBytes) && $this->pdfPassword() === '') {
            return [];
        }

        $rectangles = [];
        $streamNumber = 0;
        foreach ($this->limitedStreamContexts($pdfBytes) as $context) {
            $streamNumber++;
            $page = is_int($context['page'] ?? null) ? $context['page'] : $streamNumber;
            foreach ($this->filledRectanglesFromContentStream($context['stream']) as $rectangle) {
                if (($rectangle['fillColor'] ?? '') === '#ffffff') {
                    continue;
                }

                $filledRectangle = [
                    'page' => $page,
                    'stream' => $streamNumber,
                ] + $rectangle;
                if (is_int($context['pageObject'] ?? null)) {
                    $filledRectangle['pageObject'] = $context['pageObject'];
                }

                $rectangles[] = $filledRectangle;
            }
        }

        return $rectangles;
    }

    public function extractPlainText(string $pdfBytes): string
    {
        return implode("\n", $this->extractTextLines($pdfBytes));
    }

    public function isEncrypted(string $pdfBytes): bool
    {
        return preg_match('/\/Encrypt\b/', $pdfBytes) === 1;
    }

    /**
     * Native boundary for PDF page-label metadata used by preview/import code.
     *
     * @return list<string>
     */
    public function extractPageLabels(string $pdfBytes): array
    {
        if ($this->isEncrypted($pdfBytes)) {
            return [];
        }

        $labels = $this->previewPageLabelsWithoutTextFallback($pdfBytes);
        if ($labels !== []) {
            return $labels;
        }

        $pageCount = count($this->extractPageTexts($pdfBytes));
        if ($pageCount === 0) {
            return [];
        }

        return array_map(
            static fn (int $pageNumber): string => (string) $pageNumber,
            range(1, $pageCount)
        );
    }

    /**
     * @return list<array{page: int, page_label: string, text: string}>
     */
    public function extractLabeledPageTexts(string $pdfBytes): array
    {
        $labels = $this->extractPageLabels($pdfBytes);
        $texts = $this->extractPageTexts($pdfBytes);
        $entries = [];
        foreach ($texts as $index => $text) {
            $entries[] = [
                'page' => $index + 1,
                'page_label' => $labels[$index] ?? (string) ($index + 1),
                'text' => $text,
            ];
        }

        return $entries;
    }

    /**
     * Lightweight compatibility boundary for marker's PDF outline metadata.
     *
     * @return array{pages: int, document_info: array<string, string>, pdf_toc: list<array<string, mixed>>}
     */
    public function extractOutlineMetadata(string $pdfBytes): array
    {
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdfBytes);
        $documentInfo = [];
        foreach (['title', 'author', 'subject', 'creator', 'producer'] as $key) {
            if (is_string($metadata[$key] ?? null) && $metadata[$key] !== '') {
                $documentInfo[$key] = $metadata[$key];
            }
        }

        return [
            'pages' => count($this->pageObjectNumbers($this->pdfObjects($pdfBytes), $pdfBytes)),
            'document_info' => $documentInfo,
            'pdf_toc' => array_map(
                static function (array $row): array {
                    if (array_key_exists('destination', $row) && $row['destination'] === null) {
                        unset($row['destination']);
                    }

                    return $row;
                },
                (new PdfOutlineExtractor())->getPdfToc($pdfBytes)
            ),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function extractTaggedContent(string $pdfBytes): array
    {
        if ($this->isEncrypted($pdfBytes) && $this->pdfPassword() === '') {
            return [];
        }

        $diagnostics = $this->diagnostics($pdfBytes);
        $items = $diagnostics['taggedStructureItems'] ?? [];

        return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
    }

    /**
     * Review-only stream/CMap boundary summary. The text extractor consumes the
     * same decoded stream path for actual import; this method exposes enough
     * structured provenance for regression tests and diagnostics without
     * retaining stream payloads.
     *
     * @return array<string, mixed>
     */
    public function extractCMapStreamFilterLengthOwnerReview(string $pdfBytes): array
    {
        $objects = $this->pdfObjects($pdfBytes);
        $entries = [];
        foreach ($objects as $objectNumber => $body) {
            if (!str_contains($body, 'stream') || !$this->objectBodyLooksLikeCMap($body)) {
                continue;
            }

            $parts = $this->streamObjectParts($body);
            $dictionary = is_array($parts) ? $parts['dictionary'] : $body;
            $filters = $this->streamFilters($dictionary, $objects);
            $decodeParms = $this->streamDecodeParms($dictionary, $objects);
            $decoded = is_array($parts) ? $this->decodeStreamObject($body, $objects) : null;
            $isToUnicode = preg_match('/\/ToUnicode\b/', $this->resourceOwnersForObject($objectNumber, $objects)) === 1
                || preg_match('/\/Type\s*\/CMap\b|begincmap\b|beginbfchar\b|beginbfrange\b/s', $body) === 1;

            $entries[] = [
                'object' => $objectNumber,
                'type' => $isToUnicode ? 'to_unicode' : 'encoding',
                'filters' => $filters,
                'filter_operands' => array_map(
                    static fn (string $filter): array => ['filter' => $filter, 'supported' => in_array($filter, self::SUPPORTED_STREAM_FILTERS, true)],
                    $filters
                ),
                'decode_parms' => $decodeParms,
                'decoded' => $decoded !== null,
                'decoded_byte_length' => is_string($decoded) ? strlen($decoded) : 0,
            ];
        }

        $decodedCount = 0;
        $indirectFilterCount = 0;
        foreach ($entries as $entry) {
            $decodedCount += ($entry['decoded'] ?? false) === true ? 1 : 0;
            $indirectFilterCount += preg_match('/\/Filter\s+\d+\s+\d+\s+R\b/s', $objects[$entry['object']] ?? '') === 1 ? 1 : 0;
        }

        return [
            'source' => 'pdf_cmap_stream_filter_length_owner_review',
            'review_only' => true,
            'encrypted' => $this->isEncrypted($pdfBytes),
            'cmap_stream_count' => count($entries),
            'to_unicode_cmap_stream_count' => count(array_filter($entries, static fn (array $entry): bool => ($entry['type'] ?? '') === 'to_unicode')),
            'encoding_cmap_stream_count' => count(array_filter($entries, static fn (array $entry): bool => ($entry['type'] ?? '') === 'encoding')),
            'indirect_filter_count' => $indirectFilterCount,
            'xref_selected_operand_count' => $indirectFilterCount,
            'unresolved_operand_count' => 0,
            'decoded_cmap_count' => $decodedCount,
            'entries' => $entries,
        ];
    }

    /**
     * Native boundary for marker.pdf.extract_text::naive_get_text.
     *
     * Upstream asks pypdfium for bounded text per page and appends a newline
     * after each page. Here each extractable content stream is treated as the
     * supplied native page boundary used by the lightweight PDF fixtures.
     */
    public function naiveGetText(string $pdfBytes): string
    {
        if ($this->isEncrypted($pdfBytes) && $this->pdfPassword() === '') {
            return '';
        }

        $text = '';
        foreach ($this->extractPageTexts($pdfBytes) as $pageText) {
            $text .= $pageText . "\n";
        }

        return $text;
    }

    /**
     * Native boundary for marker.pdf.extract_text::get_length_of_text.
     */
    public function getLengthOfText(string $filepath): int
    {
        $bytes = @file_get_contents($filepath);
        if (!is_string($bytes)) {
            throw new \InvalidArgumentException('Unable to read PDF text-length source: ' . $filepath);
        }

        return $this->length(trim($this->naiveGetText($bytes)));
    }

    /**
     * @return array{
     *     encrypted: bool,
     *     encryptionDecrypted: bool,
     *     encryptionHandler: string|null,
     *     encryptionPasswordType: string|null,
     *     encryptionPermissions: array{raw: int, unsigned: int, print: bool, modify: bool, copy: bool, annotate: bool, fillForms: bool, extractAccessibility: bool, assemble: bool, printHighResolution: bool}|null,
     *     encryptionAllowsContentExtraction: bool|null,
     *     warnings: list<string>,
     *     unsupportedFilters: list<string>,
     *     failedStreams: int,
     *     malformedXrefOffsets: list<int>,
     *     malformedXrefStreams: int,
     *     malformedObjectStreams: int,
     *     missingUnicodeFonts: list<string>,
     *     missingUnicodeFontEncodings: array<string, string>,
     *     suppressedGlyphRuns: int,
     *     ignoredXObjectSubtypes: list<string>,
     *     ignoredXObjectCount: int,
     *     taggedRoleMap: array<string, string>,
     *     taggedStructureRoles: array<string, string>,
     *     taggedStructureLanguages: list<string>,
     *     taggedClassMap: array<string, list<array<string, mixed>>>,
     *     taggedStructureAttributes: list<array{role: string, resolvedRole: string, classes: list<string>, attributes: list<array<string, mixed>>}>,
     *     taggedStructureItems: list<array{objectNumber: int, role: string, resolvedRole: string, language: string|null, classes: list<string>, attributes: list<array<string, mixed>>, text: string}>,
     *     taggedStructureBlocks: list<array<string, mixed>>,
     *     taggedTables: list<array<string, mixed>>,
     *     taggedAttributeOwners: list<string>,
     *     taggedStructElementCount: int,
     *     linkAnnotations: list<array{page: int, pageObject: int, annotationObject: int|null, uri: string, text: string, rect: list<float>}>,
     *     textAnnotations: list<array<string, mixed>>,
     *     fileAttachmentAnnotations: list<array<string, mixed>>,
     *     popupAnnotations: list<array<string, mixed>>,
     *     appearanceAnnotations: list<array<string, mixed>>,
     *     pageExtractionIssues: list<array{page: int, pageObject: int, contentReference: int, contentObject: int|null, reason: string, filters: list<string>, xObjectName?: string, xObjectObject?: int|null, xObjectSubtype?: string}>,
     *     pagesWithExtractionIssues: int
     * }
     */
    public function diagnostics(string $pdfBytes): array
    {
        $objects = $this->pdfObjects($pdfBytes);
        $encryptionContext = $this->pdfEncryptionContextForBytes($pdfBytes, $this->pdfPassword());
        $encrypted = str_contains($pdfBytes, '/Encrypt');
        $encryptionDecrypted = $encrypted && $encryptionContext !== null;
        $encryptionPasswordType = is_string($encryptionContext['passwordType'] ?? null) ? $encryptionContext['passwordType'] : null;
        $encryptionPermissions = is_array($encryptionContext['permissionPolicy'] ?? null) ? $encryptionContext['permissionPolicy'] : null;
        $encryptionAllowsContentExtraction = $encryptionPermissions === null
            ? null
            : ($encryptionPasswordType === 'owner-password' || $encryptionPermissions['copy']);
        $diagnosticObjectBodies = $this->diagnosticPdfObjectBodies($pdfBytes, $encryptionContext);
        $unsupportedFilters = [];
        $failedStreams = 0;
        $malformedObjectStreams = 0;

        foreach ($diagnosticObjectBodies as $objectBody) {
            if ((str_contains($objectBody, '/Type /ObjStm') || str_contains($objectBody, '/Type/ObjStm'))
                && $this->objectsFromObjectStream($objectBody, $objects) === []
            ) {
                $malformedObjectStreams++;
            }
        }

        foreach ($diagnosticObjectBodies as $objectBody) {
            $stream = $this->streamObjectParts($objectBody);
            if ($stream === null) {
                continue;
            }

            $isXrefObject = preg_match('/\/Type\s*\/XRef\b|\/Type\/XRef\b/', $objectBody) === 1;
            $filters = $this->streamFilters($stream['dictionary'], $objects);
            foreach ($filters as $filter) {
                if (!$this->isSupportedStreamFilter($filter)) {
                    $unsupportedFilters[] = $filter;
                }
            }

            if (!$isXrefObject
                && $filters !== []
                && $this->allStreamFiltersSupported($filters)
                && $this->decodeStream($stream['dictionary'], $stream['stream'], $objects) === null
            ) {
                $failedStreams++;
            }
        }

        $malformedXrefOffsets = [];
        $malformedXrefStreams = 0;
        foreach ($this->startXrefOffsets($pdfBytes) as $offset) {
            if ($this->xrefSectionAtOffset($pdfBytes, $offset, $encryptionContext) === null) {
                $malformedXrefOffsets[] = $offset;
            }
        }
        if (preg_match_all('/\b(\d+)\s+(\d+)\s+obj\b(.*?)\bendobj/s', $pdfBytes, $xrefObjectMatches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($xrefObjectMatches as $xrefObjectMatch) {
                $objectNumber = (int) $xrefObjectMatch[1][0];
                $generation = (int) $xrefObjectMatch[2][0];
                $objectBody = $xrefObjectMatch[3][0];
                $objectOffset = $xrefObjectMatch[0][1];
                if (!str_contains($objectBody, '/Type /XRef') && !str_contains($objectBody, '/Type/XRef')) {
                    continue;
                }
                $stream = $this->streamObjectParts($objectBody);
                if ($stream === null) {
                    $malformedXrefStreams++;
                    continue;
                }
                $helperObjectMaxOffset = $this->xrefSectionBoundaryOffset($pdfBytes, $objectOffset, $objectOffset + strlen($xrefObjectMatch[0][0]));
                $decoded = $this->decodedXrefStream($stream['dictionary'], $stream['stream'], $objectNumber, $generation, $encryptionContext, $pdfBytes, $helperObjectMaxOffset);
                if ($decoded === null || $this->xrefStreamEntriesFromBody($stream['dictionary'], $decoded, $pdfBytes, $helperObjectMaxOffset) === []) {
                    $malformedXrefStreams++;
                }
            }
        }

        $textDiagnostics = $this->textSuppressionDiagnostics($pdfBytes);
        $xObjectDiagnostics = $this->ignoredXObjectDiagnostics($pdfBytes);
        $taggedSemantics = $this->taggedPdfSemantics($objects);
        $linkAnnotations = $this->linkAnnotations($objects);
        $textAnnotations = $this->textAnnotations($objects);
        $fileAttachmentAnnotations = $this->fileAttachmentAnnotations($objects);
        $popupAnnotations = $this->popupAnnotations($objects);
        $appearanceAnnotations = $this->appearanceAnnotations($objects);
        $pageExtractionIssues = $this->pageExtractionIssues($objects);
        $pagesWithExtractionIssues = [];
        foreach ($pageExtractionIssues as $issue) {
            $pagesWithExtractionIssues[$issue['page']] = true;
        }
        $unsupportedFilters = array_values(array_unique($unsupportedFilters));
        $warnings = [];
        if ($encrypted && !$encryptionDecrypted) {
            $warnings[] = 'PDF is encrypted; native text extraction does not decrypt encrypted streams.';
        }
        if ($encryptionAllowsContentExtraction === false) {
            $warnings[] = 'PDF permissions disallow content copying or extraction.';
        }
        if ($unsupportedFilters !== []) {
            $warnings[] = 'Unsupported PDF stream filters: ' . implode(', ', $unsupportedFilters) . '.';
        }
        if ($failedStreams > 0) {
            $warnings[] = $failedStreams . ' PDF stream(s) could not be decoded.';
        }
        if ($malformedXrefOffsets !== [] || $malformedXrefStreams > 0) {
            $warnings[] = 'Malformed PDF xref data was detected.';
        }
        if ($malformedObjectStreams > 0) {
            $warnings[] = $malformedObjectStreams . ' PDF object stream(s) could not be unpacked.';
        }
        if ($textDiagnostics['suppressedGlyphRuns'] > 0) {
            $warnings[] = $textDiagnostics['suppressedGlyphRuns'] . ' PDF text run(s) were suppressed because their font lacks a Unicode map.';
        }
        if ($xObjectDiagnostics['ignoredXObjectCount'] > 0) {
            $warnings[] = 'Ignored ' . $xObjectDiagnostics['ignoredXObjectCount'] . ' non-text PDF XObject(s): ' . implode(', ', $xObjectDiagnostics['ignoredXObjectSubtypes']) . '.';
        }
        if ($pageExtractionIssues !== []) {
            $warnings[] = 'PDF page-level extraction issues: ' . count($pagesWithExtractionIssues) . ' page(s) have unreadable or unresolved content streams.';
        }

        return [
            'encrypted' => $encrypted,
            'encryptionDecrypted' => $encryptionDecrypted,
            'encryptionHandler' => $encryptionContext['handler'] ?? null,
            'encryptionPasswordType' => $encryptionPasswordType,
            'encryptionPermissions' => $encryptionPermissions,
            'encryptionAllowsContentExtraction' => $encryptionAllowsContentExtraction,
            'warnings' => $warnings,
            'unsupportedFilters' => $unsupportedFilters,
            'failedStreams' => $failedStreams,
            'malformedXrefOffsets' => array_values(array_unique($malformedXrefOffsets)),
            'malformedXrefStreams' => $malformedXrefStreams,
            'malformedObjectStreams' => $malformedObjectStreams,
            'missingUnicodeFonts' => $textDiagnostics['missingUnicodeFonts'],
            'missingUnicodeFontEncodings' => $textDiagnostics['missingUnicodeFontEncodings'],
            'suppressedGlyphRuns' => $textDiagnostics['suppressedGlyphRuns'],
            'ignoredXObjectSubtypes' => $xObjectDiagnostics['ignoredXObjectSubtypes'],
            'ignoredXObjectCount' => $xObjectDiagnostics['ignoredXObjectCount'],
            'taggedRoleMap' => $taggedSemantics['roleMap'],
            'taggedStructureRoles' => $taggedSemantics['structureRoles'],
            'taggedStructureLanguages' => $taggedSemantics['languages'],
            'taggedClassMap' => $taggedSemantics['classMap'],
            'taggedStructureAttributes' => $taggedSemantics['structureAttributes'],
            'taggedStructureItems' => $taggedSemantics['structureItems'],
            'taggedStructureBlocks' => $taggedSemantics['structureBlocks'],
            'taggedTables' => $taggedSemantics['tables'],
            'taggedAttributeOwners' => $taggedSemantics['attributeOwners'],
            'taggedStructElementCount' => $taggedSemantics['structElementCount'],
            'linkAnnotations' => $linkAnnotations,
            'textAnnotations' => $textAnnotations,
            'fileAttachmentAnnotations' => $fileAttachmentAnnotations,
            'popupAnnotations' => $popupAnnotations,
            'appearanceAnnotations' => $appearanceAnnotations,
            'pageExtractionIssues' => $pageExtractionIssues,
            'pagesWithExtractionIssues' => count($pagesWithExtractionIssues),
        ];
    }

    /**
     * @return array{missingUnicodeFonts: list<string>, missingUnicodeFontEncodings: array<string, string>, suppressedGlyphRuns: int}
     */
    private function textSuppressionDiagnostics(string $pdfBytes): array
    {
        $missingUnicodeFonts = [];
        $missingUnicodeFontEncodings = [];
        $suppressedGlyphRuns = 0;

        foreach ($this->streamContexts($pdfBytes) as $context) {
            $diagnostics = $this->suppressedGlyphDiagnosticsFromContentStream(
                $context['stream'],
                $context['fontToUnicodeMaps'],
                $context['fontEncodings'],
                $context['propertyActualTexts'],
                $context['mcidActualTexts'],
                $context['propertyMcids']
            );
            $suppressedGlyphRuns += $diagnostics['suppressedGlyphRuns'];
            foreach ($diagnostics['missingUnicodeFonts'] as $fontName) {
                $missingUnicodeFonts[] = $fontName;
            }
            foreach ($diagnostics['missingUnicodeFontEncodings'] as $fontName => $encoding) {
                $missingUnicodeFontEncodings[$fontName] = $encoding;
            }
        }

        $missingUnicodeFonts = array_values(array_unique($missingUnicodeFonts));
        sort($missingUnicodeFonts);
        ksort($missingUnicodeFontEncodings);

        return [
            'missingUnicodeFonts' => $missingUnicodeFonts,
            'missingUnicodeFontEncodings' => $missingUnicodeFontEncodings,
            'suppressedGlyphRuns' => $suppressedGlyphRuns,
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return list<array<string, mixed>>
     */
    private function linkAnnotations(array $objects): array
    {
        $runsByPageObject = $this->positionedTextRunsByPageObject($objects);
        $pageNumberByObject = $this->pageNumberByObjectNumber($objects);
        $annotations = [];
        $pageNumber = 0;

        foreach ($this->limitedPageObjectNumbers($objects) as $pageObjectNumber) {
            $pageNumber++;
            $pageObjectBody = $objects[$pageObjectNumber] ?? null;
            if (!is_string($pageObjectBody) || !$this->isPageObjectBody($pageObjectBody)) {
                continue;
            }

            $pageRuns = $runsByPageObject[$pageObjectNumber] ?? [];
            foreach ($this->annotationDictionariesForPage($pageObjectBody, $objects) as $annotation) {
                $parsed = $this->linkAnnotationFromDictionary($annotation['dictionary'], $objects, $pageNumberByObject);
                if ($parsed === null) {
                    continue;
                }

                $text = $this->textForAnnotationRegions($pageRuns, $parsed['quadRects'] ?? [$parsed['rect']]);
                if ($text === '') {
                    continue;
                }

                $annotations[] = [
                    'page' => $pageNumber,
                    'pageObject' => $pageObjectNumber,
                    'annotationObject' => $annotation['objectNumber'],
                    'kind' => $parsed['kind'],
                    'uri' => $parsed['uri'],
                    'text' => $text,
                    'rect' => $parsed['rect'],
                ] + array_diff_key($parsed, array_flip(['kind', 'uri', 'rect']));
            }
        }

        return $annotations;
    }

    /**
     * @param array<int, string> $objects
     * @return list<array<string, mixed>>
     */
    private function textAnnotations(array $objects): array
    {
        $annotations = [];
        $pageNumber = 0;

        foreach ($this->limitedPageObjectNumbers($objects) as $pageObjectNumber) {
            $pageNumber++;
            $pageObjectBody = $objects[$pageObjectNumber] ?? null;
            if (!is_string($pageObjectBody) || !$this->isPageObjectBody($pageObjectBody)) {
                continue;
            }

            foreach ($this->annotationDictionariesForPage($pageObjectBody, $objects) as $annotation) {
                $parsed = $this->textAnnotationFromDictionary($annotation['dictionary'], $objects);
                if ($parsed === null) {
                    continue;
                }

                $annotations[] = [
                    'page' => $pageNumber,
                    'pageObject' => $pageObjectNumber,
                    'annotationObject' => $annotation['objectNumber'],
                ] + $parsed;
            }
        }

        return $annotations;
    }

    /**
     * @param array<int, string> $objects
     * @return list<array<string, mixed>>
     */
    private function fileAttachmentAnnotations(array $objects): array
    {
        $annotations = [];
        $pageNumber = 0;

        foreach ($this->limitedPageObjectNumbers($objects) as $pageObjectNumber) {
            $pageNumber++;
            $pageObjectBody = $objects[$pageObjectNumber] ?? null;
            if (!is_string($pageObjectBody) || !$this->isPageObjectBody($pageObjectBody)) {
                continue;
            }

            foreach ($this->annotationDictionariesForPage($pageObjectBody, $objects) as $annotation) {
                $parsed = $this->fileAttachmentAnnotationFromDictionary($annotation['dictionary'], $objects);
                if ($parsed === null) {
                    continue;
                }

                $annotations[] = [
                    'page' => $pageNumber,
                    'pageObject' => $pageObjectNumber,
                    'annotationObject' => $annotation['objectNumber'],
                ] + $parsed;
            }
        }

        return $annotations;
    }

    /**
     * @param array<int, string> $objects
     * @return list<array<string, mixed>>
     */
    private function popupAnnotations(array $objects): array
    {
        $annotations = [];
        $pageNumber = 0;

        foreach ($this->limitedPageObjectNumbers($objects) as $pageObjectNumber) {
            $pageNumber++;
            $pageObjectBody = $objects[$pageObjectNumber] ?? null;
            if (!is_string($pageObjectBody) || !$this->isPageObjectBody($pageObjectBody)) {
                continue;
            }

            foreach ($this->annotationDictionariesForPage($pageObjectBody, $objects) as $annotation) {
                $parsed = $this->popupAnnotationFromDictionary($annotation['dictionary'], $objects);
                if ($parsed === null) {
                    continue;
                }

                $annotations[] = [
                    'page' => $pageNumber,
                    'pageObject' => $pageObjectNumber,
                    'annotationObject' => $annotation['objectNumber'],
                ] + $parsed;
            }
        }

        return $annotations;
    }

    /**
     * @param array<int, string> $objects
     * @return list<array<string, mixed>>
     */
    private function appearanceAnnotations(array $objects): array
    {
        $annotations = [];
        $pageNumber = 0;

        foreach ($this->limitedPageObjectNumbers($objects) as $pageObjectNumber) {
            $pageNumber++;
            $pageObjectBody = $objects[$pageObjectNumber] ?? null;
            if (!is_string($pageObjectBody) || !$this->isPageObjectBody($pageObjectBody)) {
                continue;
            }

            foreach ($this->annotationDictionariesForPage($pageObjectBody, $objects) as $annotation) {
                $parsed = $this->appearanceAnnotationFromDictionary($annotation['dictionary'], $objects);
                if ($parsed === null) {
                    continue;
                }

                $annotations[] = [
                    'page' => $pageNumber,
                    'pageObject' => $pageObjectNumber,
                    'annotationObject' => $annotation['objectNumber'],
                ] + $parsed;
            }
        }

        return $annotations;
    }

    /**
     * @param array<int, string> $objects
     * @return array<int, int>
     */
    private function pageNumberByObjectNumber(array $objects): array
    {
        $pageNumberByObject = [];
        $pageNumber = 0;
        foreach ($this->pageObjectNumbers($objects) as $pageObjectNumber) {
            $pageNumber++;
            $pageNumberByObject[$pageObjectNumber] = $pageNumber;
        }

        return $pageNumberByObject;
    }

    /**
     * @param array<int, string> $objects
     * @return array<int, list<array{text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}>>
     */
    private function positionedTextRunsByPageObject(array $objects): array
    {
        $runsByPageObject = [];
        $totalRuns = 0;
        $maxRuns = $this->maxPositionedTextRuns();
        foreach ($this->pageContentStreamContexts($objects) as $context) {
            $pageObject = (int) ($context['pageObject'] ?? 0);
            if ($pageObject <= 0) {
                continue;
            }

            foreach ($this->positionedTextRunsFromContentStream(
                $context['stream'],
                $context['fontToUnicodeMaps'],
                $context['fontEncodings'],
                $context['propertyActualTexts'],
                $context['mcidActualTexts'],
                $context['propertyMcids']
            ) as $run) {
                $runsByPageObject[$pageObject][] = $run;
                $totalRuns++;
                if ($totalRuns >= $maxRuns) {
                    return $runsByPageObject;
                }
            }
        }

        return $runsByPageObject;
    }

    /**
     * @param array<int, string> $objects
     * @return list<array{objectNumber: int|null, dictionary: string}>
     */
    private function annotationDictionariesForPage(string $pageObjectBody, array $objects): array
    {
        $annots = $this->dictionaryArrayTokenFromTokens($this->dictionaryTokens($pageObjectBody), 'Annots', $objects);
        if ($annots === null) {
            return [];
        }

        $annotations = [];
        $tokens = $this->arrayTokens($annots);
        for ($index = 0, $count = count($tokens); $index < $count;) {
            $objectNumber = $this->indirectObjectOperand($tokens, $index);
            if ($objectNumber !== null) {
                $index += 3;
                $dictionary = isset($objects[$objectNumber]) ? $this->dictionaryFromValue($objects[$objectNumber]) : null;
                if ($dictionary !== null) {
                    $annotations[] = [
                        'objectNumber' => $objectNumber,
                        'dictionary' => $dictionary,
                    ];
                }
                continue;
            }

            $token = $tokens[$index] ?? null;
            $index++;
            if (is_string($token) && str_starts_with(trim($token), '<<')) {
                $dictionary = $this->dictionaryFromValue($token);
                if ($dictionary !== null) {
                    $annotations[] = [
                        'objectNumber' => null,
                        'dictionary' => $dictionary,
                    ];
                }
            }
        }

        return $annotations;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function textAnnotationFromDictionary(string $dictionary, array $objects): ?array
    {
        $subtype = $this->nameDictionaryValue($dictionary, 'Subtype');
        if (!is_string($subtype) || $subtype === '' || in_array(strtoupper($subtype), ['LINK', 'FILEATTACHMENT', 'POPUP', 'WIDGET'], true)) {
            return null;
        }

        $contents = $this->normalizedAnnotationText($this->textStringFromDictionaryKey($dictionary, 'Contents', $objects));
        if ($contents === null) {
            return null;
        }

        $result = [
            'subtype' => $subtype,
            'contents' => $contents,
        ];

        foreach ([
            'T' => 'title',
            'NM' => 'name',
            'M' => 'modified',
            'CreationDate' => 'created',
            'Subj' => 'subject',
            'State' => 'state',
            'StateModel' => 'stateModel',
            'OverlayText' => 'overlayText',
            'DA' => 'defaultAppearance',
            'DS' => 'defaultStyle',
            'RC' => 'richContents',
        ] as $pdfKey => $resultKey) {
            $value = $this->normalizedAnnotationText($this->textStringFromDictionaryKey($dictionary, $pdfKey, $objects));
            if ($value !== null) {
                $result[$resultKey] = $value;
            }
        }

        foreach ([
            'Name' => 'iconName',
            'IT' => 'intent',
            'RT' => 'replyType',
            'Sy' => 'symbol',
        ] as $pdfKey => $resultKey) {
            $value = $this->actionNameValue($dictionary, $pdfKey, $objects);
            if ($value !== null) {
                $result[$resultKey] = $value;
            }
        }

        $flags = $this->integerDictionaryValueFromObjects($dictionary, 'F', $objects);
        if ($flags !== null) {
            $result['flags'] = $flags;
            $result['flagNames'] = $this->annotationFlagNames($flags);
        }

        $inReplyTo = $this->indirectObjectDictionaryValue($dictionary, 'IRT');
        if ($inReplyTo !== null) {
            $result['inReplyToAnnotationObject'] = $inReplyTo;
        }

        $popup = $this->indirectObjectDictionaryValue($dictionary, 'Popup');
        if ($popup !== null) {
            $result['popupAnnotationObject'] = $popup;
        }

        foreach ([
            'C' => 'color',
            'IC' => 'interiorColor',
            'Border' => 'border',
            'L' => 'line',
            'Vertices' => 'vertices',
            'CL' => 'calloutLine',
            'RD' => 'rectDifferences',
        ] as $pdfKey => $resultKey) {
            $values = $this->annotationNumericArray($dictionary, $objects, $pdfKey);
            if ($values !== []) {
                $result[$resultKey] = $values;
            }
        }

        $lineEndingStyles = $this->annotationNameArray($dictionary, $objects, 'LE');
        if ($lineEndingStyles !== []) {
            $result['lineEndingStyles'] = $lineEndingStyles;
        }

        $inkList = $this->annotationNestedNumericArrays($dictionary, $objects, 'InkList');
        if ($inkList !== []) {
            $result['inkList'] = $inkList;
        }

        $borderStyle = $this->annotationBorderStyle($dictionary, $objects);
        if ($borderStyle !== null) {
            $result['borderStyle'] = $borderStyle;
        }

        $borderEffect = $this->annotationBorderEffect($dictionary, $objects);
        if ($borderEffect !== null) {
            $result['borderEffect'] = $borderEffect;
        }

        foreach ([
            'CA' => 'opacity',
            'LL' => 'leaderLineLength',
            'LLE' => 'leaderLineExtension',
            'LLO' => 'leaderLineOffset',
        ] as $pdfKey => $resultKey) {
            $value = $this->numericDictionaryValue($dictionary, $pdfKey);
            if ($value !== null) {
                $result[$resultKey] = $value;
            }
        }

        foreach ([
            'Open' => 'open',
            'Cap' => 'caption',
            'Repeat' => 'repeat',
        ] as $pdfKey => $resultKey) {
            $value = $this->booleanDictionaryValue($dictionary, $pdfKey);
            if ($value !== null) {
                $result[$resultKey] = $value;
            }
        }

        $quadding = $this->integerDictionaryValueFromObjects($dictionary, 'Q', $objects);
        if ($quadding !== null) {
            $result['quadding'] = $quadding;
        }

        foreach ([
            'Rotate' => 'rotation',
            'StructParent' => 'structParent',
            'StructParents' => 'structParents',
        ] as $pdfKey => $resultKey) {
            $value = $this->integerDictionaryValueFromObjects($dictionary, $pdfKey, $objects);
            if ($value !== null) {
                $result[$resultKey] = $value;
            }
        }

        $rect = $this->annotationRect($dictionary, $objects);
        if ($rect !== null) {
            $result['rect'] = $rect;
        }

        $quadPoints = $this->annotationQuadPoints($dictionary, $objects);
        if ($quadPoints !== []) {
            $result['quadPoints'] = $quadPoints;
            $result['quadRects'] = $this->quadPointRectangles($quadPoints);
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    private function annotationFlagNames(int $flags): array
    {
        $names = [];
        foreach ([
            1 => 'invisible',
            2 => 'hidden',
            4 => 'print',
            8 => 'noZoom',
            16 => 'noRotate',
            32 => 'noView',
            64 => 'readOnly',
            128 => 'locked',
            256 => 'toggleNoView',
            512 => 'lockedContents',
        ] as $bit => $name) {
            if (($flags & $bit) !== 0) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * @param array<int, string> $objects
     * @return list<float>
     */
    private function annotationNumericArray(string $dictionary, array $objects, string $key): array
    {
        $array = $this->dictionaryArrayTokenFromTokens($this->dictionaryTokens($dictionary), $key, $objects);
        return $array === null ? [] : array_map(static fn (float|int $value): float => (float) $value, $this->numericArrayValues($array));
    }

    /**
     * @param array<int, string> $objects
     * @return list<string>
     */
    private function annotationNameArray(string $dictionary, array $objects, string $key): array
    {
        $array = $this->dictionaryArrayTokenFromTokens($this->dictionaryTokens($dictionary), $key, $objects);
        if ($array === null) {
            return [];
        }

        $names = [];
        foreach ($this->arrayTokens($array) as $token) {
            if (str_starts_with($token, '/')) {
                $names[] = $this->decodePdfName(substr($token, 1));
                continue;
            }

            $text = $this->textStringFromToken($token);
            if ($text !== null && trim($text) !== '') {
                $names[] = trim($text);
            }
        }

        return $names;
    }

    /**
     * @param array<int, string> $objects
     * @return list<list<float>>
     */
    private function annotationNestedNumericArrays(string $dictionary, array $objects, string $key): array
    {
        $array = $this->dictionaryArrayTokenFromTokens($this->dictionaryTokens($dictionary), $key, $objects);
        if ($array === null) {
            return [];
        }

        $groups = [];
        foreach ($this->arrayTokens($array) as $token) {
            if (!str_starts_with(trim($token), '[')) {
                continue;
            }
            $values = array_map(static fn (float|int $value): float => (float) $value, $this->numericArrayValues($token));
            if ($values !== []) {
                $groups[] = $values;
            }
        }

        return $groups;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function annotationBorderStyle(string $dictionary, array $objects): ?array
    {
        $borderStyle = $this->dictionaryDictionaryTokenFromTokens($this->dictionaryTokens($dictionary), 'BS', $objects);
        if ($borderStyle === null) {
            return null;
        }

        $summary = [];
        $width = $this->numericDictionaryValue($borderStyle, 'W');
        if ($width !== null) {
            $summary['width'] = $width;
        }
        $style = $this->actionNameValue($borderStyle, 'S', $objects);
        if ($style !== null) {
            $summary['style'] = $style;
        }
        $dashPattern = $this->annotationNumericArray($borderStyle, $objects, 'D');
        if ($dashPattern !== []) {
            $summary['dashPattern'] = $dashPattern;
        }

        return $summary === [] ? null : $summary;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function annotationBorderEffect(string $dictionary, array $objects): ?array
    {
        $borderEffect = $this->dictionaryDictionaryTokenFromTokens($this->dictionaryTokens($dictionary), 'BE', $objects);
        if ($borderEffect === null) {
            return null;
        }

        $summary = [];
        $style = $this->actionNameValue($borderEffect, 'S', $objects);
        if ($style !== null) {
            $summary['style'] = $style;
        }
        $intensity = $this->numericDictionaryValue($borderEffect, 'I');
        if ($intensity !== null) {
            $summary['intensity'] = $intensity;
        }

        return $summary === [] ? null : $summary;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function fileAttachmentAnnotationFromDictionary(string $dictionary, array $objects): ?array
    {
        $subtype = $this->nameDictionaryValue($dictionary, 'Subtype');
        if (!is_string($subtype) || strtoupper($subtype) !== 'FILEATTACHMENT') {
            return null;
        }

        $result = ['subtype' => $subtype];

        foreach ([
            'Contents' => 'contents',
            'T' => 'title',
            'NM' => 'name',
            'M' => 'modified',
            'Subj' => 'subject',
        ] as $pdfKey => $resultKey) {
            $value = $this->normalizedAnnotationText($this->textStringFromDictionaryKey($dictionary, $pdfKey, $objects));
            if ($value !== null) {
                $result[$resultKey] = $value;
            }
        }

        $iconName = $this->nameDictionaryValue($dictionary, 'Name');
        if (is_string($iconName) && $iconName !== '') {
            $result['iconName'] = $iconName;
        }

        $fileSpecification = $this->fileSpecificationFromDictionaryKey($dictionary, 'FS', $objects);
        if ($fileSpecification !== null) {
            $result['fileSpecification'] = $fileSpecification;
            $result['file'] = $fileSpecification['file'];
        }

        $rect = $this->annotationRect($dictionary, $objects);
        if ($rect !== null) {
            $result['rect'] = $rect;
        }

        return $result;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function popupAnnotationFromDictionary(string $dictionary, array $objects): ?array
    {
        $subtype = $this->nameDictionaryValue($dictionary, 'Subtype');
        if (!is_string($subtype) || strtoupper($subtype) !== 'POPUP') {
            return null;
        }

        $result = ['subtype' => $subtype];

        foreach ([
            'Contents' => 'contents',
            'T' => 'title',
            'NM' => 'name',
            'M' => 'modified',
            'Subj' => 'subject',
        ] as $pdfKey => $resultKey) {
            $value = $this->normalizedAnnotationText($this->textStringFromDictionaryKey($dictionary, $pdfKey, $objects));
            if ($value !== null) {
                $result[$resultKey] = $value;
            }
        }

        $rect = $this->annotationRect($dictionary, $objects);
        if ($rect !== null) {
            $result['rect'] = $rect;
        }

        $open = $this->booleanDictionaryValue($dictionary, 'Open');
        if ($open !== null) {
            $result['open'] = $open;
        }

        $parentObject = $this->indirectObjectDictionaryValue($dictionary, 'Parent');
        if ($parentObject !== null) {
            $result['parentAnnotationObject'] = $parentObject;
        }

        $parentDictionary = $this->dictionaryDictionaryTokenFromTokens($this->dictionaryTokens($dictionary), 'Parent', $objects);
        if ($parentDictionary !== null) {
            $parentSubtype = $this->nameDictionaryValue($parentDictionary, 'Subtype');
            if (is_string($parentSubtype) && $parentSubtype !== '') {
                $result['parentSubtype'] = $parentSubtype;
            }

            foreach ([
                'Contents' => 'parentContents',
                'T' => 'parentTitle',
                'NM' => 'parentName',
                'M' => 'parentModified',
                'Subj' => 'parentSubject',
            ] as $pdfKey => $resultKey) {
                $value = $this->normalizedAnnotationText($this->textStringFromDictionaryKey($parentDictionary, $pdfKey, $objects));
                if ($value !== null) {
                    $result[$resultKey] = $value;
                }
            }
        }

        return $result;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function appearanceAnnotationFromDictionary(string $dictionary, array $objects): ?array
    {
        $appearanceDictionary = $this->dictionaryDictionaryTokenFromTokens($this->dictionaryTokens($dictionary), 'AP', $objects);
        if ($appearanceDictionary === null) {
            return null;
        }

        $appearanceStreams = $this->appearanceStreamsFromDictionary($appearanceDictionary, $objects);
        if ($appearanceStreams === []) {
            return null;
        }

        $subtype = $this->nameDictionaryValue($dictionary, 'Subtype');
        $result = [
            'subtype' => is_string($subtype) && $subtype !== '' ? $subtype : 'unknown',
            'appearanceStreams' => $appearanceStreams,
        ];

        $appearanceState = $this->nameDictionaryValue($dictionary, 'AS');
        if (is_string($appearanceState) && $appearanceState !== '') {
            $result['appearanceState'] = $appearanceState;
        }

        foreach ([
            'Contents' => 'contents',
            'T' => 'title',
            'NM' => 'name',
            'M' => 'modified',
            'Subj' => 'subject',
        ] as $pdfKey => $resultKey) {
            $value = $this->normalizedAnnotationText($this->textStringFromDictionaryKey($dictionary, $pdfKey, $objects));
            if ($value !== null) {
                $result[$resultKey] = $value;
            }
        }

        $rect = $this->annotationRect($dictionary, $objects);
        if ($rect !== null) {
            $result['rect'] = $rect;
        }

        return $result;
    }

    /**
     * @param array<int, string> $objects
     * @return list<array<string, mixed>>
     */
    private function appearanceStreamsFromDictionary(string $appearanceDictionary, array $objects): array
    {
        $streams = [];
        foreach ([
            'N' => 'normal',
            'R' => 'rollover',
            'D' => 'down',
        ] as $pdfKey => $appearanceType) {
            $value = $this->dictionaryEntryValue($appearanceDictionary, $pdfKey, $objects);
            if ($value === null) {
                continue;
            }

            foreach ($this->appearanceStreamsFromValue($value['token'], $objects, $pdfKey, $appearanceType, $value['objectNumber'] ?? null) as $stream) {
                $streams[] = $stream;
            }
        }

        return $streams;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     * @return list<array<string, mixed>>
     */
    private function appearanceStreamsFromValue(string $value, array $objects, string $appearanceKey, string $appearanceType, ?int $objectNumber = null, ?string $state = null, array $seen = []): array
    {
        if ($objectNumber !== null) {
            if (isset($seen[$objectNumber])) {
                return [];
            }
            $seen[$objectNumber] = true;
        }

        $value = trim($value);
        if ($this->streamObjectParts($value) !== null) {
            $summary = $this->appearanceStreamSummary($value, $objects, $appearanceKey, $appearanceType, $objectNumber, $state);
            return $summary === null ? [] : [$summary];
        }

        $dictionary = $this->dictionaryFromValue($value);
        if ($dictionary === null) {
            return [];
        }

        $streams = [];
        $tokens = $this->dictionaryTokens($dictionary);
        for ($index = 0, $count = count($tokens); $index < $count;) {
            $stateToken = $tokens[$index] ?? null;
            if (!is_string($stateToken) || !str_starts_with($stateToken, '/')) {
                $index++;
                continue;
            }

            $stateName = $this->decodePdfName(substr($stateToken, 1));
            $referencedObject = $this->indirectObjectOperand($tokens, $index + 1);
            if ($referencedObject !== null) {
                if (isset($objects[$referencedObject])) {
                    foreach ($this->appearanceStreamsFromValue($objects[$referencedObject], $objects, $appearanceKey, $appearanceType, $referencedObject, $stateName, $seen) as $stream) {
                        $streams[] = $stream;
                    }
                }
                $index += 4;
                continue;
            }

            $token = $tokens[$index + 1] ?? null;
            if (is_string($token)) {
                foreach ($this->appearanceStreamsFromValue($token, $objects, $appearanceKey, $appearanceType, null, $stateName, $seen) as $stream) {
                    $streams[] = $stream;
                }
            }
            $index += 2;
        }

        return $streams;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function appearanceStreamSummary(string $objectBody, array $objects, string $appearanceKey, string $appearanceType, ?int $objectNumber, ?string $state): ?array
    {
        $stream = $this->streamObjectParts($objectBody);
        if ($stream === null) {
            return null;
        }

        $dictionary = $stream['dictionary'];
        $tokens = $this->dictionaryTokens($dictionary);
        $filters = $this->streamFilters($dictionary, $objects);
        $decoded = $this->decodeStream($dictionary, $stream['stream'], $objects);
        $summary = [
            'appearanceKey' => $appearanceKey,
            'appearanceType' => $appearanceType,
            'decoded' => $decoded !== null,
            'filters' => $filters,
        ];

        if ($objectNumber !== null) {
            $summary['objectNumber'] = $objectNumber;
        }
        if ($state !== null) {
            $summary['state'] = $state;
        }

        foreach ([
            'Type' => 'type',
            'Subtype' => 'subtype',
        ] as $pdfKey => $resultKey) {
            $value = $this->nameDictionaryValue($dictionary, $pdfKey);
            if (is_string($value) && $value !== '') {
                $summary[$resultKey] = $value;
            }
        }

        foreach ([
            'BBox' => 'bbox',
            'Matrix' => 'matrix',
        ] as $pdfKey => $resultKey) {
            $arrayToken = $this->dictionaryArrayTokenFromTokens($tokens, $pdfKey, $objects);
            if ($arrayToken !== null) {
                $values = $this->numericArrayValues($arrayToken);
                if ($values !== []) {
                    $summary[$resultKey] = array_map(static fn (float|int $value): float => (float) $value, $values);
                }
            }
        }

        $declaredLength = $this->integerDictionaryValueFromObjects($dictionary, 'Length', $objects);
        if ($declaredLength !== null) {
            $summary['declaredLength'] = $declaredLength;
        }

        if ($decoded !== null) {
            $summary['decodedBytes'] = strlen($decoded);
            $textLines = $this->appearanceTextLines($objectBody, $decoded, $objects);
            if ($textLines !== []) {
                $summary['textLines'] = $textLines;
                $summary['text'] = implode("\n", $textLines);
            }
        }

        return $summary;
    }

    /**
     * @param array<int, string> $objects
     * @return list<string>
     */
    private function appearanceTextLines(string $objectBody, string $decoded, array $objects): array
    {
        $resourceContext = $this->resourceContextForBody($objectBody, $objects);
        $fontToUnicodeMaps = $this->fontToUnicodeMapsForResourceContext($resourceContext, $objects, false);
        $fontEncodings = $this->fontEncodingsForResourceContext($resourceContext, $objects, false);
        $propertyActualTexts = $this->propertyActualTextsFromContext($resourceContext, $objects);
        $propertyMcids = $this->propertyMcidsFromContext($resourceContext, $objects);
        $mcidActualTexts = $this->mcidActualTextsForPage($objectBody, $objects);
        $xObjects = $this->xObjectResourceObjectNumbers($resourceContext, $objects);
        $expanded = $this->expandContentStreamWithFormXObjects(
            $decoded,
            $objects,
            $xObjects,
            $fontToUnicodeMaps,
            $fontEncodings,
            $propertyActualTexts,
            $mcidActualTexts,
            $propertyMcids
        );

        $lines = [];
        foreach ($this->textLinesFromContentStream($expanded, $fontToUnicodeMaps, $fontEncodings, $propertyActualTexts, $mcidActualTexts, $propertyMcids) as $line) {
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    private function normalizedAnnotationText(?string $text): ?string
    {
        if (!is_string($text)) {
            return null;
        }

        $text = trim(str_replace(["\r\n", "\r"], "\n", $text));
        return $text === '' ? null : $text;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, int> $pageNumberByObject
     * @return array<string, mixed>|null
     */
    private function linkAnnotationFromDictionary(string $dictionary, array $objects, array $pageNumberByObject): ?array
    {
        if (strtoupper((string) $this->nameDictionaryValue($dictionary, 'Subtype')) !== 'LINK') {
            return null;
        }

        $rect = $this->annotationRect($dictionary, $objects);
        $target = $this->linkTargetFromAnnotation($dictionary, $objects, $pageNumberByObject);
        if ($rect === null || $target === null) {
            return null;
        }

        $quadPoints = $this->annotationQuadPoints($dictionary, $objects);
        $quadRects = $quadPoints === [] ? [] : $this->quadPointRectangles($quadPoints);

        return $target + [
            'rect' => $rect,
        ] + ($quadPoints === [] ? [] : [
            'quadPoints' => $quadPoints,
            'quadRects' => $quadRects,
        ]);
    }

    /**
     * @param array<int, string> $objects
     * @return list<float>|null
     */
    private function annotationRect(string $dictionary, array $objects): ?array
    {
        $rectToken = $this->dictionaryArrayTokenFromTokens($this->dictionaryTokens($dictionary), 'Rect', $objects);
        if ($rectToken === null) {
            return null;
        }

        $values = $this->numericArrayValues($rectToken);
        if (count($values) < 4) {
            return null;
        }

        $x1 = (float) $values[0];
        $y1 = (float) $values[1];
        $x2 = (float) $values[2];
        $y2 = (float) $values[3];

        return [
            min($x1, $x2),
            min($y1, $y2),
            max($x1, $x2),
            max($y1, $y2),
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return list<float>
     */
    private function annotationQuadPoints(string $dictionary, array $objects): array
    {
        $quadPointsToken = $this->dictionaryArrayTokenFromTokens($this->dictionaryTokens($dictionary), 'QuadPoints', $objects);
        if ($quadPointsToken === null) {
            return [];
        }

        $values = $this->numericArrayValues($quadPointsToken);
        if (count($values) < 8) {
            return [];
        }

        $usableCount = count($values) - (count($values) % 8);
        return array_map(static fn (float|int $value): float => (float) $value, array_slice($values, 0, $usableCount));
    }

    /**
     * @param list<float> $quadPoints
     * @return list<list<float>>
     */
    private function quadPointRectangles(array $quadPoints): array
    {
        $rectangles = [];
        for ($offset = 0, $count = count($quadPoints); $offset + 7 < $count; $offset += 8) {
            $xs = [
                (float) $quadPoints[$offset],
                (float) $quadPoints[$offset + 2],
                (float) $quadPoints[$offset + 4],
                (float) $quadPoints[$offset + 6],
            ];
            $ys = [
                (float) $quadPoints[$offset + 1],
                (float) $quadPoints[$offset + 3],
                (float) $quadPoints[$offset + 5],
                (float) $quadPoints[$offset + 7],
            ];

            $rectangles[] = [
                min($xs),
                min($ys),
                max($xs),
                max($ys),
            ];
        }

        return $rectangles;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, int> $pageNumberByObject
     * @return array<string, mixed>|null
     */
    private function linkTargetFromAnnotation(string $dictionary, array $objects, array $pageNumberByObject): ?array
    {
        $tokens = $this->dictionaryTokens($dictionary);
        $action = $this->dictionaryDictionaryTokenFromTokens($tokens, 'A', $objects);
        $additionalActions = $this->annotationAdditionalActions($dictionary, $objects);
        $namedDestinations = $this->namedDestinationMap($objects);
        if ($action !== null && strtoupper((string) $this->nameDictionaryValue($action, 'S')) === 'URI') {
            $uri = $this->textStringFromDictionaryKey($action, 'URI', $objects);
            if (is_string($uri) && trim($uri) !== '') {
                return $this->withAnnotationAdditionalActions([
                    'kind' => 'uri',
                    'uri' => trim($uri),
                ], $additionalActions);
            }
        }

        if ($action !== null && strtoupper((string) $this->nameDictionaryValue($action, 'S')) === 'GOTO') {
            $destination = $this->internalDestinationFromToken(
                $this->destinationTokenFromDictionaryKey($action, 'D', $objects),
                $objects,
                $pageNumberByObject,
                $namedDestinations
            );
            if ($destination !== null) {
                return $this->withAnnotationAdditionalActions(['kind' => 'goto'] + $destination, $additionalActions);
            }
        }

        if ($action !== null && strtoupper((string) $this->nameDictionaryValue($action, 'S')) === 'GOTOR') {
            $target = $this->remoteGoToTargetFromAction($action, $objects);
            if ($target !== null) {
                return $this->withAnnotationAdditionalActions($target, $additionalActions);
            }
        }

        if ($action !== null && strtoupper((string) $this->nameDictionaryValue($action, 'S')) === 'LAUNCH') {
            $target = $this->launchTargetFromAction($action, $objects);
            if ($target !== null) {
                return $this->withAnnotationAdditionalActions($target, $additionalActions);
            }
        }

        if ($action !== null) {
            $target = $this->reportOnlyLinkTargetFromAction($action, $objects);
            if ($target !== null) {
                return $this->withAnnotationAdditionalActions($target, $additionalActions);
            }
        }

        $destination = $this->internalDestinationFromToken(
            $this->destinationTokenFromDictionaryKey($dictionary, 'Dest', $objects),
            $objects,
            $pageNumberByObject,
            $namedDestinations
        );
        if ($destination !== null) {
            return $this->withAnnotationAdditionalActions(['kind' => 'goto'] + $destination, $additionalActions);
        }

        $uri = $this->textStringFromDictionaryKey($dictionary, 'URI', $objects);
        if (is_string($uri) && trim($uri) !== '') {
            return $this->withAnnotationAdditionalActions([
                'kind' => 'uri',
                'uri' => trim($uri),
            ], $additionalActions);
        }

        if ($additionalActions !== []) {
            return [
                'kind' => 'additionalActions',
                'uri' => '',
                'action' => 'AdditionalActions',
                'safeToApply' => false,
                'additionalActions' => $additionalActions,
            ];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $target
     * @param list<array<string, mixed>> $additionalActions
     * @return array<string, mixed>
     */
    private function withAnnotationAdditionalActions(array $target, array $additionalActions): array
    {
        if ($additionalActions !== []) {
            $target['additionalActions'] = $additionalActions;
        }

        return $target;
    }

    /**
     * @param array<int, string> $objects
     * @return list<array<string, mixed>>
     */
    private function annotationAdditionalActions(string $dictionary, array $objects): array
    {
        $additionalActionDictionary = $this->dictionaryDictionaryTokenFromTokens($this->dictionaryTokens($dictionary), 'AA', $objects);
        if ($additionalActionDictionary === null) {
            return [];
        }

        $actions = [];
        foreach ([
            'E' => 'enter',
            'X' => 'exit',
            'D' => 'mouseDown',
            'U' => 'mouseUp',
            'Fo' => 'focus',
            'Bl' => 'blur',
            'PO' => 'pageOpen',
            'PC' => 'pageClose',
            'PV' => 'pageVisible',
            'PI' => 'pageInvisible',
            'K' => 'keystroke',
            'F' => 'format',
            'V' => 'validate',
            'C' => 'calculate',
        ] as $pdfKey => $trigger) {
            $entry = $this->dictionaryEntryValue($additionalActionDictionary, $pdfKey, $objects);
            if ($entry === null) {
                continue;
            }

            foreach ($this->actionSummariesFromValue($entry['token'], $objects, $entry['objectNumber'] ?? null) as $summary) {
                $actions[] = [
                    'trigger' => $pdfKey,
                    'event' => $trigger,
                ] + $summary;
            }
        }

        return $actions;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function remoteGoToTargetFromAction(string $action, array $objects): ?array
    {
        $fileSpecification = $this->fileSpecificationFromDictionaryKey($action, 'F', $objects);
        if ($fileSpecification === null) {
            return null;
        }

        $target = [
            'kind' => 'gotor',
            'uri' => '',
            'action' => 'GoToR',
            'safeToApply' => false,
            'remoteFile' => $fileSpecification['file'],
            'fileSpecification' => $fileSpecification,
        ];

        $newWindow = $this->booleanDictionaryValue($action, 'NewWindow');
        if ($newWindow !== null) {
            $target['newWindow'] = $newWindow;
        }

        $destination = $this->remoteDestinationFromToken(
            $this->destinationTokenFromDictionaryKey($action, 'D', $objects),
            $objects
        );
        if ($destination !== null) {
            $target += $destination;
        }

        return $target;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function reportOnlyLinkTargetFromAction(string $action, array $objects): ?array
    {
        $actionType = strtoupper((string) $this->nameDictionaryValue($action, 'S'));
        $target = match ($actionType) {
            'NAMED' => $this->namedActionTargetFromAction($action, $objects),
            'JAVASCRIPT' => $this->javascriptActionTargetFromAction($action, $objects),
            'SUBMITFORM' => $this->formActionTargetFromAction($action, $objects, 'submitForm', 'SubmitForm'),
            'RESETFORM' => $this->formActionTargetFromAction($action, $objects, 'resetForm', 'ResetForm'),
            'IMPORTDATA' => $this->formActionTargetFromAction($action, $objects, 'importData', 'ImportData'),
            'HIDE' => $this->hideActionTargetFromAction($action, $objects),
            default => $this->genericReportOnlyActionTargetFromAction($action, $objects),
        };

        if ($target === null) {
            return null;
        }

        $nextActions = $this->nextActionSummaries($action, $objects);
        if ($nextActions !== []) {
            $target['nextActions'] = $nextActions;
        }

        return $target;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function genericReportOnlyActionTargetFromAction(string $action, array $objects): ?array
    {
        $summary = $this->actionSummaryFromDictionary($action, $objects);
        if ($summary === null) {
            return null;
        }

        $actionType = (string) $summary['action'];
        $kind = match (strtoupper($actionType)) {
            'GOTO' => 'gotoAction',
            'URI' => 'uriAction',
            'GOTOR' => 'gotor',
            'LAUNCH' => 'launch',
            'GOTOE' => 'gotoe',
            'THREAD' => 'thread',
            'SOUND' => 'sound',
            'MOVIE' => 'movie',
            'RENDITION' => 'rendition',
            'TRANS' => 'transition',
            'SETOCGSTATE' => 'setOCGState',
            'GOTO3DVIEW' => 'goto3DView',
            'RICHMEDIAEXECUTE' => 'richMediaExecute',
            default => 'action',
        };

        return [
            'kind' => $kind,
            'uri' => '',
            'action' => $actionType,
            'safeToApply' => false,
        ] + $summary;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function namedActionTargetFromAction(string $action, array $objects): ?array
    {
        $namedAction = $this->actionNameValue($action, 'N', $objects);
        if ($namedAction === null) {
            return null;
        }

        return [
            'kind' => 'named',
            'uri' => '',
            'action' => 'Named',
            'safeToApply' => false,
            'namedAction' => $namedAction,
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function javascriptActionTargetFromAction(string $action, array $objects): array
    {
        return [
            'kind' => 'javascript',
            'uri' => '',
            'action' => 'JavaScript',
            'safeToApply' => false,
        ] + $this->javascriptSummaryFromAction($action, $objects);
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function formActionTargetFromAction(string $action, array $objects, string $kind, string $actionName): array
    {
        $target = [
            'kind' => $kind,
            'uri' => '',
            'action' => $actionName,
            'safeToApply' => false,
        ];

        $fileSpecification = $this->fileSpecificationFromDictionaryKey($action, 'F', $objects);
        if ($fileSpecification !== null) {
            $target['fileSpecification'] = $fileSpecification;
            if ($kind === 'submitForm') {
                $target['submitFile'] = $fileSpecification['file'];
            } elseif ($kind === 'importData') {
                $target['importFile'] = $fileSpecification['file'];
            }
        }

        $fields = $this->actionReferencesFromDictionaryKey($action, 'Fields', $objects, 'field');
        if ($fields !== []) {
            $target['fields'] = $fields;
        }

        $flags = $this->integerDictionaryValueFromObjects($action, 'Flags', $objects);
        if ($flags !== null) {
            $target['flags'] = $flags;
        }

        return $target;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function hideActionTargetFromAction(string $action, array $objects): array
    {
        $target = [
            'kind' => 'hide',
            'uri' => '',
            'action' => 'Hide',
            'safeToApply' => false,
        ];

        $targets = $this->actionReferencesFromDictionaryKey($action, 'T', $objects, 'target');
        if ($targets !== []) {
            $target['targets'] = $targets;
        }

        $hide = $this->booleanDictionaryValue($action, 'H');
        if ($hide !== null) {
            $target['hide'] = $hide;
        }

        return $target;
    }

    /**
     * @param array<int, string> $objects
     */
    private function actionNameValue(string $dictionary, string $key, array $objects): ?string
    {
        $name = $this->nameDictionaryValue($dictionary, $key);
        if (is_string($name) && $name !== '') {
            return $name;
        }

        $text = $this->textStringFromDictionaryKey($dictionary, $key, $objects);
        return is_string($text) && trim($text) !== '' ? trim($text) : null;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>
     */
    private function javascriptSummaryFromAction(string $action, array $objects): array
    {
        $javascript = $this->textStringFromDictionaryKey($action, 'JS', $objects);
        if (is_string($javascript) && trim($javascript) !== '') {
            return ['javascript' => trim($javascript)];
        }

        $entry = $this->dictionaryEntryValue($action, 'JS', $objects);
        if ($entry === null) {
            return [];
        }

        $stream = $this->streamObjectParts($entry['token']);
        if ($stream === null) {
            return [];
        }

        $decoded = $this->decodeStream($stream['dictionary'], $stream['stream'], $objects);
        $summary = [
            'javascriptDecoded' => $decoded !== null,
            'javascriptFilters' => $this->streamFilters($stream['dictionary'], $objects),
            'javascriptBytes' => strlen($stream['stream']),
        ];
        if (isset($entry['objectNumber'])) {
            $summary['javascriptObject'] = $entry['objectNumber'];
        }
        if (is_string($decoded) && trim($decoded) !== '') {
            $summary['javascript'] = trim($decoded);
        }

        return $summary;
    }

    /**
     * @param array<int, string> $objects
     * @return list<array<string, mixed>>
     */
    private function actionReferencesFromDictionaryKey(string $dictionary, string $key, array $objects, string $stringKey): array
    {
        $entry = $this->dictionaryEntryValue($dictionary, $key, $objects);
        if ($entry === null) {
            return [];
        }

        return $this->actionReferencesFromValue($entry['token'], $objects, $stringKey, $entry['objectNumber'] ?? null);
    }

    /**
     * @param array<int, string> $objects
     * @return list<array<string, mixed>>
     */
    private function actionReferencesFromValue(string $value, array $objects, string $stringKey, ?int $objectNumber = null): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        if (str_starts_with($value, '[')) {
            $references = [];
            $tokens = $this->arrayTokens($value);
            for ($index = 0, $count = count($tokens); $index < $count;) {
                $referenceObject = $this->indirectObjectOperand($tokens, $index);
                if ($referenceObject !== null) {
                    $index += 3;
                    if (isset($objects[$referenceObject])) {
                        foreach ($this->actionReferencesFromValue($objects[$referenceObject], $objects, $stringKey, $referenceObject) as $reference) {
                            $references[] = $reference;
                        }
                    }
                    continue;
                }

                $token = $tokens[$index] ?? null;
                $index++;
                if (is_string($token)) {
                    foreach ($this->actionReferencesFromValue($token, $objects, $stringKey) as $reference) {
                        $references[] = $reference;
                    }
                }
            }

            return $references;
        }

        $dictionary = $this->dictionaryFromValue($value);
        if ($dictionary !== null) {
            $reference = [];
            if ($objectNumber !== null) {
                $reference['objectNumber'] = $objectNumber;
            }

            $fieldName = $this->normalizedAnnotationText($this->textStringFromDictionaryKey($dictionary, 'T', $objects));
            if ($fieldName !== null) {
                $reference['field'] = $fieldName;
            }

            $annotationName = $this->normalizedAnnotationText($this->textStringFromDictionaryKey($dictionary, 'NM', $objects));
            if ($annotationName !== null) {
                $reference['name'] = $annotationName;
            }

            $subtype = $this->nameDictionaryValue($dictionary, 'Subtype');
            if (is_string($subtype) && $subtype !== '') {
                $reference['subtype'] = $subtype;
            }

            $fieldType = $this->nameDictionaryValue($dictionary, 'FT');
            if (is_string($fieldType) && $fieldType !== '') {
                $reference['fieldType'] = $fieldType;
            }

            return $reference === [] ? [] : [$reference];
        }

        $name = $this->destinationNameFromToken($value);
        if ($name === null || $name === '') {
            return [];
        }

        return [[$stringKey => $name] + ($objectNumber === null ? [] : ['objectNumber' => $objectNumber])];
    }

    /**
     * @param array<int, string> $objects
     * @return list<array<string, mixed>>
     */
    private function nextActionSummaries(string $action, array $objects): array
    {
        $entry = $this->dictionaryEntryValue($action, 'Next', $objects);
        if ($entry === null) {
            return [];
        }

        return $this->actionSummariesFromValue($entry['token'], $objects, $entry['objectNumber'] ?? null);
    }

    /**
     * @param array<int, string> $objects
     * @return list<array<string, mixed>>
     */
    private function actionSummariesFromValue(string $value, array $objects, ?int $objectNumber = null): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        if (str_starts_with($value, '[')) {
            $summaries = [];
            $tokens = $this->arrayTokens($value);
            for ($index = 0, $count = count($tokens); $index < $count;) {
                $actionObject = $this->indirectObjectOperand($tokens, $index);
                if ($actionObject !== null) {
                    $index += 3;
                    if (isset($objects[$actionObject])) {
                        foreach ($this->actionSummariesFromValue($objects[$actionObject], $objects, $actionObject) as $summary) {
                            $summaries[] = $summary;
                        }
                    }
                    continue;
                }

                $token = $tokens[$index] ?? null;
                $index++;
                if (is_string($token)) {
                    foreach ($this->actionSummariesFromValue($token, $objects) as $summary) {
                        $summaries[] = $summary;
                    }
                }
            }

            return $summaries;
        }

        $dictionary = $this->dictionaryFromValue($value);
        if ($dictionary === null) {
            return [];
        }

        $summary = $this->actionSummaryFromDictionary($dictionary, $objects);
        if ($summary === null) {
            return [];
        }
        if ($objectNumber !== null) {
            $summary['actionObject'] = $objectNumber;
        }

        return [$summary];
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function actionSummaryFromDictionary(string $action, array $objects): ?array
    {
        $actionType = $this->nameDictionaryValue($action, 'S');
        if (!is_string($actionType) || $actionType === '') {
            return null;
        }

        $summary = ['action' => $actionType];
        switch (strtoupper($actionType)) {
            case 'URI':
                $uri = $this->textStringFromDictionaryKey($action, 'URI', $objects);
                if (is_string($uri) && trim($uri) !== '') {
                    $summary['uri'] = trim($uri);
                }
                break;
            case 'GOTO':
            case 'GOTOR':
                $destination = $this->destinationTokenFromDictionaryKey($action, 'D', $objects);
                if (is_string($destination) && trim($destination) !== '') {
                    $summary['destination'] = trim($destination);
                }
                if (strtoupper($actionType) === 'GOTOR') {
                    $fileSpecification = $this->fileSpecificationFromDictionaryKey($action, 'F', $objects);
                    if ($fileSpecification !== null) {
                        $summary['fileSpecification'] = $fileSpecification;
                        $summary['remoteFile'] = $fileSpecification['file'];
                    }
                    $newWindow = $this->booleanDictionaryValue($action, 'NewWindow');
                    if ($newWindow !== null) {
                        $summary['newWindow'] = $newWindow;
                    }
                }
                break;
            case 'GOTOE':
                $destination = $this->destinationTokenFromDictionaryKey($action, 'D', $objects);
                if (is_string($destination) && trim($destination) !== '') {
                    $summary['destination'] = trim($destination);
                }
                $fileSpecification = $this->fileSpecificationFromDictionaryKey($action, 'F', $objects);
                if ($fileSpecification !== null) {
                    $summary['fileSpecification'] = $fileSpecification;
                    $summary['embeddedFile'] = $fileSpecification['file'];
                }
                $target = $this->embeddedTargetSummaryFromDictionaryKey($action, 'T', $objects);
                if ($target !== null) {
                    $summary['target'] = $target;
                }
                $newWindow = $this->booleanDictionaryValue($action, 'NewWindow');
                if ($newWindow !== null) {
                    $summary['newWindow'] = $newWindow;
                }
                break;
            case 'NAMED':
                $namedAction = $this->actionNameValue($action, 'N', $objects);
                if ($namedAction !== null) {
                    $summary['namedAction'] = $namedAction;
                }
                break;
            case 'JAVASCRIPT':
                $summary += $this->javascriptSummaryFromAction($action, $objects);
                break;
            case 'SUBMITFORM':
            case 'RESETFORM':
            case 'IMPORTDATA':
                $fields = $this->actionReferencesFromDictionaryKey($action, 'Fields', $objects, 'field');
                if ($fields !== []) {
                    $summary['fields'] = $fields;
                }
                $flags = $this->integerDictionaryValueFromObjects($action, 'Flags', $objects);
                if ($flags !== null) {
                    $summary['flags'] = $flags;
                }
                $fileSpecification = $this->fileSpecificationFromDictionaryKey($action, 'F', $objects);
                if ($fileSpecification !== null) {
                    $summary['fileSpecification'] = $fileSpecification;
                }
                break;
            case 'HIDE':
                $targets = $this->actionReferencesFromDictionaryKey($action, 'T', $objects, 'target');
                if ($targets !== []) {
                    $summary['targets'] = $targets;
                }
                $hide = $this->booleanDictionaryValue($action, 'H');
                if ($hide !== null) {
                    $summary['hide'] = $hide;
                }
                break;
            case 'LAUNCH':
                $launchTarget = $this->launchTargetFromAction($action, $objects);
                if ($launchTarget !== null) {
                    foreach ($launchTarget as $key => $value) {
                        if (!in_array($key, ['kind', 'uri', 'action', 'safeToApply'], true)) {
                            $summary[$key] = $value;
                        }
                    }
                }
                break;
            case 'THREAD':
                $destination = $this->destinationTokenFromDictionaryKey($action, 'D', $objects);
                if (is_string($destination) && trim($destination) !== '') {
                    $summary['destination'] = trim($destination);
                }
                $threads = $this->actionReferencesFromDictionaryKey($action, 'D', $objects, 'thread');
                if ($threads !== []) {
                    $summary['threads'] = $threads;
                }
                $beads = $this->actionReferencesFromDictionaryKey($action, 'B', $objects, 'bead');
                if ($beads !== []) {
                    $summary['beads'] = $beads;
                }
                break;
            case 'SOUND':
                $sound = $this->soundSummaryFromDictionaryKey($action, 'Sound', $objects);
                if ($sound !== null) {
                    $summary['sound'] = $sound;
                }
                $volume = $this->numericDictionaryValue($action, 'Volume');
                if ($volume !== null) {
                    $summary['volume'] = $volume;
                }
                foreach ([
                    'Synchronous' => 'synchronous',
                    'Repeat' => 'repeat',
                    'Mix' => 'mix',
                ] as $pdfKey => $resultKey) {
                    $value = $this->booleanDictionaryValue($action, $pdfKey);
                    if ($value !== null) {
                        $summary[$resultKey] = $value;
                    }
                }
                break;
            case 'MOVIE':
                $annotations = $this->actionReferencesFromDictionaryKey($action, 'Annotation', $objects, 'annotation');
                if ($annotations !== []) {
                    $summary['annotations'] = $annotations;
                }
                $title = $this->normalizedAnnotationText($this->textStringFromDictionaryKey($action, 'T', $objects));
                if ($title !== null) {
                    $summary['title'] = $title;
                }
                $operation = $this->actionNameValue($action, 'Operation', $objects);
                if ($operation !== null) {
                    $summary['operation'] = $operation;
                }
                break;
            case 'RENDITION':
                $operation = $this->integerDictionaryValueFromObjects($action, 'OP', $objects);
                if ($operation !== null) {
                    $summary['operation'] = $operation;
                }
                $renditions = $this->actionReferencesFromDictionaryKey($action, 'R', $objects, 'rendition');
                if ($renditions !== []) {
                    $summary['renditions'] = $renditions;
                }
                $annotations = $this->actionReferencesFromDictionaryKey($action, 'AN', $objects, 'annotation');
                if ($annotations !== []) {
                    $summary['annotations'] = $annotations;
                }
                $summary += $this->javascriptSummaryFromAction($action, $objects);
                break;
            case 'TRANS':
                $transition = $this->transitionSummaryFromDictionaryKey($action, 'Trans', $objects);
                if ($transition !== null) {
                    $summary['transition'] = $transition;
                }
                break;
            case 'SETOCGSTATE':
                $state = $this->optionalContentStateFromAction($action, $objects);
                if ($state !== []) {
                    $summary['state'] = $state;
                }
                $preserve = $this->booleanDictionaryValue($action, 'PreserveRB');
                if ($preserve !== null) {
                    $summary['preserveRadioButtonState'] = $preserve;
                }
                break;
            case 'GOTO3DVIEW':
                $targetAnnotations = $this->actionReferencesFromDictionaryKey($action, 'TA', $objects, 'annotation');
                if ($targetAnnotations !== []) {
                    $summary['targetAnnotations'] = $targetAnnotations;
                }
                $view = $this->actionNameValue($action, 'V', $objects);
                if ($view !== null) {
                    $summary['view'] = $view;
                }
                break;
            case 'RICHMEDIAEXECUTE':
                $targetAnnotations = $this->actionReferencesFromDictionaryKey($action, 'TA', $objects, 'annotation');
                if ($targetAnnotations !== []) {
                    $summary['targetAnnotations'] = $targetAnnotations;
                }
                $command = $this->richMediaCommandSummaryFromAction($action, $objects);
                if ($command !== null) {
                    $summary['command'] = $command;
                }
                break;
        }

        return $summary;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function embeddedTargetSummaryFromDictionaryKey(string $dictionary, string $key, array $objects): ?array
    {
        $entry = $this->dictionaryEntryValue($dictionary, $key, $objects);
        if ($entry === null) {
            return null;
        }

        $target = $this->dictionaryFromValue($entry['token']);
        if ($target === null) {
            return null;
        }

        $summary = [];
        if (isset($entry['objectNumber'])) {
            $summary['objectNumber'] = $entry['objectNumber'];
        }
        $relationship = $this->actionNameValue($target, 'R', $objects);
        if ($relationship !== null) {
            $summary['relationship'] = $relationship;
        }
        $name = $this->normalizedAnnotationText($this->textStringFromDictionaryKey($target, 'N', $objects));
        if ($name !== null) {
            $summary['name'] = $name;
        }
        $page = $this->integerDictionaryValueFromObjects($target, 'P', $objects);
        if ($page !== null) {
            $summary['page'] = $page;
        }
        $annotation = $this->integerDictionaryValueFromObjects($target, 'A', $objects);
        if ($annotation !== null) {
            $summary['annotation'] = $annotation;
        }

        $parent = $this->embeddedTargetSummaryFromDictionaryKey($target, 'T', $objects);
        if ($parent !== null) {
            $summary['parent'] = $parent;
        }

        return $summary === [] ? null : $summary;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function soundSummaryFromDictionaryKey(string $dictionary, string $key, array $objects): ?array
    {
        $entry = $this->lastDictionaryEntryValue($dictionary, $key, $objects);
        if ($entry === null) {
            return null;
        }

        $sound = $this->dictionaryFromValue($entry['token']);
        $summary = isset($entry['objectNumber']) ? ['objectNumber' => $entry['objectNumber']] : [];
        if ($sound === null) {
            return $summary === [] ? null : $summary;
        }

        foreach ([
            'R' => 'rate',
            'C' => 'channels',
            'B' => 'bitsPerSample',
        ] as $pdfKey => $resultKey) {
            $value = $this->numericDictionaryValue($sound, $pdfKey);
            if ($value !== null) {
                $summary[$resultKey] = $value;
            }
        }

        $encoding = $this->actionNameValue($sound, 'E', $objects);
        if ($encoding !== null) {
            $summary['encoding'] = $encoding;
        }

        return $summary === [] ? null : $summary;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function transitionSummaryFromDictionaryKey(string $dictionary, string $key, array $objects): ?array
    {
        $entry = $this->lastDictionaryEntryValue($dictionary, $key, $objects);
        if ($entry === null) {
            return null;
        }

        $transition = $this->dictionaryFromValue($entry['token']);
        if ($transition === null) {
            return null;
        }

        $summary = isset($entry['objectNumber']) ? ['objectNumber' => $entry['objectNumber']] : [];
        foreach ([
            'S' => 'style',
            'Dm' => 'dimension',
            'M' => 'motion',
            'Di' => 'direction',
        ] as $pdfKey => $resultKey) {
            $value = $this->actionNameValue($transition, $pdfKey, $objects);
            if ($value !== null) {
                $summary[$resultKey] = $value;
            }
        }

        $duration = $this->numericDictionaryValue($transition, 'D');
        if ($duration !== null) {
            $summary['duration'] = $duration;
        }

        return $summary === [] ? null : $summary;
    }

    /**
     * @param array<int, string> $objects
     * @return list<array<string, mixed>>
     */
    private function optionalContentStateFromAction(string $action, array $objects): array
    {
        $entry = $this->dictionaryEntryValue($action, 'State', $objects);
        if ($entry === null || !str_starts_with(trim($entry['token']), '[')) {
            return [];
        }

        $state = [];
        $tokens = $this->arrayTokens($entry['token']);
        for ($index = 0, $count = count($tokens); $index < $count;) {
            $objectNumber = $this->indirectObjectOperand($tokens, $index);
            if ($objectNumber !== null) {
                $index += 3;
                $summary = ['objectNumber' => $objectNumber];
                if (isset($objects[$objectNumber])) {
                    $dictionary = $this->dictionaryFromValue($objects[$objectNumber]);
                    if ($dictionary !== null) {
                        $type = $this->actionNameValue($dictionary, 'Type', $objects);
                        if ($type !== null) {
                            $summary['type'] = $type;
                        }
                        $name = $this->normalizedAnnotationText($this->textStringFromDictionaryKey($dictionary, 'Name', $objects));
                        if ($name !== null) {
                            $summary['name'] = $name;
                        }
                    }
                }
                $state[] = $summary;
                continue;
            }

            $token = $tokens[$index] ?? null;
            $index++;
            if (!is_string($token) || trim($token) === '') {
                continue;
            }

            if (str_starts_with($token, '/')) {
                $state[] = ['command' => $this->decodePdfName(substr($token, 1))];
                continue;
            }

            $text = $this->textStringFromToken($token);
            if ($text !== null && trim($text) !== '') {
                $state[] = ['name' => trim($text)];
            }
        }

        return $state;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function richMediaCommandSummaryFromAction(string $action, array $objects): ?array
    {
        $entry = $this->dictionaryEntryValue($action, 'CMD', $objects);
        if ($entry === null) {
            return null;
        }

        $command = $this->dictionaryFromValue($entry['token']);
        if ($command === null) {
            return null;
        }

        $summary = isset($entry['objectNumber']) ? ['objectNumber' => $entry['objectNumber']] : [];
        $commandName = $this->normalizedAnnotationText($this->textStringFromDictionaryKey($command, 'C', $objects))
            ?? $this->actionNameValue($command, 'C', $objects);
        if ($commandName !== null) {
            $summary['command'] = $commandName;
        }

        $arguments = $this->richMediaCommandArguments($command, $objects);
        if ($arguments !== []) {
            $summary['arguments'] = $arguments;
        }

        return $summary === [] ? null : $summary;
    }

    /**
     * @param array<int, string> $objects
     * @return list<string>
     */
    private function richMediaCommandArguments(string $command, array $objects): array
    {
        $entry = $this->dictionaryEntryValue($command, 'Args', $objects);
        if ($entry === null || !str_starts_with(trim($entry['token']), '[')) {
            return [];
        }

        $arguments = [];
        foreach ($this->arrayTokens($entry['token']) as $token) {
            if (!is_string($token)) {
                continue;
            }
            $text = $this->textStringFromToken($token);
            if ($text !== null && trim($text) !== '') {
                $arguments[] = trim($text);
            }
        }

        return $arguments;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function launchTargetFromAction(string $action, array $objects): ?array
    {
        $tokens = $this->dictionaryTokens($action);
        $fileSpecification = $this->fileSpecificationFromDictionaryKey($action, 'F', $objects);
        $windowsLaunch = $this->dictionaryDictionaryTokenFromTokens($tokens, 'Win', $objects);
        $windowsFileSpecification = $windowsLaunch === null ? null : $this->fileSpecificationFromDictionaryKey($windowsLaunch, 'F', $objects);
        $fileSpecification ??= $windowsFileSpecification;
        if ($fileSpecification === null) {
            return null;
        }

        $target = [
            'kind' => 'launch',
            'uri' => '',
            'action' => 'Launch',
            'safeToApply' => false,
            'launchFile' => $fileSpecification['file'],
            'fileSpecification' => $fileSpecification,
        ];

        if ($windowsLaunch !== null) {
            if ($windowsFileSpecification !== null) {
                $target['windowsFileSpecification'] = $windowsFileSpecification;
            }
            foreach ([
                'O' => 'launchOperation',
                'P' => 'launchParameters',
                'D' => 'launchDirectory',
            ] as $pdfKey => $resultKey) {
                $value = $this->textStringFromDictionaryKey($windowsLaunch, $pdfKey, $objects);
                if (is_string($value) && trim($value) !== '') {
                    $target[$resultKey] = trim($value);
                }
            }
        }

        return $target;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function remoteDestinationFromToken(?string $token, array $objects): ?array
    {
        $token = is_string($token) ? trim($token) : '';
        if ($token === '') {
            return null;
        }

        if (str_starts_with($token, '<<')) {
            return $this->remoteDestinationFromToken($this->destinationTokenFromDictionaryKey($token, 'D', $objects), $objects);
        }

        if (!str_starts_with($token, '[')) {
            $name = $this->destinationNameFromToken($token);
            return $name === null ? null : [
                'destination' => $token,
                'destinationName' => $name,
            ];
        }

        $tokens = $this->arrayTokens($token);
        if ($tokens === []) {
            return null;
        }

        $result = ['destination' => $token];
        $destinationTypeOffset = 1;
        if (preg_match('/^\d+$/', $tokens[0]) === 1) {
            $result['remotePage'] = ((int) $tokens[0]) + 1;
        } else {
            $name = $this->destinationNameFromToken($tokens[0]);
            if ($name !== null) {
                $result['destinationName'] = $name;
            }
            $destinationTypeOffset = 1;
        }

        $typeToken = $tokens[$destinationTypeOffset] ?? null;
        if (is_string($typeToken) && str_starts_with($typeToken, '/')) {
            $result['destinationType'] = $this->decodePdfName(substr($typeToken, 1));
        }

        return $result;
    }

    /**
     * @param array<int, string> $objects
     * @return array{file: string, portableFile?: string, unicodeFile?: string, dosFile?: string, macFile?: string, unixFile?: string, fileSystem?: string, description?: string}|null
     */
    private function fileSpecificationFromDictionaryKey(string $dictionary, string $key, array $objects): ?array
    {
        $tokens = $this->dictionaryTokens($dictionary);
        $fileDictionary = $this->dictionaryDictionaryTokenFromTokens($tokens, $key, $objects);
        if ($fileDictionary !== null) {
            return $this->fileSpecificationFromDictionary($fileDictionary, $objects);
        }

        $file = $this->textStringFromDictionaryKey($dictionary, $key, $objects);
        if (!is_string($file) || trim($file) === '') {
            return null;
        }

        $file = trim($file);
        return [
            'file' => $file,
            'portableFile' => $file,
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return array{file: string, portableFile?: string, unicodeFile?: string, dosFile?: string, macFile?: string, unixFile?: string, fileSystem?: string, description?: string}|null
     */
    private function fileSpecificationFromDictionary(string $dictionary, array $objects): ?array
    {
        $result = [];
        foreach ([
            'F' => 'portableFile',
            'UF' => 'unicodeFile',
            'DOS' => 'dosFile',
            'Mac' => 'macFile',
            'Unix' => 'unixFile',
        ] as $pdfKey => $resultKey) {
            $value = $this->textStringFromDictionaryKey($dictionary, $pdfKey, $objects);
            if (is_string($value) && trim($value) !== '') {
                $result[$resultKey] = trim($value);
            }
        }

        $file = $result['unicodeFile']
            ?? $result['portableFile']
            ?? $result['unixFile']
            ?? $result['dosFile']
            ?? $result['macFile']
            ?? null;
        if (!is_string($file) || $file === '') {
            return null;
        }

        $fileSystem = $this->nameDictionaryValue($dictionary, 'FS');
        if (is_string($fileSystem) && $fileSystem !== '') {
            $result['fileSystem'] = $fileSystem;
        }

        $description = $this->normalizedAnnotationText($this->textStringFromDictionaryKey($dictionary, 'Desc', $objects));
        if ($description !== null) {
            $result['description'] = $description;
        }

        return ['file' => $file] + $result;
    }

    /**
     * @param array<int, string> $objects
     */
    private function destinationTokenFromDictionaryKey(string $dictionary, string $key, array $objects): ?string
    {
        $tokens = $this->dictionaryTokens($dictionary);
        $count = count($tokens);
        for ($index = 0; $index + 1 < $count; $index++) {
            if (!$this->isPdfNameToken($tokens[$index], $key)) {
                continue;
            }

            $objectNumber = $this->indirectObjectOperand($tokens, $index + 1);
            if ($objectNumber !== null && isset($objects[$objectNumber])) {
                return trim($objects[$objectNumber]);
            }

            return $tokens[$index + 1];
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, int> $pageNumberByObject
     * @param array<string, string> $namedDestinations
     * @param array<string, true> $seenNames
     * @return array<string, mixed>|null
     */
    private function internalDestinationFromToken(?string $token, array $objects, array $pageNumberByObject, array $namedDestinations, array $seenNames = []): ?array
    {
        $token = is_string($token) ? trim($token) : '';
        if ($token === '') {
            return null;
        }

        if (str_starts_with($token, '[')) {
            return $this->internalDestinationFromArray($token, $pageNumberByObject);
        }

        if (str_starts_with($token, '<<')) {
            return $this->internalDestinationFromToken(
                $this->destinationTokenFromDictionaryKey($token, 'D', $objects),
                $objects,
                $pageNumberByObject,
                $namedDestinations,
                $seenNames
            );
        }

        $name = $this->destinationNameFromToken($token);
        if ($name === null || isset($seenNames[$name]) || !isset($namedDestinations[$name])) {
            return null;
        }

        return $this->internalDestinationFromToken(
            $namedDestinations[$name],
            $objects,
            $pageNumberByObject,
            $namedDestinations,
            $seenNames + [$name => true]
        );
    }

    /**
     * @param array<int, int> $pageNumberByObject
     * @return array<string, mixed>|null
     */
    private function internalDestinationFromArray(string $array, array $pageNumberByObject): ?array
    {
        $tokens = $this->arrayTokens($array);
        if ($tokens === []) {
            return null;
        }

        $targetPageObject = $this->indirectObjectOperand($tokens, 0);
        $destinationTypeOffset = 1;
        if ($targetPageObject !== null) {
            $targetPage = $pageNumberByObject[$targetPageObject] ?? null;
            $destinationTypeOffset = 3;
        } elseif (preg_match('/^\d+$/', $tokens[0]) === 1) {
            $targetPageObject = null;
            $targetPage = ((int) $tokens[0]) + 1;
        } else {
            return null;
        }

        if (!is_int($targetPage) || $targetPage <= 0) {
            return null;
        }

        $destinationType = '';
        $typeToken = $tokens[$destinationTypeOffset] ?? null;
        if (is_string($typeToken) && str_starts_with($typeToken, '/')) {
            $destinationType = $this->decodePdfName(substr($typeToken, 1));
        }

        return [
            'uri' => '#pdf-page-' . $targetPage,
            'targetPage' => $targetPage,
            'targetPageObject' => $targetPageObject,
            'destinationType' => $destinationType,
            'destination' => $array,
        ];
    }

    private function destinationNameFromToken(string $token): ?string
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        if (str_starts_with($token, '/')) {
            return $this->decodePdfName(substr($token, 1));
        }

        $text = $this->textStringFromToken($token);
        return is_string($text) && $text !== '' ? $text : null;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, string>
     */
    private function namedDestinationMap(array $objects): array
    {
        $destinations = [];
        foreach ($objects as $body) {
            if ($this->nameDictionaryValue($body, 'Type') !== 'Catalog') {
                continue;
            }

            $tokens = $this->dictionaryTokens($body);
            $namesDictionary = $this->dictionaryDictionaryTokenFromTokens($tokens, 'Names', $objects);
            if ($namesDictionary !== null) {
                $destsTree = $this->dictionaryDictionaryTokenFromTokens($this->dictionaryTokens($namesDictionary), 'Dests', $objects);
                if ($destsTree !== null) {
                    $destinations = array_replace($destinations, $this->namedDestinationsFromNameTree($destsTree, $objects));
                }
            }

            $destsDictionary = $this->dictionaryDictionaryTokenFromTokens($tokens, 'Dests', $objects);
            if ($destsDictionary !== null) {
                $destinations = array_replace($destinations, $this->namedDestinationsFromDictionary($destsDictionary, $objects));
            }
        }

        return $destinations;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     * @return array<string, string>
     */
    private function namedDestinationsFromNameTree(string $dictionary, array $objects, array $seen = []): array
    {
        $destinations = [];
        $tokens = $this->dictionaryTokens($dictionary);
        $names = $this->dictionaryArrayTokenFromTokens($tokens, 'Names', $objects);
        if ($names !== null) {
            $nameTokens = $this->arrayTokens($names);
            for ($index = 0, $count = count($nameTokens); $index < $count;) {
                $name = $this->destinationNameFromToken($nameTokens[$index] ?? '');
                $index++;
                $destination = $this->destinationValueTokenFromArrayTokens($nameTokens, $index, $objects);
                if ($name !== null && $destination !== null) {
                    $destinations[$name] = $destination;
                }
            }
        }

        $kids = $this->dictionaryArrayTokenFromTokens($tokens, 'Kids', $objects);
        if ($kids !== null) {
            $kidTokens = $this->arrayTokens($kids);
            for ($index = 0, $count = count($kidTokens); $index < $count;) {
                $objectNumber = $this->indirectObjectOperand($kidTokens, $index);
                if ($objectNumber !== null) {
                    $index += 3;
                    if (!isset($seen[$objectNumber]) && isset($objects[$objectNumber])) {
                        $kidDictionary = $this->dictionaryFromValue($objects[$objectNumber]);
                        if ($kidDictionary !== null) {
                            $destinations = array_replace($destinations, $this->namedDestinationsFromNameTree($kidDictionary, $objects, $seen + [$objectNumber => true]));
                        }
                    }
                    continue;
                }

                $token = $kidTokens[$index] ?? null;
                $index++;
                if (is_string($token) && str_starts_with(trim($token), '<<')) {
                    $destinations = array_replace($destinations, $this->namedDestinationsFromNameTree($token, $objects, $seen));
                }
            }
        }

        return $destinations;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, string>
     */
    private function namedDestinationsFromDictionary(string $dictionary, array $objects): array
    {
        $destinations = [];
        $tokens = $this->dictionaryTokens($dictionary);
        for ($index = 0, $count = count($tokens); $index < $count;) {
            $token = $tokens[$index] ?? '';
            $index++;
            if (!str_starts_with($token, '/')) {
                continue;
            }

            $name = $this->decodePdfName(substr($token, 1));
            $destination = $this->destinationValueTokenFromArrayTokens($tokens, $index, $objects);
            if ($destination !== null) {
                $destinations[$name] = $destination;
            }
        }

        return $destinations;
    }

    /**
     * @param list<string> $tokens
     * @param array<int, string> $objects
     */
    private function destinationValueTokenFromArrayTokens(array $tokens, int &$index, array $objects): ?string
    {
        $objectNumber = $this->indirectObjectOperand($tokens, $index);
        if ($objectNumber !== null) {
            $index += 3;
            return isset($objects[$objectNumber]) ? trim($objects[$objectNumber]) : null;
        }

        $token = $tokens[$index] ?? null;
        $index++;
        return is_string($token) ? $token : null;
    }

    /**
     * @param list<array{text: string, x1: float, y1: float, x2: float, y2: float}> $runs
     * @param list<list<float>> $regions
     */
    private function textForAnnotationRegions(array $runs, array $regions): string
    {
        $normalizedRegions = array_values(array_filter(
            $regions,
            static fn (array $region): bool => count($region) >= 4
        ));
        if ($normalizedRegions === []) {
            return '';
        }

        $text = '';
        $previousRun = null;
        foreach ($runs as $run) {
            $intersects = false;
            $runRect = [$run['x1'], $run['y1'], $run['x2'], $run['y2']];
            foreach ($normalizedRegions as $region) {
                if ($this->rectanglesIntersect($runRect, $region)) {
                    $intersects = true;
                    break;
                }
            }
            if (!$intersects) {
                continue;
            }

            $runText = $run['text'];
            if ($runText === '') {
                continue;
            }

            if ($text !== ''
                && !$this->endsWithWhitespace($text)
                && !$this->startsWithWhitespace($runText)
                && is_array($previousRun)
                && (
                    abs($run['y1'] - $previousRun['y1']) >= self::POSITIONED_TEXT_LINE_TOLERANCE
                    || $run['x1'] - $previousRun['x2'] >= self::POSITIONED_TEXT_WORD_GAP
                )
            ) {
                $text .= ' ';
            }

            $text .= $runText;
            $previousRun = $run;
        }

        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        return trim($text);
    }

    /**
     * @param list<float> $a
     * @param list<float> $b
     */
    private function rectanglesIntersect(array $a, array $b): bool
    {
        if (count($a) < 4 || count($b) < 4) {
            return false;
        }

        return $a[0] < $b[2] && $a[2] > $b[0] && $a[1] < $b[3] && $a[3] > $b[1];
    }

    /**
     * @param array<int, string> $objects
     * @return array{
     *     roleMap: array<string, string>,
     *     structureRoles: array<string, string>,
     *     languages: list<string>,
     *     classMap: array<string, list<array<string, mixed>>>,
     *     structureAttributes: list<array{role: string, resolvedRole: string, classes: list<string>, attributes: list<array<string, mixed>>}>,
     *     structureItems: list<array{objectNumber: int, role: string, resolvedRole: string, language: string|null, classes: list<string>, attributes: list<array<string, mixed>>, text: string}>,
     *     structureBlocks: list<array<string, mixed>>,
     *     tables: list<array{objectNumber: int, rows: list<list<array{role: string, resolvedRole: string, text: string, rowSpan: int, colSpan: int, attributes: list<array<string, mixed>>}>>}>,
     *     attributeOwners: list<string>,
     *     structElementCount: int
     * }
     */
    private function taggedPdfSemantics(array $objects): array
    {
        $roleMap = [];
        $structureRoles = [];
        $languages = [];
        $classMap = [];
        $structureAttributes = [];
        $structureItems = [];
        $attributeOwners = [];
        $structElementCount = 0;

        foreach ($this->structTreeRootDiagnosticNodes($objects) as $rootNode) {
            $roleMap = array_replace($roleMap, $this->taggedRoleMapFromStructTreeRoot($rootNode['body'], $objects));
            $classMap = array_replace($classMap, $this->taggedClassMapFromStructTreeRoot($rootNode['body'], $objects));
            $language = $this->languageFromDictionary($rootNode['body'], $objects);
            if ($language !== null) {
                $languages[] = $language;
            }
        }

        foreach ($objects as $objectNumber => $body) {
            $type = $this->nameDictionaryValue($body, 'Type');
            if (in_array($type, ['Catalog', 'Page'], true)) {
                $language = $this->languageFromDictionary($body, $objects);
                if ($language !== null) {
                    $languages[] = $language;
                }
            }

            if ($type !== 'StructElem') {
                continue;
            }

            $structElementCount++;
            $role = $this->nameDictionaryValue($body, 'S');
            $resolvedRole = $role === null || $role === '' ? '' : $this->resolveTaggedRole($role, $roleMap);
            if ($role !== null && $role !== '') {
                $structureRoles[$role] = $resolvedRole;
            }

            $structLanguage = $this->languageFromDictionary($body, $objects);
            if ($structLanguage !== null) {
                $languages[] = $structLanguage;
            }

            $classes = $this->classNamesFromDictionary($body, $objects);
            $attributes = $this->taggedAttributesFromStructElement($body, $objects, $classMap);
            $text = $this->structElementSemanticText($body, $objects);
            $structureItems[] = [
                'objectNumber' => (int) $objectNumber,
                'role' => $role ?? '',
                'resolvedRole' => $resolvedRole,
                'language' => $structLanguage,
                'classes' => $classes,
                'attributes' => $attributes,
                'text' => $text,
            ];
            if ($attributes !== []) {
                $structureAttributes[] = [
                    'role' => $role ?? '',
                    'resolvedRole' => $resolvedRole,
                    'classes' => $classes,
                    'attributes' => $attributes,
                ];
                foreach ($attributes as $attributeDictionary) {
                    $owner = $attributeDictionary['O'] ?? null;
                    if (is_string($owner) && $owner !== '') {
                        $attributeOwners[] = $owner;
                    }
                }
            }
        }

        ksort($roleMap);
        ksort($structureRoles);
        ksort($classMap);
        $languages = array_values(array_unique(array_filter($languages, static fn (string $language): bool => $language !== '')));
        sort($languages);
        $attributeOwners = array_values(array_unique($attributeOwners));
        sort($attributeOwners);
        $tables = $this->taggedTablesFromStructElements($objects, $roleMap, $classMap);
        $structureBlocks = $this->taggedStructureBlocksFromRoots($objects, $roleMap, $classMap);

        return [
            'roleMap' => $roleMap,
            'structureRoles' => $structureRoles,
            'languages' => $languages,
            'classMap' => $classMap,
            'structureAttributes' => $structureAttributes,
            'structureItems' => $structureItems,
            'structureBlocks' => $structureBlocks,
            'tables' => $tables,
            'attributeOwners' => $attributeOwners,
            'structElementCount' => $structElementCount,
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return list<array{body: string, objectNumber: int}>
     */
    private function structTreeRootDiagnosticNodes(array $objects): array
    {
        return $this->structTreeRootNodes($objects);
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, string>
     */
    private function taggedRoleMapFromStructTreeRoot(string $structTreeBody, array $objects): array
    {
        $tokens = $this->dictionaryTokens($structTreeBody);
        $roleMapDictionary = $this->dictionaryDictionaryTokenFromTokens($tokens, 'RoleMap', $objects);
        if ($roleMapDictionary === null) {
            return [];
        }

        $map = [];
        $roleTokens = $this->dictionaryTokens($roleMapDictionary);
        for ($index = 0, $count = count($roleTokens); $index + 1 < $count;) {
            $source = $roleTokens[$index];
            $target = $roleTokens[$index + 1];
            if (!str_starts_with($source, '/') || !str_starts_with($target, '/')) {
                $index++;
                continue;
            }

            $sourceRole = $this->decodePdfName(substr($source, 1));
            $targetRole = $this->decodePdfName(substr($target, 1));
            if ($sourceRole !== '' && $targetRole !== '') {
                $map[$sourceRole] = $targetRole;
            }
            $index += 2;
        }

        return $map;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, list<array<string, mixed>>>
     */
    private function taggedClassMapFromStructTreeRoot(string $structTreeBody, array $objects): array
    {
        $tokens = $this->dictionaryTokens($structTreeBody);
        $classMapDictionary = $this->dictionaryDictionaryTokenFromTokens($tokens, 'ClassMap', $objects);
        if ($classMapDictionary === null) {
            return [];
        }

        $map = [];
        $classTokens = $this->dictionaryTokens($classMapDictionary);
        for ($index = 0, $count = count($classTokens); $index < $count;) {
            $classNameToken = $classTokens[$index] ?? null;
            if (!is_string($classNameToken) || !str_starts_with($classNameToken, '/')) {
                $index++;
                continue;
            }

            $className = $this->decodePdfName(substr($classNameToken, 1));
            $index++;
            $attributes = $this->attributeDictionariesFromTokens($classTokens, $index, $objects);
            if ($className !== '' && $attributes !== []) {
                $map[$className] = $attributes;
            }
        }

        return $map;
    }

    /**
     * @param array<int, string> $objects
     * @param array<string, list<array<string, mixed>>> $classMap
     * @return list<array<string, mixed>>
     */
    private function taggedAttributesFromStructElement(string $dictionary, array $objects, array $classMap): array
    {
        $attributes = [];
        foreach ($this->classNamesFromDictionary($dictionary, $objects) as $className) {
            foreach ($classMap[$className] ?? [] as $attributeDictionary) {
                $attributes[] = $attributeDictionary;
            }
        }

        foreach ($this->attributeDictionariesFromDictionaryKey($dictionary, 'A', $objects) as $attributeDictionary) {
            $attributes[] = $attributeDictionary;
        }

        return $this->uniqueAttributeDictionaries($attributes);
    }

    /**
     * @param array<int, string> $objects
     */
    private function structElementSemanticText(string $dictionary, array $objects): string
    {
        $replacementText = $this->structElementReplacementTextFromDictionary($dictionary, $objects);
        if ($replacementText !== null) {
            return trim($replacementText);
        }

        $childTexts = array_values(array_filter(
            $this->actualTextsFromStructElementKids($dictionary, $objects),
            static fn (string $text): bool => trim($text) !== ''
        ));

        return trim(implode("\n", $childTexts));
    }

    /**
     * @param array<int, string> $objects
     * @param array<string, string> $roleMap
     * @param array<string, list<array<string, mixed>>> $classMap
     * @return list<array<string, mixed>>
     */
    private function taggedTablesFromStructElements(array $objects, array $roleMap, array $classMap): array
    {
        $tables = [];

        foreach ($objects as $objectNumber => $body) {
            if ($this->nameDictionaryValue($body, 'Type') !== 'StructElem') {
                continue;
            }

            $role = $this->nameDictionaryValue($body, 'S') ?? '';
            $resolvedRole = $role === '' ? '' : $this->resolveTaggedRole($role, $roleMap);
            if (strtoupper($resolvedRole) !== 'TABLE') {
                continue;
            }

            $rows = $this->taggedTableRowsFromStructElement($body, $objects, $roleMap, $classMap, [(int) $objectNumber => true]);
            if (!$this->taggedTableRowsHaveText($rows)) {
                continue;
            }

            $table = [
                'objectNumber' => (int) $objectNumber,
                'rows' => $rows,
            ];
            $sections = $this->taggedTableSectionsFromStructElement($body, $objects, $roleMap, $classMap, [(int) $objectNumber => true]);
            if ($sections !== []) {
                $table['sections'] = $sections;
            }

            $tables[] = $table;
        }

        return $tables;
    }

    /**
     * @param array<int, string> $objects
     * @param array<string, string> $roleMap
     * @param array<string, list<array<string, mixed>>> $classMap
     * @return list<array<string, mixed>>
     */
    private function taggedStructureBlocksFromRoots(array $objects, array $roleMap, array $classMap): array
    {
        $blocks = [];
        foreach ($this->structTreeRootDiagnosticNodes($objects) as $rootNode) {
            foreach ($this->structElementChildObjectNumbers($rootNode['body'], $objects, [$rootNode['objectNumber'] => true]) as $objectNumber) {
                foreach ($this->taggedStructureBlocksFromStructElement($objectNumber, $objects, $roleMap, $classMap, [$rootNode['objectNumber'] => true]) as $block) {
                    if ($this->taggedStructureBlockHasText($block)) {
                        $blocks[] = $block;
                    }
                }
            }
        }

        return $blocks;
    }

    /**
     * @param array<int, string> $objects
     * @param array<string, string> $roleMap
     * @param array<string, list<array<string, mixed>>> $classMap
     * @param array<int, true> $seen
     * @return list<array<string, mixed>>
     */
    private function taggedStructureBlocksFromStructElement(int $objectNumber, array $objects, array $roleMap, array $classMap, array $seen): array
    {
        if (isset($seen[$objectNumber]) || !isset($objects[$objectNumber])) {
            return [];
        }

        $body = $objects[$objectNumber];
        if ($this->nameDictionaryValue($body, 'Type') !== 'StructElem') {
            return [];
        }

        $role = $this->nameDictionaryValue($body, 'S') ?? '';
        $resolvedRole = $role === '' ? '' : $this->resolveTaggedRole($role, $roleMap);
        $normalizedRole = strtoupper($resolvedRole);
        $classes = $this->classNamesFromDictionary($body, $objects);
        $attributes = $this->taggedAttributesFromStructElement($body, $objects, $classMap);
        $language = $this->languageFromDictionary($body, $objects);

        if ($normalizedRole === 'TABLE') {
            $rows = $this->taggedTableRowsFromStructElement($body, $objects, $roleMap, $classMap, $seen + [$objectNumber => true]);
            if (!$this->taggedTableRowsHaveText($rows)) {
                return [];
            }
            $sections = $this->taggedTableSectionsFromStructElement($body, $objects, $roleMap, $classMap, $seen + [$objectNumber => true]);

            $tableBlock = [
                'objectNumber' => $objectNumber,
                'role' => $role,
                'resolvedRole' => $resolvedRole,
                'kind' => 'table',
                'language' => $language,
                'classes' => $classes,
                'attributes' => $attributes,
                'text' => $this->taggedTableRowsText($rows),
                'rows' => $rows,
            ];
            if ($sections !== []) {
                $tableBlock['sections'] = $sections;
            }

            return [$tableBlock];
        }

        $childBlocks = [];
        foreach ($this->structElementChildObjectNumbers($body, $objects, $seen + [$objectNumber => true]) as $childObjectNumber) {
            foreach ($this->taggedStructureBlocksFromStructElement($childObjectNumber, $objects, $roleMap, $classMap, $seen + [$objectNumber => true]) as $childBlock) {
                $childBlocks[] = $childBlock;
            }
        }

        if ($this->taggedRoleIsContainer($normalizedRole) && $childBlocks !== []) {
            return $childBlocks;
        }

        $text = $this->structElementSemanticText($body, $objects);
        if (trim($text) !== '') {
            return [[
                'objectNumber' => $objectNumber,
                'role' => $role,
                'resolvedRole' => $resolvedRole,
                'kind' => 'block',
                'language' => $language,
                'classes' => $classes,
                'attributes' => $attributes,
                'text' => $text,
            ]];
        }

        return $childBlocks;
    }

    private function taggedRoleIsContainer(string $role): bool
    {
        return in_array($role, [
            'DOCUMENT',
            'PART',
            'ART',
            'SECT',
            'DIV',
            'NONSTRUCT',
            'L',
            'TOC',
            'TOCI',
            'INDEX',
            'THEAD',
            'TBODY',
            'TFOOT',
            'TR',
        ], true);
    }

    private function taggedTableRoleIsTransparentContainer(string $role): bool
    {
        return in_array($role, [
            'DOCUMENT',
            'PART',
            'ART',
            'SECT',
            'DIV',
            'NONSTRUCT',
            'SPAN',
        ], true);
    }

    /**
     * @param list<list<array{role: string, resolvedRole: string, text: string, rowSpan: int, colSpan: int, attributes: list<array<string, mixed>>}>> $rows
     */
    private function taggedTableRowsText(array $rows): string
    {
        $lines = [];
        foreach ($rows as $row) {
            foreach ($row as $cell) {
                $text = trim($cell['text']);
                if ($text !== '') {
                    $lines[] = $text;
                }
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $block
     */
    private function taggedStructureBlockHasText(array $block): bool
    {
        if (($block['kind'] ?? '') === 'table') {
            $rows = $block['rows'] ?? [];
            return is_array($rows) && $this->taggedTableRowsHaveText($rows);
        }

        $text = $block['text'] ?? '';
        return is_string($text) && trim($text) !== '';
    }

    /**
     * @param array<int, string> $objects
     * @param array<string, string> $roleMap
     * @param array<string, list<array<string, mixed>>> $classMap
     * @param array<int, true> $seen
     * @return list<list<array{role: string, resolvedRole: string, text: string, rowSpan: int, colSpan: int, attributes: list<array<string, mixed>>}>>
     */
    private function taggedTableRowsFromStructElement(string $dictionary, array $objects, array $roleMap, array $classMap, array $seen): array
    {
        $rows = [];
        $directCells = [];

        foreach ($this->structElementChildObjectNumbers($dictionary, $objects, $seen) as $childObjectNumber) {
            if (!isset($objects[$childObjectNumber])) {
                continue;
            }

            $childBody = $objects[$childObjectNumber];
            if ($this->nameDictionaryValue($childBody, 'Type') !== 'StructElem') {
                continue;
            }

            $role = $this->nameDictionaryValue($childBody, 'S') ?? '';
            $resolvedRole = $role === '' ? '' : $this->resolveTaggedRole($role, $roleMap);
            $normalizedRole = strtoupper($resolvedRole);
            if ($normalizedRole === 'TR') {
                $cells = $this->taggedTableCellsFromRow($childBody, $objects, $roleMap, $classMap, $seen + [$childObjectNumber => true]);
                if ($cells !== []) {
                    $rows[] = $cells;
                }
                continue;
            }

            if (in_array($normalizedRole, ['THEAD', 'TBODY', 'TFOOT'], true)) {
                foreach ($this->taggedTableRowsFromStructElement($childBody, $objects, $roleMap, $classMap, $seen + [$childObjectNumber => true]) as $row) {
                    $rows[] = $row;
                }
                continue;
            }

            if ($this->taggedTableRoleIsTransparentContainer($normalizedRole)) {
                foreach ($this->taggedTableRowsFromStructElement($childBody, $objects, $roleMap, $classMap, $seen + [$childObjectNumber => true]) as $row) {
                    $rows[] = $row;
                }
                continue;
            }

            if (in_array($normalizedRole, ['TH', 'TD'], true)) {
                $directCells[] = $this->taggedTableCellFromStructElement($childBody, $objects, $roleMap, $classMap);
            }
        }

        if ($rows === [] && $directCells !== []) {
            $rows[] = $directCells;
        }

        return $rows;
    }

    /**
     * @param array<int, string> $objects
     * @param array<string, string> $roleMap
     * @param array<string, list<array<string, mixed>>> $classMap
     * @param array<int, true> $seen
     * @return list<array<string, mixed>>
     */
    private function taggedTableSectionsFromStructElement(string $dictionary, array $objects, array $roleMap, array $classMap, array $seen): array
    {
        $sections = [];

        foreach ($this->structElementChildObjectNumbers($dictionary, $objects, $seen) as $childObjectNumber) {
            if (!isset($objects[$childObjectNumber])) {
                continue;
            }

            $childBody = $objects[$childObjectNumber];
            if ($this->nameDictionaryValue($childBody, 'Type') !== 'StructElem') {
                continue;
            }

            $role = $this->nameDictionaryValue($childBody, 'S') ?? '';
            $resolvedRole = $role === '' ? '' : $this->resolveTaggedRole($role, $roleMap);
            if (!in_array(strtoupper($resolvedRole), ['THEAD', 'TBODY', 'TFOOT'], true)) {
                if ($this->taggedTableRoleIsTransparentContainer(strtoupper($resolvedRole))) {
                    foreach ($this->taggedTableSectionsFromStructElement($childBody, $objects, $roleMap, $classMap, $seen + [$childObjectNumber => true]) as $section) {
                        $sections[] = $section;
                    }
                }
                continue;
            }

            $rows = $this->taggedTableRowsFromStructElement($childBody, $objects, $roleMap, $classMap, $seen + [$childObjectNumber => true]);
            if (!$this->taggedTableRowsHaveText($rows)) {
                continue;
            }

            $sections[] = [
                'objectNumber' => $childObjectNumber,
                'role' => $role,
                'resolvedRole' => $resolvedRole,
                'language' => $this->languageFromDictionary($childBody, $objects),
                'classes' => $this->classNamesFromDictionary($childBody, $objects),
                'attributes' => $this->taggedAttributesFromStructElement($childBody, $objects, $classMap),
                'rows' => $rows,
            ];
        }

        return $sections;
    }

    /**
     * @param array<int, string> $objects
     * @param array<string, string> $roleMap
     * @param array<string, list<array<string, mixed>>> $classMap
     * @param array<int, true> $seen
     * @return list<array{role: string, resolvedRole: string, text: string, rowSpan: int, colSpan: int, attributes: list<array<string, mixed>>}>
     */
    private function taggedTableCellsFromRow(string $dictionary, array $objects, array $roleMap, array $classMap, array $seen): array
    {
        $cells = [];
        foreach ($this->structElementChildObjectNumbers($dictionary, $objects, $seen) as $childObjectNumber) {
            if (!isset($objects[$childObjectNumber])) {
                continue;
            }

            $childBody = $objects[$childObjectNumber];
            if ($this->nameDictionaryValue($childBody, 'Type') !== 'StructElem') {
                continue;
            }

            $role = $this->nameDictionaryValue($childBody, 'S') ?? '';
            $resolvedRole = $role === '' ? '' : $this->resolveTaggedRole($role, $roleMap);
            $normalizedRole = strtoupper($resolvedRole);
            if (!in_array($normalizedRole, ['TH', 'TD'], true)) {
                if ($this->taggedTableRoleIsTransparentContainer($normalizedRole)) {
                    foreach ($this->taggedTableCellsFromRow($childBody, $objects, $roleMap, $classMap, $seen + [$childObjectNumber => true]) as $cell) {
                        $cells[] = $cell;
                    }
                }
                continue;
            }

            $cells[] = $this->taggedTableCellFromStructElement($childBody, $objects, $roleMap, $classMap);
        }

        return $cells;
    }

    /**
     * @param array<int, string> $objects
     * @param array<string, string> $roleMap
     * @param array<string, list<array<string, mixed>>> $classMap
     * @return array{role: string, resolvedRole: string, text: string, rowSpan: int, colSpan: int, attributes: list<array<string, mixed>>, id?: string}
     */
    private function taggedTableCellFromStructElement(string $dictionary, array $objects, array $roleMap, array $classMap): array
    {
        $role = $this->nameDictionaryValue($dictionary, 'S') ?? '';
        $resolvedRole = $role === '' ? '' : $this->resolveTaggedRole($role, $roleMap);
        $attributes = $this->taggedAttributesFromStructElement($dictionary, $objects, $classMap);

        $cell = [
            'role' => $role,
            'resolvedRole' => $resolvedRole,
            'text' => $this->structElementSemanticText($dictionary, $objects),
            'rowSpan' => $this->taggedTableSpanFromAttributes($attributes, 'RowSpan'),
            'colSpan' => $this->taggedTableSpanFromAttributes($attributes, 'ColSpan'),
            'attributes' => $attributes,
        ];
        $identifier = $this->structElementIdentifier($dictionary, $objects);
        if ($identifier !== '') {
            $cell['id'] = $identifier;
        }

        return $cell;
    }

    private function structElementIdentifier(string $dictionary, array $objects): string
    {
        $identifier = $this->textStringFromDictionaryKey($dictionary, 'ID', $objects);
        if (is_string($identifier) && trim($identifier) !== '') {
            return trim($identifier);
        }

        $identifier = $this->nameDictionaryValue($dictionary, 'ID');
        if (is_string($identifier) && trim($identifier) !== '') {
            return trim($identifier);
        }

        return '';
    }

    /**
     * @param list<array<string, mixed>> $attributes
     */
    private function taggedTableSpanFromAttributes(array $attributes, string $name): int
    {
        $span = 1;
        foreach ($attributes as $attributeDictionary) {
            $value = $attributeDictionary[$name] ?? null;
            if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
                $span = max($span, (int) $value);
            }
        }

        return max(1, $span);
    }

    /**
     * @param list<list<array{role: string, resolvedRole: string, text: string, rowSpan: int, colSpan: int, attributes: list<array<string, mixed>>}>> $rows
     */
    private function taggedTableRowsHaveText(array $rows): bool
    {
        foreach ($rows as $row) {
            foreach ($row as $cell) {
                if (trim($cell['text']) !== '') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<int, string> $objects
     * @return list<string>
     */
    private function classNamesFromDictionary(string $dictionary, array $objects): array
    {
        $tokens = $this->dictionaryTokens($dictionary);
        $classes = [];
        for ($index = 0, $count = count($tokens); $index < $count; $index++) {
            if (!$this->isPdfNameToken($tokens[$index], 'C')) {
                continue;
            }

            $classIndex = $index + 1;
            $classes = array_merge($classes, $this->classNamesFromTokens($tokens, $classIndex, $objects));
            $index = max($index, $classIndex - 1);
        }

        $classes = array_values(array_unique(array_filter($classes, static fn (string $className): bool => $className !== '')));
        sort($classes);

        return $classes;
    }

    /**
     * @param list<string> $tokens
     * @param array<int, string> $objects
     * @return list<string>
     */
    private function classNamesFromTokens(array $tokens, int &$index, array $objects): array
    {
        $objectNumber = $this->indirectObjectOperand($tokens, $index);
        if ($objectNumber !== null) {
            $index += 3;
            return isset($objects[$objectNumber]) ? $this->classNamesFromValue($objects[$objectNumber], $objects) : [];
        }

        $token = $tokens[$index] ?? null;
        if (!is_string($token)) {
            return [];
        }
        $index++;

        return $this->classNamesFromValue($token, $objects);
    }

    /**
     * @param array<int, string> $objects
     * @return list<string>
     */
    private function classNamesFromValue(string $value, array $objects): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        if (str_starts_with($value, '/')) {
            return [$this->decodePdfName(substr($value, 1))];
        }

        if (str_starts_with($value, '[')) {
            $classes = [];
            $tokens = $this->arrayTokens($value);
            for ($index = 0, $count = count($tokens); $index < $count;) {
                $before = $index;
                $classes = array_merge($classes, $this->classNamesFromTokens($tokens, $index, $objects));
                if ($index === $before) {
                    $index++;
                }
            }

            return $classes;
        }

        return [];
    }

    /**
     * @param array<int, string> $objects
     * @return list<array<string, mixed>>
     */
    private function attributeDictionariesFromDictionaryKey(string $dictionary, string $name, array $objects): array
    {
        $tokens = $this->dictionaryTokens($dictionary);
        $attributes = [];
        for ($index = 0, $count = count($tokens); $index < $count; $index++) {
            if (!$this->isPdfNameToken($tokens[$index], $name)) {
                continue;
            }

            $attributeIndex = $index + 1;
            $attributes = array_merge($attributes, $this->attributeDictionariesFromTokens($tokens, $attributeIndex, $objects));
            $index = max($index, $attributeIndex - 1);
        }

        return $this->uniqueAttributeDictionaries($attributes);
    }

    /**
     * @param list<string> $tokens
     * @param array<int, string> $objects
     * @return list<array<string, mixed>>
     */
    private function attributeDictionariesFromTokens(array $tokens, int &$index, array $objects, array $seen = []): array
    {
        $objectNumber = $this->indirectObjectOperand($tokens, $index);
        if ($objectNumber !== null) {
            $index += 3;
            if (isset($seen[$objectNumber]) || !isset($objects[$objectNumber])) {
                return [];
            }

            return $this->attributeDictionariesFromValue($objects[$objectNumber], $objects, $seen + [$objectNumber => true]);
        }

        $token = $tokens[$index] ?? null;
        if (!is_string($token)) {
            return [];
        }
        $index++;

        return $this->attributeDictionariesFromValue($token, $objects, $seen);
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     * @return list<array<string, mixed>>
     */
    private function attributeDictionariesFromValue(string $value, array $objects, array $seen = []): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        if (str_starts_with($value, '[')) {
            $attributes = [];
            $tokens = $this->arrayTokens($value);
            for ($index = 0, $count = count($tokens); $index < $count;) {
                $before = $index;
                $attributes = array_merge($attributes, $this->attributeDictionariesFromTokens($tokens, $index, $objects, $seen));
                if ($index === $before) {
                    $index++;
                }
            }

            return $this->uniqueAttributeDictionaries($attributes);
        }

        $dictionary = $this->dictionaryFromValue($value);
        if ($dictionary === null) {
            return [];
        }

        $attributeDictionary = $this->semanticDictionaryFromPdfDictionary($dictionary, $objects, $seen);
        return $attributeDictionary === [] ? [] : [$attributeDictionary];
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     * @return array<string, mixed>
     */
    private function semanticDictionaryFromPdfDictionary(string $dictionary, array $objects, array $seen = []): array
    {
        $tokens = $this->dictionaryTokens($dictionary);
        $values = [];
        for ($index = 0, $count = count($tokens); $index < $count;) {
            $keyToken = $tokens[$index] ?? null;
            if (!is_string($keyToken) || !str_starts_with($keyToken, '/')) {
                $index++;
                continue;
            }

            $key = $this->decodePdfName(substr($keyToken, 1));
            $index++;
            $values[$key] = $this->semanticValueFromTokens($tokens, $index, $objects, $seen);
        }

        ksort($values);

        return $values;
    }

    /**
     * @param list<string> $tokens
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     */
    private function semanticValueFromTokens(array $tokens, int &$index, array $objects, array $seen): mixed
    {
        $objectNumber = $this->indirectObjectOperand($tokens, $index);
        if ($objectNumber !== null) {
            $index += 3;
            if (isset($seen[$objectNumber]) || !isset($objects[$objectNumber])) {
                return null;
            }

            return $this->semanticValueFromObjectBody($objects[$objectNumber], $objects, $seen + [$objectNumber => true]);
        }

        $token = $tokens[$index] ?? null;
        if (!is_string($token)) {
            return null;
        }
        $index++;

        return $this->semanticValueFromToken($token, $objects, $seen);
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     */
    private function semanticValueFromObjectBody(string $body, array $objects, array $seen): mixed
    {
        $body = trim($body);
        if ($body === '') {
            return '';
        }

        return $this->semanticValueFromToken($body, $objects, $seen);
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     */
    private function semanticValueFromToken(string $token, array $objects, array $seen): mixed
    {
        $token = trim($token);
        if ($token === '') {
            return '';
        }

        if (str_starts_with($token, '<<')) {
            return $this->semanticDictionaryFromPdfDictionary($token, $objects, $seen);
        }

        if (str_starts_with($token, '[')) {
            $values = [];
            $tokens = $this->arrayTokens($token);
            for ($index = 0, $count = count($tokens); $index < $count;) {
                $values[] = $this->semanticValueFromTokens($tokens, $index, $objects, $seen);
            }

            return $values;
        }

        if (str_starts_with($token, '/')) {
            return $this->decodePdfName(substr($token, 1));
        }

        if (str_starts_with($token, '(') || (str_starts_with($token, '<') && !str_starts_with($token, '<<'))) {
            return $this->textStringFromToken($token);
        }

        if ($token === 'true' || $token === 'false') {
            return $token === 'true';
        }

        if ($token === 'null') {
            return null;
        }

        if (preg_match('/^[+-]?\d+$/', $token) === 1) {
            return (int) $token;
        }

        if (preg_match('/^[+-]?(?:\d+\.\d*|\.\d+)$/', $token) === 1) {
            return (float) $token;
        }

        return $token;
    }

    /**
     * @param list<array<string, mixed>> $attributes
     * @return list<array<string, mixed>>
     */
    private function uniqueAttributeDictionaries(array $attributes): array
    {
        $unique = [];
        foreach ($attributes as $attributeDictionary) {
            if ($attributeDictionary === []) {
                continue;
            }
            $unique[json_encode($attributeDictionary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)] = $attributeDictionary;
        }

        return array_values($unique);
    }

    /**
     * @param array<string, string> $roleMap
     */
    private function resolveTaggedRole(string $role, array $roleMap): string
    {
        $resolved = $role;
        $seen = [];
        while (isset($roleMap[$resolved]) && !isset($seen[$resolved])) {
            $seen[$resolved] = true;
            $resolved = $roleMap[$resolved];
        }

        return $resolved;
    }

    /**
     * @param array<int, string> $objects
     */
    private function languageFromDictionary(string $dictionary, array $objects): ?string
    {
        $language = $this->textStringFromDictionaryKey($dictionary, 'Lang', $objects);
        if ($language === null) {
            $token = $this->dictionaryValueToken($dictionary, 'Lang');
            if (is_string($token) && str_starts_with($token, '/')) {
                $language = $this->decodePdfName(substr($token, 1));
            }
        }

        if ($language === null) {
            return null;
        }

        $language = trim($language);
        return $language === '' ? null : $language;
    }

    /**
     * @return list<string>
     */
    public function extractTextLines(string $pdfBytes): array
    {
        if ($this->isEncrypted($pdfBytes) && $this->pdfPassword() === '') {
            return [];
        }

        $lines = [];
        foreach ($this->limitedStreamContexts($pdfBytes) as $context) {
            foreach ($this->textLinesFromContentStream($context['stream'], $context['fontToUnicodeMaps'], $context['fontEncodings'], $context['propertyActualTexts'], $context['mcidActualTexts'], $context['propertyMcids']) as $line) {
                if ($line !== '') {
                    $lines[] = $line;
                }
            }
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function extractPageTexts(string $pdfBytes): array
    {
        if ($this->isEncrypted($pdfBytes) && $this->pdfPassword() === '') {
            return [];
        }

        $pages = [];
        foreach ($this->limitedStreamContexts($pdfBytes) as $context) {
            $pages[] = implode("\n", $this->textLinesFromContentStream($context['stream'], $context['fontToUnicodeMaps'], $context['fontEncodings'], $context['propertyActualTexts'], $context['mcidActualTexts'], $context['propertyMcids']));
        }

        return $pages;
    }

    /**
     * Reuse MarkerAppPreview's mature page-label number-tree implementation
     * without calling its public page inventory, which deliberately asks this
     * class for text-extractor labels as its first source.
     *
     * @return list<string>
     */
    private function previewPageLabelsWithoutTextFallback(string $pdfBytes): array
    {
        $preview = new MarkerAppPreview();
        $call = static function (string $method, array $args) use ($preview): mixed {
            $reflection = new \ReflectionMethod($preview, $method);

            return $reflection->invokeArgs($preview, $args);
        };

        /** @var array<int, array{generation: int, body: string}> $objects */
        $objects = $call('pdfObjects', [$pdfBytes]);
        if ($objects === []) {
            return [];
        }

        $catalogBody = null;
        $pages = [];
        $rootCatalog = $call('catalogFromTrailerRoot', [$pdfBytes, $objects]);
        if (is_array($rootCatalog)) {
            $catalogBody = is_string($rootCatalog['body'] ?? null) ? $rootCatalog['body'] : null;
            $pagesId = $catalogBody !== null ? $call('reference', [$catalogBody, 'Pages']) : null;
            if (is_int($pagesId) && isset($objects[$pagesId])) {
                $pages = $call('uniquePagesByObjectId', [$call('collectPages', [$pagesId, $objects])]);
            }
        }

        if ($pages === [] && $rootCatalog === null) {
            foreach ($objects as $object) {
                $body = is_string($object['body'] ?? null) ? $object['body'] : '';
                if ($call('objectType', [$body]) !== 'Catalog') {
                    continue;
                }

                $catalogBody = $body;
                $pagesId = $call('reference', [$body, 'Pages']);
                if (is_int($pagesId) && isset($objects[$pagesId])) {
                    $pages = $call('uniquePagesByObjectId', [$call('collectPages', [$pagesId, $objects])]);
                    break;
                }
            }
        }

        if ($pages === []) {
            foreach ($objects as $objectId => $object) {
                $body = is_string($object['body'] ?? null) ? $object['body'] : '';
                if ($call('objectType', [$body]) === 'Page') {
                    $pages[] = ['object_id' => $objectId];
                }
            }
        }

        /** @var list<string> $labels */
        $labels = $call('pageLabelsFromCatalog', [$catalogBody, $objects, count($pages)]);

        return $labels;
    }

    /**
     * @return list<array{
     *     stream: string,
     *     fontToUnicodeMaps: array<string, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}>,
     *     fontEncodings: array<string, array{base: string, differences: array<int, string>, suppressUnmapped: bool}>,
     *     propertyActualTexts: array<string, string>,
     *     mcidActualTexts: array<int, string>,
     *     propertyMcids: array<string, int>,
     *     page?: int,
     *     pageObject?: int
     * }>
     */
    private function limitedStreamContexts(string $pdfBytes): array
    {
        $contexts = $this->streamContexts($pdfBytes);
        $maxPages = $this->maxPages();
        if ($maxPages === null || $contexts === []) {
            return $contexts;
        }

        $limited = [];
        $seenPages = [];
        foreach ($contexts as $index => $context) {
            $page = is_int($context['page'] ?? null) ? $context['page'] : $index + 1;
            if (!isset($seenPages[$page]) && count($seenPages) >= $maxPages) {
                continue;
            }
            $seenPages[$page] = true;
            $limited[] = $context;
        }

        return $limited;
    }

    private function maxPages(): ?int
    {
        foreach (['pdfMaxPages', 'maxPages', 'max_pages'] as $key) {
            if (!array_key_exists($key, $this->options) || $this->options[$key] === null || $this->options[$key] === '') {
                continue;
            }
            $value = (int) $this->options[$key];

            return $value > 0 ? $value : null;
        }

        return null;
    }

    /**
     * @return list<array{
     *     stream: string,
     *     fontToUnicodeMaps: array<string, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}>,
     *     fontEncodings: array<string, array{base: string, differences: array<int, string>, suppressUnmapped: bool}>,
     *     propertyActualTexts: array<string, string>,
     *     mcidActualTexts: array<int, string>,
     *     propertyMcids: array<string, int>,
     *     page?: int,
     *     pageObject?: int
     * }>
     */
    private function streamContexts(string $pdfBytes): array
    {
        $objects = $this->pdfObjects($pdfBytes);
        $pageContexts = $this->pageContentStreamContexts($objects, $pdfBytes);
        if ($pageContexts !== []) {
            return $pageContexts;
        }

        $contexts = [];
        $fontToUnicodeMaps = $this->fontToUnicodeMapsForResourceContext($pdfBytes, $objects, true);
        $fontEncodings = $this->fontEncodingsForResourceContext($pdfBytes, $objects, true);
        $propertyActualTexts = $this->propertyActualTextsFromContext($pdfBytes, $objects);
        $propertyMcids = $this->propertyMcidsFromContext($pdfBytes, $objects);
        $fallbackHiddenStreamObjects = $this->fallbackHiddenStreamObjectNumbers($objects);
        foreach ($objects as $objectNumber => $objectBody) {
            $streamParts = $this->streamObjectParts($objectBody);
            if (
                $streamParts === null
                || isset($fallbackHiddenStreamObjects[(int) $objectNumber])
                || !$this->fallbackStreamObjectIsVisibleContentCandidate($streamParts['dictionary'])
            ) {
                continue;
            }

            $decoded = $this->decodeStream($streamParts['dictionary'], $streamParts['stream'], $objects);
            if ($decoded === null) {
                continue;
            }
            $contexts[] = [
                'stream' => $decoded,
                'fontToUnicodeMaps' => $fontToUnicodeMaps,
                'fontEncodings' => $fontEncodings,
                'propertyActualTexts' => $propertyActualTexts,
                'mcidActualTexts' => [],
                'propertyMcids' => $propertyMcids,
            ];
        }

        return $contexts;
    }

    /**
     * @param array<int, string> $objects
     * @return array<int, true>
     */
    private function fallbackHiddenStreamObjectNumbers(array $objects): array
    {
        $hidden = [];
        foreach ($objects as $body) {
            foreach (['Metadata', 'Private'] as $name) {
                if (preg_match_all('/\/' . $name . '\s+(\d+)\s+\d+\s+R\b/s', $body, $matches)) {
                    foreach ($matches[1] as $objectNumber) {
                        $hidden[(int) $objectNumber] = true;
                    }
                }
            }

            foreach ($this->fallbackEmbeddedFileReferences($body) as $objectNumber) {
                $hidden[$objectNumber] = true;
            }
        }

        return $hidden;
    }

    /**
     * @return list<int>
     */
    private function fallbackEmbeddedFileReferences(string $body): array
    {
        $references = [];
        if (preg_match_all('/\/EF\b(.*?)(?=\/[A-Za-z0-9_.#-]+\b|>>|\bendobj\b)/s', $body, $matches)) {
            foreach ($matches[1] as $efOperand) {
                if (preg_match_all('/(\d+)\s+\d+\s+R\b/', $efOperand, $referenceMatches)) {
                    foreach ($referenceMatches[1] as $objectNumber) {
                        $references[] = (int) $objectNumber;
                    }
                }
            }
        }

        return array_values(array_unique($references));
    }

    private function fallbackStreamObjectIsVisibleContentCandidate(string $dictionary): bool
    {
        if (preg_match('/\/Type\s*\/(?:XRef|ObjStm|Metadata|EmbeddedFile|Filespec|Font|FontDescriptor|CMap)\b/s', $dictionary) === 1) {
            return false;
        }

        if (preg_match('/\/Subtype\s*\/(?:Image|XML|CIDFontType0C|CIDFontType2|Type1C|OpenType|TrueType|Form)\b/s', $dictionary) === 1) {
            return false;
        }

        return true;
    }

    /**
     * @param array<int, string> $objects
     */
    private function resourceOwnersForObject(int $objectNumber, array $objects): string
    {
        $needle = (string) $objectNumber . ' 0 R';
        $owners = '';
        foreach ($objects as $body) {
            if (str_contains($body, $needle)) {
                $owners .= "\n" . $body;
            }
        }

        return $owners;
    }

    private function objectBodyLooksLikeCMap(string $body): bool
    {
        return preg_match('/\/Type\s*\/CMap\b|begincmap\b|beginbfchar\b|beginbfrange\b|\/CMapName\b/s', $body) === 1;
    }

    /**
     * @return list<string>
     */
    private function streams(string $pdfBytes): array
    {
        $streams = [];
        foreach ($this->streamContexts($pdfBytes) as $context) {
            $streams[] = $context['stream'];
        }

        return $streams;
    }

    /**
     * @param array<int, string> $objects
     * @return list<array{
     *     stream: string,
     *     fontToUnicodeMaps: array<string, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}>,
     *     fontEncodings: array<string, array{base: string, differences: array<int, string>, suppressUnmapped: bool}>,
     *     propertyActualTexts: array<string, string>,
     *     mcidActualTexts: array<int, string>,
     *     propertyMcids: array<string, int>,
     *     page: int,
     *     pageObject: int
     * }>
     */
    private function pageContentStreamContexts(array $objects, ?string $pdfBytes = null): array
    {
        $contexts = [];
        $pageNumber = 0;
        foreach ($this->limitedPageObjectNumbers($objects, $pdfBytes) as $objectNumber) {
            $pageNumber++;
            $body = $objects[$objectNumber] ?? null;
            if (!is_string($body) || !$this->isPageObjectBody($body)) {
                continue;
            }

            $resourceContext = $this->resourceContextForPage($objectNumber, $objects);
            $fontToUnicodeMaps = $this->fontToUnicodeMapsForResourceContext($resourceContext, $objects, false);
            $fontEncodings = $this->fontEncodingsForResourceContext($resourceContext, $objects, false);
            $propertyActualTexts = $this->propertyActualTextsFromContext($resourceContext, $objects);
            $propertyMcids = $this->propertyMcidsFromContext($resourceContext, $objects);
            $mcidActualTexts = $this->mcidActualTextsForPage($body, $objects, $objectNumber);
            $xObjects = $this->xObjectResourceObjectNumbers($resourceContext, $objects);

            foreach ($this->pageContentsReferences($body) as $contentsObjectNumber) {
                foreach ($this->resolveContentObjectNumbers($contentsObjectNumber, $objects) as $contentObjectNumber) {
                    if (!isset($objects[$contentObjectNumber])) {
                        continue;
                    }

                    $decoded = $this->decodeStreamObject($objects[$contentObjectNumber], $objects);
                    if ($decoded !== null) {
                        $streamFontToUnicodeMaps = $fontToUnicodeMaps;
                        $streamFontEncodings = $fontEncodings;
                        $streamPropertyActualTexts = $propertyActualTexts;
                        $streamPropertyMcids = $propertyMcids;
                        $streamMcidActualTexts = $mcidActualTexts;
                        $contexts[] = [
                            'stream' => $this->expandContentStreamWithFormXObjects(
                                $decoded,
                                $objects,
                                $xObjects,
                                $streamFontToUnicodeMaps,
                                $streamFontEncodings,
                                $streamPropertyActualTexts,
                                $streamMcidActualTexts,
                                $streamPropertyMcids
                            ),
                            'fontToUnicodeMaps' => $streamFontToUnicodeMaps,
                            'fontEncodings' => $streamFontEncodings,
                            'propertyActualTexts' => $streamPropertyActualTexts,
                            'mcidActualTexts' => $streamMcidActualTexts,
                            'propertyMcids' => $streamPropertyMcids,
                            'page' => $pageNumber,
                            'pageObject' => $objectNumber,
                        ];
                    }
                }
            }
        }

        return $contexts;
    }

    /**
     * @param array<int, string> $objects
     * @return list<int>
     */
    private function pageObjectNumbers(array $objects, ?string $pdfBytes = null): array
    {
        $pageObjectNumbers = [];
        foreach ($this->catalogPagesRootObjectNumbers($objects, $pdfBytes) as $pagesObjectNumber) {
            foreach ($this->pageObjectNumbersFromTree($pagesObjectNumber, $objects) as $pageObjectNumber) {
                $pageObjectNumbers[] = $pageObjectNumber;
            }
        }

        $pageObjectNumbers = array_values(array_unique($pageObjectNumbers));
        return $pageObjectNumbers === [] ? $this->allPageObjectNumbers($objects) : $pageObjectNumbers;
    }

    /**
     * @param array<int, string> $objects
     * @return list<int>
     */
    private function limitedPageObjectNumbers(array $objects, ?string $pdfBytes = null): array
    {
        $pageObjectNumbers = $this->pageObjectNumbers($objects, $pdfBytes);
        $maxPages = $this->maxPages();
        if ($maxPages === null) {
            return $pageObjectNumbers;
        }

        return array_slice($pageObjectNumbers, 0, $maxPages);
    }

    /**
     * @param array<int, string> $objects
     * @return list<int>
     */
    private function catalogPagesRootObjectNumbers(array $objects, ?string $pdfBytes = null): array
    {
        $trailerRootObjectNumber = $pdfBytes === null ? null : $this->xrefTrailerRootObjectNumber($pdfBytes);
        if ($trailerRootObjectNumber !== null && isset($objects[$trailerRootObjectNumber])) {
            $body = $objects[$trailerRootObjectNumber];
            if (preg_match('/\/Type\s*\/Catalog\b/', $body) === 1
                && preg_match('/\/Pages\s+(\d+)\s+\d+\s+R\b/', $body, $match) === 1
            ) {
                return [(int) $match[1]];
            }
        }

        $roots = [];
        foreach ($objects as $body) {
            if (preg_match('/\/Type\s*\/Catalog\b/', $body) !== 1) {
                continue;
            }
            if (preg_match('/\/Pages\s+(\d+)\s+\d+\s+R\b/', $body, $match) === 1) {
                $roots[] = (int) $match[1];
            }
        }

        return array_values(array_unique($roots));
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     * @return list<int>
     */
    private function pageObjectNumbersFromTree(int $objectNumber, array $objects, array $seen = []): array
    {
        if (isset($seen[$objectNumber]) || !isset($objects[$objectNumber])) {
            return [];
        }
        $seen[$objectNumber] = true;
        $body = $objects[$objectNumber];

        if ($this->isPageObjectBody($body)) {
            return [$objectNumber];
        }

        $pages = [];
        foreach ($this->kidObjectNumbers($body) as $kidObjectNumber) {
            foreach ($this->pageObjectNumbersFromTree($kidObjectNumber, $objects, $seen) as $pageObjectNumber) {
                $pages[] = $pageObjectNumber;
            }
        }

        return $pages;
    }

    /**
     * @return list<int>
     */
    private function kidObjectNumbers(string $objectBody): array
    {
        if (preg_match('/\/Kids\s*\[(.*?)\]/s', $objectBody, $match) !== 1) {
            return [];
        }

        $kids = [];
        if (preg_match_all('/(\d+)\s+\d+\s+R\b/', $match[1], $matches)) {
            foreach ($matches[1] as $objectNumber) {
                $kids[] = (int) $objectNumber;
            }
        }

        return $kids;
    }

    /**
     * @param array<int, string> $objects
     * @return list<int>
     */
    private function allPageObjectNumbers(array $objects): array
    {
        $pages = [];
        foreach ($objects as $objectNumber => $body) {
            if ($this->isPageObjectBody($body)) {
                $pages[] = (int) $objectNumber;
            }
        }

        return $pages;
    }

    private function isPageObjectBody(string $objectBody): bool
    {
        return preg_match('/\/Type\s*\/Page\b/', $objectBody) === 1;
    }

    /**
     * @param array<int, string> $objects
     * @param array<string, int> $xObjects
     * @param array<string, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}> $fontToUnicodeMaps
     * @param array<string, array{base: string, differences: array<int, string>, suppressUnmapped: bool}> $fontEncodings
     * @param array<string, string> $propertyActualTexts
     * @param array<int, string> $mcidActualTexts
     * @param array<string, int> $propertyMcids
     * @param array<int, true> $seen
     */
    private function expandContentStreamWithFormXObjects(
        string $stream,
        array $objects,
        array $xObjects,
        array &$fontToUnicodeMaps,
        array &$fontEncodings,
        array &$propertyActualTexts,
        array &$mcidActualTexts,
        array &$propertyMcids,
        array $seen = []
    ): string
    {
        if ($xObjects === []) {
            return $stream;
        }

        $tokens = [];
        $operands = [];
        foreach ($this->contentTokens($stream) as $token) {
            $tokens[] = $token;

            if ($token === 'Do') {
                $name = $this->xObjectNameOperand($operands);
                if ($name !== null && isset($xObjects[$name])) {
                    $expanded = $this->expandedFormXObjectStream(
                        $xObjects[$name],
                        $objects,
                        $xObjects,
                        $fontToUnicodeMaps,
                        $fontEncodings,
                        $propertyActualTexts,
                        $mcidActualTexts,
                        $propertyMcids,
                        $seen
                    );
                    if ($expanded !== null && $expanded !== '') {
                        $tokens[] = $expanded;
                    }
                }

                $operands = [];
                continue;
            }

            if ($this->isOperator($token)) {
                $operands = [];
                continue;
            }

            $operands[] = $token;
        }

        return implode(' ', $tokens);
    }

    /**
     * @param array<int, string> $objects
     * @param array<string, int> $parentXObjects
     * @param array<string, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}> $fontToUnicodeMaps
     * @param array<string, array{base: string, differences: array<int, string>, suppressUnmapped: bool}> $fontEncodings
     * @param array<string, string> $propertyActualTexts
     * @param array<int, string> $mcidActualTexts
     * @param array<string, int> $propertyMcids
     * @param array<int, true> $seen
     */
    private function expandedFormXObjectStream(
        int $objectNumber,
        array $objects,
        array $parentXObjects,
        array &$fontToUnicodeMaps,
        array &$fontEncodings,
        array &$propertyActualTexts,
        array &$mcidActualTexts,
        array &$propertyMcids,
        array $seen
    ): ?string {
        if (isset($seen[$objectNumber]) || !isset($objects[$objectNumber])) {
            return null;
        }

        $objectBody = $objects[$objectNumber];
        if (!$this->isFormXObjectObject($objectBody)) {
            return null;
        }

        $decoded = $this->decodeStreamObject($objectBody, $objects);
        if ($decoded === null) {
            return null;
        }

        $seen[$objectNumber] = true;
        $resourceContext = $this->resourceContextForBody($objectBody, $objects);
        $formFontToUnicodeMaps = $this->fontToUnicodeMapsForResourceContext($resourceContext, $objects, false);
        $formFontEncodings = $this->fontEncodingsForResourceContext($resourceContext, $objects, false);
        $formPropertyActualTexts = $this->propertyActualTextsFromContext($resourceContext, $objects);
        $formPropertyMcids = $this->propertyMcidsFromContext($resourceContext, $objects);
        $formMcidActualTexts = $this->mcidActualTextsForPage($objectBody, $objects);
        $formMcidMap = $this->formMcidMap(
            array_merge(array_keys($formMcidActualTexts), array_values($formPropertyMcids)),
            $mcidActualTexts
        );
        $prefix = 'X' . $objectNumber . '_';
        $fontNameMap = $this->prefixedResourceNameMap(array_values(array_unique(array_merge(
            array_keys($formFontToUnicodeMaps),
            array_keys($formFontEncodings)
        ))), $prefix);
        $propertyNameMap = $this->prefixedResourceNameMap(array_values(array_unique(array_merge(
            array_keys($formPropertyActualTexts),
            array_keys($formPropertyMcids)
        ))), $prefix);

        foreach ($formFontToUnicodeMaps as $name => $map) {
            $fontToUnicodeMaps[$fontNameMap[$name] ?? $name] = $map;
        }
        foreach ($formFontEncodings as $name => $encoding) {
            $fontEncodings[$fontNameMap[$name] ?? $name] = $encoding;
        }
        foreach ($formPropertyActualTexts as $name => $actualText) {
            $propertyActualTexts[$propertyNameMap[$name] ?? $name] = $actualText;
        }
        foreach ($formMcidActualTexts as $mcid => $actualText) {
            $mcidActualTexts[$formMcidMap[$mcid] ?? $mcid] = $actualText;
        }
        foreach ($formPropertyMcids as $name => $mcid) {
            $propertyMcids[$propertyNameMap[$name] ?? $name] = $formMcidMap[$mcid] ?? $mcid;
        }

        $renamed = $this->renameContentStreamResources($decoded, $fontNameMap, $propertyNameMap, $formMcidMap);
        $formXObjects = $this->xObjectResourceObjectNumbers($resourceContext, $objects);

        return $this->expandContentStreamWithFormXObjects(
            $renamed,
            $objects,
            $formXObjects + $parentXObjects,
            $fontToUnicodeMaps,
            $fontEncodings,
            $propertyActualTexts,
            $mcidActualTexts,
            $propertyMcids,
            $seen
        );
    }

    /**
     * @param array<int, string> $objects
     */
    private function resourceContextForPage(int $pageObjectNumber, array $objects): string
    {
        $contexts = [];
        $seen = [];
        $objectNumber = $pageObjectNumber;

        while (isset($objects[$objectNumber]) && !isset($seen[$objectNumber])) {
            $seen[$objectNumber] = true;
            array_unshift($contexts, $this->resourceContextForBody($objects[$objectNumber], $objects));

            if (preg_match('/\/Parent\s+(\d+)\s+\d+\s+R\b/', $objects[$objectNumber], $match) !== 1) {
                break;
            }
            $objectNumber = (int) $match[1];
        }

        return implode("\n", $contexts);
    }

    /**
     * @param array<int, string> $objects
     */
    private function resourceContextForBody(string $objectBody, array $objects): string
    {
        $contexts = [$objectBody];
        if (preg_match_all('/\/Resources\s+(\d+)\s+\d+\s+R\b/', $objectBody, $matches)) {
            foreach ($matches[1] as $objectNumber) {
                if (isset($objects[(int) $objectNumber])) {
                    $contexts[] = $objects[(int) $objectNumber];
                }
            }
        }

        return implode("\n", $contexts);
    }

    /**
     * @param list<string> $names
     * @return array<string, string>
     */
    private function prefixedResourceNameMap(array $names, string $prefix): array
    {
        $map = [];
        foreach ($names as $name) {
            if ($name !== '') {
                $map[$name] = $prefix . $name;
            }
        }

        return $map;
    }

    /**
     * @param list<int> $formMcids
     * @param array<int, string> $existingMcidActualTexts
     * @return array<int, int>
     */
    private function formMcidMap(array $formMcids, array $existingMcidActualTexts): array
    {
        $formMcids = $this->uniqueIntegers($formMcids);
        if ($formMcids === []) {
            return [];
        }

        $nextMcid = $existingMcidActualTexts === [] ? 0 : max(array_keys($existingMcidActualTexts)) + 1;
        $map = [];
        foreach ($formMcids as $mcid) {
            $map[$mcid] = $nextMcid++;
        }

        return $map;
    }

    /**
     * @param array<string, string> $fontNameMap
     * @param array<string, string> $propertyNameMap
     * @param array<int, int> $mcidMap
     */
    private function renameContentStreamResources(string $stream, array $fontNameMap, array $propertyNameMap, array $mcidMap = []): string
    {
        if ($fontNameMap === [] && $propertyNameMap === [] && $mcidMap === []) {
            return $stream;
        }

        $tokens = [];
        $operandIndexes = [];
        foreach ($this->contentTokens($stream) as $token) {
            if ($mcidMap !== [] && str_starts_with(trim($token), '<<')) {
                $token = $this->renamedMcidDictionaryToken($token, $mcidMap);
            }

            $tokens[] = $token;

            if ($token === 'Tf') {
                if (count($operandIndexes) >= 2) {
                    $nameIndex = $operandIndexes[count($operandIndexes) - 2];
                    $tokens[$nameIndex] = $this->renamedNameToken($tokens[$nameIndex], $fontNameMap);
                }
                $operandIndexes = [];
                continue;
            }

            if ($token === 'BDC') {
                for ($index = count($operandIndexes) - 1; $index >= 0; $index--) {
                    $nameIndex = $operandIndexes[$index];
                    $renamed = $this->renamedNameToken($tokens[$nameIndex], $propertyNameMap);
                    if ($renamed !== $tokens[$nameIndex]) {
                        $tokens[$nameIndex] = $renamed;
                        break;
                    }
                }
                $operandIndexes = [];
                continue;
            }

            if ($this->isOperator($token)) {
                $operandIndexes = [];
                continue;
            }

            $operandIndexes[] = count($tokens) - 1;
        }

        return implode(' ', $tokens);
    }

    /**
     * @param array<int, int> $mcidMap
     */
    private function renamedMcidDictionaryToken(string $token, array $mcidMap): string
    {
        return preg_replace_callback(
            '/\/MCID\s+(\d+)\b/',
            static function (array $match) use ($mcidMap): string {
                $mcid = (int) $match[1];
                return '/MCID ' . ($mcidMap[$mcid] ?? $mcid);
            },
            $token
        ) ?? $token;
    }

    /**
     * @param array<string, string> $nameMap
     */
    private function renamedNameToken(string $token, array $nameMap): string
    {
        if (!str_starts_with($token, '/')) {
            return $token;
        }

        $name = $this->decodePdfName(substr($token, 1));
        if (!isset($nameMap[$name])) {
            return $token;
        }

        return '/' . $this->encodePdfName($nameMap[$name]);
    }

    private function encodePdfName(string $name): string
    {
        $encoded = '';
        foreach (str_split($name) as $char) {
            $ord = ord($char);
            $isSafe = ($ord >= 0x30 && $ord <= 0x39)
                || ($ord >= 0x41 && $ord <= 0x5A)
                || ($ord >= 0x61 && $ord <= 0x7A)
                || in_array($char, ['_', '.', '-'], true);
            $encoded .= $isSafe ? $char : '#' . strtoupper(str_pad(dechex($ord), 2, '0', STR_PAD_LEFT));
        }

        return $encoded;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, int>
     */
    private function xObjectResourceObjectNumbers(string $contextBody, array $objects): array
    {
        $xObjects = [];
        if (preg_match_all('/\/XObject\s*<<(.*?)>>/s', $contextBody, $dictionaryMatches)) {
            foreach ($dictionaryMatches[1] as $dictionary) {
                foreach ($this->xObjectResourceObjectNumbersFromDictionary($dictionary) as $name => $objectNumber) {
                    $xObjects[$name] = $objectNumber;
                }
            }
        }

        if (preg_match_all('/\/XObject\s+(\d+)\s+\d+\s+R\b/', $contextBody, $referenceMatches)) {
            foreach ($referenceMatches[1] as $objectNumber) {
                $referenced = $objects[(int) $objectNumber] ?? null;
                if (!is_string($referenced)) {
                    continue;
                }

                foreach ($this->xObjectResourceObjectNumbersFromDictionaryObject($referenced) as $name => $referencedObjectNumber) {
                    $xObjects[$name] = $referencedObjectNumber;
                }
            }
        }

        return $xObjects;
    }

    /**
     * @return array<string, int>
     */
    private function xObjectResourceObjectNumbersFromDictionaryObject(string $objectBody): array
    {
        if (preg_match('/<<(.*)>>/s', $objectBody, $match) !== 1) {
            return [];
        }

        return $this->xObjectResourceObjectNumbersFromDictionary($match[1]);
    }

    /**
     * @return array<string, int>
     */
    private function xObjectResourceObjectNumbersFromDictionary(string $dictionary): array
    {
        $xObjects = [];
        if (preg_match_all('/\/([A-Za-z0-9_.#-]+)\s+(\d+)\s+\d+\s+R\b/', $dictionary, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $xObjects[$this->decodePdfName($match[1])] = (int) $match[2];
            }
        }

        return $xObjects;
    }

    private function isFormXObjectObject(string $objectBody): bool
    {
        return preg_match('/\/Subtype\s*\/Form\b/', $objectBody) === 1;
    }

    /**
     * @param list<string> $operands
     */
    private function xObjectNameOperand(array $operands): ?string
    {
        $operand = end($operands);
        if (!is_string($operand) || !str_starts_with($operand, '/')) {
            return null;
        }

        return $this->decodePdfName(substr($operand, 1));
    }

    /**
     * @return array{ignoredXObjectSubtypes: list<string>, ignoredXObjectCount: int}
     */
    private function ignoredXObjectDiagnostics(string $pdfBytes): array
    {
        $objects = $this->pdfObjects($pdfBytes);
        $ignoredSubtypes = [];
        $ignoredCount = 0;

        foreach ($this->limitedPageObjectNumbers($objects) as $pageObjectNumber) {
            $body = $objects[$pageObjectNumber] ?? null;
            if (!is_string($body) || !$this->isPageObjectBody($body)) {
                continue;
            }

            $resourceContext = $this->resourceContextForPage($pageObjectNumber, $objects);
            $xObjects = $this->xObjectResourceObjectNumbers($resourceContext, $objects);
            if ($xObjects === []) {
                continue;
            }

            foreach ($this->pageContentsReferences($body) as $contentsObjectNumber) {
                foreach ($this->resolveContentObjectNumbers($contentsObjectNumber, $objects) as $contentObjectNumber) {
                    if (!isset($objects[$contentObjectNumber])) {
                        continue;
                    }

                    $decoded = $this->decodeStreamObject($objects[$contentObjectNumber], $objects);
                    if ($decoded === null) {
                        continue;
                    }

                    $diagnostics = $this->ignoredXObjectDiagnosticsFromContentStream($decoded, $xObjects, $objects);
                    $ignoredCount += $diagnostics['ignoredXObjectCount'];
                    foreach ($diagnostics['ignoredXObjectSubtypes'] as $subtype) {
                        $ignoredSubtypes[] = $subtype;
                    }
                }
            }
        }

        $ignoredSubtypes = array_values(array_unique($ignoredSubtypes));
        sort($ignoredSubtypes);

        return [
            'ignoredXObjectSubtypes' => $ignoredSubtypes,
            'ignoredXObjectCount' => $ignoredCount,
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return list<array{page: int, pageObject: int, contentReference: int, contentObject: int|null, reason: string, filters: list<string>, xObjectName?: string, xObjectObject?: int|null, xObjectSubtype?: string}>
     */
    private function pageExtractionIssues(array $objects): array
    {
        $issues = [];
        $page = 0;
        foreach ($this->limitedPageObjectNumbers($objects) as $pageObjectNumber) {
            $body = $objects[$pageObjectNumber] ?? null;
            if (!is_string($body) || !$this->isPageObjectBody($body)) {
                continue;
            }

            $page++;
            $resourceContext = $this->resourceContextForPage($pageObjectNumber, $objects);
            $xObjects = $this->xObjectResourceObjectNumbers($resourceContext, $objects);
            foreach ($this->pageContentsReferences($body) as $contentsObjectNumber) {
                $contentObjectNumbers = $this->resolveContentObjectNumbers($contentsObjectNumber, $objects);
                if ($contentObjectNumbers === []) {
                    $issues[] = [
                        'page' => $page,
                        'pageObject' => $pageObjectNumber,
                        'contentReference' => $contentsObjectNumber,
                        'contentObject' => null,
                        'reason' => 'unresolved_content_reference',
                        'filters' => [],
                    ];
                    continue;
                }

                foreach ($contentObjectNumbers as $contentObjectNumber) {
                    $objectBody = $objects[$contentObjectNumber] ?? null;
                    $stream = is_string($objectBody) ? $this->streamObjectParts($objectBody) : null;
                    if ($stream === null) {
                        $issues[] = [
                            'page' => $page,
                            'pageObject' => $pageObjectNumber,
                            'contentReference' => $contentsObjectNumber,
                            'contentObject' => $contentObjectNumber,
                            'reason' => 'unresolved_content_reference',
                            'filters' => [],
                        ];
                        continue;
                    }

                    $filters = $this->streamFilters($stream['dictionary'], $objects);
                    $unsupportedFilters = [];
                    foreach ($filters as $filter) {
                        if (!$this->isSupportedStreamFilter($filter)) {
                            $unsupportedFilters[] = $filter;
                        }
                    }

                    if ($unsupportedFilters !== []) {
                        $issues[] = [
                            'page' => $page,
                            'pageObject' => $pageObjectNumber,
                            'contentReference' => $contentsObjectNumber,
                            'contentObject' => $contentObjectNumber,
                            'reason' => 'unsupported_content_filter',
                            'filters' => array_values(array_unique($unsupportedFilters)),
                        ];
                        continue;
                    }

                    $decoded = $this->decodeStream($stream['dictionary'], $stream['stream'], $objects);
                    if ($decoded === null) {
                        $issues[] = [
                            'page' => $page,
                            'pageObject' => $pageObjectNumber,
                            'contentReference' => $contentsObjectNumber,
                            'contentObject' => $contentObjectNumber,
                            'reason' => 'failed_content_decode',
                            'filters' => $filters,
                        ];
                        continue;
                    }

                    foreach ($this->pageExtractionIssuesFromFormXObjects(
                        $decoded,
                        $xObjects,
                        $objects,
                        $page,
                        $pageObjectNumber,
                        $contentsObjectNumber,
                        $contentObjectNumber
                    ) as $issue) {
                        $issues[] = $issue;
                    }
                }
            }
        }

        return $issues;
    }

    /**
     * @param array<string, int> $xObjects
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     * @return list<array{page: int, pageObject: int, contentReference: int, contentObject: int|null, reason: string, filters: list<string>, xObjectName?: string, xObjectObject?: int|null, xObjectSubtype?: string}>
     */
    private function pageExtractionIssuesFromFormXObjects(
        string $stream,
        array $xObjects,
        array $objects,
        int $page,
        int $pageObjectNumber,
        int $contentReference,
        int $contentObjectNumber,
        array $seen = []
    ): array {
        $issues = [];
        $operands = [];
        foreach ($this->contentTokens($stream) as $token) {
            if ($token === 'Do') {
                $name = $this->xObjectNameOperand($operands);
                if ($name !== null && !array_key_exists($name, $xObjects)) {
                    $issues[] = [
                        'page' => $page,
                        'pageObject' => $pageObjectNumber,
                        'contentReference' => $contentReference,
                        'contentObject' => $contentObjectNumber,
                        'xObjectName' => $name,
                        'xObjectObject' => null,
                        'xObjectSubtype' => 'Unknown',
                        'reason' => 'unresolved_xobject_resource',
                        'filters' => [],
                    ];
                } elseif ($name !== null) {
                    foreach ($this->pageExtractionIssuesFromFormXObject(
                        $name,
                        $xObjects[$name],
                        $xObjects,
                        $objects,
                        $page,
                        $pageObjectNumber,
                        $contentReference,
                        $contentObjectNumber,
                        $seen
                    ) as $issue) {
                        $issues[] = $issue;
                    }
                }

                $operands = [];
                continue;
            }

            if ($this->isOperator($token)) {
                $operands = [];
                continue;
            }

            $operands[] = $token;
        }

        return $issues;
    }

    /**
     * @param array<string, int> $parentXObjects
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     * @return list<array{page: int, pageObject: int, contentReference: int, contentObject: int|null, reason: string, filters: list<string>, xObjectName?: string, xObjectObject?: int|null, xObjectSubtype?: string}>
     */
    private function pageExtractionIssuesFromFormXObject(
        string $name,
        int $objectNumber,
        array $parentXObjects,
        array $objects,
        int $page,
        int $pageObjectNumber,
        int $contentReference,
        int $contentObjectNumber,
        array $seen
    ): array {
        if (isset($seen[$objectNumber])) {
            return [];
        }

        $objectBody = $objects[$objectNumber] ?? null;
        if (!is_string($objectBody)) {
            return [
                [
                    'page' => $page,
                    'pageObject' => $pageObjectNumber,
                    'contentReference' => $contentReference,
                    'contentObject' => $contentObjectNumber,
                    'xObjectName' => $name,
                    'xObjectObject' => $objectNumber,
                    'xObjectSubtype' => 'Unknown',
                    'reason' => 'unresolved_xobject_reference',
                    'filters' => [],
                ],
            ];
        }

        if (!$this->isFormXObjectObject($objectBody)) {
            return [];
        }

        $issueBase = [
            'page' => $page,
            'pageObject' => $pageObjectNumber,
            'contentReference' => $contentReference,
            'contentObject' => $contentObjectNumber,
            'xObjectName' => $name,
            'xObjectObject' => $objectNumber,
            'xObjectSubtype' => $this->xObjectSubtype($objectBody) ?? 'Unknown',
        ];
        $stream = $this->streamObjectParts($objectBody);
        if ($stream === null) {
            return [
                $issueBase + [
                    'reason' => 'unresolved_form_xobject_stream',
                    'filters' => [],
                ],
            ];
        }

        $filters = $this->streamFilters($stream['dictionary'], $objects);
        $unsupportedFilters = [];
        foreach ($filters as $filter) {
            if (!$this->isSupportedStreamFilter($filter)) {
                $unsupportedFilters[] = $filter;
            }
        }

        if ($unsupportedFilters !== []) {
            return [
                $issueBase + [
                    'reason' => 'unsupported_form_xobject_filter',
                    'filters' => array_values(array_unique($unsupportedFilters)),
                ],
            ];
        }

        $decoded = $this->decodeStream($stream['dictionary'], $stream['stream'], $objects);
        if ($decoded === null) {
            return [
                $issueBase + [
                    'reason' => 'failed_form_xobject_decode',
                    'filters' => $filters,
                ],
            ];
        }

        $resourceContext = $this->resourceContextForBody($objectBody, $objects);
        $formXObjects = $this->xObjectResourceObjectNumbers($resourceContext, $objects);
        return $this->pageExtractionIssuesFromFormXObjects(
            $decoded,
            $formXObjects + $parentXObjects,
            $objects,
            $page,
            $pageObjectNumber,
            $contentReference,
            $contentObjectNumber,
            $seen + [$objectNumber => true]
        );
    }

    /**
     * @param array<string, int> $xObjects
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     * @return array{ignoredXObjectSubtypes: list<string>, ignoredXObjectCount: int}
     */
    private function ignoredXObjectDiagnosticsFromContentStream(string $stream, array $xObjects, array $objects, array $seen = []): array
    {
        $ignoredSubtypes = [];
        $ignoredCount = 0;
        $operands = [];

        foreach ($this->contentTokens($stream) as $token) {
            if ($token === 'Do') {
                $name = $this->xObjectNameOperand($operands);
                if ($name !== null && isset($xObjects[$name], $objects[$xObjects[$name]])) {
                    $objectNumber = $xObjects[$name];
                    $objectBody = $objects[$objectNumber];
                    if ($this->isFormXObjectObject($objectBody)) {
                        if (!isset($seen[$objectNumber])) {
                            $decoded = $this->decodeStreamObject($objectBody, $objects);
                            if ($decoded !== null) {
                                $resourceContext = $this->resourceContextForBody($objectBody, $objects);
                                $formXObjects = $this->xObjectResourceObjectNumbers($resourceContext, $objects);
                                $nested = $this->ignoredXObjectDiagnosticsFromContentStream($decoded, $formXObjects, $objects, $seen + [$objectNumber => true]);
                                $ignoredCount += $nested['ignoredXObjectCount'];
                                foreach ($nested['ignoredXObjectSubtypes'] as $subtype) {
                                    $ignoredSubtypes[] = $subtype;
                                }
                            }
                        }
                    } else {
                        $ignoredCount++;
                        $ignoredSubtypes[] = $this->xObjectSubtype($objectBody) ?? 'Unknown';
                    }
                }

                $operands = [];
                continue;
            }

            if ($this->isOperator($token)) {
                $operands = [];
                continue;
            }

            $operands[] = $token;
        }

        return [
            'ignoredXObjectSubtypes' => array_values(array_unique($ignoredSubtypes)),
            'ignoredXObjectCount' => $ignoredCount,
        ];
    }

    private function xObjectSubtype(string $objectBody): ?string
    {
        if (preg_match('/\/Subtype\s*\/([^\s\[\]<>\/%()]+)/', $objectBody, $match) !== 1) {
            return null;
        }

        return $this->decodePdfName($match[1]);
    }

    /**
     * @return list<int>
     */
    private function pageContentsReferences(string $pageObjectBody): array
    {
        $references = [];

        if (preg_match('/\/Contents\s*\[(.*?)\]/s', $pageObjectBody, $arrayMatch) === 1) {
            if (preg_match_all('/(\d+)\s+\d+\s+R\b/', $arrayMatch[1], $matches)) {
                foreach ($matches[1] as $objectNumber) {
                    $references[] = (int) $objectNumber;
                }
            }
        }

        if (preg_match_all('/\/Contents\s+(\d+)\s+\d+\s+R\b/', $pageObjectBody, $matches)) {
            foreach ($matches[1] as $objectNumber) {
                $references[] = (int) $objectNumber;
            }
        }

        return array_values(array_unique($references));
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     * @return list<int>
     */
    private function resolveContentObjectNumbers(int $objectNumber, array $objects, array $seen = []): array
    {
        if (isset($seen[$objectNumber]) || !isset($objects[$objectNumber])) {
            return [];
        }
        $seen[$objectNumber] = true;

        $body = $objects[$objectNumber];
        if ($this->streamObjectParts($body) !== null) {
            return [$objectNumber];
        }

        if (preg_match_all('/(\d+)\s+\d+\s+R\b/', $body, $matches) !== 1) {
            return [];
        }

        $contentObjectNumbers = [];
        foreach ($matches[1] as $nestedObjectNumber) {
            foreach ($this->resolveContentObjectNumbers((int) $nestedObjectNumber, $objects, $seen) as $contentObjectNumber) {
                $contentObjectNumbers[] = $contentObjectNumber;
            }
        }

        return $contentObjectNumbers;
    }

    /**
     * @param array<int, string> $objects
     */
    private function decodeStreamObject(string $objectBody, array $objects): ?string
    {
        $stream = $this->streamObjectParts($objectBody);
        if ($stream === null) {
            return null;
        }

        return $this->decodeStream($stream['dictionary'], $stream['stream'], $objects);
    }

    /**
     * @return array{dictionary: string, stream: string}|null
     */
    private function streamObjectParts(string $objectBody): ?array
    {
        $index = 0;
        $length = strlen($objectBody);
        while ($index < $length && ctype_space($objectBody[$index])) {
            $index++;
        }

        if ($index + 1 >= $length || $objectBody[$index] !== '<' || $objectBody[$index + 1] !== '<') {
            return null;
        }

        $dictionary = $this->readDictionaryToken($objectBody, $index);
        if ($dictionary === '' || !str_starts_with($dictionary, '<<') || !str_ends_with($dictionary, '>>')) {
            return null;
        }

        while ($index < $length && ctype_space($objectBody[$index])) {
            $index++;
        }
        if (substr($objectBody, $index, 6) !== 'stream') {
            return null;
        }
        $index += 6;

        if (substr($objectBody, $index, 2) === "\r\n") {
            $index += 2;
        } elseif ($index < $length && ($objectBody[$index] === "\n" || $objectBody[$index] === "\r")) {
            $index++;
        }

        $endOffset = strpos($objectBody, 'endstream', $index);
        if ($endOffset === false) {
            return null;
        }

        $stream = substr($objectBody, $index, $endOffset - $index);
        if (str_ends_with($stream, "\r\n")) {
            $stream = substr($stream, 0, -2);
        } elseif (str_ends_with($stream, "\n") || str_ends_with($stream, "\r")) {
            $stream = substr($stream, 0, -1);
        }

        return [
            'dictionary' => $dictionary,
            'stream' => $stream,
        ];
    }

    /**
     * @param array<int, string> $objects
     */
    private function decodeStream(string $dict, string $stream, array $objects = []): ?string
    {
        $decodeParms = $this->streamDecodeParms($dict, $objects);
        foreach ($this->streamFilters($dict, $objects) as $index => $filter) {
            $decoded = match ($filter) {
                'ASCIIHexDecode', 'AHx' => $this->decodeAsciiHexStream($stream),
                'ASCII85Decode', 'A85' => $this->decodeAscii85Stream($stream),
                'RunLengthDecode', 'RL' => $this->decodeRunLengthStream($stream),
                'FlateDecode', 'Fl' => $this->decodeFlateStream($stream),
                'LZWDecode', 'LZW' => $this->decodeLzwStream($stream, $decodeParms[$index] ?? $decodeParms[0] ?? []),
                'Crypt' => $this->decodeCryptStream($stream, $decodeParms[$index] ?? $decodeParms[0] ?? []),
                default => null,
            };

            if ($decoded === null) {
                return null;
            }
            if ($filter === 'FlateDecode' || $filter === 'Fl' || $filter === 'LZWDecode' || $filter === 'LZW') {
                $decoded = $this->applyStreamDecodeParms($decoded, $decodeParms[$index] ?? $decodeParms[0] ?? []);
                if ($decoded === null) {
                    return null;
                }
            }
            $stream = $decoded;
        }

        return $stream;
    }

    /**
     * @return list<string>
     * @param array<int, string> $objects
     */
    private function streamFilters(string $dict, array $objects = []): array
    {
        if (!preg_match('/\/Filter\s*(?:\[(.*?)\]|\/((?:#[0-9A-Fa-f]{2}|[^\s<>\[\]\(\)\/%])+)|(\d+)\s+\d+\s+R\b)/s', $dict, $match)) {
            return [];
        }

        if (($match[1] ?? '') !== '') {
            return $this->streamFiltersFromValue($match[1], $objects);
        }

        if (($match[3] ?? '') !== '') {
            return $this->streamFiltersFromValue($objects[(int) $match[3]] ?? '', $objects);
        }

        return isset($match[2]) ? [$this->decodePdfName($match[2])] : [];
    }

    /**
     * @return list<array<string, int|string>>
     * @param array<int, string> $objects
     */
    private function streamDecodeParms(string $dict, array $objects = []): array
    {
        if (!preg_match('/\/DecodeParms\s*(?:\[(.*?)\]|<<(.*?)>>|(\d+)\s+\d+\s+R\b)/s', $dict, $match)) {
            return [];
        }

        if (($match[1] ?? '') !== '') {
            return $this->streamDecodeParmsFromValue($match[1], $objects);
        }

        if (($match[3] ?? '') !== '') {
            return $this->streamDecodeParmsFromValue($objects[(int) $match[3]] ?? '', $objects);
        }

        return [$this->decodeParmsDictionary($match[2] ?? '')];
    }

    /**
     * @return list<string>
     * @param array<int, string> $objects
     */
    private function streamFiltersFromValue(string $value, array $objects): array
    {
        preg_match_all('/\/((?:#[0-9A-Fa-f]{2}|[^\s<>\[\]\(\)\/%])+)|(\d+)\s+\d+\s+R\b/s', $value, $matches, PREG_SET_ORDER);
        $filters = [];
        foreach ($matches as $match) {
            if (($match[1] ?? '') !== '') {
                $filters[] = $this->decodePdfName($match[1]);
                continue;
            }

            $referenced = $objects[(int) $match[2]] ?? '';
            foreach ($this->streamFiltersFromValue($referenced, $objects) as $filter) {
                $filters[] = $filter;
            }
        }

        return $filters;
    }

    private function isSupportedStreamFilter(string $filter): bool
    {
        return in_array($filter, self::SUPPORTED_STREAM_FILTERS, true);
    }

    /**
     * @param list<string> $filters
     */
    private function allStreamFiltersSupported(array $filters): bool
    {
        foreach ($filters as $filter) {
            if (!$this->isSupportedStreamFilter($filter)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<array<string, int|string>>
     * @param array<int, string> $objects
     */
    private function streamDecodeParmsFromValue(string $value, array $objects): array
    {
        preg_match_all('/\bnull\b|<<(.*?)>>|(\d+)\s+\d+\s+R\b/s', $value, $matches, PREG_SET_ORDER);
        $parms = [];
        foreach ($matches as $match) {
            if ($match[0] === 'null') {
                $parms[] = [];
                continue;
            }

            if (($match[1] ?? '') !== '') {
                $parms[] = $this->decodeParmsDictionary($match[1]);
                continue;
            }

            $referenced = $objects[(int) $match[2]] ?? '';
            foreach ($this->streamDecodeParmsFromValue($referenced, $objects) as $entry) {
                $parms[] = $entry;
            }
        }

        return $parms;
    }

    /**
     * @return array<string, int|string>
     */
    private function decodeParmsDictionary(string $dict): array
    {
        $values = [];
        if (preg_match_all('/\/(Predictor|Columns|Colors|BitsPerComponent|EarlyChange)\s+([+-]?\d+)/', $dict, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $values[$match[1]] = (int) $match[2];
            }
        }
        if (preg_match_all('/\/(Name)\s+\/((?:#[0-9A-Fa-f]{2}|[^\s<>\[\]\(\)\/%])+)/', $dict, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $values[$match[1]] = $this->decodePdfName($match[2]);
            }
        }

        return $values;
    }

    /**
     * @param array<string, int|string> $decodeParms
     */
    private function applyStreamDecodeParms(string $stream, array $decodeParms): ?string
    {
        $predictor = $decodeParms['Predictor'] ?? 1;
        $columns = $decodeParms['Columns'] ?? 1;
        $colors = $decodeParms['Colors'] ?? 1;
        $bitsPerComponent = $decodeParms['BitsPerComponent'] ?? 8;
        if (!is_int($predictor) || !is_int($columns) || !is_int($colors) || !is_int($bitsPerComponent)) {
            return null;
        }
        if ($predictor <= 1) {
            return $stream;
        }
        if ($columns <= 0 || $colors <= 0 || $bitsPerComponent !== 8) {
            return null;
        }
        if ($columns > intdiv($this->maxDecodedStreamBytes(), max(1, $colors))) {
            return null;
        }

        if ($predictor === 2) {
            return $this->decodeTiffPredictor($stream, $columns * $colors);
        }

        if ($predictor < 10 || $predictor > 15) {
            return null;
        }

        return $this->decodePngPredictor($stream, $columns * $colors);
    }

    private function decodeTiffPredictor(string $stream, int $rowLength): ?string
    {
        if ($rowLength <= 0 || $rowLength > $this->maxDecodedStreamBytes() || strlen($stream) % $rowLength !== 0) {
            return null;
        }

        $decoded = '';
        foreach (str_split($stream, $rowLength) as $row) {
            $out = '';
            for ($index = 0; $index < $rowLength; $index++) {
                $left = $index === 0 ? 0 : ord($out[$index - 1]);
                $out .= chr((ord($row[$index]) + $left) & 0xFF);
            }
            $decoded .= $out;
        }

        return $decoded;
    }

    private function decodePngPredictor(string $stream, int $rowLength): ?string
    {
        if ($rowLength <= 0 || $rowLength > $this->maxDecodedStreamBytes()) {
            return null;
        }

        $decoded = '';
        $previous = str_repeat("\0", $rowLength);
        $offset = 0;
        $length = strlen($stream);
        while ($offset < $length) {
            if ($offset + 1 + $rowLength > $length) {
                return null;
            }

            $filter = ord($stream[$offset]);
            $offset++;
            $row = substr($stream, $offset, $rowLength);
            $offset += $rowLength;
            $out = '';

            for ($index = 0; $index < $rowLength; $index++) {
                $raw = ord($row[$index]);
                $left = $index === 0 ? 0 : ord($out[$index - 1]);
                $up = ord($previous[$index]);
                $upperLeft = $index === 0 ? 0 : ord($previous[$index - 1]);
                $value = match ($filter) {
                    0 => $raw,
                    1 => $raw + $left,
                    2 => $raw + $up,
                    3 => $raw + intdiv($left + $up, 2),
                    4 => $raw + $this->paethPredictor($left, $up, $upperLeft),
                    default => null,
                };
                if ($value === null) {
                    return null;
                }
                $out .= chr($value & 0xFF);
            }

            $decoded .= $out;
            $previous = $out;
        }

        return $decoded;
    }

    private function paethPredictor(int $left, int $up, int $upperLeft): int
    {
        $estimate = $left + $up - $upperLeft;
        $leftDistance = abs($estimate - $left);
        $upDistance = abs($estimate - $up);
        $upperLeftDistance = abs($estimate - $upperLeft);

        if ($leftDistance <= $upDistance && $leftDistance <= $upperLeftDistance) {
            return $left;
        }
        if ($upDistance <= $upperLeftDistance) {
            return $up;
        }

        return $upperLeft;
    }

    private function decodeAsciiHexStream(string $stream): ?string
    {
        $body = strstr($stream, '>', true);
        if ($body === false) {
            $body = $stream;
        }

        $hex = preg_replace('/\s+/', '', $body);
        if ($hex === null || preg_match('/^[\da-fA-F]*$/', $hex) !== 1) {
            return null;
        }

        if (strlen($hex) % 2 === 1) {
            $hex .= '0';
        }

        $decoded = hex2bin($hex);
        return $decoded === false ? null : $decoded;
    }

    private function decodeAscii85Stream(string $stream): ?string
    {
        $body = strstr($stream, '~>', true);
        if ($body === false) {
            $body = $stream;
        }

        $body = preg_replace('/\s+/', '', $body);
        if ($body === null) {
            return null;
        }
        if (str_starts_with($body, '<~')) {
            $body = substr($body, 2);
        }

        $decoded = '';
        $digits = [];
        $length = strlen($body);
        for ($offset = 0; $offset < $length; $offset++) {
            $char = $body[$offset];
            if ($char === 'z') {
                if ($digits !== []) {
                    return null;
                }
                $decoded .= "\x00\x00\x00\x00";
                continue;
            }

            $value = ord($char) - 33;
            if ($value < 0 || $value > 84) {
                return null;
            }

            $digits[] = $value;
            if (count($digits) === 5) {
                $bytes = $this->ascii85DigitsToBytes($digits, 4);
                if ($bytes === null) {
                    return null;
                }
                $decoded .= $bytes;
                $digits = [];
            }
        }

        if ($digits !== []) {
            $partialCount = count($digits);
            while (count($digits) < 5) {
                $digits[] = 84;
            }
            $bytes = $this->ascii85DigitsToBytes($digits, $partialCount - 1);
            if ($bytes === null) {
                return null;
            }
            $decoded .= $bytes;
        }

        return $decoded;
    }

    /**
     * @param list<int> $digits
     */
    private function ascii85DigitsToBytes(array $digits, int $byteCount): ?string
    {
        $value = 0;
        foreach ($digits as $digit) {
            $value = ($value * 85) + $digit;
        }

        if ($value > 0xFFFFFFFF) {
            return null;
        }

        return substr(pack('N', $value), 0, $byteCount);
    }

    private function decodeFlateStream(string $stream): ?string
    {
        $maxLength = $this->maxDecodedStreamBytes();
        $inflated = @gzuncompress($stream, $maxLength);
        if ($inflated === false) {
            $inflated = @gzinflate($stream, $maxLength);
        }
        if ($inflated === false) {
            $inflated = @gzdecode($stream, $maxLength);
        }

        return $inflated === false ? null : $inflated;
    }

    private function maxDecodedStreamBytes(): int
    {
        foreach (['pdfMaxDecodedStreamBytes', 'maxDecodedStreamBytes'] as $key) {
            if (array_key_exists($key, $this->options) && $this->options[$key] !== null && $this->options[$key] !== '') {
                return max(1, (int) $this->options[$key]);
            }
        }

        return self::DEFAULT_MAX_DECODED_STREAM_BYTES;
    }

    /**
     * @param array<string, int|string> $decodeParms
     */
    private function decodeLzwStream(string $stream, array $decodeParms): ?string
    {
        $earlyChange = $decodeParms['EarlyChange'] ?? 1;
        if (!is_int($earlyChange)) {
            return null;
        }
        if ($earlyChange !== 0 && $earlyChange !== 1) {
            return null;
        }

        $resetDictionary = static function () use (&$dictionary, &$nextCode, &$codeSize): void {
            $dictionary = [];
            for ($code = 0; $code < 256; $code++) {
                $dictionary[$code] = chr($code);
            }
            $nextCode = 258;
            $codeSize = 9;
        };

        $resetDictionary();

        $decoded = '';
        $previous = null;
        $bitOffset = 0;
        while (($code = $this->readLzwCode($stream, $bitOffset, $codeSize)) !== null) {
            if ($code === 256) {
                $resetDictionary();
                $previous = null;
                continue;
            }

            if ($code === 257) {
                return $decoded;
            }

            if (isset($dictionary[$code])) {
                $entry = $dictionary[$code];
            } elseif ($previous !== null && $code === $nextCode) {
                $entry = $previous . $previous[0];
            } else {
                return null;
            }

            $decoded .= $entry;

            if ($previous !== null && $nextCode < 4096) {
                $dictionary[$nextCode] = $previous . $entry[0];
                $nextCode++;
                if ($codeSize < 12 && $nextCode + $earlyChange >= (1 << $codeSize)) {
                    $codeSize++;
                }
            }

            $previous = $entry;
        }

        return $decoded;
    }

    private function readLzwCode(string $stream, int &$bitOffset, int $codeSize): ?int
    {
        if ($codeSize <= 0 || $bitOffset + $codeSize > strlen($stream) * 8) {
            return null;
        }

        $code = 0;
        for ($bit = 0; $bit < $codeSize; $bit++, $bitOffset++) {
            $byte = ord($stream[intdiv($bitOffset, 8)]);
            $bitShift = 7 - ($bitOffset % 8);
            $code = ($code << 1) | (($byte >> $bitShift) & 1);
        }

        return $code;
    }

    /**
     * @param array<string, int|string> $decodeParms
     */
    private function decodeCryptStream(string $stream, array $decodeParms): ?string
    {
        $name = $decodeParms['Name'] ?? 'Identity';
        if (!is_string($name)) {
            return null;
        }

        return $name === 'Identity' ? $stream : null;
    }

    private function decodeRunLengthStream(string $stream): ?string
    {
        $decoded = '';
        $length = strlen($stream);
        $offset = 0;

        while ($offset < $length) {
            $lengthByte = ord($stream[$offset]);
            $offset++;

            if ($lengthByte === 128) {
                return $decoded;
            }

            if ($lengthByte <= 127) {
                $literalLength = $lengthByte + 1;
                if ($offset + $literalLength > $length) {
                    return null;
                }
                $decoded .= substr($stream, $offset, $literalLength);
                $offset += $literalLength;
                continue;
            }

            if ($offset >= $length) {
                return null;
            }
            $decoded .= str_repeat($stream[$offset], 257 - $lengthByte);
            $offset++;
        }

        return null;
    }

    /**
     * @return array<string, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}>
     */
    private function fontToUnicodeMaps(string $pdfBytes): array
    {
        $objects = $this->pdfObjects($pdfBytes);
        return $this->fontToUnicodeMapsForResourceContext($pdfBytes, $objects, true);
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}>
     */
    private function fontToUnicodeMapsForResourceContext(string $resourceContext, array $objects, bool $allowSingleFallback): array
    {
        $fontObjectMaps = $this->fontObjectToUnicodeMaps($objects);
        if ($fontObjectMaps === []) {
            return [];
        }

        $resourceMaps = [];
        foreach ($this->fontResourceDictionariesFromContext($resourceContext, $objects) as $fontResourceDictionary) {
            if (!preg_match_all('/\/([A-Za-z0-9_.#-]+)\s+(\d+)\s+\d+\s+R\b/', $fontResourceDictionary, $resourceMatches, PREG_SET_ORDER)) {
                continue;
            }

            foreach ($resourceMatches as $resourceMatch) {
                $fontObjectNumber = (int) $resourceMatch[2];
                if (isset($fontObjectMaps[$fontObjectNumber])) {
                    $resourceMaps[$this->decodePdfName($resourceMatch[1])] = $fontObjectMaps[$fontObjectNumber];
                }
            }
        }

        if ($resourceMaps !== []) {
            return $resourceMaps;
        }

        if ($allowSingleFallback && count($fontObjectMaps) === 1) {
            $onlyMap = reset($fontObjectMaps);
            return is_array($onlyMap) ? ['' => $onlyMap] : [];
        }

        return [];
    }

    /**
     * @param array<int, string> $objects
     * @return list<string>
     */
    private function fontResourceDictionariesFromContext(string $resourceContext, array $objects): array
    {
        $dictionaries = [];
        if (preg_match_all('/\/Font\s*<<(.*?)>>/s', $resourceContext, $fontMatches)) {
            foreach ($fontMatches[1] as $fontResourceDictionary) {
                $dictionaries[] = $fontResourceDictionary;
            }
        }

        if (preg_match_all('/\/Font\s+(\d+)\s+\d+\s+R\b/', $resourceContext, $fontReferenceMatches)) {
            foreach ($fontReferenceMatches[1] as $objectNumber) {
                $body = $objects[(int) $objectNumber] ?? null;
                if (!is_string($body)) {
                    continue;
                }
                $dictionaries[] = $body;
            }
        }

        return $dictionaries;
    }

    /**
     * @param array<int, string> $objects
     * @return array<int, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}>
     */
    private function fontObjectToUnicodeMaps(array $objects): array
    {
        $cacheKey = $this->objectsCacheKey($objects);
        if (array_key_exists($cacheKey, $this->fontObjectToUnicodeMapsCache)) {
            return $this->fontObjectToUnicodeMapsCache[$cacheKey];
        }

        $fontObjectMaps = [];

        foreach ($objects as $objectNumber => $body) {
            if (!$this->isFontObjectBody($body)) {
                continue;
            }

            $cmap = $this->toUnicodeMapForFontObject((int) $objectNumber, $objects);
            if ($cmap !== null && ($cmap['map'] !== [] || $cmap['codeSpaceRanges'] !== [])) {
                $fontObjectMaps[$objectNumber] = $cmap;
            }
        }

        if ($fontObjectMaps === []) {
            $this->fontObjectToUnicodeMapsCache[$cacheKey] = [];
            return [];
        }

        $this->fontObjectToUnicodeMapsCache[$cacheKey] = $fontObjectMaps;

        return $fontObjectMaps;
    }

    private function isFontObjectBody(string $objectBody): bool
    {
        return str_contains($objectBody, '/Type /Font') || str_contains($objectBody, '/Type/Font');
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     * @return array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}|null
     */
    private function toUnicodeMapForFontObject(int $objectNumber, array $objects, array $seen = []): ?array
    {
        if (isset($seen[$objectNumber]) || !isset($objects[$objectNumber])) {
            return null;
        }
        $seen[$objectNumber] = true;
        $objectBody = $objects[$objectNumber];

        if (preg_match('/\/ToUnicode\s+(\d+)\s+\d+\s+R\b/', $objectBody, $match) === 1) {
            $cmapObjectNumber = (int) $match[1];
            if (isset($objects[$cmapObjectNumber])) {
                $cmap = $this->toUnicodeMapFromObject($objects[$cmapObjectNumber], $objects);
                if ($cmap !== null && ($cmap['map'] !== [] || $cmap['codeSpaceRanges'] !== [])) {
                    return $this->withFontWritingMode($cmap, $objectBody, $objects);
                }
            }
        }

        foreach ($this->descendantFontObjectNumbers($objectBody, $objects) as $descendantObjectNumber) {
            $cmap = $this->toUnicodeMapForFontObject($descendantObjectNumber, $objects, $seen);
            if ($cmap !== null && ($cmap['map'] !== [] || $cmap['codeSpaceRanges'] !== [])) {
                return $this->withFontWritingMode($cmap, $objectBody, $objects);
            }
        }

        $encodingMap = $this->unicodeCidEncodingMapForFontObject($objectBody, $objects);
        if ($encodingMap !== null && ($encodingMap['map'] !== [] || $encodingMap['codeSpaceRanges'] !== [])) {
            return $this->withFontWritingMode($encodingMap, $objectBody, $objects);
        }

        $cffCidMap = $this->cidKeyedCffToUnicodeMapForFontObject($objectBody, $objects);
        if ($cffCidMap !== null && ($cffCidMap['map'] !== [] || $cffCidMap['codeSpaceRanges'] !== [])) {
            return $this->withFontWritingMode($cffCidMap, $objectBody, $objects);
        }

        return null;
    }

    /**
     * @param array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>, writingMode?: int} $cmap
     * @param array<int, string> $objects
     * @return array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>, writingMode: int}
     */
    private function withFontWritingMode(array $cmap, string $fontObjectBody, array $objects): array
    {
        if ($this->fontObjectWritingMode($fontObjectBody, $objects) === 1) {
            $cmap['writingMode'] = 1;
        } else {
            $cmap['writingMode'] = (int) ($cmap['writingMode'] ?? 0);
        }

        $cidCharacterMap = $this->cidCharacterMapForFontObject($fontObjectBody, $objects);
        if (($cidCharacterMap['map'] ?? []) !== []) {
            $cmap['sourceToCid'] = $cidCharacterMap['map'];
        }
        if (($cidCharacterMap['codeSpaceRanges'] ?? []) !== []) {
            $cmap['codeSpaceRanges'] = $this->mergeCMapCodeSpaceRanges(
                $cmap['codeSpaceRanges'] ?? [],
                $cidCharacterMap['codeSpaceRanges']
            );
        }

        $metrics = $this->cidWidthMetricsFromFontObject($fontObjectBody, $objects);
        if (($metrics['widths'] ?? []) !== []) {
            $cmap['cidWidths'] = $metrics['widths'];
        }
        if (array_key_exists('defaultWidth', $metrics)) {
            $cmap['cidDefaultWidth'] = $metrics['defaultWidth'];
        }

        return $cmap;
    }

    /**
     * @param array<int, string> $objects
     */
    private function fontObjectWritingMode(string $fontObjectBody, array $objects): ?int
    {
        $encoding = $this->cidEncodingNameFromFontObject($fontObjectBody, $objects);
        if ($encoding === null) {
            return null;
        }

        if ($encoding === 'Identity-V' || str_ends_with($encoding, '-V')) {
            return 1;
        }

        if ($encoding === 'Identity-H' || str_ends_with($encoding, '-H')) {
            return 0;
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     * @return list<int>
     */
    private function descendantFontObjectNumbers(string $objectBody, array $objects): array
    {
        $objectNumbers = [];

        if (preg_match_all('/\/DescendantFonts\s*\[(.*?)\]/s', $objectBody, $matches)) {
            foreach ($matches[1] as $arrayBody) {
                if (preg_match_all('/(\d+)\s+\d+\s+R\b/', $arrayBody, $refs)) {
                    foreach ($refs[1] as $objectNumber) {
                        $objectNumbers[] = (int) $objectNumber;
                    }
                }
            }
        }

        if (preg_match_all('/\/DescendantFonts\s+(\d+)\s+\d+\s+R\b/', $objectBody, $matches)) {
            foreach ($matches[1] as $arrayObjectNumber) {
                $arrayObject = trim($objects[(int) $arrayObjectNumber] ?? '');
                if (!str_starts_with($arrayObject, '[')) {
                    continue;
                }

                if (preg_match_all('/(\d+)\s+\d+\s+R\b/', $arrayObject, $refs)) {
                    foreach ($refs[1] as $objectNumber) {
                        $objectNumbers[] = (int) $objectNumber;
                    }
                }
            }
        }

        return array_values(array_unique($objectNumbers));
    }

    /**
     * @return array<string, array{base: string, differences: array<int, string>, suppressUnmapped: bool}>
     */
    private function fontEncodings(string $pdfBytes): array
    {
        $objects = $this->pdfObjects($pdfBytes);
        return $this->fontEncodingsForResourceContext($pdfBytes, $objects, true);
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, array{base: string, differences: array<int, string>, suppressUnmapped: bool}>
     */
    private function fontEncodingsForResourceContext(string $resourceContext, array $objects, bool $allowSingleFallback): array
    {
        $fontObjectEncodings = $this->fontObjectEncodings($objects);
        if ($fontObjectEncodings === []) {
            return [];
        }

        $resourceEncodings = [];
        foreach ($this->fontResourceDictionariesFromContext($resourceContext, $objects) as $fontResourceDictionary) {
            if (!preg_match_all('/\/([A-Za-z0-9_.#-]+)\s+(\d+)\s+\d+\s+R\b/', $fontResourceDictionary, $resourceMatches, PREG_SET_ORDER)) {
                continue;
            }

            foreach ($resourceMatches as $resourceMatch) {
                $fontObjectNumber = (int) $resourceMatch[2];
                if (isset($fontObjectEncodings[$fontObjectNumber])) {
                    $resourceEncodings[$this->decodePdfName($resourceMatch[1])] = $fontObjectEncodings[$fontObjectNumber];
                }
            }
        }

        if ($resourceEncodings !== []) {
            return $resourceEncodings;
        }

        if ($allowSingleFallback && count($fontObjectEncodings) === 1) {
            $onlyEncoding = reset($fontObjectEncodings);
            return is_array($onlyEncoding) ? ['' => $onlyEncoding] : [];
        }

        return [];
    }

    /**
     * @param array<int, string> $objects
     * @return array<int, array{base: string, differences: array<int, string>, suppressUnmapped: bool}>
     */
    private function fontObjectEncodings(array $objects): array
    {
        $cacheKey = $this->objectsCacheKey($objects);
        if (array_key_exists($cacheKey, $this->fontObjectEncodingsCache)) {
            return $this->fontObjectEncodingsCache[$cacheKey];
        }

        $fontObjectEncodings = [];

        foreach ($objects as $objectNumber => $body) {
            if (!str_contains($body, '/Type /Font') && !str_contains($body, '/Type/Font')) {
                continue;
            }

            $encoding = $this->fontEncodingFromObject($body, $objects);
            if ($encoding === null) {
                $encoding = $this->missingUnicodeCidFontEncodingFromObject((int) $objectNumber, $objects);
            }
            if ($encoding !== null) {
                $fontObjectEncodings[$objectNumber] = $encoding;
            }
        }

        if ($fontObjectEncodings === []) {
            $this->fontObjectEncodingsCache[$cacheKey] = [];
            return [];
        }

        $this->fontObjectEncodingsCache[$cacheKey] = $fontObjectEncodings;

        return $fontObjectEncodings;
    }

    /**
     * @param array<int, string> $objects
     * @return array{base: string, differences: array<int, string>, suppressUnmapped: bool}|null
     */
    private function missingUnicodeCidFontEncodingFromObject(int $objectNumber, array $objects): ?array
    {
        if (!isset($objects[$objectNumber])) {
            return null;
        }

        $objectBody = $objects[$objectNumber];
        if (preg_match('/\/Subtype\s*\/Type0\b/', $objectBody) !== 1 && preg_match('/\/DescendantFonts\b/', $objectBody) !== 1) {
            return null;
        }

        $cidEncoding = $this->cidEncodingNameFromFontObject($objectBody, $objects);
        if ($cidEncoding === null) {
            return null;
        }

        $toUnicodeMap = $this->toUnicodeMapForFontObject($objectNumber, $objects);
        if ($toUnicodeMap !== null && ($toUnicodeMap['map'] !== [] || $toUnicodeMap['codeSpaceRanges'] !== [])) {
            return null;
        }

        return [
            'base' => $cidEncoding,
            'differences' => [],
            'suppressUnmapped' => true,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function propertyActualTexts(string $pdfBytes): array
    {
        $objects = $this->pdfObjects($pdfBytes);
        return $this->propertyActualTextsFromContext($pdfBytes, $objects);
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, string>
     */
    private function propertyActualTextsFromContext(string $context, array $objects): array
    {
        $actualTexts = [];

        if (preg_match_all('/\/Properties\s+(\d+)\s+\d+\s+R\b/', $context, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $objectNumber = (int) $match[1];
                if (isset($objects[$objectNumber])) {
                    $actualTexts = array_replace($actualTexts, $this->propertyActualTextsFromDictionaryObject($objects[$objectNumber], $objects));
                }
            }
        }

        $offset = 0;
        while (preg_match('/\/Properties\s*<</', $context, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $dictionaryOffset = $match[0][1] + strlen('/Properties ');
            while ($dictionaryOffset < strlen($context) && ctype_space($context[$dictionaryOffset])) {
                $dictionaryOffset++;
            }
            if ($dictionaryOffset >= strlen($context) || substr($context, $dictionaryOffset, 2) !== '<<') {
                $offset = $match[0][1] + strlen($match[0][0]);
                continue;
            }

            $readOffset = $dictionaryOffset;
            $dictionary = $this->readDictionaryToken($context, $readOffset);
            $actualTexts = array_replace($actualTexts, $this->propertyActualTextsFromDictionary($dictionary, $objects));
            $offset = max($readOffset, $match[0][1] + strlen($match[0][0]));
        }

        return $actualTexts;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, string>
     */
    private function propertyActualTextsFromDictionaryObject(string $objectBody, array $objects): array
    {
        if (preg_match('/<<(.*)>>/s', $objectBody, $match) !== 1) {
            return [];
        }

        return $this->propertyActualTextsFromDictionary('<<' . $match[1] . '>>', $objects);
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, string>
     */
    private function propertyActualTextsFromDictionary(string $dictionary, array $objects): array
    {
        $body = trim($dictionary);
        if (str_starts_with($body, '<<')) {
            $body = substr($body, 2);
        }
        if (str_ends_with($body, '>>')) {
            $body = substr($body, 0, -2);
        }

        $tokens = $this->contentTokens($body);
        $actualTexts = [];
        $count = count($tokens);
        for ($index = 0; $index < $count; $index++) {
            $token = $tokens[$index];
            if (!str_starts_with($token, '/')) {
                continue;
            }

            $name = $this->decodePdfName(substr($token, 1));
            $next = $tokens[$index + 1] ?? null;
            if (is_string($next) && str_starts_with(trim($next), '<<')) {
                $actualText = $this->actualTextFromDictionary($next, $objects);
                if ($actualText !== null) {
                    $actualTexts[$name] = $actualText;
                }
                $index++;
                continue;
            }

            $objectNumber = $this->indirectObjectOperand($tokens, $index + 1);
            if ($objectNumber !== null && isset($objects[$objectNumber])) {
                $actualText = $this->actualTextFromDictionaryObject($objects[$objectNumber], $objects);
                if ($actualText !== null) {
                    $actualTexts[$name] = $actualText;
                }
                $index += 3;
            }
        }

        return $actualTexts;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, int>
     */
    private function propertyMcidsFromContext(string $context, array $objects): array
    {
        $mcids = [];

        if (preg_match_all('/\/Properties\s+(\d+)\s+\d+\s+R\b/', $context, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $objectNumber = (int) $match[1];
                if (isset($objects[$objectNumber])) {
                    $mcids = array_replace($mcids, $this->propertyMcidsFromDictionaryObject($objects[$objectNumber], $objects));
                }
            }
        }

        $offset = 0;
        while (preg_match('/\/Properties\s*<</', $context, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $dictionaryOffset = $match[0][1] + strlen('/Properties ');
            while ($dictionaryOffset < strlen($context) && ctype_space($context[$dictionaryOffset])) {
                $dictionaryOffset++;
            }
            if ($dictionaryOffset >= strlen($context) || substr($context, $dictionaryOffset, 2) !== '<<') {
                $offset = $match[0][1] + strlen($match[0][0]);
                continue;
            }

            $readOffset = $dictionaryOffset;
            $dictionary = $this->readDictionaryToken($context, $readOffset);
            $mcids = array_replace($mcids, $this->propertyMcidsFromDictionary($dictionary, $objects));
            $offset = max($readOffset, $match[0][1] + strlen($match[0][0]));
        }

        return $mcids;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, int>
     */
    private function propertyMcidsFromDictionaryObject(string $objectBody, array $objects): array
    {
        if (preg_match('/<<(.*)>>/s', $objectBody, $match) !== 1) {
            return [];
        }

        return $this->propertyMcidsFromDictionary('<<' . $match[1] . '>>', $objects);
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, int>
     */
    private function propertyMcidsFromDictionary(string $dictionary, array $objects): array
    {
        $tokens = $this->dictionaryTokens($dictionary);
        $mcids = [];
        $count = count($tokens);
        for ($index = 0; $index < $count; $index++) {
            $token = $tokens[$index];
            if (!str_starts_with($token, '/')) {
                continue;
            }

            $name = $this->decodePdfName(substr($token, 1));
            $next = $tokens[$index + 1] ?? null;
            if (is_string($next) && str_starts_with(trim($next), '<<')) {
                $mcid = $this->mcidFromDictionary($next);
                if ($mcid !== null) {
                    $mcids[$name] = $mcid;
                }
                $index++;
                continue;
            }

            $objectNumber = $this->indirectObjectOperand($tokens, $index + 1);
            if ($objectNumber !== null && isset($objects[$objectNumber])) {
                $mcid = $this->mcidFromDictionaryObject($objects[$objectNumber]);
                if ($mcid !== null) {
                    $mcids[$name] = $mcid;
                }
                $index += 3;
            }
        }

        return $mcids;
    }

    /**
     * @param array<int, string> $objects
     * @return array<int, string>
     */
    private function mcidActualTextsForPage(string $pageObjectBody, array $objects, ?int $pageObjectNumber = null): array
    {
        $structParents = $this->integerDictionaryValueFromObjects($pageObjectBody, 'StructParents', $objects);
        if ($structParents !== null) {
            foreach ($this->parentTreeRootNodes($objects) as $parentTreeNode) {
                $actualTexts = $this->parentTreeActualTextsForNode(
                    $parentTreeNode['body'],
                    $structParents,
                    $objects,
                    $parentTreeNode['objectNumber'] === null ? [] : [$parentTreeNode['objectNumber'] => true]
                );
                if ($actualTexts !== []) {
                    return $actualTexts;
                }
            }
        }

        if ($pageObjectNumber !== null) {
            if (!$this->objectsContainStructTreeRoot($objects)) {
                return [];
            }

            return $this->structTreeActualTextsForPage($pageObjectNumber, $objects);
        }

        return [];
    }

    /**
     * @param array<int, string> $objects
     */
    private function objectsContainStructTreeRoot(array $objects): bool
    {
        return $this->structTreeRootNodes($objects) !== [];
    }

    /**
     * @param array<int, string> $objects
     */
    private function objectsCacheKey(array $objects): string
    {
        $firstKey = array_key_first($objects);
        $lastKey = array_key_last($objects);
        $firstBody = is_int($firstKey) ? (string) ($objects[$firstKey] ?? '') : '';
        $lastBody = is_int($lastKey) ? (string) ($objects[$lastKey] ?? '') : '';

        return count($objects) . ':' . (string) $firstKey . ':' . (string) $lastKey . ':' . md5(substr($firstBody, 0, 256) . "\0" . substr($lastBody, 0, 256));
    }

    /**
     * @param array<int, string> $objects
     * @return array<int, string>
     */
    private function structTreeActualTextsForPage(int $pageObjectNumber, array $objects): array
    {
        $texts = [];

        foreach ($this->structTreeRootNodes($objects) as $rootNode) {
            $texts = array_replace(
                $texts,
                $this->actualTextsFromStructTreeRootForPage(
                    $rootNode['body'],
                    $objects,
                    $pageObjectNumber,
                    [$rootNode['objectNumber'] => true]
                )
            );
        }

        return $texts;
    }

    /**
     * @param array<int, string> $objects
     * @return list<array{body: string, objectNumber: int}>
     */
    private function structTreeRootNodes(array $objects): array
    {
        $cacheKey = $this->objectsCacheKey($objects);
        if (array_key_exists($cacheKey, $this->structTreeRootNodesCache)) {
            return $this->structTreeRootNodesCache[$cacheKey];
        }

        $nodes = [];
        $seen = [];

        foreach ($objects as $body) {
            $rootObjectNumber = $this->indirectObjectDictionaryValue($body, 'StructTreeRoot');
            if ($rootObjectNumber === null || isset($seen[$rootObjectNumber]) || !isset($objects[$rootObjectNumber])) {
                continue;
            }

            $seen[$rootObjectNumber] = true;
            $nodes[] = [
                'body' => $objects[$rootObjectNumber],
                'objectNumber' => $rootObjectNumber,
            ];
        }

        foreach ($objects as $objectNumber => $body) {
            if (isset($seen[$objectNumber]) || $this->nameDictionaryValue($body, 'Type') !== 'StructTreeRoot') {
                continue;
            }

            $seen[$objectNumber] = true;
            $nodes[] = [
                'body' => $body,
                'objectNumber' => $objectNumber,
            ];
        }

        $this->structTreeRootNodesCache[$cacheKey] = $nodes;

        return $nodes;
    }

    /**
     * @param array<int, string> $objects
     * @return list<array{body: string, objectNumber: int|null}>
     */
    private function parentTreeRootNodes(array $objects): array
    {
        $cacheKey = $this->objectsCacheKey($objects);
        if (array_key_exists($cacheKey, $this->parentTreeRootNodesCache)) {
            return $this->parentTreeRootNodesCache[$cacheKey];
        }

        $nodes = [];
        $seenObjectNumbers = [];

        foreach ($this->structTreeRootNodes($objects) as $rootNode) {
            foreach ($this->parentTreeNodesFromStructTreeBody($rootNode['body'], $objects) as $node) {
                $objectNumber = $node['objectNumber'];
                if ($objectNumber !== null) {
                    if (isset($seenObjectNumbers[$objectNumber])) {
                        continue;
                    }
                    $seenObjectNumbers[$objectNumber] = true;
                }
                $nodes[] = $node;
            }
        }

        $this->parentTreeRootNodesCache[$cacheKey] = $nodes;

        return $nodes;
    }

    /**
     * @param array<int, string> $objects
     * @return list<array{body: string, objectNumber: int|null}>
     */
    private function parentTreeNodesFromStructTreeBody(string $structTreeBody, array $objects): array
    {
        $tokens = $this->dictionaryTokens($structTreeBody);
        $nodes = [];
        $count = count($tokens);

        for ($index = 0; $index < $count; $index++) {
            if (!$this->isPdfNameToken($tokens[$index], 'ParentTree')) {
                continue;
            }

            $objectNumber = $this->indirectObjectOperand($tokens, $index + 1);
            if ($objectNumber !== null) {
                if (isset($objects[$objectNumber])) {
                    $nodes[] = [
                        'body' => $objects[$objectNumber],
                        'objectNumber' => $objectNumber,
                    ];
                }
                $index += 3;
                continue;
            }

            $next = $tokens[$index + 1] ?? null;
            if (is_string($next) && str_starts_with(trim($next), '<<')) {
                $nodes[] = [
                    'body' => $next,
                    'objectNumber' => null,
                ];
                $index++;
            }
        }

        return $nodes;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     * @return array<int, string>
     */
    private function actualTextsFromStructTreeRootForPage(string $structTreeBody, array $objects, int $pageObjectNumber, array $seen = []): array
    {
        $tokens = $this->dictionaryTokens($structTreeBody);
        $texts = [];
        $count = count($tokens);

        for ($index = 0; $index < $count; $index++) {
            if (!$this->isPdfNameToken($tokens[$index], 'K')) {
                continue;
            }

            $kidIndex = $index + 1;
            $texts = array_replace(
                $texts,
                $this->actualTextsFromStructTreeKidTokensForPage($tokens, $kidIndex, $objects, $pageObjectNumber, null, $seen)
            );
            $index = max($index, $kidIndex - 1);
        }

        return $texts;
    }

    /**
     * @param list<string> $tokens
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     * @return array<int, string>
     */
    private function actualTextsFromStructTreeKidTokensForPage(
        array $tokens,
        int &$index,
        array $objects,
        int $pageObjectNumber,
        ?int $inheritedPageObjectNumber,
        array $seen
    ): array {
        $objectNumber = $this->indirectObjectOperand($tokens, $index);
        if ($objectNumber !== null) {
            $index += 3;
            if (isset($seen[$objectNumber]) || !isset($objects[$objectNumber])) {
                return [];
            }

            $arrayToken = $this->arrayTokenFromObjectBody($objects[$objectNumber]);
            if ($arrayToken !== null) {
                return $this->actualTextsFromStructTreeKidArrayForPage(
                    $arrayToken,
                    $objects,
                    $pageObjectNumber,
                    $inheritedPageObjectNumber,
                    $seen + [$objectNumber => true]
                );
            }

            return $this->actualTextsFromStructElementValueForPage(
                $objects[$objectNumber],
                $objects,
                $pageObjectNumber,
                $inheritedPageObjectNumber,
                $seen + [$objectNumber => true]
            );
        }

        $token = $tokens[$index] ?? null;
        if (!is_string($token)) {
            return [];
        }
        $index++;

        $trimmed = trim($token);
        if (str_starts_with($trimmed, '[')) {
            return $this->actualTextsFromStructTreeKidArrayForPage($trimmed, $objects, $pageObjectNumber, $inheritedPageObjectNumber, $seen);
        }

        if (str_starts_with($trimmed, '<<')) {
            return $this->actualTextsFromStructElementValueForPage(
                $trimmed,
                $objects,
                $pageObjectNumber,
                $inheritedPageObjectNumber,
                $seen
            );
        }

        return [];
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     * @return array<int, string>
     */
    private function actualTextsFromStructTreeKidArrayForPage(
        string $array,
        array $objects,
        int $pageObjectNumber,
        ?int $inheritedPageObjectNumber,
        array $seen
    ): array {
        $texts = [];
        $arrayTokens = $this->arrayTokens($array);
        for ($arrayIndex = 0, $arrayCount = count($arrayTokens); $arrayIndex < $arrayCount;) {
            $before = $arrayIndex;
            $texts = array_replace(
                $texts,
                $this->actualTextsFromStructTreeKidTokensForPage(
                    $arrayTokens,
                    $arrayIndex,
                    $objects,
                    $pageObjectNumber,
                    $inheritedPageObjectNumber,
                    $seen
                )
            );
            if ($arrayIndex === $before) {
                $arrayIndex++;
            }
        }

        return $texts;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     * @return array<int, string>
     */
    private function actualTextsFromStructElementValueForPage(
        string $value,
        array $objects,
        int $pageObjectNumber,
        ?int $inheritedPageObjectNumber,
        array $seen = []
    ): array {
        $dictionary = $this->dictionaryFromValue($value);
        if ($dictionary === null) {
            return [];
        }

        $elementPageObjectNumber = $this->pageObjectNumberFromDictionary($dictionary) ?? $inheritedPageObjectNumber;
        $replacementText = $this->structElementReplacementTextFromDictionary($dictionary, $objects);
        if ($replacementText === null) {
            return $this->actualTextsFromStructElementKidsForPage(
                $dictionary,
                $objects,
                $pageObjectNumber,
                $elementPageObjectNumber,
                $seen
            );
        }

        $mcids = $this->structElementKidMcidsForPage($dictionary, $objects, $pageObjectNumber, $elementPageObjectNumber, $seen);
        if ($mcids === []) {
            $directMcid = $this->topLevelMcidFromDictionary($dictionary);
            if ($directMcid !== null && $elementPageObjectNumber === $pageObjectNumber) {
                $mcids = [$directMcid];
            }
        }
        if ($mcids === []) {
            return [];
        }

        $texts = [];
        foreach ($mcids as $index => $mcid) {
            $texts[$mcid] = $index === 0 ? $replacementText : '';
        }

        return $texts;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     * @return array<int, string>
     */
    private function actualTextsFromStructElementKidsForPage(
        string $dictionary,
        array $objects,
        int $pageObjectNumber,
        ?int $inheritedPageObjectNumber,
        array $seen = []
    ): array {
        $tokens = $this->dictionaryTokens($dictionary);
        $texts = [];
        $count = count($tokens);

        for ($index = 0; $index < $count; $index++) {
            if (!$this->isPdfNameToken($tokens[$index], 'K')) {
                continue;
            }

            $kidIndex = $index + 1;
            $texts = array_replace(
                $texts,
                $this->actualTextsFromStructTreeKidTokensForPage(
                    $tokens,
                    $kidIndex,
                    $objects,
                    $pageObjectNumber,
                    $inheritedPageObjectNumber,
                    $seen
                )
            );
            $index = max($index, $kidIndex - 1);
        }

        return $texts;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     * @return list<int>
     */
    private function structElementKidMcidsForPage(
        string $dictionary,
        array $objects,
        int $pageObjectNumber,
        ?int $inheritedPageObjectNumber,
        array $seen = []
    ): array {
        $tokens = $this->dictionaryTokens($dictionary);
        $mcids = [];
        $count = count($tokens);

        for ($index = 0; $index < $count; $index++) {
            if (!$this->isPdfNameToken($tokens[$index], 'K')) {
                continue;
            }

            $kidIndex = $index + 1;
            $mcids = array_merge(
                $mcids,
                $this->structElementKidMcidsFromTokensForPage(
                    $tokens,
                    $kidIndex,
                    $objects,
                    $pageObjectNumber,
                    $inheritedPageObjectNumber,
                    $seen
                )
            );
            $index = max($index, $kidIndex - 1);
        }

        return $this->uniqueIntegers($mcids);
    }

    /**
     * @param list<string> $tokens
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     * @return list<int>
     */
    private function structElementKidMcidsFromTokensForPage(
        array $tokens,
        int &$index,
        array $objects,
        int $pageObjectNumber,
        ?int $inheritedPageObjectNumber,
        array $seen
    ): array {
        $objectNumber = $this->indirectObjectOperand($tokens, $index);
        if ($objectNumber !== null) {
            $index += 3;
            if (isset($seen[$objectNumber]) || !isset($objects[$objectNumber])) {
                return [];
            }

            $mcid = $this->integerTokenFromObjectBody($objects[$objectNumber]);
            if ($mcid !== null) {
                return $inheritedPageObjectNumber === $pageObjectNumber ? [$mcid] : [];
            }

            $arrayToken = $this->arrayTokenFromObjectBody($objects[$objectNumber]);
            if ($arrayToken !== null) {
                return $this->structElementKidMcidsFromArrayForPage(
                    $arrayToken,
                    $objects,
                    $pageObjectNumber,
                    $inheritedPageObjectNumber,
                    $seen + [$objectNumber => true]
                );
            }

            return $this->structElementKidMcidsFromValueForPage(
                $objects[$objectNumber],
                $objects,
                $pageObjectNumber,
                $inheritedPageObjectNumber,
                $seen + [$objectNumber => true]
            );
        }

        $token = $tokens[$index] ?? null;
        if (!is_string($token)) {
            return [];
        }
        $index++;

        if (preg_match('/^\d+$/', $token) === 1) {
            return $inheritedPageObjectNumber === $pageObjectNumber ? [(int) $token] : [];
        }

        $trimmed = trim($token);
        if (str_starts_with($trimmed, '[')) {
            return $this->structElementKidMcidsFromArrayForPage($trimmed, $objects, $pageObjectNumber, $inheritedPageObjectNumber, $seen);
        }

        if (str_starts_with($trimmed, '<<')) {
            return $this->structElementKidMcidsFromValueForPage(
                $trimmed,
                $objects,
                $pageObjectNumber,
                $inheritedPageObjectNumber,
                $seen
            );
        }

        return [];
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     * @return list<int>
     */
    private function structElementKidMcidsFromArrayForPage(
        string $array,
        array $objects,
        int $pageObjectNumber,
        ?int $inheritedPageObjectNumber,
        array $seen
    ): array {
        $mcids = [];
        $arrayTokens = $this->arrayTokens($array);
        for ($arrayIndex = 0, $arrayCount = count($arrayTokens); $arrayIndex < $arrayCount;) {
            $before = $arrayIndex;
            $mcids = array_merge(
                $mcids,
                $this->structElementKidMcidsFromTokensForPage(
                    $arrayTokens,
                    $arrayIndex,
                    $objects,
                    $pageObjectNumber,
                    $inheritedPageObjectNumber,
                    $seen
                )
            );
            if ($arrayIndex === $before) {
                $arrayIndex++;
            }
        }

        return $this->uniqueIntegers($mcids);
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     * @return list<int>
     */
    private function structElementKidMcidsFromValueForPage(
        string $value,
        array $objects,
        int $pageObjectNumber,
        ?int $inheritedPageObjectNumber,
        array $seen
    ): array {
        $dictionary = $this->dictionaryFromValue($value);
        if ($dictionary === null) {
            return [];
        }

        $elementPageObjectNumber = $this->pageObjectNumberFromDictionary($dictionary) ?? $inheritedPageObjectNumber;
        $mcid = $this->topLevelMcidFromDictionary($dictionary);
        if ($mcid !== null && $elementPageObjectNumber === $pageObjectNumber) {
            return [$mcid];
        }

        return $this->structElementKidMcidsForPage($dictionary, $objects, $pageObjectNumber, $elementPageObjectNumber, $seen);
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     * @return array<int, string>
     */
    private function parentTreeActualTextsForNode(string $objectBody, int $key, array $objects, array $seen = []): array
    {
        $tokens = $this->dictionaryTokens($objectBody);
        if (!$this->parentTreeLimitsContainKey($tokens, $key, $objects)) {
            return [];
        }

        $nums = $this->dictionaryArrayTokenFromTokens($tokens, 'Nums', $objects);
        if ($nums !== null) {
            $entries = $this->arrayTokens($nums);
            for ($entryIndex = 0, $entryCount = count($entries); $entryIndex < $entryCount;) {
                $entryKey = $entries[$entryIndex] ?? null;
                if (!is_string($entryKey) || preg_match('/^\d+$/', $entryKey) !== 1) {
                    $entryIndex++;
                    continue;
                }

                $entryIndex++;
                $value = $this->parentTreeValueToken($entries, $entryIndex, $objects);
                if ((int) $entryKey === $key && $value !== null) {
                    return $this->actualTextsFromParentTreeValue($value, $objects);
                }
            }
        }

        foreach ($this->parentTreeKidNodes($tokens, $objects, $seen) as $kidNode) {
            $actualTexts = $this->parentTreeActualTextsForNode(
                $kidNode['body'],
                $key,
                $objects,
                $kidNode['objectNumber'] === null ? $seen : $seen + [$kidNode['objectNumber'] => true]
            );
            if ($actualTexts !== []) {
                return $actualTexts;
            }
        }

        return [];
    }

    /**
     * @param list<string> $tokens
     */
    private function parentTreeLimitsContainKey(array $tokens, int $key, array $objects): bool
    {
        $limits = $this->dictionaryArrayTokenFromTokens($tokens, 'Limits', $objects);
        if ($limits !== null) {
            $limitTokens = $this->arrayTokens($limits);
            if (!isset($limitTokens[0], $limitTokens[1]) || preg_match('/^\d+$/', $limitTokens[0]) !== 1 || preg_match('/^\d+$/', $limitTokens[1]) !== 1) {
                return true;
            }

            return $key >= (int) $limitTokens[0] && $key <= (int) $limitTokens[1];
        }

        return true;
    }

    /**
     * @param list<string> $tokens
     * @param array<int, string> $objects
     */
    private function parentTreeValueToken(array $tokens, int &$index, array $objects): ?string
    {
        $objectNumber = $this->indirectObjectOperand($tokens, $index);
        if ($objectNumber !== null) {
            $index += 3;
            return $objects[$objectNumber] ?? null;
        }

        if (!isset($tokens[$index])) {
            return null;
        }

        return $tokens[$index++];
    }

    /**
     * @param list<string> $tokens
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     * @return list<array{body: string, objectNumber: ?int}>
     */
    private function parentTreeKidNodes(array $tokens, array $objects, array $seen): array
    {
        $kids = [];
        $kidsToken = $this->dictionaryArrayTokenFromTokens($tokens, 'Kids', $objects);
        if ($kidsToken === null) {
            return $kids;
        }

        $kidTokens = $this->arrayTokens($kidsToken);
        for ($kidIndex = 0, $kidCount = count($kidTokens); $kidIndex < $kidCount;) {
            $objectNumber = $this->indirectObjectOperand($kidTokens, $kidIndex);
            if ($objectNumber !== null) {
                if (!isset($seen[$objectNumber]) && isset($objects[$objectNumber])) {
                    $kids[] = [
                        'body' => $objects[$objectNumber],
                        'objectNumber' => $objectNumber,
                    ];
                }
                $kidIndex += 3;
                continue;
            }

            $kidToken = $kidTokens[$kidIndex] ?? '';
            if (str_starts_with(trim($kidToken), '<<')) {
                $kids[] = [
                    'body' => $kidToken,
                    'objectNumber' => null,
                ];
            }
            $kidIndex++;
        }

        return $kids;
    }

    /**
     * @param list<string> $tokens
     * @param array<int, string> $objects
     */
    private function dictionaryArrayTokenFromTokens(array $tokens, string $name, array $objects): ?string
    {
        $count = count($tokens);
        for ($index = 0; $index < $count; $index++) {
            if (!$this->isPdfNameToken($tokens[$index], $name)) {
                continue;
            }

            $objectNumber = $this->indirectObjectOperand($tokens, $index + 1);
            if ($objectNumber !== null && isset($objects[$objectNumber])) {
                return $this->arrayTokenFromObjectBody($objects[$objectNumber]);
            }

            $token = $tokens[$index + 1] ?? null;
            if (is_string($token) && str_starts_with(trim($token), '[')) {
                return $token;
            }
        }

        return null;
    }

    /**
     * @param list<string> $tokens
     * @param array<int, string> $objects
     */
    private function dictionaryDictionaryTokenFromTokens(array $tokens, string $name, array $objects): ?string
    {
        $count = count($tokens);
        for ($index = 0; $index < $count; $index++) {
            if (!$this->isPdfNameToken($tokens[$index], $name)) {
                continue;
            }

            $objectNumber = $this->indirectObjectOperand($tokens, $index + 1);
            if ($objectNumber !== null && isset($objects[$objectNumber])) {
                return $this->dictionaryFromValue($objects[$objectNumber]);
            }

            $token = $tokens[$index + 1] ?? null;
            if (is_string($token) && str_starts_with(trim($token), '<<')) {
                return $token;
            }
        }

        return null;
    }

    private function arrayTokenFromObjectBody(string $body): ?string
    {
        $offset = 0;
        $length = strlen($body);
        while ($offset < $length && ctype_space($body[$offset])) {
            $offset++;
        }

        if ($offset >= $length || $body[$offset] !== '[') {
            return null;
        }

        return $this->readArrayToken($body, $offset);
    }

    private function integerTokenFromObjectBody(string $body): ?int
    {
        $body = trim($body);
        if (preg_match('/^\d+$/', $body) !== 1) {
            return null;
        }

        return (int) $body;
    }

    /**
     * @param array<int, string> $objects
     * @return array<int, string>
     */
    private function actualTextsFromParentTreeValue(string $value, array $objects): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        if (str_starts_with($value, '[')) {
            $texts = [];
            $tokens = $this->arrayTokens($value);
            $mcid = 0;
            for ($index = 0, $count = count($tokens); $index < $count;) {
                $actualTexts = [];
                $objectNumber = $this->indirectObjectOperand($tokens, $index);
                if ($objectNumber !== null) {
                    if (isset($objects[$objectNumber])) {
                        $actualTexts = $this->actualTextsFromStructElementValue($objects[$objectNumber], $objects, $mcid);
                    }
                    $index += 3;
                } else {
                    $token = $tokens[$index] ?? '';
                    if (str_starts_with(trim($token), '<<')) {
                        $actualTexts = $this->actualTextsFromStructElementValue($token, $objects, $mcid);
                    }
                    $index++;
                }

                foreach ($actualTexts as $actualTextMcid => $actualText) {
                    $texts[$actualTextMcid] = $actualText;
                }
                $mcid++;
            }

            return $texts;
        }

        if (str_starts_with($value, '<<')) {
            return $this->actualTextsFromStructElementValue($value, $objects, 0);
        }

        return [];
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     * @return array<int, string>
     */
    private function actualTextsFromStructElementValue(string $value, array $objects, int $fallbackMcid, array $seen = []): array
    {
        $dictionary = $this->dictionaryFromValue($value);
        if ($dictionary === null) {
            return [];
        }

        $replacementText = $this->structElementReplacementTextFromDictionary($dictionary, $objects);
        if ($replacementText === null) {
            return $this->actualTextsFromStructElementKids($dictionary, $objects, $seen);
        }

        $directMcid = $this->topLevelMcidFromDictionary($dictionary);
        if ($directMcid !== null) {
            return [$directMcid => $replacementText];
        }

        $mcids = $this->structElementKidMcids($dictionary, $objects);
        if ($mcids === []) {
            return [$fallbackMcid => $replacementText];
        }

        $texts = [];
        foreach ($mcids as $index => $mcid) {
            $texts[$mcid] = $index === 0 ? $replacementText : '';
        }

        return $texts;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     * @return array<int, string>
     */
    private function actualTextsFromStructElementKids(string $dictionary, array $objects, array $seen = []): array
    {
        $tokens = $this->dictionaryTokens($dictionary);
        $texts = [];
        $count = count($tokens);

        for ($index = 0; $index < $count; $index++) {
            if (!$this->isPdfNameToken($tokens[$index], 'K')) {
                continue;
            }

            $kidIndex = $index + 1;
            $texts = array_replace($texts, $this->actualTextsFromStructElementKidTokens($tokens, $kidIndex, $objects, $seen));
            $index = max($index, $kidIndex - 1);
        }

        return $texts;
    }

    /**
     * @param list<string> $tokens
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     * @return array<int, string>
     */
    private function actualTextsFromStructElementKidTokens(array $tokens, int &$index, array $objects, array $seen): array
    {
        $objectNumber = $this->indirectObjectOperand($tokens, $index);
        if ($objectNumber !== null) {
            $index += 3;
            if (isset($seen[$objectNumber]) || !isset($objects[$objectNumber])) {
                return [];
            }

            $arrayToken = $this->arrayTokenFromObjectBody($objects[$objectNumber]);
            if ($arrayToken !== null) {
                return $this->actualTextsFromStructElementKidArray($arrayToken, $objects, $seen + [$objectNumber => true]);
            }

            return $this->actualTextsFromStructElementValue($objects[$objectNumber], $objects, 0, $seen + [$objectNumber => true]);
        }

        $token = $tokens[$index] ?? null;
        if (!is_string($token)) {
            return [];
        }
        $index++;

        $trimmed = trim($token);
        if (str_starts_with($trimmed, '[')) {
            return $this->actualTextsFromStructElementKidArray($trimmed, $objects, $seen);
        }

        if (str_starts_with($trimmed, '<<')) {
            return $this->actualTextsFromStructElementValue($trimmed, $objects, 0, $seen);
        }

        return [];
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     * @return array<int, string>
     */
    private function actualTextsFromStructElementKidArray(string $array, array $objects, array $seen): array
    {
        $texts = [];
        $arrayTokens = $this->arrayTokens($array);
        for ($arrayIndex = 0, $arrayCount = count($arrayTokens); $arrayIndex < $arrayCount;) {
            $before = $arrayIndex;
            $texts = array_replace($texts, $this->actualTextsFromStructElementKidTokens($arrayTokens, $arrayIndex, $objects, $seen));
            if ($arrayIndex === $before) {
                $arrayIndex++;
            }
        }

        return $texts;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     * @return list<int>
     */
    private function structElementChildObjectNumbers(string $dictionary, array $objects, array $seen = []): array
    {
        $tokens = $this->dictionaryTokens($dictionary);
        $childObjectNumbers = [];
        $count = count($tokens);

        for ($index = 0; $index < $count; $index++) {
            if (!$this->isPdfNameToken($tokens[$index], 'K')) {
                continue;
            }

            $kidIndex = $index + 1;
            $childObjectNumbers = array_merge($childObjectNumbers, $this->structElementChildObjectNumbersFromTokens($tokens, $kidIndex, $objects, $seen));
            $index = max($index, $kidIndex - 1);
        }

        return $this->uniqueIntegers($childObjectNumbers);
    }

    /**
     * @param list<string> $tokens
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     * @return list<int>
     */
    private function structElementChildObjectNumbersFromTokens(array $tokens, int &$index, array $objects, array $seen): array
    {
        $objectNumber = $this->indirectObjectOperand($tokens, $index);
        if ($objectNumber !== null) {
            $index += 3;
            if (isset($seen[$objectNumber]) || !isset($objects[$objectNumber])) {
                return [];
            }

            if ($this->nameDictionaryValue($objects[$objectNumber], 'Type') === 'StructElem') {
                return [$objectNumber];
            }

            $arrayToken = $this->arrayTokenFromObjectBody($objects[$objectNumber]);
            if ($arrayToken !== null) {
                return $this->structElementChildObjectNumbersFromArray($arrayToken, $objects, $seen + [$objectNumber => true]);
            }

            return [];
        }

        $token = $tokens[$index] ?? null;
        if (!is_string($token)) {
            return [];
        }
        $index++;

        $trimmed = trim($token);
        if (str_starts_with($trimmed, '[')) {
            return $this->structElementChildObjectNumbersFromArray($trimmed, $objects, $seen);
        }

        return [];
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     * @return list<int>
     */
    private function structElementChildObjectNumbersFromArray(string $array, array $objects, array $seen): array
    {
        $childObjectNumbers = [];
        $arrayTokens = $this->arrayTokens($array);
        for ($arrayIndex = 0, $arrayCount = count($arrayTokens); $arrayIndex < $arrayCount;) {
            $before = $arrayIndex;
            $childObjectNumbers = array_merge($childObjectNumbers, $this->structElementChildObjectNumbersFromTokens($arrayTokens, $arrayIndex, $objects, $seen));
            if ($arrayIndex === $before) {
                $arrayIndex++;
            }
        }

        return $this->uniqueIntegers($childObjectNumbers);
    }

    private function dictionaryFromValue(string $value): ?string
    {
        $value = trim($value);
        if (str_starts_with($value, '<<')) {
            return $value;
        }

        if (preg_match('/<<(.*)>>/s', $value, $match) === 1) {
            return '<<' . $match[1] . '>>';
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     * @return list<int>
     */
    private function structElementKidMcids(string $dictionary, array $objects, array $seen = []): array
    {
        $tokens = $this->dictionaryTokens($dictionary);
        $mcids = [];
        $count = count($tokens);

        for ($index = 0; $index < $count; $index++) {
            if (!$this->isPdfNameToken($tokens[$index], 'K')) {
                continue;
            }

            $kidIndex = $index + 1;
            $mcids = array_merge($mcids, $this->structElementKidMcidsFromTokens($tokens, $kidIndex, $objects, $seen));
            $index = max($index, $kidIndex - 1);
        }

        return $this->uniqueIntegers($mcids);
    }

    /**
     * @param list<string> $tokens
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     * @return list<int>
     */
    private function structElementKidMcidsFromTokens(array $tokens, int &$index, array $objects, array $seen): array
    {
        $objectNumber = $this->indirectObjectOperand($tokens, $index);
        if ($objectNumber !== null) {
            $index += 3;
            if (isset($seen[$objectNumber]) || !isset($objects[$objectNumber])) {
                return [];
            }

            $mcid = $this->integerTokenFromObjectBody($objects[$objectNumber]);
            if ($mcid !== null) {
                return [$mcid];
            }

            $arrayToken = $this->arrayTokenFromObjectBody($objects[$objectNumber]);
            if ($arrayToken !== null) {
                return $this->structElementKidMcidsFromArray($arrayToken, $objects, $seen + [$objectNumber => true]);
            }

            return $this->structElementKidMcidsFromValue($objects[$objectNumber], $objects, $seen + [$objectNumber => true]);
        }

        $token = $tokens[$index] ?? null;
        if (!is_string($token)) {
            return [];
        }
        $index++;

        if (preg_match('/^\d+$/', $token) === 1) {
            return [(int) $token];
        }

        $trimmed = trim($token);
        if (str_starts_with($trimmed, '[')) {
            return $this->structElementKidMcidsFromArray($trimmed, $objects, $seen);
        }

        if (str_starts_with($trimmed, '<<')) {
            return $this->structElementKidMcidsFromValue($trimmed, $objects, $seen);
        }

        return [];
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     * @return list<int>
     */
    private function structElementKidMcidsFromArray(string $array, array $objects, array $seen): array
    {
        $mcids = [];
        $arrayTokens = $this->arrayTokens($array);
        for ($arrayIndex = 0, $arrayCount = count($arrayTokens); $arrayIndex < $arrayCount;) {
            $before = $arrayIndex;
            $mcids = array_merge($mcids, $this->structElementKidMcidsFromTokens($arrayTokens, $arrayIndex, $objects, $seen));
            if ($arrayIndex === $before) {
                $arrayIndex++;
            }
        }

        return $this->uniqueIntegers($mcids);
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     * @return list<int>
     */
    private function structElementKidMcidsFromValue(string $value, array $objects, array $seen): array
    {
        $dictionary = $this->dictionaryFromValue($value);
        if ($dictionary === null) {
            return [];
        }

        $mcid = $this->topLevelMcidFromDictionary($dictionary);
        if ($mcid !== null) {
            return [$mcid];
        }

        return $this->structElementKidMcids($dictionary, $objects, $seen);
    }

    private function topLevelMcidFromDictionary(string $dictionary): ?int
    {
        $tokens = $this->dictionaryTokens($dictionary);
        $count = count($tokens);
        for ($index = 0; $index + 1 < $count; $index++) {
            if (!$this->isPdfNameToken($tokens[$index], 'MCID')) {
                continue;
            }

            $mcid = $tokens[$index + 1];
            if (preg_match('/^\d+$/', $mcid) === 1) {
                return (int) $mcid;
            }
        }

        return null;
    }

    private function pageObjectNumberFromDictionary(string $dictionary): ?int
    {
        return $this->indirectObjectDictionaryValue($dictionary, 'Pg');
    }

    /**
     * @param list<int> $values
     * @return list<int>
     */
    private function uniqueIntegers(array $values): array
    {
        $unique = [];
        foreach ($values as $value) {
            $unique[(int) $value] = (int) $value;
        }

        return array_values($unique);
    }

    private function mcidFromDictionaryObject(string $objectBody): ?int
    {
        if (preg_match('/<<(.*)>>/s', $objectBody, $match) !== 1) {
            return null;
        }

        return $this->mcidFromDictionary('<<' . $match[1] . '>>');
    }

    private function mcidFromDictionary(string $dictionary): ?int
    {
        return $this->integerDictionaryValue($dictionary, 'MCID');
    }

    /**
     * @return list<string>
     */
    private function dictionaryTokens(string $dictionary): array
    {
        $body = trim($dictionary);
        if (str_starts_with($body, '<<')) {
            $body = substr($body, 2);
        }
        if (str_ends_with($body, '>>')) {
            $body = substr($body, 0, -2);
        }

        return $this->contentTokens($body);
    }

    private function isPdfNameToken(string $token, string $name): bool
    {
        return str_starts_with($token, '/') && $this->decodePdfName(substr($token, 1)) === $name;
    }

    private function dictionaryValueToken(string $dictionary, string $name): ?string
    {
        $tokens = $this->dictionaryTokens($dictionary);
        $count = count($tokens);
        for ($index = 0; $index + 1 < $count; $index++) {
            if ($this->isPdfNameToken($tokens[$index], $name)) {
                return $tokens[$index + 1];
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     * @return array{token: string, objectNumber?: int}|null
     */
    private function dictionaryEntryValue(string $dictionary, string $name, array $objects): ?array
    {
        $tokens = $this->dictionaryTokens($dictionary);
        $count = count($tokens);
        for ($index = 0; $index + 1 < $count; $index++) {
            if (!$this->isPdfNameToken($tokens[$index], $name)) {
                continue;
            }

            $objectNumber = $this->indirectObjectOperand($tokens, $index + 1);
            if ($objectNumber !== null && isset($objects[$objectNumber])) {
                return [
                    'token' => trim($objects[$objectNumber]),
                    'objectNumber' => $objectNumber,
                ];
            }

            $token = $tokens[$index + 1] ?? null;
            return is_string($token) ? ['token' => $token] : null;
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     * @return array{token: string, objectNumber?: int}|null
     */
    private function lastDictionaryEntryValue(string $dictionary, string $name, array $objects): ?array
    {
        $tokens = $this->dictionaryTokens($dictionary);
        $count = count($tokens);
        $entry = null;
        for ($index = 0; $index + 1 < $count; $index++) {
            if (!$this->isPdfNameToken($tokens[$index], $name)) {
                continue;
            }

            $objectNumber = $this->indirectObjectOperand($tokens, $index + 1);
            if ($objectNumber !== null && isset($objects[$objectNumber])) {
                $entry = [
                    'token' => trim($objects[$objectNumber]),
                    'objectNumber' => $objectNumber,
                ];
                continue;
            }

            $token = $tokens[$index + 1] ?? null;
            if (is_string($token)) {
                $entry = ['token' => $token];
            }
        }

        return $entry;
    }

    private function indirectObjectDictionaryValue(string $dictionary, string $name): ?int
    {
        $tokens = $this->dictionaryTokens($dictionary);
        $count = count($tokens);
        for ($index = 0; $index + 3 < $count; $index++) {
            if (!$this->isPdfNameToken($tokens[$index], $name)) {
                continue;
            }

            $objectNumber = $this->indirectObjectOperand($tokens, $index + 1);
            if ($objectNumber !== null) {
                return $objectNumber;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function arrayTokens(string $array): array
    {
        $body = trim($array);
        if (str_starts_with($body, '[')) {
            $body = substr($body, 1);
        }
        if (str_ends_with($body, ']')) {
            $body = substr($body, 0, -1);
        }

        return $this->contentTokens($body);
    }

    /**
     * @param list<string> $tokens
     */
    private function indirectObjectOperand(array $tokens, int $offset): ?int
    {
        if (!isset($tokens[$offset], $tokens[$offset + 1], $tokens[$offset + 2])) {
            return null;
        }
        if (preg_match('/^\d+$/', $tokens[$offset]) !== 1 || preg_match('/^\d+$/', $tokens[$offset + 1]) !== 1 || $tokens[$offset + 2] !== 'R') {
            return null;
        }

        return (int) $tokens[$offset];
    }

    private function actualTextFromDictionaryObject(string $objectBody, array $objects = []): ?string
    {
        if (preg_match('/<<(.*)>>/s', $objectBody, $match) !== 1) {
            return null;
        }

        return $this->actualTextFromDictionary('<<' . $match[1] . '>>', $objects);
    }

    /**
     * @return array{base: string, differences: array<int, string>, suppressUnmapped: bool}|null
     */
    /**
     * @param array<int, string> $objects
     */
    private function fontEncodingFromObject(string $objectBody, array $objects = []): ?array
    {
        if (preg_match('/\/Encoding\s+(\d+)\s+\d+\s+R\b/', $objectBody, $match) === 1) {
            $encodingObjectNumber = (int) $match[1];
            if (isset($objects[$encodingObjectNumber])) {
                $encoding = $this->fontEncodingFromObject($objects[$encodingObjectNumber], $objects);
                if ($encoding !== null) {
                    if ($this->isType3FontWithSafeDeclaredEncoding($objectBody, $encoding['base'])) {
                        $encoding['suppressUnmapped'] = false;
                    }
                    if ($this->isSimpleTrueTypeFontWithSafeDeclaredEncoding(
                        $objectBody,
                        $encoding['base'],
                        $this->fontWidthsFromObject($objectBody, $objects)
                    )) {
                        $encoding['suppressUnmapped'] = false;
                    }

                    return $encoding;
                }
            }
        }

        $baseEncoding = null;
        $trimmedObjectBody = trim($objectBody);
        if (preg_match('/^\/([A-Za-z0-9_.-]+)$/', $trimmedObjectBody, $match) === 1) {
            $baseEncoding = $this->supportedSimpleFontEncoding($match[1]);
        }

        if (preg_match('/\/Encoding\s*\/([A-Za-z0-9_.-]+)/', $objectBody, $match) === 1) {
            $baseEncoding = $this->supportedSimpleFontEncoding($match[1]);
        }

        if ($baseEncoding === null && preg_match('/\/BaseEncoding\s*\/([A-Za-z0-9_.-]+)/', $objectBody, $match) === 1) {
            $baseEncoding = $this->supportedSimpleFontEncoding($match[1]);
        }

        if ($baseEncoding === null) {
            $baseEncoding = $this->implicitStandardFontEncoding($objectBody);
        }

        $differences = $this->fontEncodingDifferences($objectBody);
        $embeddedDifferences = [];
        if ($differences === []) {
            $embeddedDifferences = $this->embeddedType1FontEncodingDifferences($objectBody, $objects);
            if ($embeddedDifferences === []) {
                $embeddedDifferences = $this->embeddedTrueTypeFontEncodingDifferences($objectBody, $objects);
            }
            if ($embeddedDifferences === []) {
                $embeddedDifferences = $this->embeddedCffFontEncodingDifferences($objectBody, $objects);
            }
            $differences = $embeddedDifferences;
        }

        if ($baseEncoding === null && $differences !== []) {
            $baseEncoding = self::GLYPH_NAME_ENCODING;
        }

        if ($baseEncoding !== null && $embeddedDifferences !== [] && $this->isUnmappedCustomFont($objectBody)) {
            $baseEncoding = self::GLYPH_NAME_ENCODING;
        }

        if ($baseEncoding === null) {
            return null;
        }

        $widths = $this->fontWidthsFromObject($objectBody, $objects);
        $suppressUnmapped = $differences === [] && $this->isUnmappedCustomFont($objectBody);
        if ($suppressUnmapped && $this->isType3FontWithSafeDeclaredEncoding($objectBody, $baseEncoding)) {
            $suppressUnmapped = false;
        }
        if ($suppressUnmapped && $this->isSimpleTrueTypeFontWithSafeDeclaredEncoding($objectBody, $baseEncoding, $widths)) {
            $suppressUnmapped = false;
        }

        return [
            'base' => $baseEncoding,
            'differences' => $differences,
            'suppressUnmapped' => $suppressUnmapped,
            'widths' => $widths,
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return array<int, float>
     */
    private function fontWidthsFromObject(string $objectBody, array $objects): array
    {
        $firstChar = $this->integerDictionaryValue($objectBody, 'FirstChar');
        if ($firstChar === null) {
            return [];
        }

        $widthValues = null;
        if (preg_match('/\/Widths\s*(\[(?:[^\[\]]|\R)*\])/s', $objectBody, $match) === 1) {
            $widthValues = $this->numericArrayValues($match[1]);
        } elseif (preg_match('/\/Widths\s+(\d+)\s+\d+\s+R\b/', $objectBody, $match) === 1) {
            $widthObject = trim($objects[(int) $match[1]] ?? '');
            if (str_starts_with($widthObject, '[')) {
                $widthValues = $this->numericArrayValues($widthObject);
            }
        }

        if ($widthValues === null || $widthValues === []) {
            return [];
        }

        $widths = [];
        foreach ($widthValues as $index => $width) {
            $widths[$firstChar + $index] = $width;
        }

        return $widths;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     * @return array{widths?: array<int, float>, defaultWidth?: float}
     */
    private function cidWidthMetricsFromFontObject(string $objectBody, array $objects, array $seen = []): array
    {
        $widths = $this->cidWidthsFromObject($objectBody, $objects);
        $defaultWidth = $this->numericDictionaryValue($objectBody, 'DW');

        foreach ($this->descendantFontObjectNumbers($objectBody, $objects) as $objectNumber) {
            if (isset($seen[$objectNumber]) || !isset($objects[$objectNumber])) {
                continue;
            }

            $childMetrics = $this->cidWidthMetricsFromFontObject($objects[$objectNumber], $objects, $seen + [$objectNumber => true]);
            if (($childMetrics['widths'] ?? []) !== []) {
                $widths = array_replace($widths, $childMetrics['widths']);
            }
            if ($defaultWidth === null && array_key_exists('defaultWidth', $childMetrics)) {
                $defaultWidth = $childMetrics['defaultWidth'];
            }
        }

        $metrics = [];
        if ($widths !== []) {
            $metrics['widths'] = $widths;
        }
        if ($defaultWidth !== null) {
            $metrics['defaultWidth'] = $defaultWidth;
        }

        return $metrics;
    }

    /**
     * @param array<int, string> $objects
     * @return array<int, float>
     */
    private function cidWidthsFromObject(string $objectBody, array $objects): array
    {
        $array = $this->cidWidthArrayFromObject($objectBody, $objects);
        if ($array === null) {
            return [];
        }

        $tokens = $this->arrayTokens($array);
        $widths = [];
        $index = 0;
        $count = count($tokens);
        while ($index < $count) {
            $firstCid = $this->numericOperand($tokens[$index]);
            if ($firstCid === null) {
                $index++;
                continue;
            }
            $index++;

            if (!isset($tokens[$index])) {
                break;
            }

            if (str_starts_with(trim($tokens[$index]), '[')) {
                foreach ($this->numericArrayValues($tokens[$index]) as $offset => $width) {
                    $widths[(int) $firstCid + $offset] = $width;
                }
                $index++;
                continue;
            }

            $lastCid = $this->numericOperand($tokens[$index]);
            $width = $this->numericOperand($tokens[$index + 1] ?? '');
            if ($lastCid === null || $width === null) {
                continue;
            }
            $index += 2;

            $first = (int) $firstCid;
            $last = (int) $lastCid;
            if ($last < $first || $last - $first > 65535) {
                continue;
            }

            for ($cid = $first; $cid <= $last; $cid++) {
                $widths[$cid] = $width;
            }
        }

        return $widths;
    }

    /**
     * @param array<int, string> $objects
     */
    private function cidWidthArrayFromObject(string $objectBody, array $objects): ?string
    {
        $array = $this->arrayDictionaryValue($objectBody, 'W');
        if ($array !== null) {
            return $array;
        }

        if (preg_match('/\/W(?![A-Za-z0-9_.#-])\s+(\d+)\s+\d+\s+R\b/', $objectBody, $match) === 1) {
            $widthObject = trim($objects[(int) $match[1]] ?? '');
            if (str_starts_with($widthObject, '[')) {
                return $widthObject;
            }
        }

        return null;
    }

    private function arrayDictionaryValue(string $dictionary, string $name): ?string
    {
        if (preg_match('/\/' . preg_quote($name, '/') . '(?![A-Za-z0-9_.#-])/', $dictionary, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $index = $match[0][1] + strlen($match[0][0]);
        $length = strlen($dictionary);
        while ($index < $length && ctype_space($dictionary[$index])) {
            $index++;
        }

        if ($index >= $length || $dictionary[$index] !== '[') {
            return null;
        }

        return $this->readArrayToken($dictionary, $index);
    }

    private function numericDictionaryValue(string $dictionary, string $name): ?float
    {
        if (preg_match('/\/' . preg_quote($name, '/') . '(?![A-Za-z0-9_.#-])\s+([+-]?(?:\d+(?:\.\d*)?|\.\d+))\b/', $dictionary, $match) !== 1) {
            return null;
        }

        return (float) $match[1];
    }

    /**
     * @return list<float>
     */
    private function numericArrayValues(string $array): array
    {
        $values = [];
        foreach ($this->arrayTokens($array) as $token) {
            $value = $this->numericOperand($token);
            if ($value !== null) {
                $values[] = $value;
            }
        }

        return $values;
    }

    private function supportedSimpleFontEncoding(string $encoding): ?string
    {
        return match ($encoding) {
            'WinAnsiEncoding', 'MacRomanEncoding', 'StandardEncoding', 'SymbolEncoding' => $encoding,
            'ZapfDingbatsEncoding', 'DingbatsEncoding' => 'ZapfDingbatsEncoding',
            default => null,
        };
    }

    private function isType3FontWithSafeDeclaredEncoding(string $objectBody, string $baseEncoding): bool
    {
        if (preg_match('/\/Subtype\s*\/Type3\b/', $objectBody) !== 1) {
            return false;
        }

        return in_array($baseEncoding, ['WinAnsiEncoding', 'MacRomanEncoding', 'StandardEncoding', 'SymbolEncoding', 'ZapfDingbatsEncoding'], true);
    }

    /**
     * @param array<int, float> $widths
     */
    private function isSimpleTrueTypeFontWithSafeDeclaredEncoding(string $objectBody, string $baseEncoding, array $widths): bool
    {
        if (preg_match('/\/Subtype\s*\/TrueType\b/', $objectBody) !== 1) {
            return false;
        }

        if (!in_array($baseEncoding, ['WinAnsiEncoding', 'MacRomanEncoding', 'StandardEncoding'], true)) {
            return false;
        }

        if ($widths === []) {
            return false;
        }

        if (preg_match('/\/Differences\b/', $objectBody) === 1) {
            return false;
        }

        return preg_match('/\/Encoding\s*(?:\/' . preg_quote($baseEncoding, '/') . '\b|\d+\s+\d+\s+R\b)/', $objectBody) === 1;
    }

    private function implicitStandardFontEncoding(string $objectBody): ?string
    {
        if (preg_match('/\/BaseFont\s*\/([A-Za-z0-9_.#+-]+)/', $objectBody, $match) !== 1) {
            return null;
        }

        $baseFont = $this->decodePdfName($match[1]);
        if (preg_match('/^[A-Z]{6}\+/', $baseFont) === 1) {
            return null;
        }

        $baseFont = preg_replace('/,[A-Za-z-]+$/', '', $baseFont) ?? $baseFont;

        return match ($baseFont) {
            'Symbol' => 'SymbolEncoding',
            'ZapfDingbats' => 'ZapfDingbatsEncoding',
            default => null,
        };
    }

    /**
     * @param array<int, string> $objects
     */
    private function cidEncodingNameFromFontObject(string $objectBody, array $objects): ?string
    {
        if (preg_match('/\/Encoding\s+(\d+)\s+\d+\s+R\b/', $objectBody, $match) === 1) {
            return $this->cidEncodingNameFromObject((int) $match[1], $objects);
        }

        if (preg_match('/\/Encoding\s*\/([^\s\[\]<>\/%()]+)/', $objectBody, $match) !== 1) {
            return null;
        }

        return $this->decodePdfName($match[1]);
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     */
    private function cidEncodingNameFromObject(int $objectNumber, array $objects, array $seen = []): ?string
    {
        if (isset($seen[$objectNumber]) || !isset($objects[$objectNumber])) {
            return null;
        }

        $seen[$objectNumber] = true;
        $objectBody = $objects[$objectNumber];
        $trimmed = trim($objectBody);

        if (preg_match('/^\/([^\s\[\]<>\/%()]+)/', $trimmed, $match) === 1) {
            return $this->decodePdfName($match[1]);
        }

        $stream = $this->streamObjectParts($objectBody);
        if ($stream !== null) {
            $decoded = $this->decodeStream($stream['dictionary'], $stream['stream'], $objects);
            if ($decoded !== null) {
                $name = $this->cMapName($decoded);
                if ($name !== null) {
                    return $name;
                }

                if (stripos($decoded, 'begincmap') !== false || stripos($decoded, 'endcmap') !== false) {
                    return 'EmbeddedCMap';
                }
            }
        }

        $name = $this->cMapName($objectBody);
        if ($name !== null) {
            return $name;
        }

        if (preg_match('/\/Encoding\s+(\d+)\s+\d+\s+R\b/', $objectBody, $match) === 1) {
            return $this->cidEncodingNameFromObject((int) $match[1], $objects, $seen);
        }

        if (preg_match('/\/Encoding\s*\/([^\s\[\]<>\/%()]+)/', $objectBody, $match) === 1) {
            return $this->decodePdfName($match[1]);
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     * @return array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>, writingMode?: int}|null
     */
    private function unicodeCidEncodingMapForFontObject(string $objectBody, array $objects): ?array
    {
        if (preg_match('/\/Subtype\s*\/Type0\b/', $objectBody) !== 1) {
            return null;
        }

        $cmap = $this->cidEncodingCMapFromFontObject($objectBody, $objects);
        $namedCMaps = $this->namedToUnicodeCMapStreams($objects);
        if ($cmap === null || !$this->cidCMapCanBeUnicodeFallback($cmap, $namedCMaps)) {
            return null;
        }

        return $this->parseUnicodeCidEncodingCMap($cmap, $namedCMaps);
    }

    /**
     * @param array<int, string> $objects
     * @return array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>, suppressUnmapped?: bool}|null
     */
    private function cidKeyedCffToUnicodeMapForFontObject(string $objectBody, array $objects): ?array
    {
        if (preg_match('/\/Subtype\s*\/CIDFontType0\b/', $objectBody) !== 1) {
            return null;
        }

        $pdfOrdering = $this->cidSystemOrderingFromFontObject($objectBody);
        foreach ($this->fontDescriptorBodies($objectBody, $objects) as $descriptorBody) {
            foreach ($this->fontFileObjectNumbers($descriptorBody, ['/FontFile3']) as $fontFileObjectNumber) {
                if (!isset($objects[$fontFileObjectNumber])) {
                    continue;
                }

                $fontProgram = $this->decodeStreamObject($objects[$fontFileObjectNumber], $objects);
                if ($fontProgram === null) {
                    continue;
                }

                $map = $this->cffCidKeyedFontProgramToUnicodeMap($fontProgram, $pdfOrdering);
                if ($map !== null && ($map['map'] !== [] || $map['codeSpaceRanges'] !== [])) {
                    return $map;
                }
            }
        }

        return null;
    }

    private function cidSystemOrderingFromFontObject(string $objectBody): ?string
    {
        if (preg_match('/\/CIDSystemInfo\s*<<(.*?)>>/s', $objectBody, $match) !== 1) {
            return null;
        }

        if (preg_match('/\/Ordering\s*\((.*?)\)/s', $match[1], $orderingMatch) === 1) {
            return $this->decodeLiteralString($orderingMatch[1]);
        }

        if (preg_match('/\/Ordering\s*\/([^\s\[\]<>\/%()]+)/', $match[1], $orderingMatch) === 1) {
            return $this->decodePdfName($orderingMatch[1]);
        }

        return null;
    }

    private function cidOrderingCanBeUnicodeFallback(?string $ordering): bool
    {
        return $ordering !== null && preg_match('/(?:^|[-_.\s])(ucs|unicode)(?:[-_.\s]|$)/i', $ordering) === 1;
    }

    /**
     * @param array<int, string> $objects
     * @return array{map: array<string, int>, codeSpaceRanges: list<array{start: int, end: int, width: int}>, writingMode: int}|null
     */
    private function cidCharacterMapForFontObject(string $objectBody, array $objects): ?array
    {
        $cmap = $this->cidEncodingCMapFromFontObject($objectBody, $objects);
        if ($cmap === null) {
            return null;
        }

        return $this->parseCidEncodingCMap($cmap, $this->namedToUnicodeCMapStreams($objects));
    }

    /**
     * @param array<int, string> $objects
     */
    private function cidEncodingCMapFromFontObject(string $objectBody, array $objects): ?string
    {
        if (preg_match('/\/Encoding\s+(\d+)\s+\d+\s+R\b/', $objectBody, $match) === 1) {
            return $this->cidEncodingCMapFromObject((int) $match[1], $objects);
        }

        if (preg_match('/\/Encoding\s*\/([^\s\[\]<>\/%()]+)/', $objectBody, $match) === 1) {
            return $this->cidEncodingCMapFromName($this->decodePdfName($match[1]), $objects);
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     */
    private function cidEncodingCMapFromObject(int $objectNumber, array $objects, array $seen = []): ?string
    {
        if (isset($seen[$objectNumber]) || !isset($objects[$objectNumber])) {
            return null;
        }

        $seen[$objectNumber] = true;
        $objectBody = $objects[$objectNumber];
        $trimmed = trim($objectBody);

        if (preg_match('/^\/([^\s\[\]<>\/%()]+)/', $trimmed, $match) === 1) {
            return $this->cidEncodingCMapFromName($this->decodePdfName($match[1]), $objects);
        }

        $stream = $this->streamObjectParts($objectBody);
        if ($stream !== null) {
            $decoded = $this->decodeStream($stream['dictionary'], $stream['stream'], $objects);
            if ($decoded !== null && stripos($decoded, 'begincmap') !== false) {
                return $decoded;
            }
        }

        if (stripos($objectBody, 'begincmap') !== false) {
            return $objectBody;
        }

        if (preg_match('/\/Encoding\s+(\d+)\s+\d+\s+R\b/', $objectBody, $match) === 1) {
            return $this->cidEncodingCMapFromObject((int) $match[1], $objects, $seen);
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     */
    private function cidEncodingCMapFromName(string $name, array $objects): ?string
    {
        if ($name === 'Identity-H' || $name === 'Identity-V') {
            return null;
        }

        $namedCMaps = $this->namedToUnicodeCMapStreams($objects);
        if (isset($namedCMaps[$name])) {
            return $namedCMaps[$name];
        }

        return $this->predefinedCidEncodingCMap($name);
    }

    private function predefinedCidEncodingCMap(string $name): ?string
    {
        if (preg_match('/\A[A-Za-z0-9._-]+\z/', $name) !== 1) {
            return null;
        }

        static $cache = [];
        if (array_key_exists($name, $cache)) {
            return $cache[$name];
        }

        $path = __DIR__ . '/Resources/CMap/' . $name;
        if (!is_file($path) || !is_readable($path)) {
            $cache[$name] = null;
            return null;
        }

        $cmap = file_get_contents($path);
        if (!is_string($cmap) || stripos($cmap, 'begincmap') === false) {
            $cache[$name] = null;
            return null;
        }

        $cache[$name] = $cmap;
        return $cmap;
    }

    /**
     * @param array<string, string> $namedCMaps
     * @param array<string, true> $seenCMaps
     */
    private function cidCMapCanBeUnicodeFallback(string $cmap, array $namedCMaps = [], array $seenCMaps = []): bool
    {
        $cmap = $this->stripCMapComments($cmap);
        $name = $this->cMapName($cmap);
        if ($name !== null && preg_match('/(?:^|[-_.])(ucs2?|utf8|utf16|utf32|unicode)(?:[-_.]|$)/i', $name) === 1) {
            return true;
        }

        if (preg_match('/\/Ordering\s*\((.*?)\)/s', $cmap, $match) === 1) {
            $ordering = $this->decodeLiteralString($match[1]);
            if (preg_match('/(?:^|[-_.\s])(ucs|unicode)(?:[-_.\s]|$)/i', $ordering) === 1) {
                return true;
            }
        }

        if (preg_match('/\/Ordering\s*\/([^\s\[\]<>\/%()]+)/', $cmap, $match) === 1) {
            $ordering = $this->decodePdfName($match[1]);
            if (preg_match('/(?:^|[-_.])(ucs|unicode)(?:[-_.]|$)/i', $ordering) === 1) {
                return true;
            }
        }

        foreach ($this->parseCMapUseNames($cmap) as $useName) {
            if (isset($seenCMaps[$useName])) {
                continue;
            }

            $baseCMap = $this->cMapBodyForUseName($useName, $namedCMaps);
            if ($baseCMap !== null && $this->cidCMapCanBeUnicodeFallback($baseCMap, $namedCMaps, $seenCMaps + [$useName => true])) {
                return true;
            }
        }

        return false;
    }

    private function cidCMapUnicodeSourceEncoding(string $cmap): ?string
    {
        $name = $this->cMapName($cmap);
        if ($name === null) {
            return null;
        }

        if (preg_match('/(?:^|[-_.])(ucs2|utf16|utf32|utf8)(?:[-_.]|$)/i', $name, $match) !== 1) {
            return null;
        }

        return match (strtolower($match[1])) {
            'ucs2', 'utf16' => 'UTF-16BE',
            'utf32' => 'UTF-32BE',
            'utf8' => 'UTF-8',
            default => null,
        };
    }

    private function decodeCMapUnicodeSourceHex(string $hex, string $encoding): ?string
    {
        $normalized = $this->normalizeHexKey($hex);
        if ($normalized === '' || strlen($normalized) % 2 !== 0) {
            return null;
        }

        $bytes = hex2bin($normalized);
        if ($bytes === false || $bytes === '') {
            return null;
        }

        $decoded = $encoding === 'UTF-8'
            ? iconv('UTF-8', 'UTF-8//IGNORE', $bytes)
            : iconv($encoding, 'UTF-8//IGNORE', $bytes);

        return $decoded === false || $decoded === '' ? null : $decoded;
    }

    private function stripCMapComments(string $cmap): string
    {
        $result = '';
        $length = strlen($cmap);
        $inLiteralString = false;
        $literalDepth = 0;
        $escaped = false;

        for ($offset = 0; $offset < $length; $offset++) {
            $char = $cmap[$offset];

            if ($inLiteralString) {
                $result .= $char;

                if ($escaped) {
                    $escaped = false;
                    continue;
                }

                if ($char === '\\') {
                    $escaped = true;
                    continue;
                }

                if ($char === '(') {
                    $literalDepth++;
                    continue;
                }

                if ($char === ')') {
                    $literalDepth--;
                    if ($literalDepth <= 0) {
                        $inLiteralString = false;
                        $literalDepth = 0;
                    }
                }

                continue;
            }

            if ($char === '(') {
                $inLiteralString = true;
                $literalDepth = 1;
                $result .= $char;
                continue;
            }

            if ($char === '%') {
                while ($offset < $length && $cmap[$offset] !== "\n" && $cmap[$offset] !== "\r") {
                    $offset++;
                }

                if ($offset < $length) {
                    if ($cmap[$offset] === "\r" && $offset + 1 < $length && $cmap[$offset + 1] === "\n") {
                        $result .= "\r\n";
                        $offset++;
                    } else {
                        $result .= $cmap[$offset];
                    }
                }

                continue;
            }

            $result .= $char;
        }

        return $result;
    }

    private function isUnmappedCustomFont(string $objectBody): bool
    {
        if (preg_match('/\/ToUnicode\s+\d+\s+\d+\s+R\b/', $objectBody) === 1) {
            return false;
        }

        $baseFont = '';
        if (preg_match('/\/BaseFont\s*\/([A-Za-z0-9_.#+-]+)/', $objectBody, $match) === 1) {
            $baseFont = $this->decodePdfName($match[1]);
        }

        if ($baseFont === '') {
            return true;
        }

        if (preg_match('/^[A-Z]{6}\+/', $baseFont) === 1) {
            return true;
        }

        return !$this->isStandardSimpleFont($baseFont);
    }

    private function isStandardSimpleFont(string $baseFont): bool
    {
        $baseFont = ltrim($baseFont, '/');
        $baseFont = preg_replace('/,[A-Za-z-]+$/', '', $baseFont) ?? $baseFont;

        return in_array($baseFont, [
            'Courier',
            'Courier-Bold',
            'Courier-BoldOblique',
            'Courier-Oblique',
            'Helvetica',
            'Helvetica-Bold',
            'Helvetica-BoldOblique',
            'Helvetica-Oblique',
            'Symbol',
            'Times-Bold',
            'Times-BoldItalic',
            'Times-Italic',
            'Times-Roman',
            'ZapfDingbats',
        ], true);
    }

    /**
     * @return array<int, string>
     */
    private function fontEncodingDifferences(string $objectBody): array
    {
        if (preg_match('/\/Differences\s*\[(.*?)\]/s', $objectBody, $match) !== 1) {
            return [];
        }

        preg_match_all('/\/([^\s\[\]<>\/%()]+)|([+-]?\d+)/', $match[1], $tokens, PREG_SET_ORDER);
        $differences = [];
        $currentCode = null;
        foreach ($tokens as $token) {
            if (($token[2] ?? '') !== '') {
                $currentCode = (int) $token[2];
                continue;
            }

            if ($currentCode === null || $currentCode < 0 || $currentCode > 255) {
                continue;
            }

            $unicode = $this->glyphNameToUnicode($token[1]);
            if ($unicode !== null) {
                $differences[$currentCode] = $unicode;
            }
            $currentCode++;
        }

        return $differences;
    }

    /**
     * @param array<int, string> $objects
     * @return array<int, string>
     */
    private function embeddedType1FontEncodingDifferences(string $fontObjectBody, array $objects): array
    {
        foreach ($this->fontDescriptorBodies($fontObjectBody, $objects) as $descriptorBody) {
            foreach ($this->fontFileObjectNumbers($descriptorBody, ['/FontFile']) as $fontFileObjectNumber) {
                if (!isset($objects[$fontFileObjectNumber])) {
                    continue;
                }

                $fontProgram = $this->decodeStreamObject($objects[$fontFileObjectNumber], $objects);
                if ($fontProgram === null) {
                    continue;
                }

                $differences = $this->type1FontProgramEncodingDifferences($fontProgram);
                if ($differences !== []) {
                    return $differences;
                }
            }
        }

        return [];
    }

    /**
     * @param array<int, string> $objects
     * @return array<int, string>
     */
    private function embeddedTrueTypeFontEncodingDifferences(string $fontObjectBody, array $objects): array
    {
        foreach ($this->fontDescriptorBodies($fontObjectBody, $objects) as $descriptorBody) {
            foreach ($this->fontFileObjectNumbers($descriptorBody, ['/FontFile2']) as $fontFileObjectNumber) {
                if (!isset($objects[$fontFileObjectNumber])) {
                    continue;
                }

                $fontProgram = $this->decodeStreamObject($objects[$fontFileObjectNumber], $objects);
                if ($fontProgram === null) {
                    continue;
                }

                $differences = $this->trueTypeFontProgramEncodingDifferences($fontProgram);
                if ($differences !== []) {
                    return $differences;
                }
            }
        }

        return [];
    }

    /**
     * @param array<int, string> $objects
     * @return array<int, string>
     */
    private function embeddedCffFontEncodingDifferences(string $fontObjectBody, array $objects): array
    {
        foreach ($this->fontDescriptorBodies($fontObjectBody, $objects) as $descriptorBody) {
            foreach ($this->fontFileObjectNumbers($descriptorBody, ['/FontFile3']) as $fontFileObjectNumber) {
                if (!isset($objects[$fontFileObjectNumber])) {
                    continue;
                }

                $fontProgram = $this->decodeStreamObject($objects[$fontFileObjectNumber], $objects);
                if ($fontProgram === null) {
                    continue;
                }

                $differences = $this->cffFontProgramEncodingDifferences($fontProgram);
                if ($differences !== []) {
                    return $differences;
                }
            }
        }

        return [];
    }

    /**
     * @param array<int, string> $objects
     * @return list<string>
     */
    private function fontDescriptorBodies(string $fontObjectBody, array $objects): array
    {
        $descriptors = [];
        $tokens = $this->dictionaryTokens($fontObjectBody);
        for ($index = 0, $count = count($tokens); $index < $count; $index++) {
            if ($tokens[$index] !== '/FontDescriptor') {
                continue;
            }

            $objectNumber = $this->indirectObjectOperand($tokens, $index + 1);
            if ($objectNumber !== null && isset($objects[$objectNumber])) {
                $descriptors[] = $objects[$objectNumber];
                continue;
            }

            $next = $tokens[$index + 1] ?? null;
            if (is_string($next) && str_starts_with(trim($next), '<<')) {
                $descriptors[] = $next;
            }
        }

        return $descriptors;
    }

    /**
     * @return list<int>
     */
    private function fontFileObjectNumbers(string $descriptorBody, array $fontFileNames = ['/FontFile']): array
    {
        $objectNumbers = [];
        $tokens = $this->dictionaryTokens($descriptorBody);
        for ($index = 0, $count = count($tokens); $index < $count; $index++) {
            if (!in_array($tokens[$index], $fontFileNames, true)) {
                continue;
            }

            $objectNumber = $this->indirectObjectOperand($tokens, $index + 1);
            if ($objectNumber !== null) {
                $objectNumbers[] = $objectNumber;
            }
        }

        return array_values(array_unique($objectNumbers));
    }

    /**
     * @return array<int, string>
     */
    private function trueTypeFontProgramEncodingDifferences(string $fontProgram): array
    {
        $cmap = $this->trueTypeFontTable($fontProgram, 'cmap');
        if ($cmap === null) {
            return [];
        }

        return $this->trueTypeCMapEncodingDifferences($cmap, $this->trueTypePostGlyphNames($fontProgram));
    }

    /**
     * @return array<int, string>
     */
    private function cffFontProgramEncodingDifferences(string $fontProgram): array
    {
        if (strlen($fontProgram) < 4 || ord($fontProgram[0]) !== 1) {
            return [];
        }

        $offset = ord($fontProgram[2]);
        if ($offset < 4 || $offset > strlen($fontProgram)) {
            return [];
        }

        $nameIndex = $this->cffReadIndex($fontProgram, $offset);
        if ($nameIndex === null) {
            return [];
        }

        $topDictIndex = $this->cffReadIndex($fontProgram, $nameIndex['next']);
        if ($topDictIndex === null || ($topDictIndex['objects'][0] ?? '') === '') {
            return [];
        }

        $stringIndex = $this->cffReadIndex($fontProgram, $topDictIndex['next']);
        if ($stringIndex === null) {
            return [];
        }

        $globalSubrIndex = $this->cffReadIndex($fontProgram, $stringIndex['next']);
        if ($globalSubrIndex === null) {
            return [];
        }

        $operators = $this->cffDictOperators($topDictIndex['objects'][0]);
        if (isset($operators['12 30'])) {
            return [];
        }

        $charsetOffset = $this->cffDictIntegerOperand($operators, '15') ?? 0;
        $encodingOffset = $this->cffDictIntegerOperand($operators, '16') ?? 0;
        $charStringsOffset = $this->cffDictIntegerOperand($operators, '17');
        if ($charStringsOffset === null) {
            return [];
        }

        $charStringsIndex = $this->cffReadIndex($fontProgram, $charStringsOffset);
        if ($charStringsIndex === null || $charStringsIndex['objects'] === []) {
            return [];
        }

        $glyphNames = $this->cffCharsetGlyphNames($fontProgram, $charsetOffset, count($charStringsIndex['objects']), $stringIndex['objects']);
        if ($glyphNames === []) {
            return [];
        }

        $codeToGlyphId = $this->cffEncodingCodeToGlyphId($fontProgram, $encodingOffset, count($charStringsIndex['objects']), $glyphNames);
        if ($codeToGlyphId === []) {
            return [];
        }

        $differences = [];
        foreach ($codeToGlyphId as $code => $glyphId) {
            $glyphName = $glyphNames[$glyphId] ?? null;
            if ($glyphName === null) {
                continue;
            }

            $unicode = $this->glyphNameToUnicode($glyphName);
            if ($unicode !== null) {
                $differences[$code] = $unicode;
            }
        }

        ksort($differences);
        return $differences;
    }

    /**
     * @return array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>, suppressUnmapped: bool}|null
     */
    private function cffCidKeyedFontProgramToUnicodeMap(string $fontProgram, ?string $pdfOrdering): ?array
    {
        if (strlen($fontProgram) < 4 || ord($fontProgram[0]) !== 1) {
            return null;
        }

        $offset = ord($fontProgram[2]);
        if ($offset < 4 || $offset > strlen($fontProgram)) {
            return null;
        }

        $nameIndex = $this->cffReadIndex($fontProgram, $offset);
        if ($nameIndex === null) {
            return null;
        }

        $topDictIndex = $this->cffReadIndex($fontProgram, $nameIndex['next']);
        if ($topDictIndex === null || ($topDictIndex['objects'][0] ?? '') === '') {
            return null;
        }

        $stringIndex = $this->cffReadIndex($fontProgram, $topDictIndex['next']);
        if ($stringIndex === null) {
            return null;
        }

        $globalSubrIndex = $this->cffReadIndex($fontProgram, $stringIndex['next']);
        if ($globalSubrIndex === null) {
            return null;
        }

        $operators = $this->cffDictOperators($topDictIndex['objects'][0]);
        $rosOrdering = $this->cffRosOrdering($operators, $stringIndex['objects']);
        if (!$this->cidOrderingCanBeUnicodeFallback($pdfOrdering) && !$this->cidOrderingCanBeUnicodeFallback($rosOrdering)) {
            return null;
        }

        $charStringsOffset = $this->cffDictIntegerOperand($operators, '17');
        if ($charStringsOffset === null) {
            return null;
        }

        $charStringsIndex = $this->cffReadIndex($fontProgram, $charStringsOffset);
        if ($charStringsIndex === null || $charStringsIndex['objects'] === []) {
            return null;
        }

        $charsetOffset = $this->cffDictIntegerOperand($operators, '15') ?? 0;
        $cids = $this->cffCidCharset($fontProgram, $charsetOffset, count($charStringsIndex['objects']));
        if ($cids === []) {
            return null;
        }

        $map = [];
        foreach ($cids as $cid) {
            $unicode = $this->unicodeScalarFromCid($cid);
            if ($unicode === null) {
                continue;
            }

            $map[str_pad(strtolower(dechex($cid)), 4, '0', STR_PAD_LEFT)] = $unicode;
        }

        if ($map === []) {
            return null;
        }

        ksort($map);
        return [
            'map' => $map,
            'codeSpaceRanges' => [
                [
                    'start' => 0,
                    'end' => 0xFFFF,
                    'width' => 4,
                ],
            ],
            'suppressUnmapped' => true,
        ];
    }

    /**
     * @param array<string, list<int|float>> $operators
     * @param list<string> $strings
     */
    private function cffRosOrdering(array $operators, array $strings): ?string
    {
        $operands = $operators['12 30'] ?? [];
        $orderingSid = $operands[1] ?? null;
        if (!is_int($orderingSid) && !is_float($orderingSid)) {
            return null;
        }

        return $this->cffStringName((int) $orderingSid, $strings);
    }

    /**
     * @return array{objects: list<string>, next: int}|null
     */
    private function cffReadIndex(string $bytes, int $offset): ?array
    {
        if ($offset < 0 || $offset + 2 > strlen($bytes)) {
            return null;
        }

        $count = $this->trueTypeUnsignedShort($bytes, $offset);
        if ($count === null) {
            return null;
        }
        $offset += 2;
        if ($count === 0) {
            return ['objects' => [], 'next' => $offset];
        }

        if ($offset >= strlen($bytes)) {
            return null;
        }

        $offSize = ord($bytes[$offset]);
        $offset++;
        if ($offSize < 1 || $offSize > 4) {
            return null;
        }

        $offsets = [];
        for ($index = 0; $index <= $count; $index++) {
            if ($offset + $offSize > strlen($bytes)) {
                return null;
            }

            $offsets[] = $this->unsignedIntegerFromBytes(substr($bytes, $offset, $offSize));
            $offset += $offSize;
        }

        $dataStart = $offset;
        $lastOffset = end($offsets);
        if (!is_int($lastOffset) || $lastOffset < 1 || $dataStart + $lastOffset - 1 > strlen($bytes)) {
            return null;
        }

        $objects = [];
        for ($index = 0; $index < $count; $index++) {
            $start = $offsets[$index];
            $end = $offsets[$index + 1];
            if ($start < 1 || $end < $start) {
                return null;
            }

            $objects[] = substr($bytes, $dataStart + $start - 1, $end - $start);
        }

        return ['objects' => $objects, 'next' => $dataStart + $lastOffset - 1];
    }

    /**
     * @return array<string, list<int|float>>
     */
    private function cffDictOperators(string $dict): array
    {
        $operators = [];
        $operands = [];
        $offset = 0;
        $length = strlen($dict);
        while ($offset < $length) {
            $byte = ord($dict[$offset]);
            if ($byte <= 21 && $byte !== 12) {
                $operators[(string) $byte] = $operands;
                $operands = [];
                $offset++;
                continue;
            }

            if ($byte === 12) {
                if ($offset + 1 >= $length) {
                    break;
                }

                $operators['12 ' . ord($dict[$offset + 1])] = $operands;
                $operands = [];
                $offset += 2;
                continue;
            }

            $number = $this->cffReadDictNumber($dict, $offset);
            if ($number === null) {
                break;
            }

            $operands[] = $number;
        }

        return $operators;
    }

    private function cffReadDictNumber(string $dict, int &$offset): int|float|null
    {
        if ($offset >= strlen($dict)) {
            return null;
        }

        $byte = ord($dict[$offset]);
        $offset++;
        if ($byte >= 32 && $byte <= 246) {
            return $byte - 139;
        }

        if ($byte >= 247 && $byte <= 250) {
            if ($offset >= strlen($dict)) {
                return null;
            }

            $next = ord($dict[$offset]);
            $offset++;
            return (($byte - 247) * 256) + $next + 108;
        }

        if ($byte >= 251 && $byte <= 254) {
            if ($offset >= strlen($dict)) {
                return null;
            }

            $next = ord($dict[$offset]);
            $offset++;
            return -(($byte - 251) * 256) - $next - 108;
        }

        if ($byte === 28) {
            if ($offset + 2 > strlen($dict)) {
                return null;
            }

            $value = $this->trueTypeSignedShort($dict, $offset);
            $offset += 2;
            return $value;
        }

        if ($byte === 29) {
            if ($offset + 4 > strlen($dict)) {
                return null;
            }

            $value = $this->trueTypeUnsignedLong($dict, $offset);
            $offset += 4;
            if ($value === null) {
                return null;
            }

            return $value >= 0x80000000 ? $value - 0x100000000 : $value;
        }

        if ($byte === 30) {
            return $this->cffReadRealNumber($dict, $offset);
        }

        if ($byte === 255) {
            if ($offset + 4 > strlen($dict)) {
                return null;
            }

            $integer = $this->trueTypeSignedShort($dict, $offset);
            $fraction = $this->trueTypeUnsignedShort($dict, $offset + 2);
            $offset += 4;
            if ($integer === null || $fraction === null) {
                return null;
            }

            return $integer + ($fraction / 65536);
        }

        return null;
    }

    private function cffReadRealNumber(string $dict, int &$offset): ?float
    {
        $text = '';
        while ($offset < strlen($dict)) {
            $byte = ord($dict[$offset]);
            $offset++;
            foreach ([($byte >> 4) & 0x0F, $byte & 0x0F] as $nibble) {
                if ($nibble <= 9) {
                    $text .= (string) $nibble;
                    continue;
                }

                if ($nibble === 0x0A) {
                    $text .= '.';
                    continue;
                }

                if ($nibble === 0x0B) {
                    $text .= 'E';
                    continue;
                }

                if ($nibble === 0x0C) {
                    $text .= 'E-';
                    continue;
                }

                if ($nibble === 0x0E) {
                    $text .= '-';
                    continue;
                }

                if ($nibble === 0x0F) {
                    return is_numeric($text) ? (float) $text : null;
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, list<int|float>> $operators
     */
    private function cffDictIntegerOperand(array $operators, string $operator, int $operandOffset = -1): ?int
    {
        $operands = $operators[$operator] ?? [];
        if ($operands === []) {
            return null;
        }

        $index = $operandOffset >= 0 ? $operandOffset : count($operands) + $operandOffset;
        $value = $operands[$index] ?? null;
        return is_int($value) ? $value : (is_float($value) ? (int) $value : null);
    }

    /**
     * @param list<string> $strings
     * @return array<int, string>
     */
    private function cffCharsetGlyphNames(string $fontProgram, int $charsetOffset, int $glyphCount, array $strings): array
    {
        if ($glyphCount <= 0) {
            return [];
        }

        if ($charsetOffset === 0) {
            $glyphNames = [];
            for ($glyphId = 0; $glyphId < $glyphCount; $glyphId++) {
                $glyphNames[$glyphId] = $this->cffStringName($glyphId, []) ?? '.notdef';
            }

            return $glyphNames;
        }

        if ($charsetOffset === 1 || $charsetOffset === 2) {
            $charsetSids = $charsetOffset === 1 ? self::CFF_EXPERT_CHARSET_SIDS : self::CFF_EXPERT_SUBSET_CHARSET_SIDS;
            $glyphNames = [0 => '.notdef'];
            for ($glyphId = 1; $glyphId < $glyphCount; $glyphId++) {
                $sid = $charsetSids[$glyphId - 1] ?? null;
                if ($sid === null) {
                    break;
                }

                $glyphNames[$glyphId] = $this->cffStringName($sid, []) ?? '.notdef';
            }

            return $glyphNames;
        }

        if ($charsetOffset < 0 || $charsetOffset >= strlen($fontProgram)) {
            return [];
        }

        $glyphNames = [0 => '.notdef'];
        $format = ord($fontProgram[$charsetOffset]);
        $offset = $charsetOffset + 1;
        if ($format === 0) {
            for ($glyphId = 1; $glyphId < $glyphCount; $glyphId++) {
                if ($offset + 2 > strlen($fontProgram)) {
                    return [];
                }

                $sid = $this->trueTypeUnsignedShort($fontProgram, $offset);
                $offset += 2;
                if ($sid === null) {
                    return [];
                }

                $glyphNames[$glyphId] = $this->cffStringName($sid, $strings) ?? '.notdef';
            }

            return $glyphNames;
        }

        if ($format !== 1 && $format !== 2) {
            return [];
        }

        $glyphId = 1;
        while ($glyphId < $glyphCount) {
            if ($offset + ($format === 1 ? 3 : 4) > strlen($fontProgram)) {
                return [];
            }

            $firstSid = $this->trueTypeUnsignedShort($fontProgram, $offset);
            $offset += 2;
            $left = $format === 1 ? ord($fontProgram[$offset]) : $this->trueTypeUnsignedShort($fontProgram, $offset);
            $offset += $format === 1 ? 1 : 2;
            if ($firstSid === null || $left === null) {
                return [];
            }

            for ($rangeOffset = 0; $rangeOffset <= $left && $glyphId < $glyphCount; $rangeOffset++, $glyphId++) {
                $glyphNames[$glyphId] = $this->cffStringName($firstSid + $rangeOffset, $strings) ?? '.notdef';
            }
        }

        return $glyphNames;
    }

    /**
     * @return list<int>
     */
    private function cffCidCharset(string $fontProgram, int $charsetOffset, int $glyphCount): array
    {
        if ($glyphCount <= 0) {
            return [];
        }

        if ($charsetOffset === 0) {
            return range(0, $glyphCount - 1);
        }

        if ($charsetOffset < 0 || $charsetOffset >= strlen($fontProgram)) {
            return [];
        }

        $cids = [0];
        $format = ord($fontProgram[$charsetOffset]);
        $offset = $charsetOffset + 1;
        if ($format === 0) {
            for ($glyphId = 1; $glyphId < $glyphCount; $glyphId++) {
                if ($offset + 2 > strlen($fontProgram)) {
                    return [];
                }

                $cid = $this->trueTypeUnsignedShort($fontProgram, $offset);
                $offset += 2;
                if ($cid === null) {
                    return [];
                }

                $cids[] = $cid;
            }

            return $cids;
        }

        if ($format !== 1 && $format !== 2) {
            return [];
        }

        $glyphId = 1;
        while ($glyphId < $glyphCount) {
            if ($offset + ($format === 1 ? 3 : 4) > strlen($fontProgram)) {
                return [];
            }

            $firstCid = $this->trueTypeUnsignedShort($fontProgram, $offset);
            $offset += 2;
            $left = $format === 1 ? ord($fontProgram[$offset]) : $this->trueTypeUnsignedShort($fontProgram, $offset);
            $offset += $format === 1 ? 1 : 2;
            if ($firstCid === null || $left === null) {
                return [];
            }

            for ($rangeOffset = 0; $rangeOffset <= $left && $glyphId < $glyphCount; $rangeOffset++, $glyphId++) {
                $cids[] = $firstCid + $rangeOffset;
            }
        }

        return $cids;
    }

    /**
     * @param array<int, string> $glyphNames
     * @return array<int, int>
     */
    private function cffEncodingCodeToGlyphId(string $fontProgram, int $encodingOffset, int $glyphCount, array $glyphNames = []): array
    {
        if ($glyphCount <= 1) {
            return [];
        }

        if ($encodingOffset === 0) {
            return $this->cffStandardEncodingCodeToGlyphId($glyphNames);
        }

        if ($encodingOffset === 1) {
            return $this->cffPredefinedEncodingCodeToGlyphId(self::CFF_EXPERT_ENCODING_SIDS, $glyphNames);
        }

        if ($encodingOffset < 0 || $encodingOffset >= strlen($fontProgram)) {
            return [];
        }

        $format = ord($fontProgram[$encodingOffset]);
        $baseFormat = $format & 0x7F;
        $offset = $encodingOffset + 1;
        $codeToGlyphId = [];
        if ($baseFormat === 0) {
            if ($offset >= strlen($fontProgram)) {
                return [];
            }

            $codeCount = ord($fontProgram[$offset]);
            $offset++;
            for ($glyphId = 1; $glyphId <= $codeCount && $glyphId < $glyphCount; $glyphId++) {
                if ($offset >= strlen($fontProgram)) {
                    return [];
                }

                $codeToGlyphId[ord($fontProgram[$offset])] = $glyphId;
                $offset++;
            }
        } elseif ($baseFormat === 1) {
            if ($offset >= strlen($fontProgram)) {
                return [];
            }

            $rangeCount = ord($fontProgram[$offset]);
            $offset++;
            $glyphId = 1;
            for ($range = 0; $range < $rangeCount && $glyphId < $glyphCount; $range++) {
                if ($offset + 2 > strlen($fontProgram)) {
                    return [];
                }

                $firstCode = ord($fontProgram[$offset]);
                $left = ord($fontProgram[$offset + 1]);
                $offset += 2;
                for ($rangeOffset = 0; $rangeOffset <= $left && $glyphId < $glyphCount; $rangeOffset++, $glyphId++) {
                    $codeToGlyphId[$firstCode + $rangeOffset] = $glyphId;
                }
            }
        } else {
            return [];
        }

        return $codeToGlyphId;
    }

    /**
     * @param array<int, string> $glyphNames
     * @return array<int, int>
     */
    private function cffStandardEncodingCodeToGlyphId(array $glyphNames): array
    {
        $codeToGlyphName = [];
        for ($code = 0; $code <= 255; $code++) {
            $glyphName = $this->cffStandardEncodingGlyphName($code);
            if ($glyphName === null) {
                continue;
            }

            $codeToGlyphName[$code] = $glyphName;
        }

        return $this->cffPredefinedEncodingNamesToGlyphId($codeToGlyphName, $glyphNames);
    }

    /**
     * @param array<int, int|null> $codeToSid
     * @param array<int, string> $glyphNames
     * @return array<int, int>
     */
    private function cffPredefinedEncodingCodeToGlyphId(array $codeToSid, array $glyphNames): array
    {
        $codeToGlyphName = [];
        foreach ($codeToSid as $code => $sid) {
            $glyphName = $this->cffStringName($sid, []);
            if ($glyphName !== null && $glyphName !== '.notdef') {
                $codeToGlyphName[$code] = $glyphName;
            }
        }

        return $this->cffPredefinedEncodingNamesToGlyphId($codeToGlyphName, $glyphNames);
    }

    /**
     * @param array<int, string> $codeToGlyphName
     * @param array<int, string> $glyphNames
     * @return array<int, int>
     */
    private function cffPredefinedEncodingNamesToGlyphId(array $codeToGlyphName, array $glyphNames): array
    {
        $glyphIdByName = [];
        foreach ($glyphNames as $glyphId => $glyphName) {
            if ($glyphName === '' || $glyphName === '.notdef' || isset($glyphIdByName[$glyphName])) {
                continue;
            }

            $glyphIdByName[$glyphName] = $glyphId;
        }

        if ($glyphIdByName === []) {
            return [];
        }

        $codeToGlyphId = [];
        foreach ($codeToGlyphName as $code => $glyphName) {
            if (!isset($glyphIdByName[$glyphName])) {
                continue;
            }

            $codeToGlyphId[$code] = $glyphIdByName[$glyphName];
        }

        return $codeToGlyphId;
    }

    private function cffStandardEncodingGlyphName(int $code): ?string
    {
        if ($code >= 32 && $code <= 126) {
            return $this->trueTypeStandardPostGlyphName($code - 29);
        }

        return match ($code) {
            161 => 'exclamdown',
            162 => 'cent',
            163 => 'sterling',
            164 => 'fraction',
            165 => 'yen',
            166 => 'florin',
            167 => 'section',
            168 => 'currency',
            169 => 'quotesingle',
            170 => 'quotedblleft',
            171 => 'guillemotleft',
            172 => 'guilsinglleft',
            173 => 'guilsinglright',
            174 => 'fi',
            175 => 'fl',
            177 => 'endash',
            178 => 'dagger',
            179 => 'daggerdbl',
            180 => 'periodcentered',
            182 => 'paragraph',
            183 => 'bullet',
            184 => 'quotesinglbase',
            185 => 'quotedblbase',
            186 => 'quotedblright',
            187 => 'guillemotright',
            188 => 'ellipsis',
            189 => 'perthousand',
            191 => 'questiondown',
            193 => 'grave',
            194 => 'acute',
            195 => 'circumflex',
            196 => 'tilde',
            197 => 'macron',
            198 => 'breve',
            199 => 'dotaccent',
            200 => 'dieresis',
            202 => 'ring',
            203 => 'cedilla',
            205 => 'hungarumlaut',
            206 => 'ogonek',
            207 => 'caron',
            208 => 'emdash',
            default => null,
        };
    }

    /**
     * @param list<string> $strings
     */
    private function cffStringName(int $sid, array $strings): ?string
    {
        if ($sid === 0) {
            return '.notdef';
        }

        if ($sid >= 391) {
            return $strings[$sid - 391] ?? null;
        }

        if (isset(self::CFF_STANDARD_STRING_NAMES[$sid])) {
            return self::CFF_STANDARD_STRING_NAMES[$sid];
        }

        if ($sid === 1) {
            return 'space';
        }

        if ($sid >= 17 && $sid <= 26) {
            return chr(ord('0') + $sid - 17);
        }

        if ($sid >= 34 && $sid <= 59) {
            return chr(ord('A') + $sid - 34);
        }

        if ($sid >= 66 && $sid <= 91) {
            return chr(ord('a') + $sid - 66);
        }

        return match ($sid) {
            2 => 'exclam',
            3 => 'quotedbl',
            4 => 'numbersign',
            5 => 'dollar',
            6 => 'percent',
            7 => 'ampersand',
            8 => 'quoteright',
            9 => 'parenleft',
            10 => 'parenright',
            11 => 'asterisk',
            12 => 'plus',
            13 => 'comma',
            14 => 'hyphen',
            15 => 'period',
            16 => 'slash',
            27 => 'colon',
            28 => 'semicolon',
            29 => 'less',
            30 => 'equal',
            31 => 'greater',
            32 => 'question',
            33 => 'at',
            60 => 'bracketleft',
            61 => 'backslash',
            62 => 'bracketright',
            63 => 'asciicircum',
            64 => 'underscore',
            65 => 'quoteleft',
            92 => 'braceleft',
            93 => 'bar',
            94 => 'braceright',
            95 => 'asciitilde',
            default => null,
        };
    }

    private function trueTypeFontTable(string $fontProgram, string $tableTag): ?string
    {
        if (strlen($fontProgram) < 12 || strlen($tableTag) !== 4) {
            return null;
        }

        $numTables = $this->trueTypeUnsignedShort($fontProgram, 4);
        if ($numTables === null) {
            return null;
        }

        $recordOffset = 12;
        for ($index = 0; $index < $numTables; $index++) {
            if ($recordOffset + 16 > strlen($fontProgram)) {
                return null;
            }

            $tag = substr($fontProgram, $recordOffset, 4);
            $tableOffset = $this->trueTypeUnsignedLong($fontProgram, $recordOffset + 8);
            $tableLength = $this->trueTypeUnsignedLong($fontProgram, $recordOffset + 12);
            if ($tag === $tableTag && $tableOffset !== null && $tableLength !== null) {
                if ($tableLength <= 0 || $tableOffset < 0 || $tableOffset + $tableLength > strlen($fontProgram)) {
                    return null;
                }

                return substr($fontProgram, $tableOffset, $tableLength);
            }

            $recordOffset += 16;
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function trueTypePostGlyphNames(string $fontProgram): array
    {
        $post = $this->trueTypeFontTable($fontProgram, 'post');
        if ($post === null || strlen($post) < 32) {
            return [];
        }

        $version = $this->trueTypeUnsignedLong($post, 0);
        if ($version === 0x00010000) {
            $glyphNames = [];
            for ($glyphId = 0; $glyphId < 258; $glyphId++) {
                $glyphName = $this->trueTypeStandardPostGlyphName($glyphId);
                if ($glyphName !== null) {
                    $glyphNames[$glyphId] = $glyphName;
                }
            }

            return $glyphNames;
        }

        if ($version === 0x00025000) {
            return $this->trueTypePost25GlyphNames($post);
        }

        if ($version !== 0x00020000 || strlen($post) < 34) {
            return [];
        }

        $glyphCount = $this->trueTypeUnsignedShort($post, 32);
        if ($glyphCount === null || $glyphCount < 1 || 34 + (2 * $glyphCount) > strlen($post)) {
            return [];
        }

        $indexes = [];
        $highestCustomIndex = -1;
        $offset = 34;
        for ($glyphId = 0; $glyphId < $glyphCount; $glyphId++, $offset += 2) {
            $index = $this->trueTypeUnsignedShort($post, $offset);
            if ($index === null) {
                return [];
            }

            $indexes[$glyphId] = $index;
            if ($index >= 258) {
                $highestCustomIndex = max($highestCustomIndex, $index - 258);
            }
        }

        $customNames = [];
        $offset = 34 + (2 * $glyphCount);
        for ($customIndex = 0; $customIndex <= $highestCustomIndex; $customIndex++) {
            if ($offset >= strlen($post)) {
                break;
            }

            $nameLength = ord($post[$offset]);
            $offset++;
            if ($offset + $nameLength > strlen($post)) {
                break;
            }

            $customNames[$customIndex] = substr($post, $offset, $nameLength);
            $offset += $nameLength;
        }

        $glyphNames = [];
        foreach ($indexes as $glyphId => $index) {
            if ($index < 258) {
                $glyphName = $this->trueTypeStandardPostGlyphName($index);
                if ($glyphName !== null) {
                    $glyphNames[$glyphId] = $glyphName;
                }
                continue;
            }

            $customName = $customNames[$index - 258] ?? null;
            if ($customName !== null && $customName !== '') {
                $glyphNames[$glyphId] = $customName;
            }
        }

        return $glyphNames;
    }

    /**
     * @return array<int, string>
     */
    private function trueTypePost25GlyphNames(string $post): array
    {
        if (strlen($post) < 34) {
            return [];
        }

        $glyphCount = $this->trueTypeUnsignedShort($post, 32);
        if ($glyphCount === null || $glyphCount < 1 || 34 + $glyphCount > strlen($post)) {
            return [];
        }

        $glyphNames = [];
        for ($glyphId = 0; $glyphId < $glyphCount; $glyphId++) {
            $offset = ord($post[34 + $glyphId]);
            if ($offset >= 128) {
                $offset -= 256;
            }

            $glyphName = $this->trueTypeStandardPostGlyphName($glyphId + $offset);
            if ($glyphName !== null) {
                $glyphNames[$glyphId] = $glyphName;
            }
        }

        return $glyphNames;
    }

    private function trueTypeStandardPostGlyphName(int $index): ?string
    {
        if ($index < 0 || $index > 257) {
            return null;
        }

        if ($index === 0) {
            return '.notdef';
        }

        if ($index === 1) {
            return '.null';
        }

        if ($index === 2) {
            return 'nonmarkingreturn';
        }

        $asciiNames = [
            'space', 'exclam', 'quotedbl', 'numbersign', 'dollar', 'percent', 'ampersand', 'quotesingle',
            'parenleft', 'parenright', 'asterisk', 'plus', 'comma', 'hyphen', 'period', 'slash',
            'zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine',
            'colon', 'semicolon', 'less', 'equal', 'greater', 'question', 'at',
            'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M',
            'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z',
            'bracketleft', 'backslash', 'bracketright', 'asciicircum', 'underscore', 'grave',
            'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm',
            'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z',
            'braceleft', 'bar', 'braceright', 'asciitilde',
        ];

        return $asciiNames[$index - 3] ?? null;
    }

    /**
     * @param array<int, string> $postGlyphNames
     * @return array<int, string>
     */
    private function trueTypeCMapEncodingDifferences(string $cmap, array $postGlyphNames = []): array
    {
        if (strlen($cmap) < 4) {
            return [];
        }

        $numTables = $this->trueTypeUnsignedShort($cmap, 2);
        if ($numTables === null) {
            return [];
        }

        $candidates = [];
        $recordOffset = 4;
        for ($index = 0; $index < $numTables; $index++) {
            if ($recordOffset + 8 > strlen($cmap)) {
                break;
            }

            $platformId = $this->trueTypeUnsignedShort($cmap, $recordOffset);
            $encodingId = $this->trueTypeUnsignedShort($cmap, $recordOffset + 2);
            $subtableOffset = $this->trueTypeUnsignedLong($cmap, $recordOffset + 4);
            if ($platformId === null || $encodingId === null || $subtableOffset === null || $subtableOffset + 2 > strlen($cmap)) {
                $recordOffset += 8;
                continue;
            }

            $format = $this->trueTypeUnsignedShort($cmap, $subtableOffset);
            if ($format === null) {
                $recordOffset += 8;
                continue;
            }

            $priority = $this->trueTypeCMapSubtablePriority($platformId, $encodingId, $format);
            if ($priority !== null) {
                $candidates[] = [
                    'priority' => $priority,
                    'format' => $format,
                    'offset' => $subtableOffset,
                ];
            }

            $recordOffset += 8;
        }

        usort($candidates, static fn (array $left, array $right): int => $left['priority'] <=> $right['priority']);
        foreach ($candidates as $candidate) {
            $differences = match ($candidate['format']) {
                0 => $this->trueTypeFormat0EncodingDifferences($cmap, $candidate['offset'], $postGlyphNames),
                6 => $this->trueTypeFormat6EncodingDifferences($cmap, $candidate['offset'], $postGlyphNames),
                8 => $this->trueTypeFormat8EncodingDifferences($cmap, $candidate['offset'], $postGlyphNames),
                10 => $this->trueTypeFormat10EncodingDifferences($cmap, $candidate['offset'], $postGlyphNames),
                4 => $this->trueTypeFormat4EncodingDifferences($cmap, $candidate['offset'], $postGlyphNames),
                12 => $this->trueTypeFormat12EncodingDifferences($cmap, $candidate['offset'], $postGlyphNames),
                13 => $this->trueTypeFormat13EncodingDifferences($cmap, $candidate['offset'], $postGlyphNames),
                default => [],
            };
            if ($differences !== []) {
                return $differences;
            }
        }

        return [];
    }

    private function trueTypeCMapSubtablePriority(int $platformId, int $encodingId, int $format): ?int
    {
        if (!in_array($format, [0, 4, 6, 8, 10, 12, 13], true)) {
            return null;
        }

        if ($platformId === 3 && $encodingId === 10 && $format === 12) {
            return 0;
        }

        if ($platformId === 3 && $encodingId === 10 && $format === 8) {
            return 1;
        }

        if ($platformId === 3 && $encodingId === 10 && $format === 10) {
            return 2;
        }

        if ($platformId === 3 && $encodingId === 1 && $format === 4) {
            return 3;
        }

        if ($platformId === 3 && $encodingId === 1 && $format === 6) {
            return 4;
        }

        if ($platformId === 0 && $format === 12) {
            return 5;
        }

        if ($platformId === 0 && $format === 8) {
            return 6;
        }

        if ($platformId === 0 && $format === 10) {
            return 7;
        }

        if ($platformId === 0 && $format === 4) {
            return 8;
        }

        if ($platformId === 0 && $format === 6) {
            return 9;
        }

        if ($platformId === 0 && $format === 0) {
            return 10;
        }

        if ($platformId === 3 && $encodingId === 0 && $format === 4) {
            return 11;
        }

        if ($platformId === 3 && $encodingId === 0 && $format === 6) {
            return 12;
        }

        if ($platformId === 3 && $format === 12) {
            return 13;
        }

        if ($platformId === 3 && $format === 8) {
            return 14;
        }

        if ($platformId === 3 && $format === 10) {
            return 15;
        }

        if ($platformId === 3 && $format === 4) {
            return 16;
        }

        if ($platformId === 3 && $format === 6) {
            return 17;
        }

        if ($platformId === 3 && $encodingId === 10 && $format === 13) {
            return 18;
        }

        if ($platformId === 0 && $format === 13) {
            return 19;
        }

        if ($platformId === 3 && $format === 13) {
            return 20;
        }

        return null;
    }

    /**
     * @param array<int, string> $postGlyphNames
     * @return array<int, string>
     */
    private function trueTypeFormat0EncodingDifferences(string $cmap, int $offset, array $postGlyphNames = []): array
    {
        $length = $this->trueTypeUnsignedShort($cmap, $offset + 2);
        if ($length === null || $length < 262) {
            return [];
        }

        $tableEnd = $offset + $length;
        if ($tableEnd > strlen($cmap) || $offset + 262 > $tableEnd) {
            return [];
        }

        $differences = [];
        for ($code = 0; $code <= 255; $code++) {
            $glyphId = ord($cmap[$offset + 6 + $code]);
            if ($glyphId === 0) {
                continue;
            }

            $unicode = $this->trueTypeGlyphUnicode($glyphId, $code, $postGlyphNames);
            if ($unicode !== null) {
                $differences[$code] = $unicode;
            }
        }

        return $differences;
    }

    /**
     * @param array<int, string> $postGlyphNames
     * @return array<int, string>
     */
    private function trueTypeFormat4EncodingDifferences(string $cmap, int $offset, array $postGlyphNames = []): array
    {
        $length = $this->trueTypeUnsignedShort($cmap, $offset + 2);
        $segCountX2 = $this->trueTypeUnsignedShort($cmap, $offset + 6);
        if ($length === null || $segCountX2 === null || $length <= 0 || $segCountX2 <= 0 || $segCountX2 % 2 !== 0) {
            return [];
        }

        $tableEnd = $offset + $length;
        if ($tableEnd > strlen($cmap)) {
            return [];
        }

        $segCount = intdiv($segCountX2, 2);
        $endCodeOffset = $offset + 14;
        $reservedPadOffset = $endCodeOffset + (2 * $segCount);
        $startCodeOffset = $reservedPadOffset + 2;
        $idDeltaOffset = $startCodeOffset + (2 * $segCount);
        $idRangeOffsetOffset = $idDeltaOffset + (2 * $segCount);
        $glyphIdArrayOffset = $idRangeOffsetOffset + (2 * $segCount);
        if ($glyphIdArrayOffset > $tableEnd) {
            return [];
        }

        $differences = [];
        for ($segment = 0; $segment < $segCount; $segment++) {
            $endCode = $this->trueTypeUnsignedShort($cmap, $endCodeOffset + (2 * $segment));
            $startCode = $this->trueTypeUnsignedShort($cmap, $startCodeOffset + (2 * $segment));
            $idDelta = $this->trueTypeSignedShort($cmap, $idDeltaOffset + (2 * $segment));
            $idRangeOffset = $this->trueTypeUnsignedShort($cmap, $idRangeOffsetOffset + (2 * $segment));
            if ($endCode === null || $startCode === null || $idDelta === null || $idRangeOffset === null || $startCode > $endCode) {
                continue;
            }

            $firstCode = max(0, $startCode);
            $lastCode = min(255, $endCode);
            for ($code = $firstCode; $code <= $lastCode; $code++) {
                $glyphId = 0;
                if ($idRangeOffset === 0) {
                    $glyphId = ($code + $idDelta) & 0xFFFF;
                } else {
                    $glyphOffset = $idRangeOffsetOffset + (2 * $segment) + $idRangeOffset + (2 * ($code - $startCode));
                    if ($glyphOffset + 2 > $tableEnd) {
                        continue;
                    }

                    $mappedGlyphId = $this->trueTypeUnsignedShort($cmap, $glyphOffset);
                    if ($mappedGlyphId !== null && $mappedGlyphId !== 0) {
                        $glyphId = ($mappedGlyphId + $idDelta) & 0xFFFF;
                    }
                }

                if ($glyphId !== 0) {
                    $unicode = $this->trueTypeGlyphUnicode($glyphId, $code, $postGlyphNames);
                    if ($unicode !== null) {
                        $differences[$code] = $unicode;
                    }
                }
            }
        }

        ksort($differences);
        return $differences;
    }

    /**
     * @param array<int, string> $postGlyphNames
     * @return array<int, string>
     */
    private function trueTypeFormat6EncodingDifferences(string $cmap, int $offset, array $postGlyphNames = []): array
    {
        $length = $this->trueTypeUnsignedShort($cmap, $offset + 2);
        $firstCode = $this->trueTypeUnsignedShort($cmap, $offset + 6);
        $entryCount = $this->trueTypeUnsignedShort($cmap, $offset + 8);
        if ($length === null || $firstCode === null || $entryCount === null || $length < 10) {
            return [];
        }

        $tableEnd = $offset + $length;
        $glyphIdArrayOffset = $offset + 10;
        if ($tableEnd > strlen($cmap) || $glyphIdArrayOffset + (2 * $entryCount) > $tableEnd) {
            return [];
        }

        $differences = [];
        for ($index = 0; $index < $entryCount; $index++) {
            $code = $firstCode + $index;
            if ($code > 255) {
                break;
            }

            $glyphId = $this->trueTypeUnsignedShort($cmap, $glyphIdArrayOffset + (2 * $index));
            if ($glyphId === null || $glyphId === 0) {
                continue;
            }

            $unicode = $this->trueTypeGlyphUnicode($glyphId, $code, $postGlyphNames);
            if ($unicode !== null) {
                $differences[$code] = $unicode;
            }
        }

        ksort($differences);
        return $differences;
    }

    /**
     * @param array<int, string> $postGlyphNames
     * @return array<int, string>
     */
    private function trueTypeFormat8EncodingDifferences(string $cmap, int $offset, array $postGlyphNames = []): array
    {
        $length = $this->trueTypeUnsignedLong($cmap, $offset + 4);
        $groupCount = $this->trueTypeUnsignedLong($cmap, $offset + 8204);
        if ($length === null || $groupCount === null || $length < 8208) {
            return [];
        }

        $tableEnd = $offset + $length;
        if ($tableEnd > strlen($cmap)) {
            return [];
        }

        $differences = [];
        $groupOffset = $offset + 8208;
        $availableGroups = intdiv(max(0, $tableEnd - $groupOffset), 12);
        $groupCount = min($groupCount, $availableGroups);
        for ($group = 0; $group < $groupCount; $group++) {
            $startCharCode = $this->trueTypeUnsignedLong($cmap, $groupOffset);
            $endCharCode = $this->trueTypeUnsignedLong($cmap, $groupOffset + 4);
            $startGlyphId = $this->trueTypeUnsignedLong($cmap, $groupOffset + 8);
            if ($startCharCode === null || $endCharCode === null || $startGlyphId === null || $startCharCode > $endCharCode) {
                $groupOffset += 12;
                continue;
            }

            $firstCode = max(0, $startCharCode);
            $lastCode = min(255, $endCharCode);
            for ($code = $firstCode; $code <= $lastCode; $code++) {
                $glyphId = $startGlyphId + ($code - $startCharCode);
                if ($glyphId === 0) {
                    continue;
                }

                $unicode = $this->trueTypeGlyphUnicode($glyphId, $code, $postGlyphNames);
                if ($unicode !== null) {
                    $differences[$code] = $unicode;
                }
            }

            $groupOffset += 12;
        }

        ksort($differences);
        return $differences;
    }

    /**
     * @param array<int, string> $postGlyphNames
     * @return array<int, string>
     */
    private function trueTypeFormat10EncodingDifferences(string $cmap, int $offset, array $postGlyphNames = []): array
    {
        $length = $this->trueTypeUnsignedLong($cmap, $offset + 4);
        $firstCode = $this->trueTypeUnsignedLong($cmap, $offset + 12);
        $entryCount = $this->trueTypeUnsignedLong($cmap, $offset + 16);
        if ($length === null || $firstCode === null || $entryCount === null || $length < 20) {
            return [];
        }

        $tableEnd = $offset + $length;
        $glyphIdArrayOffset = $offset + 20;
        if ($tableEnd > strlen($cmap) || $glyphIdArrayOffset + (2 * $entryCount) > $tableEnd) {
            return [];
        }

        $differences = [];
        for ($index = 0; $index < $entryCount; $index++) {
            $code = $firstCode + $index;
            if ($code > 255) {
                break;
            }

            $glyphId = $this->trueTypeUnsignedShort($cmap, $glyphIdArrayOffset + (2 * $index));
            if ($glyphId === null || $glyphId === 0) {
                continue;
            }

            $unicode = $this->trueTypeGlyphUnicode($glyphId, $code, $postGlyphNames);
            if ($unicode !== null) {
                $differences[$code] = $unicode;
            }
        }

        ksort($differences);
        return $differences;
    }

    /**
     * @param array<int, string> $postGlyphNames
     * @return array<int, string>
     */
    private function trueTypeFormat12EncodingDifferences(string $cmap, int $offset, array $postGlyphNames = []): array
    {
        $length = $this->trueTypeUnsignedLong($cmap, $offset + 4);
        $groupCount = $this->trueTypeUnsignedLong($cmap, $offset + 12);
        if ($length === null || $groupCount === null || $length <= 0) {
            return [];
        }

        $tableEnd = $offset + $length;
        if ($tableEnd > strlen($cmap)) {
            return [];
        }

        $differences = [];
        $groupOffset = $offset + 16;
        $availableGroups = intdiv(max(0, $tableEnd - $groupOffset), 12);
        $groupCount = min($groupCount, $availableGroups);
        for ($group = 0; $group < $groupCount; $group++) {
            $startCharCode = $this->trueTypeUnsignedLong($cmap, $groupOffset);
            $endCharCode = $this->trueTypeUnsignedLong($cmap, $groupOffset + 4);
            $startGlyphId = $this->trueTypeUnsignedLong($cmap, $groupOffset + 8);
            if ($startCharCode === null || $endCharCode === null || $startGlyphId === null || $startCharCode > $endCharCode) {
                $groupOffset += 12;
                continue;
            }

            $firstCode = max(0, $startCharCode);
            $lastCode = min(255, $endCharCode);
            for ($code = $firstCode; $code <= $lastCode; $code++) {
                $glyphId = $startGlyphId + ($code - $startCharCode);
                if ($glyphId === 0) {
                    continue;
                }

                $unicode = $this->trueTypeGlyphUnicode($glyphId, $code, $postGlyphNames);
                if ($unicode !== null) {
                    $differences[$code] = $unicode;
                }
            }

            $groupOffset += 12;
        }

        ksort($differences);
        return $differences;
    }

    /**
     * @param array<int, string> $postGlyphNames
     * @return array<int, string>
     */
    private function trueTypeFormat13EncodingDifferences(string $cmap, int $offset, array $postGlyphNames = []): array
    {
        $length = $this->trueTypeUnsignedLong($cmap, $offset + 4);
        $groupCount = $this->trueTypeUnsignedLong($cmap, $offset + 12);
        if ($length === null || $groupCount === null || $length <= 0) {
            return [];
        }

        $tableEnd = $offset + $length;
        if ($tableEnd > strlen($cmap)) {
            return [];
        }

        $differences = [];
        $groupOffset = $offset + 16;
        $availableGroups = intdiv(max(0, $tableEnd - $groupOffset), 12);
        $groupCount = min($groupCount, $availableGroups);
        for ($group = 0; $group < $groupCount; $group++) {
            $startCharCode = $this->trueTypeUnsignedLong($cmap, $groupOffset);
            $endCharCode = $this->trueTypeUnsignedLong($cmap, $groupOffset + 4);
            $glyphId = $this->trueTypeUnsignedLong($cmap, $groupOffset + 8);
            if ($startCharCode === null || $endCharCode === null || $glyphId === null || $startCharCode > $endCharCode || $glyphId === 0) {
                $groupOffset += 12;
                continue;
            }

            $firstCode = max(0, $startCharCode);
            $lastCode = min(255, $endCharCode);
            for ($code = $firstCode; $code <= $lastCode; $code++) {
                $unicode = $this->trueTypeGlyphUnicode($glyphId, $code, $postGlyphNames);
                if ($unicode !== null) {
                    $differences[$code] = $unicode;
                }
            }

            $groupOffset += 12;
        }

        ksort($differences);
        return $differences;
    }

    /**
     * @param array<int, string> $postGlyphNames
     */
    private function trueTypeGlyphUnicode(int $glyphId, int $code, array $postGlyphNames = []): ?string
    {
        $glyphName = $postGlyphNames[$glyphId] ?? null;
        if ($glyphName !== null) {
            $unicode = $this->glyphNameToUnicode($glyphName);
            if ($unicode !== null) {
                return $unicode;
            }
        }

        if ($code < 0x20 || $code === 0x7F) {
            return null;
        }

        $unicode = $this->unicodeCodePoint($code);
        return $unicode === '' ? null : $unicode;
    }

    private function trueTypeUnsignedShort(string $bytes, int $offset): ?int
    {
        if ($offset < 0 || $offset + 2 > strlen($bytes)) {
            return null;
        }

        return unpack('n', substr($bytes, $offset, 2))[1];
    }

    private function trueTypeSignedShort(string $bytes, int $offset): ?int
    {
        $value = $this->trueTypeUnsignedShort($bytes, $offset);
        if ($value === null) {
            return null;
        }

        return $value >= 0x8000 ? $value - 0x10000 : $value;
    }

    private function trueTypeUnsignedLong(string $bytes, int $offset): ?int
    {
        if ($offset < 0 || $offset + 4 > strlen($bytes)) {
            return null;
        }

        return unpack('N', substr($bytes, $offset, 4))[1];
    }

    /**
     * @return array<int, string>
     */
    private function type1FontProgramEncodingDifferences(string $fontProgram): array
    {
        $fontProgram = $this->normalizeType1FontProgram($fontProgram);
        $clearText = strstr($fontProgram, 'eexec', true);
        if ($clearText === false) {
            $clearText = $fontProgram;
        }

        $differences = [];
        if (preg_match_all('/\bdup\s+(\d{1,3})\s+\/([^\s\[\]<>\/%()]+)\s+put\b/', $clearText, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $code = (int) $match[1];
                if ($code < 0 || $code > 255) {
                    continue;
                }

                $unicode = $this->glyphNameToUnicode($match[2]);
                if ($unicode !== null) {
                    $differences[$code] = $unicode;
                }
            }
        }

        if ($differences !== []) {
            ksort($differences);
            return $differences;
        }

        if (preg_match('/\/Encoding\s*\[(.*?)\]\s*(?:readonly\s*)?def/s', $clearText, $match) !== 1) {
            return [];
        }

        if (!preg_match_all('/\/([^\s\[\]<>\/%()]+)/', $match[1], $glyphMatches)) {
            return [];
        }

        foreach ($glyphMatches[1] as $code => $glyphName) {
            if ($code > 255) {
                break;
            }

            $unicode = $this->glyphNameToUnicode($glyphName);
            if ($unicode !== null) {
                $differences[(int) $code] = $unicode;
            }
        }

        return $differences;
    }

    private function normalizeType1FontProgram(string $fontProgram): string
    {
        if (strlen($fontProgram) < 6 || ord($fontProgram[0]) !== 0x80) {
            return $fontProgram;
        }

        $normalized = '';
        $offset = 0;
        $length = strlen($fontProgram);
        while ($offset + 6 <= $length && ord($fontProgram[$offset]) === 0x80) {
            $segmentType = ord($fontProgram[$offset + 1]);
            if ($segmentType === 3) {
                break;
            }

            $segmentLength = unpack('V', substr($fontProgram, $offset + 2, 4))[1];
            $offset += 6;
            if ($segmentLength < 0 || $offset + $segmentLength > $length) {
                return $normalized === '' ? $fontProgram : $normalized;
            }

            if ($segmentType === 1 || $segmentType === 2) {
                $normalized .= substr($fontProgram, $offset, $segmentLength);
            }
            $offset += $segmentLength;
        }

        return $normalized === '' ? $fontProgram : $normalized;
    }

    private function glyphNameToUnicode(string $glyphName): ?string
    {
        $glyphName = $this->decodePdfName($glyphName);
        if ($glyphName === '' || $glyphName === '.notdef') {
            return null;
        }

        if (isset(self::GLYPH_NAME_MAP[$glyphName])) {
            return self::GLYPH_NAME_MAP[$glyphName];
        }

        if (isset(self::STANDARD_GLYPH_NAME_MAP[$glyphName])) {
            return self::STANDARD_GLYPH_NAME_MAP[$glyphName];
        }

        $expertGlyph = $this->expertGlyphNameToUnicode($glyphName);
        if ($expertGlyph !== null) {
            return $expertGlyph;
        }

        $baseName = explode('.', $glyphName, 2)[0];
        if ($baseName !== $glyphName) {
            return $this->glyphNameToUnicode($baseName);
        }

        if (str_contains($glyphName, '_')) {
            $text = '';
            foreach (explode('_', $glyphName) as $part) {
                $unicode = $this->glyphNameToUnicode($part);
                if ($unicode === null) {
                    return null;
                }
                $text .= $unicode;
            }

            return $text;
        }

        if (preg_match('/^uni((?:[\da-fA-F]{4})+)$/', $glyphName, $match) === 1) {
            $text = '';
            foreach (str_split($match[1], 4) as $hex) {
                $text .= $this->unicodeCodePoint((int) hexdec($hex));
            }

            return $text;
        }

        if (preg_match('/^u([\da-fA-F]{4,6})$/', $glyphName, $match) === 1) {
            return $this->unicodeCodePoint((int) hexdec($match[1]));
        }

        return strlen($glyphName) === 1 ? $glyphName : null;
    }

    private function expertGlyphNameToUnicode(string $glyphName): ?string
    {
        foreach (['oldstyle', 'small'] as $suffix) {
            if (!str_ends_with($glyphName, $suffix)) {
                continue;
            }

            $baseName = substr($glyphName, 0, -strlen($suffix));
            if ($baseName === '') {
                return null;
            }

            return $this->glyphNameToUnicode($baseName);
        }

        foreach (['superior' => true, 'inferior' => false] as $suffix => $isSuperior) {
            if (!str_ends_with($glyphName, $suffix)) {
                continue;
            }

            $baseName = substr($glyphName, 0, -strlen($suffix));
            if ($baseName === '') {
                return null;
            }

            $positioned = $isSuperior
                ? $this->superiorGlyphNameToUnicode($baseName)
                : $this->inferiorGlyphNameToUnicode($baseName);

            return $positioned ?? $this->glyphNameToUnicode($baseName);
        }

        return null;
    }

    private function superiorGlyphNameToUnicode(string $glyphName): ?string
    {
        return match ($glyphName) {
            'zero' => "\u{2070}",
            'one' => "\u{00B9}",
            'two' => "\u{00B2}",
            'three' => "\u{00B3}",
            'four' => "\u{2074}",
            'five' => "\u{2075}",
            'six' => "\u{2076}",
            'seven' => "\u{2077}",
            'eight' => "\u{2078}",
            'nine' => "\u{2079}",
            'parenleft' => "\u{207D}",
            'parenright' => "\u{207E}",
            'a' => "\u{1D43}",
            'b' => "\u{1D47}",
            'c' => "\u{1D9C}",
            'd' => "\u{1D48}",
            'e' => "\u{1D49}",
            'i' => "\u{2071}",
            'l' => "\u{02E1}",
            'm' => "\u{1D50}",
            'n' => "\u{207F}",
            'o' => "\u{1D52}",
            'r' => "\u{02B3}",
            's' => "\u{02E2}",
            't' => "\u{1D57}",
            default => null,
        };
    }

    private function inferiorGlyphNameToUnicode(string $glyphName): ?string
    {
        return match ($glyphName) {
            'zero' => "\u{2080}",
            'one' => "\u{2081}",
            'two' => "\u{2082}",
            'three' => "\u{2083}",
            'four' => "\u{2084}",
            'five' => "\u{2085}",
            'six' => "\u{2086}",
            'seven' => "\u{2087}",
            'eight' => "\u{2088}",
            'nine' => "\u{2089}",
            'parenleft' => "\u{208D}",
            'parenright' => "\u{208E}",
            default => null,
        };
    }

    private function decodePdfName(string $name): string
    {
        return preg_replace_callback('/#([\da-fA-F]{2})/', static function (array $match): string {
            return chr((int) hexdec($match[1]));
        }, $name) ?? $name;
    }

    private function unicodeCodePoint(int $codePoint): string
    {
        if ($codePoint < 0 || $codePoint > 0x10FFFF || ($codePoint >= 0xD800 && $codePoint <= 0xDFFF)) {
            return '';
        }

        if (function_exists('mb_chr')) {
            $char = mb_chr($codePoint, 'UTF-8');
            return $char === false ? '' : $char;
        }

        $bytes = pack('N', $codePoint);
        $decoded = iconv('UTF-32BE', 'UTF-8//IGNORE', $bytes);
        return $decoded === false ? '' : $decoded;
    }

    /**
     * @return array<int, string>
     */
    private function pdfObjects(string $pdfBytes): array
    {
        $objects = [];
        $objectOffsets = [];
        $rawObjects = $this->rawPdfObjects($pdfBytes);
        if ($rawObjects === []) {
            return $objects;
        }

        $maxObjectNumber = 0;
        foreach ($rawObjects as $rawObject) {
            $maxObjectNumber = max($maxObjectNumber, $rawObject['objectNumber']);
        }

        $xrefEntries = $this->xrefEntries($pdfBytes);
        if ($xrefEntries !== []) {
            $selectedRawObjects = $this->rawObjectsSelectedByXrefEntries($rawObjects, $xrefEntries);
            if ($selectedRawObjects !== []) {
                $rawObjects = $selectedRawObjects;
            }
        }

        $encryptionContext = $this->pdfEncryptionContext($pdfBytes, $rawObjects, $this->pdfPassword());
        if ($encryptionContext !== null) {
            $encryptedXrefEntries = $this->xrefEntries($pdfBytes, $encryptionContext);
            if ($encryptedXrefEntries !== []) {
                $selectedRawObjects = $this->rawObjectsSelectedByXrefEntries($this->rawPdfObjects($pdfBytes), $encryptedXrefEntries);
                if ($selectedRawObjects !== []) {
                    $rawObjects = $selectedRawObjects;
                    $xrefEntries = $encryptedXrefEntries;
                }
            }
        }

        $referenceObjectNumbers = $this->internalObjectNumbersByReference($rawObjects, $maxObjectNumber);
        foreach ($rawObjects as $rawObject) {
            $objectKey = $referenceObjectNumbers[$rawObject['objectNumber'] . ':' . $rawObject['generation']];
            $body = $rawObject['body'];
            if ($encryptionContext !== null) {
                $body = $this->decryptPdfObjectBody($body, $rawObject['objectNumber'], $rawObject['generation'], $encryptionContext);
            }
            $objects[$objectKey] = $this->rewriteIndirectReferences($body, $referenceObjectNumbers);
            $objectOffsets[$objectKey] = $rawObject['offset'];
        }

        return $this->expandObjectStreams($objects, $objectOffsets, $referenceObjectNumbers, $xrefEntries);
    }

    /**
     * @return list<array{objectNumber: int, generation: int, body: string, offset: int}>
     */
    private function rawPdfObjects(string $pdfBytes): array
    {
        if (!preg_match_all('/(\d+)\s+(\d+)\s+obj\b/s', $pdfBytes, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $streamRanges = $this->streamPayloadRanges($pdfBytes);
        $rawObjects = [];
        foreach ($matches as $match) {
            if ($this->offsetInsideRanges($match[0][1], $streamRanges)) {
                continue;
            }
            $bodyStart = $match[0][1] + strlen($match[0][0]);
            $bodyEnd = $this->endObjectOffsetOutsideRanges($pdfBytes, $bodyStart, $streamRanges);
            if ($bodyEnd === null) {
                continue;
            }

            $rawObjects[] = [
                'objectNumber' => (int) $match[1][0],
                'generation' => (int) $match[2][0],
                'body' => substr($pdfBytes, $bodyStart, $bodyEnd - $bodyStart),
                'offset' => $match[0][1],
            ];
        }

        return $rawObjects;
    }

    /**
     * @return list<array{start: int, end: int}>
     */
    private function streamPayloadRanges(string $pdfBytes): array
    {
        if (preg_match_all('/\bstream(?:\r\n|\n|\r)?(.*?)\r?\n?endstream\b/s', $pdfBytes, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) < 1) {
            return [];
        }

        $ranges = [];
        foreach ($matches as $match) {
            $ranges[] = [
                'start' => $match[1][1],
                'end' => $match[1][1] + strlen($match[1][0]),
            ];
        }

        return $ranges;
    }

    /**
     * @param list<array{start: int, end: int}> $ranges
     */
    private function offsetInsideRanges(int $offset, array $ranges): bool
    {
        foreach ($ranges as $range) {
            if ($offset >= $range['start'] && $offset < $range['end']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array{start: int, end: int}> $ranges
     */
    private function endObjectOffsetOutsideRanges(string $pdfBytes, int $offset, array $ranges): ?int
    {
        while (preg_match('/\bendobj\b/', $pdfBytes, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $candidate = $match[0][1];
            if (!$this->offsetInsideRanges($candidate, $ranges)) {
                return $candidate;
            }

            $offset = $candidate + strlen($match[0][0]);
        }

        return null;
    }

    /**
     * @param list<array{objectNumber: int, generation: int, body: string, offset: int}> $rawObjects
     * @param array<string, array{status: string, offset?: int, objectStreamNumber?: int, objectStreamIndex?: int}> $xrefEntries
     * @return list<array{objectNumber: int, generation: int, body: string, offset: int}>
     */
    private function rawObjectsSelectedByXrefEntries(array $rawObjects, array $xrefEntries): array
    {
        $selected = [];
        foreach ($rawObjects as $rawObject) {
            $referenceKey = $rawObject['objectNumber'] . ':' . $rawObject['generation'];
            $entry = $xrefEntries[$referenceKey] ?? null;
            if ($entry === null || $entry['status'] !== 'n' || ($entry['offset'] ?? null) !== $rawObject['offset']) {
                continue;
            }

            $selected[] = $rawObject;
        }

        return $selected;
    }

    /**
     * @param array<string, mixed>|null $encryptionContext
     * @return list<string>
     */
    private function diagnosticPdfObjectBodies(string $pdfBytes, ?array $encryptionContext): array
    {
        $rawObjects = $this->rawPdfObjects($pdfBytes);
        if ($rawObjects === []) {
            return [];
        }

        $xrefEntries = $this->xrefEntries($pdfBytes, $encryptionContext);
        if ($xrefEntries !== []) {
            $selectedRawObjects = $this->rawObjectsSelectedByXrefEntries($rawObjects, $xrefEntries);
            if ($selectedRawObjects !== []) {
                $rawObjects = $selectedRawObjects;
            }
        }

        $bodies = [];
        foreach ($rawObjects as $rawObject) {
            $body = $rawObject['body'];
            if ($encryptionContext !== null && preg_match('/\/Type\s*\/XRef\b|\/Type\/XRef\b/', $body) !== 1) {
                $body = $this->decryptPdfObjectBody($body, $rawObject['objectNumber'], $rawObject['generation'], $encryptionContext);
            }
            $bodies[] = $body;
        }

        return $bodies;
    }

    /**
     * @return array{
     *     handler: string,
     *     key: string,
     *     keyLength: int,
     *     streamCryptFilterMethod: string,
     *     stringCryptFilterMethod: string,
     *     encryptObjectNumber: int,
     *     encryptGeneration: int,
     *     passwordType: string,
     *     permissions: int,
     *     permissionPolicy: array{raw: int, unsigned: int, print: bool, modify: bool, copy: bool, annotate: bool, fillForms: bool, extractAccessibility: bool, assemble: bool, printHighResolution: bool}
     * }|null
     */
    private function pdfEncryptionContextForBytes(string $pdfBytes, string $password): ?array
    {
        $rawObjects = $this->rawPdfObjects($pdfBytes);
        if ($rawObjects === []) {
            return null;
        }

        $xrefEntries = $this->xrefEntries($pdfBytes);
        if ($xrefEntries !== []) {
            $selectedRawObjects = $this->rawObjectsSelectedByXrefEntries($rawObjects, $xrefEntries);
            if ($selectedRawObjects !== []) {
                $rawObjects = $selectedRawObjects;
            }
        }

        $encryptionContext = $this->pdfEncryptionContext($pdfBytes, $rawObjects, $password);
        if ($encryptionContext === null) {
            return null;
        }

        $encryptedXrefEntries = $this->xrefEntries($pdfBytes, $encryptionContext);
        if ($encryptedXrefEntries !== []) {
            $selectedRawObjects = $this->rawObjectsSelectedByXrefEntries($this->rawPdfObjects($pdfBytes), $encryptedXrefEntries);
            if ($selectedRawObjects !== []) {
                $rawObjects = $selectedRawObjects;
            }
        }

        return $this->pdfEncryptionContext($pdfBytes, $rawObjects, $password);
    }

    /**
     * @param list<array{objectNumber: int, generation: int, body: string, offset: int}> $rawObjects
     * @return array{
     *     handler: string,
     *     key: string,
     *     keyLength: int,
     *     streamCryptFilterMethod: string,
     *     stringCryptFilterMethod: string,
     *     encryptObjectNumber: int,
     *     encryptGeneration: int,
     *     passwordType: string,
     *     permissions: int,
     *     permissionPolicy: array{raw: int, unsigned: int, print: bool, modify: bool, copy: bool, annotate: bool, fillForms: bool, extractAccessibility: bool, assemble: bool, printHighResolution: bool}
     * }|null
     */
    private function pdfEncryptionContext(string $pdfBytes, array $rawObjects, string $password): ?array
    {
        $encryptReference = $this->pdfEncryptionReference($pdfBytes);
        if ($encryptReference === null) {
            return null;
        }

        $encryptDictionary = null;
        foreach ($rawObjects as $rawObject) {
            if ($rawObject['objectNumber'] === $encryptReference['objectNumber']
                && $rawObject['generation'] === $encryptReference['generation']
            ) {
                $encryptDictionary = $rawObject['body'];
                break;
            }
        }
        if ($encryptDictionary === null) {
            return null;
        }

        if (preg_match('/\/Filter\s*\/([A-Za-z0-9_.-]+)/', $encryptDictionary, $filterMatch) !== 1
            || $filterMatch[1] !== 'Standard'
        ) {
            return null;
        }

        $revision = $this->topLevelSignedIntegerDictionaryValue($encryptDictionary, 'R');
        $version = $this->topLevelSignedIntegerDictionaryValue($encryptDictionary, 'V') ?? 1;
        $lengthBits = $this->topLevelSignedIntegerDictionaryValue($encryptDictionary, 'Length') ?? 40;
        if ($revision === null || $lengthBits % 8 !== 0) {
            return null;
        }

        $ownerValue = $this->rawPdfStringFromDictionaryKey($encryptDictionary, 'O');
        $userValue = $this->rawPdfStringFromDictionaryKey($encryptDictionary, 'U');
        $permissions = $this->topLevelSignedIntegerDictionaryValue($encryptDictionary, 'P');
        $fileId = $this->firstPdfFileIdentifier($pdfBytes);
        if ($ownerValue === null || $userValue === null || $permissions === null || $fileId === null) {
            return null;
        }

        $streamCryptFilterMethod = 'V2';
        $stringCryptFilterMethod = 'V2';
        $encryptMetadata = true;
        $keyLength = intdiv($lengthBits, 8);
        $key = null;
        $passwordType = null;
        if ($revision === 2) {
            if (!in_array($version, [1, 2], true) || $lengthBits !== 40 || strlen($ownerValue) !== 32 || strlen($userValue) < 32) {
                return null;
            }
        } elseif ($revision === 3) {
            if ($version !== 2 || $lengthBits < 40 || $lengthBits > 128 || strlen($ownerValue) !== 32 || strlen($userValue) < 16) {
                return null;
            }
        } elseif ($revision === 4) {
            if ($version !== 4 || $lengthBits < 40 || $lengthBits > 128 || strlen($ownerValue) !== 32 || strlen($userValue) < 16) {
                return null;
            }

            $streamFilterName = $this->nameDictionaryValue($encryptDictionary, 'StmF') ?? 'Identity';
            $stringFilterName = $this->nameDictionaryValue($encryptDictionary, 'StrF') ?? 'Identity';
            $streamCryptFilterMethod = $this->standardSecurityCryptFilterMethod($encryptDictionary, $streamFilterName);
            $stringCryptFilterMethod = $this->standardSecurityCryptFilterMethod($encryptDictionary, $stringFilterName);
            if ($streamCryptFilterMethod === null || $stringCryptFilterMethod === null) {
                return null;
            }
            if (!in_array($streamCryptFilterMethod, ['Identity', 'V2', 'AESV2'], true)
                || !in_array($stringCryptFilterMethod, ['Identity', 'V2', 'AESV2'], true)
            ) {
                return null;
            }
            if (($streamCryptFilterMethod === 'AESV2' || $stringCryptFilterMethod === 'AESV2') && $keyLength !== 16) {
                return null;
            }
            $encryptMetadata = $this->booleanDictionaryValue($encryptDictionary, 'EncryptMetadata') ?? true;
        } elseif ($revision === 5 || $revision === 6) {
            if ($version !== 5 || $lengthBits !== 256 || $keyLength !== 32 || strlen($ownerValue) !== 48 || strlen($userValue) !== 48) {
                return null;
            }

            $streamFilterName = $this->nameDictionaryValue($encryptDictionary, 'StmF') ?? 'Identity';
            $stringFilterName = $this->nameDictionaryValue($encryptDictionary, 'StrF') ?? 'Identity';
            $streamCryptFilterMethod = $this->standardSecurityCryptFilterMethod($encryptDictionary, $streamFilterName);
            $stringCryptFilterMethod = $this->standardSecurityCryptFilterMethod($encryptDictionary, $stringFilterName);
            if ($streamCryptFilterMethod === null || $stringCryptFilterMethod === null) {
                return null;
            }
            if (!in_array($streamCryptFilterMethod, ['Identity', 'AESV3'], true)
                || !in_array($stringCryptFilterMethod, ['Identity', 'AESV3'], true)
            ) {
                return null;
            }

            $oeValue = $this->rawPdfStringFromDictionaryKey($encryptDictionary, 'OE');
            $ueValue = $this->rawPdfStringFromDictionaryKey($encryptDictionary, 'UE');
            $permsValue = $this->rawPdfStringFromDictionaryKey($encryptDictionary, 'Perms');
            if ($ueValue === null || strlen($ueValue) !== 32 || $permsValue === null || strlen($permsValue) !== 16) {
                return null;
            }

            $encryptMetadata = $this->booleanDictionaryValue($encryptDictionary, 'EncryptMetadata') ?? true;
            $key = $revision === 5
                ? $this->standardSecurityR5FileKeyForUserPassword($password, $userValue, $ueValue)
                : $this->standardSecurityR6FileKeyForUserPassword($password, $userValue, $ueValue);
            if ($key !== null && $this->standardSecurityAes256PermsValid($permsValue, $key, $permissions, $encryptMetadata)) {
                $passwordType = $password === '' ? 'empty-user-password' : 'user-password';
            } elseif ($password !== '' && $oeValue !== null && strlen($oeValue) === 32) {
                $key = $revision === 5
                    ? $this->standardSecurityR5FileKeyForOwnerPassword($password, $ownerValue, $userValue, $oeValue)
                    : $this->standardSecurityR6FileKeyForOwnerPassword($password, $ownerValue, $userValue, $oeValue);
                $passwordType = $key !== null && $this->standardSecurityAes256PermsValid($permsValue, $key, $permissions, $encryptMetadata)
                    ? 'owner-password'
                    : null;
            }
            if ($key === null || !$this->standardSecurityAes256PermsValid($permsValue, $key, $permissions, $encryptMetadata)) {
                return null;
            }
        } else {
            return null;
        }

        if ($key === null) {
            $key = $this->standardSecurityFileKey($password, $ownerValue, $permissions, $fileId, $keyLength, $revision, $encryptMetadata);
            $expectedUserValue = $this->standardSecurityUserValue($revision, $key, $fileId);
            $compareLength = $revision === 2 ? 32 : 16;
            if (!hash_equals(substr($userValue, 0, $compareLength), substr($expectedUserValue, 0, $compareLength))) {
                if ($password === '') {
                    return null;
                }

                $derivedUserPassword = $this->standardSecurityUserPasswordFromOwnerPassword($password, $ownerValue, $revision, $keyLength);
                $key = $this->standardSecurityFileKey($derivedUserPassword, $ownerValue, $permissions, $fileId, $keyLength, $revision, $encryptMetadata);
                $expectedUserValue = $this->standardSecurityUserValue($revision, $key, $fileId);
                if (!hash_equals(substr($userValue, 0, $compareLength), substr($expectedUserValue, 0, $compareLength))) {
                    return null;
                }
                $passwordType = 'owner-password';
            } else {
                $passwordType = $password === '' ? 'empty-user-password' : 'user-password';
            }
        }
        if ($passwordType === null) {
            return null;
        }

        $handler = match ($revision) {
            2 => 'Standard-R2-' . $passwordType,
            3 => 'Standard-R3-' . $passwordType . '-RC4',
            4 => $streamCryptFilterMethod !== $stringCryptFilterMethod
                ? 'Standard-R4-' . $passwordType . '-mixed-crypt-filters'
                : ($streamCryptFilterMethod === 'AESV2'
                ? 'Standard-R4-' . $passwordType . '-AESV2'
                : 'Standard-R4-' . $passwordType . '-RC4'),
            5 => $streamCryptFilterMethod !== $stringCryptFilterMethod
                ? 'Standard-R5-' . $passwordType . '-mixed-crypt-filters'
                : 'Standard-R5-' . $passwordType . '-AESV3',
            default => $streamCryptFilterMethod !== $stringCryptFilterMethod
                ? 'Standard-R6-' . $passwordType . '-mixed-crypt-filters'
                : 'Standard-R6-' . $passwordType . '-AESV3',
        };

        return [
            'handler' => $handler,
            'key' => $key,
            'keyLength' => $keyLength,
            'streamCryptFilterMethod' => $streamCryptFilterMethod,
            'stringCryptFilterMethod' => $stringCryptFilterMethod,
            'encryptObjectNumber' => $encryptReference['objectNumber'],
            'encryptGeneration' => $encryptReference['generation'],
            'passwordType' => $passwordType,
            'permissions' => $permissions,
            'permissionPolicy' => $this->standardSecurityPermissionPolicy($permissions),
        ];
    }

    /**
     * @return array{objectNumber: int, generation: int}|null
     */
    private function pdfEncryptionReference(string $pdfBytes): ?array
    {
        if (preg_match_all('/trailer\s*<<(.*?)>>/s', $pdfBytes, $matches) === 1) {
            for ($index = count($matches[1]) - 1; $index >= 0; $index--) {
                if (preg_match('/\/Encrypt\s+(\d+)\s+(\d+)\s+R\b/', $matches[1][$index], $referenceMatch) === 1) {
                    return [
                        'objectNumber' => (int) $referenceMatch[1],
                        'generation' => (int) $referenceMatch[2],
                    ];
                }
            }
        }

        if (preg_match('/\/Encrypt\s+(\d+)\s+(\d+)\s+R\b/', $pdfBytes, $referenceMatch) !== 1) {
            return null;
        }

        return [
            'objectNumber' => (int) $referenceMatch[1],
            'generation' => (int) $referenceMatch[2],
        ];
    }

    private function firstPdfFileIdentifier(string $pdfBytes): ?string
    {
        if (preg_match_all('/\/ID\s*\[\s*<([\da-fA-F\s]+)>/s', $pdfBytes, $matches) !== 1 || $matches[1] === []) {
            return null;
        }

        $identifier = $this->decodePdfHexBytes($matches[1][count($matches[1]) - 1]);
        return $identifier === '' ? null : $identifier;
    }

    private function standardSecurityFileKey(string $password, string $ownerValue, int $permissions, string $fileIdentifier, int $keyLength, int $revision, bool $encryptMetadata): string
    {
        $paddedPassword = substr($password . self::PDF_PASSWORD_PADDING, 0, 32);
        $permissionsValue = $permissions < 0 ? $permissions + 4294967296 : $permissions;
        $digestInput = $paddedPassword . $ownerValue . pack('V', $permissionsValue) . $fileIdentifier;
        if ($revision >= 4 && !$encryptMetadata) {
            $digestInput .= "\xFF\xFF\xFF\xFF";
        }
        $digest = md5($digestInput, true);
        if ($revision >= 3) {
            for ($round = 0; $round < 50; $round++) {
                $digest = md5(substr($digest, 0, $keyLength), true);
            }
        }

        return substr($digest, 0, $keyLength);
    }

    private function standardSecurityUserPasswordFromOwnerPassword(string $ownerPassword, string $ownerValue, int $revision, int $keyLength): string
    {
        $paddedOwnerPassword = substr($ownerPassword . self::PDF_PASSWORD_PADDING, 0, 32);
        $ownerHash = md5($paddedOwnerPassword, true);
        if ($revision >= 3) {
            for ($round = 0; $round < 50; $round++) {
                $ownerHash = md5(substr($ownerHash, 0, $keyLength), true);
            }
        }

        $ownerKey = substr($ownerHash, 0, $keyLength);
        if ($revision === 2) {
            return $this->rc4($ownerValue, $ownerKey);
        }

        $userPassword = $ownerValue;
        for ($round = 19; $round >= 0; $round--) {
            $userPassword = $this->rc4($userPassword, $this->xorBytesWithByte($ownerKey, $round));
        }

        return $userPassword;
    }

    private function standardSecurityCryptFilterMethod(string $dictionary, string $filterName): ?string
    {
        if ($filterName === 'Identity') {
            return 'Identity';
        }

        if (preg_match('/\/' . preg_quote($filterName, '/') . '\s*<<(.*?)>>/s', $dictionary, $match) !== 1) {
            return null;
        }

        $method = $this->nameDictionaryValue($match[1], 'CFM') ?? 'None';
        return $method === 'None' ? 'Identity' : $method;
    }

    private function standardSecurityUserValue(int $revision, string $key, string $fileIdentifier): string
    {
        if ($revision === 2) {
            return $this->rc4(self::PDF_PASSWORD_PADDING, $key);
        }

        $value = $this->rc4(md5(self::PDF_PASSWORD_PADDING . $fileIdentifier, true), $key);
        for ($round = 1; $round <= 19; $round++) {
            $value = $this->rc4($value, $this->xorBytesWithByte($key, $round));
        }

        return $value;
    }

    private function standardSecurityR5FileKeyForUserPassword(string $password, string $userValue, string $ueValue): ?string
    {
        if (strlen($userValue) !== 48 || strlen($ueValue) !== 32) {
            return null;
        }

        $passwordBytes = substr($password, 0, 127);
        $validationSalt = substr($userValue, 32, 8);
        $keySalt = substr($userValue, 40, 8);
        $validationHash = hash('sha256', $passwordBytes . $validationSalt, true);
        if (!hash_equals(substr($userValue, 0, 32), $validationHash)) {
            return null;
        }

        $key = hash('sha256', $passwordBytes . $keySalt, true);
        $fileKey = openssl_decrypt(
            $ueValue,
            'aes-256-cbc',
            $key,
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
            str_repeat("\0", 16)
        );

        return is_string($fileKey) && strlen($fileKey) === 32 ? $fileKey : null;
    }

    private function standardSecurityR5FileKeyForOwnerPassword(string $password, string $ownerValue, string $userValue, string $oeValue): ?string
    {
        if (strlen($ownerValue) !== 48 || strlen($userValue) !== 48 || strlen($oeValue) !== 32) {
            return null;
        }

        $passwordBytes = substr($password, 0, 127);
        $validationSalt = substr($ownerValue, 32, 8);
        $keySalt = substr($ownerValue, 40, 8);
        $validationHash = hash('sha256', $passwordBytes . $validationSalt . $userValue, true);
        if (!hash_equals(substr($ownerValue, 0, 32), $validationHash)) {
            return null;
        }

        $key = hash('sha256', $passwordBytes . $keySalt . $userValue, true);
        $fileKey = openssl_decrypt(
            $oeValue,
            'aes-256-cbc',
            $key,
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
            str_repeat("\0", 16)
        );

        return is_string($fileKey) && strlen($fileKey) === 32 ? $fileKey : null;
    }

    private function standardSecurityR6FileKeyForUserPassword(string $password, string $userValue, string $ueValue): ?string
    {
        if (strlen($userValue) !== 48 || strlen($ueValue) !== 32) {
            return null;
        }

        $passwordBytes = substr($password, 0, 127);
        $validationSalt = substr($userValue, 32, 8);
        $keySalt = substr($userValue, 40, 8);
        $validationHash = $this->standardSecurityR6Hash($passwordBytes, $validationSalt, '');
        if (!hash_equals(substr($userValue, 0, 32), $validationHash)) {
            return null;
        }

        $key = $this->standardSecurityR6Hash($passwordBytes, $keySalt, '');
        $fileKey = openssl_decrypt(
            $ueValue,
            'aes-256-cbc',
            $key,
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
            str_repeat("\0", 16)
        );

        return is_string($fileKey) && strlen($fileKey) === 32 ? $fileKey : null;
    }

    private function standardSecurityR6FileKeyForOwnerPassword(string $password, string $ownerValue, string $userValue, string $oeValue): ?string
    {
        if (strlen($ownerValue) !== 48 || strlen($userValue) !== 48 || strlen($oeValue) !== 32) {
            return null;
        }

        $passwordBytes = substr($password, 0, 127);
        $validationSalt = substr($ownerValue, 32, 8);
        $keySalt = substr($ownerValue, 40, 8);
        $validationHash = $this->standardSecurityR6Hash($passwordBytes, $validationSalt, $userValue);
        if (!hash_equals(substr($ownerValue, 0, 32), $validationHash)) {
            return null;
        }

        $key = $this->standardSecurityR6Hash($passwordBytes, $keySalt, $userValue);
        $fileKey = openssl_decrypt(
            $oeValue,
            'aes-256-cbc',
            $key,
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
            str_repeat("\0", 16)
        );

        return is_string($fileKey) && strlen($fileKey) === 32 ? $fileKey : null;
    }

    private function standardSecurityR6Hash(string $passwordBytes, string $salt, string $userValue): string
    {
        $key = hash('sha256', $passwordBytes . $salt . $userValue, true);
        for ($round = 0; ; $round++) {
            $input = str_repeat($passwordBytes . $key . $userValue, 64);
            $encrypted = openssl_encrypt(
                $input,
                'aes-128-cbc',
                substr($key, 0, 16),
                OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
                substr($key, 16, 16)
            );
            if (!is_string($encrypted) || $encrypted === '') {
                return '';
            }

            $hashSelector = 0;
            for ($index = 0; $index < 16; $index++) {
                $hashSelector = (($hashSelector * 256) + ord($encrypted[$index])) % 3;
            }

            $key = hash(match ($hashSelector) {
                0 => 'sha256',
                1 => 'sha384',
                default => 'sha512',
            }, $encrypted, true);

            if ($round >= 63 && ord($encrypted[strlen($encrypted) - 1]) <= $round - 32) {
                break;
            }
        }

        return substr($key, 0, 32);
    }

    private function standardSecurityAes256PermsValid(string $permsValue, string $fileKey, int $permissions, bool $encryptMetadata): bool
    {
        if (strlen($permsValue) !== 16 || strlen($fileKey) !== 32) {
            return false;
        }

        $plain = openssl_decrypt(
            $permsValue,
            'aes-256-ecb',
            $fileKey,
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING
        );
        if (!is_string($plain) || strlen($plain) !== 16) {
            return false;
        }

        $permissionsValue = $permissions < 0 ? $permissions + 4294967296 : $permissions;
        $unpacked = unpack('Vpermissions', substr($plain, 0, 4));
        if (!is_array($unpacked) || ($unpacked['permissions'] ?? null) !== $permissionsValue) {
            return false;
        }

        return substr($plain, 4, 4) === "\xFF\xFF\xFF\xFF"
            && $plain[8] === ($encryptMetadata ? 'T' : 'F')
            && substr($plain, 9, 3) === 'adb';
    }

    /**
     * @return array{raw: int, unsigned: int, print: bool, modify: bool, copy: bool, annotate: bool, fillForms: bool, extractAccessibility: bool, assemble: bool, printHighResolution: bool}
     */
    private function standardSecurityPermissionPolicy(int $permissions): array
    {
        $permissionsValue = $permissions < 0 ? $permissions + 4294967296 : $permissions;
        $hasBit = static fn (int $bit): bool => ($permissionsValue & (1 << ($bit - 1))) !== 0;

        return [
            'raw' => $permissions,
            'unsigned' => $permissionsValue,
            'print' => $hasBit(3),
            'modify' => $hasBit(4),
            'copy' => $hasBit(5),
            'annotate' => $hasBit(6),
            'fillForms' => $hasBit(9),
            'extractAccessibility' => $hasBit(10),
            'assemble' => $hasBit(11),
            'printHighResolution' => $hasBit(12),
        ];
    }

    private function xorBytesWithByte(string $bytes, int $byte): string
    {
        $result = '';
        for ($index = 0, $length = strlen($bytes); $index < $length; $index++) {
            $result .= chr(ord($bytes[$index]) ^ ($byte & 0xFF));
        }

        return $result;
    }

    /**
     * @param array{
     *     handler: string,
     *     key: string,
     *     keyLength: int,
     *     streamCryptFilterMethod: string,
     *     stringCryptFilterMethod: string,
     *     encryptObjectNumber: int,
     *     encryptGeneration: int
     * } $encryptionContext
     */
    private function decryptPdfObjectBody(string $body, int $objectNumber, int $generation, array $encryptionContext): string
    {
        if ($objectNumber === $encryptionContext['encryptObjectNumber'] && $generation === $encryptionContext['encryptGeneration']) {
            return $body;
        }

        $streamObjectKey = $this->pdfObjectCryptKey($encryptionContext, $objectNumber, $generation, $encryptionContext['streamCryptFilterMethod']);
        $stringObjectKey = $this->pdfObjectCryptKey($encryptionContext, $objectNumber, $generation, $encryptionContext['stringCryptFilterMethod']);
        $result = '';
        $offset = 0;
        $streamBlockCount = preg_match_all('/<<(.*?)>>\s*stream\r?\n?(.*?)\r?\n?endstream/s', $body, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        if ($streamBlockCount !== false && $streamBlockCount > 0) {
            foreach ($matches as $match) {
                $streamBlock = $match[0][0];
                $streamStart = $match[0][1];
                $result .= $this->decryptPdfStringTokens(substr($body, $offset, $streamStart - $offset), $stringObjectKey, $encryptionContext['stringCryptFilterMethod']);

                if (preg_match('/\A(.*?stream\r?\n?)(.*?)(\r?\n?endstream)\z/s', $streamBlock, $streamParts) === 1) {
                    $result .= $this->decryptPdfStringTokens($streamParts[1], $stringObjectKey, $encryptionContext['stringCryptFilterMethod'])
                        . $this->decryptPdfBytes($streamParts[2], $streamObjectKey, $encryptionContext['streamCryptFilterMethod'])
                        . $streamParts[3];
                } else {
                    $result .= $streamBlock;
                }

                $offset = $streamStart + strlen($streamBlock);
            }
        }

        $result .= $this->decryptPdfStringTokens(substr($body, $offset), $stringObjectKey, $encryptionContext['stringCryptFilterMethod']);
        return $result;
    }

    /**
     * @param array{key: string, keyLength: int} $encryptionContext
     */
    private function pdfObjectCryptKey(array $encryptionContext, int $objectNumber, int $generation, string $cryptFilterMethod): string
    {
        if ($cryptFilterMethod === 'Identity') {
            return '';
        }
        if ($cryptFilterMethod === 'AESV3') {
            return $encryptionContext['key'];
        }

        $objectSalt = chr($objectNumber & 0xFF)
            . chr(($objectNumber >> 8) & 0xFF)
            . chr(($objectNumber >> 16) & 0xFF)
            . chr($generation & 0xFF)
            . chr(($generation >> 8) & 0xFF);
        $input = $encryptionContext['key'] . $objectSalt;
        if ($cryptFilterMethod === 'AESV2') {
            $input .= 'sAlT';
        }
        $digest = md5($input, true);

        return substr($digest, 0, min($encryptionContext['keyLength'] + 5, 16));
    }

    /**
     */
    private function decryptPdfBytes(string $bytes, string $objectKey, string $cryptFilterMethod): string
    {
        return match ($cryptFilterMethod) {
            'Identity' => $bytes,
            'V2' => $this->rc4($bytes, $objectKey),
            'AESV2' => $this->decryptPdfAesV2Bytes($bytes, $objectKey),
            'AESV3' => $this->decryptPdfAesV3Bytes($bytes, $objectKey),
            default => $bytes,
        };
    }

    private function decryptPdfAesV2Bytes(string $bytes, string $objectKey): string
    {
        if (strlen($bytes) < 32) {
            return '';
        }

        $plain = openssl_decrypt(
            substr($bytes, 16),
            'aes-128-cbc',
            $objectKey,
            OPENSSL_RAW_DATA,
            substr($bytes, 0, 16)
        );

        return is_string($plain) ? $plain : '';
    }

    private function decryptPdfAesV3Bytes(string $bytes, string $fileKey): string
    {
        if (strlen($bytes) < 32 || strlen($fileKey) !== 32) {
            return '';
        }

        $plain = openssl_decrypt(
            substr($bytes, 16),
            'aes-256-cbc',
            $fileKey,
            OPENSSL_RAW_DATA,
            substr($bytes, 0, 16)
        );

        return is_string($plain) ? $plain : '';
    }

    private function decryptPdfStringTokens(string $text, string $objectKey, string $cryptFilterMethod): string
    {
        if ($cryptFilterMethod === 'Identity') {
            return $text;
        }

        $result = '';
        $offset = 0;
        $length = strlen($text);

        while ($offset < $length) {
            $char = $text[$offset];
            if ($char === '(') {
                $token = $this->readLiteralToken($text, $offset);
                $bytes = $this->decodeLiteralString(substr($token, 1, -1));
                $result .= '<' . strtoupper(bin2hex($this->decryptPdfBytes($bytes, $objectKey, $cryptFilterMethod))) . '>';
                continue;
            }

            if ($char === '<' && $offset + 1 < $length && $text[$offset + 1] === '<') {
                $result .= '<<';
                $offset += 2;
                continue;
            }

            if ($char === '<' && ($offset + 1 >= $length || $text[$offset + 1] !== '<')) {
                $token = $this->readHexToken($text, $offset);
                $bytes = $this->decodePdfHexBytes(trim($token, '<>'));
                $result .= '<' . strtoupper(bin2hex($this->decryptPdfBytes($bytes, $objectKey, $cryptFilterMethod))) . '>';
                continue;
            }

            $result .= $char;
            $offset++;
        }

        return $result;
    }

    private function rawPdfStringFromDictionaryKey(string $dictionary, string $key): ?string
    {
        if (preg_match('/\/' . preg_quote($key, '/') . '\b/', $dictionary, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $offset = $match[0][1] + strlen($match[0][0]);
        $length = strlen($dictionary);
        while ($offset < $length && ctype_space($dictionary[$offset])) {
            $offset++;
        }

        if ($offset >= $length) {
            return null;
        }

        if ($dictionary[$offset] === '(') {
            $token = $this->readLiteralToken($dictionary, $offset);
            return $this->decodeLiteralString(substr($token, 1, -1));
        }

        if ($dictionary[$offset] === '<' && ($offset + 1 >= $length || $dictionary[$offset + 1] !== '<')) {
            $token = $this->readHexToken($dictionary, $offset);
            return $this->decodePdfHexBytes(trim($token, '<>'));
        }

        return null;
    }

    private function decodePdfHexBytes(string $hex): string
    {
        $normalized = preg_replace('/\s+/', '', $hex);
        if ($normalized === null || $normalized === '' || preg_match('/^[\da-fA-F]+$/', $normalized) !== 1) {
            return '';
        }
        if (strlen($normalized) % 2 === 1) {
            $normalized .= '0';
        }

        $bytes = hex2bin($normalized);
        return $bytes === false ? '' : $bytes;
    }

    private function signedIntegerDictionaryValue(string $dictionary, string $name): ?int
    {
        if (preg_match('/\/' . preg_quote($name, '/') . '\s+([+-]?\d+)\b/', $dictionary, $match) !== 1) {
            return null;
        }

        return (int) $match[1];
    }

    private function topLevelSignedIntegerDictionaryValue(string $dictionary, string $name): ?int
    {
        $offset = 0;
        $length = strlen($dictionary);
        $depth = 0;
        $pattern = '/\G\/' . preg_quote($name, '/') . '(?![A-Za-z0-9_.-])\s+([+-]?\d+)\b/s';

        while ($offset < $length) {
            if ($offset + 1 < $length && $dictionary[$offset] === '<' && $dictionary[$offset + 1] === '<') {
                $depth++;
                $offset += 2;
                continue;
            }
            if ($offset + 1 < $length && $dictionary[$offset] === '>' && $dictionary[$offset + 1] === '>') {
                $depth = max(0, $depth - 1);
                $offset += 2;
                continue;
            }

            $char = $dictionary[$offset];
            if ($char === '(') {
                $this->readLiteralToken($dictionary, $offset);
                continue;
            }
            if ($char === '<') {
                $this->readHexToken($dictionary, $offset);
                continue;
            }
            if ($char === '[') {
                $this->readArrayToken($dictionary, $offset);
                continue;
            }
            if ($char === '%') {
                while ($offset < $length && !in_array($dictionary[$offset], ["\n", "\r"], true)) {
                    $offset++;
                }
                continue;
            }

            if ($depth === 1 && $char === '/' && preg_match($pattern, $dictionary, $match, 0, $offset) === 1) {
                return (int) $match[1];
            }

            $offset++;
        }

        return null;
    }

    private function nameDictionaryValue(string $dictionary, string $name): ?string
    {
        $token = $this->dictionaryValueToken($dictionary, $name);
        if (is_string($token) && str_starts_with($token, '/')) {
            return $this->decodePdfName(substr($token, 1));
        }

        if (preg_match('/\/' . preg_quote($name, '/') . '\s*\/([A-Za-z0-9_.-]+)/', $dictionary, $match) !== 1) {
            return null;
        }

        return $this->decodePdfName($match[1]);
    }

    private function booleanDictionaryValue(string $dictionary, string $name): ?bool
    {
        if (preg_match('/\/' . preg_quote($name, '/') . '\s+(true|false)\b/', $dictionary, $match) !== 1) {
            return null;
        }

        return $match[1] === 'true';
    }

    private function rc4(string $data, string $key): string
    {
        $keyLength = strlen($key);
        if ($keyLength === 0 || $data === '') {
            return $data;
        }

        $state = range(0, 255);
        $j = 0;
        for ($i = 0; $i < 256; $i++) {
            $j = ($j + $state[$i] + ord($key[$i % $keyLength])) & 0xFF;
            $swap = $state[$i];
            $state[$i] = $state[$j];
            $state[$j] = $swap;
        }

        $output = '';
        $i = 0;
        $j = 0;
        $length = strlen($data);
        for ($offset = 0; $offset < $length; $offset++) {
            $i = ($i + 1) & 0xFF;
            $j = ($j + $state[$i]) & 0xFF;
            $swap = $state[$i];
            $state[$i] = $state[$j];
            $state[$j] = $swap;
            $keyByte = $state[($state[$i] + $state[$j]) & 0xFF];
            $output .= chr(ord($data[$offset]) ^ $keyByte);
        }

        return $output;
    }

    /**
     * @return array<string, array{status: string, offset?: int, objectStreamNumber?: int, objectStreamIndex?: int}>
     */
    private function xrefEntries(string $pdfBytes, ?array $encryptionContext = null): array
    {
        $startOffsets = $this->startXrefOffsets($pdfBytes);
        if ($startOffsets === []) {
            return [];
        }

        for ($startIndex = count($startOffsets) - 1; $startIndex >= 0; $startIndex--) {
            $sections = [];
            $seenOffsets = [];
            $pendingOffsets = [$startOffsets[$startIndex]];
            while ($pendingOffsets !== []) {
                $offset = array_pop($pendingOffsets);
                if ($offset === null || isset($seenOffsets[$offset])) {
                    continue;
                }
                $seenOffsets[$offset] = true;
                $section = $this->xrefSectionAtOffset($pdfBytes, $offset, $encryptionContext);
                if ($section === null) {
                    continue;
                }

                $sections[] = $section;
                $previousOffsets = $section['prevOffsets'] ?? ($section['prev'] === null ? [] : [$section['prev']]);
                foreach (array_reverse($previousOffsets) as $previousOffset) {
                    if (!isset($seenOffsets[$previousOffset])) {
                        $pendingOffsets[] = $previousOffset;
                    }
                }
            }

            $entries = [];
            foreach (array_reverse($sections) as $section) {
                foreach ($section['entries'] as $referenceKey => $entry) {
                    $entries[$referenceKey] = $entry;
                }
            }
            if ($entries !== []) {
                return $entries;
            }
        }

        return [];
    }

    /**
     * @return array{entries: array<string, array{status: string, offset?: int, objectStreamNumber?: int, objectStreamIndex?: int}>, prev: int|null, prevOffsets?: list<int>}|null
     */
    private function xrefSectionAtOffset(string $pdfBytes, int $offset, ?array $encryptionContext = null): ?array
    {
        return $this->xrefTableSectionAtOffset($pdfBytes, $offset, $encryptionContext)
            ?? $this->xrefStreamSectionAtOffset($pdfBytes, $offset, $encryptionContext);
    }

    private function xrefTrailerRootObjectNumber(string $pdfBytes): ?int
    {
        $startOffsets = $this->startXrefOffsets($pdfBytes);
        if ($startOffsets === []) {
            return null;
        }

        for ($startIndex = count($startOffsets) - 1; $startIndex >= 0; $startIndex--) {
            $seenOffsets = [];
            $pendingOffsets = [$startOffsets[$startIndex]];
            while ($pendingOffsets !== []) {
                $offset = array_pop($pendingOffsets);
                if ($offset === null || isset($seenOffsets[$offset])) {
                    continue;
                }
                $seenOffsets[$offset] = true;

                $rootObjectNumber = $this->xrefSectionRootObjectNumberAtOffset($pdfBytes, $offset);
                if ($rootObjectNumber !== null) {
                    return $rootObjectNumber;
                }

                $section = $this->xrefSectionAtOffset($pdfBytes, $offset);
                if ($section === null) {
                    continue;
                }

                $previousOffsets = $section['prevOffsets'] ?? ($section['prev'] === null ? [] : [$section['prev']]);
                foreach (array_reverse($previousOffsets) as $previousOffset) {
                    if (!isset($seenOffsets[$previousOffset])) {
                        $pendingOffsets[] = $previousOffset;
                    }
                }
            }
        }

        return null;
    }

    private function xrefSectionRootObjectNumberAtOffset(string $pdfBytes, int $offset): ?int
    {
        if ($offset < 0 || $offset >= strlen($pdfBytes)) {
            return null;
        }

        $tail = substr($pdfBytes, $offset);
        if (str_starts_with($tail, 'xref')) {
            $table = $this->xrefTableBodyAndTrailer($tail);
            $dictionary = $table['dictionary'] ?? null;
            if (is_string($dictionary) && preg_match('/\/Root\s+(\d+)\s+\d+\s+R\b/', $dictionary, $match) === 1) {
                return (int) $match[1];
            }
        }

        if (preg_match('/\A\d+\s+\d+\s+obj\b(.*?)\bendobj/s', $tail, $objectMatch) === 1
            && preg_match('/\/Type\s*\/XRef\b|\/Type\/XRef\b/', $objectMatch[1]) === 1
            && preg_match('/\/Root\s+(\d+)\s+\d+\s+R\b/', $objectMatch[1], $rootMatch) === 1
        ) {
            return (int) $rootMatch[1];
        }

        return null;
    }

    /**
     * @return list<int>
     */
    private function startXrefOffsets(string $pdfBytes): array
    {
        if (preg_match_all('/startxref\s+(\d+)/', $pdfBytes, $matches) < 1) {
            return [];
        }

        $offsets = [];
        foreach ($matches[1] as $offset) {
            $offsets[] = (int) $offset;
        }

        return $offsets;
    }

    private function xrefSectionBoundaryOffset(string $pdfBytes, int $offset, ?int $searchStartOffset = null): ?int
    {
        if ($offset < 0 || $offset >= strlen($pdfBytes)) {
            return null;
        }

        $searchOffset = $searchStartOffset ?? $offset;
        if ($searchOffset < $offset || $searchOffset >= strlen($pdfBytes)) {
            return null;
        }

        $tail = substr($pdfBytes, $searchOffset);
        if (preg_match('/\bstartxref\b/', $tail, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        return $searchOffset + $match[0][1];
    }

    /**
     * @return array{entries: array<string, array{status: string, offset?: int, objectStreamNumber?: int, objectStreamIndex?: int}>, prev: int|null, prevOffsets?: list<int>}|null
     */
    private function xrefTableSectionAtOffset(string $pdfBytes, int $offset, ?array $encryptionContext = null): ?array
    {
        if ($offset < 0 || $offset >= strlen($pdfBytes)) {
            return null;
        }

        $tail = substr($pdfBytes, $offset);
        if (!str_starts_with($tail, 'xref')) {
            return null;
        }
        $table = $this->xrefTableBodyAndTrailer($tail);
        if ($table === null) {
            return null;
        }

        $trailerDictionary = $table['dictionary'];
        $helperObjectMaxOffset = $this->xrefSectionBoundaryOffset($pdfBytes, $offset);
        $prev = $this->integerDictionaryValue($trailerDictionary, 'Prev', $pdfBytes, true, $helperObjectMaxOffset);
        $prevOffsets = $prev === null ? [] : [$prev];

        $entries = $this->xrefTableEntriesFromBody($table['body']);
        $xrefStreamOffset = $this->integerDictionaryValue($trailerDictionary, 'XRefStm', $pdfBytes, true, $helperObjectMaxOffset);
        if ($xrefStreamOffset !== null && $xrefStreamOffset !== $offset) {
            $xrefStreamSection = $this->xrefStreamSectionAtOffset($pdfBytes, $xrefStreamOffset, $encryptionContext);
            if ($xrefStreamSection !== null) {
                $entries = array_merge($xrefStreamSection['entries'], $entries);
                if ($xrefStreamSection['prev'] !== null) {
                    $prevOffsets[] = $xrefStreamSection['prev'];
                }
                $prev ??= $xrefStreamSection['prev'];
            }
        }

        $prevOffsets = array_values(array_unique($prevOffsets));

        return [
            'entries' => $entries,
            'prev' => $prev,
            'prevOffsets' => $prevOffsets,
        ];
    }

    /**
     * @return array{body: string, dictionary: string}|null
     */
    private function xrefTableBodyAndTrailer(string $tail): ?array
    {
        if (!str_starts_with($tail, 'xref')) {
            return null;
        }

        if (preg_match('/\btrailer\b/', $tail, $match, PREG_OFFSET_CAPTURE, 4) !== 1) {
            return null;
        }

        $trailerOffset = $match[0][1];
        $dictionaryOffset = $trailerOffset + strlen($match[0][0]);
        $length = strlen($tail);
        while ($dictionaryOffset < $length && ctype_space($tail[$dictionaryOffset])) {
            $dictionaryOffset++;
        }

        if ($dictionaryOffset + 1 >= $length || $tail[$dictionaryOffset] !== '<' || $tail[$dictionaryOffset + 1] !== '<') {
            return null;
        }

        $dictionaryIndex = $dictionaryOffset;
        $dictionary = $this->readDictionaryToken($tail, $dictionaryIndex);
        if ($dictionary === '' || !str_starts_with($dictionary, '<<') || !str_ends_with($dictionary, '>>')) {
            return null;
        }

        return [
            'body' => substr($tail, 4, $trailerOffset - 4),
            'dictionary' => $dictionary,
        ];
    }

    /**
     * @return array<string, array{status: string, offset?: int, objectStreamNumber?: int, objectStreamIndex?: int}>
     */
    private function xrefTableEntriesFromBody(string $body): array
    {
        $entries = [];
        $lines = preg_split('/\r\n|\r|\n/', $body);
        if ($lines === false) {
            return $entries;
        }

        for ($index = 0, $lineCount = count($lines); $index < $lineCount;) {
            $line = trim($lines[$index]);
            $index++;
            if ($line === '') {
                continue;
            }
            if (preg_match('/^(\d+)\s+(\d+)$/', $line, $header) !== 1) {
                continue;
            }

            $firstObjectNumber = (int) $header[1];
            $entryCount = (int) $header[2];
            for ($entryIndex = 0; $entryIndex < $entryCount && $index < $lineCount; $entryIndex++, $index++) {
                $entryLine = trim($lines[$index]);
                if (preg_match('/^(\d{10})\s+(\d{5})\s+([nf])\b/', $entryLine, $entry) !== 1) {
                    continue;
                }

                $objectNumber = $firstObjectNumber + $entryIndex;
                $generation = (int) $entry[2];
                $entries[$objectNumber . ':' . $generation] = [
                    'status' => $entry[3],
                    'offset' => (int) $entry[1],
                ];
            }
        }

        return $entries;
    }

    /**
     * @return array{entries: array<string, array{status: string, offset?: int, objectStreamNumber?: int, objectStreamIndex?: int}>, prev: int|null, prevOffsets?: list<int>}|null
     */
    private function xrefStreamSectionAtOffset(string $pdfBytes, int $offset, ?array $encryptionContext = null): ?array
    {
        if ($offset < 0 || $offset >= strlen($pdfBytes)) {
            return null;
        }

        $tail = substr($pdfBytes, $offset);
        if (preg_match('/\A(\d+)\s+(\d+)\s+obj\b(.*?)\bendobj/s', $tail, $objectMatch) !== 1) {
            return null;
        }
        $objectNumber = (int) $objectMatch[1];
        $generation = (int) $objectMatch[2];
        $objectBody = $objectMatch[3];
        if (preg_match('/\/Type\s*\/XRef\b|\/Type\/XRef\b/', $objectBody) !== 1) {
            return null;
        }
        $stream = $this->streamObjectParts($objectBody);
        if ($stream === null) {
            return null;
        }

        $helperObjectMaxOffset = $this->xrefSectionBoundaryOffset($pdfBytes, $offset, $offset + strlen($objectMatch[0]));
        $decoded = $this->decodedXrefStream($stream['dictionary'], $stream['stream'], $objectNumber, $generation, $encryptionContext, $pdfBytes, $helperObjectMaxOffset);
        if ($decoded === null) {
            return null;
        }

        $prev = $this->integerDictionaryValue($stream['dictionary'], 'Prev', $pdfBytes, true, $helperObjectMaxOffset);

        return [
            'entries' => $this->xrefStreamEntriesFromBody($stream['dictionary'], $decoded, $pdfBytes, $helperObjectMaxOffset),
            'prev' => $prev,
            'prevOffsets' => $prev === null ? [] : [$prev],
        ];
    }

    /**
     * @param array<string, mixed>|null $encryptionContext
     */
    private function decodedXrefStream(string $dictionary, string $stream, int $objectNumber, int $generation, ?array $encryptionContext, ?string $pdfBytes = null, ?int $helperObjectMaxOffset = null): ?string
    {
        $decoded = $this->decodeStream($dictionary, $stream, []);
        if ($decoded !== null && $this->xrefStreamEntriesFromBody($dictionary, $decoded, $pdfBytes, $helperObjectMaxOffset) !== []) {
            return $decoded;
        }

        if ($encryptionContext !== null
            && ($objectNumber !== $encryptionContext['encryptObjectNumber'] || $generation !== $encryptionContext['encryptGeneration'])
        ) {
            $streamObjectKey = $this->pdfObjectCryptKey($encryptionContext, $objectNumber, $generation, $encryptionContext['streamCryptFilterMethod']);
            $stream = $this->decryptPdfBytes($stream, $streamObjectKey, $encryptionContext['streamCryptFilterMethod']);
            return $this->decodeStream($dictionary, $stream, []);
        }

        return $decoded;
    }

    /**
     * @return array<string, array{status: string, offset?: int, objectStreamNumber?: int, objectStreamIndex?: int}>
     */
    private function xrefStreamEntriesFromBody(string $dictionary, string $stream, ?string $pdfBytes = null, ?int $helperObjectMaxOffset = null): array
    {
        $widths = $this->integerArrayDictionaryValue($dictionary, 'W', $pdfBytes, true, $helperObjectMaxOffset);
        if (count($widths) !== 3) {
            return [];
        }

        $entryWidth = array_sum($widths);
        if ($entryWidth <= 0) {
            return [];
        }

        $indexPairs = $this->integerArrayDictionaryValue($dictionary, 'Index', $pdfBytes, true, $helperObjectMaxOffset);
        if ($indexPairs === []) {
            $size = $this->integerDictionaryValue($dictionary, 'Size', $pdfBytes, true, $helperObjectMaxOffset) ?? 0;
            if ($size <= 0) {
                return [];
            }
            $indexPairs = [0, $size];
        }

        $entries = [];
        $offset = 0;
        for ($index = 0, $pairCount = count($indexPairs); $index + 1 < $pairCount; $index += 2) {
            $firstObjectNumber = $indexPairs[$index];
            $entryCount = $indexPairs[$index + 1];
            for ($entryIndex = 0; $entryIndex < $entryCount; $entryIndex++) {
                if ($offset + $entryWidth > strlen($stream)) {
                    return $entries;
                }

                $type = $widths[0] === 0 ? 1 : $this->unsignedIntegerFromBytes(substr($stream, $offset, $widths[0]));
                $offset += $widths[0];
                $field2 = $this->unsignedIntegerFromBytes(substr($stream, $offset, $widths[1]));
                $offset += $widths[1];
                $field3 = $this->unsignedIntegerFromBytes(substr($stream, $offset, $widths[2]));
                $offset += $widths[2];

                $objectNumber = $firstObjectNumber + $entryIndex;
                if ($type === 1) {
                    $entries[$objectNumber . ':' . $field3] = [
                        'status' => 'n',
                        'offset' => $field2,
                    ];
                    continue;
                }

                if ($type === 2) {
                    $entries[$objectNumber . ':0'] = [
                        'status' => 'compressed',
                        'objectStreamNumber' => $field2,
                        'objectStreamIndex' => $field3,
                    ];
                    continue;
                }

                if ($type === 0) {
                    $entries[$objectNumber . ':' . $field3] = [
                        'status' => 'f',
                    ];
                }
            }
        }

        return $entries;
    }

    /**
     * @return list<int>
     */
    private function integerArrayDictionaryValue(string $dictionary, string $name, ?string $pdfBytes = null, bool $preferLatestIndirect = false, ?int $indirectObjectMaxOffset = null): array
    {
        if (preg_match('/\/' . preg_quote($name, '/') . '\s*\[(.*?)\]/s', $dictionary, $match) === 1) {
            return $this->integerArrayFromBody($match[1]);
        }

        if ($pdfBytes === null) {
            return [];
        }

        $body = $this->indirectDictionaryObjectBody($dictionary, $name, $pdfBytes, $preferLatestIndirect, $indirectObjectMaxOffset);
        if ($body === null) {
            return [];
        }

        return $this->integerArrayObjectBodyValue($body);
    }

    /**
     * @return list<int>
     */
    private function integerArrayObjectBodyValue(string $body): array
    {
        if (preg_match('/\A\s*\[(.*?)\]\s*\z/s', $body, $match) !== 1
            && preg_match('/\[(.*?)\]/s', $body, $match) !== 1
        ) {
            return [];
        }

        return $this->integerArrayFromBody($match[1]);
    }

    /**
     * @return list<int>
     */
    private function integerArrayFromBody(string $body): array
    {
        if (preg_match_all('/[+-]?\d+/', $body, $numbers) < 1) {
            return [];
        }

        return array_map(static fn (string $number): int => (int) $number, $numbers[0]);
    }

    private function indirectDictionaryObjectBody(string $dictionary, string $name, string $pdfBytes, bool $preferLatest = false, ?int $maxOffset = null): ?string
    {
        if (preg_match('/\/' . preg_quote($name, '/') . '\s+(\d+)\s+(\d+)\s+R\b/', $dictionary, $match) !== 1) {
            return null;
        }

        $objectNumber = (int) $match[1];
        $generation = (int) $match[2];
        $selectedBody = null;
        foreach ($this->rawPdfObjects($pdfBytes) as $rawObject) {
            if ($maxOffset !== null && $rawObject['offset'] > $maxOffset) {
                continue;
            }
            if ($rawObject['objectNumber'] === $objectNumber && $rawObject['generation'] === $generation) {
                if (!$preferLatest) {
                    return $rawObject['body'];
                }
                $selectedBody = $rawObject['body'];
            }
        }

        return $selectedBody;
    }

    private function unsignedIntegerFromBytes(string $bytes): int
    {
        $value = 0;
        foreach (unpack('C*', $bytes) ?: [] as $byte) {
            $value = ($value << 8) + (int) $byte;
        }

        return $value;
    }

    /**
     * @param list<array{objectNumber: int, generation: int, body: string, offset: int}> $rawObjects
     * @return array<string, int>
     */
    private function internalObjectNumbersByReference(array $rawObjects, int $maxObjectNumber): array
    {
        $referenceObjectNumbers = [];
        $nextSyntheticObjectNumber = max($maxObjectNumber + 1, 1000000000);

        foreach ($rawObjects as $rawObject) {
            $referenceKey = $rawObject['objectNumber'] . ':' . $rawObject['generation'];
            if (isset($referenceObjectNumbers[$referenceKey])) {
                continue;
            }

            if ($rawObject['generation'] === 0) {
                $referenceObjectNumbers[$referenceKey] = $rawObject['objectNumber'];
                continue;
            }

            while (in_array($nextSyntheticObjectNumber, $referenceObjectNumbers, true)) {
                $nextSyntheticObjectNumber++;
            }
            $referenceObjectNumbers[$referenceKey] = $nextSyntheticObjectNumber;
            $nextSyntheticObjectNumber++;
        }

        return $referenceObjectNumbers;
    }

    /**
     * @param array<string, int> $referenceObjectNumbers
     */
    private function rewriteIndirectReferences(string $body, array $referenceObjectNumbers): string
    {
        if ($referenceObjectNumbers === []) {
            return $body;
        }

        $streamOffset = strpos($body, 'stream');
        $endStreamOffset = $streamOffset === false ? false : strpos($body, 'endstream', $streamOffset);
        if ($streamOffset === false || $endStreamOffset === false) {
            return $this->rewriteIndirectReferencesInText($body, $referenceObjectNumbers);
        }

        $prefix = substr($body, 0, $streamOffset);
        $streamAndPayload = substr($body, $streamOffset, $endStreamOffset - $streamOffset);
        $suffix = substr($body, $endStreamOffset);

        return $this->rewriteIndirectReferencesInText($prefix, $referenceObjectNumbers)
            . $streamAndPayload
            . $this->rewriteIndirectReferencesInText($suffix, $referenceObjectNumbers);
    }

    /**
     * @param array<string, int> $referenceObjectNumbers
     */
    private function rewriteIndirectReferencesInText(string $text, array $referenceObjectNumbers): string
    {
        return preg_replace_callback('/\b(\d+)\s+(\d+)\s+R\b/', static function (array $match) use ($referenceObjectNumbers): string {
            $referenceKey = (int) $match[1] . ':' . (int) $match[2];
            if (!isset($referenceObjectNumbers[$referenceKey])) {
                return $match[0];
            }

            return $referenceObjectNumbers[$referenceKey] . ' 0 R';
        }, $text) ?? $text;
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, int> $objectOffsets
     * @param array<string, int> $referenceObjectNumbers
     * @param array<string, array{status: string, offset?: int, objectStreamNumber?: int, objectStreamIndex?: int}> $xrefEntries
     * @return array<int, string>
     */
    private function expandObjectStreams(array $objects, array $objectOffsets = [], array $referenceObjectNumbers = [], array $xrefEntries = []): array
    {
        $expanded = $objects;
        $processed = [];

        do {
            $added = false;
            foreach ($expanded as $objectNumber => $objectBody) {
                if (isset($processed[$objectNumber])) {
                    continue;
                }
                $processed[$objectNumber] = true;
                $sourceOffset = $objectOffsets[$objectNumber] ?? -1;

                foreach ($this->objectsFromObjectStream($objectBody, $expanded) as $embeddedObjectNumber => $embeddedObject) {
                    if (!$this->xrefAllowsEmbeddedObject($embeddedObjectNumber, $objectNumber, $embeddedObject['index'], $xrefEntries)) {
                        continue;
                    }

                    $existingOffset = $objectOffsets[$embeddedObjectNumber] ?? -1;
                    if (isset($expanded[$embeddedObjectNumber]) && $sourceOffset <= $existingOffset) {
                        continue;
                    }

                    $expanded[$embeddedObjectNumber] = $this->rewriteIndirectReferences($embeddedObject['body'], $referenceObjectNumbers);
                    $objectOffsets[$embeddedObjectNumber] = $sourceOffset;
                    unset($processed[$embeddedObjectNumber]);
                    $added = true;
                }
            }
        } while ($added);

        ksort($expanded);
        return $expanded;
    }

    /**
     * @param array<string, array{status: string, offset?: int, objectStreamNumber?: int, objectStreamIndex?: int}> $xrefEntries
     */
    private function xrefAllowsEmbeddedObject(int $embeddedObjectNumber, int $objectStreamNumber, int $objectStreamIndex, array $xrefEntries): bool
    {
        if ($xrefEntries === []) {
            return true;
        }

        $referencePrefix = $embeddedObjectNumber . ':';
        foreach ($xrefEntries as $referenceKey => $entry) {
            if (str_starts_with($referenceKey, $referencePrefix) && ($entry['status'] ?? '') === 'f') {
                return false;
            }
        }

        $entry = $xrefEntries[$embeddedObjectNumber . ':0'] ?? null;
        return $entry !== null
            && ($entry['status'] ?? '') === 'compressed'
            && ($entry['objectStreamNumber'] ?? null) === $objectStreamNumber
            && ($entry['objectStreamIndex'] ?? null) === $objectStreamIndex;
    }

    /**
     * @param array<int, string> $objects
     * @return array<int, array{body: string, index: int}>
     */
    private function objectsFromObjectStream(string $objectBody, array $objects): array
    {
        if (preg_match('/\/Type\s*\/ObjStm\b|\/Type\/ObjStm\b/', $objectBody) !== 1) {
            return [];
        }

        $objectCount = $this->integerDictionaryValueFromObjects($objectBody, 'N', $objects);
        $firstObjectOffset = $this->integerDictionaryValueFromObjects($objectBody, 'First', $objects);
        if ($objectCount === null || $firstObjectOffset === null || $objectCount < 1 || $firstObjectOffset < 0) {
            return [];
        }

        $decoded = $this->decodeStreamObject($objectBody, $objects);
        if ($decoded === null || $firstObjectOffset > strlen($decoded)) {
            return [];
        }

        $header = substr($decoded, 0, $firstObjectOffset);
        if (preg_match_all('/\d+/', $header, $matches) === false || count($matches[0]) < $objectCount * 2) {
            return [];
        }

        $objectData = substr($decoded, $firstObjectOffset);
        $entries = [];
        for ($index = 0; $index < $objectCount; $index++) {
            $objectNumber = (int) $matches[0][$index * 2];
            $offset = (int) $matches[0][$index * 2 + 1];
            if ($objectNumber < 1 || $offset < 0 || $offset > strlen($objectData)) {
                continue;
            }

            $entries[] = [
                'objectNumber' => $objectNumber,
                'offset' => $offset,
                'index' => $index,
            ];
        }

        usort($entries, static fn (array $left, array $right): int => $left['offset'] <=> $right['offset']);

        $embedded = [];
        foreach ($entries as $index => $entry) {
            $start = $entry['offset'];
            $end = $entries[$index + 1]['offset'] ?? strlen($objectData);
            if ($end < $start) {
                continue;
            }

            $body = trim(substr($objectData, $start, $end - $start));
            if ($body === '') {
                continue;
            }

            $embedded[$entry['objectNumber']] = [
                'body' => "\n" . $body . "\n",
                'index' => $entry['index'],
            ];
        }

        return $embedded;
    }

    private function integerDictionaryValue(string $dictionary, string $name, ?string $pdfBytes = null, bool $preferLatestIndirect = false, ?int $indirectObjectMaxOffset = null): ?int
    {
        if ($pdfBytes !== null) {
            $body = $this->indirectDictionaryObjectBody($dictionary, $name, $pdfBytes, $preferLatestIndirect, $indirectObjectMaxOffset);
            if ($body !== null) {
                return $this->integerObjectBodyValue($body);
            }
        }

        if (preg_match('/\/' . preg_quote($name, '/') . '\s+([+-]?\d+)(?!\s+\d+\s+R\b)\b/', $dictionary, $match) === 1) {
            return (int) $match[1];
        }

        $tokens = $this->dictionaryTokens($dictionary);
        $count = count($tokens);
        for ($index = 0; $index + 1 < $count; $index++) {
            if (!$this->isPdfNameToken($tokens[$index], $name)) {
                continue;
            }
            if ($this->indirectObjectOperand($tokens, $index + 1) !== null) {
                continue;
            }
            if (preg_match('/^[+-]?\d+$/', $tokens[$index + 1]) === 1) {
                return (int) $tokens[$index + 1];
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     */
    private function integerDictionaryValueFromObjects(string $dictionary, string $name, array $objects): ?int
    {
        $directValue = $this->integerDictionaryValue($dictionary, $name);
        if ($directValue !== null) {
            return $directValue;
        }

        $objectNumber = $this->indirectObjectDictionaryValue($dictionary, $name);
        if ($objectNumber === null && preg_match('/\/' . preg_quote($name, '/') . '\s+(\d+)\s+\d+\s+R\b/', $dictionary, $match) === 1) {
            $objectNumber = (int) $match[1];
        }
        if ($objectNumber === null) {
            return null;
        }

        return $this->integerObjectBodyValueFromObjects($objectNumber, $objects);
    }

    private function integerObjectBodyValue(string $body): ?int
    {
        if (preg_match('/\A\s*([+-]?\d+)\s*\z/s', $body, $match) !== 1) {
            return null;
        }

        return (int) $match[1];
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     */
    private function integerObjectBodyValueFromObjects(int $objectNumber, array $objects, array $seen = []): ?int
    {
        if (isset($seen[$objectNumber]) || !isset($objects[$objectNumber])) {
            return null;
        }

        $body = $objects[$objectNumber];
        $value = $this->integerObjectBodyValue($body);
        if ($value !== null) {
            return $value;
        }

        if (preg_match('/\A\s*(\d+)\s+\d+\s+R\s*\z/s', $body, $match) !== 1) {
            return null;
        }

        return $this->integerObjectBodyValueFromObjects((int) $match[1], $objects, $seen + [$objectNumber => true]);
    }

    /**
     * @param array<int, string> $objects
     * @return array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}|null
     */
    private function toUnicodeMapFromObject(string $objectBody, array $objects = []): ?array
    {
        $stream = $this->streamObjectParts($objectBody);
        if ($stream === null) {
            return null;
        }

        $decoded = $this->decodeStream($stream['dictionary'], $stream['stream'], $objects);
        if ($decoded === null) {
            return null;
        }

        return $this->parseToUnicodeCMap($decoded, $this->namedToUnicodeCMapStreams($objects));
    }

    /**
     * @return array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}
     * @param array<string, string> $namedCMaps
     * @param array<string, true> $seenCMaps
     */
    private function parseToUnicodeCMap(string $cmap, array $namedCMaps = [], array $seenCMaps = []): array
    {
        $cmap = $this->stripCMapComments($cmap);
        $map = [];
        $codeSpaceRanges = [];
        $explicitWritingMode = $this->cMapWritingMode($cmap);
        $writingMode = $explicitWritingMode ?? 0;

        foreach ($this->parseCMapUseNames($cmap) as $name) {
            if (isset($seenCMaps[$name]) || !isset($namedCMaps[$name])) {
                continue;
            }

            $base = $this->parseToUnicodeCMap($namedCMaps[$name], $namedCMaps, $seenCMaps + [$name => true]);
            $map = array_replace($map, $base['map']);
            $codeSpaceRanges = $this->mergeCMapCodeSpaceRanges($codeSpaceRanges, $base['codeSpaceRanges']);
            if ($explicitWritingMode === null) {
                $writingMode = (int) ($base['writingMode'] ?? $writingMode);
            }
        }

        if (preg_match_all('/beginbfchar(.*?)endbfchar/s', $cmap, $charBlocks)) {
            foreach ($charBlocks[1] as $block) {
                foreach ($this->parseToUnicodeCharEntries($block) as $entry) {
                    $map[$entry['source']] = $entry['target'];
                }
            }
        }

        if (preg_match_all('/beginbfrange(.*?)endbfrange/s', $cmap, $rangeBlocks)) {
            foreach ($rangeBlocks[1] as $block) {
                $this->parseToUnicodeRanges($block, $map);
            }
        }
        $codeSpaceRanges = $this->mergeCMapCodeSpaceRanges($codeSpaceRanges, $this->parseCMapCodeSpaceRanges($cmap));

        return [
            'map' => $map,
            'codeSpaceRanges' => $codeSpaceRanges,
            'writingMode' => $writingMode,
        ];
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, string>
     */
    private function namedToUnicodeCMapStreams(array $objects): array
    {
        $namedCMaps = [];
        foreach ($objects as $body) {
            $decoded = null;
            $stream = $this->streamObjectParts($body);
            if ($stream !== null) {
                $decoded = $this->decodeStream($stream['dictionary'], $stream['stream'], $objects);
            } elseif (stripos($body, 'begincmap') !== false) {
                $decoded = $body;
            }
            if ($decoded === null || stripos($decoded, 'begincmap') === false) {
                continue;
            }

            $name = $this->cMapName($decoded);
            if ($name !== null) {
                $namedCMaps[$name] = $decoded;
            }
        }

        return $namedCMaps;
    }

    private function cMapName(string $cmap): ?string
    {
        $cmap = $this->stripCMapComments($cmap);
        if (preg_match('/\/CMapName\s*\/([^\s\[\]<>\/%()]+)/', $cmap, $match) === 1) {
            return $this->decodePdfName($match[1]);
        }

        if (preg_match('/CMapName\s+currentdict\s+\/([^\s\[\]<>\/%()]+)\s+defineresource\b/', $cmap, $match) === 1) {
            return $this->decodePdfName($match[1]);
        }

        return null;
    }

    private function cMapWritingMode(string $cmap): ?int
    {
        if (preg_match('/\/WMode\s+([01])\b/', $cmap, $match) !== 1) {
            return null;
        }

        return (int) $match[1];
    }

    /**
     * @return list<string>
     */
    private function parseCMapUseNames(string $cmap): array
    {
        if (preg_match_all('/\/([^\s\[\]<>\/%()]+)\s+usecmap\b/i', $cmap, $matches) !== 1) {
            return [];
        }

        $names = [];
        foreach ($matches[1] as $name) {
            $names[] = $this->decodePdfName($name);
        }

        return array_values(array_unique($names));
    }

    /**
     * @param array<string, string> $namedCMaps
     */
    private function cMapBodyForUseName(string $name, array $namedCMaps): ?string
    {
        if (isset($namedCMaps[$name])) {
            return $namedCMaps[$name];
        }

        return $this->predefinedCidEncodingCMap($name);
    }

    /**
     * @param list<array{start: int, end: int, width: int}> $baseRanges
     * @param list<array{start: int, end: int, width: int}> $additionalRanges
     * @return list<array{start: int, end: int, width: int}>
     */
    private function mergeCMapCodeSpaceRanges(array $baseRanges, array $additionalRanges): array
    {
        $ranges = [];
        foreach (array_merge($baseRanges, $additionalRanges) as $range) {
            $ranges[$range['width'] . ':' . $range['start'] . ':' . $range['end']] = $range;
        }

        $ranges = array_values($ranges);
        usort($ranges, static function (array $left, array $right): int {
            return $right['width'] <=> $left['width'] ?: $left['start'] <=> $right['start'];
        });

        return $ranges;
    }

    /**
     * @return list<array{source: string, target: string}>
     */
    private function parseToUnicodeCharEntries(string $block): array
    {
        $entries = [];
        $index = 0;
        while (($sourceToken = $this->nextCMapToken($block, $index)) !== null) {
            $targetToken = $this->nextCMapToken($block, $index);
            if ($targetToken === null) {
                break;
            }
            if (!$this->isCMapStringToken($sourceToken) || !$this->isCMapStringToken($targetToken)) {
                continue;
            }

            $source = $this->normalizeCMapSourceToken($sourceToken);
            if ($source === '') {
                continue;
            }

            $entries[] = [
                'source' => $source,
                'target' => $this->decodeCMapStringToken($targetToken),
            ];
        }

        return $entries;
    }

    /**
     * @param array<string, string> $map
     */
    private function parseToUnicodeRanges(string $block, array &$map): void
    {
        $index = 0;
        while (($startToken = $this->nextCMapToken($block, $index)) !== null) {
            $endToken = $this->nextCMapToken($block, $index);
            $targetToken = $this->nextCMapToken($block, $index);
            if ($endToken === null || $targetToken === null) {
                break;
            }
            if (!$this->isCMapStringToken($startToken) || !$this->isCMapStringToken($endToken)) {
                continue;
            }

            $start = $this->normalizeCMapSourceToken($startToken);
            $end = $this->normalizeCMapSourceToken($endToken);
            if ($start === '' || $end === '' || strlen($start) !== strlen($end)) {
                continue;
            }

            $source = hexdec($start);
            $last = hexdec($end);
            $sourceWidth = strlen($start);
            if ($targetToken[0] === '[') {
                foreach ($this->cMapStringTokensFromArray($targetToken) as $targetStringToken) {
                    if ($source > $last) {
                        break;
                    }
                    $map[str_pad(strtolower(dechex($source)), $sourceWidth, '0', STR_PAD_LEFT)] = $this->decodeCMapStringToken($targetStringToken);
                    $source++;
                }
                continue;
            }

            $targetHex = $this->cMapTargetHexForSequentialRange($targetToken);
            if ($targetHex === null) {
                continue;
            }

            $targetIsLiteral = $targetToken[0] === '(';
            $literalIsUnicode = $targetIsLiteral && $this->literalCMapTargetLooksUnicode($this->decodeLiteralString(substr($targetToken, 1, -1)));
            $count = 0;
            while ($source <= $last && $count < 512) {
                $sourceKey = str_pad(strtolower(dechex($source)), $sourceWidth, '0', STR_PAD_LEFT);
                $mappedTargetHex = $this->incrementHexString($targetHex, $count);
                $map[$sourceKey] = $targetIsLiteral
                    ? $this->decodeCMapLiteralTargetHex($mappedTargetHex, $literalIsUnicode)
                    : $this->decodeCMapUnicodeHex($mappedTargetHex);
                $source++;
                $count++;
            }
        }
    }

    private function nextCMapToken(string $block, int &$index): ?string
    {
        $length = strlen($block);
        while ($index < $length && ctype_space($block[$index])) {
            $index++;
        }
        if ($index >= $length) {
            return null;
        }

        $char = $block[$index];
        if ($char === '(') {
            return $this->readLiteralToken($block, $index);
        }
        if ($char === '[') {
            return $this->readArrayToken($block, $index);
        }
        if ($char === '<' && ($index + 1 >= $length || $block[$index + 1] !== '<')) {
            return $this->readHexToken($block, $index);
        }

        $start = $index;
        while ($index < $length && !ctype_space($block[$index]) && !str_contains('[]()<>{}%', $block[$index])) {
            $index++;
        }
        if ($index === $start) {
            $index++;
            return null;
        }

        return substr($block, $start, $index - $start);
    }

    private function isCMapStringToken(string $token): bool
    {
        return $this->isCMapHexStringToken($token) || str_starts_with($token, '(');
    }

    private function isCMapHexStringToken(string $token): bool
    {
        return str_starts_with($token, '<') && !str_starts_with($token, '<<') && str_ends_with($token, '>');
    }

    /**
     * @return list<string>
     */
    private function cMapStringTokensFromArray(string $arrayToken): array
    {
        $body = substr($arrayToken, 1, -1);
        $tokens = [];
        $index = 0;
        while (($token = $this->nextCMapToken($body, $index)) !== null) {
            if ($this->isCMapStringToken($token)) {
                $tokens[] = $token;
            }
        }

        return $tokens;
    }

    private function decodeCMapStringToken(string $token): string
    {
        if ($this->isCMapHexStringToken($token)) {
            return $this->decodeCMapUnicodeHex(substr($token, 1, -1));
        }

        if (str_starts_with($token, '(')) {
            return $this->decodeCMapLiteralTargetBytes($this->decodeLiteralString(substr($token, 1, -1)));
        }

        return '';
    }

    private function cMapTargetHexForSequentialRange(string $token): ?string
    {
        if ($this->isCMapHexStringToken($token)) {
            $hex = $this->normalizeHexKey(substr($token, 1, -1));
            return $hex === '' ? null : $hex;
        }

        if (str_starts_with($token, '(')) {
            $bytes = $this->decodeLiteralString(substr($token, 1, -1));
            return $bytes === '' ? null : bin2hex($bytes);
        }

        return null;
    }

    private function normalizeCMapSourceToken(string $token): string
    {
        if ($this->isCMapHexStringToken($token)) {
            return $this->normalizeHexKey(substr($token, 1, -1));
        }

        if (str_starts_with($token, '(')) {
            return bin2hex($this->decodeLiteralString(substr($token, 1, -1)));
        }

        return '';
    }

    private function decodeCMapLiteralTargetBytes(string $bytes): string
    {
        if ($bytes === '') {
            return '';
        }

        if ($this->literalCMapTargetLooksUnicode($bytes)) {
            return $this->decodeCMapUnicodeHex(bin2hex($bytes));
        }

        return $bytes;
    }

    private function decodeCMapLiteralTargetHex(string $hex, bool $unicodeLike): string
    {
        if ($unicodeLike) {
            return $this->decodeCMapUnicodeHex($hex);
        }

        $bytes = hex2bin($hex);
        return $bytes === false ? '' : $bytes;
    }

    private function literalCMapTargetLooksUnicode(string $bytes): bool
    {
        return str_starts_with($bytes, "\xFE\xFF")
            || str_starts_with($bytes, "\xFF\xFE")
            || str_contains($bytes, "\0");
    }

    private function incrementHexString(string $hex, int $increment): string
    {
        $normalized = $this->normalizeHexKey($hex);
        if ($normalized === '' || $increment <= 0) {
            return $normalized;
        }

        $bytes = hex2bin($normalized);
        if ($bytes === false || $bytes === '') {
            return $normalized;
        }

        $carry = $increment;
        for ($index = strlen($bytes) - 1; $index >= 0 && $carry > 0; $index--) {
            $sum = ord($bytes[$index]) + $carry;
            $bytes[$index] = chr($sum & 0xFF);
            $carry = intdiv($sum, 256);
        }

        return bin2hex($bytes);
    }

    /**
     * @return array{map: array<string, int>, codeSpaceRanges: list<array{start: int, end: int, width: int}>, writingMode: int}
     * @param array<string, string> $namedCMaps
     * @param array<string, true> $seenCMaps
     */
    private function parseCidEncodingCMap(string $cmap, array $namedCMaps = [], array $seenCMaps = []): array
    {
        $cmap = $this->stripCMapComments($cmap);
        $map = [];
        $codeSpaceRanges = [];
        $explicitWritingMode = $this->cMapWritingMode($cmap);
        $writingMode = $explicitWritingMode ?? 0;

        foreach ($this->parseCMapUseNames($cmap) as $name) {
            if (isset($seenCMaps[$name])) {
                continue;
            }

            $baseCMap = $this->cMapBodyForUseName($name, $namedCMaps);
            if ($baseCMap === null) {
                continue;
            }

            $base = $this->parseCidEncodingCMap($baseCMap, $namedCMaps, $seenCMaps + [$name => true]);
            $map = array_replace($map, $base['map']);
            $codeSpaceRanges = $this->mergeCMapCodeSpaceRanges($codeSpaceRanges, $base['codeSpaceRanges']);
            if ($explicitWritingMode === null) {
                $writingMode = (int) ($base['writingMode'] ?? $writingMode);
            }
        }

        if (preg_match_all('/beginnotdefchar(.*?)endnotdefchar/s', $cmap, $charBlocks)) {
            foreach ($charBlocks[1] as $block) {
                foreach ($this->parseCMapCidCharEntries($block) as $entry) {
                    $map[$entry['source']] = $entry['cid'];
                }
            }
        }

        if (preg_match_all('/beginnotdefrange(.*?)endnotdefrange/s', $cmap, $rangeBlocks)) {
            foreach ($rangeBlocks[1] as $block) {
                foreach ($this->parseCMapCidRangeEntries($block, false) as $entry) {
                    $map[$entry['source']] = $entry['cid'];
                }
            }
        }

        if (preg_match_all('/begincidchar(.*?)endcidchar/s', $cmap, $charBlocks)) {
            foreach ($charBlocks[1] as $block) {
                foreach ($this->parseCMapCidCharEntries($block) as $entry) {
                    $map[$entry['source']] = $entry['cid'];
                }
            }
        }

        if (preg_match_all('/begincidrange(.*?)endcidrange/s', $cmap, $rangeBlocks)) {
            foreach ($rangeBlocks[1] as $block) {
                foreach ($this->parseCMapCidRangeEntries($block, true) as $entry) {
                    $map[$entry['source']] = $entry['cid'];
                }
            }
        }

        return [
            'map' => $map,
            'codeSpaceRanges' => $this->mergeCMapCodeSpaceRanges($codeSpaceRanges, $this->parseCMapCodeSpaceRanges($cmap)),
            'writingMode' => $writingMode,
        ];
    }

    /**
     * @param array<string, string> $namedCMaps
     * @param array<string, true> $seenCMaps
     * @return array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>, writingMode: int, unicodeSourceEncoding?: string}
     */
    private function parseUnicodeCidEncodingCMap(string $cmap, array $namedCMaps = [], array $seenCMaps = []): array
    {
        $cmap = $this->stripCMapComments($cmap);
        $map = [];
        $codeSpaceRanges = [];
        $unicodeSourceEncoding = $this->cidCMapUnicodeSourceEncoding($cmap);
        $explicitWritingMode = $this->cMapWritingMode($cmap);
        $writingMode = $explicitWritingMode ?? 0;

        foreach ($this->parseCMapUseNames($cmap) as $name) {
            if (isset($seenCMaps[$name])) {
                continue;
            }

            $baseCMap = $this->cMapBodyForUseName($name, $namedCMaps);
            if ($baseCMap === null) {
                continue;
            }

            $base = $this->parseUnicodeCidEncodingCMap($baseCMap, $namedCMaps, $seenCMaps + [$name => true]);
            $map = array_replace($map, $base['map']);
            $codeSpaceRanges = $this->mergeCMapCodeSpaceRanges($codeSpaceRanges, $base['codeSpaceRanges']);
            if ($unicodeSourceEncoding === null && isset($base['unicodeSourceEncoding']) && is_string($base['unicodeSourceEncoding'])) {
                $unicodeSourceEncoding = $base['unicodeSourceEncoding'];
            }
            if ($explicitWritingMode === null) {
                $writingMode = (int) ($base['writingMode'] ?? $writingMode);
            }
        }

        if (preg_match_all('/begincidchar(.*?)endcidchar/s', $cmap, $charBlocks)) {
            foreach ($charBlocks[1] as $block) {
                foreach ($this->parseCMapCidCharEntries($block) as $entry) {
                    $source = $entry['source'];
                    $unicode = $unicodeSourceEncoding !== null
                        ? $this->decodeCMapUnicodeSourceHex($source, $unicodeSourceEncoding)
                        : $this->unicodeScalarFromCid($entry['cid']);
                    if ($source !== '' && $unicode !== null) {
                        $map[$source] = $unicode;
                    }
                }
            }
        }

        if (preg_match_all('/begincidrange(.*?)endcidrange/s', $cmap, $rangeBlocks)) {
            foreach ($rangeBlocks[1] as $block) {
                foreach ($this->parseCMapCidRangeEntries($block, true, 1024) as $entry) {
                    $unicode = $unicodeSourceEncoding !== null
                        ? $this->decodeCMapUnicodeSourceHex($entry['source'], $unicodeSourceEncoding)
                        : $this->unicodeScalarFromCid($entry['cid']);
                    if ($unicode !== null) {
                        $map[$entry['source']] = $unicode;
                    }
                }
            }
        }

        $result = [
            'map' => $map,
            'codeSpaceRanges' => $this->mergeCMapCodeSpaceRanges($codeSpaceRanges, $this->parseCMapCodeSpaceRanges($cmap)),
            'writingMode' => $writingMode,
        ];
        if ($unicodeSourceEncoding !== null) {
            $result['unicodeSourceEncoding'] = $unicodeSourceEncoding;
        }

        return $result;
    }

    /**
     * @return list<array{source: string, cid: int}>
     */
    private function parseCMapCidCharEntries(string $block): array
    {
        $entries = [];
        $index = 0;
        while (($sourceToken = $this->nextCMapToken($block, $index)) !== null) {
            $cidToken = $this->nextCMapToken($block, $index);
            if ($cidToken === null) {
                break;
            }
            $cid = $this->cMapCidOperand($cidToken);
            if (!$this->isCMapStringToken($sourceToken) || $cid === null) {
                continue;
            }

            $source = $this->normalizeCMapSourceToken($sourceToken);
            if ($source === '') {
                continue;
            }

            $entries[] = [
                'source' => $source,
                'cid' => $cid,
            ];
        }

        return $entries;
    }

    /**
     * @return list<array{source: string, cid: int}>
     */
    private function parseCMapCidRangeEntries(string $block, bool $incrementCid, int $limit = 65535): array
    {
        $entries = [];
        $index = 0;
        while (($startToken = $this->nextCMapToken($block, $index)) !== null) {
            $endToken = $this->nextCMapToken($block, $index);
            $cidToken = $this->nextCMapToken($block, $index);
            if ($endToken === null || $cidToken === null) {
                break;
            }
            $cid = $this->cMapCidOperand($cidToken);
            if (!$this->isCMapStringToken($startToken) || !$this->isCMapStringToken($endToken) || $cid === null) {
                continue;
            }

            $start = $this->normalizeCMapSourceToken($startToken);
            $end = $this->normalizeCMapSourceToken($endToken);
            if ($start === '' || $end === '' || strlen($start) !== strlen($end)) {
                continue;
            }

            $source = hexdec($start);
            $last = hexdec($end);
            if ($last < $source || $last - $source > $limit) {
                continue;
            }

            $width = strlen($start);
            $count = 0;
            while ($source <= $last) {
                $entries[] = [
                    'source' => str_pad(strtolower(dechex($source)), $width, '0', STR_PAD_LEFT),
                    'cid' => $incrementCid ? $cid + $count : $cid,
                ];
                $source++;
                $count++;
            }
        }

        return $entries;
    }

    private function cMapCidOperand(string $token): ?int
    {
        if (preg_match('/^\+?\d+$/', $token) !== 1) {
            return null;
        }

        return (int) ltrim($token, '+');
    }

    private function unicodeScalarFromCid(int $cid): ?string
    {
        if (($cid < 0x20 && !in_array($cid, [0x09, 0x0A, 0x0D], true))
            || $cid > 0x10FFFF
            || ($cid >= 0xD800 && $cid <= 0xDFFF)
        ) {
            return null;
        }

        $bytes = pack('N', $cid);
        $decoded = iconv('UCS-4BE', 'UTF-8//IGNORE', $bytes);
        return $decoded === false || $decoded === '' ? null : $decoded;
    }

    /**
     * @return list<array{start: int, end: int, width: int}>
     */
    private function parseCMapCodeSpaceRanges(string $cmap): array
    {
        $ranges = [];
        if (!preg_match_all('/begincodespacerange(.*?)endcodespacerange/s', $cmap, $blocks)) {
            return [];
        }

        foreach ($blocks[1] as $block) {
            $index = 0;
            while (($startToken = $this->nextCMapToken($block, $index)) !== null) {
                $endToken = $this->nextCMapToken($block, $index);
                if ($endToken === null) {
                    break;
                }
                if (!$this->isCMapStringToken($startToken) || !$this->isCMapStringToken($endToken)) {
                    continue;
                }

                $start = $this->normalizeCMapSourceToken($startToken);
                $end = $this->normalizeCMapSourceToken($endToken);
                if ($start === '' || $end === '' || strlen($start) !== strlen($end) || strlen($start) > 8) {
                    continue;
                }

                $startValue = hexdec($start);
                $endValue = hexdec($end);
                if ($startValue > $endValue) {
                    continue;
                }

                $ranges[$start . ':' . $end] = [
                    'start' => $startValue,
                    'end' => $endValue,
                    'width' => strlen($start),
                ];
            }
        }

        $ranges = array_values($ranges);
        usort($ranges, static function (array $left, array $right): int {
            return $right['width'] <=> $left['width'] ?: $left['start'] <=> $right['start'];
        });

        return $ranges;
    }

    private function normalizeHexKey(string $hex): string
    {
        $normalized = preg_replace('/\s+/', '', strtolower($hex));
        if ($normalized === null || $normalized === '' || preg_match('/^[\da-f]+$/', $normalized) !== 1) {
            return '';
        }
        if (strlen($normalized) % 2 === 1) {
            $normalized .= '0';
        }

        return $normalized;
    }

    private function decodeCMapUnicodeHex(string $hex): string
    {
        $normalized = $this->normalizeHexKey($hex);
        if ($normalized === '') {
            return '';
        }

        if (str_starts_with($normalized, 'feff') || str_starts_with($normalized, 'fffe')) {
            return $this->decodeHexString($normalized);
        }

        if (strlen($normalized) % 4 === 0) {
            $bytes = hex2bin($normalized);
            if ($bytes !== false) {
                $decoded = @iconv('UTF-16BE', 'UTF-8//IGNORE', $bytes);
                if ($decoded !== false) {
                    return $decoded;
                }
            }
        }

        return $this->decodeHexString($normalized);
    }

    /**
     * @return list<string>
     * @param array<string, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}> $fontToUnicodeMaps
     * @param array<string, array{base: string, differences: array<int, string>, suppressUnmapped: bool}> $fontEncodings
     * @param array<string, string> $propertyActualTexts
     * @param array<int, string> $mcidActualTexts
     * @param array<string, int> $propertyMcids
     */
    private function textRunsFromContentStream(string $stream, array $fontToUnicodeMaps, array $fontEncodings, array $propertyActualTexts, array $mcidActualTexts, array $propertyMcids): array
    {
        $runs = [];
        $operands = [];
        $currentFontResource = null;
        $actualTextStack = [];
        $artifactStack = [];
        foreach ($this->contentTokens($stream) as $token) {
            if ($token === 'BDC') {
                $isArtifact = $this->markedContentIsArtifact($operands);
                $actualText = $isArtifact ? null : $this->actualTextOperand($operands, $propertyActualTexts, $mcidActualTexts, $propertyMcids);
                if ($actualText !== null) {
                    $runs[] = $actualText;
                }
                $actualTextStack[] = $actualText;
                $artifactStack[] = $isArtifact;
                $operands = [];
                continue;
            }

            if ($token === 'BMC') {
                $actualTextStack[] = null;
                $artifactStack[] = $this->markedContentIsArtifact($operands);
                $operands = [];
                continue;
            }

            if ($token === 'EMC') {
                array_pop($actualTextStack);
                array_pop($artifactStack);
                $operands = [];
                continue;
            }

            if ($this->isTextShowingOperator($token)) {
                $operand = $this->textShowingOperand($token, $operands);
                if ($operand !== null && !$this->insideActualText($actualTextStack) && !$this->insideArtifact($artifactStack)) {
                    $runs[] = $this->decodeTextOperandWithArraySpacing(
                        $operand,
                        $this->currentToUnicodeMap($fontToUnicodeMaps, $currentFontResource),
                        $this->currentFontEncoding($fontEncodings, $currentFontResource)
                    );
                }
                $operands = [];
                continue;
            }

            if ($token === 'Tf') {
                $currentFontResource = $this->fontResourceOperand($operands) ?? $currentFontResource;
                $operands = [];
                continue;
            }

            if ($this->isOperator($token)) {
                $operands = [];
                continue;
            }

            $operands[] = $token;
        }

        return $runs;
    }

    /**
     * @return array{missingUnicodeFonts: list<string>, missingUnicodeFontEncodings: array<string, string>, suppressedGlyphRuns: int}
     * @param array<string, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}> $fontToUnicodeMaps
     * @param array<string, array{base: string, differences: array<int, string>, suppressUnmapped: bool}> $fontEncodings
     * @param array<string, string> $propertyActualTexts
     * @param array<int, string> $mcidActualTexts
     * @param array<string, int> $propertyMcids
     */
    private function suppressedGlyphDiagnosticsFromContentStream(string $stream, array $fontToUnicodeMaps, array $fontEncodings, array $propertyActualTexts, array $mcidActualTexts, array $propertyMcids): array
    {
        $missingUnicodeFonts = [];
        $missingUnicodeFontEncodings = [];
        $suppressedGlyphRuns = 0;
        $operands = [];
        $currentFontResource = null;
        $actualTextStack = [];
        $artifactStack = [];

        foreach ($this->contentTokens($stream) as $token) {
            if ($token === 'BDC') {
                $isArtifact = $this->markedContentIsArtifact($operands);
                $actualTextStack[] = $isArtifact ? null : $this->actualTextOperand($operands, $propertyActualTexts, $mcidActualTexts, $propertyMcids);
                $artifactStack[] = $isArtifact;
                $operands = [];
                continue;
            }

            if ($token === 'BMC') {
                $actualTextStack[] = null;
                $artifactStack[] = $this->markedContentIsArtifact($operands);
                $operands = [];
                continue;
            }

            if ($token === 'EMC') {
                array_pop($actualTextStack);
                array_pop($artifactStack);
                $operands = [];
                continue;
            }

            if ($this->isTextShowingOperator($token)) {
                $operand = $this->textShowingOperand($token, $operands);
                $toUnicodeMap = $this->currentToUnicodeMap($fontToUnicodeMaps, $currentFontResource);
                $fontEncoding = $this->currentFontEncoding($fontEncodings, $currentFontResource);
                if ($operand !== null
                    && !$this->insideActualText($actualTextStack)
                    && !$this->insideArtifact($artifactStack)
                    && $toUnicodeMap === null
                    && ($fontEncoding['suppressUnmapped'] ?? false) === true
                    && $this->textShowingOperandHasGlyphBytes($operand)
                ) {
                    $fontName = $this->diagnosticFontResourceName($currentFontResource);
                    $suppressedGlyphRuns++;
                    $missingUnicodeFonts[] = $fontName;
                    $missingUnicodeFontEncodings[$fontName] = (string) ($fontEncoding['base'] ?? 'unknown');
                }

                $operands = [];
                continue;
            }

            if ($token === 'Tf') {
                $currentFontResource = $this->fontResourceOperand($operands) ?? $currentFontResource;
                $operands = [];
                continue;
            }

            if ($this->isOperator($token)) {
                $operands = [];
                continue;
            }

            $operands[] = $token;
        }

        return [
            'missingUnicodeFonts' => array_values(array_unique($missingUnicodeFonts)),
            'missingUnicodeFontEncodings' => $missingUnicodeFontEncodings,
            'suppressedGlyphRuns' => $suppressedGlyphRuns,
        ];
    }

    /**
     * @return list<array{x1: float, y1: float, x2: float, y2: float, fillColor: string}>
     */
    private function filledRectanglesFromContentStream(string $stream): array
    {
        $rectangles = [];
        $pathRectangles = [];
        $operands = [];
        $state = [
            'fillColor' => '#000000',
            'fillColorSpace' => 'DeviceGray',
            'transformationMatrix' => $this->identityTransformationMatrix(),
        ];
        $stateStack = [];

        foreach ($this->contentTokens($stream) as $token) {
            if ($token === 'q') {
                $stateStack[] = $state;
                $operands = [];
                continue;
            }

            if ($token === 'Q') {
                $restored = array_pop($stateStack);
                if (is_array($restored)) {
                    $state = $restored;
                }
                $operands = [];
                continue;
            }

            if ($token === 'cm') {
                $matrix = $this->transformationMatrixOperand($operands);
                if ($matrix !== null) {
                    $state['transformationMatrix'] = $this->concatenateTransformationMatrices($state['transformationMatrix'], $matrix);
                }
                $operands = [];
                continue;
            }

            if ($token === 'g') {
                $state['fillColor'] = $this->grayColorOperand($operands) ?? $state['fillColor'];
                $state['fillColorSpace'] = 'DeviceGray';
                $operands = [];
                continue;
            }

            if ($token === 'rg') {
                $state['fillColor'] = $this->rgbColorOperand($operands) ?? $state['fillColor'];
                $state['fillColorSpace'] = 'DeviceRGB';
                $operands = [];
                continue;
            }

            if ($token === 'k') {
                $state['fillColor'] = $this->cmykColorOperand($operands) ?? $state['fillColor'];
                $state['fillColorSpace'] = 'DeviceCMYK';
                $operands = [];
                continue;
            }

            if ($token === 'cs') {
                $state['fillColorSpace'] = $this->colorSpaceOperand($operands) ?? $state['fillColorSpace'];
                $operands = [];
                continue;
            }

            if ($token === 'sc' || $token === 'scn') {
                $state['fillColor'] = $this->colorSpaceColorOperand($operands, (string) $state['fillColorSpace']) ?? $state['fillColor'];
                $operands = [];
                continue;
            }

            if ($token === 're') {
                $rectangle = $this->filledRectangleOperand($operands, $state['transformationMatrix']);
                if ($rectangle !== null) {
                    $pathRectangles[] = $rectangle;
                }
                $operands = [];
                continue;
            }

            if (in_array($token, ['f', 'F', 'f*', 'B', 'B*', 'b', 'b*'], true)) {
                foreach ($pathRectangles as $rectangle) {
                    $rectangles[] = $rectangle + ['fillColor' => $state['fillColor']];
                }
                $pathRectangles = [];
                $operands = [];
                continue;
            }

            if (in_array($token, ['n', 'S', 's'], true)) {
                $pathRectangles = [];
                $operands = [];
                continue;
            }

            if ($this->isOperator($token)) {
                $operands = [];
                continue;
            }

            $operands[] = $token;
        }

        return $rectangles;
    }

    /**
     * @param list<string> $operands
     * @param array{a: float, b: float, c: float, d: float, e: float, f: float} $transformationMatrix
     * @return array{x1: float, y1: float, x2: float, y2: float}|null
     */
    private function filledRectangleOperand(array $operands, array $transformationMatrix): ?array
    {
        if (count($operands) < 4) {
            return null;
        }

        $x = $this->numericOperand($operands[count($operands) - 4]);
        $y = $this->numericOperand($operands[count($operands) - 3]);
        $width = $this->numericOperand($operands[count($operands) - 2]);
        $height = $this->numericOperand($operands[count($operands) - 1]);
        if ($x === null || $y === null || $width === null || $height === null || abs($width) <= 0.000001 || abs($height) <= 0.000001) {
            return null;
        }

        $points = [
            $this->transformPoint($x, $y, $transformationMatrix),
            $this->transformPoint($x + $width, $y, $transformationMatrix),
            $this->transformPoint($x, $y + $height, $transformationMatrix),
            $this->transformPoint($x + $width, $y + $height, $transformationMatrix),
        ];
        $xs = array_map(static fn (array $point): float => $point[0], $points);
        $ys = array_map(static fn (array $point): float => $point[1], $points);

        return [
            'x1' => min($xs),
            'y1' => min($ys),
            'x2' => max($xs),
            'y2' => max($ys),
        ];
    }

    /**
     * @param list<string> $operands
     */
    private function grayColorOperand(array $operands): ?string
    {
        if ($operands === []) {
            return null;
        }

        $gray = $this->numericOperand($operands[array_key_last($operands)]);

        return $gray === null ? null : $this->rgbHexColor($gray, $gray, $gray);
    }

    /**
     * @param list<string> $operands
     */
    private function rgbColorOperand(array $operands): ?string
    {
        if (count($operands) < 3) {
            return null;
        }

        $r = $this->numericOperand($operands[count($operands) - 3]);
        $g = $this->numericOperand($operands[count($operands) - 2]);
        $b = $this->numericOperand($operands[count($operands) - 1]);
        if ($r === null || $g === null || $b === null) {
            return null;
        }

        return $this->rgbHexColor($r, $g, $b);
    }

    /**
     * @param list<string> $operands
     */
    private function cmykColorOperand(array $operands): ?string
    {
        if (count($operands) < 4) {
            return null;
        }

        $c = $this->clampedColorComponent($this->numericOperand($operands[count($operands) - 4]));
        $m = $this->clampedColorComponent($this->numericOperand($operands[count($operands) - 3]));
        $y = $this->clampedColorComponent($this->numericOperand($operands[count($operands) - 2]));
        $k = $this->clampedColorComponent($this->numericOperand($operands[count($operands) - 1]));
        if ($c === null || $m === null || $y === null || $k === null) {
            return null;
        }

        return $this->rgbHexColor((1.0 - $c) * (1.0 - $k), (1.0 - $m) * (1.0 - $k), (1.0 - $y) * (1.0 - $k));
    }

    /**
     * @param list<string> $operands
     */
    private function colorSpaceOperand(array $operands): ?string
    {
        if ($operands === []) {
            return null;
        }

        $operand = $operands[array_key_last($operands)];
        if (!is_string($operand) || !str_starts_with($operand, '/')) {
            return null;
        }

        return $this->decodePdfName(substr($operand, 1));
    }

    /**
     * @param list<string> $operands
     */
    private function colorSpaceColorOperand(array $operands, string $colorSpace): ?string
    {
        return match ($colorSpace) {
            'DeviceGray', 'G' => $this->grayColorOperand($operands),
            'DeviceRGB', 'RGB' => $this->rgbColorOperand($operands),
            'DeviceCMYK', 'CMYK' => $this->cmykColorOperand($operands),
            default => null,
        };
    }

    private function rgbHexColor(float $r, float $g, float $b): string
    {
        return sprintf(
            '#%02x%02x%02x',
            (int) round($this->clampedColorComponent($r) * 255),
            (int) round($this->clampedColorComponent($g) * 255),
            (int) round($this->clampedColorComponent($b) * 255)
        );
    }

    private function clampedColorComponent(?float $component): ?float
    {
        if ($component === null) {
            return null;
        }

        return min(1.0, max(0.0, $component));
    }

    private function diagnosticFontResourceName(?string $fontResource): string
    {
        return $fontResource === null || $fontResource === '' ? 'unscoped font' : $fontResource;
    }

    private function textShowingOperandHasGlyphBytes(string $operand): bool
    {
        $operand = trim($operand);
        if ($operand === '') {
            return false;
        }

        if (str_starts_with($operand, '[')) {
            foreach ($this->textArrayElements($operand) as $element) {
                if ($element['type'] === 'text' && $this->textShowingOperandHasGlyphBytes((string) $element['value'])) {
                    return true;
                }
            }

            return false;
        }

        if (str_starts_with($operand, '<')) {
            $hex = preg_replace('/\s+/', '', trim($operand, '<>'));
            return is_string($hex) && $hex !== '';
        }

        if (str_starts_with($operand, '(')) {
            return substr($operand, 1, -1) !== '';
        }

        return false;
    }

    /**
     * @return list<string>
     * @param array<string, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}> $fontToUnicodeMaps
     * @param array<string, array{base: string, differences: array<int, string>, suppressUnmapped: bool}> $fontEncodings
     * @param array<string, string> $propertyActualTexts
     * @param array<int, string> $mcidActualTexts
     * @param array<string, int> $propertyMcids
     */
    private function textLinesFromContentStream(string $stream, array $fontToUnicodeMaps, array $fontEncodings, array $propertyActualTexts, array $mcidActualTexts, array $propertyMcids): array
    {
        $lines = [];
        $operands = [];
        $currentLine = '';
        $currentFontResource = null;
        $currentFontSize = null;
        $currentTextLeading = null;
        $currentTextX = null;
        $currentTextY = null;
        $currentTextEndX = null;
        $currentTextEndY = null;
        $characterSpacing = 0.0;
        $wordSpacing = 0.0;
        $horizontalScale = 100.0;
        $currentTextXAxisX = 1.0;
        $currentTextXAxisY = 0.0;
        $currentTextYAxisX = 0.0;
        $currentTextYAxisY = 1.0;
        $pendingPositionWordGap = false;
        $pendingPositionGap = null;
        $pendingPositionFontSize = null;
        $textStateStack = [];
        $actualTextStack = [];
        $artifactStack = [];
        $currentTransformationMatrix = $this->identityTransformationMatrix();

        foreach ($this->contentTokens($stream) as $token) {
            if ($token === 'BDC') {
                $isArtifact = $this->markedContentIsArtifact($operands);
                $actualText = $isArtifact ? null : $this->actualTextOperand($operands, $propertyActualTexts, $mcidActualTexts, $propertyMcids);
                if ($actualText !== null) {
                    $this->appendActualText($lines, $currentLine, $actualText, $pendingPositionWordGap, $pendingPositionGap, $pendingPositionFontSize);
                }
                $actualTextStack[] = $actualText;
                $artifactStack[] = $isArtifact;
                $operands = [];
                continue;
            }

            if ($token === 'BMC') {
                $actualTextStack[] = null;
                $artifactStack[] = $this->markedContentIsArtifact($operands);
                $operands = [];
                continue;
            }

            if ($token === 'EMC') {
                array_pop($actualTextStack);
                array_pop($artifactStack);
                $operands = [];
                continue;
            }

            if ($this->isTextShowingOperator($token)) {
                if ($token === "'" || $token === '"') {
                    $this->pushLine($lines, $currentLine);
                    [$currentTextX, $currentTextY] = $this->advanceTextPositionByLeading(
                        $currentTextX,
                        $currentTextY,
                        $currentTextLeading,
                        $currentTextYAxisX,
                        $currentTextYAxisY
                    );
                    $currentTextEndX = $currentTextX;
                    $currentTextEndY = $currentTextY;
                    $pendingPositionWordGap = false;
                    $pendingPositionGap = null;
                    $pendingPositionFontSize = null;
                }

                if ($token === '"') {
                    $wordSpacing = $this->quoteWordSpacingOperand($operands) ?? $wordSpacing;
                    $characterSpacing = $this->quoteCharacterSpacingOperand($operands) ?? $characterSpacing;
                }

                $operand = $this->textShowingOperand($token, $operands);
                if ($operand !== null) {
                    $toUnicodeMap = $this->currentToUnicodeMap($fontToUnicodeMaps, $currentFontResource);
                    $fontEncoding = $this->currentFontEncoding($fontEncodings, $currentFontResource);
                    $writingMode = $this->toUnicodeWritingMode($toUnicodeMap);
                    $axis = $this->textProgressionAxis(
                        $currentTextXAxisX,
                        $currentTextXAxisY,
                        $currentTextYAxisX,
                        $currentTextYAxisY,
                        $writingMode
                    );
                    if (!$this->insideActualText($actualTextStack) && !$this->insideArtifact($artifactStack)) {
                        foreach ($this->textOperandVisualSegments(
                            $operand,
                            $toUnicodeMap,
                            $fontEncoding,
                            $currentFontSize,
                            $characterSpacing,
                            $wordSpacing,
                            $horizontalScale,
                            $axis
                        ) as $segment) {
                            if ($segment['gapBefore'] !== null && $currentLine !== '') {
                                $pendingPositionWordGap = $this->tjAdjustmentGapLooksLikeWordBoundary($segment['gapBefore'], $currentFontSize);
                                $pendingPositionGap = $segment['gapBefore'];
                                $pendingPositionFontSize = $currentFontSize;
                            }
                            $this->appendPositionedText($currentLine, $segment['text'], $pendingPositionWordGap, $pendingPositionGap, $pendingPositionFontSize);
                        }
                    }
                    [$currentTextEndX, $currentTextEndY] = $this->advanceTextEndPointForOperand(
                        $currentTextEndX ?? $currentTextX,
                        $currentTextEndY ?? $currentTextY,
                        $operand,
                        $toUnicodeMap,
                        $fontEncoding,
                        $currentFontSize,
                        $characterSpacing,
                        $wordSpacing,
                        $horizontalScale,
                        $axis
                    );
                }
                $operands = [];
                continue;
            }

            if ($token === 'q') {
                $textStateStack[] = [
                    'fontSize' => $currentFontSize,
                    'fontResource' => $currentFontResource,
                    'textLeading' => $currentTextLeading,
                    'characterSpacing' => $characterSpacing,
                    'wordSpacing' => $wordSpacing,
                    'horizontalScale' => $horizontalScale,
                    'transformationMatrix' => $currentTransformationMatrix,
                ];
                $operands = [];
                continue;
            }

            if ($token === 'Q') {
                $state = array_pop($textStateStack);
                if (is_array($state)) {
                    $currentFontSize = $state['fontSize'];
                    $currentFontResource = $state['fontResource'];
                    $currentTextLeading = $state['textLeading'];
                    $characterSpacing = $state['characterSpacing'];
                    $wordSpacing = $state['wordSpacing'];
                    $horizontalScale = $state['horizontalScale'];
                    $currentTransformationMatrix = is_array($state['transformationMatrix'] ?? null)
                        ? $state['transformationMatrix']
                        : $this->identityTransformationMatrix();
                }
                $operands = [];
                continue;
            }

            if ($token === 'Tf') {
                $currentFontResource = $this->fontResourceOperand($operands) ?? $currentFontResource;
                $currentFontSize = $this->fontSizeOperand($operands) ?? $currentFontSize;
                $operands = [];
                continue;
            }

            if ($token === 'TL') {
                $currentTextLeading = $this->textLeadingOperand($operands) ?? $currentTextLeading;
                $operands = [];
                continue;
            }

            if ($token === 'Tc') {
                $characterSpacing = $this->textCharacterSpacingOperand($operands) ?? $characterSpacing;
                $operands = [];
                continue;
            }

            if ($token === 'Tw') {
                $wordSpacing = $this->textWordSpacingOperand($operands) ?? $wordSpacing;
                $operands = [];
                continue;
            }

            if ($token === 'Tz') {
                $horizontalScale = $this->textHorizontalScaleOperand($operands) ?? $horizontalScale;
                $operands = [];
                continue;
            }

            if ($token === 'cm') {
                $matrix = $this->transformationMatrixOperand($operands);
                if ($matrix !== null) {
                    $currentTransformationMatrix = $this->concatenateTransformationMatrices($currentTransformationMatrix, $matrix);
                }
                $operands = [];
                continue;
            }

            if ($token === 'Td' || $token === 'TD') {
                if ($token === 'TD') {
                    $moveY = $this->textMoveOperandY($operands);
                    if ($moveY !== null) {
                        $currentTextLeading = -$moveY;
                    }
                }
                $writingMode = $this->currentWritingMode($fontToUnicodeMaps, $currentFontResource);
                if ($this->textMoveBreaksLine($operands, $writingMode)) {
                    $this->pushLine($lines, $currentLine);
                    $pendingPositionWordGap = false;
                    $pendingPositionGap = null;
                    $pendingPositionFontSize = null;
                } else {
                    $gap = $this->textMoveWordGap($operands, $writingMode);
                    $pendingPositionWordGap = $currentLine !== '' && $this->gapExceedsLegacyWordThreshold($gap);
                    $pendingPositionGap = $currentLine !== '' ? $gap : null;
                    $pendingPositionFontSize = $currentFontSize;
                }
                [$currentTextX, $currentTextY] = $this->textMovePosition(
                    $operands,
                    $currentTextX,
                    $currentTextY,
                    $currentTextXAxisX,
                    $currentTextXAxisY,
                    $currentTextYAxisX,
                    $currentTextYAxisY
                );
                $currentTextEndX = $currentTextX;
                $currentTextEndY = $currentTextY;
                $operands = [];
                continue;
            }

            if ($token === 'Tm') {
                $writingMode = $this->currentWritingMode($fontToUnicodeMaps, $currentFontResource);
                $axis = $this->textProgressionAxis(
                    $currentTextXAxisX,
                    $currentTextXAxisY,
                    $currentTextYAxisX,
                    $currentTextYAxisY,
                    $writingMode
                );
                if ($this->textMatrixBreaksLine($operands, $currentTextX, $currentTextY, $axis, $currentTransformationMatrix)) {
                    $this->pushLine($lines, $currentLine);
                    $pendingPositionWordGap = false;
                    $pendingPositionGap = null;
                    $pendingPositionFontSize = null;
                } else {
                    $gap = $this->textMatrixWordGap($operands, $currentTextEndX, $currentTextEndY, $axis, $writingMode, $currentTransformationMatrix);
                    $pendingPositionWordGap = $currentLine !== '' && $this->gapExceedsLegacyWordThreshold($gap);
                    $pendingPositionGap = $currentLine !== '' ? $gap : null;
                    $pendingPositionFontSize = $currentFontSize;
                }
                $matrixPosition = $this->textMatrixPosition($operands, $currentTransformationMatrix);
                $currentTextX = $matrixPosition['x'] ?? null;
                $currentTextY = $matrixPosition['y'] ?? null;
                $currentTextEndX = $currentTextX;
                $currentTextEndY = $currentTextY;
                $matrixAxes = $this->textMatrixAxes($operands, $currentTransformationMatrix);
                $currentTextXAxisX = $matrixAxes['xAxisX'] ?? 1.0;
                $currentTextXAxisY = $matrixAxes['xAxisY'] ?? 0.0;
                $currentTextYAxisX = $matrixAxes['yAxisX'] ?? 0.0;
                $currentTextYAxisY = $matrixAxes['yAxisY'] ?? 1.0;
                $operands = [];
                continue;
            }

            if ($token === 'T*') {
                $this->pushLine($lines, $currentLine);
                [$currentTextX, $currentTextY] = $this->advanceTextPositionByLeading(
                    $currentTextX,
                    $currentTextY,
                    $currentTextLeading,
                    $currentTextYAxisX,
                    $currentTextYAxisY
                );
                $currentTextEndX = $currentTextX;
                $currentTextEndY = $currentTextY;
                $pendingPositionWordGap = false;
                $pendingPositionGap = null;
                $pendingPositionFontSize = null;
                $operands = [];
                continue;
            }

            if ($token === 'BT') {
                $currentTextX = null;
                $currentTextY = null;
                $currentTextEndX = null;
                $currentTextEndY = null;
                $currentTextXAxisX = 1.0;
                $currentTextXAxisY = 0.0;
                $currentTextYAxisX = 0.0;
                $currentTextYAxisY = 1.0;
                $pendingPositionWordGap = false;
                $pendingPositionGap = null;
                $pendingPositionFontSize = null;
                $operands = [];
                continue;
            }

            if ($token === 'ET') {
                $this->pushLine($lines, $currentLine);
                $currentTextX = null;
                $currentTextY = null;
                $currentTextEndX = null;
                $currentTextEndY = null;
                $currentTextXAxisX = 1.0;
                $currentTextXAxisY = 0.0;
                $currentTextYAxisX = 0.0;
                $currentTextYAxisY = 1.0;
                $pendingPositionWordGap = false;
                $pendingPositionGap = null;
                $pendingPositionFontSize = null;
                $operands = [];
                continue;
            }

            if ($this->isOperator($token)) {
                $operands = [];
                continue;
            }

            $operands[] = $token;
        }

        $this->pushLine($lines, $currentLine);

        return $lines;
    }

    /**
     * @return list<array{text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}>
     * @param array<string, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}> $fontToUnicodeMaps
     * @param array<string, array{base: string, differences: array<int, string>, suppressUnmapped: bool}> $fontEncodings
     * @param array<string, string> $propertyActualTexts
     * @param array<int, string> $mcidActualTexts
     * @param array<string, int> $propertyMcids
     */
    private function positionedTextRunsFromContentStream(string $stream, array $fontToUnicodeMaps, array $fontEncodings, array $propertyActualTexts, array $mcidActualTexts, array $propertyMcids): array
    {
        $runs = [];
        $operands = [];
        $currentFontResource = null;
        $currentFontSize = null;
        $currentTextLeading = null;
        $currentTextX = null;
        $currentTextY = null;
        $currentTextEndX = null;
        $currentTextEndY = null;
        $characterSpacing = 0.0;
        $wordSpacing = 0.0;
        $horizontalScale = 100.0;
        $currentTextXAxisX = 1.0;
        $currentTextXAxisY = 0.0;
        $currentTextYAxisX = 0.0;
        $currentTextYAxisY = 1.0;
        $textStateStack = [];
        $actualTextStack = [];
        $artifactStack = [];
        $currentTransformationMatrix = $this->identityTransformationMatrix();
        $maxRuns = $this->maxPositionedTextRuns();

        foreach ($this->contentTokens($stream) as $token) {
            if ($token === 'BDC') {
                $isArtifact = $this->markedContentIsArtifact($operands);
                $actualTextStack[] = $isArtifact ? null : $this->actualTextOperand($operands, $propertyActualTexts, $mcidActualTexts, $propertyMcids);
                $artifactStack[] = $isArtifact;
                $operands = [];
                continue;
            }

            if ($token === 'BMC') {
                $actualTextStack[] = null;
                $artifactStack[] = $this->markedContentIsArtifact($operands);
                $operands = [];
                continue;
            }

            if ($token === 'EMC') {
                array_pop($actualTextStack);
                array_pop($artifactStack);
                $operands = [];
                continue;
            }

            if ($this->isTextShowingOperator($token)) {
                if ($token === "'" || $token === '"') {
                    [$currentTextX, $currentTextY] = $this->advanceTextPositionByLeading(
                        $currentTextX,
                        $currentTextY,
                        $currentTextLeading,
                        $currentTextYAxisX,
                        $currentTextYAxisY
                    );
                    $currentTextEndX = $currentTextX;
                    $currentTextEndY = $currentTextY;
                }

                if ($token === '"') {
                    $wordSpacing = $this->quoteWordSpacingOperand($operands) ?? $wordSpacing;
                    $characterSpacing = $this->quoteCharacterSpacingOperand($operands) ?? $characterSpacing;
                }

                $operand = $this->textShowingOperand($token, $operands);
                if ($operand !== null) {
                    $toUnicodeMap = $this->currentToUnicodeMap($fontToUnicodeMaps, $currentFontResource);
                    $fontEncoding = $this->currentFontEncoding($fontEncodings, $currentFontResource);
                    $writingMode = $this->toUnicodeWritingMode($toUnicodeMap);
                    $axis = $this->textProgressionAxis(
                        $currentTextXAxisX,
                        $currentTextXAxisY,
                        $currentTextYAxisX,
                        $currentTextYAxisY,
                        $writingMode
                    );
                    $startX = $currentTextEndX ?? $currentTextX;
                    $startY = $currentTextEndY ?? $currentTextY;
                    [$nextTextEndX, $nextTextEndY] = $this->advanceTextEndPointForOperand(
                        $startX,
                        $startY,
                        $operand,
                        $toUnicodeMap,
                        $fontEncoding,
                        $currentFontSize,
                        $characterSpacing,
                        $wordSpacing,
                        $horizontalScale,
                        $axis
                    );

                    if (!$this->insideActualText($actualTextStack)
                        && !$this->insideArtifact($artifactStack)
                        && $startX !== null
                        && $startY !== null
                    ) {
                        foreach ($this->textOperandVisualSegments(
                            $operand,
                            $toUnicodeMap,
                            $fontEncoding,
                            $currentFontSize,
                            $characterSpacing,
                            $wordSpacing,
                            $horizontalScale,
                            $axis
                        ) as $segment) {
                            $segmentStartX = $startX + ($segment['startOffset'] * $axis['x']);
                            $segmentStartY = $startY + ($segment['startOffset'] * $axis['y']);
                            $segmentEndX = $startX + ($segment['endOffset'] * $axis['x']);
                            $segmentEndY = $startY + ($segment['endOffset'] * $axis['y']);
                            $runs[] = $this->positionedTextRun(
                                $segment['text'],
                                $segmentStartX,
                                $segmentStartY,
                                $segmentEndX,
                                $segmentEndY,
                                $currentFontSize,
                                $axis
                            );
                            if (count($runs) >= $maxRuns) {
                                return $runs;
                            }
                        }
                    }

                    $currentTextEndX = $nextTextEndX;
                    $currentTextEndY = $nextTextEndY;
                }
                $operands = [];
                continue;
            }

            if ($token === 'q') {
                $textStateStack[] = [
                    'fontSize' => $currentFontSize,
                    'fontResource' => $currentFontResource,
                    'textLeading' => $currentTextLeading,
                    'characterSpacing' => $characterSpacing,
                    'wordSpacing' => $wordSpacing,
                    'horizontalScale' => $horizontalScale,
                    'textX' => $currentTextX,
                    'textY' => $currentTextY,
                    'textEndX' => $currentTextEndX,
                    'textEndY' => $currentTextEndY,
                    'xAxisX' => $currentTextXAxisX,
                    'xAxisY' => $currentTextXAxisY,
                    'yAxisX' => $currentTextYAxisX,
                    'yAxisY' => $currentTextYAxisY,
                    'transformationMatrix' => $currentTransformationMatrix,
                ];
                $operands = [];
                continue;
            }

            if ($token === 'Q') {
                $state = array_pop($textStateStack);
                if (is_array($state)) {
                    $currentFontSize = $state['fontSize'];
                    $currentFontResource = $state['fontResource'];
                    $currentTextLeading = $state['textLeading'];
                    $characterSpacing = $state['characterSpacing'];
                    $wordSpacing = $state['wordSpacing'];
                    $horizontalScale = $state['horizontalScale'];
                    $currentTextX = $state['textX'];
                    $currentTextY = $state['textY'];
                    $currentTextEndX = $state['textEndX'];
                    $currentTextEndY = $state['textEndY'];
                    $currentTextXAxisX = $state['xAxisX'];
                    $currentTextXAxisY = $state['xAxisY'];
                    $currentTextYAxisX = $state['yAxisX'];
                    $currentTextYAxisY = $state['yAxisY'];
                    $currentTransformationMatrix = is_array($state['transformationMatrix'] ?? null)
                        ? $state['transformationMatrix']
                        : $this->identityTransformationMatrix();
                }
                $operands = [];
                continue;
            }

            if ($token === 'Tf') {
                $currentFontResource = $this->fontResourceOperand($operands) ?? $currentFontResource;
                $currentFontSize = $this->fontSizeOperand($operands) ?? $currentFontSize;
                $operands = [];
                continue;
            }

            if ($token === 'TL') {
                $currentTextLeading = $this->textLeadingOperand($operands) ?? $currentTextLeading;
                $operands = [];
                continue;
            }

            if ($token === 'Tc') {
                $characterSpacing = $this->textCharacterSpacingOperand($operands) ?? $characterSpacing;
                $operands = [];
                continue;
            }

            if ($token === 'Tw') {
                $wordSpacing = $this->textWordSpacingOperand($operands) ?? $wordSpacing;
                $operands = [];
                continue;
            }

            if ($token === 'Tz') {
                $horizontalScale = $this->textHorizontalScaleOperand($operands) ?? $horizontalScale;
                $operands = [];
                continue;
            }

            if ($token === 'cm') {
                $matrix = $this->transformationMatrixOperand($operands);
                if ($matrix !== null) {
                    $currentTransformationMatrix = $this->concatenateTransformationMatrices($currentTransformationMatrix, $matrix);
                }
                $operands = [];
                continue;
            }

            if ($token === 'Td' || $token === 'TD') {
                if ($token === 'TD') {
                    $moveY = $this->textMoveOperandY($operands);
                    if ($moveY !== null) {
                        $currentTextLeading = -$moveY;
                    }
                }
                [$currentTextX, $currentTextY] = $this->textMovePosition(
                    $operands,
                    $currentTextX,
                    $currentTextY,
                    $currentTextXAxisX,
                    $currentTextXAxisY,
                    $currentTextYAxisX,
                    $currentTextYAxisY
                );
                $currentTextEndX = $currentTextX;
                $currentTextEndY = $currentTextY;
                $operands = [];
                continue;
            }

            if ($token === 'Tm') {
                $matrixPosition = $this->textMatrixPosition($operands, $currentTransformationMatrix);
                $currentTextX = $matrixPosition['x'] ?? null;
                $currentTextY = $matrixPosition['y'] ?? null;
                $currentTextEndX = $currentTextX;
                $currentTextEndY = $currentTextY;
                $matrixAxes = $this->textMatrixAxes($operands, $currentTransformationMatrix);
                $currentTextXAxisX = $matrixAxes['xAxisX'] ?? 1.0;
                $currentTextXAxisY = $matrixAxes['xAxisY'] ?? 0.0;
                $currentTextYAxisX = $matrixAxes['yAxisX'] ?? 0.0;
                $currentTextYAxisY = $matrixAxes['yAxisY'] ?? 1.0;
                $operands = [];
                continue;
            }

            if ($token === 'T*') {
                [$currentTextX, $currentTextY] = $this->advanceTextPositionByLeading(
                    $currentTextX,
                    $currentTextY,
                    $currentTextLeading,
                    $currentTextYAxisX,
                    $currentTextYAxisY
                );
                $currentTextEndX = $currentTextX;
                $currentTextEndY = $currentTextY;
                $operands = [];
                continue;
            }

            if ($token === 'BT') {
                $currentTextX = null;
                $currentTextY = null;
                $currentTextEndX = null;
                $currentTextEndY = null;
                $currentTextXAxisX = 1.0;
                $currentTextXAxisY = 0.0;
                $currentTextYAxisX = 0.0;
                $currentTextYAxisY = 1.0;
                $operands = [];
                continue;
            }

            if ($token === 'ET') {
                $currentTextX = null;
                $currentTextY = null;
                $currentTextEndX = null;
                $currentTextEndY = null;
                $currentTextXAxisX = 1.0;
                $currentTextXAxisY = 0.0;
                $currentTextYAxisX = 0.0;
                $currentTextYAxisY = 1.0;
                $operands = [];
                continue;
            }

            if ($this->isOperator($token)) {
                $operands = [];
                continue;
            }

            $operands[] = $token;
        }

        return $runs;
    }

    /**
     * @param array{x: float, y: float, scale: float} $axis
     * @return array{text: string, x1: float, y1: float, x2: float, y2: float, textX1: float, textY1: float, textX2: float, textY2: float, fontSize: float}
     */
    private function positionedTextRun(string $text, float $startX, float $startY, float $endX, float $endY, ?float $fontSize, array $axis): array
    {
        $resolvedFontSize = $fontSize ?? 12.0;
        $height = max(1.0, $resolvedFontSize * max(1.0, $axis['scale']));
        $padding = $height * 0.25;

        return [
            'text' => $text,
            'x1' => min($startX, $endX) - $padding,
            'y1' => min($startY, $endY) - $padding,
            'x2' => max($startX, $endX) + $padding,
            'y2' => max($startY, $endY) + $height,
            'textX1' => min($startX, $endX),
            'textY1' => min($startY, $endY),
            'textX2' => max($startX, $endX),
            'textY2' => max($startY, $endY),
            'fontSize' => $resolvedFontSize,
        ];
    }

    /**
     * @return list<string>
     */
    private function contentTokens(string $stream): array
    {
        if (strlen($stream) > $this->maxTokenizedContentStreamBytes()) {
            return [];
        }

        $tokens = [];
        $length = strlen($stream);
        $index = 0;

        while ($index < $length) {
            $char = $stream[$index];
            if (ctype_space($char)) {
                $index++;
                continue;
            }

            if ($char === '%') {
                while ($index < $length && !in_array($stream[$index], ["\n", "\r"], true)) {
                    $index++;
                }
                continue;
            }

            if ($char === '(') {
                $tokens[] = $this->readLiteralToken($stream, $index);
                if (count($tokens) >= $this->maxContentTokens()) {
                    break;
                }
                continue;
            }

            if ($char === '<' && $index + 1 < $length && $stream[$index + 1] === '<') {
                $tokens[] = $this->readDictionaryToken($stream, $index);
                if (count($tokens) >= $this->maxContentTokens()) {
                    break;
                }
                continue;
            }

            if ($char === '<' && ($index + 1 >= $length || $stream[$index + 1] !== '<')) {
                $tokens[] = $this->readHexToken($stream, $index);
                if (count($tokens) >= $this->maxContentTokens()) {
                    break;
                }
                continue;
            }

            if ($char === '[') {
                $tokens[] = $this->readArrayToken($stream, $index);
                if (count($tokens) >= $this->maxContentTokens()) {
                    break;
                }
                continue;
            }

            $start = $index;
            while ($index < $length && !$this->isDelimiter($stream[$index])) {
                $index++;
            }
            if ($index === $start) {
                $index++;
                continue;
            }
            $token = substr($stream, $start, $index - $start);
            if ($token === 'BI') {
                $this->skipInlineImage($stream, $index);
                continue;
            }
            if ($token !== '') {
                $tokens[] = $token;
                if (count($tokens) >= $this->maxContentTokens()) {
                    break;
                }
            }
        }

        return $tokens;
    }

    private function maxTokenizedContentStreamBytes(): int
    {
        foreach (['pdfMaxTokenizedContentStreamBytes', 'maxTokenizedContentStreamBytes'] as $key) {
            if (array_key_exists($key, $this->options) && $this->options[$key] !== null && $this->options[$key] !== '') {
                return max(1, (int) $this->options[$key]);
            }
        }

        return self::DEFAULT_MAX_TOKENIZED_CONTENT_STREAM_BYTES;
    }

    private function maxContentTokens(): int
    {
        foreach (['pdfMaxContentTokens', 'maxContentTokens'] as $key) {
            if (array_key_exists($key, $this->options) && $this->options[$key] !== null && $this->options[$key] !== '') {
                return max(1, (int) $this->options[$key]);
            }
        }

        return self::DEFAULT_MAX_CONTENT_TOKENS;
    }

    private function maxPositionedTextRuns(): int
    {
        foreach (['pdfMaxPositionedTextRuns', 'maxPositionedTextRuns'] as $key) {
            if (array_key_exists($key, $this->options) && $this->options[$key] !== null && $this->options[$key] !== '') {
                return max(1, (int) $this->options[$key]);
            }
        }

        return self::DEFAULT_MAX_POSITIONED_TEXT_RUNS;
    }

    private function skipInlineImage(string $stream, int &$index): void
    {
        $length = strlen($stream);
        $tail = substr($stream, $index);
        if (preg_match('/(?:^|\s)ID\s/s', $tail, $idMatch, PREG_OFFSET_CAPTURE) !== 1) {
            return;
        }

        $dataStart = $index + $idMatch[0][1] + strlen($idMatch[0][0]);
        $dataTail = substr($stream, $dataStart);
        if (preg_match('/\sEI(?=\s|$)/s', $dataTail, $endMatch, PREG_OFFSET_CAPTURE) !== 1) {
            $index = $length;
            return;
        }

        $index = $dataStart + $endMatch[0][1] + strlen($endMatch[0][0]);
    }

    private function readLiteralToken(string $stream, int &$index): string
    {
        $start = $index;
        $depth = 0;
        $length = strlen($stream);

        while ($index < $length) {
            $char = $stream[$index];
            if ($char === '\\') {
                $index += 2;
                continue;
            }
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    $index++;
                    break;
                }
            }
            $index++;
        }

        return substr($stream, $start, $index - $start);
    }

    private function readDictionaryToken(string $stream, int &$index): string
    {
        $start = $index;
        $depth = 0;
        $length = strlen($stream);

        while ($index < $length) {
            if ($index + 1 < $length && $stream[$index] === '<' && $stream[$index + 1] === '<') {
                $depth++;
                $index += 2;
                continue;
            }

            if ($index + 1 < $length && $stream[$index] === '>' && $stream[$index + 1] === '>') {
                $depth--;
                $index += 2;
                if ($depth <= 0) {
                    break;
                }
                continue;
            }

            $char = $stream[$index];
            if ($char === '(') {
                $this->readLiteralToken($stream, $index);
                continue;
            }
            if ($char === '<') {
                $this->readHexToken($stream, $index);
                continue;
            }
            if ($char === '[') {
                $this->readArrayToken($stream, $index);
                continue;
            }
            if ($char === '%') {
                while ($index < $length && !in_array($stream[$index], ["\n", "\r"], true)) {
                    $index++;
                }
                continue;
            }
            $index++;
        }

        return substr($stream, $start, $index - $start);
    }

    private function readHexToken(string $stream, int &$index): string
    {
        $start = $index;
        $length = strlen($stream);
        $index++;

        while ($index < $length && $stream[$index] !== '>') {
            $index++;
        }
        if ($index < $length) {
            $index++;
        }

        return substr($stream, $start, $index - $start);
    }

    private function readArrayToken(string $stream, int &$index): string
    {
        $start = $index;
        $length = strlen($stream);
        $index++;
        $depth = 1;

        while ($index < $length) {
            $char = $stream[$index];
            if ($char === '(') {
                $this->readLiteralToken($stream, $index);
                continue;
            }
            if ($char === '<' && $index + 1 < $length && $stream[$index + 1] === '<') {
                $this->readDictionaryToken($stream, $index);
                continue;
            }
            if ($char === '<' && ($index + 1 >= $length || $stream[$index + 1] !== '<')) {
                $this->readHexToken($stream, $index);
                continue;
            }
            if ($char === '[') {
                $depth++;
                $index++;
                continue;
            }
            if ($char === ']') {
                $index++;
                $depth--;
                if ($depth <= 0) {
                    break;
                }
                continue;
            }
            $index++;
        }

        return substr($stream, $start, $index - $start);
    }

    private function isDelimiter(string $char): bool
    {
        return ctype_space($char) || str_contains('[]()<>{}%', $char);
    }

    /**
     * @param list<string> $operands
     */
    private function textShowingOperand(string $operator, array $operands): ?string
    {
        if ($operator === '"') {
            for ($index = count($operands) - 1; $index >= 0; $index--) {
                if ($this->isTextOperand($operands[$index])) {
                    return $operands[$index];
                }
            }

            return null;
        }

        $operand = end($operands);
        return is_string($operand) && $this->isTextOperand($operand) ? $operand : null;
    }

    private function isTextShowingOperator(string $token): bool
    {
        return in_array($token, ['Tj', 'TJ', "'", '"'], true);
    }

    private function isTextOperand(string $token): bool
    {
        $token = ltrim($token);
        return str_starts_with($token, '(') || str_starts_with($token, '[') || preg_match('/^<[\da-fA-F\s]*>$/', $token) === 1;
    }

    private function isOperator(string $token): bool
    {
        return preg_match('/^[A-Za-z*"\']+$/', $token) === 1;
    }

    /**
     * @param list<string> $operands
     */
    private function fontResourceOperand(array $operands): ?string
    {
        if (count($operands) < 2) {
            return null;
        }

        $operand = $operands[count($operands) - 2];
        if (!str_starts_with($operand, '/')) {
            return null;
        }

        return $this->decodePdfName(substr($operand, 1));
    }

    /**
     * @param array<string, array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}> $fontToUnicodeMaps
     * @return array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>}|null
     */
    private function currentToUnicodeMap(array $fontToUnicodeMaps, ?string $fontResource): ?array
    {
        if ($fontResource !== null && isset($fontToUnicodeMaps[$fontResource])) {
            return $fontToUnicodeMaps[$fontResource];
        }

        return $fontToUnicodeMaps[''] ?? null;
    }

    private function currentWritingMode(array $fontToUnicodeMaps, ?string $fontResource): int
    {
        return $this->toUnicodeWritingMode($this->currentToUnicodeMap($fontToUnicodeMaps, $fontResource));
    }

    private function toUnicodeWritingMode(?array $toUnicodeMap): int
    {
        return (int) ($toUnicodeMap['writingMode'] ?? 0);
    }

    /**
     * @param array<string, array{base: string, differences: array<int, string>, suppressUnmapped: bool}> $fontEncodings
     * @return array{base: string, differences: array<int, string>, suppressUnmapped: bool}|null
     */
    private function currentFontEncoding(array $fontEncodings, ?string $fontResource): ?array
    {
        if ($fontResource !== null && isset($fontEncodings[$fontResource])) {
            return $fontEncodings[$fontResource];
        }

        return $fontEncodings[''] ?? null;
    }

    /**
     * @param list<string> $operands
     */
    private function fontSizeOperand(array $operands): ?float
    {
        if ($operands === []) {
            return null;
        }

        return $this->numericOperand($operands[count($operands) - 1]);
    }

    /**
     * @param list<string> $operands
     */
    private function textLeadingOperand(array $operands): ?float
    {
        if ($operands === []) {
            return null;
        }

        return $this->numericOperand($operands[count($operands) - 1]);
    }

    /**
     * @param list<string> $operands
     */
    private function textCharacterSpacingOperand(array $operands): ?float
    {
        if ($operands === []) {
            return null;
        }

        return $this->numericOperand($operands[count($operands) - 1]);
    }

    /**
     * @param list<string> $operands
     */
    private function textWordSpacingOperand(array $operands): ?float
    {
        if ($operands === []) {
            return null;
        }

        return $this->numericOperand($operands[count($operands) - 1]);
    }

    /**
     * @param list<string> $operands
     */
    private function textHorizontalScaleOperand(array $operands): ?float
    {
        if ($operands === []) {
            return null;
        }

        return $this->numericOperand($operands[count($operands) - 1]);
    }

    /**
     * @param list<string> $operands
     */
    private function quoteWordSpacingOperand(array $operands): ?float
    {
        if (count($operands) < 3) {
            return null;
        }

        return $this->numericOperand($operands[count($operands) - 3]);
    }

    /**
     * @param list<string> $operands
     */
    private function quoteCharacterSpacingOperand(array $operands): ?float
    {
        if (count($operands) < 3) {
            return null;
        }

        return $this->numericOperand($operands[count($operands) - 2]);
    }

    /**
     * @param list<string> $operands
     */
    private function textMoveBreaksLine(array $operands, int $writingMode = 0): bool
    {
        if ($writingMode === 1) {
            $tx = $this->textMoveOperandX($operands);
            if ($tx === null) {
                return true;
            }

            return abs($tx) > 0.000001;
        }

        $ty = $this->textMoveOperandY($operands);
        if ($ty === null) {
            return true;
        }

        return abs($ty) > 0.000001;
    }

    /**
     * @param list<string> $operands
     */
    private function textMoveCreatesWordGap(array $operands, int $writingMode = 0, ?float $fontSize = null): bool
    {
        $gap = $this->textMoveWordGap($operands, $writingMode);

        return $this->gapExceedsLegacyWordThreshold($gap);
    }

    /**
     * @param list<string> $operands
     */
    private function textMoveWordGap(array $operands, int $writingMode = 0): ?float
    {
        if ($writingMode === 1) {
            $ty = $this->textMoveOperandY($operands);
            if ($ty === null) {
                return null;
            }

            return abs($ty);
        }

        $tx = $this->textMoveOperandX($operands);
        if ($tx === null) {
            return null;
        }

        return $tx >= -1.5 ? $tx : null;
    }

    /**
     * @param list<string> $operands
     */
    private function textMovePosition(
        array $operands,
        ?float $currentTextX,
        ?float $currentTextY,
        float $xAxisX,
        float $xAxisY,
        float $yAxisX,
        float $yAxisY
    ): array {
        $tx = $this->textMoveOperandX($operands);
        $ty = $this->textMoveOperandY($operands);
        if ($tx === null || $ty === null) {
            return [null, null];
        }

        $originX = $currentTextX ?? 0.0;
        $originY = $currentTextY ?? 0.0;

        return [
            $originX + ($tx * $xAxisX) + ($ty * $yAxisX),
            $originY + ($tx * $xAxisY) + ($ty * $yAxisY),
        ];
    }

    /**
     * @param list<string> $operands
     */
    private function textMoveOperandX(array $operands): ?float
    {
        if (count($operands) < 2) {
            return null;
        }

        return $this->numericOperand($operands[count($operands) - 2]);
    }

    /**
     * @param list<string> $operands
     */
    private function textMoveOperandY(array $operands): ?float
    {
        if (count($operands) < 2) {
            return null;
        }

        return $this->numericOperand($operands[count($operands) - 1]);
    }

    /**
     * @param list<string> $operands
     */
    private function textMatrixBreaksLine(array $operands, ?float $currentTextX, ?float $currentTextY, array $axis, ?array $transformationMatrix = null): bool
    {
        $position = $this->textMatrixPosition($operands, $transformationMatrix);
        $matrixX = $position['x'] ?? null;
        $matrixY = $position['y'] ?? null;
        if ($matrixX === null || $matrixY === null || $currentTextX === null || $currentTextY === null) {
            return true;
        }

        $deltaX = $matrixX - $currentTextX;
        $deltaY = $matrixY - $currentTextY;
        $perpendicularDistance = abs(($deltaX * -$axis['y']) + ($deltaY * $axis['x']));

        return $perpendicularDistance > 0.000001;
    }

    /**
     * @param list<string> $operands
     * @param array{x: float, y: float, scale: float} $axis
     */
    private function textMatrixCreatesWordGap(array $operands, ?float $currentTextEndX, ?float $currentTextEndY, array $axis, int $writingMode = 0, ?array $transformationMatrix = null, ?float $fontSize = null): bool
    {
        $gap = $this->textMatrixWordGap($operands, $currentTextEndX, $currentTextEndY, $axis, $writingMode, $transformationMatrix);

        return $this->gapExceedsLegacyWordThreshold($gap);
    }

    /**
     * @param list<string> $operands
     * @param array{x: float, y: float, scale: float} $axis
     */
    private function textMatrixWordGap(array $operands, ?float $currentTextEndX, ?float $currentTextEndY, array $axis, int $writingMode = 0, ?array $transformationMatrix = null): ?float
    {
        $position = $this->textMatrixPosition($operands, $transformationMatrix);
        $matrixX = $position['x'] ?? null;
        $matrixY = $position['y'] ?? null;
        if ($matrixX === null || $matrixY === null || $currentTextEndX === null || $currentTextEndY === null) {
            return null;
        }

        $projection = (($matrixX - $currentTextEndX) * $axis['x']) + (($matrixY - $currentTextEndY) * $axis['y']);
        if ($writingMode === 1) {
            return abs($projection);
        }

        return $projection >= -1.5 ? $projection : null;
    }

    private function gapExceedsLegacyWordThreshold(?float $gap): bool
    {
        return $gap !== null && $gap >= self::POSITIONED_TEXT_WORD_GAP;
    }

    private function positionedGapLooksLikeWordBoundary(?float $gap, ?float $fontSize, string $leftText, string $rightText): bool
    {
        if ($gap === null) {
            return false;
        }
        if ($gap < max(2.0, ($fontSize ?? 12.0) * 0.35)) {
            return false;
        }

        $leftWord = $this->lastWordToken($leftText);
        $rightWord = $this->firstWordToken($rightText);
        if ($rightWord === '') {
            return false;
        }

        if (preg_match('/[:,;]$/u', rtrim($leftText)) === 1) {
            return true;
        }
        if ($leftWord === '') {
            return false;
        }

        $lowerRight = strtolower($rightWord);
        if ($this->length($rightWord) === 1) {
            return in_array($lowerRight, ['a', 'i', 'o', 'u', 'w', 'z'], true);
        }
        if ($this->length($leftWord) === 1) {
            return in_array(strtolower($leftWord), ['a', 'i', 'o', 'u', 'w', 'z'], true);
        }
        if (preg_match('/^\p{Lu}{2,}$/u', $rightWord) === 1) {
            return true;
        }
        if (preg_match('/^\p{Lu}\p{Ll}+$/u', $rightWord) === 1) {
            return false;
        }

        return preg_match('/^\p{Ll}/u', $rightWord) === 1 && $this->length($rightWord) > 2;
    }

    private function lastWordToken(string $text): string
    {
        if (preg_match('/[\p{L}\p{N}]+$/u', rtrim($text), $match) !== 1) {
            return '';
        }

        return $match[0];
    }

    private function firstWordToken(string $text): string
    {
        if (preg_match('/^[^\p{L}\p{N}]*([\p{L}\p{N}]+)/u', ltrim($text), $match) !== 1) {
            return '';
        }

        return $match[1];
    }

    /**
     * @param list<string> $operands
     */
    private function textMatrixX(array $operands): ?float
    {
        if (count($operands) < 6) {
            return null;
        }

        return $this->numericOperand($operands[count($operands) - 2]);
    }

    /**
     * @param list<string> $operands
     */
    private function textMatrixY(array $operands): ?float
    {
        if (count($operands) < 6) {
            return null;
        }

        return $this->numericOperand($operands[count($operands) - 1]);
    }

    /**
     * @param list<string> $operands
     * @return array{x: ?float, y: ?float}
     */
    private function textMatrixPosition(array $operands, ?array $transformationMatrix = null): array
    {
        $x = $this->textMatrixX($operands);
        $y = $this->textMatrixY($operands);
        if ($x === null || $y === null) {
            return ['x' => null, 'y' => null];
        }

        if ($transformationMatrix === null) {
            return ['x' => $x, 'y' => $y];
        }

        [$transformedX, $transformedY] = $this->transformPoint($x, $y, $transformationMatrix);
        return ['x' => $transformedX, 'y' => $transformedY];
    }

    /**
     * @param list<string> $operands
     * @return array{xAxisX: float, xAxisY: float, yAxisX: float, yAxisY: float}|null
     */
    private function textMatrixAxes(array $operands, ?array $transformationMatrix = null): ?array
    {
        if (count($operands) < 6) {
            return null;
        }

        $xAxisX = $this->numericOperand($operands[count($operands) - 6]);
        $xAxisY = $this->numericOperand($operands[count($operands) - 5]);
        $yAxisX = $this->numericOperand($operands[count($operands) - 4]);
        $yAxisY = $this->numericOperand($operands[count($operands) - 3]);
        if ($xAxisX === null || $xAxisY === null || $yAxisX === null || $yAxisY === null) {
            return null;
        }

        if ($transformationMatrix !== null) {
            [$xAxisX, $xAxisY] = $this->transformVector($xAxisX, $xAxisY, $transformationMatrix);
            [$yAxisX, $yAxisY] = $this->transformVector($yAxisX, $yAxisY, $transformationMatrix);
        }

        return [
            'xAxisX' => $xAxisX,
            'xAxisY' => $xAxisY,
            'yAxisX' => $yAxisX,
            'yAxisY' => $yAxisY,
        ];
    }

    /**
     * @return array{a: float, b: float, c: float, d: float, e: float, f: float}
     */
    private function identityTransformationMatrix(): array
    {
        return [
            'a' => 1.0,
            'b' => 0.0,
            'c' => 0.0,
            'd' => 1.0,
            'e' => 0.0,
            'f' => 0.0,
        ];
    }

    /**
     * @param list<string> $operands
     * @return array{a: float, b: float, c: float, d: float, e: float, f: float}|null
     */
    private function transformationMatrixOperand(array $operands): ?array
    {
        if (count($operands) < 6) {
            return null;
        }

        $a = $this->numericOperand($operands[count($operands) - 6]);
        $b = $this->numericOperand($operands[count($operands) - 5]);
        $c = $this->numericOperand($operands[count($operands) - 4]);
        $d = $this->numericOperand($operands[count($operands) - 3]);
        $e = $this->numericOperand($operands[count($operands) - 2]);
        $f = $this->numericOperand($operands[count($operands) - 1]);
        if ($a === null || $b === null || $c === null || $d === null || $e === null || $f === null) {
            return null;
        }

        return [
            'a' => $a,
            'b' => $b,
            'c' => $c,
            'd' => $d,
            'e' => $e,
            'f' => $f,
        ];
    }

    /**
     * @param array{a: float, b: float, c: float, d: float, e: float, f: float} $current
     * @param array{a: float, b: float, c: float, d: float, e: float, f: float} $next
     * @return array{a: float, b: float, c: float, d: float, e: float, f: float}
     */
    private function concatenateTransformationMatrices(array $current, array $next): array
    {
        return [
            'a' => ($current['a'] * $next['a']) + ($current['c'] * $next['b']),
            'b' => ($current['b'] * $next['a']) + ($current['d'] * $next['b']),
            'c' => ($current['a'] * $next['c']) + ($current['c'] * $next['d']),
            'd' => ($current['b'] * $next['c']) + ($current['d'] * $next['d']),
            'e' => ($current['a'] * $next['e']) + ($current['c'] * $next['f']) + $current['e'],
            'f' => ($current['b'] * $next['e']) + ($current['d'] * $next['f']) + $current['f'],
        ];
    }

    /**
     * @param array{a: float, b: float, c: float, d: float, e: float, f: float} $matrix
     * @return array{0: float, 1: float}
     */
    private function transformPoint(float $x, float $y, array $matrix): array
    {
        return [
            ($matrix['a'] * $x) + ($matrix['c'] * $y) + $matrix['e'],
            ($matrix['b'] * $x) + ($matrix['d'] * $y) + $matrix['f'],
        ];
    }

    /**
     * @param array{a: float, b: float, c: float, d: float, e: float, f: float} $matrix
     * @return array{0: float, 1: float}
     */
    private function transformVector(float $x, float $y, array $matrix): array
    {
        return [
            ($matrix['a'] * $x) + ($matrix['c'] * $y),
            ($matrix['b'] * $x) + ($matrix['d'] * $y),
        ];
    }

    /**
     * @return array{x: float, y: float, scale: float}
     */
    private function textProgressionAxis(float $xAxisX, float $xAxisY, float $yAxisX, float $yAxisY, int $writingMode): array
    {
        if ($writingMode === 1) {
            return $this->normalizedTextAxis(-$yAxisX, -$yAxisY, 0.0, -1.0);
        }

        return $this->normalizedTextAxis($xAxisX, $xAxisY, 1.0, 0.0);
    }

    /**
     * @return array{x: float, y: float, scale: float}
     */
    private function normalizedTextAxis(float $axisX, float $axisY, float $fallbackX, float $fallbackY): array
    {
        $scale = sqrt(($axisX * $axisX) + ($axisY * $axisY));
        if ($scale <= 0.000001) {
            return [
                'x' => $fallbackX,
                'y' => $fallbackY,
                'scale' => 1.0,
            ];
        }

        return [
            'x' => $axisX / $scale,
            'y' => $axisY / $scale,
            'scale' => $scale,
        ];
    }

    /**
     * @return array{0: ?float, 1: ?float}
     */
    private function advanceTextPositionByLeading(
        ?float $currentTextX,
        ?float $currentTextY,
        ?float $currentTextLeading,
        float $yAxisX,
        float $yAxisY
    ): array {
        if ($currentTextX === null || $currentTextY === null || $currentTextLeading === null) {
            return [$currentTextX, $currentTextY];
        }

        return [
            $currentTextX - ($currentTextLeading * $yAxisX),
            $currentTextY - ($currentTextLeading * $yAxisY),
        ];
    }

    /**
     * @param list<string> $lines
     */
    private function pushLine(array &$lines, string &$currentLine): void
    {
        $line = rtrim($currentLine);
        if ($line !== '') {
            $lines[] = $line;
        }
        $currentLine = '';
    }

    private function appendPositionedText(string &$currentLine, string $decoded, bool &$pendingPositionWordGap, ?float &$pendingPositionGap = null, ?float &$pendingPositionFontSize = null): void
    {
        if ($decoded === '') {
            $pendingPositionWordGap = false;
            $pendingPositionGap = null;
            $pendingPositionFontSize = null;
            return;
        }

        if (!$this->endsWithWhitespace($currentLine)
            && !$this->startsWithWhitespace($decoded)
            && ($pendingPositionWordGap || $this->positionedGapLooksLikeWordBoundary($pendingPositionGap, $pendingPositionFontSize, $currentLine, $decoded))
        ) {
            $currentLine .= ' ';
        }

        $currentLine .= $decoded;
        $pendingPositionWordGap = false;
        $pendingPositionGap = null;
        $pendingPositionFontSize = null;
    }

    /**
     * @param list<string> $lines
     */
    private function appendActualText(array &$lines, string &$currentLine, string $actualText, bool &$pendingPositionWordGap, ?float &$pendingPositionGap = null, ?float &$pendingPositionFontSize = null): void
    {
        $parts = preg_split('/\R/u', $actualText);
        if ($parts === false) {
            $parts = [$actualText];
        }

        foreach ($parts as $index => $part) {
            if ($index > 0) {
                $this->pushLine($lines, $currentLine);
                $pendingPositionWordGap = false;
                $pendingPositionGap = null;
                $pendingPositionFontSize = null;
            }
            $this->appendPositionedText($currentLine, $part, $pendingPositionWordGap, $pendingPositionGap, $pendingPositionFontSize);
        }
    }

    /**
     * @param array{cidWidths?: array<int, float>, cidDefaultWidth?: float, codeSpaceRanges?: list<array{start: int, end: int, width: int}>, map?: array<string, string>, sourceToCid?: array<string, int>}|null $toUnicodeMap
     * @param array{widths?: array<int, float>}|null $fontEncoding
     * @param array{x: float, y: float, scale: float} $axis
     * @return array{0: ?float, 1: ?float}
     */
    private function advanceTextEndPointForOperand(
        ?float $currentTextEndX,
        ?float $currentTextEndY,
        string $operand,
        ?array $toUnicodeMap,
        ?array $fontEncoding,
        ?float $fontSize,
        float $characterSpacing,
        float $wordSpacing,
        float $horizontalScale,
        array $axis
    ): array {
        if ($currentTextEndX === null || $currentTextEndY === null) {
            return [$currentTextEndX, $currentTextEndY];
        }

        $fontSize ??= 12.0;
        $advance = $this->textOperandAdvanceForOperand(
            $operand,
            $toUnicodeMap,
            $fontEncoding,
            $fontSize,
            $characterSpacing,
            $wordSpacing
        );
        $scale = ($horizontalScale / 100.0) * $axis['scale'];

        return [
            $currentTextEndX + ($advance * $scale * $axis['x']),
            $currentTextEndY + ($advance * $scale * $axis['y']),
        ];
    }

    /**
     * @param array{cidWidths?: array<int, float>, cidDefaultWidth?: float, codeSpaceRanges?: list<array{start: int, end: int, width: int}>, map?: array<string, string>, sourceToCid?: array<string, int>}|null $toUnicodeMap
     * @param array{widths?: array<int, float>}|null $fontEncoding
     */
    private function textOperandAdvanceForOperand(
        string $operand,
        ?array $toUnicodeMap,
        ?array $fontEncoding,
        float $fontSize,
        float $characterSpacing,
        float $wordSpacing
    ): float {
        $operand = trim($operand);
        if (!str_starts_with($operand, '[')) {
            $decoded = $this->decodeTextOperand($operand, $toUnicodeMap, $fontEncoding);

            return $this->textOperandBaseAdvance($decoded, $operand, $toUnicodeMap, $fontEncoding, $fontSize)
                + $this->textOperandSpacingAdvance($decoded, $operand, $toUnicodeMap, $fontEncoding, $characterSpacing, $wordSpacing);
        }

        $advance = 0.0;
        foreach ($this->textArrayElements($operand) as $element) {
            if ($element['type'] === 'text') {
                $decoded = $this->decodeTextOperand($element['value'], $toUnicodeMap, $fontEncoding);
                $advance += $this->textOperandBaseAdvance($decoded, $element['value'], $toUnicodeMap, $fontEncoding, $fontSize)
                    + $this->textOperandSpacingAdvance($decoded, $element['value'], $toUnicodeMap, $fontEncoding, $characterSpacing, $wordSpacing);
                continue;
            }

            $advance -= (((float) $element['value']) / 1000.0) * $fontSize;
        }

        return $advance;
    }

    /**
     * @param array{cidWidths?: array<int, float>, cidDefaultWidth?: float, codeSpaceRanges?: list<array{start: int, end: int, width: int}>, map?: array<string, string>, sourceToCid?: array<string, int>}|null $toUnicodeMap
     * @param array{widths?: array<int, float>, base?: string, differences?: array<int, string>, suppressUnmapped?: bool}|null $fontEncoding
     */
    private function decodeTextOperandWithArraySpacing(string $operand, ?array $toUnicodeMap, ?array $fontEncoding): string
    {
        $text = '';
        foreach ($this->textOperandVisualSegments(
            $operand,
            $toUnicodeMap,
            $fontEncoding,
            12.0,
            0.0,
            0.0,
            100.0,
            ['x' => 1.0, 'y' => 0.0, 'scale' => 1.0]
        ) as $segment) {
            if ($segment['gapBefore'] !== null && $this->tjAdjustmentGapLooksLikeWordBoundary($segment['gapBefore'], 12.0)) {
                $text = rtrim($text) . ' ';
            }
            $text .= $segment['text'];
        }

        return $text;
    }

    /**
     * @param array{cidWidths?: array<int, float>, cidDefaultWidth?: float, codeSpaceRanges?: list<array{start: int, end: int, width: int}>, map?: array<string, string>, sourceToCid?: array<string, int>}|null $toUnicodeMap
     * @param array{widths?: array<int, float>, base?: string, differences?: array<int, string>, suppressUnmapped?: bool}|null $fontEncoding
     * @param array{x: float, y: float, scale: float} $axis
     * @return list<array{text: string, startOffset: float, endOffset: float, gapBefore: ?float}>
     */
    private function textOperandVisualSegments(
        string $operand,
        ?array $toUnicodeMap,
        ?array $fontEncoding,
        ?float $fontSize,
        float $characterSpacing,
        float $wordSpacing,
        float $horizontalScale,
        array $axis
    ): array {
        $operand = trim($operand);
        $resolvedFontSize = $fontSize ?? 12.0;
        $scale = ($horizontalScale / 100.0) * $axis['scale'];

        if (!str_starts_with($operand, '[')) {
            $decoded = $this->decodeTextOperand($operand, $toUnicodeMap, $fontEncoding);
            if ($decoded === '') {
                return [];
            }
            $advance = $this->textOperandAdvanceForOperand($operand, $toUnicodeMap, $fontEncoding, $resolvedFontSize, $characterSpacing, $wordSpacing);

            return [[
                'text' => $decoded,
                'startOffset' => 0.0,
                'endOffset' => $advance * $scale,
                'gapBefore' => null,
            ]];
        }

        $segments = [];
        $advance = 0.0;
        $pendingGap = null;
        foreach ($this->textArrayElements($operand) as $element) {
            if ($element['type'] === 'text') {
                $elementOperand = (string) $element['value'];
                $decoded = $this->decodeTextOperand($elementOperand, $toUnicodeMap, $fontEncoding);
                $elementAdvance = $this->textOperandBaseAdvance($decoded, $elementOperand, $toUnicodeMap, $fontEncoding, $resolvedFontSize)
                    + $this->textOperandSpacingAdvance($decoded, $elementOperand, $toUnicodeMap, $fontEncoding, $characterSpacing, $wordSpacing);
                if ($decoded !== '') {
                    $segments[] = [
                        'text' => $decoded,
                        'startOffset' => $advance * $scale,
                        'endOffset' => ($advance + $elementAdvance) * $scale,
                        'gapBefore' => $pendingGap,
                    ];
                    $pendingGap = null;
                }
                $advance += $elementAdvance;
                continue;
            }

            $adjustmentAdvance = -(((float) $element['value']) / 1000.0) * $resolvedFontSize;
            $advance += $adjustmentAdvance;
            $visualGap = $adjustmentAdvance * $scale;
            if ($visualGap > 0.0 && $visualGap < $resolvedFontSize * 4.0) {
                $pendingGap = max($pendingGap ?? 0.0, $visualGap);
            }
        }

        return $segments;
    }

    private function tjAdjustmentGapLooksLikeWordBoundary(?float $gap, ?float $fontSize): bool
    {
        if ($gap === null) {
            return false;
        }

        return $gap >= max(1.5, ($fontSize ?? 12.0) * 0.22);
    }

    /**
     * @param array{cidWidths?: array<int, float>, cidDefaultWidth?: float, codeSpaceRanges?: list<array{start: int, end: int, width: int}>, map?: array<string, string>, sourceToCid?: array<string, int>}|null $toUnicodeMap
     * @param array{widths?: array<int, float>}|null $fontEncoding
     */
    private function textOperandBaseAdvance(string $decoded, ?string $operand, ?array $toUnicodeMap, ?array $fontEncoding, float $fontSize): float
    {
        $sourceCodes = $this->simpleFontSourceCodesFromOperand($operand, $fontEncoding);
        if ($sourceCodes !== []) {
            $widths = $fontEncoding['widths'] ?? [];
            $advance = 0.0;
            foreach ($sourceCodes as $sourceCode) {
                $advance += ($widths[$sourceCode] ?? 500.0) / 1000.0 * $fontSize;
            }

            return $advance;
        }

        $cidCodes = $this->cidSourceCodesFromOperand($operand, $toUnicodeMap);
        if ($cidCodes !== []) {
            $widths = $toUnicodeMap['cidWidths'] ?? [];
            $defaultWidth = (float) ($toUnicodeMap['cidDefaultWidth'] ?? 1000.0);
            $advance = 0.0;
            foreach ($cidCodes as $cid) {
                $advance += ($widths[$cid] ?? $defaultWidth) / 1000.0 * $fontSize;
            }

            return $advance;
        }

        return $this->length($decoded) * $fontSize * self::SIMPLE_TEXT_ADVANCE_RATIO;
    }

    /**
     * @param array{cidWidths?: array<int, float>, cidDefaultWidth?: float, codeSpaceRanges?: list<array{start: int, end: int, width: int}>, map?: array<string, string>, sourceToCid?: array<string, int>}|null $toUnicodeMap
     * @param array{widths?: array<int, float>}|null $fontEncoding
     */
    private function textOperandSpacingAdvance(string $decoded, ?string $operand, ?array $toUnicodeMap, ?array $fontEncoding, float $characterSpacing, float $wordSpacing): float
    {
        $sourceCodes = $this->simpleFontSourceCodesFromOperand($operand, $fontEncoding);
        if ($sourceCodes !== []) {
            $spaces = 0;
            foreach ($sourceCodes as $sourceCode) {
                if ($sourceCode === 0x20) {
                    $spaces++;
                }
            }

            return (max(0, count($sourceCodes) - 1) * $characterSpacing) + ($spaces * $wordSpacing);
        }

        $cidCodes = $this->cidSourceCodesFromOperand($operand, $toUnicodeMap);
        if ($cidCodes !== []) {
            $spaces = 0;
            foreach ($cidCodes as $cid) {
                if ($cid === 0x20) {
                    $spaces++;
                }
            }

            return (max(0, count($cidCodes) - 1) * $characterSpacing) + ($spaces * $wordSpacing);
        }

        $characters = $this->length($decoded);
        return (max(0, $characters - 1) * $characterSpacing) + (substr_count($decoded, ' ') * $wordSpacing);
    }

    /**
     * @param array{widths?: array<int, float>}|null $fontEncoding
     * @return list<int>
     */
    private function simpleFontSourceCodesFromOperand(?string $operand, ?array $fontEncoding): array
    {
        if ($operand === null || $fontEncoding === null || ($fontEncoding['widths'] ?? []) === []) {
            return [];
        }

        $bytes = $this->textOperandBytes($operand);
        if ($bytes === null || $bytes === '') {
            return [];
        }

        return array_values(array_map(static fn (int $byte): int => $byte, unpack('C*', $bytes) ?: []));
    }

    /**
     * @param array{cidWidths?: array<int, float>, cidDefaultWidth?: float, codeSpaceRanges?: list<array{start: int, end: int, width: int}>, map?: array<string, string>, sourceToCid?: array<string, int>}|null $toUnicodeMap
     * @return list<int>
     */
    private function cidSourceCodesFromOperand(?string $operand, ?array $toUnicodeMap): array
    {
        if ($operand === null || $toUnicodeMap === null) {
            return [];
        }
        if (($toUnicodeMap['cidWidths'] ?? []) === [] && !array_key_exists('cidDefaultWidth', $toUnicodeMap)) {
            return [];
        }

        $bytes = $this->textOperandBytes($operand);
        if ($bytes === null || $bytes === '') {
            return [];
        }

        $hex = bin2hex($bytes);
        $length = strlen($hex);
        $offset = 0;
        $codes = [];
        $keyLengths = array_values(array_unique(array_map('strlen', array_keys($toUnicodeMap['map'] ?? []))));
        rsort($keyLengths, SORT_NUMERIC);
        $codeSpaceRanges = $toUnicodeMap['codeSpaceRanges'] ?? [];
        $sourceToCid = $toUnicodeMap['sourceToCid'] ?? [];

        while ($offset < $length) {
            $sourceLength = $this->fallbackToUnicodeSourceLength(
                $keyLengths,
                $length - $offset,
                $codeSpaceRanges,
                $hex,
                $offset
            );
            if ($sourceLength <= 0 || $offset + $sourceLength > $length) {
                break;
            }

            $sourceHex = substr($hex, $offset, $sourceLength);
            if (strlen($sourceHex) <= 8) {
                $codes[] = $sourceToCid[$sourceHex] ?? hexdec($sourceHex);
            }
            $offset += $sourceLength;
        }

        return $codes;
    }

    private function textOperandBytes(string $operand): ?string
    {
        $operand = trim($operand);
        if (str_starts_with($operand, '<')) {
            $hex = preg_replace('/\s+/', '', trim($operand, '<>'));
            if ($hex === null || $hex === '') {
                return '';
            }
            if (strlen($hex) % 2 === 1) {
                $hex .= '0';
            }

            $bytes = hex2bin($hex);
            return $bytes === false ? null : $bytes;
        }

        if (str_starts_with($operand, '(')) {
            return $this->decodeLiteralString(substr($operand, 1, -1));
        }

        return null;
    }

    /**
     * @return list<array{type: string, value: string|float}>
     */
    private function textArrayElements(string $operand): array
    {
        $operand = trim($operand);
        $body = substr($operand, 1, -1);
        $elements = [];
        $index = 0;
        $length = strlen($body);

        while ($index < $length) {
            if (ctype_space($body[$index])) {
                $index++;
                continue;
            }

            if ($body[$index] === '(') {
                $elements[] = [
                    'type' => 'text',
                    'value' => $this->readLiteralToken($body, $index),
                ];
                continue;
            }

            if ($body[$index] === '<' && ($index + 1 >= $length || $body[$index + 1] !== '<')) {
                $elements[] = [
                    'type' => 'text',
                    'value' => $this->readHexToken($body, $index),
                ];
                continue;
            }

            $start = $index;
            while ($index < $length && !ctype_space($body[$index]) && !str_contains('[]()<>{}%', $body[$index])) {
                $index++;
            }

            if ($index === $start) {
                $index++;
                continue;
            }

            $token = substr($body, $start, $index - $start);
            $adjustment = $this->numericOperand($token);
            if ($adjustment !== null) {
                $elements[] = [
                    'type' => 'adjustment',
                    'value' => $adjustment,
                ];
            }
        }

        return $elements;
    }

    private function startsWithWhitespace(string $text): bool
    {
        return $text !== '' && ctype_space($text[0]);
    }

    private function endsWithWhitespace(string $text): bool
    {
        return $text !== '' && ctype_space(substr($text, -1));
    }

    private function numericOperand(string $operand): ?float
    {
        if (preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)$/', $operand) !== 1) {
            return null;
        }

        return (float) $operand;
    }

    /**
     * @param list<string> $operands
     * @param array<string, string> $propertyActualTexts
     * @param array<int, string> $mcidActualTexts
     * @param array<string, int> $propertyMcids
     */
    private function actualTextOperand(array $operands, array $propertyActualTexts, array $mcidActualTexts, array $propertyMcids): ?string
    {
        for ($index = count($operands) - 1; $index >= 0; $index--) {
            $operand = trim($operands[$index]);
            if (str_starts_with($operand, '<<')) {
                $actualText = $this->actualTextFromDictionary($operand);
                if ($actualText !== null) {
                    return $actualText;
                }
            }
        }

        for ($index = count($operands) - 1; $index >= 0; $index--) {
            $operand = trim($operands[$index]);
            if (!str_starts_with($operand, '/')) {
                continue;
            }
            $name = $this->decodePdfName(substr($operand, 1));
            if (array_key_exists($name, $propertyActualTexts)) {
                return $propertyActualTexts[$name];
            }
        }

        $mcid = $this->mcidOperand($operands, $propertyMcids);
        if ($mcid !== null && array_key_exists($mcid, $mcidActualTexts)) {
            return $mcidActualTexts[$mcid];
        }

        return null;
    }

    /**
     * @param list<string> $operands
     */
    private function markedContentIsArtifact(array $operands): bool
    {
        foreach ($operands as $operand) {
            $operand = trim($operand);
            if (!str_starts_with($operand, '/')) {
                continue;
            }

            return strtoupper($this->decodePdfName(substr($operand, 1))) === 'ARTIFACT';
        }

        return false;
    }

    /**
     * @param list<string> $operands
     * @param array<string, int> $propertyMcids
     */
    private function mcidOperand(array $operands, array $propertyMcids): ?int
    {
        for ($index = count($operands) - 1; $index >= 0; $index--) {
            $operand = trim($operands[$index]);
            if (str_starts_with($operand, '<<')) {
                $mcid = $this->mcidFromDictionary($operand);
                if ($mcid !== null) {
                    return $mcid;
                }
            }
        }

        for ($index = count($operands) - 1; $index >= 0; $index--) {
            $operand = trim($operands[$index]);
            if (!str_starts_with($operand, '/')) {
                continue;
            }

            $name = $this->decodePdfName(substr($operand, 1));
            if (array_key_exists($name, $propertyMcids)) {
                return $propertyMcids[$name];
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     */
    private function structElementReplacementTextFromDictionary(string $dictionary, array $objects = []): ?string
    {
        foreach (['ActualText', 'Alt', 'E'] as $key) {
            $text = $this->textStringFromDictionaryKey($dictionary, $key, $objects);
            if ($text !== null) {
                return $text;
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     */
    private function actualTextFromDictionary(string $dictionary, array $objects = []): ?string
    {
        return $this->textStringFromDictionaryKey($dictionary, 'ActualText', $objects);
    }

    /**
     * @param array<int, string> $objects
     */
    private function textStringFromDictionaryKey(string $dictionary, string $key, array $objects = []): ?string
    {
        $tokens = $this->dictionaryTokens($dictionary);
        $count = count($tokens);
        for ($index = 0; $index + 1 < $count; $index++) {
            if (!$this->isPdfNameToken($tokens[$index], $key)) {
                continue;
            }

            $objectNumber = $this->indirectObjectOperand($tokens, $index + 1);
            if ($objectNumber !== null && isset($objects[$objectNumber])) {
                $text = $this->textStringFromObjectBody($objects[$objectNumber]);
                if ($text !== null) {
                    return $text;
                }
                continue;
            }

            $text = $this->textStringFromToken($tokens[$index + 1]);
            if ($text !== null) {
                return $text;
            }
        }

        return null;
    }

    private function textStringFromObjectBody(string $objectBody): ?string
    {
        $token = trim($objectBody);
        return $this->textStringFromToken($token);
    }

    private function textStringFromToken(string $token): ?string
    {
        $token = trim($token);
        if (str_starts_with($token, '(') || (str_starts_with($token, '<') && !str_starts_with($token, '<<'))) {
            return $this->decodeActualTextToken($token);
        }

        return null;
    }

    private function decodeActualTextToken(string $token): string
    {
        $token = trim($token);
        if (str_starts_with($token, '(')) {
            $decoded = $this->decodeLiteralString(substr($token, 1, -1));
            if (str_starts_with($decoded, "\xFE\xFF") || str_starts_with($decoded, "\xFF\xFE")) {
                return $this->decodeHexString(bin2hex($decoded));
            }

            return $decoded;
        }

        if (str_starts_with($token, '<')) {
            $hex = preg_replace('/\s+/', '', trim($token, '<>'));
            if ($hex === null || $hex === '') {
                return '';
            }
            if (strlen($hex) % 2 === 1) {
                $hex .= '0';
            }

            return $this->decodeHexString($hex);
        }

        return '';
    }

    /**
     * @param list<string|null> $actualTextStack
     */
    private function insideActualText(array $actualTextStack): bool
    {
        foreach ($actualTextStack as $actualText) {
            if (is_string($actualText)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<bool> $artifactStack
     */
    private function insideArtifact(array $artifactStack): bool
    {
        foreach ($artifactStack as $isArtifact) {
            if ($isArtifact) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>, unicodeSourceEncoding?: string}|null $toUnicodeMap
     * @param array{base: string, differences: array<int, string>, suppressUnmapped?: bool}|null $fontEncoding
     */
    private function decodeTextOperand(string $operand, ?array $toUnicodeMap = null, ?array $fontEncoding = null): string
    {
        if ($toUnicodeMap === null && ($fontEncoding['suppressUnmapped'] ?? false) === true) {
            return '';
        }

        $operand = trim($operand);
        if (str_starts_with($operand, '[')) {
            $text = '';
            if (preg_match_all('/\((?:\\\\.|[^\\\\()])*\)|<[\da-fA-F\s]+>/', $operand, $parts)) {
                foreach ($parts[0] as $part) {
                    $text .= $this->decodeTextOperand($part, $toUnicodeMap, $fontEncoding);
                }
            }
            return $text;
        }
        if (str_starts_with($operand, '<')) {
            $hex = preg_replace('/\s+/', '', trim($operand, '<>'));
            if ($hex === null || $hex === '') {
                return '';
            }
            if (strlen($hex) % 2 === 1) {
                $hex .= '0';
            }
            if ($toUnicodeMap !== null) {
                return $this->decodeHexStringWithToUnicodeMap($hex, $toUnicodeMap, $fontEncoding);
            }
            return $this->decodeHexStringWithEncoding($hex, $fontEncoding);
        }

        $decoded = $this->decodeLiteralString(substr($operand, 1, -1));
        if ($toUnicodeMap !== null) {
            return $this->decodeHexStringWithToUnicodeMap(bin2hex($decoded), $toUnicodeMap, $fontEncoding);
        }
        if (str_starts_with($decoded, "\xFE\xFF") || str_starts_with($decoded, "\xFF\xFE")) {
            return $this->decodeHexString(bin2hex($decoded));
        }
        if ($fontEncoding !== null) {
            return $this->decodeSimpleEncodedBytes($decoded, $fontEncoding);
        }

        return $decoded;
    }

    /**
     * @param array{map: array<string, string>, codeSpaceRanges: list<array{start: int, end: int, width: int}>, suppressUnmapped?: bool, unicodeSourceEncoding?: string} $toUnicodeMap
     * @param array{base: string, differences: array<int, string>, suppressUnmapped?: bool}|null $fontEncoding
     */
    private function decodeHexStringWithToUnicodeMap(string $hex, array $toUnicodeMap, ?array $fontEncoding = null): string
    {
        $normalized = $this->normalizeHexKey($hex);
        if ($normalized === '') {
            return '';
        }

        $mappings = $toUnicodeMap['map'] ?? [];
        $codeSpaceRanges = $toUnicodeMap['codeSpaceRanges'] ?? [];
        $unicodeSourceEncoding = $toUnicodeMap['unicodeSourceEncoding'] ?? null;
        $keyLengths = array_values(array_unique(array_map('strlen', array_keys($mappings))));
        rsort($keyLengths, SORT_NUMERIC);
        if ($keyLengths === []) {
            if (is_string($unicodeSourceEncoding)) {
                return $this->decodeUnmappedToUnicodeSource($normalized, $fontEncoding, $unicodeSourceEncoding);
            }

            return $this->decodeHexStringWithEncoding($normalized, $fontEncoding);
        }

        $text = '';
        $offset = 0;
        $length = strlen($normalized);
        while ($offset < $length) {
            $matched = false;
            foreach ($keyLengths as $keyLength) {
                if ($keyLength <= 0 || $offset + $keyLength > $length) {
                    continue;
                }

                $key = substr($normalized, $offset, $keyLength);
                if (array_key_exists($key, $mappings)) {
                    $text .= $mappings[$key];
                    $offset += $keyLength;
                    $matched = true;
                    break;
                }
            }

            if ($matched) {
                continue;
            }

            $fallbackLength = $this->fallbackToUnicodeSourceLength($keyLengths, $length - $offset, $codeSpaceRanges, $normalized, $offset);
            if (($toUnicodeMap['suppressUnmapped'] ?? false) !== true) {
                $text .= $this->decodeUnmappedToUnicodeSource(
                    substr($normalized, $offset, $fallbackLength),
                    $fontEncoding,
                    is_string($unicodeSourceEncoding) ? $unicodeSourceEncoding : null
                );
            }
            $offset += $fallbackLength;
        }

        return $text;
    }

    /**
     * @param list<int> $keyLengths
     * @param list<array{start: int, end: int, width: int}> $codeSpaceRanges
     */
    private function fallbackToUnicodeSourceLength(
        array $keyLengths,
        int $remainingHexLength,
        array $codeSpaceRanges = [],
        ?string $normalized = null,
        int $offset = 0
    ): int {
        if ($normalized !== null && $codeSpaceRanges !== []) {
            foreach ($codeSpaceRanges as $range) {
                $width = $range['width'];
                if ($width <= 0 || $width > $remainingHexLength) {
                    continue;
                }

                $source = hexdec(substr($normalized, $offset, $width));
                if ($source >= $range['start'] && $source <= $range['end']) {
                    return $width;
                }
            }
        }

        return $this->fallbackToMappedSourceLength($keyLengths, $remainingHexLength);
    }

    /**
     * @param list<int> $keyLengths
     */
    private function fallbackToMappedSourceLength(array $keyLengths, int $remainingHexLength): int
    {
        $usableLengths = array_values(array_filter(
            $keyLengths,
            static fn (int $keyLength): bool => $keyLength > 0 && $keyLength <= $remainingHexLength
        ));
        sort($usableLengths, SORT_NUMERIC);

        return $usableLengths[0] ?? min(2, max(1, $remainingHexLength));
    }

    /**
     * @param array{base: string, differences: array<int, string>, suppressUnmapped?: bool}|null $fontEncoding
     */
    private function decodeUnmappedToUnicodeSource(string $hex, ?array $fontEncoding, ?string $unicodeSourceEncoding = null): string
    {
        if ($hex === '') {
            return '';
        }

        $decoded = $unicodeSourceEncoding !== null
            ? ($this->decodeCMapUnicodeSourceHex($hex, $unicodeSourceEncoding) ?? '')
            : ($fontEncoding === null
            ? $this->decodeCMapUnicodeHex($hex)
            : $this->decodeHexStringWithEncoding($hex, $fontEncoding));

        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $decoded) ?? $decoded;
    }

    /**
     * @param array{base: string, differences: array<int, string>, suppressUnmapped?: bool}|null $fontEncoding
     */
    private function decodeHexStringWithEncoding(string $hex, ?array $fontEncoding): string
    {
        if ($fontEncoding === null) {
            return $this->decodeHexString($hex);
        }

        $bytes = hex2bin($hex);
        if ($bytes === false) {
            return '';
        }

        $prefix = strtolower(substr($hex, 0, 4));
        if ($prefix === 'feff' || $prefix === 'fffe') {
            return $this->decodeHexString($hex);
        }

        return $this->decodeSimpleEncodedBytes($bytes, $fontEncoding);
    }

    /**
     * @param array{base: string, differences: array<int, string>, suppressUnmapped?: bool} $fontEncoding
     */
    private function decodeSimpleEncodedBytes(string $bytes, array $fontEncoding): string
    {
        $baseEncoding = (string) ($fontEncoding['base'] ?? 'WinAnsiEncoding');
        if ($baseEncoding === 'MacRomanEncoding' && ($fontEncoding['differences'] ?? []) === []) {
            return $this->decodeMacRomanBytes($bytes);
        }

        if (!in_array($baseEncoding, ['WinAnsiEncoding', 'MacRomanEncoding', 'StandardEncoding', 'SymbolEncoding', 'ZapfDingbatsEncoding', self::GLYPH_NAME_ENCODING], true)) {
            return $bytes;
        }

        $differences = $fontEncoding['differences'] ?? [];
        if ($baseEncoding === 'WinAnsiEncoding' && $differences === []) {
            $decoded = iconv('Windows-1252', 'UTF-8//IGNORE', $bytes);
            return $decoded === false ? $bytes : $decoded;
        }

        $text = '';
        foreach (unpack('C*', $bytes) ?: [] as $byte) {
            $text .= $differences[$byte] ?? $this->decodeBaseEncodedByte((int) $byte, $baseEncoding);
        }

        return $text;
    }

    private function decodeBaseEncodedByte(int $byte, string $encoding): string
    {
        return match ($encoding) {
            'MacRomanEncoding' => $this->decodeMacRomanByte($byte),
            'StandardEncoding' => $this->decodeStandardEncodingByte($byte),
            'SymbolEncoding' => $this->decodeSymbolByte($byte),
            'ZapfDingbatsEncoding' => $this->decodeZapfDingbatsByte($byte),
            self::GLYPH_NAME_ENCODING => '',
            default => $this->decodeWinAnsiByte($byte),
        };
    }

    private function decodeSymbolByte(int $byte): string
    {
        return $this->decodeNamedGlyphByte($byte, self::SYMBOL_ENCODING_GLYPHS);
    }

    private function decodeStandardEncodingByte(int $byte): string
    {
        $glyphName = $this->cffStandardEncodingGlyphName($byte);
        if ($glyphName === null) {
            return '';
        }

        return $this->glyphNameToUnicode($glyphName) ?? '';
    }

    private function decodeZapfDingbatsByte(int $byte): string
    {
        return $this->decodeNamedGlyphByte($byte, self::ZAPF_DINGBATS_ENCODING_GLYPHS);
    }

    /**
     * @param array<int, string> $glyphs
     */
    private function decodeNamedGlyphByte(int $byte, array $glyphs): string
    {
        $glyphName = $glyphs[$byte] ?? null;
        if ($glyphName === null) {
            return '';
        }

        return $this->glyphNameToUnicode($glyphName) ?? '';
    }

    private function decodeWinAnsiByte(int $byte): string
    {
        $decoded = iconv('Windows-1252', 'UTF-8//IGNORE', chr($byte));
        return $decoded === false ? chr($byte) : $decoded;
    }

    private function decodeMacRomanBytes(string $bytes): string
    {
        $text = '';
        foreach (unpack('C*', $bytes) ?: [] as $byte) {
            $text .= $this->decodeMacRomanByte((int) $byte);
        }

        return $text;
    }

    private function decodeMacRomanByte(int $byte): string
    {
        if ($byte < 0x80) {
            return chr($byte);
        }

        return self::MAC_ROMAN_HIGH_BYTES[$byte] ?? '';
    }

    private function decodeHexString(string $hex): string
    {
        $bytes = hex2bin($hex);
        if ($bytes === false) {
            return '';
        }

        $prefix = strtolower(substr($hex, 0, 4));
        if ($prefix === 'feff') {
            $decoded = iconv('UTF-16BE', 'UTF-8//IGNORE', substr($bytes, 2));
            return $decoded === false ? '' : $decoded;
        }
        if ($prefix === 'fffe') {
            $decoded = iconv('UTF-16LE', 'UTF-8//IGNORE', substr($bytes, 2));
            return $decoded === false ? '' : $decoded;
        }

        return $bytes;
    }

    private function decodeLiteralString(string $value): string
    {
        $value = preg_replace("/\\\\\r\n|\\\\\n|\\\\\r/s", '', $value) ?? $value;

        return preg_replace_callback('/\\\\([0-7]{1,3}|.)/s', static function (array $match): string {
            return match ($match[1]) {
                'n' => "\n",
                'r' => "\r",
                't' => "\t",
                'b' => "\x08",
                'f' => "\x0c",
                '(' => '(',
                ')' => ')',
                '\\' => '\\',
                default => preg_match('/^[0-7]+$/', $match[1]) === 1 ? chr(octdec($match[1])) : $match[1],
            };
        }, $value) ?? $value;
    }

    private function length(string $text): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($text, 'UTF-8');
        }

        return strlen($text);
    }
}
