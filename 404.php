<?php get_header(); ?>

<main class="lc-content">
<?php
/**
 * Not found. Without this, a missing URL fell through to an empty loop and
 * rendered a blank page between the header and footer.
 */
lc_render_404();
?>
</main>

<?php get_footer(); ?>
