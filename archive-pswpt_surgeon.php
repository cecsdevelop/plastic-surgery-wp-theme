<?php
/**
 * Listado de cirujanos (/surgeon/, /es/cirujano/). El cuerpo es la "Página
 * de Cirujanos" de Ajustes → Lectura: su hero y sus bloques por idioma. Sin
 * página asignada: hero con el nombre del tipo y una grilla simple con foto,
 * nombre y rol enlazados a la ficha.
 */
if (!defined('ABSPATH')) exit;
get_header();
$archive_page = pswpt_get_archive_page(\pswptInit\Surgeons\SurgeonsController::POST_TYPE);
?>
<main class="site-main">
  <?php if ($archive_page) : ?>
    <?php pswpt_render_archive_page($archive_page); ?>
  <?php else : ?>
    <?php pswpt_render_interior_hero(post_type_archive_title('', false), '', false); ?>
    <div class="entry-content">
      <ul class="tiles">
        <?php $ctrl = new \pswptInit\Surgeons\SurgeonsController(); foreach ($ctrl->get_items() as $surgeon) : ?>
          <li class="tile">
            <a class="tile__link" href="<?php echo esc_url(get_permalink($surgeon)); ?>">
              <?php echo get_the_post_thumbnail($surgeon, 'medium'); ?>
              <h3 class="tile__name"><?php echo esc_html(get_the_title($surgeon)); ?></h3>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>
</main>
<?php
get_footer();
