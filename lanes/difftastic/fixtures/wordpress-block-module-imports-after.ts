import { registerBlockType, type BlockConfiguration } from "@wordpress/blocks";
import { __, sprintf } from "@wordpress/i18n";
import Edit from "./edit";
import save, { deprecatedSave } from "./save";

export { Edit, save, deprecatedSave };

const metadata: BlockConfiguration = {
  title: sprintf(__("%s Card", "acme"), "Editorial"),
};

registerBlockType("acme/card", {
  ...metadata,
  edit: Edit,
  save,
});
