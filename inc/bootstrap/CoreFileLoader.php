<?php

namespace pswptInit\Bootstrap;

class CoreFileLoader
{
    public function load_all(): void
    {
        foreach ($this->core_files() as $file) {
            $path = get_template_directory() . $file;
            if (file_exists($path)) {
                require_once $path;
            }
        }
    }

    private function core_files(): array
    {
        return [
            '/inc/admin/setup.php',
            '/inc/admin/i18n.php',
            '/inc/admin/admin-languages.php',
            '/inc/admin/admin-translations.php',
            '/inc/admin/admin-settings.php',
            '/inc/admin/admin-typography.php',
            '/inc/admin/admin-fields.php',
            '/inc/admin/admin-header-settings.php',
            '/inc/admin/admin-footer-settings.php',
            '/inc/admin/admin-post-translation-settings.php',
            '/inc/admin/admin-post-categories-translation-settings.php',
            '/inc/admin/image-optimization.php',
            '/inc/admin/menus.php',
            '/inc/admin/customizer.php',
            '/inc/admin/multilang-rewrite.php',
            '/inc/admin/content-filters.php',
            '/inc/admin/helpers.php',
            '/inc/admin/chrome-helpers.php',
            '/inc/admin/seo-analytics.php',
            '/inc/admin/seo-multilang.php',
            '/inc/admin/security-hardening.php',
            '/inc/admin/login-url.php',
        ];
    }
}
