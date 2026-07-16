const metadataPromise = import("./block.json", { assert: { type: "json" } });
const viewScriptPromise = import("./view.js", { with: { type: "javascript" } });

export async function loadBlockAssets() {
    const metadata = await metadataPromise;
    const viewScript = await viewScriptPromise;

    return { metadata, viewScript };
}
