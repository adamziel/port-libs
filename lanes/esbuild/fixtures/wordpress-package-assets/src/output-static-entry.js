import './local-preview.js';
import { preview } from './local-preview.js';
import './block.css';
import metadata from './block.json' with { type: 'json' };

export const blockName = metadata.name;
export const previewName = preview;
