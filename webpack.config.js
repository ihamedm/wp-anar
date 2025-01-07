const path = require('path');

module.exports = {
    entry: {
        admin: './assets/js/admin.js',
        public: './assets/js/public.js'
    },
    output: {
        path: path.resolve(__dirname, 'assets/dist'),
        filename: '[name].js'
    },
    module: {
        rules: [
            {
                test: /\.js$/,
                exclude: /node_modules/,
                use: {
                    loader: 'babel-loader',
                    options: {
                        presets: ['@babel/preset-env']
                    }
                }
            }
        ]
    }
};