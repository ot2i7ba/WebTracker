# Simple Web-Link-Tracker
Simple Web-Link-Tracker is a lightweight application to manage your bookmarks easily and efficiently. This tool is specifically adapted to my needs, originating from my [favorites](https://github.com/ot2i7ba/favorites/) project that I've been tinkering with to improve in various ways. The application is not intended to be a fully-fledged bookmark management system, but rather a bookmark scribble more or less according to the KISS [^1] principle. It's a simple application with a lot of coding quirks, designed for both functionality and as a coding playground. This repository includes several key files that enable you to set up, configure, and use the application.

# Features
This project is packed with a bunch of cool features that I've implemented mainly because I wanted to play around and learn more about these concepts. It's my little sandbox to test out some neat performance, security and coding mechanisms. Here's what I've included:

- **HTTPS Enforcement**<br/>Ensures all communication is secure by requiring HTTPS connections. If a request is made over HTTP, it's blocked.

- **Simple Caching Mechanism**<br/>Implements basic caching to improve performance by storing frequently accessed data in a cache file for a set duration.

- **Input Validation**<br/>All user inputs are validated and sanitized. Whether it's a URL, a string, or an integer, I make sure it's clean and safe before processing it.

- **Automatic Cleanup**<br/>Periodically cleans up old data and sends backup emails to ensure data integrity and availability.

- **Rate Limiting**<br/>Protects the application from being overwhelmed by limiting the number of requests per IP address per minute.

- **Intrusion Detection**<br/>Unauthorized access attempts get logged in an `intruder.json` file. This helps me keep track of any funny business and understand where my security might need tightening up.

- **Session Handling**<br/>To avoid tracking IP addresses, I create unique session IDs for users. This way, everyone's experience is isolated and secure.

- **CSRF Token**<br/>Each session gets a CSRF token to protect against Cross-Site Request Forgery attacks. It's one of those essential web security practices that I wanted to get hands-on with.

- **Secure File Access**<br/>Important files are protected via `.htaccess`, and access is only allowed through `proxy.php`, ensuring secure, controlled access within the application.

Now, let's be real here – **I'm no security expert, and neither is anyone else on this planet able to guarantee 100% security**! But hey, we can throw some hurdles in the way of those pesky intruders! That's exactly what I've tried to do with these techniques, all while having a bit of fun and learning the ropes. So, enjoy the ride, appreciate the irony, but take the security bits seriously because, at the end of the day, they're there to keep our little corner of the internet safe.

I've intentionally kept this project simple by using only basic tools: PHP, a bit of HTML, and CSS. No JavaScript, no external databases – just good old-fashioned coding. This approach makes it easier to manage and perfect for my learning purposes. Sure, it limits what I can do, but it suits my needs perfectly and keeps things straightforward. Plus, it's fun to see how much you can achieve with just the basics!

# Files Overview
- **favorites.php**<br/>The main application file that handles the storage and management of bookmarks.

- **favconfig.php**<br/>Configuration file for the main application. Here, you need to define several options including a secret value.

- **blacklist.php**<br/>Contains a list of domains from which bookmarks should not be saved.

- **proxy.php**<br/>This file allows the opening of specific files from within the application, ensuring secure access.

- **bookmarklet.txt**<br/>Contains the JavaScript code for the bookmarklet, which enables users to send bookmarks to the main application easily.

# Configuration

### favconfig.php
Before using the application, you need to configure the `favconfig.php` file. Open the file and define the following options:

```php
<?php
// Ensure this file cannot be accessed directly
if (!defined('IN_APP')) {
    header('HTTP/1.1 403 Forbidden');
    exit('Direct access not permitted');
}

return [
    // Secret Values
    'secrets' => [
        'SECRET_VALUE' => '<YOUR_SECRET_VALUE>',
    ],

[...]

    // Email Configuration
    'email' => [
        'EMAIL_ADDRESS' => filter_var('<YOUR_EMAIL>', FILTER_VALIDATE_EMAIL),
        'FROM_ADDRESS' => filter_var('<YOUR_EMAIL>', FILTER_VALIDATE_EMAIL),
    ],

[...]

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

### blacklist.php
To prevent certain domains from being bookmarked, you can add them to `blacklist.php`. Each domain should be listed like this:

```php
// Ensure this file cannot be accessed directly
if (!defined('IN_APP')) {
    header('HTTP/1.1 403 Forbidden');
    exit('Direct access not permitted');
}

// Return an array of blacklisted domains
return [
    'google.com',
    'google.de',
];
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

> [!NOTE]
> The `bookmarklet.txt` file **only contains the template** for the bookmarklet (Favelet) [^2]! Adapt the template, create a bookmark and then replace the URL of this newly created bookmark with the adapted content of the bookmarklet.txt. The bookmarklet.txt is not needed in file form, it should only help you to customize the URL for your individual WebTracker more easily!


### .htaccess
The configuration of your `.htaccess` [^3] file should always be tailored to your specific needs and the technical requirements of your environment. Below, I've provided an excerpt of a possible configuration that demonstrates how to enhance the security and functionality of an application. This setup is one of many ways to secure a web application and should be adjusted according to your specific use case.

This example configuration complements the security measures already implemented in the `favorites.php` file. You could, of course, implement all the security settings directly in the `.htaccess`, but as mentioned, I'm having fun playing in my sandbox.

```apache
# Enable HTTPS encryption
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Limit the size of requests
LimitRequestBody 102400

# Deny access to sensitive files
<FilesMatch "(favconfig.php|favorites.json|favorites.lock|blacklist.php|proxy.json|intruder.json)$">
    Require all denied
</FilesMatch>

# Protect against clickjacking
Header always set X-Frame-Options "DENY"

# Prevent MIME type sniffing
Header always set X-Content-Type-Options "nosniff"

# XSS protection
Header always set X-XSS-Protection "1; mode=block"

# Set Content Security Policy
Header always set Content-Security-Policy "default-src 'self'; img-src 'self'; script-src 'self'; style-src 'self'; font-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'"

# Set HTTP Strict Transport Security (HSTS) for the subdomain
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"

# Prevent caching by search engines
Header set Cache-Control "no-store, no-cache, must-revalidate, max-age=0"
Header set Pragma "no-cache"
Header set Expires "0"
```

This configuration helps enforce HTTPS, limit request sizes, protect sensitive files, and enhance overall security through various headers. Remember, these are just examples and may need to be adjusted to fit the specific needs and technical realities of your deployment.

# Usage
- **Add Bookmarklet to Browser**<br/>Copy the `Bookmarklet`, create a new bookmark in your browser, and paste the JavaScript code as the URL of the bookmark.

- **Bookmarking a Page**<br/>When you are on a page you want to bookmark, simply click the bookmarklet. This will send the page's URL and title to the Simple Web-Link-Tracker application.

- **Manage Bookmarks**<br/>Use the `favorites.php` application to view, edit, and manage your bookmarks.<br/>**URL**: https://<YOUR_DOMAIN>/favorites.php?secret=<YOUR_SECRET_VALUE>

- **Blacklist Management**<br/>Update `blacklist.php` to add or remove domains that should be ignored by the bookmarklet.

# Security
Ensure that your secret value is kept confidential and is not shared. This secret value is critical for the security of your bookmark management system. The `favorites.php` file can only be accessed when the correct secret is included in the URL. This measure helps protect against unauthorized access, spam, and abuse.<br/>

- **URL**: `https://<YOUR_DOMAIN>/favorites.php?secret=<YOUR_SECRET_VALUE>`

# Changes
I've made some updates to the Simple Web-Link-Tracker to enhance structure, readability, performance, security and functionality. Check out the changes below:

## 2024-07-27
1. **Improved Function Grouping**
   - Reorganized functions into logical groups to enhance readability and maintainability.

2. **Security Enhancements**
   - Ensured HTTPS connection check is performed early in the script.
   - Added CSRF token generation at the beginning for enhanced security.

3. **File Existence Check**
   - Implemented `file_exists_secure` function for checking file existence in a secure manner.
   - Used this function to conditionally display links to JSON files in the footer only if they exist.

4. **Modular Functions**
   - Separated concerns by grouping similar functions together (e.g., functions for handling favorites, email-related functions).

5. **New Functionality**
   - Added `get_oldest_link_age` function to calculate and display the age of the oldest link in the measured values section.
   - Included logic to display the maximum days to keep links in the measured values section.

6. **Code Cleanup**
   - Removed redundant or repetitive code and comments for clarity.
   - Enhanced error handling in various parts of the script.

7. **Footer and Measured Values Enhancements**
   - Improved footer logic to ensure links are displayed conditionally based on file existence.
   - Enhanced measured values section to include processing time and the age of the oldest link.

8. **Favicon Integration**

## 2024-07-26
1. **Error Handling:**
   - Added more detailed and user-friendly error messages.
   - Utilized `try-catch` blocks for better error handling, providing specific feedback to users.

2. **Security Enhancements:**
   - Implemented stricter validation and sanitization of user inputs to enhance security.
   - Continued use of PHP sessions for CSRF token management, ensuring protection against cross-site request forgery.

3. **Code Cleanup:**
   - Centralized configuration settings in `favconfig.php` for better manageability.
   - Improved code readability by using constants and variables for recurring values (e.g., cache times, file paths).
   - Modularized the code further by breaking down larger functions into smaller, more manageable ones.

4. **Performance Optimization:**
   - Enhanced the caching mechanism by externalizing cache configuration (e.g., `CACHE_TIME` and `CACHE_DIR`) to `favconfig.php`.
   - Proposed future implementation of advanced caching mechanisms (e.g., Memcached or Redis) for further performance improvements.

5. **Email Improvements:**
   - Added validation to ensure that only valid email addresses are used.
   - Prevented header injection attacks by sanitizing email headers.
   - Structured email headers and body for the `mail` function to ensure secure and consistent email sending.
   - Restricted the length of email fields to avoid potential abuse.

## 2024-07-25
1. **Security Boost with IN_APP Constant**<br/>Added the IN_APP constant to make sure certain files can’t be accessed directly. It’s defined in favorites.php and checked in favconfig.php and blacklist.php to block unauthorized access.

2. **Switched from blacklist.txt to blacklist.php**<br/>The domain blacklist is now in blacklist.php instead of blacklist.txt. This is more secure because it prevents direct access.

3. **Switched from proxy.txt to proxy.json**<br/>The log file for the proxy has been switched from proxy.txt to proxy.json to better structure the data and make it easier to manage.

___

# License
This project is licensed under the **[MIT license](https://github.com/ot2i7ba/WebTracker/blob/main/LICENSE)**, providing users with flexibility and freedom to use and modify the software according to their needs.

# Contributing
Contributions are welcome! Please fork the repository and submit a pull request for review.

# Disclaimer
This project is provided without warranties. Users are advised to review the accompanying license for more information on the terms of use and limitations of liability.

# Conclusion
I use this script to keep track of all the cool OSINT links I find during my regular hunts so I don't miss a thing. I'm no professional coder or security expert, but this app is custom-tailored for me and doubles as a practice project to (hopefully) level up my skills someday. 😉

[^1]: [Wikipedia - KISS-Prinzip](https://en.wikipedia.org/wiki/KISS_principle)
[^2]: [Wikipedia - Bookmarklet](https://en.wikipedia.org/wiki/Bookmarklet)
[^3]: [Apache - htaccess](https://httpd.apache.org/docs/2.4/howto/htaccess.html)


