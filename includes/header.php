<?php
/**
 * CSQA portal page header, breadcrumb bar and left menu.
 *
 * Set before including:
 *   $page_title  (string) title of the page
 *   $active_page (string) 'home', 'cycles', 'about' or a parameter id
 *   $breadcrumb  (string) breadcrumb label of the page (empty for home)
 *   $extra_head  (string, optional) extra html for <head> (ie scripts)
 */

require_once __DIR__ . '/functions.php';

$manifest = csqa_manifest();
$page_title = $page_title ?? CSQA_SITE_TITLE;
$active_page = $active_page ?? '';
$breadcrumb = $breadcrumb ?? '';

// menu: parameters grouped by source product
$menu_groups = [];
if ($manifest) {
    foreach ($manifest['parameters'] as $menu_param) {
        $menu_groups[$menu_param['source']][] = $menu_param;
    }
}
$menu_group_titles = ['GDR-A' => 'L2 Parameters', 'L2I' => 'L2i Parameters'];

function csqa_menu_item(string $href, string $label, bool $active, string $icon = 'fa-circle-chevron-right'): void
{
    $class = $active ? 'csqa-menu-item active' : 'csqa-menu-item';
    $aria = $active ? ' aria-current="page"' : '';
    echo '<a class="' . $class . '" href="' . h($href) . '"' . $aria . '>'
        . '<i class="fa-solid ' . h($icon) . '" aria-hidden="true"></i>' . h($label) . '</a>' . "\n";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($page_title === CSQA_SITE_TITLE ? $page_title : "$page_title | " . CSQA_SITE_TITLE) ?></title>
    <meta name="description" content="CryoSat-2 performance and quality monitoring by UCL on behalf of the European Space Agency. Monitors the performance of CryoSat-2 Level-2 products in LRM, SAR and SARin modes.">
    <link rel="icon" href="assets/images/favicon.ico">
    <link rel="stylesheet" href="<?= h(CSQA_BOOTSTRAP_CSS) ?>">
    <link rel="stylesheet" href="<?= h(CSQA_FONTAWESOME_CSS) ?>">
    <link rel="stylesheet" href="assets/css/csqa.css?v=5">
    <?= $extra_head ?? '' ?>
</head>
<body>
<div class="csqa-page">
    <header class="csqa-banner">
        <a href="index.php"><img src="assets/images/cryosatqa_banner.gif" width="960" height="100"
             alt="CryoSat Performance Monitoring - UCL"></a>
    </header>

    <nav class="csqa-crumbs" aria-label="breadcrumb">
        <div>
            <button class="btn btn-sm btn-outline-secondary d-lg-none me-2" type="button"
                    data-bs-toggle="offcanvas" data-bs-target="#csqa-menu" aria-controls="csqa-menu">
                <i class="fa-solid fa-bars"></i> Menu
            </button>
            <a href="index.php">Home</a><?php if ($breadcrumb !== ''): ?>
            <span class="sep">&rarr;</span><span><?= h($breadcrumb) ?></span><?php endif; ?>
        </div>
        <a class="d-none d-sm-inline" href="about.php">Documentation</a>
    </nav>

    <div class="csqa-body">
        <aside class="offcanvas-lg offcanvas-start csqa-menu" tabindex="-1" id="csqa-menu"
               aria-labelledby="csqa-menu-title">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title" id="csqa-menu-title">Menu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas"
                        data-bs-target="#csqa-menu" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body flex-column">
                <?php
                csqa_menu_item('index.php', 'Overview', $active_page === 'home', 'fa-house');
                csqa_menu_item('cycles.php', 'Data Takes (Cycles)', $active_page === 'cycles', 'fa-calendar-days');
                ?>
                <?php foreach ($menu_groups as $source => $menu_params): ?>
                    <div class="csqa-menu-header"><?= h($menu_group_titles[$source] ?? $source) ?></div>
                    <?php foreach ($menu_params as $menu_param) {
                        csqa_menu_item(csqa_url('parameter.php', ['p' => $menu_param['id']]),
                            $menu_param['long_name'], $active_page === $menu_param['id']);
                    } ?>
                <?php endforeach; ?>
                <div class="csqa-menu-header">Further Info</div>
                <?php
                csqa_menu_item('about.php', 'Documentation', $active_page === 'about', 'fa-book');
                csqa_menu_item('https://earth.esa.int/eogateway/missions/cryosat', 'ESA CryoSat Site', false,
                    'fa-arrow-up-right-from-square');
                csqa_menu_item('https://earth.esa.int/eogateway/missions/cryosat/data/data-unavailability-periods',
                    'Unavailability Periods', false, 'fa-arrow-up-right-from-square');
                ?>
                <img class="csqa-esa" src="assets/images/on_behalf_esa.gif" width="163" height="172"
                     alt="On behalf of the European Space Agency">
            </div>
        </aside>

        <main class="csqa-main">
