# Language Science Press Additional Features

A generic OMP 3.5 plugin bundling additional features. Each feature is a class
in `classes/features/` and is registered in `classes/FeatureRegistry.php`.
Features are enabled per press under Settings -> Workflow -> Submission ->
"Language Science Press Additional Features". Frontend display is left to a
(child) theme.

## Features

### reviews

Provides existing book review links to the book-page template. Enable the
feature per press; the active theme is responsible for displaying them.
It reads the existing `langsci_review_links` table created by the legacy
reviewsTab plugin, which must already exist and be populated or managed
separately. This feature does not create the table or provide an editing UI.
Reviews are selected by submission ID, including on previews and older
publication versions, as in the old bookPage plugin.

The `reviewsBySubmission` template variable contains `link`, `linkIsValid`,
`name`, `reviewer`, `date`, `dateIsValid`, and `money_quote` fields.
`linkIsValid` is true only for valid HTTP(S) URLs. Valid legacy YYYYMMDD
dates are provided as ISO dates with `dateIsValid` set to true;
invalid dates are provided unchanged with that flag set to false.
Disable the legacy bookPage plugin when using this feature and the LangSci
child theme to avoid duplicate reviews and its book-page template override.

To run the reviews provider and theme integration check (requires PHP SQLite
and the installed OMP dependencies), from the OMP root run:

    php plugins/generic/langsciAdditionalFeatures/tests/reviews.php

### pubState

Adds a "Publication State" select (Forthcoming, Published, Superseded) before
the prefix field of the publication's Title & Abstract form. The value is stored
as publication setting `pubState` (1, 2, 3; default 1) and is available via the
API. Replaces the old PubState plugin; stored values are compatible.

Suggested theme CSS for the labels:

    .pubState_forthcoming, .pubState_superseded {
        color: black !important;
        vertical-align: super;
        font-size: 66%;
    }

### remoteChapters

Adds remote galley URLs and labels to chapter definitions, with a configurable
chapter number offset and optional exclusion from numbering. Chapter numbers
are written permanently into eligible remote labels when a publication is
published. The existing chapter, publication, and press settings retain their
names and values.

The chapter form is an override of the OMP core template
`templates/controllers/grid/users/chapter/form/chapterForm.tpl`. Re-diff
`templates/controllers/grid/users/chapter/form/chapterForm.tpl` against the
core template on every OMP upgrade.

This feature replaces the standalone `remoteChapterGalleys` plugin; do not
enable or register the old plugin alongside this feature.

## Adding a feature

1. Extend `classes/features/Feature.php`.
2. Add the class to `FeatureRegistry::getFeatureClasses()`.
3. Add its settings label and strings to `locale/*/locale.po`.

## License

Copyright (c) 2026 Language Science Press. Distributed under the GNU GPL v3.
