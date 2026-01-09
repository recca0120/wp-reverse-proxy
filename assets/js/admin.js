/**
 * Reverse Proxy Admin JavaScript
 */
(function($, wp) {
    'use strict';

    var __ = wp.i18n.__;
    var middlewareIndex = 0;
    var middlewares = window.reverseProxyAdmin ? window.reverseProxyAdmin.middlewares : {};
    var existingMiddlewares = window.reverseProxyAdmin ? window.reverseProxyAdmin.existingMiddlewares : [];

    function init() {
        // Initialize middleware index
        middlewareIndex = $('#middleware-list .middleware-item').length;

        // Load existing middlewares if editing, or add default for new route
        if (existingMiddlewares && existingMiddlewares.length > 0) {
            loadExistingMiddlewares();
        } else if ($('#middleware-list').length > 0) {
            // New route: add ProxyHeaders as default
            addMiddlewareWithValues('ProxyHeaders', []);
        }

        // Add middleware button
        $('#add-middleware').on('click', addMiddleware);

        // Remove middleware button (delegated)
        $(document).on('click', '.remove-middleware', function() {
            $(this).closest('.middleware-item').remove();
            reindexMiddlewares();
        });

        // Middleware select change (delegated)
        $(document).on('change', '.middleware-select', function() {
            updateMiddlewareFields($(this));
        });

        // Enable drag-and-drop sorting for middlewares
        if ($('#middleware-list').length > 0 && $.fn.sortable) {
            $('#middleware-list').sortable({
                handle: '.middleware-drag-handle',
                placeholder: 'middleware-item-placeholder',
                update: function() {
                    reindexMiddlewares();
                }
            });
        }

        // Toggle route status
        $('.reverse-proxy-toggle').on('click', function() {
            var routeId = $(this).data('route-id');
            toggleRoute(routeId, $(this));
        });

        // Delete route
        $('.reverse-proxy-delete').on('click', function() {
            if (confirm(__('Are you sure you want to delete this route?', 'reverse-proxy'))) {
                var routeId = $(this).data('route-id');
                deleteRoute(routeId, $(this).closest('tr'));
            }
        });

        // Form submission
        $('#reverse-proxy-route-form').on('submit', function(e) {
            e.preventDefault();
            saveRoute($(this));
        });
    }

    function loadExistingMiddlewares() {
        $('#middleware-list').empty();
        middlewareIndex = 0;

        existingMiddlewares.forEach(function(mw) {
            var name, args;

            if (typeof mw === 'string') {
                name = mw;
                args = [];
            } else if (Array.isArray(mw)) {
                name = mw[0];
                args = mw.slice(1);
            } else if (typeof mw === 'object' && mw.name) {
                name = mw.name;
                args = mw.args || mw.options || [];
                if (!Array.isArray(args)) {
                    args = [args];
                }
            } else {
                return;
            }

            addMiddlewareWithValues(name, args);
        });
    }

    function addMiddleware() {
        var html = createMiddlewareItem(middlewareIndex);
        $('#middleware-list').append(html);
        middlewareIndex++;
    }

    function addMiddlewareWithValues(name, args) {
        var html = createMiddlewareItem(middlewareIndex, name);
        var $item = $(html);
        $('#middleware-list').append($item);

        // Set the selected middleware and populate fields with values
        var $select = $item.find('.middleware-select');
        $select.val(name);
        updateMiddlewareFields($select, args);

        middlewareIndex++;
    }

    function createMiddlewareItem(index, selectedName) {
        var html = '<div class="middleware-item" data-index="' + index + '">';
        html += '<div class="middleware-header">';
        html += '<span class="middleware-drag-handle dashicons dashicons-move" title="' + __('Drag to reorder', 'reverse-proxy') + '"></span>';
        html += '<select name="route[middlewares][' + index + '][name]" class="middleware-select">';
        html += '<option value="">' + __('-- Select Middleware --', 'reverse-proxy') + '</option>';

        // Sort middleware names alphabetically
        var sortedNames = Object.keys(middlewares).sort(function(a, b) {
            return middlewares[a].label.localeCompare(middlewares[b].label);
        });

        sortedNames.forEach(function(name) {
            var selected = (name === selectedName) ? ' selected' : '';
            html += '<option value="' + name + '"' + selected + '>' + middlewares[name].label + '</option>';
        });

        html += '</select>';
        html += '<button type="button" class="button button-small button-link-delete remove-middleware">' + __('Remove', 'reverse-proxy') + '</button>';
        html += '</div>';
        html += '<div class="middleware-body"></div>';
        html += '</div>';

        return html;
    }

    function reindexMiddlewares() {
        $('#middleware-list .middleware-item').each(function(index) {
            var $item = $(this);
            $item.attr('data-index', index);
            $item.find('.middleware-select').attr('name', 'route[middlewares][' + index + '][name]');
            $item.find('.middleware-body input, .middleware-body textarea').each(function() {
                var name = $(this).attr('name');
                if (name) {
                    name = name.replace(/\[middlewares\]\[\d+\]/, '[middlewares][' + index + ']');
                    $(this).attr('name', name);
                }
            });
        });
        middlewareIndex = $('#middleware-list .middleware-item').length;
    }

    function updateMiddlewareFields($select, existingValues) {
        var name = $select.val();
        var $item = $select.closest('.middleware-item');
        var $body = $item.find('.middleware-body');
        var index = $item.data('index');
        var values = existingValues || [];
        $body.empty().addClass('empty');

        if (!name || !middlewares[name]) {
            return;
        }

        var middleware = middlewares[name];
        var fields = middleware.fields || [];

        if (middleware.description) {
            $body.append('<p class="description">' + escapeHtml(middleware.description) + '</p>');
            $body.removeClass('empty');
        }

        if (fields.length === 0) {
            return;
        }

        $body.removeClass('empty');
        var $grid = $('<div class="middleware-fields-grid">');

        fields.forEach(function(field, fieldIndex) {
            var inputId = 'mw-' + index + '-' + field.name;
            var inputName = 'route[middlewares][' + index + '][' + field.name + ']';
            var $wrapper = $('<div class="middleware-field-wrapper">');
            var $label = $('<label>').attr('for', inputId).text(field.label + (field.required ? ' *' : ''));
            var $input;

            // Determine the value: existing value > default > empty
            var value = values[fieldIndex] !== undefined ? values[fieldIndex] : field.default;

            if (field.type === 'textarea') {
                $input = $('<textarea>').attr({
                    id: inputId,
                    name: inputName,
                    rows: 3,
                    placeholder: field.placeholder || ''
                }).addClass('large-text');
                if (value !== undefined) {
                    $input.val(value);
                }
            } else {
                var inputClass = field.type === 'number' ? 'small-text' : 'regular-text';
                $input = $('<input>').attr({
                    type: field.type || 'text',
                    id: inputId,
                    name: inputName,
                    placeholder: field.placeholder || ''
                }).addClass(inputClass);

                if (field.required) {
                    $input.attr('required', true);
                }
                if (value !== undefined) {
                    $input.val(value);
                }
            }

            $wrapper.append($label).append($input);
            $grid.append($wrapper);
        });

        $body.append($grid);
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    function toggleRoute(routeId, $button) {
        $button.prop('disabled', true);

        $.ajax({
            url: reverseProxyAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'reverse_proxy_toggle_route',
                nonce: reverseProxyAdmin.nonce,
                route_id: routeId
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || __('Failed to toggle route status.', 'reverse-proxy'));
                    $button.prop('disabled', false);
                }
            },
            error: function() {
                alert(__('An error occurred.', 'reverse-proxy'));
                $button.prop('disabled', false);
            }
        });
    }

    function deleteRoute(routeId, $row) {
        $.ajax({
            url: reverseProxyAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'reverse_proxy_delete_route',
                nonce: reverseProxyAdmin.nonce,
                route_id: routeId
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(function() {
                        $(this).remove();
                        // Check if table is now empty
                        if ($('.wp-list-table tbody tr').length === 0) {
                            location.reload();
                        }
                    });
                } else {
                    alert(response.data.message || __('Failed to delete route.', 'reverse-proxy'));
                }
            },
            error: function() {
                alert(__('An error occurred.', 'reverse-proxy'));
            }
        });
    }

    function saveRoute($form) {
        var $submitBtn = $form.find('#submit');
        var originalText = $submitBtn.val();
        $submitBtn.prop('disabled', true).val(__('Saving...', 'reverse-proxy'));

        // Build form data
        var formData = new FormData($form[0]);

        // Collect middleware data
        var middlewareArray = collectMiddlewares();
        formData.set('middlewares_json', JSON.stringify(middlewareArray));

        $.ajax({
            url: reverseProxyAdmin.ajaxUrl,
            method: 'POST',
            data: formDataToObject(formData),
            success: function(response) {
                if (response.success) {
                    window.location.href = response.data.redirect || (reverseProxyAdmin.adminUrl + '?page=reverse-proxy');
                } else {
                    alert(response.data.message || __('Failed to save route.', 'reverse-proxy'));
                    $submitBtn.prop('disabled', false).val(originalText);
                }
            },
            error: function() {
                alert(__('An error occurred while saving.', 'reverse-proxy'));
                $submitBtn.prop('disabled', false).val(originalText);
            }
        });
    }

    function collectMiddlewares() {
        var middlewareArray = [];
        $('#middleware-list .middleware-item').each(function() {
            var $item = $(this);
            var name = $item.find('.middleware-select').val();
            if (!name) return;

            var middleware = middlewares[name];
            var fields = middleware ? middleware.fields || [] : [];
            var args = [];

            fields.forEach(function(field) {
                var $input = $item.find('[name*="[' + field.name + ']"]');
                var val = $input.val();

                if (val !== '' && val !== null && val !== undefined) {
                    // Type conversion
                    if (field.type === 'number' && !isNaN(val)) {
                        val = parseFloat(val);
                    } else if (val === 'true') {
                        val = true;
                    } else if (val === 'false') {
                        val = false;
                    }
                    args.push(val);
                } else if (field.required) {
                    // Skip this middleware if required field is empty
                    return;
                }
            });

            if (fields.length === 0) {
                middlewareArray.push(name);
            } else if (args.length === 1) {
                middlewareArray.push([name, args[0]]);
            } else if (args.length > 1) {
                middlewareArray.push([name].concat(args));
            } else {
                middlewareArray.push(name);
            }
        });

        return middlewareArray;
    }

    function formDataToObject(formData) {
        var obj = {};
        formData.forEach(function(value, key) {
            // Handle array notation in keys
            if (key.indexOf('[') > -1) {
                // Skip middleware array fields in favor of middlewares_json
                if (key.indexOf('[middlewares]') > -1 && key !== 'middlewares_json') {
                    return;
                }
            }
            obj[key] = value;
        });
        return obj;
    }

    $(document).ready(init);

})(jQuery, wp);
