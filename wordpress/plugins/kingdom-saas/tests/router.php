<?php
// php -S router for a WordPress multisite test install.
$root = $_SERVER['DOCUMENT_ROOT'];
$path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
$file = $root . $path;
if ( is_file( $file ) ) {
	if ( substr( $file, -4 ) === '.php' ) { $_SERVER['SCRIPT_NAME'] = $path; $_SERVER['SCRIPT_FILENAME'] = $file; chdir( dirname( $file ) ); require $file; return true; }
	return false;
}
if ( is_dir( $file ) && is_file( rtrim( $file, '/' ) . '/index.php' ) ) {
	$_SERVER['SCRIPT_NAME'] = rtrim( $path, '/' ) . '/index.php'; chdir( $file ); require rtrim( $file, '/' ) . '/index.php'; return true;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require $root . '/index.php';
