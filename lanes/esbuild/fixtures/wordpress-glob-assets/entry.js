const metadata = {
  name: 'card',
  variation: 'blue',
  style: 'front',
};

const viewAsset = require(`./blocks/${metadata.name}/view.js`);
const variationAsset = import('./variations/' + metadata.variation + '.js');
const styleAsset = require('./styles/' + metadata.style + '.css');

console.log(viewAsset, variationAsset, styleAsset);
