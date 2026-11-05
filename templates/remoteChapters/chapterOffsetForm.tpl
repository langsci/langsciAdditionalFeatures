{**
 * plugins/generic/langsciAdditionalFeatures/templates/remoteChapters/chapterOffsetForm.tpl
 *
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Form to set the chapter number offset of a publication
 *}
<script type="text/javascript">
	$(function() {ldelim}
		$('#chapterOffsetForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="chapterOffsetForm" method="post" action="{url router=PKP\core\PKPApplication::ROUTE_COMPONENT component="grid.users.chapter.ChapterGridHandler" op="updateChapterOffset"}">
	{csrf}
	<input type="hidden" name="submissionId" value="{$submissionId|escape}" />
	<input type="hidden" name="publicationId" value="{$publicationId|escape}" />

	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="chapterOffsetFormNotification"}

	<p class="pkp_help">{translate key="plugins.generic.langsciAdditionalFeatures.remoteChapters.numbering.description"}</p>

	{fbvFormSection title="plugins.generic.langsciAdditionalFeatures.remoteChapters.offset.label" for="chapterNumberOffset" required=true}
		<p class="pkp_help">{translate key="plugins.generic.langsciAdditionalFeatures.remoteChapters.offset.description"}</p>
		{fbvElement type="text" id="chapterNumberOffset" name="chapterNumberOffset" value=$chapterNumberOffset required=true size=$fbvStyles.size.SMALL}
	{/fbvFormSection}

	{fbvFormSection title="plugins.generic.langsciAdditionalFeatures.remoteChapters.defaultLabel.label" for="chapterLabelDefault"}
		<p class="pkp_help">{translate key="plugins.generic.langsciAdditionalFeatures.remoteChapters.defaultLabel.description"}</p>
		{fbvElement type="text" id="chapterLabelDefault" name="chapterLabelDefault" value=$chapterLabelDefault size=$fbvStyles.size.MEDIUM}
	{/fbvFormSection}

	<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
	{fbvFormButtons submitText="common.save"}
</form>
