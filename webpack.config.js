const path = require('path');

module.exports = (env, argv) => {
    const isProduction = argv.mode === 'production';
    
    return {
        entry: {
            admin: './assets/js/admin.js',
            'status-tools': './assets/js/status-tools.js',
            notifications: './assets/js/notifications.js',
            public: './assets/js/public.js',
            'import-wizard': './assets/js/import/import.js'
        },
        output: {
            path: path.resolve(__dirname, 'assets/dist'),
            filename: '[name].min.js'
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
        },
        externals: {
            // jQuery is provided by WordPress
            'jquery': 'jQuery'
        },
        plugins: isProduction ? [
            // Remove console logs in production
            new (require('webpack')).DefinePlugin({
                'process.env.NODE_ENV': JSON.stringify('production')
            })
        ] : [],
        optimization: isProduction ? {
            minimize: true,
            minimizer: [
                new (require('terser-webpack-plugin'))({
                    terserOptions: {
                        compress: {
                            drop_console: true, // Remove console.log statements
                            drop_debugger: true, // Remove debugger statements
                        }
                    }
                })
            ]
        } : {}
    };
};