<!--
  - @copyright 2022 Christopher Ng <chrng8@gmail.com>
  -
  - @author Christopher Ng <chrng8@gmail.com>
  -
  - @license AGPL-3.0-or-later
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
-->

<template>
	<div class="section">
		<h2>{{ t('user_migration', 'Export') }}</h2>

		<template v-if="!loading">
			<h3 class="settings-hint">
				{{ t('user_migration', 'Please select the data you want to export') }}
			</h3>

			<div class="section__grid">
				<!-- Base user data is permanently enabled -->
				<div class="section__checkbox">
					<CheckboxRadioSwitch :checked="true"
						:disabled="true">
						{{ t('user_migration', 'User information and settings') }}
					</CheckboxRadioSwitch>
					<em class="section__description">{{ t('user_migration', 'Basic user information including user ID and display name as well as your settings') }}</em>
				</div>
				<div v-for="({id, displayName, description}) in sortedMigrators"
					:key="id"
					class="section__checkbox">
					<CheckboxRadioSwitch name="migrators"
						:value="id"
						:checked.sync="selectedMigrators">
						{{ displayName }}
					</CheckboxRadioSwitch>
					<em class="section__description">{{ description }}</em>
				</div>
			</div>

			<div v-if="status.current === TYPE.EXPORT"
				class="section__status">
				<Button type="secondary"
					:aria-label="t('user_migration', 'Show export status')"
					:disabled="status.current === TYPE.IMPORT || cancellingExport"
					@click.stop.prevent="openModal">
					<template #icon>
						<InformationOutline title="" :size="20" />
					</template>
					{{ t('user_migration', 'Show status') }}
				</Button>
				<Button class="section__modal-button"
					type="secondary"
					:aria-label="t('user_migration', 'Cancel export')"
					:disabled="status.status !== STATUS.WAITING || cancellingExport"
					@click.stop.prevent="cancelExport">
					{{ t('user_migration', 'Cancel') }}
				</Button>
				<span class="settings-hint">{{ status.status === STATUS.WAITING ? t('user_migration', 'Export queued') : t('user_migration', 'Export in progress…') }}</span>
				<div v-if="cancellingExport" class="icon-loading section__loading" />
			</div>
			<div v-else class="section__status">
				<Button type="secondary"
					:aria-label="t('user_migration', 'Export your data')"
					:disabled="status.current === TYPE.IMPORT || starting"
					@click.stop.prevent="startExport">
					<template #icon>
						<PackageDown title="" :size="20" />
					</template>
					{{ t('user_migration', 'Export') }}
				</Button>
				<div v-if="starting" class="icon-loading section__loading" />
			</div>

			<Modal v-if="modalOpened"
				@close="closeModal">
				<div class="section__modal">
					<EmptyContent>
						{{ modalMessage }}
						<template #icon>
							<PackageDown decorative />
						</template>
						<template v-if="status.status === STATUS.WAITING" #desc>
							{{ notificationsEnabled ? t('user_migration', 'You will be notified when your export has completed. This may take a while.') : t('user_migration', 'This may take a while.') }}
						</template>
						<template v-else-if="status.status === STATUS.STARTED" #desc>
							{{ t('user_migration', 'Please do not use your account while exporting.') }}
						</template>
					</EmptyContent>
					<div v-if="status.status === STATUS.WAITING || status.status === STATUS.STARTED"
						class="section__icon icon-loading" />
					<template v-else>
						<CheckCircleOutline class="section__icon"
							title=""
							:size="40" />
						<Button class="section__modal-button"
							type="secondary"
							:aria-label="t('user_migration', 'Close export status')"
							@click.stop.prevent="closeModal">
							{{ t('user_migration', 'Close') }}
						</Button>
					</template>
				</div>
			</Modal>
		</template>
		<div v-else class="icon-loading" />
	</div>
</template>

<script>
import { showError } from '@nextcloud/dialogs'

