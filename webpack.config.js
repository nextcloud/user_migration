const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')

// Let webpack determine automatically where it's located
webpackConfig.output.publicPath = 'auto'

webpackConfig.entry = {
	'personal-settings': path.join(__dirname, 'src', 'personal-settings.js'),
}

module.exports = webpackConfig
