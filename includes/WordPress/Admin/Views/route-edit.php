<?php
/**
 * Route edit view
 *
 * @var array|null $route
 * @var array $middlewares
 */

if (! defined('ABSPATH')) {
    exit;
}

$isNew = empty($route);
$pageTitle = $isNew ? __('Add New Route', 'reverse-proxy') : __('Edit Route', 'reverse-proxy');
?>
<div class="wrap">
    <h1><?php echo esc_html($pageTitle); ?></h1>

    <form method="post" action="" id="reverse-proxy-route-form">
        <?php wp_nonce_field('reverse_proxy_save_route', 'reverse_proxy_nonce'); ?>
        <input type="hidden" name="action" value="reverse_proxy_save_route">
        <?php if (! $isNew) : ?>
            <input type="hidden" name="route[id]" value="<?php echo esc_attr($route['id']); ?>">
        <?php endif; ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="route-path"><?php esc_html_e('Path Pattern', 'reverse-proxy'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <input type="text" id="route-path" name="route[path]" class="regular-text code"
                           value="<?php echo esc_attr($route['path'] ?? ''); ?>"
                           placeholder="/api/*" required>
                    <p class="description">
                        <?php esc_html_e('Use * as wildcard. Examples: /api/*, /blog/posts/*, /proxy/service/*', 'reverse-proxy'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="route-target"><?php esc_html_e('Target URL', 'reverse-proxy'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <input type="url" id="route-target" name="route[target]" class="regular-text code"
                           value="<?php echo esc_attr($route['target'] ?? ''); ?>"
                           placeholder="https://api.example.com" required>
                    <p class="description">
                        <?php esc_html_e('The backend server URL to proxy requests to (must be http:// or https://).', 'reverse-proxy'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('HTTP Methods', 'reverse-proxy'); ?></th>
                <td>
                    <fieldset>
                        <?php
                        $allMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];
$selectedMethods = $route['methods'] ?? [];
foreach ($allMethods as $method) :
    ?>
                            <label style="margin-right: 15px; display: inline-block;">
                                <input type="checkbox" name="route[methods][]" value="<?php echo esc_attr($method); ?>"
                                    <?php checked(in_array($method, $selectedMethods, true)); ?>>
                                <?php echo esc_html($method); ?>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                    <p class="description">
                        <?php esc_html_e('Leave all unchecked to allow all HTTP methods.', 'reverse-proxy'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="route-enabled"><?php esc_html_e('Status', 'reverse-proxy'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" id="route-enabled" name="route[enabled]" value="1"
                            <?php checked(! empty($route['enabled']) || $isNew); ?>>
                        <?php esc_html_e('Enable this route', 'reverse-proxy'); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e('Disabled routes will not proxy any requests.', 'reverse-proxy'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e('Middlewares', 'reverse-proxy'); ?></h2>
        <p class="description" style="margin-bottom: 15px;">
            <?php esc_html_e('Middlewares process requests before they are sent to the target server. They are executed in order from top to bottom.', 'reverse-proxy'); ?>
        </p>

        <div id="middleware-list">
            <!-- Middlewares will be loaded by JavaScript -->
        </div>
        <p>
            <button type="button" id="add-middleware" class="button">
                <span class="dashicons dashicons-plus-alt2" style="vertical-align: middle;"></span>
                <?php esc_html_e('Add Middleware', 'reverse-proxy'); ?>
            </button>
        </p>
        <input type="hidden" id="middlewares-json" name="middlewares_json" value="<?php
            $currentMiddlewares = $route['middlewares'] ?? [];
echo esc_attr(json_encode($currentMiddlewares));
?>" />

        <p class="submit">
            <input type="submit" name="submit" id="submit" class="button button-primary"
                   value="<?php echo esc_attr($isNew ? __('Add Route', 'reverse-proxy') : __('Update Route', 'reverse-proxy')); ?>">
            <a href="<?php echo esc_url(admin_url('admin.php?page=reverse-proxy')); ?>" class="button">
                <?php esc_html_e('Cancel', 'reverse-proxy'); ?>
            </a>
        </p>
    </form>
</div>

<style>
.required {
    color: #d63638;
}
.middleware-item {
    background: #f6f7f7;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    margin-bottom: 10px;
}
.middleware-header {
    display: flex;
    align-items: center;
    padding: 10px 12px;
    gap: 8px;
}
.middleware-drag-handle {
    cursor: move;
    color: #787c82;
    flex-shrink: 0;
}
.middleware-drag-handle:hover {
    color: #2271b1;
}
.middleware-select {
    flex: 1;
    max-width: 250px;
}
.middleware-header .remove-middleware {
    margin-left: auto;
    color: #b32d2e;
}
.middleware-header .remove-middleware:hover {
    color: #a00;
    border-color: #a00;
}
.middleware-body {
    padding: 0 12px 12px;
    border-top: 1px solid #dcdcde;
    margin-top: 0;
}
.middleware-body:empty,
.middleware-body.empty {
    display: none;
}
.middleware-body .description {
    margin: 10px 0 8px;
    color: #646970;
}
.middleware-fields-grid {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.middleware-field-wrapper label {
    display: block;
    font-weight: 500;
    margin-bottom: 4px;
}
.middleware-item-placeholder {
    background: #f0f6fc;
    border: 2px dashed #2271b1;
    border-radius: 4px;
    height: 50px;
    margin-bottom: 10px;
}
.ui-sortable-helper {
    background: #fff;
    box-shadow: 0 3px 6px rgba(0,0,0,0.15);
}
</style>
