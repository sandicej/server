<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcModal label-id="template-field-modal__label">
		<div class="template-field-modal__content">
			<form>
				<h3 id="template-field-modal__label">
					{{ t('files', 'Fill template fields') }}
				</h3>

				<div v-for="field in fields" :key="field.index">
					<component :is="getFieldComponent(field.type)"
						v-if="fieldHasLabel(field)"
						:field="field"
						@input="trackInput" />
				</div>
			</form>
		</div>

		<div class="template-field-modal__buttons">
			<NcLoadingIcon v-if="loading" :name="t('files', 'Submitting fields …')" />
			<NcButton aria-label="Submit button"
				type="primary"
				@click="submit">
				{{ t('files', 'Submit') }}
			</NcButton>
		</div>
	</NcModal>
</template>

<script>
import { defineComponent } from 'vue'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcModal from '@nextcloud/vue/components/NcModal'
import TemplateRichTextField from './TemplateFiller/TemplateRichTextField.vue'
import TemplateCheckboxField from './TemplateFiller/TemplateCheckboxField.vue'

export default defineComponent({
	name: 'TemplateFiller',

	components: {
		NcModal,
		NcButton,
		NcLoadingIcon,
		TemplateRichTextField,
		TemplateCheckboxField,
	},

	props: {
		fields: {
			type: Array,
			default: () => [],
		},
		onSubmit: {
			type: Function,
			default: async () => {},
		},
	},

	data() {
		return {
			localFields: {},
			loading: false,
		}
	},

	methods: {
		t,
		trackInput({ index, property, value }) {
			if (!this.localFields[index]) {
				this.localFields[index] = {}
			}

			this.localFields[index][property] = value
		},
		getFieldComponent(fieldType) {
			const fieldComponentType = fieldType.split('-')
				.map((str) => {
					return str.charAt(0).toUpperCase() + str.slice(1)
				})
				.join('')

			return `Template${fieldComponentType}Field`
		},
		fieldHasLabel(field) {
			return field.name || field.alias
		},
		async submit() {
			this.loading = true

			await this.onSubmit(this.localFields)

			this.$emit('close')
		},
	},
})
</script>

<style lang="scss" scoped>
$modal-margin: calc(var(--default-grid-baseline) * 4);

.template-field-modal__content {
	padding: $modal-margin;

	h3 {
		text-align: center;
	}
}

.template-field-modal__buttons {
	display: flex;
	justify-content: flex-end;
	gap: var(--default-grid-baseline);
	margin: $modal-margin;
	margin-top: 0;
}
</style>
