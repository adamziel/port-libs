import metadata from "./block.json";
import * as blockEditor from "@wordpress/block-editor";
import viewScript from "./view";

export { save } from "./save";

registerBlockType(metadata.name, {
  edit: blockEditor.useBlockProps,
  save,
});
