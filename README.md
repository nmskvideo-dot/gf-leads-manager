# Leads (GF) Manager

A WordPress plugin for managing Gravity Forms entries with an intuitive interface. Allows administrators and editors to view, search, filter, and export entries from all forms in one convenient location.

## Features

- 📊 View all Gravity Forms entries in one unified dashboard
- 🔍 Full-text search across all entry fields
- 📥 Export to CSV (all entries or selected ones)
- 📋 Detailed entry preview in modal dialog
- ☑️ Multi-select entries for bulk export
- 📄 Flexible pagination with customizable results per page
- 💬 Expandable message field with overflow detection
- 🌍 Bilingual support (English & Russian field detection)
- 👥 Access control for Administrators and Editors
- 🎨 Seamlessly integrated with WordPress Admin panel
- 📱 Responsive design for desktop and mobile

## Requirements

- WordPress 5.0+
- Gravity Forms plugin installed and activated
- PHP 7.2+

## Installation

1. Upload the plugin files to the `/wp-content/plugins/gf-leads-manager/` directory
2. Activate the plugin through the **Plugins** page in the WordPress admin panel
3. Navigate to the **Ranked Leads** menu item in the main admin menu

## Usage

After activating the plugin:

### Dashboard
- A new **Ranked Leads** menu item will appear in the admin menu
- View all Gravity Forms entries in a table format with key information
- Sort pagination by clicking page numbers or adjusting entries per page

### Search
- Use the search box to find entries across all form fields
- Works with partial text matching

### Export
- **Export All to CSV** - Export all entries (respects current search filters)
- **Export Selected** - Export only checked entries
- Downloads as UTF-8 encoded CSV file with proper formatting

### View Details
- Click the **Info** button to view complete entry details in a popup dialog
- Shows all form fields and their values

### Entry Selection
- Use checkboxes to select individual entries
- Click the header checkbox to select/deselect all visible entries

## Supported Fields

The plugin automatically detects common field names:
- **Name**: name, имя
- **Message/Text**: message, text, сообщение
- **Phone**: phone, телефон
- **Email**: email, почта

## Version

1.6

## Author

Ranked (https://ranked.net.au)

## License

GPL v2 or later. See the LICENSE file for details.
