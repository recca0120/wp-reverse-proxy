/**
 * Reverse Proxy Admin JavaScript
 */
(function($) {
    'use strict';

    var middlewareIndex = 0;
    var middlewares = window.reverseProxyAdmin ? window.reverseProxyAdmin.middlewares : {};

    function init() {
        // Initialize middleware index
        middlewareIndex = $('#middleware-list .middleware-item').length;

        // Add middleware button
        $('#add-middleware').on('click', addMiddleware);

        // Remove middleware button (delegated)
        $(document).on('click', '.remove-middleware', function() {
            $(this).closest('.middleware-item').remove();
        });

        // Middleware select change (delegated)
        $(document).on('change', '.middleware-select', function() {
            updateMiddlewareFields($(this));
        });

        // Mode toggle
        $('input[name="middleware_mode"]').on('change', function() {
            var mode = $(this).val();
            if (mode === 'simple') {
                $('#middleware-simple-mode').show();
                $('#middleware-advanced-mode').hide();
            } else {
                $('#middleware-simple-mode').hide();
                $('#middleware-advanced-mode').show();
                syncToJson();
            }
        });

        // Toggle route status
        $('.reverse-proxy-toggle').on('click', function() {
            var routeId = $(this).data('route-id');
            toggleRoute(routeId, $(this));
        });

        // Delete route
        $('.reverse-proxy-delete').on('click', function() {
            if (confirm('Are you sure you want to delete this route?')) {
                var routeId = $(this).data('route-id');
                deleteRoute(routeId, $(this).closest('tr'));
            }
        });

        // Form submission
        $('#reverse-proxy-route-form').on('submit', function(e) {
            e.preventDefault();
            saveRoute($(this));
        });

        // Initialize existing middleware fields
        $('.middleware-select').each(function() {
            updateMiddlewareFields($(this));
        });
    }

    function addMiddleware() {
        var template = $('#middleware-item-template').html();
        var html = template.replace(/\{\{index\}\}/g, middlewareIndex);
        $('#middleware-list').append(html);
        middlewareIndex++;
    }

    function updateMiddlewareFields($select) {
        var name = $select.val();
        var $fieldsContainer = $select.siblings('.middleware-fields');
        $fieldsContainer.empty();

        if (!name || !middlewares[name]) {
            return;
        }

        var middleware = middlewares[name];
        var fields = middleware.fields || [];

        if (middleware.description) {
            $fieldsContainer.append('<p class="description">' + middleware.description + '</p>');
        }

        fields.forEach(function(field) {
            var inputId = 'mw-' + $select.closest('.middleware-item').data('index') + '-' + field.name;
            var inputName = $select.attr('name').replace('[name]', '[' + field.name + ']');
            var $label = $('<label>').attr('for', inputId).text(field.label + (field.required ? ' *' : ''));
            var $input;

            if (field.type === 'textarea') {
                $input = $('<textarea>').attr({
                    id: inputId,
                    name: inputName,
                    rows: 3,
                    placeholder: field.placeholder || ''
                });
            } else {
                $input = $('<input>').attr({
                    type: field.type || 'text',
                    id: inputId,
                    name: inputName,
                    placeholder: field.placeholder || '',
                    required: field.required || false
                });
                if (field.default !== undefined) {
                    $input.val(field.default);
                }
            }

            $fieldsContainer.append($label).append($input);
        });
    }

    function syncToJson() {
        var middlewareArray = [];
        $('#middleware-list .middleware-item').each(function() {
            var $item = $(this);
            var name = $item.find('.middleware-select').val();
            if (!name) return;

            var args = [];
            $item.find('.middleware-fields input, .middleware-fields textarea').each(function() {
                var val = $(this).val();
                if (val) {
                    // Try to parse as number
                    if (!isNaN(val) && val !== '') {
                        val = parseFloat(val);
                    }
                    args.push(val);
                }
            });

            if (args.length === 0) {
                middlewareArray.push(name);
            } else if (args.length === 1) {
                middlewareArray.push([name, args[0]]);
            } else {
                middlewareArray.push([name].concat(args));
            }
        });

        $('#middlewares-json').val(JSON.stringify(middlewareArray, null, 2));
    }

    function toggleRoute(routeId, $button) {
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
                    alert('Failed to toggle route status.');
                }
            },
            error: function() {
                alert('An error occurred.');
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
                    });
                } else {
                    alert('Failed to delete route.');
                }
            },
            error: function() {
                alert('An error occurred.');
            }
        });
    }

    function saveRoute($form) {
        var mode = $('input[name="middleware_mode"]:checked').val();
        var formData = $form.serialize();

        // If in advanced mode, parse JSON and add to form data
        if (mode === 'advanced') {
            try {
                var json = $('#middlewares-json').val();
                var middlewareArray = JSON.parse(json || '[]');
                // Convert to form data format
                formData += '&middlewares_json=' + encodeURIComponent(JSON.stringify(middlewareArray));
            } catch (e) {
                alert('Invalid JSON format for middlewares.');
                return;
            }
        }

        $.ajax({
            url: reverseProxyAdmin.ajaxUrl,
            method: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    window.location.href = response.data.redirect || (reverseProxyAdmin.ajaxUrl.replace('admin-ajax.php', 'admin.php?page=reverse-proxy'));
                } else {
                    alert(response.data.message || 'Failed to save route.');
                }
            },
            error: function() {
                alert('An error occurred while saving.');
            }
        });
    }

    $(document).ready(init);

})(jQuery);
