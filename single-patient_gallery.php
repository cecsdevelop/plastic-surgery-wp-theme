<?php
/**
 * Detalle de un caso: calcado del DOM real en producción
 * (femsculpt.com/patient-gallery/{slug}/, pegado por el cliente el
 * 2026-09-25) — mismas clases `pgd-*`/`pg-lb-*` y mismo orden, ver
 * PatientGalleryRenderer::detail_html(). Sin loop de contenido propio de la
 * plantilla: todo el marcado (breadcrumb, título, fotos, ficha, categorías,
 * narrativa, CTA, siguiente/anterior, mobile bar, lightbox, JSON-LD) sale de
 * ese único método.
 */
if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>
<main class="site-main">
  <?php while (have_posts()) : the_post(); ?>
    <?php echo \pswptInit\PatientGallery\PatientGalleryRenderer::detail_html(get_post()); ?>
  <?php endwhile; ?>
</main>
<?php
get_footer();
