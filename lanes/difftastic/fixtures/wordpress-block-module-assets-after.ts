import metadata, { supports } from "./block.json";
import * as editor from "@wordpress/block-editor";
import viewScript from "./view";

export { save } from "./frontend/save";

registerBlockType(metadata.name, {
  edit: editor.useBlockProps,
  supports,
  save,
});
