<?php
/**
 * V2 Canvas-first 文档预览入口。
 * 复用主站的用户、订单、福利码和表单逻辑，只替换文档渲染/下载引擎。
 */
declare(strict_types=1);
define('CANVAS_V2', true);
require dirname(__DIR__) . '/index.php';
