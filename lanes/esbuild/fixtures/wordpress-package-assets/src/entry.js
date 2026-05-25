import { store } from '@wordpress/interactivity';
import helper from 'port-libs-card-runtime/helper';
import fixtureSelf from 'wordpress-package-assets-fixture';
import selfExport from 'wordpress-package-assets-fixture/self-export';
import exportedRuntime from 'exports-map-pkg';
import featureRuntime from 'exports-map-pkg/features/card';
import conditionalArrayRuntime from 'conditional-array-pkg';
import conditionalCustomRuntime from 'conditional-array-pkg/custom';
import browserMappedRuntime from 'browser-map-pkg';
import browserMappedFeature from 'browser-map-pkg/feature';
import containingMappedRuntime from 'containing-browser-map-pkg';
import internalView from '#view';
import conditionalRuntime from '#conditional';
import internalBlock from '#/blocks/card';
import importedRuntime from '#pkg-runtime';

const runtime = require('port-libs-card-runtime');
const serverRuntime = require('server-only-package');
const fallback = require('bad-main-pkg');
const previewRuntime = require('exports-map-pkg/preview');
const legacyTool = require('exports-map-pkg/legacy/admin');
const requirePreview = require('#require-preview');

export { store, helper, fixtureSelf, selfExport, exportedRuntime, featureRuntime, conditionalArrayRuntime, conditionalCustomRuntime, browserMappedRuntime, browserMappedFeature, containingMappedRuntime, internalView, conditionalRuntime, internalBlock, importedRuntime, runtime, serverRuntime, fallback, previewRuntime, legacyTool, requirePreview };
