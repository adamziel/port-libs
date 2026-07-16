"use strict";

var path = require("path");
var models = require("./models.js");
var total = 0;

function createResource(resourcePath, localPath) {
  var MIME_TYPES = {
    ".css": "text/css",
    ".html": "text/html",
    ".js": "application/javascript",
    "": "text/x-markdown",
  };

  return function (cb) {
    total++;
    new models.Resource({
      path: resourcePath,
      mimeType: MIME_TYPES[path.extname(localPath)],
      bootstrapPath: localPath,
    }).save(cb);
  };
}
