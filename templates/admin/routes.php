<?php
/**
 * Routes list view
 *
 * @var array $routes
 * @var \Recca0120\ReverseProxy\WordPress\Admin\RoutesPage $routesPage
 */

if (! defined('ABSPATH')) {
    exit;
}

// Display admin notices
if (isset($_GET['message'])) {
    $message = sanitize_text_field($_GET['message']);
    $notice_class = 'notice-success';
    $notice_text = '';

    switch ($message) {
        case 'saved':
            $notice_text = __('Route saved successfully.', 'reverse-proxy');
            break;
        case 'deleted':
            $notice_text = __('Route deleted successfully.', 'reverse-proxy');
            break;
        case 'toggled':
            $notice_text = __('Route status updated.', 'reverse-proxy');
            break;
    }

    if ($notice_text) {
        echo '<div class="notice ' . esc_attr($notice_class) . ' is-dismissible"><p>' . esc_html($notice_text) . '</p></div>';
    }
}

if (isset($_GET['error'])) {
    $error = sanitize_text_field($_GET['error']);
    $error_text = '';

    switch ($error) {
        case 'save_failed':
            $error_text = __('Failed to save route. Please check the path and target URL.', 'reverse-proxy');
            break;
    }

    if ($error_text) {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($error_text) . '</p></div>';
    }
}
?>
<div class="wrap reverse-proxy-admin">
    <h1 class="wp-heading-inline"><?php esc_html_e('Reverse Proxy Routes', 'reverse-proxy'); ?></h1>
    <a href="<?php echo esc_url(admin_url('options-general.php?page=reverse-proxy&action=new')); ?>" class="page-title-action">
        <?php esc_html_e('Add New', 'reverse-proxy'); ?>
    </a>
    <hr class="wp-header-end">

    <?php if (empty($routes)) : ?>
        <div class="notice notice-info">
            <p>
                <?php esc_html_e('No routes configured yet.', 'reverse-proxy'); ?>
                <a href="<?php echo esc_url(admin_url('options-general.php?page=reverse-proxy&action=new')); ?>">
                    <?php esc_html_e('Add your first route', 'reverse-proxy'); ?>
                </a>
            </p>
        </div>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" class="column-status"><?php esc_html_e('Status', 'reverse-proxy'); ?></th>
                    <th scope="col" class="column-path"><?php esc_html_e('Path', 'reverse-proxy'); ?></th>
                    <th scope="col" class="column-target"><?php esc_html_e('Target', 'reverse-proxy'); ?></th>
                    <th scope="col" class="column-methods"><?php esc_html_e('Methods', 'reverse-proxy'); ?></th>
                    <th scope="col" class="column-actions"><?php esc_html_e('Actions', 'reverse-proxy'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($routes as $route) : ?>
                    <tr>
                        <td class="column-status">
                            <?php if (! empty($route['enabled'])) : ?>
                                <span class="dashicons dashicons-yes-alt status-enabled" title="<?php esc_attr_e('Enabled', 'reverse-proxy'); ?>"></span>
                            <?php else : ?>
                                <span class="dashicons dashicons-marker status-disabled" title="<?php esc_attr_e('Disabled', 'reverse-proxy'); ?>"></span>
                            <?php endif; ?>
                        </td>
                        <td class="column-path">
                            <strong>
                                <a href="<?php echo esc_url(admin_url('options-general.php?page=reverse-proxy&action=edit&route_id=' . $route['id'])); ?>">
                                    <code><?php echo esc_html($route['path']); ?></code>
                                </a>
                            </strong>
                        </td>
                        <td class="column-target">
                            <code><?php echo esc_html($route['target']); ?></code>
                        </td>
                        <td class="column-methods">
                            <?php
                            if (! empty($route['methods'])) {
                                echo esc_html(implode(', ', $route['methods']));
                            } else {
                                echo '<span class="description">' . esc_html__('ALL', 'reverse-proxy') . '</span>';
                            }
                    ?>
                        </td>
                        <td class="column-actions">
                            <a href="<?php echo esc_url(admin_url('options-general.php?page=reverse-proxy&action=edit&route_id=' . $route['id'])); ?>" class="button button-small">
                                <?php esc_html_e('Edit', 'reverse-proxy'); ?>
                            </a>
                            <?php
                            $toggleUrl = $routesPage->getActionUrl($route['id'], 'toggle');
                    $deleteUrl = $routesPage->getActionUrl($route['id'], 'delete');
                    ?>
                            <a href="<?php echo esc_url($toggleUrl); ?>" class="button button-small reverse-proxy-toggle" data-route-id="<?php echo esc_attr($route['id']); ?>">
                                <?php echo ! empty($route['enabled']) ? esc_html__('Disable', 'reverse-proxy') : esc_html__('Enable', 'reverse-proxy'); ?>
                            </a>
                            <a href="<?php echo esc_url($deleteUrl); ?>" class="button button-small button-link-delete reverse-proxy-delete" data-route-id="<?php echo esc_attr($route['id']); ?>" onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this route?', 'reverse-proxy'); ?>');">
                                <?php esc_html_e('Delete', 'reverse-proxy'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div class="tablenav bottom">
        <div class="alignleft actions">
            <p class="description">
                <?php
                $fullPath = apply_filters('reverse_proxy_routes_directory', WP_CONTENT_DIR . '/reverse-proxy-routes');
$relativePath = str_replace(ABSPATH, '', $fullPath);
printf(
    /* translators: %s: directory path */
    esc_html__('Routes from configuration files in %s will also be loaded.', 'reverse-proxy'),
    '<code>' . esc_html($relativePath) . '</code>'
);
?>
            </p>
        </div>
    </div>
</div>
