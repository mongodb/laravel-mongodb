# Releasing

The following steps outline the release process for both new minor versions and
patch versions.

The command examples below assume that the canonical "mongodb" repository has
the remote name "mongodb". You may need to adjust these commands if you've given
the remote another name (e.g. "upstream"). The "origin" remote name was not used
as it likely refers to your personal fork.

It helps to keep your own fork in sync with the "mongodb" repository (i.e. any
branches and tags on the main repository should also exist in your fork). This
is left as an exercise to the reader.

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
[Manage Versions](https://jira.mongodb.org/plugins/servlet/project-config/PHPORM/versions)
page.

## Choose a version

This uses [semantic versioning](https://semver.org/). Do not break
backwards compatibility in a non-major release or your users will kill you.

## Create the release with GitHub Action

Go to the [Release New Version](https://github.com/mongodb/laravel-mongodb/actions/workflows/release.yml) action an click on "Run workflow".

Select the branch to release, the new version number and click "Run workflow".

The GitHub Action will create the tag and a draft release. Update and publish the release.

## Branch management

# Creating a maintenance branch and updating default branch name

When releasing a new major or minor version (e.g. 4.0.0), the default branch
should be renamed to the next version (e.g. 4.1). Renaming the default branch
using GitHub's UI ensures that all open pull request are changed to target the
new version.

Once the default branch has been renamed, create the maintenance branch for the
version to be released (e.g. 4.0):

```console
$ git checkout -b X.Y
$ git push mongodb X.Y
```

### After releasing a patch version

If this was a patch release, the maintenance branch must be merged up to the
default branch (e.g. 4.1):

```console
$ git checkout 4.1
$ git pull mongodb 4.1
$ git merge 4.0 --strategy=ours
$ git push mongodb
```

The `--strategy=ours` option ensures that all changes from the merged commits
will be ignored. This is OK because we previously ensured that the `4.1`
branch was up-to-date with all code changes in this maintenance branch before
tagging.


## Publish release notes

Use the generated release note in [this form](https://github.com/mongodb/laravel-mongodb/releases/new).

Release announcements should also be posted in the [MongoDB Product & Driver Announcements: Driver Releases](https://mongodb.com/community/forums/tags/c/announcements/driver-releases/110/php) forum and shared on Twitter.
