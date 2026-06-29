# Releasing

The following steps outline the release process for both new minor versions and
patch versions.

## Ensure PHP version compatibility

Ensure that the test suite completes on supported versions of PHP.

## Transition JIRA issues and version

All issues associated with the release version should be in the "Closed" state
and have a resolution of "Fixed". Issues with other resolutions (e.g.
"Duplicate", "Works as Designed") should be removed from the release version so
that they do not appear in the release notes.

Check the corresponding "laravel-*.x" fix version to see if it contains any
issues that are resolved as "Fixed" and should be included in this release
version.

Update the version's release date and status from the
[Manage Versions](https://jira.mongodb.org/plugins/servlet/project-config/PHPLARA/versions)
page.

## Trigger the release workflow

Releases are done automatically through a GitHub Action. Visit the corresponding
[Release New Version](https://github.com/mongodb/laravel-mongodb/actions/workflows/release.yml)
workflow page to trigger a new build. Select the correct branch (e.g. `4.5`)
and trigger a new run using the "Run workflow" button. In the following prompt,
enter the version number.

The automation will then create and push the necessary commits and tag, and create
a draft release. The release is created in a draft state and can be published
once the release notes have been updated.

## Publish release notes

Use the generated release note in [this form](https://github.com/mongodb/laravel-mongodb/releases/new).

Release announcements should also be posted in the [MongoDB Product & Driver Announcements: Driver Releases](https://mongodb.com/community/forums/tags/c/announcements/driver-releases/110/php) forum and shared on Twitter.

## Mark JIRA version as released

Mark the version as released from the
[Manage Versions](https://jira.mongodb.org/plugins/servlet/project-config/PHPLARA/versions)
page.
