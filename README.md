# Language Science Press Additional Features

A generic OMP 3.5 plugin bundling additional features. Each feature is a class
in `classes/features/` and is registered in `classes/FeatureRegistry.php`.
Features are enabled per press under Settings -> Workflow -> Submission ->
"Language Science Press Additional Features". Frontend display is left to a
(child) theme.

## Features

### reviews

Enable Reviews per press; the active theme is responsible for displaying
review links. Managers and site administrators can manage them from Reviews ->
Manage reviews in the submission's editorial workflow. The editor and public
display list all reviews for the submission, including on previews and older
publication versions. New reviews are assigned to the submission's current
publication, regardless of the version selected in the workflow; edits preserve
the existing publication association. Dates are stored as YYYYMMDD and new or
changed links must use HTTP(S). Invalid legacy dates and links can be retained
unchanged while editing other fields.

The plugin installation migration creates `langsci_review_links` if absent or
validates and adopts the existing legacy table without copying its records. For
an installed plugin, use the OMP plugin upgrade process with a package containing
`upgrade.xml` so the migration runs; merely copying files or registering a new
version does not run that upgrade. Back up the database and plugin files before
upgrading. Incompatible schemas stop the migration with an error and must be
resolved before enabling the editor. Disable the old reviewsTab plugin after
migration to avoid competing editors.

Disabling the feature or plugin does not delete reviews. The migration does
not drop the table on rollback. Before uninstalling or rolling back, retain a
database backup and the existing table; restoring an older plugin version may
require restoring compatible files and database state. Do not remove the table
if legacy or public review data must be retained.

The `reviewsBySubmission` template variable contains `link`, `linkIsValid`,
`name`, `reviewer`, `date`, `dateIsValid`, and `money_quote` fields.
`linkIsValid` is true only for valid HTTP(S) URLs. Valid legacy YYYYMMDD
dates are provided as ISO dates with `dateIsValid` set to true;
invalid dates are provided unchanged with that flag set to false.
Disable the legacy bookPage plugin when using this feature and the LangSci
child theme to avoid duplicate reviews and its book-page template override.

To run the isolated migration, CRUD, workflow and theme integration checks
(requires Node.js, PHP SQLite and the installed OMP dependencies), from the
OMP root run:

    php plugins/generic/langsciAdditionalFeatures/tests/reviewsBackend.php
    node plugins/generic/langsciAdditionalFeatures/tests/reviewsWorkflow.cjs
    php plugins/generic/langsciAdditionalFeatures/tests/reviews.php

These checks do not replace authenticated browser tests of the editor and its
authorization against the target installation.

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
