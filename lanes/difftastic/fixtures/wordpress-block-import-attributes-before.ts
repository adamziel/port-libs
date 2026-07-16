import metadata from "./block.json" assert { type: "json" };
import variations from "./variations.json" with { type: "json" };

export * as icons from "./icons";
export type * from "./types";

registerBlockType(metadata.name, {
  variations,
});
