import { registerBlockType } from "@wordpress/blocks";
import { __ } from "@wordpress/i18n";
import Edit from "./edit";
import save from "./save";

export { Edit, save };

registerBlockType("acme/card", {
  title: __("Card", "acme"),
  edit: Edit,
  save,
});
