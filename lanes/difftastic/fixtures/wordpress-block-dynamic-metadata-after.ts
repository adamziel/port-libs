const metadataPromise = import("./block.json", { with: { type: "json" } });
const viewScriptPromise = import("./view.js", { with: { type: "module" } });
const supportsPromise = import("./supports.json", { with: { type: "json" } });

export async function loadBlockAssets() {
    const metadata = await metadataPromise;
    const supports = await supportsPromise;
    const viewScript = await viewScriptPromise;

    return { metadata, supports, viewScript };
}
