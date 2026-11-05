{**
 * plugins/generic/langsciAdditionalFeatures/templates/remoteChapters/remoteChapters.tpl
 *
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Remote chapter galleys, appended to the book page via Templates::Catalog::Book::Main
 *
 * @uses $remoteChapters array Chapters that have a remote URL
 *}

<style>
.remoteChapterGalleyEntry {
	display: grid;
	grid-template-columns: minmax(0, 1fr) 30%;
	align-items: start;
	gap: 0.5em;
	margin-top: 0.5rem;
	margin-bottom: 0rem;
}
.remoteChapterGalleyEntry-title {
	min-width: 0;
	overflow-wrap: anywhere;
}
.remoteChapterGalleyEntry-files {
	display: flex;
	flex-direction: row;
	gap: 0.5em;
}
.remoteChapterGalleyEntry-files > a {
	flex: 1 1 0;
	min-width: 0;
	text-align: center;
}
</style>
<div class="item remote_chapters">
	<ul>
		{foreach from=$remoteChapters item=remoteChapter}
			<li>
			 <div class="remoteChapterGalleyEntry">
					<div class="remoteChapterGalleyEntry-title">{$remoteChapter->getLocalizedTitle()|escape}</div>
					<div class="remoteChapterGalleyEntry-files">
						<a href="{$remoteChapter->getData('urlRemote')|escape}" class="cmp_download_link" target="_blank" rel="noopener">
							{$remoteChapter->getData('urlRemoteLabel')|escape}
						</a>
					</div>
				</div>
			</li>
		{/foreach}
	</ul>
</div>
