<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Simple Web-Link-Tracker</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self';">
    <meta http-equiv="Referrer-Policy" content="no-referrer">
    <meta http-equiv="Cache-Control" content="no-cache, must-revalidate, no-store">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta http-equiv="Strict-Transport-Security" content="max-age=31536000; includeSubDomains; preload">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="X-XSS-Protection" content="1; mode=block">
    <!-- Styles -->
    <link rel="stylesheet" href="assets/css/styles.css">
</head>

<body>
    <div class="container">
        <?php
            /**
             * Simple Bookmarklet Web-Link-Tracker
             * Script to store URLs submitted via bookmarklet
             *
             * @copyright (c) 2023 ot2i7ba
             * https://github.com/ot2i7ba/
             * @license MIT License
             */

            session_start();
            $start = microtime(true);

            // Define a constant to ensure scripts are not accessed directly
            define('IN_APP', true);

            // Create a session ID to avoid IP tracking
            if (!isset($_SESSION['session_id'])) {
                $_SESSION['session_id'] = bin2hex(random_bytes(16));
            }
            $session_id = $_SESSION['session_id'];

            // Generate a CSRF token
            if (empty($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }

            // Ensure HTTPS connection
            if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
                show_message("Secure connection required.", true);
                exit;
            }

            // Load configuration
            $config = include('favconfig.php');

            // Define constants from config
            define('SECRET_VALUE', $config['secrets']['SECRET_VALUE']);
            define('MAX_LINKS_PER_PAGE', $config['limits']['MAX_LINKS_PER_PAGE']);
            define('MAX_TITLE_LENGTH', $config['limits']['MAX_TITLE_LENGTH']);
            define('MAX_DAYS_TO_KEEP', $config['limits']['MAX_DAYS_TO_KEEP']);
            define('EMAIL_ADDRESS', $config['email']['EMAIL_ADDRESS']);
            define('FROM_ADDRESS', $config['email']['FROM_ADDRESS']);
            define('DAYS_BEFORE_DELETION', $config['limits']['DAYS_BEFORE_DELETION']);
            define('MAX_REQUESTS_PER_MINUTE', $config['limits']['MAX_REQUESTS_PER_MINUTE']);
            define('CACHE_TIME', $config['cache']['CACHE_TIME']);
            define('CACHE_DIR', $config['cache']['CACHE_DIR']);

            // Path to the JSON file and lock file
            $file = $config['paths']['favorites'];
            $lockFile = $config['paths']['lock'];
            $intruder = $config['paths']['intruder'];
            $blacklist = $config['paths']['blacklist'];

            // Ensure the cache directory exists
            if (!is_dir(CACHE_DIR)) {
                mkdir(CACHE_DIR, 0700, true);
            }

            // Function to validate and sanitize input
            function validate_input($input, $type) {
                switch ($type) {
                    case 'url':
                        return filter_var($input, FILTER_VALIDATE_URL) ? $input : false;
                    case 'string':
                        return htmlspecialchars(filter_var($input, FILTER_SANITIZE_STRING), ENT_QUOTES, 'UTF-8');
                    case 'int':
                        return filter_var($input, FILTER_VALIDATE_INT);
                    default:
                        return false;
                }
            }

            // Function to create a file if it does not exist
            function create_file_if_not_exists($filename, $default_content = '') {
                if (!file_exists($filename)) {
                    file_put_contents($filename, $default_content);
                    chmod($filename, 0600);
                }
            }

            create_file_if_not_exists($file, json_encode([]));
            create_file_if_not_exists($lockFile);
            create_file_if_not_exists($intruder, json_encode([]));

            // Function to read the blacklist
            function read_blacklist($blacklist) {
                if (!defined('IN_APP')) {
                    header('HTTP/1.1 403 Forbidden');
                    exit('Direct access not permitted');
                }
                return include($blacklist);
            }

            // Function to read the favorites from the JSON file with caching
            function read_favorites($file, $reverse = true) {
                global $cache_file;
                $cache_file = CACHE_DIR . '/cache_' . md5($file) . '.json';
                if (file_exists($cache_file) && (time() - filemtime($cache_file) < CACHE_TIME)) {
                    $favorites = json_decode(file_get_contents($cache_file), true);
                } else {
                    $favorites = json_decode(file_get_contents($file), true) ?: [];
                    file_put_contents($cache_file, json_encode($favorites));
                }
                return $reverse ? array_reverse($favorites) : $favorites;
            }

            // Function to add a favorite
            function add_favorite(&$favorites, $url, $title, $blacklist_domains) {
                if (is_blacklisted($url, $blacklist_domains)) {
                    return "The URL is on the blacklist.";
                }
                foreach ($favorites as $favorite) {
                    if ($favorite['url'] === $url) {
                        return "The URL is already in favorites.";
                    }
                }
                $favorites[] = [
                    'timestamp' => date('Y-m-d H:i:s'),
                    'title' => $title,
                    'url' => $url,
                    'delete_at' => null // New field for deletion date
                ];
                return "";
            }

            // Function to delete a favorite by URL
            function delete_favorite_by_url(&$favorites, $url) {
                $favorites = array_filter($favorites, function($favorite) use ($url) {
                    return $favorite['url'] !== $url;
                });
            }

            // Function to clean up old data and send backup email
            function cleanup_old_data(&$favorites, $file, $email_address) {
                global $cache_file;
                $current_time = new DateTime();
                $send_email = false;

                foreach ($favorites as &$favorite) {
                    $favorite_time = DateTime::createFromFormat('Y-m-d H:i:s', $favorite['timestamp']);
                    $interval = $current_time->diff($favorite_time);

                    // Mark for deletion if older than MAX_DAYS_TO_KEEP
                    if ($interval->days > MAX_DAYS_TO_KEEP && $favorite['delete_at'] === null) {
                        $favorite['delete_at'] = (new DateTime())->modify("+".DAYS_BEFORE_DELETION." days")->format('Y-m-d H:i:s');
                        $send_email = true;
                    }

                    // Delete if delete_at date is reached
                    if ($favorite['delete_at'] !== null) {
                        $delete_time = DateTime::createFromFormat('Y-m-d H:i:s', $favorite['delete_at']);
                        if ($current_time >= $delete_time) {
                            unset($favorite);
                        }
                    }
                }

                // Send backup email if any link is marked for deletion
                if ($send_email) {
                    send_backup_email($file, $email_address);
                }

                // Remove null entries from array
                $favorites = array_filter($favorites);

                // Save updated favorites and update cache
                file_put_contents($file, json_encode($favorites, JSON_PRETTY_PRINT));
                file_put_contents($cache_file, json_encode($favorites));
            }

            // Function to show messages
            function show_message($message, $is_error = false) {
                $class = $is_error ? 'error' : 'success';
                echo "<div class='{$class}'>{$message}</div>";
            }

            // Function to escape strings
            function escape($string) {
                return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
            }

            // Function to send backup email with favorites.json as attachment
            function send_backup_email($file, $email_address) {
                $subject = "Simple Web-Link-Tracker - " . date('Y-m-d');
                $message = "Here is the backup of your favorites before the deletion of old links.";
                $separator = md5(time());
                $filename = "favorites.json";
                $attachment = chunk_split(base64_encode(file_get_contents($file)));
                $eol = PHP_EOL;

                // Headers
                $headers = "From: " . sanitize_email_header(FROM_ADDRESS) . $eol;
                $headers .= "MIME-Version: 1.0" . $eol;
                $headers .= "Content-Type: multipart/mixed; boundary=\"" . $separator . "\"" . $eol;
                $headers .= "Content-Transfer-Encoding: 7bit" . $eol;
                $headers .= "This is a MIME encoded message." . $eol;

                // Message part
                $body = "--" . $separator . $eol;
                $body .= "Content-Type: text/plain; charset=\"iso-8859-1\"" . $eol;
                $body .= "Content-Transfer-Encoding: 8bit" . $eol . $eol;
                $body .= $message . $eol;

                // Attachment part
                $body .= "--" . $separator . $eol;
                $body .= "Content-Type: application/octet-stream; name=\"" . $filename . "\"" . $eol;
                $body .= "Content-Transfer-Encoding: base64" . $eol;
                $body .= "Content-Disposition: attachment" . $eol . $eol;
                $body .= $attachment . $eol;
                $body .= "--" . $separator . "--";

                // Send email
                if (mail($email_address, $subject, $body, $headers)) {
                    show_message("Backup email sent successfully.");
                } else {
                    show_message("Error sending backup email.", true);
                }
            }

            // Function to sanitize email headers
            function sanitize_email_header($header) {
                return preg_replace('/\r\n|\r|\n/', '', $header);
            }

            // Function to clean up old cache and rate limit files
            function clean_old_files($dir, $lifetime = 3600) {
                foreach (glob("$dir/*") as $file) {
                    if (is_file($file) && (time() - filemtime($file) > $lifetime)) {
                        unlink($file);
                    }
                }
            }

            // Ensure cache directory is cleaned up
            clean_old_files(CACHE_DIR);

            // Check if the secret value is provided and valid
            if (!isset($_GET['secret']) || $_GET['secret'] !== SECRET_VALUE) {
                $intruder_attempt = [
                    'timestamp' => date('Y-m-d H:i:s'),
                    'session_id' => $session_id,
                    'ip_address' => $_SERVER['REMOTE_ADDR'],
                    'used_secret' => $_GET['secret'] ?? '',
                    'submitted_title' => $_GET['title'] ?? '',
                    'submitted_url' => $_GET['url'] ?? ''
                ];
                $intruders = json_decode(file_get_contents($intruder), true) ?: [];
                $intruders[] = $intruder_attempt;
                file_put_contents($intruder, json_encode($intruders, JSON_PRETTY_PRINT));
                show_message('Invalid secret value. Request aborted.', true);
                exit;
            }

            // Check CSRF token
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token'])) {
                show_message('Invalid CSRF token.', true);
                exit;
            }

            $blacklist_domains = read_blacklist($blacklist);

            // Function to check if the URL is blacklisted
            function is_blacklisted($url, $blacklist_domains) {
                $parsed_url = parse_url($url);
                if (!isset($parsed_url['host'])) {
                    return false;
                }
                $host = $parsed_url['host'];
                foreach ($blacklist_domains as $blacklisted_domain) {
                    if (stripos($host, trim($blacklisted_domain)) !== false) {
                        return true;
                    }
                }
                return false;
            }

            // Read sorting order from query parameter
            $sort_order = $_GET['sort'] ?? 'desc';

            // Cleanup old data
            $favorites = read_favorites($file, $sort_order === 'desc');
            cleanup_old_data($favorites, $file, EMAIL_ADDRESS);

            // Handle manual backup request
            if (isset($_GET['backup']) && $_GET['backup'] === 'true') {
                send_backup_email($file, EMAIL_ADDRESS);
                show_message("Manual backup email sent successfully.");
            }

            // Handle deletion of a favorite
            if (isset($_GET['delete'])) {
                try {
                    $url_to_delete = validate_input($_GET['delete'], 'url');
                    if ($url_to_delete !== false) {
                        $lockHandle = fopen($lockFile, 'r');
                        if ($lockHandle) {
                            flock($lockHandle, LOCK_EX);
                            delete_favorite_by_url($favorites, $url_to_delete);
                            file_put_contents($file, json_encode($favorites, JSON_PRETTY_PRINT));
                            file_put_contents($cache_file, json_encode($favorites)); // Update cache
                            flock($lockHandle, LOCK_UN);
                            fclose($lockHandle);
                            show_message("The URL has been deleted successfully.");
                        } else {
                            throw new Exception("Could not open lock file.");
                        }
                    } else {
                        show_message("Error: Invalid URL for deletion.", true);
                    }
                } catch (Exception $e) {
                    show_message("An error occurred: " . $e->getMessage(), true);
                }
            }

            // Get the search query and filter the favorites
            $search_query = $_GET['search'] ?? '';
            if ($search_query !== '') {
                $favorites = array_filter($favorites, function($favorite) use ($search_query) {
                    return stripos($favorite['title'], $search_query) !== false ||
                           stripos($favorite['url'], $search_query) !== false ||
                           stripos($favorite['timestamp'], $search_query) !== false;
                });
            }

            // Calculate pagination
            $total_pages = ceil(count($favorites) / MAX_LINKS_PER_PAGE);
            $current_page = max(1, min($total_pages, (int)($_GET['page'] ?? 1)));
            $offset = ($current_page - 1) * MAX_LINKS_PER_PAGE;
            $links_on_page = array_slice($favorites, $offset, MAX_LINKS_PER_PAGE);

            // Handle adding a new favorite
            if (isset($_GET['url']) && isset($_GET['title'])) {
                try {
                    $url = validate_input($_GET['url'], 'url');
                    $title = validate_input($_GET['title'], 'string');
                    $title = escape($title);
                    if ($url && strlen($url) <= 2048 && $title && strlen($title) <= MAX_TITLE_LENGTH) {
                        $lockHandle = fopen($lockFile, 'r');
                        if ($lockHandle) {
                            flock($lockHandle, LOCK_EX);
                            $error = add_favorite($favorites, $url, $title, $blacklist_domains);
                            if ($error) {
                                show_message($error, true);
                            } else {
                                file_put_contents($file, json_encode($favorites, JSON_PRETTY_PRINT));
                                file_put_contents($cache_file, json_encode($favorites)); // Update cache
                                show_message("The URL has been added successfully.");
                            }
                            flock($lockHandle, LOCK_UN);
                            fclose($lockHandle);
                        } else {
                            throw new Exception("Could not open lock file.");
                        }
                    } else {
                        show_message("An unexpected error occurred. Please try again later.", true);
                    }
                } catch (Exception $e) {
                    show_message("An error occurred: " . $e->getMessage(), true);
                }
                exit;
            }

            // Check rate limiting based on IP address
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $rate_limit_file = CACHE_DIR . '/rate_limit_' . md5($ip_address) . '.json';
            $rate_limit = json_decode(@file_get_contents($rate_limit_file), true) ?: ['requests' => 0, 'timestamp' => time()];

            if (time() - $rate_limit['timestamp'] < 60) {
                $rate_limit['requests']++;
                if ($rate_limit['requests'] > MAX_REQUESTS_PER_MINUTE) {
                    header('HTTP/1.1 429 Too Many Requests');
                    header('Retry-After: 60');
                    show_message('Too many requests. Please wait a minute.', true);
                    exit;
                }
            } else {
                $rate_limit['requests'] = 1;
                $rate_limit['timestamp'] = time();
            }
            file_put_contents($rate_limit_file, json_encode($rate_limit));

            ?>

        <a href="?secret=<?php echo SECRET_VALUE; ?>"><h1>Simple Web-Link-Tracker</h1></a>
        <h3>keep it stupid simple</h3>

        <div class="controls">
            <!-- Pagination controls -->
            <div class="pagination">
                <?php for ($page = 1; $page <= $total_pages; $page++): ?>
                    <?php if ($page == $current_page): ?>
                        <strong><?php echo $page; ?></strong>
                    <?php else: ?>
                        <a href="?secret=<?php echo SECRET_VALUE; ?>&page=<?php echo $page; ?>"><?php echo $page; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
            
            <!-- Search form -->
            <form action="" method="get" class="search-container">
                <input type="hidden" name="secret" value="<?php echo SECRET_VALUE; ?>">
                <input type="text" name="search" placeholder="Search ..." value="<?php echo escape($search_query); ?>">
                <button type="submit">Search</button>
            </form>
        </div>

        <div class="table">
            <div class="header cell">Delete</div>
            <div class="header cell"><a href="?secret=<?php echo SECRET_VALUE; ?>&sort=<?php echo $sort_order === 'asc' ? 'desc' : 'asc'; ?>">Timestamp</a></div>
            <div class="header cell">Bookmark</div>

            <?php foreach ($links_on_page as $favorite) { ?>
            <div class="cell">
                <a href="?secret=<?php echo SECRET_VALUE; ?>&delete=<?php echo urlencode($favorite['url']); ?>"><img src="assets/icons/del_red.svg" alt="Delete" width="24" height="24"></a>
            </div>
            <div class="cell"><?php echo $favorite['timestamp']; ?></div>
            <?php $escaped_title = escape($favorite['title']); ?>
            <div class="cell"><a href="<?php echo escape($favorite['url']); ?>" rel="noopener noreferrer" target="_blank"><?php echo $escaped_title; ?></a></div>
            <?php } ?>
        </div>

        <!-- Pagination controls -->
        <div class="pagination">
            <?php for ($page = 1; $page <= $total_pages; $page++): ?>
                <?php if ($page == $current_page): ?>
                    <strong><?php echo $page; ?></strong>
                <?php else: ?>
                    <a href="?secret=<?php echo SECRET_VALUE; ?>&page=<?php echo $page; ?>"><?php echo $page; ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </div>

        <!-- Measured values -->
        <div class="measured">
            <?php $time_total = microtime(true) - $start; ?>
            Total links: <?php echo count($favorites); ?><br>
            Total processing time:<br><?php echo $time_total; ?> seconds.
        </div>

        <!-- Footer -->
        <div class="footer">
            <a href="proxy.php?secret=<?php echo SECRET_VALUE; ?>&file=favorites.json">favorites.json</a>&nbsp;&vert;
            <a href="proxy.php?secret=<?php echo SECRET_VALUE; ?>&file=intruder.json">intruder.json</a>&nbsp;&vert;
            <a href="proxy.php?secret=<?php echo SECRET_VALUE; ?>&file=proxy.json">proxy.json</a>&nbsp;&vert;
            <a href="?secret=<?php echo SECRET_VALUE; ?>&backup=true">Manual Backup</a>
        </div>

        <!-- Copyright -->
        <div class="copyright">
            made with <img src="assets/icons/heart.svg" width="12" height="12"> by (c) 2023-<?php $currentYear = date('Y'); echo $currentYear; ?> 
            <a href="https://github.com/ot2i7ba/" rel="noopener noreferrer" target="_blank">ot2i7ba</a>,<br>
            without Java, Perl, MySQL, Postgres and specialist knowledge. :)
        </div> 
    </div>
</body>
</html>
