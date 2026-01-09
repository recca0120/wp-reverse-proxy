/**
 * Reverse Proxy Admin JavaScript
 */
(function($) {
    'use strict';

    var middlewareIndex = 0;
    var middlewares = window.reverseProxyAdmin ? window.reverseProxyAdmin.middlewares : {};
    var existingMiddlewares = window.reverseProxyAdmin ? window.reverseProxyAdmin.existingMiddlewares : [];

    function init() {
        // Initialize middleware index
        middlewareIndex = $('#middleware-list .middleware-item').length;

        // Load existing middlewares if editing
        if (existingMiddlewares && existingMiddlewares.length > 0) {
            loadExistingMiddlewares();
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
            if (confirm(reverseProxyAdmin.i18n ? reverseProxyAdmin.i18n.confirmDelete : 'Are you sure you want to delete this route?')) {
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

        // Set the selected middleware
        $item.find('.middleware-select').val(name).trigger('change');

        // Fill in the field values after a short delay to let fields render
        setTimeout(function() {
            var $fields = $item.find('.middleware-fields input, .middleware-fields textarea');
            $fields.each(function(i) {
                if (args[i] !== undefined) {
                    $(this).val(args[i]);
                }
            });
        }, 10);

        middlewareIndex++;
    }

    function createMiddlewareItem(index, selectedName) {
        var html = '<div class="middleware-item" data-index="' + index + '">';
        html += '<select name="route[middlewares][' + index + '][name]" class="middleware-select">';
        html += '<option value="">-- Select Middleware --</option>';

        for (var name in middlewares) {
            if (middlewares.hasOwnProperty(name)) {
                var selected = (name === selectedName) ? ' selected' : '';
                html += '<option value="' + name + '"' + selected + '>' + middlewares[name].label + '</option>';
            }
        }

        html += '</select>';
        html += '<div class="middleware-fields"></div>';
        html += '<button type="button" class="button button-small remove-middleware">Remove</button>';
        html += '</div>';

        return html;
    }

    function reindexMiddlewares() {
        $('#middleware-list .middleware-item').each(function(index) {
            var $item = $(this);
            $item.attr('data-index', index);
            $item.find('.middleware-select').attr('name', 'route[middlewares][' + index + '][name]');
            $item.find('.middleware-fields input, .middleware-fields textarea').each(function() {
                var name = $(this).attr('name');
                if (name) {
                    name = name.replace(/\[middlewares\]\[\d+\]/, '[middlewares][' + index + ']');
                    $(this).attr('name', name);
                }
            });
        });
        middlewareIndex = $('#middleware-list .middleware-item').length;
    }

    function updateMiddlewareFields($select) {
        var name = $select.val();
        var $fieldsContainer = $select.siblings('.middleware-fields');
        var index = $select.closest('.middleware-item').data('index');
        $fieldsContainer.empty();

        if (!name || !middlewares[name]) {
            return;
        }

        var middleware = middlewares[name];
        var fields = middleware.fields || [];

        if (middleware.description) {
            $fieldsContainer.append('<p class="description">' + escapeHtml(middleware.description) + '</p>');
        }

        if (fields.length === 0) {
            $fieldsContainer.append('<p class="description"><em>No configuration required</em></p>');
            return;
        }

        fields.forEach(function(field, fieldIndex) {
            var inputId = 'mw-' + index + '-' + field.name;
            var inputName = 'route[middlewares][' + index + '][' + field.name + ']';
            var $wrapper = $('<div class="middleware-field-wrapper" style="margin-bottom: 8px;">');
            var $label = $('<label>').attr('for', inputId).text(field.label + (field.required ? ' *' : ''));
            var $input;

            if (field.type === 'textarea') {
                $input = $('<textarea>').attr({
                    id: inputId,
                    name: inputName,
                    rows: 3,
                    placeholder: field.placeholder || ''
                }).css('width', '100%').css('max-width', '400px');
            } else {
                $input = $('<input>').attr({
                    type: field.type || 'text',
                    id: inputId,
                    name: inputName,
                    placeholder: field.placeholder || ''
                }).css('width', '100%').css('max-width', '400px');

                if (field.required) {
                    $input.attr('required', true);
                }
                if (field.default !== undefined) {
                    $input.val(field.default);
                }
            }

            $wrapper.append($label).append('<br>').append($input);
            $fieldsContainer.append($wrapper);
        });
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    function syncToJson() {
        var middlewareArray = [];
        $('#middleware-list .middleware-item').each(function() {
            var $item = $(this);
            var name = $item.find('.middleware-select').val();
            if (!name) return;

            var middleware = middlewares[name];
            var fields = middleware ? middleware.fields || [] : [];
            var args = [];

            $item.find('.middleware-fields input, .middleware-fields textarea').each(function(i) {
                var val = $(this).val();
                if (val !== '' && val !== null) {
                    // Try to parse as number
                    if (!isNaN(val) && val !== '') {
                        val = parseFloat(val);
                    } else if (val === 'true') {
                        val = true;
                    } else if (val === 'false') {
                        val = false;
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
                    alert(response.data.message || 'Failed to toggle route status.');
                    $button.prop('disabled', false);
                }
            },
            error: function() {
                alert('An error occurred.');
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
                    alert(response.data.message || 'Failed to delete route.');
                }
            },
            error: function() {
                alert('An error occurred.');
            }
        });
    }

    function saveRoute($form) {
        var $submitBtn = $form.find('#submit');
        $submitBtn.prop('disabled', true).val('Saving...');

        var mode = $('input[name="middleware_mode"]:checked').val();

        // Build form data
        var formData = new FormData($form[0]);

        // If in advanced mode, parse JSON and use it
        if (mode === 'advanced') {
            try {
                var json = $('#middlewares-json').val();
                var middlewareArray = JSON.parse(json || '[]');
                formData.set('middlewares_json', JSON.stringify(middlewareArray));
            } catch (e) {
                alert('Invalid JSON format for middlewares.');
                $submitBtn.prop('disabled', false).val('Save Route');
                return;
            }
        } else {
            // In simple mode, collect middleware data
            var middlewareArray = collectSimpleModeMiddlewares();
            formData.set('middlewares_json', JSON.stringify(middlewareArray));
        }

        $.ajax({
            url: reverseProxyAdmin.ajaxUrl,
            method: 'POST',
            data: formDataToObject(formData),
            success: function(response) {
                if (response.success) {
                    window.location.href = response.data.redirect || (reverseProxyAdmin.adminUrl + '?page=reverse-proxy');
                } else {
                    alert(response.data.message || 'Failed to save route.');
                    $submitBtn.prop('disabled', false).val('Save Route');
                }
            },
            error: function() {
                alert('An error occurred while saving.');
                $submitBtn.prop('disabled', false).val('Save Route');
            }
        });
    }

    function collectSimpleModeMiddlewares() {
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

})(jQuery);
