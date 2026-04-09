<?php
/**
 * M15 – Trombinoscope — Header
 */
$rootPrefix = '../';
require_once __DIR__ . '/../../API/bootstrap.php';
requireAuth();

require_once __DIR__ . '/TrombinoscopeService.php';
$trombiService = new TrombinoscopeService(getPDO());

$activePage = 'trombinoscope';
$pageTitle = $pageTitle ?? 'Trombinoscope';
$extraCss = ['assets/css/trombinoscope.css'];

require_once __DIR__ . '/../../templates/shared_header.php';
require_once __DIR__ . '/../../templates/shared_topbar.php';
