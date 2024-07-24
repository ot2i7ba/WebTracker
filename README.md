# Simple Web-Link-Tracker
Simple Web-Link-Tracker is a lightweight application to manage your bookmarks easily and efficiently. This tool is specifically adapted to my needs, originating from my [favorites](https://github.com/ot2i7ba/favorites/) project that I've been tinkering with to improve in various ways. It's a simple application with a lot of coding quirks, designed for both functionality and as a coding playground. This repository includes several key files that enable you to set up, configure, and use the application.

## Files Overview
- **favorites.php**: The main application file that handles the storage and management of bookmarks.
- **favconfig.php**: Configuration file for the main application. Here, you need to define several options including a secret value.
- **proxy.php**: This file allows the opening of specific files from within the application, ensuring secure access.
- **blacklist.txt**: Contains a list of domains from which bookmarks should not be saved. Each domain is specified on a new line.
- **bookmarklet.txt**: Contains the JavaScript code for the bookmarklet, which enables users to send bookmarks to the main application easily.

## Configuration

### favconfig.php
Before using the application, you need to configure the `favconfig.php` file. Open the file and define the following options:

```php
<?php
// Secret Values
'secrets' => [
    'SECRET_VALUE' => '<YOUR_SECRET_VALUE>',
],

[...]

// Email Configuration
'email' => [
    'EMAIL_ADDRESS' => '<YOUR_EMAIL>',
    'FROM_ADDRESS' => '<YOUR_EMAIL>',
],
?>
```

Replace `<YOUR_EMAIL>` and `<YOUR_SECRET_VALUE>` with a secure secret value of your choice. This value will be used to authenticate requests to the application.

### proxy.php
In the `proxy.php` file, you also need to define the same secret value and adjust the base path. Open `proxy.php` and make the following changes:

```php
<?php
// Proxy configuration

// Secret value for security
define('SECRET_VALUE', '<YOUR_SECRET_VALUE>');

// Base path configuration
$base_path = '/path/to/base/';
?>
```

Replace `<YOUR_SECRET_VALUE>` with the same value you set in `favconfig.php` and adjust the `$base_path` to your desired base path.

### blacklist.txt
To prevent certain domains from being bookmarked, you can add them to `blacklist.txt`. Each domain should be listed on a new line:

```
example.com
google.com
```

### bookmarklet.txt
The bookmarklet allows you to quickly add bookmarks to the Simple Web-Link-Tracker. You need to customize the domain and secret value in the bookmarklet.txt file. The content should look like this:

```javascript
javascript:(function() {
  var domain = '<YOUR_DOMAIN>';
  var secret = '<YOUR_SECRET_VALUE>';
  var url = encodeURIComponent(window.location.href);
  var title = encodeURIComponent(document.title);
  var bookmarklet_url = 'https://' + domain + '/favorites.php?secret=' + secret + '&url=' + url + '&title=' + title;
  window.open(bookmarklet_url, '_blank');
})();
```

Replace `<YOUR_DOMAIN>` with your actual domain and `<YOUR_SECRET_VALUE>` with the secret value you defined in `favconfig.php`.

# Usage
- **Add Bookmarklet to Browser**: Copy the `Bookmarklet`, create a new bookmark in your browser, and paste the JavaScript code as the URL of the bookmark.

- **Bookmarking a Page**: When you are on a page you want to bookmark, simply click the bookmarklet. This will send the page's URL and title to the Simple Web-Link-Tracker application.

- **Manage Bookmarks**: Use the `favorites.php` application to view, edit, and manage your bookmarks.
	- **URL**: https://<YOUR_DOMAIN>/favorites.php?secret=<YOUR_SECRET_VALUE>

- **Blacklist Management**: Update `blacklist.txt` to add or remove domains that should be ignored by the bookmarklet.

# Security
Ensure that your secret value is kept confidential and is not shared. This secret value is critical for the security of your bookmark management system. The `favorites.php` file can only be accessed when the correct secret is included in the URL. This measure helps protect against unauthorized access, spam, and abuse.
**URL**: `https://<YOUR_DOMAIN>/favorites.php?secret=<YOUR_SECRET_VALUE>`

___

# License
This project is licensed under the **[MIT license](https://github.com/ot2i7ba/WebTracker/blob/main/LICENSE)**, providing users with flexibility and freedom to use and modify the software according to their needs.

# Contributing
Contributions are welcome! Please fork the repository and submit a pull request for review.

# Disclaimer
This project is provided without warranties. Users are advised to review the accompanying license for more information on the terms of use and limitations of liability.

# Conclusion
I use this script to keep track of all the cool OSINT links I find during my regular hunts so I don't miss a thing. I'm no professional coder or security expert, but this app is custom-tailored for me and doubles as a practice project to (hopefully) level up my skills someday. 😉
