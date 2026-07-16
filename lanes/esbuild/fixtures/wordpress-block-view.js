import domReady from '@wordpress/dom-ready';
import blockMeta from './block.json' with { type: 'json' };

const cardStylesheet = new URL('./view.css', import.meta.url);
const cardWorker = new URL('./card-worker.js', import.meta.url);
const visibleItems = 0b1010;
const animationStep = .5e+1;

domReady(() => {
  document.querySelectorAll('.wp-block-port-libs-card').forEach((card, index) => {
    if (index < visibleItems) {
      card.style.setProperty('--port-libs-step', animationStep + index);
      card.dataset.blockName = blockMeta.name;
      card.dataset.assetStylesheet = cardStylesheet.href;
      card.dataset.assetWorker = cardWorker.href;
    }
  });
});
