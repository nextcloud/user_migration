/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Vue from 'vue'
import { getRequestToken } from '@nextcloud/auth'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'

import logger from './shared/logger.js'
import PersonalSettings from './views/Personal/Settings.vue'

// eslint-disable-next-line camelcase, no-undef
__webpack_nonce__ = btoa(getRequestToken())

Vue.prototype.t = t
Vue.prototype.n = n
Vue.prototype.logger = logger

export default new Vue({
	el: '#personal-settings',
	render: h => h(PersonalSettings),
})
