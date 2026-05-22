import metadata, { supports } from "./block.json" with { type: "json" };
import viewScript from "./view.js" with { type: "module" };

export * from "./frontend";
export * as blockIcons from "./icons";
export type * from "./frontend/types";
