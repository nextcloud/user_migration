# Nextcloud User migration

[![PHPUnit GitHub Action](https://github.com/nextcloud/user_migration/workflows/PHPUnit/badge.svg)](https://github.com/nextcloud/user_migration/actions?query=workflow%3APHPUnit)
[![Node GitHub Action](https://github.com/nextcloud/user_migration/workflows/Node/badge.svg)](https://github.com/nextcloud/user_migration/actions?query=workflow%3ANode)
[![Lint GitHub Action](https://github.com/nextcloud/user_migration/workflows/Lint/badge.svg)](https://github.com/nextcloud/user_migration/actions?query=workflow%3ALint)

**π€β‘ User migration app for Nextcloud**

This app allows users to easily migrate from one instance to another using an export of their account.

- **π± Log in to cat.example.com/nextcloud**
- **β Select what you want to export** (settings, files, profile information, profile picture, calendars, contactsβ¦)
- **β Start the export** and wait for the server to process it
- **π Download the resulting archive**
- **πΆ Open an account on dog.example.com/nextcloud**
- **π‘ Upload the archive into your files**
- **β Start the import**
- **π Enjoy your stay on your new instance** and close you old account

---

## Screenshots

### Select what to export from your old instance
![Export data selection](screenshots/export.png)

### Export in progress
![Export in progress](screenshots/exporting.png)

### Import into your new instance
![Import file selection](screenshots/import.png)
