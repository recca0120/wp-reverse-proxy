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

        <div id="middleware-mode-toggle" style="margin-bottom: 15px;">
            <label style="margin-right: 15px;">
                <input type="radio" name="middleware_mode" value="simple" checked>
                <?php esc_html_e('Simple Mode', 'reverse-proxy'); ?>
            </label>
            <label>
                <input type="radio" name="middleware_mode" value="advanced">
                <?php esc_html_e('Advanced Mode (JSON)', 'reverse-proxy'); ?>
            </label>
        </div>

        <div id="middleware-simple-mode">
            <div id="middleware-list">
                <!-- Middlewares will be loaded by JavaScript -->
            </div>
            <p>
                <button type="button" id="add-middleware" class="button">
                    <span class="dashicons dashicons-plus-alt2" style="vertical-align: middle;"></span>
                    <?php esc_html_e('Add Middleware', 'reverse-proxy'); ?>
                </button>
            </p>
        </div>

        <div id="middleware-advanced-mode" style="display: none;">
            <textarea id="middlewares-json" name="middlewares_json" rows="12" class="large-text code"
                      placeholder='[
  "ProxyHeaders",
  ["SetHost", "api.example.com"],
  ["Timeout", 30]
]'><?php
                $currentMiddlewares = $route['middlewares'] ?? [];
if (! empty($currentMiddlewares)) {
    echo esc_textarea(json_encode($currentMiddlewares, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}
?></textarea>
            <p class="description">
                <?php esc_html_e('Enter middleware configuration as a JSON array. Supported formats:', 'reverse-proxy'); ?>
            </p>
            <ul class="description" style="list-style: disc; margin-left: 20px;">
                <li><code>"MiddlewareName"</code> - <?php esc_html_e('Simple middleware without options', 'reverse-proxy'); ?></li>
                <li><code>["MiddlewareName", "arg1"]</code> - <?php esc_html_e('Middleware with single argument', 'reverse-proxy'); ?></li>
                <li><code>["MiddlewareName", "arg1", "arg2"]</code> - <?php esc_html_e('Middleware with multiple arguments', 'reverse-proxy'); ?></li>
            </ul>
        </div>

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
    padding: 12px;
    margin-bottom: 10px;
}
.middleware-item .middleware-select {
    min-width: 200px;
    margin-right: 10px;
}
.middleware-item .middleware-fields {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid #dcdcde;
}
.middleware-item .middleware-fields:empty {
    display: none;
}
.middleware-item .remove-middleware {
    color: #b32d2e;
}
.middleware-item .remove-middleware:hover {
    color: #a00;
    border-color: #a00;
}
#middlewares-json {
    font-family: Consolas, Monaco, monospace;
    font-size: 13px;
}
</style>
