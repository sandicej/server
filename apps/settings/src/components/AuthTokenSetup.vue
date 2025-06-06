<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<form id="generate-app-token-section"
		@submit.prevent="submit">
		<!-- Port to TextField component when available -->
		<NcTextField :value.sync="deviceName"
			type="text"
			:maxlength="120"
			:disabled="loading"
			class="app-name-text-field"
			:label="t('settings', 'App name')"
			:placeholder="t('settings', 'App name')" />
		<NcButton type="primary"
			:disabled="loading || deviceName.length === 0"
			native-type="submit">
			{{ t('settings', 'Create new app password') }}
		</NcButton>

		<AuthTokenSetupDialog :token="newToken" @close="newToken = null" />
	</form>
</template>

<script lang="ts">
import { showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import { defineComponent } from 'vue'
import { useAuthTokenStore, type ITokenResponse } from '../store/authtoken'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import AuthTokenSetupDialog from './AuthTokenSetupDialog.vue'
import logger from '../logger'

export default defineComponent({
	name: 'AuthTokenSetup',
	components: {
		NcButton,
		NcTextField,
		AuthTokenSetupDialog,
	},
	setup() {
		const authTokenStore = useAuthTokenStore()
		return { authTokenStore }
	},
	data() {
		return {
			deviceName: '',
			loading: false,
			newToken: null as ITokenResponse|null,
		}
	},
	methods: {
		t,
		reset() {
			this.loading = false
			this.deviceName = ''
			this.newToken = null
		},
		async submit() {
			try {
				this.loading = true
				this.newToken = await this.authTokenStore.addToken(this.deviceName)
			} catch (error) {
				logger.error(error as Error)
				showError(t('settings', 'Error while creating device token'))
				this.reset()
			} finally {
				this.loading = false
			}
		},
	},
})
</script>

<style lang="scss" scoped>
	#generate-app-token-section {
		display: flex;
		flex-direction: column;
		gap: 1rem;
		max-width: 400px;
		padding-top: 16px;
	}
</style>
