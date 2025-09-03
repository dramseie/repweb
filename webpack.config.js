const Encore = require('@symfony/webpack-encore');

Encore
    // directory where compiled assets will be stored
    .setOutputPath('public/build/')
    // public path used by the web server to access the output path
    .setPublicPath('/build')
    // only needed for CDN's or sub-directory deploy
    //.setManifestKeyPrefix('build/')

    .addEntry('app', './assets/app.js')

    // existing entries
    .addEntry('plotly', './assets/plotly.js')
    .addEntry('cmdb-modeler', './assets/tools/cmdb-modeler/index.jsx')

    // 👇 NEW entry for SQL Composer
    .addEntry('sql-composer', './assets/react/sql-composer.jsx')

    // enables React support
    .enableReactPreset()

    // enables Sass/SCSS support
    //.enableSassLoader()

    // enables PostCSS support
    //.enablePostCssLoader()

    // enables versioning (e.g. app.abc123.css)
    .enableVersioning(true)

    // enables single runtime chunk (recommended for Symfony)
    .enableSingleRuntimeChunk()

    // enables source maps during development
    .enableSourceMaps(!Encore.isProduction())

    // enables integrity hashes for CDN use
    //.enableIntegrityHashes()

    // enables hashed filenames (e.g. app.abc123.css)
    //.enableVersioning(Encore.isProduction())

    // cleans output before build
    .cleanupOutputBeforeBuild()
;

module.exports = Encore.getWebpackConfig();
