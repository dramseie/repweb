const Encore = require('@symfony/webpack-encore');

Encore
  // directory where compiled assets will be stored
  .setOutputPath('public/build/')
  // public path used by the web server to access the output path
  .setPublicPath('/build')
  //.setManifestKeyPrefix('build/')

  .addEntry('app', './assets/app.js')

  // existing entries
  .addEntry('plotly', './assets/plotly.js')
  .addEntry('cmdb-modeler', './assets/tools/cmdb-modeler/index.jsx')

  // 👇 NEW entry for SQL Composer
  .addEntry('sql-composer', './assets/react/sql-composer.jsx')

  // enables React support
  .enableReactPreset()

  // enables versioning (e.g. app.abc123.css)
  .enableVersioning(true)

  // enables single runtime chunk (recommended for Symfony)
  .enableSingleRuntimeChunk()

  // enables source maps during development
  .enableSourceMaps(!Encore.isProduction())

  // cleans output before build
  .cleanupOutputBeforeBuild()
;

const config = Encore.getWebpackConfig();

/**
 * Suppress rsuite toaster warning:
 *   export '__SECRET_INTERNALS_DO_NOT_USE_OR_YOU_WILL_BE_FIRED' ...
 * Webpack 5 supports `ignoreWarnings`. We match that specific module + message.
 */
const RSUITE_TOASTER_RENDER = /[\\/]node_modules[\\/]rsuite[\\/]esm[\\/]toaster[\\/]render\.js$/;

config.ignoreWarnings = (config.ignoreWarnings || []).concat([
  {
    module: RSUITE_TOASTER_RENDER,
    message: /__SECRET_INTERNALS_DO_NOT_USE_OR_YOU_WILL_BE_FIRED/,
  },
]);

module.exports = config;
