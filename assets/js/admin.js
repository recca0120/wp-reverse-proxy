/**
 * Reverse Proxy Admin JavaScript (Vanilla JS)
 */
(function(wp) {
    'use strict';

    var __ = wp.i18n.__;
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
        keyvalue: renderKeyValue,
        json: renderJson
    };

    // Field value collectors registry
    var fieldCollectors = {
        checkbox: collectCheckbox,
        checkboxes: collectCheckboxes,
        repeater: collectRepeater,
        keyvalue: collectKeyValue,
        select: collectSelect,
        number: collectNumber,
        json: collectJson
    };

    // DOM Helper functions
    function $(selector, context) {
        return (context || document).querySelector(selector);
    }

    function $$(selector, context) {
        return Array.from((context || document).querySelectorAll(selector));
    }

    function createElement(tag, attrs, children) {
        var el = document.createElement(tag);
        if (attrs) {
            Object.keys(attrs).forEach(function(key) {
                if (key === 'className') {
                    el.className = attrs[key];
                } else if (key === 'dataset') {
                    Object.keys(attrs[key]).forEach(function(dataKey) {
                        el.dataset[dataKey] = attrs[key][dataKey];
                    });
                } else if (key === 'textContent' || key === 'innerHTML') {
                    el[key] = attrs[key];
                } else {
                    el.setAttribute(key, attrs[key]);
                }
            });
        }
        if (children) {
            if (Array.isArray(children)) {
                children.forEach(function(child) {
                    if (child) el.appendChild(typeof child === 'string' ? document.createTextNode(child) : child);
                });
            } else if (typeof children === 'string') {
                el.textContent = children;
            } else {
                el.appendChild(children);
            }
        }
        return el;
    }

    function on(element, event, selectorOrHandler, handler) {
        if (typeof selectorOrHandler === 'function') {
            element.addEventListener(event, selectorOrHandler);
        } else {
            element.addEventListener(event, function(e) {
                var target = e.target.closest(selectorOrHandler);
                if (target && element.contains(target)) {
                    handler.call(target, e);
                }
            });
        }
    }

    function fadeOut(element, callback) {
        element.style.transition = 'opacity 0.4s';
        element.style.opacity = '0';
        element.addEventListener('transitionend', function handler() {
            element.removeEventListener('transitionend', handler);
            if (callback) callback();
        });
    }

    // AJAX helper using fetch
    function ajax(options) {
        var url = options.url;
        var method = (options.method || 'GET').toUpperCase();
        var data = options.data;

        var fetchOptions = {
            method: method,
            headers: {}
        };

        if (method === 'GET' && data) {
            url += (url.indexOf('?') === -1 ? '?' : '&') + new URLSearchParams(data).toString();
        } else if (data) {
            fetchOptions.headers['Content-Type'] = 'application/x-www-form-urlencoded';
            fetchOptions.body = new URLSearchParams(data).toString();
        }

        return fetch(url, fetchOptions)
            .then(function(response) { return response.json(); })
            .then(function(json) {
                if (options.success) options.success(json);
                return json;
            })
            .catch(function(error) {
                if (options.error) options.error(error);
                throw error;
            });
    }

    function init() {
        var middlewareList = $('#middleware-list');
        middlewareIndex = middlewareList ? $$('.middleware-item', middlewareList).length : 0;

        if (existingMiddlewares && existingMiddlewares.length > 0) {
            loadExistingMiddlewares();
        } else if (middlewareList) {
            addMiddlewareWithValues('ProxyHeaders', []);
        }

        bindEvents();
    }

    function bindEvents() {
        var addMiddlewareBtn = $('#add-middleware');
        if (addMiddlewareBtn) {
            on(addMiddlewareBtn, 'click', addMiddleware);
        }

        on(document, 'click', '.remove-middleware', handleRemoveMiddleware);
        on(document, 'change', '.middleware-select', handleMiddlewareChange);
        on(document, 'click', '.dynamic-list-add', handleDynamicListAdd);
        on(document, 'click', '.dynamic-list-remove', handleDynamicListRemove);

        var middlewareList = $('#middleware-list');
        if (middlewareList && typeof Sortable !== 'undefined') {
            new Sortable(middlewareList, {
                handle: '.middleware-drag-handle',
                ghostClass: 'middleware-item-placeholder',
                animation: 150,
                onEnd: reindexMiddlewares
            });
        }

        $$('.reverse-proxy-toggle').forEach(function(btn) {
            on(btn, 'click', function(e) {
                e.preventDefault();
                toggleRoute(btn.dataset.routeId, btn);
            });
        });

        $$('.reverse-proxy-delete').forEach(function(btn) {
            on(btn, 'click', function(e) {
                e.preventDefault();
                if (confirm(__('Are you sure you want to delete this route?', 'reverse-proxy'))) {
                    deleteRoute(btn.dataset.routeId, btn.closest('tr'));
                }
            });
        });

        var routeForm = $('#reverse-proxy-route-form');
        if (routeForm) {
            on(routeForm, 'submit', function(e) {
                e.preventDefault();
                saveRoute(routeForm);
            });
        }

        // Import/Export handlers
        var exportBtn = $('#reverse-proxy-export');
        if (exportBtn) {
            on(exportBtn, 'click', exportRoutes);
        }

        var importBtn = $('#reverse-proxy-import');
        var importFile = $('#reverse-proxy-import-file');
        if (importBtn && importFile) {
            on(importBtn, 'click', function() { importFile.click(); });
            on(importFile, 'change', handleImportFile);
        }
    }

    function handleRemoveMiddleware() {
        var item = this.closest('.middleware-item');
        if (item) {
            item.remove();
            reindexMiddlewares();
        }
    }

    function handleMiddlewareChange() {
        updateMiddlewareFields(this);
    }

    function handleDynamicListAdd() {
        var container = this.closest('.dynamic-list-container');
        var items = $('.dynamic-list-items', container);
        var type = container.dataset.type;
        var baseName = container.dataset.name;

        if (type === 'keyvalue') {
            items.appendChild(createKeyValueItem(baseName, '', ''));
        } else {
            var firstInput = $('.dynamic-list-item:first-child input', items);
            var inputType = firstInput ? (firstInput.getAttribute('type') || 'text') : 'text';
            var placeholder = firstInput ? (firstInput.getAttribute('placeholder') || '') : '';
            items.appendChild(createRepeaterItem(baseName, inputType, placeholder, ''));
        }
    }

    function handleDynamicListRemove() {
        var items = this.closest('.dynamic-list-items');
        var allItems = $$('.dynamic-list-item', items);
        if (allItems.length > 1) {
            this.closest('.dynamic-list-item').remove();
        } else {
            $$('input', this.closest('.dynamic-list-item')).forEach(function(input) {
                input.value = '';
            });
        }
    }

    function parseMiddleware(mw) {
        if (typeof mw === 'string') return { name: mw, args: [] };
        if (Array.isArray(mw)) return { name: mw[0], args: mw.slice(1) };
        if (typeof mw === 'object' && mw.name) {
            var args = mw.args || mw.options || [];
            return { name: mw.name, args: Array.isArray(args) ? args : [args] };
        }
        return null;
    }

    function loadExistingMiddlewares() {
        var middlewareList = $('#middleware-list');
        if (!middlewareList) return;

        middlewareList.innerHTML = '';
        middlewareIndex = 0;

        existingMiddlewares.forEach(function(mw) {
            var parsed = parseMiddleware(mw);
            if (parsed) addMiddlewareWithValues(parsed.name, parsed.args);
        });
    }

    function addMiddleware() {
        var middlewareList = $('#middleware-list');
        if (middlewareList) {
            middlewareList.appendChild(createMiddlewareItem(middlewareIndex));
            middlewareIndex++;
        }
    }

    function addMiddlewareWithValues(name, args) {
        var middlewareList = $('#middleware-list');
        if (!middlewareList) return;

        var item = createMiddlewareItem(middlewareIndex, name);
        middlewareList.appendChild(item);

        var select = $('.middleware-select', item);
        if (select) {
            select.value = name;
            updateMiddlewareFields(select, args);
        }
        middlewareIndex++;
    }

    function createMiddlewareItem(index, selectedName) {
        var sortedNames = Object.keys(middlewares).sort(function(a, b) {
            return middlewares[a].label.localeCompare(middlewares[b].label);
        });

        var item = createElement('div', { className: 'middleware-item', dataset: { index: index } });
        var header = createElement('div', { className: 'middleware-header' });
        var dragHandle = createElement('span', {
            className: 'middleware-drag-handle dashicons dashicons-menu',
            title: __('Drag to reorder', 'reverse-proxy')
        });
        var select = createElement('select', {
            className: 'middleware-select',
            name: 'route[middlewares][' + index + '][name]'
        });

        select.appendChild(createElement('option', { value: '' }, __('-- Select Middleware --', 'reverse-proxy')));
        sortedNames.forEach(function(name) {
            var opt = createElement('option', { value: name }, middlewares[name].label);
            if (name === selectedName) opt.selected = true;
            select.appendChild(opt);
        });

        var removeBtn = createElement('button', {
            type: 'button',
            className: 'button button-small button-link-delete remove-middleware'
        }, __('Remove', 'reverse-proxy'));

        header.appendChild(dragHandle);
        header.appendChild(select);
        header.appendChild(removeBtn);
        item.appendChild(header);
        item.appendChild(createElement('div', { className: 'middleware-body empty' }));

        return item;
    }

    function reindexMiddlewares() {
        var middlewareList = $('#middleware-list');
        if (!middlewareList) return;

        $$('.middleware-item', middlewareList).forEach(function(item, index) {
            item.dataset.index = index;
            var select = $('.middleware-select', item);
            if (select) select.name = 'route[middlewares][' + index + '][name]';
            $$('.middleware-body input, .middleware-body textarea, .middleware-body select', item).forEach(function(input) {
                var name = input.getAttribute('name');
                if (name) input.setAttribute('name', name.replace(/\[middlewares\]\[\d+\]/, '[middlewares][' + index + ']'));
            });
        });
        middlewareIndex = $$('.middleware-item', middlewareList).length;
    }

    function updateMiddlewareFields(select, existingValues) {
        var name = select.value;
        var item = select.closest('.middleware-item');
        var body = $('.middleware-body', item);
        var index = parseInt(item.dataset.index, 10);
        var values = existingValues || [];

        body.innerHTML = '';
        body.classList.add('empty');

        if (!name || !middlewares[name]) return;

        var middleware = middlewares[name];
        var fields = middleware.fields || [];

        if (middleware.description) {
            body.appendChild(createElement('p', { className: 'description', textContent: middleware.description }));
            body.classList.remove('empty');
        }

        if (fields.length === 0) return;

        body.classList.remove('empty');
        var grid = createElement('div', { className: 'middleware-fields-grid' });

        fields.forEach(function(field, fieldIndex) {
            var ctx = {
                inputId: 'mw-' + index + '-' + field.name,
                inputName: 'route[middlewares][' + index + '][' + field.name + ']',
                value: values[fieldIndex] !== undefined ? values[fieldIndex] : field.default,
                field: field
            };
            grid.appendChild((fieldRenderers[field.type] || renderDefaultInput)(ctx));
        });

        body.appendChild(grid);
    }

    // Field Renderers
    function renderTextarea(ctx) {
        var wrapper = createFieldWrapper();
        var textarea = createElement('textarea', {
            id: ctx.inputId,
            name: ctx.inputName,
            rows: '3',
            placeholder: ctx.field.placeholder || '',
            className: 'large-text'
        });
        if (ctx.value !== undefined) textarea.value = ctx.value;
        wrapper.appendChild(createLabel(ctx));
        wrapper.appendChild(textarea);
        return wrapper;
    }

    function renderCheckbox(ctx) {
        var wrapper = createElement('div', { className: 'middleware-field-wrapper middleware-checkbox-wrapper' });
        var input = createElement('input', { type: 'checkbox', id: ctx.inputId, name: ctx.inputName, value: '1' });
        if (isTruthy(ctx.value)) input.checked = true;
        var label = createElement('label', { for: ctx.inputId });
        label.appendChild(input);
        label.appendChild(document.createTextNode(' ' + ctx.field.label));
        wrapper.appendChild(label);
        return wrapper;
    }

    function renderSelect(ctx) {
        var wrapper = createFieldWrapper();
        var select = createElement('select', { id: ctx.inputId, name: ctx.inputName });
        parseOptions(ctx.field.options).forEach(function(opt) {
            var option = createElement('option', { value: opt.value }, opt.label);
            if (ctx.value !== undefined && String(ctx.value) === String(opt.value)) option.selected = true;
            select.appendChild(option);
        });
        wrapper.appendChild(createLabel(ctx));
        wrapper.appendChild(select);
        return wrapper;
    }

    function renderCheckboxes(ctx) {
        var wrapper = createElement('div', { className: 'middleware-field-wrapper middleware-checkboxes-wrapper' });
        wrapper.appendChild(createLabel(ctx));
        var group = createElement('div', { className: 'checkbox-group' });
        var selectedValues = parseArrayValue(ctx.value);
        parseOptions(ctx.field.options).forEach(function(opt) {
            var cbId = ctx.inputId + '-' + opt.value;
            var cb = createElement('input', { type: 'checkbox', id: cbId, name: ctx.inputName + '[]', value: opt.value });
            if (selectedValues.indexOf(opt.value) !== -1) cb.checked = true;
            var label = createElement('label', { for: cbId });
            label.appendChild(cb);
            label.appendChild(document.createTextNode(' ' + opt.label));
            group.appendChild(label);
        });
        wrapper.appendChild(group);
        return wrapper;
    }

    function renderRepeater(ctx) {
        var wrapper = createElement('div', { className: 'middleware-field-wrapper middleware-repeater-wrapper' });
        wrapper.appendChild(createLabel(ctx));
        var container = createElement('div', { className: 'dynamic-list-container', dataset: { type: 'repeater', name: ctx.inputName } });
        var items = createElement('div', { className: 'dynamic-list-items' });
        var inputType = ctx.field.inputType || 'text';
        var placeholder = ctx.field.placeholder || '';
        var values = parseArrayValue(ctx.value);
        if (values.length === 0) values = [''];
        values.forEach(function(val) { items.appendChild(createRepeaterItem(ctx.inputName, inputType, placeholder, val)); });
        container.appendChild(items);
        container.appendChild(createAddButton());
        wrapper.appendChild(container);
        return wrapper;
    }

    function renderKeyValue(ctx) {
        var wrapper = createElement('div', { className: 'middleware-field-wrapper middleware-keyvalue-wrapper' });
        wrapper.appendChild(createLabel(ctx));
        var container = createElement('div', { className: 'dynamic-list-container', dataset: { type: 'keyvalue', name: ctx.inputName } });
        var header = createElement('div', { className: 'keyvalue-header' });
        header.appendChild(createElement('span', { className: 'keyvalue-key-header' }, ctx.field.keyLabel || 'Key'));
        header.appendChild(createElement('span', { className: 'keyvalue-value-header' }, ctx.field.valueLabel || 'Value'));
        header.appendChild(createElement('span', { className: 'keyvalue-action-header' }));
        container.appendChild(header);
        var items = createElement('div', { className: 'dynamic-list-items' });
        var entries = ctx.value && typeof ctx.value === 'object' ? Object.keys(ctx.value) : [];
        if (entries.length === 0) {
            items.appendChild(createKeyValueItem(ctx.inputName, '', ''));
        } else {
            entries.forEach(function(key) { items.appendChild(createKeyValueItem(ctx.inputName, key, ctx.value[key])); });
        }
        container.appendChild(items);
        container.appendChild(createAddButton());
        wrapper.appendChild(container);
        return wrapper;
    }

    function renderJson(ctx) {
        var wrapper = createElement('div', { className: 'middleware-field-wrapper middleware-json-wrapper' });
        wrapper.appendChild(createLabel(ctx));
        var textarea = createElement('textarea', { id: ctx.inputId, name: ctx.inputName, rows: '6', className: 'large-text code json-editor' });
        if (ctx.value !== undefined && ctx.value !== null) {
            try {
                textarea.value = typeof ctx.value === 'string' ? ctx.value : JSON.stringify(ctx.value, null, 2);
            } catch (e) { textarea.value = ''; }
        }
        wrapper.appendChild(textarea);
        setTimeout(function() { initCodeMirror(textarea); }, 0);
        return wrapper;
    }

    function initCodeMirror(textarea) {
        if (!window.reverseProxyAdmin || !window.reverseProxyAdmin.codeEditor || !window.wp || !window.wp.codeEditor) return;
        var baseSettings = window.reverseProxyAdmin.codeEditor;
        var settings = Object.assign({}, baseSettings, {
            codemirror: Object.assign({}, baseSettings.codemirror, {
                lineNumbers: true, lineWrapping: true, matchBrackets: true, autoCloseBrackets: true, mode: 'application/json'
            })
        });
        var editor = wp.codeEditor.initialize(textarea, settings);
        textarea._codemirror = editor.codemirror;
    }

    function renderDefaultInput(ctx) {
        var wrapper = createFieldWrapper();
        var inputClass = ctx.field.type === 'number' ? 'small-text' : 'regular-text';
        var input = createElement('input', {
            type: ctx.field.type || 'text',
            id: ctx.inputId,
            name: ctx.inputName,
            placeholder: ctx.field.placeholder || '',
            className: inputClass
        });
        if (ctx.field.required) input.required = true;
        if (ctx.value !== undefined) input.value = ctx.value;
        wrapper.appendChild(createLabel(ctx));
        wrapper.appendChild(input);
        return wrapper;
    }

    // Helper functions
    function createFieldWrapper() {
        return createElement('div', { className: 'middleware-field-wrapper' });
    }

    function createLabel(ctx) {
        return createElement('label', { for: ctx.inputId, textContent: ctx.field.label + (ctx.field.required ? ' *' : '') });
    }

    function createAddButton() {
        return createElement('button', { type: 'button', className: 'button button-small dynamic-list-add' }, __('+ Add', 'reverse-proxy'));
    }

    function createRemoveButton() {
        var btn = createElement('button', { type: 'button', className: 'button button-small button-link-delete dynamic-list-remove' });
        btn.innerHTML = '<span class="dashicons dashicons-no-alt"></span>';
        return btn;
    }

    function createRepeaterItem(baseName, inputType, placeholder, value) {
        var row = createElement('div', { className: 'dynamic-list-item' });
        var input = createElement('input', {
            type: inputType,
            name: baseName + '[]',
            placeholder: placeholder,
            className: inputType === 'number' ? 'small-text' : 'regular-text'
        });
        input.value = value || '';
        row.appendChild(input);
        row.appendChild(createRemoveButton());
        return row;
    }

    function createKeyValueItem(baseName, key, value) {
        var row = createElement('div', { className: 'dynamic-list-item keyvalue-item' });
        var keyInput = createElement('input', { type: 'text', name: baseName + '[keys][]', className: 'regular-text keyvalue-key' });
        keyInput.value = key || '';
        var valueInput = createElement('input', { type: 'text', name: baseName + '[values][]', className: 'regular-text keyvalue-value' });
        valueInput.value = value || '';
        row.appendChild(keyInput);
        row.appendChild(valueInput);
        row.appendChild(createRemoveButton());
        return row;
    }

    // Utility functions
    function isTruthy(value) {
        return value === true || value === 'true' || value === '1' || value === 1;
    }

    function parseOptions(options) {
        if (!options) return [];
        if (Array.isArray(options)) return options;
        return options.split('|').map(function(opt) {
            var parts = opt.split(':');
            return { value: parts[0].trim(), label: (parts[1] || parts[0]).trim() };
        });
    }

    function parseArrayValue(value) {
        if (!value) return [];
        if (Array.isArray(value)) return value.map(String);
        if (typeof value === 'string') return value.split(',').map(function(v) { return v.trim(); }).filter(Boolean);
        return [String(value)];
    }

    // AJAX functions
    function makeRouteAjaxRequest(action, routeId, options) {
        var settings = Object.assign({
            onSuccess: function() { location.reload(); },
            onError: function() {},
            errorMessage: __('An error occurred.', 'reverse-proxy')
        }, options);

        ajax({
            url: reverseProxyAdmin.ajaxUrl,
            method: 'POST',
            data: { action: action, nonce: reverseProxyAdmin.nonce, route_id: routeId },
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

    function toggleRoute(routeId, button) {
        button.disabled = true;
        makeRouteAjaxRequest('reverse_proxy_toggle_route', routeId, {
            errorMessage: __('Failed to toggle route status.', 'reverse-proxy'),
            onError: function() { button.disabled = false; }
        });
    }

    function deleteRoute(routeId, row) {
        makeRouteAjaxRequest('reverse_proxy_delete_route', routeId, {
            errorMessage: __('Failed to delete route.', 'reverse-proxy'),
            onSuccess: function() {
                fadeOut(row, function() {
                    row.remove();
                    if ($$('.wp-list-table tbody tr').length === 0) location.reload();
                });
            }
        });
    }

    function saveRoute(form) {
        var submitBtn = $('#submit', form);
        var originalText = submitBtn.value;
        submitBtn.disabled = true;
        submitBtn.value = __('Saving...', 'reverse-proxy');

        var formData = new FormData(form);
        formData.set('middlewares_json', JSON.stringify(collectMiddlewares()));

        ajax({
            url: reverseProxyAdmin.ajaxUrl,
            method: 'POST',
            data: formDataToObject(formData),
            success: function(response) {
                if (response.success) {
                    window.location.href = response.data.redirect || (reverseProxyAdmin.adminUrl + '?page=reverse-proxy');
                } else {
                    alert(response.data.message || __('Failed to save route.', 'reverse-proxy'));
                    submitBtn.disabled = false;
                    submitBtn.value = originalText;
                }
            },
            error: function() {
                alert(__('An error occurred while saving.', 'reverse-proxy'));
                submitBtn.disabled = false;
                submitBtn.value = originalText;
            }
        });
    }

    function collectMiddlewares() {
        var result = [];
        var middlewareList = $('#middleware-list');
        if (!middlewareList) return result;

        $$('.middleware-item', middlewareList).forEach(function(item) {
            var select = $('.middleware-select', item);
            var name = select ? select.value : '';
            if (!name) return;

            var middleware = middlewares[name];
            var fields = middleware ? middleware.fields || [] : [];
            var args = [];
            var valid = true;

            fields.forEach(function(field) {
                if (!valid) return;
                var collector = fieldCollectors[field.type] || collectDefault;
                var val = collector(item, field);
                if (val !== null && val !== undefined) {
                    args.push(val);
                } else if (field.required) {
                    valid = false;
                }
            });

            if (!valid) return;

            if (fields.length === 0 || args.length === 0) {
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
    function collectCheckbox(item, field) {
        var input = $('[name*="[' + field.name + ']"]', item);
        return input ? input.checked : false;
    }

    function collectCheckboxes(item, field) {
        var checked = [];
        $$('[name*="[' + field.name + ']"]:checked', item).forEach(function(input) {
            checked.push(input.value);
        });
        return checked.length > 0 ? checked : null;
    }

    function collectRepeater(item, field) {
        var values = [];
        var isNumber = field.inputType === 'number';
        $$('.middleware-repeater-wrapper .dynamic-list-item input', item).forEach(function(input) {
            var v = input.value;
            if (v !== '' && v !== null) values.push(isNumber && !isNaN(v) ? parseFloat(v) : v);
        });
        return values.length > 0 ? values : null;
    }

    function collectKeyValue(item) {
        var obj = {};
        $$('.middleware-keyvalue-wrapper .dynamic-list-item', item).forEach(function(row) {
            var k = $('.keyvalue-key', row);
            var v = $('.keyvalue-value', row);
            if (k && k.value) obj[k.value] = v ? v.value : '';
        });
        return Object.keys(obj).length > 0 ? obj : null;
    }

    function collectSelect(item, field) {
        var input = $('[name*="[' + field.name + ']"]', item);
        var val = input ? input.value : '';
        return val !== '' ? val : null;
    }

    function collectNumber(item, field) {
        var input = $('[name*="[' + field.name + ']"]', item);
        var val = input ? input.value : '';
        if (val !== '' && val !== null && !isNaN(val)) return parseFloat(val);
        return field.default !== undefined ? field.default : null;
    }

    function collectJson(item, field) {
        var textarea = $('.json-editor[name*="[' + field.name + ']"]', item);
        if (!textarea) return null;
        var cm = textarea._codemirror;
        var val = cm ? cm.getValue() : textarea.value;
        if (!val || val.trim() === '') return null;
        try { return JSON.parse(val); } catch (e) { return val; }
    }

    function collectDefault(item, field) {
        var input = $('[name*="[' + field.name + ']"]', item);
        var val = input ? input.value : '';
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
            if (key.indexOf('[middlewares]') === -1 || key === 'middlewares_json') obj[key] = value;
        });
        return obj;
    }

    function exportRoutes() {
        var btn = $('#reverse-proxy-export');
        btn.disabled = true;
        btn.textContent = __('Exporting...', 'reverse-proxy');

        ajax({
            url: reverseProxyAdmin.ajaxUrl,
            method: 'GET',
            data: { action: 'reverse_proxy_export_routes', nonce: reverseProxyAdmin.nonce },
            success: function(response) {
                if (response.success) {
                    downloadJson(response.data, 'reverse-proxy-routes.json');
                } else {
                    alert(response.data.message || __('Export failed.', 'reverse-proxy'));
                }
                btn.disabled = false;
                btn.textContent = __('Export', 'reverse-proxy');
            },
            error: function() {
                alert(__('Export failed.', 'reverse-proxy'));
                btn.disabled = false;
                btn.textContent = __('Export', 'reverse-proxy');
            }
        });
    }

    function downloadJson(data, filename) {
        var json = JSON.stringify(data, null, 2);
        var blob = new Blob([json], { type: 'application/json' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }

    function handleImportFile(e) {
        var file = e.target.files[0];
        if (!file) return;

        var reader = new FileReader();
        reader.onload = function(e) {
            try {
                showImportDialog(JSON.parse(e.target.result));
            } catch (err) {
                alert(__('Invalid JSON file.', 'reverse-proxy'));
            }
        };
        reader.readAsText(file);
        e.target.value = '';
    }

    function showImportDialog(data) {
        var routeCount = data.routes ? data.routes.length : 0;
        var message = __('Import %d routes?', 'reverse-proxy').replace('%d', routeCount) + '\n\n' +
            __('Choose import mode:', 'reverse-proxy') + '\n' +
            '• ' + __('Merge: Add new routes, update existing by ID', 'reverse-proxy') + '\n' +
            '• ' + __('Replace: Remove all existing routes first', 'reverse-proxy');

        var mode = prompt(message + '\n\n' + __('Enter "merge" or "replace":', 'reverse-proxy'), 'merge');

        if (mode !== 'merge' && mode !== 'replace') {
            if (mode !== null) alert(__('Invalid mode. Please enter "merge" or "replace".', 'reverse-proxy'));
            return;
        }

        importRoutes(data, mode);
    }

    function importRoutes(data, mode) {
        var btn = $('#reverse-proxy-import');
        btn.disabled = true;
        btn.textContent = __('Importing...', 'reverse-proxy');

        ajax({
            url: reverseProxyAdmin.ajaxUrl,
            method: 'POST',
            data: { action: 'reverse_proxy_import_routes', nonce: reverseProxyAdmin.nonce, data: JSON.stringify(data), mode: mode },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert(response.data.message || __('Import failed.', 'reverse-proxy'));
                    btn.disabled = false;
                    btn.textContent = __('Import', 'reverse-proxy');
                }
            },
            error: function() {
                alert(__('Import failed.', 'reverse-proxy'));
                btn.disabled = false;
                btn.textContent = __('Import', 'reverse-proxy');
            }
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})(wp);
