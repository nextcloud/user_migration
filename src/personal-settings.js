/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getRequestToken } from '@nextcloud/auth'
import { translatePlural as n, translate as t } from '@nextcloud/l10n'
import Vue from 'vue'
import PersonalSettings from './views/Personal/Settings.vue'
import logger from './shared/logger.js'

__webpack_nonce__ = btoa(getRequestToken())

Vue.prototype.t = t
Vue.prototype.n = n
Vue.prototype.logger = logger

export default new Vue({
	el: '#personal-settings',
	render: (h) => h(PersonalSettings),
})
