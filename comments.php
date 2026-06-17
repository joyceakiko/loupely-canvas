<?php
/**
 * Comments for single posts. Standard WordPress markup, intentionally
 * unstyled so you can style it from your Head code box like the rest of your
 * site. Target the usual classes: .commentlist, .comment, #respond.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( post_password_required() ) {
    return;
}
?>
<div id="comments" class="comments-area">
    <?php if ( have_comments() ) : ?>
        <h2 class="comments-title">
            <?php
            $lc_count = get_comments_number();
            printf(
                /* translators: %s: comment count number. */
                esc_html( _n( '%s comment', '%s comments', $lc_count, 'loupely-canvas' ) ),
                esc_html( number_format_i18n( $lc_count ) )
            );
            ?>
        </h2>

        <ol class="commentlist">
            <?php
            wp_list_comments( [
                'style'      => 'ol',
                'short_ping' => true,
            ] );
            ?>
        </ol>

        <?php
        the_comments_pagination( [
            'prev_text' => esc_html__( 'Older comments', 'loupely-canvas' ),
            'next_text' => esc_html__( 'Newer comments', 'loupely-canvas' ),
        ] );
        ?>
    <?php endif; ?>

    <?php if ( ! comments_open() && get_comments_number() && post_type_supports( get_post_type(), 'comments' ) ) : ?>
        <p class="no-comments"><?php esc_html_e( 'Comments are closed.', 'loupely-canvas' ); ?></p>
    <?php endif; ?>

    <?php comment_form(); ?>
</div>
