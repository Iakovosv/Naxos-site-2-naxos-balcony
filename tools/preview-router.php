<?php
/**
 * Dev preview router — not part of the deployed site.
 *
 * Usage:
 *   php -S 0.0.0.0:12000 -t preview tools/preview-router.php
 *
 * Serves preview/ while executing .php instead of handing it out as a file.
 * A plain static server once exposed admin/index.php as a 20 KB download,
 * leaking the CSRF logic, so this router must stay in front of the docroot.
 *
 * /api/* is proxied to the live site (the real weather endpoints live only on
 * the host and are never committed), falling back to a cached snapshot when
 * the network is unavailable.
 */
declare(strict_types=1);

$ROOT = dirname(__DIR__) . '/preview';
$CACHE = sys_get_temp_dir() . '/preview-api';
@mkdir($CACHE, 0777, true);

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$uri = urldecode($uri);

/* ------------------------------------------------------------- weather API */
if (str_starts_with($uri, '/api/')) {
    $name = basename($uri);
    if (!preg_match('/^weather-[a-z\-]+\.php$/', $name)) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo '{"error":"unknown endpoint"}';
        return true;
    }

    $cacheFile = $CACHE . '/' . $name . '.json';
    $ctx = stream_context_create(['http' => [
        'timeout' => 8,
        'ignore_errors' => true,
        'header' => "User-Agent: naxos-preview\r\n",
    ]]);
    $body = @file_get_contents('https://www.naxosbalcony.gr/api/' . $name, false, $ctx);

    if ($body !== false && $body !== '') {
        @file_put_contents($cacheFile, $body, LOCK_EX);
    } elseif (is_file($cacheFile)) {
        $body = (string)file_get_contents($cacheFile);
    }

    if ($body === false || $body === '') {
        http_response_code(503);
        header('Content-Type: application/json; charset=utf-8');
        echo '{"error":"weather source unavailable"}';
        return true;
    }

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo $body;
    return true;
}

/* --------------------------------------------------------------- static/php */
$path = realpath($ROOT . $uri);

// Directory request -> index.html, or index.php if that is all there is.
if ($uri !== '/' && is_dir($ROOT . $uri)) {
    $dir = rtrim($ROOT . $uri, '/');
    if (is_file($dir . '/index.html')) { $path = $dir . '/index.html'; $uri = rtrim($uri, '/') . '/index.html'; }
    elseif (is_file($dir . '/index.php')) { $path = $dir . '/index.php'; }
}

// Never let a path escape the docroot.
if ($path === false || !str_starts_with($path, $ROOT)) {
    http_response_code(404);
    $custom = $ROOT . '/404.html';
    if (is_file($custom)) { header('Content-Type: text/html; charset=utf-8'); readfile($custom); return true; }
    echo 'Not Found';
    return true;
}

if (is_dir($path)) {
    $path = $path . '/index.html';
}

// A real file: .php runs, anything else is streamed by the built-in server.
if (is_file($path)) {
    if (str_ends_with($path, '.php')) {
        // credentials.php is a config return; the real host blocks it too.
        if (basename($path) === 'credentials.php') {
            http_response_code(403);
            echo 'Forbidden';
            return true;
        }
        chdir(dirname($path));
        require $path;
        return true;
    }
    return false;
}

http_response_code(404);
$custom = $ROOT . '/404.html';
if (is_file($custom)) { header('Content-Type: text/html; charset=utf-8'); readfile($custom); return true; }
echo 'Not Found';
return true;
