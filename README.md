# Course Transfer Plugin

## Description

The **Course Transfer Plugin** is a WordPress plugin designed to facilitate the seamless transfer of a course along with all its related data from the main site to a new site. It also ensures that the associated WooCommerce product for the course is transferred to the new site. This plugin simplifies the migration process and ensures all course-related dependencies are moved efficiently.

## Demo Video

For a quick demonstration of the plugin in action, check out this video:

[<img src="https://img.youtube.com/vi/lsiS9jD5o-A/0.jpg">](https://www.youtube.com/watch?v=lsiS9jD5o-A)

## Features

- **Course Transfer**: Transfer a course from the main site to a new site with all its data.
- **WooCommerce Product Migration**: Automatically transfers the WooCommerce product linked to the course.
- **Data Integrity**: Ensures all related data, including custom fields and metadata, are migrated successfully.
- **Easy to Use**: User-friendly interface for selecting and transferring courses.

## Installation

1. Download the plugin and upload it to your WordPress installation:
   - Upload the plugin folder to the `/wp-content/plugins/` directory.
   - Alternatively, install it directly through the WordPress Plugins menu by uploading the `.zip` file.
2. Activate the plugin through the "Plugins" menu in WordPress.

## Usage

1. Navigate to the **Course Migration** menu in the WordPress admin sidebar on the main site.
2. Select the course you want to transfer.
3. Click the **Export** button to download a `.json` file containing the course data.
4. On the new site, ensure the **Course Transfer Plugin** is installed and activated.
5. Navigate to the **Course Migration** menu in the WordPress admin sidebar on the new site.
6. Use the **Import** option to upload the `.json` file downloaded from the main site.
7. Once the import is complete:
   - The course will be created on the new site along with all its related data.
   - The associated WooCommerce product will also be imported and linked to the course.

## Requirements

- **WordPress 5.0 or higher**
- **WooCommerce** plugin
- Both the source and destination sites should have this plugin installed and active.

---

### Notes for Developers

- Ensure the new site has all required dependencies (e.g., WooCommerce, custom fields, etc.) installed and active.

---

### Happy Transferring!