import Button from '@nextcloud/vue/dist/Components/Button'
import CheckboxRadioSwitch from '@nextcloud/vue/dist/Components/CheckboxRadioSwitch'
import CheckCircleOutline from 'vue-material-design-icons/CheckCircleOutline'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'
import InformationOutline from 'vue-material-design-icons/InformationOutline'
import Modal from '@nextcloud/vue/dist/Components/Modal'
import PackageDown from 'vue-material-design-icons/PackageDown'

import { queueExportJob, cancelJob } from '../services/migrationService.js'
import { PENDING, STATUS, TYPE } from '../shared/constants.js'

export default {
	name: 'ExportSection',

	components: {
		Button,
		CheckboxRadioSwitch,
		CheckCircleOutline,
		EmptyContent,
		InformationOutline,
		Modal,
		PackageDown,
	},

	props: {
		loading: {
			type: Boolean,
			default: true,
		},
		migrators: {
			type: Array,
			default: () => [],
		},
		notificationsEnabled: {
			type: Boolean,
			default: false,
		},
		pending: {
			type: Object,
			default: () => ({}),
		},
		status: {
			type: Object,
			default: () => ({}),
		},
	},

	data() {
		return {
			modalOpened: false,
			selectedMigrators: [],
			TYPE,
			STATUS,
		}
	},

	computed: {
		sortedMigrators() {
			// TODO do this in better way if needed
			const sortOrder = ['files', 'trashbin', 'account', 'calendar', 'contacts']
			return [...this.migrators].sort((a, b) => sortOrder.indexOf(a.id) - sortOrder.indexOf(b.id))
		},

		starting() {
			return this.pending.current === TYPE.EXPORT && this.pending.type === PENDING.STARTING
		},

		modalMessage() {
			if (this.status.status === STATUS.WAITING) {
				return t('user_migration', 'Export queued')
			} else if (this.status.status === STATUS.STARTED) {
				return t('user_migration', 'Export in progress…')
			}
			return t('user_migration', 'Export completed successfully')
		},
	},

	watch: {
		sortedMigrators: {
			deep: true,
			immediate: true,
			handler(migrators, oldMigrators) {
				this.selectedMigrators = migrators.map(({ id }) => id)
			},
		},
	},

	methods: {
		async startExport() {
			try {
				this.$emit('update:pending', { current: TYPE.EXPORT, type: PENDING.STARTING })
				await queueExportJob(this.selectedMigrators)
				this.$emit('refresh-status', () => {
					this.openModal()
					this.$emit('update:pending', { current: null, type: null })
				})
			} catch (error) {
				this.$emit('update:pending', { current: null, type: null })
				const errorMessage = error.message || 'Unknown error'
				this.logger.error(`Error starting user export: ${errorMessage}`, { error })
				showError(errorMessage)
			}
		},

		async cancelExport() {
			try {
				this.cancellingExport = true
				await cancelJob()
				this.$emit('refresh-status', () => {
					this.cancellingExport = false
				})
			} catch (error) {
				this.cancellingExport = false
				const errorMessage = error.message || 'Unknown error'
				this.logger.error(`Error cancelling user export: ${errorMessage}`, { error })
				showError(errorMessage)
			}
		},

		openModal() {
			this.modalOpened = true
		},

		closeModal() {
			this.modalOpened = false
		},
	},
}
</script>

<style lang="scss" scoped>
.section__grid {
	display: grid;
	gap: 40px;
	grid-auto-flow: row;
	grid-template-columns: repeat(auto-fit, minmax(320px, 400px));
	margin-bottom: 40px;

	.section__description {
		display: inline-block;
		min-height: 44px;
		margin-left: 26px;
	}
}

.section__status {
	display: flex;
	gap: 0 14px;

	.section__loading {
		margin-left: 6px;
	}

	.settings-hint {
		margin: auto 0;
	}
}

.section__modal {
	margin: 80px auto 60px auto;

	&::v-deep .empty-content {
		margin-top: 0;
	}

	.section__icon {
		height: 40px;
		margin: 20px 0;
	}

	.section__modal-button {
		margin: 40px auto 0 auto;
	}
}
</style>
