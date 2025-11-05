const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');

module.exports = {
	entry: {
		main: './assets/src/js/main.jsx',
		upload: './assets/src/js/upload.jsx',
		dashboard: './assets/src/js/dashboard.jsx',
		'teknup-styles': './assets/src/css/teknup-styles.css',
	},
	output: {
		path: path.resolve(__dirname, 'assets/dist'),
		filename: '[name].js',
	},
	module: {
		rules: [
			{
				test: /\.(js|jsx)$/,
				exclude: /node_modules/,
				use: {
					loader: 'babel-loader',
					options: {
						presets: [
							'@babel/preset-env',
							['@babel/preset-react', { runtime: 'automatic' }],
						],
					},
				},
			},
			{
				test: /\.css$/,
				use: [MiniCssExtractPlugin.loader, 'css-loader'],
			},
		],
	},
	plugins: [
		new MiniCssExtractPlugin({
			filename: '[name].css',
		}),
	],
	resolve: {
		extensions: ['.js', '.jsx'],
	},
};
