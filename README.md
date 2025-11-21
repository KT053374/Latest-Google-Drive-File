# Latest-Google-Drive-File
Latest Google Drive File is a back-end plugin that was developed for WordPress websites.  It may work on other hosting site, but not guaranteed.  This allows you to serve the most recent file from a Google Drive folder at a stable URL. Perfect for always showing the latest PDF, image, or other file in viewers like PDF Embedder, img, or video.

=== Latest Google Drive File ===
Contributors: Ken Thompson
Tags: google drive, pdf, media, embed, api, files, newsletter
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 5.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Serve the most recent file from a Google Drive folder at a stable URL. Perfect for always showing the latest newsletter PDF, image, or other file in viewers like PDF Embedder, img, or video.

== Description ==

Latest Google Drive File lets you define up to 10 Google Drive folders and exposes a simple endpoint that always returns the most recently modified file from each folder.

Common use cases:

* Always embed the latest PDF newsletter using PDF Embedder
* Always show the latest flyer or image from a Drive folder
* Serve the newest MP4 from a Drive folder into a video player
* Keep a "Latest XXX" download link in sync without editing pages

You configure:

* A Google Drive API key (restricted to your site and the Drive API)
* A root domain for example URL generation (optional)
* Up to 10 folder slots, each with:
  * Folder key (short identifier)
  * Drive Folder ID
  * Optional MIME type filter
  * Cache duration in minutes
  * Custom public error message

The plugin then gives you for each folder:

* An example URL that always streams the latest file
* An example PDF Embedder shortcode that uses that URL

You can drop the URL into any embed system that accepts URLs:
* PDF Embedder
* Standard HTML tags like `img`, `video`, or `a`
* Your own shortcodes or custom templates

== How it works ==

The plugin registers an endpoint on your site:

`https://your-site.com/?latest_newsletter=1&folder_key=yourkey`

When called, the plugin:

1. Resolves the folder configuration based on `folder_key` (or `folder_id` if passed directly).
2. Calls the Google Drive API (v3) using your API key.
3. Runs a query such as  
   *With MIME filter:*  
   `'FOLDER_ID' in parents and mimeType = 'application/pdf' and trashed = false`  
   *Without MIME filter:*  
   `'FOLDER_ID' in parents and trashed = false`
4. Orders results by `modifiedTime desc` and takes the first file.
5. Downloads the file bytes via `files.get?alt=media`.
6. Streams it back to the browser with correct `Content-Type` and `Content-Disposition: inline`.

A per folder cache stores the selected file ID for a configurable number of minutes to reduce API calls.

== Requirements ==

* WordPress 5.0 or higher
* PHP 7.4 or higher
* A Google Cloud project with the Google Drive API enabled
* A Google Drive API key restricted to:
  * Your site domain (HTTP referrer restriction)
  * Google Drive API only (API restriction)
* Drive folders shared as:
  * General access: `Anyone with the link`
  * Permission: `Viewer`

== Installation ==

1. Download the plugin and place it in `wp-content/plugins/latest-google-drive-file`
2. Ensure the folder structure looks like:

   `latest-google-drive-file/`  
   `├── latest-google-drive-file.php`  
   `├── includes/`  
   `│   ├── helpers.php`  
   `│   ├── admin-settings.php`  
   `│   └── serve-file.php`  
   `└── assets/`  
   `    ├── css/admin.css`  
   `    └── js/admin.js`

3. Activate the plugin from **Plugins** in the WordPress admin.

4. Go to **Settings → Latest Google Drive File**.

5. Under **Global Settings**:
   * Enter your **Google API Key** (see next section).
   * Optionally enter your **Root domain** (for example `https://alp166sc.com`) so example URLs use that domain.
   * Choose how many **Folder slots to show**.

6. Under **Google Drive Folders**, configure one or more folders:
   * Folder key  
     A short identifier like `newsletter`, `events`, `flyer`. This is used in `folder_key` in the URL.
   * Label  
     Human friendly description such as "Main Newsletter".
   * Folder ID  
     The string after `/folders/` in your Google Drive folder URL.
   * MIME type  
     Choose a specific MIME type (for example `application/pdf`) or "Any type".
   * Cache (minutes)  
     How long to cache the latest file ID for this folder.
   * Error message  
     Public message shown to visitors when no file is found or an error occurs.

7. Click **Save Changes**.

Each configured folder card will show an example URL and an example shortcode you can use.

== Setting up the Google Drive API key ==

1. Visit the Google Cloud Console and create or select a project.
2. Go to **APIs and Services → Library** and search for **Google Drive API**.
3. Enable the Google Drive API for your project.
4. Go to **APIs and Services → Credentials**.
5. Click **Create credentials → API key**.
6. Edit the new API key:
   * Under **Application restrictions**, choose **HTTP referrers (web sites)** and add your site for example:  
     `https://example.com/*`
   * Under **API restrictions**, select **Restrict key** and choose **Google Drive API**.
7. Save, then copy the key and paste it into **Google API Key** on the plugin settings page.

== Sharing your Google Drive folder ==

1. Go to Google Drive and locate the folder you will use.
2. Right click the folder and choose **Share**.
3. Under **General access**, set it to **Anyone with the link**.
4. Set the role to **Viewer**.
5. Click **Copy link**.
6. In the copied URL, find the part after `/folders/` and before any `?` query. That is your **Folder ID**.
7. Paste that value into the **Folder ID** field on the plugin settings page.

Any file placed in that folder will inherit the sharing and can be accessed by the plugin.

== Usage ==

### Basic usage with folder key

1. Configure a folder with:
   * Folder key: `newsletter`
   * MIME type: `application/pdf`
   * Folder ID: your Drive folder ID

2. After saving, the folder card shows example values like:

   *Example URL*  
   `https://example.com/?latest_newsletter=1&folder_key=newsletter`

   *Example shortcode (for PDF Embedder)*  
   `[pdf-embedder url="https://example.com/?latest_newsletter=1&folder_key=newsletter"]`

3. Insert that shortcode into a page or post.  
   PDF Embedder will fetch from the plugin endpoint. The endpoint always serves the newest PDF in that folder.

### Using different viewers

The endpoint streams the raw file bytes, so you can embed it anywhere a URL is accepted.

**Image example:**

```html
<img src="https://example.com/?latest_newsletter=1&folder_key=flyer" alt="Latest flyer">
