<?php
/**
 * Legacy HTML/CSS renderer retained for comparison and emergency rollback.
 * Production /index.php defaults to Canvas V2.
 */
declare(strict_types=1);
define('CANVAS_V2', false);
require __DIR__ . '/index.php';
