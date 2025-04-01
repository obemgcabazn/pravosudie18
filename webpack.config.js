const path = require('path');
const miniCss = require('mini-css-extract-plugin');
const webpack = require('webpack');

module.exports = (env) => {
    return {
        mode: (env.mode) ? 'development' : 'production',
        entry: './src/index.js',
        output: {
            path: path.resolve(__dirname, 'public/wp-content/themes/template/js'),
            filename: 'scripts.js'
        },
        module: {
            rules: [
                {
                    test: /\.(sass|scss|css)$/,
                    use: [
                        miniCss.loader,
                        {
                            loader: 'css-loader',
                            options: {url: false}
                        },
                        "group-css-media-queries-loader",
                        'sass-loader'
                    ]
                }
            ]
        },
        plugins: [
            new miniCss({
                filename: '../css/styles.css',
            }),
            new webpack.DefinePlugin({
                __VUE_OPTIONS_API__: 'true',
                __VUE_PROD_DEVTOOLS__: 'false',
                __VUE_PROD_HYDRATION_MISMATCH_DETAILS__: 'false'
            }),
            new webpack.ProvidePlugin({
                $: 'jquery',
                jQuery: 'jquery',
            }),
        ]
    }
};