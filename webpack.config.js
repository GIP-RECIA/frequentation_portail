const webpack = require("webpack");
const path = require("path");
const isProduction = process.env.NODE_ENV === 'production';

let config = {
    entry: {
        main: "./src/js/index.js"
    },
    output: {
        filename: "index.js",
        path: path.resolve(__dirname, "./dist")
    },
    plugins: [
        new webpack.ProvidePlugin({
           $: "jquery",
           jQuery: "jquery"
       })
    ]
}

module.exports = () => {
    if (isProduction) {
        config.mode = "production";
    } else {
        config.mode = "development";
        config.devtool = false;
    }
    return config;
};