"use strict";

var path = require("path");
var models = require("./models.js");
var total = 0;

var MIME_TYPES = {
  ".css": "text/css",
  ".html": "text/html",
  ".js": "application/javascript",
  "": "text/x-markdown",
};

function createNodeModuleResource(localPath, resourcePath) {
  if (resourcePath == null) {
    resourcePath = path.relative("node_modules", localPath);
  }

  return function (cb) {
    total++;
    new models.Resource({
      path: resourcePath,
      mimeType: MIME_TYPES[path.extname(localPath)],
      bootstrapPath: localPath,
    }).save(cb);
  };
}

function createResource(resourcePath, localPath) {
  return function (cb) {
    total++;
    new models.Resource({
      path: resourcePath,
      mimeType: MIME_TYPES[path.extname(localPath)],
      bootstrapPath: localPath,
    }).save(cb);
  };
}
