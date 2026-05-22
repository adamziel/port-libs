import metadata, { supports } from "./block.json" with { type: "json" };
import variations from "./variations.json" with { type: "json" };

export * as blockIcons from "./icons";
export type * from "./frontend/types";

registerBlockType(metadata.name, {
  supports,
  variations,
});
