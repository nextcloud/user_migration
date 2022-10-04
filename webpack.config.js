const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')

webpackConfig.entry = {
	'personal-settings': path.join(__dirname, 'src', 'personal-settings.js'),
}

module.exports = webpackConfig
