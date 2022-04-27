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

			<div v-if="status.current !== 'export'"
				class="section__status">
				<Button type="secondary"
					:aria-label="t('user_migration', 'Export your data')"
					:disabled="status.current === 'import' || startingExport"
					@click.stop.prevent="startExport">
					<template #icon>
						<PackageDown title="" :size="20" />
					</template>
					{{ t('user_migration', 'Export') }}
				</Button>
				<div v-if="startingExport" class="icon-loading" />
			</div>
			<div v-else class="section__status">
				<Button type="secondary"
					:aria-label="t('user_migration', 'Show export status')"
					:disabled="status.current === 'import'"
					@click.stop.prevent="openModal">
					{{ t('user_migration', 'Show status') }}
				</Button>
				<span class="settings-hint">{{ status.status === 'waiting' ? t('user_migration', 'Export queued') : t('user_migration', 'Export in progress…') }}</span>
			</div>

			<Modal v-if="modalOpened"
				@close="closeModal">
				<div class="section__modal">
					<EmptyContent>
						{{ modalMessage }}
						<template #icon>
							<PackageDown decorative />
						</template>
						<template v-if="status.status === 'waiting'" #desc>
							{{ notificationsEnabled ? t('user_migration', 'You will be notified when your export has completed, this may take a while.') : t('user_migration', 'This may take a while.') }}
						</template>
						<template v-else-if="status.status === 'started'" #desc>
							{{ t('user_migration', 'Please do not use your account while exporting.') }}
						</template>
					</EmptyContent>
					<div v-if="status.status === 'waiting' || status.status === 'started'"
						class="section__icon icon-loading" />
					<template v-else>
						<CheckCircleOutline class="section__icon"
							title=""
							:size="40" />
						<Button class="section__close"
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
import Modal from '@nextcloud/vue/dist/Components/Modal'
import PackageDown from 'vue-material-design-icons/PackageDown'

import { queueExport } from '../services/migrationService'

export default {
	name: 'ExportSection',

	components: {
		Button,
		CheckboxRadioSwitch,
		CheckCircleOutline,
		EmptyContent,
		Modal,
		PackageDown,
	},

	props: {
		notificationsEnabled: {
			type: Boolean,
			default: false,
		},
		loading: {
			type: Boolean,
			default: true,
		},
		migrators: {
			type: Array,
			default: () => [],
		},
		status: {
			type: Object,
			default: () => ({}),
		},
	},

	data() {
		return {
			modalOpened: false,
			startingExport: false,
			selectedMigrators: [],
		}
	},

	computed: {
		sortedMigrators() {
			// TODO do this in better way if needed
			const sortOrder = ['files', 'trashbin', 'account', 'calendar', 'contacts']
			return [...this.migrators].sort((a, b) => sortOrder.indexOf(a.id) - sortOrder.indexOf(b.id))
		},

		modalMessage() {
			if (this.status.status === 'waiting') {
				return t('user_migration', 'Export queued')
			} else if (this.status.status === 'started') {
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
				this.startingExport = true
				await queueExport(this.selectedMigrators)
				this.$emit('refresh-status', () => {
					this.openModal()
					this.startingExport = false
				})
			} catch (error) {
				this.startingExport = false
				const errorMessage = error.message || 'Unknown error'
				this.logger.error(`Error starting user export: ${errorMessage}`, { error })
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
	gap: 0 20px;

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

	.section__close {
		margin: 40px auto 0 auto;
	}
}
</style>
