/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
 
const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')

const buildMode = process.env.NODE_ENV
const isDev = buildMode === 'development'

// Let webpack determine automatically where it's located
webpackConfig.output.publicPath = 'auto'

webpackConfig.entry = {
	'personal-settings': path.join(__dirname, 'src', 'personal-settings.js'),
}

// Generate reuse license files if not in development mode
if (!isDev) {
	const WebpackSPDXPlugin = require('./build-js/WebpackSPDXPlugin.js')
	webpackConfig.plugins.push(new WebpackSPDXPlugin({
		override: {
			select2: 'MIT',
		},
	}))
}

module.exports = webpackConfig
