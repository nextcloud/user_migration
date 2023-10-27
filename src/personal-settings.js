/**
 * @copyright 2022 Christopher Ng <chrng8@gmail.com>
 *
 * @author Christopher Ng <chrng8@gmail.com>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
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
