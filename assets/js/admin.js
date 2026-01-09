/**
 * Reverse Proxy Admin JavaScript
 */
(function($, wp) {
    'use strict';

    var __ = wp.i18n.__;
    var middlewareItemTemplate = wp.template('middleware-item');
    var middlewareIndex = 0;
    var middlewares = window.reverseProxyAdmin ? window.reverseProxyAdmin.middlewares : {};
    var existingMiddlewares = window.reverseProxyAdmin ? window.reverseProxyAdmin.existingMiddlewares : [];

    // Field renderers registry
    var fieldRenderers = {
        textarea: renderTextarea,
        checkbox: renderCheckbox,
        select: renderSelect,
        checkboxes: renderCheckboxes,
        repeater: renderRepeater,
        keyvalue: renderKeyValue
    };

    // Field value collectors registry
    var fieldCollectors = {
        checkbox: collectCheckbox,
        checkboxes: collectCheckboxes,
        repeater: collectRepeater,
        keyvalue: collectKeyValue,
        select: collectSelect,
        number: collectNumber
    };

    function init() {
        middlewareIndex = $('#middleware-list .middleware-item').length;

        if (existingMiddlewares && existingMiddlewares.length > 0) {
            loadExistingMiddlewares();
        } else if ($('#middleware-list').length > 0) {
            addMiddlewareWithValues('ProxyHeaders', []);
        }

        bindEvents();
    }

    function bindEvents() {
        $('#add-middleware').on('click', addMiddleware);

        $(document)
            .on('click', '.remove-middleware', handleRemoveMiddleware)
            .on('change', '.middleware-select', handleMiddlewareChange)
            .on('click', '.dynamic-list-add', handleDynamicListAdd)
            .on('click', '.dynamic-list-remove', handleDynamicListRemove);

        if ($('#middleware-list').length > 0 && $.fn.sortable) {
            $('#middleware-list').sortable({
                handle: '.middleware-drag-handle',
                placeholder: 'middleware-item-placeholder',
                update: reindexMiddlewares
            });
        }

        $('.reverse-proxy-toggle').on('click', function() {
            toggleRoute($(this).data('route-id'), $(this));
        });

        $('.reverse-proxy-delete').on('click', function() {
            if (confirm(__('Are you sure you want to delete this route?', 'reverse-proxy'))) {
                deleteRoute($(this).data('route-id'), $(this).closest('tr'));
            }
        });

        $('#reverse-proxy-route-form').on('submit', function(e) {
            e.preventDefault();
            saveRoute($(this));
        });
    }

    function handleRemoveMiddleware() {
        $(this).closest('.middleware-item').remove();
        reindexMiddlewares();
    }

    function handleMiddlewareChange() {
        updateMiddlewareFields($(this));
    }

    function handleDynamicListAdd() {
        var $container = $(this).closest('.dynamic-list-container');
        var $items = $container.find('.dynamic-list-items');
        var type = $container.data('type');
        var baseName = $container.data('name');

        if (type === 'keyvalue') {
            $items.append(createKeyValueItem(baseName, '', ''));
        } else {
            var $first = $items.find('.dynamic-list-item:first input');
            var inputType = $first.attr('type') || 'text';
            var placeholder = $first.attr('placeholder') || '';
            $items.append(createRepeaterItem(baseName, inputType, placeholder, ''));
        }
    }

    function handleDynamicListRemove() {
        var $items = $(this).closest('.dynamic-list-items');
        if ($items.find('.dynamic-list-item').length > 1) {
            $(this).closest('.dynamic-list-item').remove();
        } else {
            $(this).siblings('input').val('');
        }
    }

    function parseMiddleware(mw) {
        if (typeof mw === 'string') {
            return { name: mw, args: [] };
        }
        if (Array.isArray(mw)) {
            return { name: mw[0], args: mw.slice(1) };
        }
        if (typeof mw === 'object' && mw.name) {
            var args = mw.args || mw.options || [];
            return { name: mw.name, args: Array.isArray(args) ? args : [args] };
        }
        return null;
    }

    function loadExistingMiddlewares() {
        $('#middleware-list').empty();
        middlewareIndex = 0;

        existingMiddlewares.forEach(function(mw) {
            var parsed = parseMiddleware(mw);
            if (parsed) {
                addMiddlewareWithValues(parsed.name, parsed.args);
            }
        });
    }

    function addMiddleware() {
        $('#middleware-list').append(createMiddlewareItem(middlewareIndex));
        middlewareIndex++;
    }

    function addMiddlewareWithValues(name, args) {
        var $item = $(createMiddlewareItem(middlewareIndex, name));
        $('#middleware-list').append($item);

        var $select = $item.find('.middleware-select');
        $select.val(name);
        updateMiddlewareFields($select, args);

        middlewareIndex++;
    }

    function createMiddlewareItem(index, selectedName) {
        var sortedMiddlewares = {};
        Object.keys(middlewares).sort(function(a, b) {
            return middlewares[a].label.localeCompare(middlewares[b].label);
        }).forEach(function(name) {
            sortedMiddlewares[name] = middlewares[name];
        });

        return middlewareItemTemplate({
            index: index,
            middlewares: sortedMiddlewares,
            selected: selectedName || ''
        });
    }

    function reindexMiddlewares() {
        $('#middleware-list .middleware-item').each(function(index) {
            var $item = $(this);
            $item.attr('data-index', index);
            $item.find('.middleware-select').attr('name', 'route[middlewares][' + index + '][name]');
            $item.find('.middleware-body input, .middleware-body textarea, .middleware-body select').each(function() {
                var name = $(this).attr('name');
                if (name) {
                    $(this).attr('name', name.replace(/\[middlewares\]\[\d+\]/, '[middlewares][' + index + ']'));
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
            var context = {
                inputId: 'mw-' + index + '-' + field.name,
                inputName: 'route[middlewares][' + index + '][' + field.name + ']',
                value: values[fieldIndex] !== undefined ? values[fieldIndex] : field.default,
                field: field
            };

            var renderer = fieldRenderers[field.type] || renderDefaultInput;
            $grid.append(renderer(context));
        });

        $body.append($grid);
    }

    // Field Renderers
    function renderTextarea(ctx) {
        var $wrapper = createFieldWrapper(ctx.field);
        var $input = $('<textarea>').attr({
            id: ctx.inputId,
            name: ctx.inputName,
            rows: 3,
            placeholder: ctx.field.placeholder || ''
        }).addClass('large-text');

        if (ctx.value !== undefined) {
            $input.val(ctx.value);
        }

        return $wrapper.append(createLabel(ctx)).append($input);
    }

    function renderCheckbox(ctx) {
        var $wrapper = $('<div class="middleware-field-wrapper middleware-checkbox-wrapper">');
        var $input = $('<input>').attr({
            type: 'checkbox',
            id: ctx.inputId,
            name: ctx.inputName,
            value: '1'
        });

        if (isTruthy(ctx.value)) {
            $input.prop('checked', true);
        }

        var $label = $('<label>').attr('for', ctx.inputId).append($input).append(' ' + ctx.field.label);
        return $wrapper.append($label);
    }

    function renderSelect(ctx) {
        var $wrapper = createFieldWrapper(ctx.field);
        var $input = $('<select>').attr({ id: ctx.inputId, name: ctx.inputName });

        parseOptions(ctx.field.options).forEach(function(opt) {
            var $option = $('<option>').val(opt.value).text(opt.label);
            if (ctx.value !== undefined && String(ctx.value) === String(opt.value)) {
                $option.prop('selected', true);
            }
            $input.append($option);
        });

        return $wrapper.append(createLabel(ctx)).append($input);
    }

    function renderCheckboxes(ctx) {
        var $wrapper = $('<div class="middleware-field-wrapper middleware-checkboxes-wrapper">');
        $wrapper.append(createLabel(ctx));

        var $group = $('<div class="checkbox-group">');
        var selectedValues = parseArrayValue(ctx.value);

        parseOptions(ctx.field.options).forEach(function(opt) {
            var cbId = ctx.inputId + '-' + opt.value;
            var $cb = $('<input>').attr({
                type: 'checkbox',
                id: cbId,
                name: ctx.inputName + '[]',
                value: opt.value
            });

            if (selectedValues.indexOf(opt.value) !== -1) {
                $cb.prop('checked', true);
            }

            $group.append($('<label>').attr('for', cbId).append($cb).append(' ' + opt.label));
        });

        return $wrapper.append($group);
    }

    function renderRepeater(ctx) {
        var $wrapper = $('<div class="middleware-field-wrapper middleware-repeater-wrapper">');
        $wrapper.append(createLabel(ctx));

        var $container = $('<div class="dynamic-list-container" data-type="repeater">')
            .attr('data-name', ctx.inputName);
        var $items = $('<div class="dynamic-list-items">');

        var inputType = ctx.field.inputType || 'text';
        var placeholder = ctx.field.placeholder || '';
        var values = parseArrayValue(ctx.value);

        if (values.length === 0) {
            values = [''];
        }

        values.forEach(function(val) {
            $items.append(createRepeaterItem(ctx.inputName, inputType, placeholder, val));
        });

        $container.append($items).append(createAddButton());
        return $wrapper.append($container);
    }

    function renderKeyValue(ctx) {
        var $wrapper = $('<div class="middleware-field-wrapper middleware-keyvalue-wrapper">');
        $wrapper.append(createLabel(ctx));

        var $container = $('<div class="dynamic-list-container" data-type="keyvalue">')
            .attr('data-name', ctx.inputName);

        // Header
        var $header = $('<div class="keyvalue-header">');
        $header.append($('<span class="keyvalue-key-header">').text(ctx.field.keyLabel || 'Key'));
        $header.append($('<span class="keyvalue-value-header">').text(ctx.field.valueLabel || 'Value'));
        $header.append($('<span class="keyvalue-action-header">'));
        $container.append($header);

        var $items = $('<div class="dynamic-list-items">');
        var entries = ctx.value && typeof ctx.value === 'object' ? Object.keys(ctx.value) : [];

        if (entries.length === 0) {
            $items.append(createKeyValueItem(ctx.inputName, '', ''));
        } else {
            entries.forEach(function(key) {
                $items.append(createKeyValueItem(ctx.inputName, key, ctx.value[key]));
            });
        }

        $container.append($items).append(createAddButton());
        return $wrapper.append($container);
    }

    function renderDefaultInput(ctx) {
        var $wrapper = createFieldWrapper(ctx.field);
        var inputClass = ctx.field.type === 'number' ? 'small-text' : 'regular-text';

        var $input = $('<input>').attr({
            type: ctx.field.type || 'text',
            id: ctx.inputId,
            name: ctx.inputName,
            placeholder: ctx.field.placeholder || ''
        }).addClass(inputClass);

        if (ctx.field.required) {
            $input.attr('required', true);
        }
        if (ctx.value !== undefined) {
            $input.val(ctx.value);
        }

        return $wrapper.append(createLabel(ctx)).append($input);
    }

    // Helper functions for renderers
    function createFieldWrapper() {
        return $('<div class="middleware-field-wrapper">');
    }

    function createLabel(ctx) {
        return $('<label>').attr('for', ctx.inputId)
            .text(ctx.field.label + (ctx.field.required ? ' *' : ''));
    }

    function createAddButton() {
        return $('<button type="button" class="button button-small dynamic-list-add">')
            .text(__('+ Add', 'reverse-proxy'));
    }

    function createRemoveButton() {
        return $('<button type="button" class="button button-small button-link-delete dynamic-list-remove">')
            .html('<span class="dashicons dashicons-no-alt"></span>');
    }

    function createRepeaterItem(baseName, inputType, placeholder, value) {
        var $row = $('<div class="dynamic-list-item">');
        var inputClass = inputType === 'number' ? 'small-text' : 'regular-text';

        var $input = $('<input>').attr({
            type: inputType,
            name: baseName + '[]',
            placeholder: placeholder
        }).addClass(inputClass).val(value || '');

        return $row.append($input).append(createRemoveButton());
    }

    function createKeyValueItem(baseName, key, value) {
        var $row = $('<div class="dynamic-list-item keyvalue-item">');

        var $keyInput = $('<input>').attr({
            type: 'text',
            name: baseName + '[keys][]'
        }).addClass('regular-text keyvalue-key').val(key || '');

        var $valueInput = $('<input>').attr({
            type: 'text',
            name: baseName + '[values][]'
        }).addClass('regular-text keyvalue-value').val(value || '');

        return $row.append($keyInput).append($valueInput).append(createRemoveButton());
    }

    // Utility functions
    function isTruthy(value) {
        return value === true || value === 'true' || value === '1' || value === 1;
    }

    function parseOptions(options) {
        if (!options) return [];
        if (Array.isArray(options)) return options;

        return options.split(',').map(function(opt) {
            var parts = opt.split(':');
            return {
                value: parts[0].trim(),
                label: (parts[1] || parts[0]).trim()
            };
        });
    }

    function parseArrayValue(value) {
        if (!value) return [];
        if (Array.isArray(value)) return value.map(String);
        if (typeof value === 'string') {
            return value.split(',').map(function(v) { return v.trim(); }).filter(Boolean);
        }
        return [String(value)];
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    // AJAX functions
    function makeRouteAjaxRequest(action, routeId, options) {
        var settings = $.extend({
            onSuccess: function() { location.reload(); },
            onError: function() {},
            errorMessage: __('An error occurred.', 'reverse-proxy')
        }, options);

        $.ajax({
            url: reverseProxyAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: action,
                nonce: reverseProxyAdmin.nonce,
                route_id: routeId
            },
            success: function(response) {
                if (response.success) {
                    settings.onSuccess(response);
                } else {
                    alert(response.data.message || settings.errorMessage);
                    settings.onError();
                }
            },
            error: function() {
                alert(settings.errorMessage);
                settings.onError();
            }
        });
    }

    function toggleRoute(routeId, $button) {
        $button.prop('disabled', true);
        makeRouteAjaxRequest('reverse_proxy_toggle_route', routeId, {
            errorMessage: __('Failed to toggle route status.', 'reverse-proxy'),
            onError: function() { $button.prop('disabled', false); }
        });
    }

    function deleteRoute(routeId, $row) {
        makeRouteAjaxRequest('reverse_proxy_delete_route', routeId, {
            errorMessage: __('Failed to delete route.', 'reverse-proxy'),
            onSuccess: function() {
                $row.fadeOut(function() {
                    $(this).remove();
                    if ($('.wp-list-table tbody tr').length === 0) {
                        location.reload();
                    }
                });
            }
        });
    }

    function saveRoute($form) {
        var $submitBtn = $form.find('#submit');
        var originalText = $submitBtn.val();
        $submitBtn.prop('disabled', true).val(__('Saving...', 'reverse-proxy'));

        var formData = new FormData($form[0]);
        formData.set('middlewares_json', JSON.stringify(collectMiddlewares()));

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
        var result = [];

        $('#middleware-list .middleware-item').each(function() {
            var $item = $(this);
            var name = $item.find('.middleware-select').val();
            if (!name) return;

            var middleware = middlewares[name];
            var fields = middleware ? middleware.fields || [] : [];
            var args = [];
            var valid = true;

            fields.forEach(function(field) {
                if (!valid) return;

                var collector = fieldCollectors[field.type] || collectDefault;
                var val = collector($item, field);

                if (val !== null && val !== undefined) {
                    args.push(val);
                } else if (field.required) {
                    valid = false;
                }
            });

            if (!valid) return;

            if (fields.length === 0) {
                result.push(name);
            } else if (args.length === 0) {
                result.push(name);
            } else if (args.length === 1) {
                result.push([name, args[0]]);
            } else {
                result.push([name].concat(args));
            }
        });

        return result;
    }

    // Field value collectors
    function collectCheckbox($item, field) {
        return $item.find('[name*="[' + field.name + ']"]').is(':checked');
    }

    function collectCheckboxes($item, field) {
        var checked = [];
        $item.find('[name*="[' + field.name + ']"]:checked').each(function() {
            checked.push($(this).val());
        });
        return checked.length > 0 ? checked : null;
    }

    function collectRepeater($item, field) {
        var values = [];
        var isNumber = field.inputType === 'number';

        $item.find('.middleware-repeater-wrapper .dynamic-list-item input').each(function() {
            var v = $(this).val();
            if (v !== '' && v !== null) {
                values.push(isNumber && !isNaN(v) ? parseFloat(v) : v);
            }
        });

        return values.length > 0 ? values : null;
    }

    function collectKeyValue($item) {
        var obj = {};

        $item.find('.middleware-keyvalue-wrapper .dynamic-list-item').each(function() {
            var k = $(this).find('.keyvalue-key').val();
            var v = $(this).find('.keyvalue-value').val();
            if (k) {
                obj[k] = v || '';
            }
        });

        return Object.keys(obj).length > 0 ? obj : null;
    }

    function collectSelect($item, field) {
        var val = $item.find('[name*="[' + field.name + ']"]').val();
        return val !== '' ? val : null;
    }

    function collectNumber($item, field) {
        var val = $item.find('[name*="[' + field.name + ']"]').val();
        if (val !== '' && val !== null && !isNaN(val)) {
            return parseFloat(val);
        }
        return field.default !== undefined ? field.default : null;
    }

    function collectDefault($item, field) {
        var val = $item.find('[name*="[' + field.name + ']"]').val();
        if (val !== '' && val !== null && val !== undefined) {
            if (val === 'true') return true;
            if (val === 'false') return false;
            return val;
        }
        return null;
    }

    function formDataToObject(formData) {
        var obj = {};
        formData.forEach(function(value, key) {
            if (key.indexOf('[middlewares]') === -1 || key === 'middlewares_json') {
                obj[key] = value;
            }
        });
        return obj;
    }

    $(document).ready(init);

})(jQuery, wp);
