const Encore = require('@symfony/webpack-encore');

Encore
  // directory where compiled assets will be stored
  .setOutputPath('public/build/')
  // public path used by the web server to access the output path
  .setPublicPath('/build')
  //.setManifestKeyPrefix('build/')

  // existing entries
  .addEntry('app', './assets/app.js')
  .addEntry('plotly', './assets/plotly.js')
  .addEntry('cmdb-modeler', './assets/tools/cmdb-modeler/index.jsx')

  // 👇 NEW entry for SQL Composer
  .addEntry('sql-composer', './assets/react/sql-composer.jsx')

  // enables React support
  .enableReactPreset()

  // ✅ TypeScript/TSX support (required for ./assets/react/qw/entry.tsx)
  .enableTypeScriptLoader()

  // enables versioning (e.g. app.abc123.css)
  .enableVersioning(true)

  .enableSassLoader()


  // enables single runtime chunk (recommended for Symfony)
  .enableSingleRuntimeChunk()

  // enables source maps during development
  .enableSourceMaps(!Encore.isProduction())

  // cleans output before build
  .cleanupOutputBeforeBuild()

  // split chunks
  .splitEntryChunks()

  // other entries
  .addEntry('frw', './assets/frw/app.js')
  .addEntry('flow-studio', './assets/flow-studio.jsx')
  .addEntry('accounting', './assets/js/accounting_boot.js')

  // QW builder entry (TSX)
  .addEntry('qw-entry', './assets/react/qw/entry.tsx')
  
  .addEntry('migration-manager', './assets/react/migration-manager/index.jsx')

  .addEntry('psr', './assets/psr/index.tsx')

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
