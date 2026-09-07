# Edmingle to Tutor LMS Migration

[![WordPress Plugin Check](https://img.shields.io/badge/Plugin%20Check-Passed-success)](https://wordpress.org/plugins/edmingle-tutor-migration)
[![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

A production-ready WordPress plugin for migrating students, courses, batches, curriculum, and enrollments from Edmingle to Tutor LMS.

## Features

- **Setup Wizard & API Onboarding**: Secure API token generation, AES-256-CBC password encryption, and multi-step connection verification.
- **Data Explorer & Staging**: Step-by-step local staging of Students, Courses, Batches, Curriculum, Materials, and Certificates with resumption support.
- **Robust Migration Engine**:
  - Automatically imports students into WordPress/Tutor users while preserving custom registration dates.
  - Automatically maps and matches Courses, Topics, and Lessons into Tutor LMS.
  - Automatically imports and transitions Enrollments into Tutor LMS (`tutor_enrolled`).
- **Google Sheets Synchronization**: Asynchronously syncs student registrations and Tutor LMS course enrollments to a connected Google Sheet via Google Apps Script Webhook.
- **Developer API Explorer**: Built-in interactive request builder to debug and inspect Edmingle API endpoints.
- **Logs & Diagnostics**: Detailed logging of request times, status codes, and batch progress.

## Requirements

- **WordPress**: 6.0 or higher (Tested up to 7.1)
- **PHP**: 8.0 or higher
- **Tutor LMS**: Free or Pro active on WordPress

## Directory Structure

```text
edmingle-tutor-migration/
├── admin/                     # Admin controllers, AJAX handlers, and views
│   ├── views/                 # UI Templates (Dashboard, Settings, Data Explorer, Migration, etc.)
│   ├── Admin.php              # Menu and asset enqueueing
│   ├── Data_Explorer.php      # Edmingle data staging handlers
│   ├── Migration_Engine.php   # Tutor LMS migration handlers
│   └── Setup_Wizard.php       # Setup & connection wizard
├── assets/                    # CSS and JavaScript assets
│   ├── css/
│   └── js/
├── includes/                  # Core orchestration and services
│   ├── ApiClient.php          # Interactive API client
│   ├── Auth.php               # Token and encrypted credential service
│   ├── Autoloader.php         # PSR-4 Autoloader
│   ├── ETM_Database.php       # Custom staging tables manager
│   ├── Edmingle_API.php       # Core API integration service
│   ├── Google_Sheet_Sync.php  # Webhook synchronization service
│   ├── Logger.php             # Debug logger
│   └── Plugin.php             # Plugin initialization and hooks
├── languages/                 # Translation files
├── .gitattributes             # Release export filter
├── .gitignore                 # Git ignore rules
├── build.sh                   # Automated production ZIP packaging script
├── edmingle-tutor-migration.php # Plugin bootstrap
├── readme.txt                 # WordPress.org standard metadata
└── uninstall.php              # Cleanup on plugin deletion
```

## Release Packaging

To build a clean production-ready ZIP package ready for deployment or WordPress.org:

```bash
./build.sh
```

The output package will be generated inside `dist/edmingle-tutor-migration.zip`.

## License

This plugin is licensed under the [GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html).
