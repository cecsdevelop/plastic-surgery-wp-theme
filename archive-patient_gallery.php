<?php
/**
 * Listado de casos de Patient Gallery (antes/después). El cuerpo es la
 * "Página de Patient Gallery" de Ajustes → Lectura si hay una asignada (hero
 * + bloques propios); si no, la intro real de producción
 * (PatientGalleryRenderer::intro_html()). Debajo va el filtro de dos niveles
 * (categoría → sub-procedimiento, más cirujano) y la grilla completa.
 *
 * Sin paginar: igual que producción, las ~211 fichas se entregan todas en
 * una sola carga (PatientGalleryController::filter_archive_query() pone
 * posts_per_page=-1) y el filtro las muestra/oculta 100% client-side
 * (patient-gallery-archive.js) — confirmado navegando el sitio real, que
 * tampoco pagina. Se decidió así después de comprobar que el filtro de dos
 * niveles necesita las fichas de todo el listado presentes en el DOM para
 * poder filtrarlas; con paginación server-side solo se podría filtrar
 * dentro de la página actual, y el contador "Showing X of 211" quedaría mal.
 *
 * `.pg-rebuild`/`.pg-intro`/`.pg-grid`/`.pg-card`/`.pg-filterbox`/
 * `.pg-saved-bar`/`.pg-mobilebar` son las clases reales de producción (HTML
 * y CSS pegados por el cliente el 2026-09-25, comportamiento confirmado
 * navegando el sitio real en mobile). El envío de "Send My Favorites" pasa
 * por FavoritesHandler (REST pswpt/v1/patient-gallery/favorites, wp_mail —
 * ver ese archivo). La franja pegada abajo del todo con teléfono + "Book
 * Consultation" que se ve en producción en mobile (`.fsc-bar`) es el módulo
 * Sticky CTA del theme, global y ya existente — no es parte de esto.
 */
if (!defined('ABSPATH')) {
    exit;
}

get_header();

$archive_page = pswpt_get_archive_page(\pswptInit\PatientGallery\PatientGalleryController::POST_TYPE);
?>
<main class="site-main">
  <div class="pg-rebuild">
  <?php if ($archive_page) : ?>
    <?php pswpt_render_archive_page($archive_page); ?>
  <?php else : ?>
    <?php echo \pswptInit\PatientGallery\PatientGalleryRenderer::intro_html(); ?>
  <?php endif; ?>

  <?php echo \pswptInit\PatientGallery\PatientGalleryRenderer::archive_mobilebar_html(); ?>
  <?php echo \pswptInit\PatientGallery\PatientGalleryRenderer::filter_box_html(); ?>
  <?php echo \pswptInit\PatientGallery\PatientGalleryRenderer::saved_bar_html(); ?>

  <?php if (have_posts()) : ?>
    <div class="pg-grid" id="pg-grid">
      <?php $i = 0; while (have_posts()) : the_post(); ?>
        <?php echo \pswptInit\PatientGallery\PatientGalleryRenderer::card_html(get_post(), $i); $i++; ?>
      <?php endwhile; ?>
    </div>
  <?php else : ?>
    <p class="pg-empty"><?php esc_html_e('No cases match these filters.', 'pswpt'); ?></p>
  <?php endif; ?>
  </div>
</main>
<?php
get_footer();
