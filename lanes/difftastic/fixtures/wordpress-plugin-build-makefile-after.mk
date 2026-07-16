PLUGIN_SLUG=acme-card
CCFLAGS+=-std=c99 -DWP_PLUGIN_SLUG=\"$(PLUGIN_SLUG)\" -O2 -Wall -D_FORTIFY_SOURCE=2 $(CFLAGS)
ASSETS=block.json build/index.js build/view.js build/style-index.css
