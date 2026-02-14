const Encore = require('@symfony/webpack-encore');
const MonacoWebpackPlugin = require('monaco-editor-webpack-plugin');

Encore
    // Directory where compiled assets will be stored
    .setOutputPath('public/build/')
    // Public path used by the web server to access the output path
    .setPublicPath('/build')
    // Add your JavaScript entry file
    .addEntry('app', './assets/app.js')
    .addEntry('three-model', './assets/js/three-model.js')
    .addEntry('integration-visual', './assets/js/integration-visual.js')
    .addEntry('integration-classic', './assets/js/integration-classic.js')
    .copyFiles({from: './assets/models/',
       to: 'models/[path][name].[ext]'})
    // Enable Sass/SCSS support
    .enableSassLoader()
    // Enable source maps during development
    .enableSourceMaps(!Encore.isProduction())
    .enableSingleRuntimeChunk()
    // Enable hashed filenames (e.g., app.abc123.css)
    //.enableVersioning(Encore.isProduction());
    .enableVersioning(false)
    .addPlugin(new MonacoWebpackPlugin({
        languages: ['python'] // Only load Python support to keep build size down
    }));
   

// Export the final configuration
module.exports = Encore.getWebpackConfig();

