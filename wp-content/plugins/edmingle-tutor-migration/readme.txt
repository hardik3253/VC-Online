=== Edmingle to Tutor LMS Migration ===
Contributors: hardik3253
Donate link: https://hardikmprajapati.com/
Tags: edmingle, tutor-lms, migration, lms, course-migration
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A comprehensive migration tool to migrate students, courses, batches, curriculum, and enrollments from Edmingle to Tutor LMS.

== Description ==

Edmingle to Tutor LMS Migration allows seamless transfer of your learning management data from Edmingle into WordPress powered by Tutor LMS.

= Key Features =
* Seamless API authentication and institution setup wizard.
* Data Explorer to fetch and cache Students, Courses, Batches, Curriculum, Materials, and Certificates.
* Robust migration engine to import Students as WordPress/Tutor users, Courses into Tutor LMS, and enrollments.
* Detailed migration logs and error reporting.
* Google Sheets synchronization integration.

== Installation ==

1. Upload the `edmingle-tutor-migration` folder to the `/wp-content/plugins/` directory, or install the ZIP through the WordPress Plugins menu.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Make sure Tutor LMS is installed and activated.
4. Navigate to **Edmingle Migration** in the WordPress admin menu to configure your API credentials and begin migration.

== Frequently Asked Questions ==

= Do I need Tutor LMS installed? =
Yes, Tutor LMS (Free or Pro) must be installed and active before running the migration.

= Where do I find my Edmingle API credentials? =
Log in to your Edmingle admin portal, navigate to Settings > API & Integrations, and retrieve your Base URL, Admin Email, and API credentials.

== Changelog ==

= 1.0.0 =
* Initial release with setup wizard, data explorer, migration engine, and log viewer.

== Screenshots ==

1. Setup Wizard: Connect and authenticate with the Edmingle API.
2. Data Explorer: Staging and caching Edmingle entities locally.
3. Migration Engine: Migrate Students, Courses, and Enrollments into Tutor LMS.
4. API Explorer: Test and inspect Edmingle endpoints.
5. Logs & Debug Mode: Track requests, execution times, and batch history.

== Upgrade Notice ==

= 1.0.0 =
Initial release for Edmingle to Tutor LMS Migration.


