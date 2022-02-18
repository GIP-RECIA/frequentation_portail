const webpack = require("webpack");
const { CleanWebpackPlugin } = require('clean-webpack-plugin');
const MiniCssExtractPlugin = require("mini-css-extract-plugin");
const TerserPlugin = require("terser-webpack-plugin");
const CssMinimizerPlugin = require("css-minimizer-webpack-plugin");
const path = require("path");
const isProduction = process.env.NODE_ENV === 'production';


let config = {
    entry: {
        main: "./src/js/index.js"
    },
    output: {
        filename: "index.js",
        path: path.resolve(__dirname, "./assets")
    },
    module: {
        rules: [
            {
                test: /\.css$/,
                use: [MiniCssExtractPlugin.loader, 'css-loader']
            }
        ]
    },
    optimization: {
      minimizer: [
        new TerserPlugin(),
        new CssMinimizerPlugin(),
      ],
    },
    plugins: [
        new CleanWebpackPlugin(),
        new webpack.ProvidePlugin({
           $: "jquery",
           jQuery: "jquery"
       }),
       new MiniCssExtractPlugin({
           filename: 'styles.css'
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