{**
 * plugins/generic/langsciAdditionalFeatures/templates/settingsForm.tpl
 *
 * Copyright (c) 2026 Language Science Press
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * langsciAdditionalFeatures settings form (Settings -> Workflow)
 *}

<tab id="langsciAdditionalFeaturesSettings" label="{translate key="plugins.generic.langsciAdditionalFeatures.displayName"}">
	<pkp-form
		v-bind="components.{$langsciFormId}"
		@set="set"
	/>
</tab>
