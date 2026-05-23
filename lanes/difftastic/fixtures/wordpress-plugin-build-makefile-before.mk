PLUGIN_SLUG=acme-card
CCFLAGS+=-std=c99 -DWP_PLUGIN_SLUG=\"$(PLUGIN_SLUG)\" -O2 -Wall -Werror $(CFLAGS)
ASSETS=block.json build/index.js build/style-index.css
