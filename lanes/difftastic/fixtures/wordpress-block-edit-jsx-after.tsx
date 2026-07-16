export default function Edit() {
  return (
    <PanelBody title="Card settings" initialOpen={ true }>
      <TextControl label="Title" value={ attributes.title } />
    </PanelBody>
  );
}
