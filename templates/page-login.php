<?php
/**
 * Template Name: Passwordless Login Page
 * 
 * A template for displaying the passwordless login form
 */

get_header();
?>

<div id="primary" class="content-area">
    <main id="main" class="site-main" role="main">
        <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
            <header class="entry-header">
                <h1 class="entry-title"><?php the_title(); ?></h1>
            </header>

            <div class="entry-content">
                <?php
                // Display the login form
                echo do_shortcode('[passwordless_login_form]');
                ?>
            </div>
        </article>
    </main>
</div>

<?php
get_sidebar();
get_footer();
