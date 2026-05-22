interface BlockEditProps {
  clientId: string;
  context: "edit";
  attributes: {
    title: string;
    mediaId: number;
    ctaText: string;
  };
}
