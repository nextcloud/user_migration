# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [7.0.1] - 2024-08-22

### Changes

* Fix failed release

## [7.0.0] - 2024-08-22

### Breaking

* Drop support for Nextcloud 29

### Changes

* Nextcloud 30 compatibility
* Dependency bumps

## [6.0.0] - 2024-03-28

### Breaking

* Drop support for Nextcloud 28

### Changes

* Nextcloud 29 compatibility
* Add unit tests https://github.com/nextcloud/user_migration/pull/250
* Avoid crashes in Notifier when user or file is not found https://github.com/nextcloud/user_migration/pull/608
* Fix hidden password confirmation dialog with new global search https://github.com/nextcloud/user_migration/pull/550
* Dependency bumps

## [5.0.0] - 2023-12-01

### Breaking

* Drop support for Nextcloud 27

### Changes

* Nextcloud 28 compatibility
* Remove deprecated ${var} syntax https://github.com/nextcloud/user_migration/pull/519
* Fix hidden password confirmation dialog with new global search https://github.com/nextcloud/user_migration/pull/551
* Various dependencies bump

## [4.0.1] - 2023-05-17

### Breaking

* Drop support for Nextcloud 25 + 26

### Changes

* Nextcloud 27 compatibility
* Improved 32bit compatibility https://github.com/nextcloud/user_migration/pull/408
* CLI command exports directly to final folder instead of a temporary folder https://github.com/nextcloud/user_migration/pull/402
* Various dependencies bump

## [4.0.0] - 2023-05-16

* Nextcloud 27 compatibility
* Improved 32bit compatibility https://github.com/nextcloud/user_migration/pull/408
* CLI command exports directly to final folder instead of a temporary folder https://github.com/nextcloud/user_migration/pull/402
* Various dependencies bump

## [3.0.0] - 2023-03-03

* Use file picker with filter from dialogs package https://github.com/nextcloud/user_migration/pull/369
* Improve verbose output when an exception happens https://github.com/nextcloud/user_migration/pull/364
* Apply node filter for comments and tags as well https://github.com/nextcloud/user_migration/pull/354
* Fix type error in debug mode when an Exception is thrown https://github.com/nextcloud/user_migration/pull/353
* Disable cancel button while cancelling https://github.com/nextcloud/user_migration/pull/358
* Various dependencies bump
