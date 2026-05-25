import view from '@blocks/card/view';
import style from '@blocks/card/style.css';
import settings from '@shared/settings';
import sharedConfig from 'shared-config';
import themeCard from '@theme/card';
import runtime from 'wordpress-runtime';
import virtualCard from '/virtual/card';
import blockRuntime from '@wordpress/block-runtime';
import packageThemeCard from '@wordpress/package-theme/card';
import packageSharedCard from '@package-shared/card';
import presetBlockView from '@preset-block/card/view';
import wpElement from 'wp-element';
import baseUrlOnlyView from 'blocks/card/view';
import legacyFallbackCard from '@legacy-fallback/card';

export { view, style, settings, sharedConfig, themeCard, runtime, virtualCard, blockRuntime, packageThemeCard, packageSharedCard, presetBlockView, wpElement, baseUrlOnlyView, legacyFallbackCard };
