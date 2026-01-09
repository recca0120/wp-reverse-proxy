<?php
/**
 * Routes list view
 *
 * @var array $routes
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Reverse Proxy Routes', 'reverse-proxy'); ?></h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=reverse-proxy&action=new')); ?>" class="page-title-action">
        <?php esc_html_e('Add New', 'reverse-proxy'); ?>
    </a>
    <hr class="wp-header-end">

    <?php if (empty($routes)) : ?>
        <p><?php esc_html_e('No routes configured yet.', 'reverse-proxy'); ?></p>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" class="column-status"><?php esc_html_e('Status', 'reverse-proxy'); ?></th>
                    <th scope="col" class="column-path"><?php esc_html_e('Path', 'reverse-proxy'); ?></th>
                    <th scope="col" class="column-target"><?php esc_html_e('Target', 'reverse-proxy'); ?></th>
                    <th scope="col" class="column-methods"><?php esc_html_e('Methods', 'reverse-proxy'); ?></th>
                    <th scope="col" class="column-middlewares"><?php esc_html_e('Middlewares', 'reverse-proxy'); ?></th>
                    <th scope="col" class="column-actions"><?php esc_html_e('Actions', 'reverse-proxy'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($routes as $route) : ?>
                    <tr>
                        <td class="column-status">
                            <?php if (!empty($route['enabled'])) : ?>
                                <span class="dashicons dashicons-yes-alt" style="color: green;" title="<?php esc_attr_e('Enabled', 'reverse-proxy'); ?>"></span>
                            <?php else : ?>
                                <span class="dashicons dashicons-dismiss" style="color: gray;" title="<?php esc_attr_e('Disabled', 'reverse-proxy'); ?>"></span>
                            <?php endif; ?>
                        </td>
                        <td class="column-path">
                            <code><?php echo esc_html($route['path']); ?></code>
                        </td>
                        <td class="column-target">
                            <code><?php echo esc_html($route['target']); ?></code>
                        </td>
                        <td class="column-methods">
                            <?php echo esc_html(!empty($route['methods']) ? implode(', ', $route['methods']) : 'ALL'); ?>
                        </td>
                        <td class="column-middlewares">
                            <?php echo esc_html(count($route['middlewares'] ?? [])); ?>
                        </td>
                        <td class="column-actions">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=reverse-proxy&action=edit&route_id=' . $route['id'])); ?>" class="button button-small">
                                <?php esc_html_e('Edit', 'reverse-proxy'); ?>
                            </a>
                            <button type="button" class="button button-small reverse-proxy-toggle" data-route-id="<?php echo esc_attr($route['id']); ?>">
                                <?php echo !empty($route['enabled']) ? esc_html__('Disable', 'reverse-proxy') : esc_html__('Enable', 'reverse-proxy'); ?>
                            </button>
                            <button type="button" class="button button-small button-link-delete reverse-proxy-delete" data-route-id="<?php echo esc_attr($route['id']); ?>">
                                <?php esc_html_e('Delete', 'reverse-proxy'); ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
