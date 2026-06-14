<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<?php
/**
 * Renders the site header.
 *
 * Pulls from Appearance > Header & Footer HTML (the Header box).
 * If that box is empty, falls back to a published page with the
 * slug 'site-header'. If neither exists, outputs nothing.
 */
lc_render_header();
?>
