<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitConfigValue;

return [
    'boolean::from_str_false' => static function (TestRunner $t): void {
        $t->same(false, GitConfigValue::parseBoolean('no'));
        $t->same(false, GitConfigValue::parseBoolean('off'));
        $t->same(false, GitConfigValue::parseBoolean('false'));
        $t->same(false, GitConfigValue::parseBoolean('0'));
        $t->same(false, GitConfigValue::parseBoolean(''));
    },

    'boolean::from_str_true' => static function (TestRunner $t): void {
        $t->same(true, GitConfigValue::parseBoolean('yes'));
        $t->same(true, GitConfigValue::parseBoolean('on'));
        $t->same(true, GitConfigValue::parseBoolean('true'));
        $t->same(true, GitConfigValue::parseBoolean('1'));
        $t->same(true, GitConfigValue::parseBoolean('+10'));
        $t->same(true, GitConfigValue::parseBoolean('-1'));
    },

    'boolean::ignores_case' => static function (TestRunner $t): void {
        foreach (['no', 'yes', 'on', 'off', 'true', 'false'] as $word) {
            $t->same(GitConfigValue::parseBoolean($word), GitConfigValue::parseBoolean(strtoupper($word)), $word);
        }
    },

    'boolean::from_str_err' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => GitConfigValue::parseBoolean("yesn't"));
        $t->throws(InvalidArgumentException::class, static fn () => GitConfigValue::parseBoolean('yesno'));
    },

    'integer::from_str_no_suffix' => static function (TestRunner $t): void {
        $t->same(['value' => 1, 'suffix' => null], GitConfigValue::parseInteger('1'));
        $t->same(['value' => -1, 'suffix' => null], GitConfigValue::parseInteger('-1'));
    },

    'integer::from_str_with_suffix' => static function (TestRunner $t): void {
        $t->same(['value' => 1, 'suffix' => 'k'], GitConfigValue::parseInteger('1k'));
        $t->same(['value' => 1, 'suffix' => 'm'], GitConfigValue::parseInteger('1m'));
        $t->same(['value' => 1, 'suffix' => 'g'], GitConfigValue::parseInteger('1g'));
    },

    'integer::invalid_from_str' => static function (TestRunner $t): void {
        foreach (['', '-', 'k', 'm', 'g', '123123123123123123123123', 'gg', "™️🤦‍♂️"] as $input) {
            $t->throws(InvalidArgumentException::class, static fn () => GitConfigValue::parseInteger($input));
        }
    },

    'integer::as_decimal' => static function (TestRunner $t): void {
        $decimal = static fn (string $input): ?int => GitConfigValue::parseIntegerDecimal($input);

        $t->same(12, $decimal('12'), 'works without suffix');
        $t->same(13 * 1024, $decimal('13k'), 'works with kilobyte suffix');
        $t->same(13 * 1024, $decimal('13K'), 'works with Kilobyte suffix');
        $t->same(14 * 1_048_576, $decimal('14m'), 'works with megabyte suffix');
        $t->same(14 * 1_048_576, $decimal('14M'), 'works with Megabyte suffix');
        $t->same(15 * 1_073_741_824, $decimal('15g'), 'works with gigabyte suffix');
        $t->same(15 * 1_073_741_824, $decimal('15G'), 'works with Gigabyte suffix');
        $t->same(null, $decimal(PHP_INT_MAX . 'g'), 'overflow results in None');
        $t->same(null, $decimal(PHP_INT_MIN . 'g'), 'underflow results in None');
    },

    'color::name::non_bright' => static function (TestRunner $t): void {
        $t->same('normal', GitConfigValue::parseColorName('normal'));
        $t->same('normal', GitConfigValue::parseColorName('-1'));
        $t->same('default', GitConfigValue::parseColorName('default'));
        $t->same('black', GitConfigValue::parseColorName('black'));
        $t->same('red', GitConfigValue::parseColorName('red'));
        $t->same('green', GitConfigValue::parseColorName('green'));
        $t->same('yellow', GitConfigValue::parseColorName('yellow'));
        $t->same('blue', GitConfigValue::parseColorName('blue'));
        $t->same('magenta', GitConfigValue::parseColorName('magenta'));
        $t->same('cyan', GitConfigValue::parseColorName('cyan'));
        $t->same('white', GitConfigValue::parseColorName('white'));
    },

    'color::name::bright' => static function (TestRunner $t): void {
        $t->same('brightblack', GitConfigValue::parseColorName('brightblack'));
        $t->same('brightred', GitConfigValue::parseColorName('brightred'));
        $t->same('brightgreen', GitConfigValue::parseColorName('brightgreen'));
        $t->same('brightyellow', GitConfigValue::parseColorName('brightyellow'));
        $t->same('brightblue', GitConfigValue::parseColorName('brightblue'));
        $t->same('brightmagenta', GitConfigValue::parseColorName('brightmagenta'));
        $t->same('brightcyan', GitConfigValue::parseColorName('brightcyan'));
        $t->same('brightwhite', GitConfigValue::parseColorName('brightwhite'));
    },

    'color::name::ansi' => static function (TestRunner $t): void {
        $t->same('255', GitConfigValue::parseColorName('255'));
        $t->same('0', GitConfigValue::parseColorName('0'));
    },

    'color::name::hex' => static function (TestRunner $t): void {
        $t->same('#ff0010', GitConfigValue::parseColorName('#ff0010'));
        $t->same('#ffffff', GitConfigValue::parseColorName('#ffffff'));
        $t->same('#000000', GitConfigValue::parseColorName('#000000'));
    },

    'color::name::invalid' => static function (TestRunner $t): void {
        foreach (['-2', 'brightnormal', 'brightdefault', '', 'bright', '256', '#', '#fff', '#gggggg', '#=»©='] as $input) {
            $t->throws(InvalidArgumentException::class, static fn () => GitConfigValue::parseColorName($input));
        }
    },

    'color::attribute::non_inverted' => static function (TestRunner $t): void {
        $t->same('reset', GitConfigValue::parseColorAttribute('reset'));
        $t->same('bold', GitConfigValue::parseColorAttribute('bold'));
        $t->same('dim', GitConfigValue::parseColorAttribute('dim'));
        $t->same('ul', GitConfigValue::parseColorAttribute('ul'));
        $t->same('blink', GitConfigValue::parseColorAttribute('blink'));
        $t->same('reverse', GitConfigValue::parseColorAttribute('reverse'));
        $t->same('italic', GitConfigValue::parseColorAttribute('italic'));
        $t->same('strike', GitConfigValue::parseColorAttribute('strike'));
    },

    'color::attribute::inverted_no_dash' => static function (TestRunner $t): void {
        $t->same('nobold', GitConfigValue::parseColorAttribute('nobold'));
        $t->same('nodim', GitConfigValue::parseColorAttribute('nodim'));
        $t->same('noul', GitConfigValue::parseColorAttribute('noul'));
        $t->same('noblink', GitConfigValue::parseColorAttribute('noblink'));
        $t->same('noreverse', GitConfigValue::parseColorAttribute('noreverse'));
        $t->same('noitalic', GitConfigValue::parseColorAttribute('noitalic'));
        $t->same('nostrike', GitConfigValue::parseColorAttribute('nostrike'));
    },

    'color::attribute::inverted_dashed' => static function (TestRunner $t): void {
        $t->same('nobold', GitConfigValue::parseColorAttribute('no-bold'));
        $t->same('nodim', GitConfigValue::parseColorAttribute('no-dim'));
        $t->same('noul', GitConfigValue::parseColorAttribute('no-ul'));
        $t->same('noblink', GitConfigValue::parseColorAttribute('no-blink'));
        $t->same('noreverse', GitConfigValue::parseColorAttribute('no-reverse'));
        $t->same('noitalic', GitConfigValue::parseColorAttribute('no-italic'));
        $t->same('nostrike', GitConfigValue::parseColorAttribute('no-strike'));
    },

    'color::attribute::invalid' => static function (TestRunner $t): void {
        foreach (['no-reset', 'noreset', 'a', 'no bold', '', 'no', 'no-'] as $input) {
            $t->throws(InvalidArgumentException::class, static fn () => GitConfigValue::parseColorAttribute($input));
        }
    },

    'color::from_git::reset' => static function (TestRunner $t): void {
        $t->same('reset', GitConfigValue::normalizeColor('reset'));
    },

    'color::from_git::empty' => static function (TestRunner $t): void {
        $t->same('', GitConfigValue::normalizeColor(''));
    },

    'color::from_git::at_most_two_colors' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => GitConfigValue::normalizeColor('red green blue'));
    },

    'color::from_git::attribute_before_color_name' => static function (TestRunner $t): void {
        $t->same('red bold', GitConfigValue::normalizeColor('bold red'));
    },

    'color::from_git::color_name_before_attribute' => static function (TestRunner $t): void {
        $t->same('red bold', GitConfigValue::normalizeColor('red bold'));
    },

    'color::from_git::attribute_fg_bg' => static function (TestRunner $t): void {
        $t->same('blue red ul', GitConfigValue::normalizeColor('ul blue red'));
    },

    'color::from_git::fg_bg_attribute' => static function (TestRunner $t): void {
        $t->same('blue red ul', GitConfigValue::normalizeColor('blue red ul'));
    },

    'color::from_git::multiple_attributes' => static function (TestRunner $t): void {
        $t->same('blue bold dim ul blink reverse', GitConfigValue::normalizeColor('blue bold dim ul blink reverse'));
    },

    'color::from_git::reset_then_multiple_attributes' => static function (TestRunner $t): void {
        $t->same('blue bold dim ul blink reverse reset', GitConfigValue::normalizeColor('blue bold dim ul blink reverse reset'));
    },

    'color::from_git::long_color_spec' => static function (TestRunner $t): void {
        $t->same('254 255 bold dim ul blink reverse', GitConfigValue::normalizeColor('254 255 bold dim ul blink reverse'));

        $input = '#ffffff #ffffff bold nobold dim nodim italic noitalic ul noul blink noblink reverse noreverse strike nostrike';
        $expected = '#ffffff #ffffff bold dim italic ul blink reverse strike nodim nobold noitalic noul noblink noreverse nostrike';
        $t->same($expected, GitConfigValue::normalizeColor($input));
    },

    'color::from_git::normal_default_can_clear_backgrounds' => static function (TestRunner $t): void {
        $t->same('normal default', GitConfigValue::normalizeColor('normal default'));
    },

    'color::from_git::default_can_combine_with_attributes' => static function (TestRunner $t): void {
        $t->same('default default bold noreverse', GitConfigValue::normalizeColor('default default no-reverse bold'));
    },
];
