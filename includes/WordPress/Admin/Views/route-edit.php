<?php
/**
 * Route edit view
 *
 * @var array|null $route
 * @var array $middlewares
 */

if (!defined('ABSPATH')) {
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
        <?php if (!$isNew) : ?>
            <input type="hidden" name="route[id]" value="<?php echo esc_attr($route['id']); ?>">
        <?php endif; ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="route-path"><?php esc_html_e('Path Pattern', 'reverse-proxy'); ?></label>
                </th>
                <td>
                    <input type="text" id="route-path" name="route[path]" class="regular-text"
                           value="<?php echo esc_attr($route['path'] ?? ''); ?>"
                           placeholder="/api/*" required>
                    <p class="description">
                        <?php esc_html_e('Use * as wildcard. Examples: /api/*, /blog/posts/*', 'reverse-proxy'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="route-target"><?php esc_html_e('Target URL', 'reverse-proxy'); ?></label>
                </th>
                <td>
                    <input type="url" id="route-target" name="route[target]" class="regular-text"
                           value="<?php echo esc_attr($route['target'] ?? ''); ?>"
                           placeholder="https://api.example.com" required>
                    <p class="description">
                        <?php esc_html_e('The backend server URL to proxy requests to.', 'reverse-proxy'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('HTTP Methods', 'reverse-proxy'); ?></th>
                <td>
                    <?php
                    $allMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];
$selectedMethods = $route['methods'] ?? [];
foreach ($allMethods as $method) :
    ?>
                        <label style="margin-right: 15px;">
                            <input type="checkbox" name="route[methods][]" value="<?php echo esc_attr($method); ?>"
                                <?php checked(in_array($method, $selectedMethods, true)); ?>>
                            <?php echo esc_html($method); ?>
                        </label>
                    <?php endforeach; ?>
                    <p class="description">
                        <?php esc_html_e('Leave all unchecked to allow all methods.', 'reverse-proxy'); ?>
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
                            <?php checked(!empty($route['enabled'])); ?>>
                        <?php esc_html_e('Enable this route', 'reverse-proxy'); ?>
                    </label>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e('Middlewares', 'reverse-proxy'); ?></h2>

        <div id="middleware-mode-toggle" style="margin-bottom: 15px;">
            <label>
                <input type="radio" name="middleware_mode" value="simple" checked>
                <?php esc_html_e('Simple Mode', 'reverse-proxy'); ?>
            </label>
            <label style="margin-left: 15px;">
                <input type="radio" name="middleware_mode" value="advanced">
                <?php esc_html_e('Advanced Mode (JSON)', 'reverse-proxy'); ?>
            </label>
        </div>

        <div id="middleware-simple-mode">
            <div id="middleware-list">
                <?php
                $routeMiddlewares = $route['middlewares'] ?? [];
if (!empty($routeMiddlewares)) :
    foreach ($routeMiddlewares as $index => $mw) :
        $mwName = is_string($mw) ? $mw : ($mw[0] ?? $mw['name'] ?? '');
        ?>
                        <div class="middleware-item" data-index="<?php echo esc_attr($index); ?>">
                            <select name="route[middlewares][<?php echo esc_attr($index); ?>][name]" class="middleware-select">
                                <option value=""><?php esc_html_e('Select Middleware', 'reverse-proxy'); ?></option>
                                <?php foreach ($middlewares as $name => $info) : ?>
                                    <option value="<?php echo esc_attr($name); ?>" <?php selected($mwName, $name); ?>>
                                        <?php echo esc_html($info['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="middleware-fields"></div>
                            <button type="button" class="button button-small remove-middleware">
                                <?php esc_html_e('Remove', 'reverse-proxy'); ?>
                            </button>
                        </div>
                    <?php endforeach;
endif; ?>
            </div>
            <button type="button" id="add-middleware" class="button">
                <?php esc_html_e('Add Middleware', 'reverse-proxy'); ?>
            </button>
        </div>

        <div id="middleware-advanced-mode" style="display: none;">
            <textarea id="middlewares-json" name="middlewares_json" rows="10" class="large-text code"
                      placeholder='[&#10;  "ProxyHeaders",&#10;  ["SetHost", "api.example.com"],&#10;  ["Timeout", 30]&#10;]'><?php
echo esc_textarea(json_encode($route['middlewares'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
?></textarea>
            <p class="description">
                <?php esc_html_e('Enter middleware configuration as JSON array.', 'reverse-proxy'); ?>
            </p>
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

<script type="text/template" id="middleware-item-template">
    <div class="middleware-item" data-index="{{index}}">
        <select name="route[middlewares][{{index}}][name]" class="middleware-select">
            <option value=""><?php esc_html_e('Select Middleware', 'reverse-proxy'); ?></option>
            <?php foreach ($middlewares as $name => $info) : ?>
                <option value="<?php echo esc_attr($name); ?>">
                    <?php echo esc_html($info['label']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <div class="middleware-fields"></div>
        <button type="button" class="button button-small remove-middleware">
            <?php esc_html_e('Remove', 'reverse-proxy'); ?>
        </button>
    </div>
</script>
