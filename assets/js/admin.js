/**
 * Reverse Proxy Admin JavaScript (Vanilla JS, Template-based)
 */
(function() {
    'use strict';

    var config = window.reverseProxyAdmin || {};
    var i18n = config.i18n || {};
    var middlewares = config.middlewares || {};
    var existingMiddlewares = config.existingMiddlewares || [];
    var middlewareIndex = 0;

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
        json: collectJson,
        select: collectDefault,
        number: collectDefault
    };

    // DOM Helper functions
    function $(selector, context) {
        return (context || document).querySelector(selector);
    }

    function $$(selector, context) {
        return Array.from((context || document).querySelectorAll(selector));
    }

    function cloneTemplate(templateId) {
        var template = $('#' + templateId);
        return template ? template.content.cloneNode(true).firstElementChild : null;
    }

    function setupField(templateId, ctx, inputSelector) {
        var wrapper = cloneTemplate(templateId);
        if (!wrapper) return null;

        var label = $('label', wrapper);
        var input = $(inputSelector || 'input, textarea, select', wrapper);

        if (label && !$('.label-text', label)) {
            label.setAttribute('for', ctx.inputId);
            label.textContent = ctx.field.label + (ctx.field.required ? ' *' : '');
        }

        if (input) {
            input.id = ctx.inputId;
            input.name = ctx.inputName;
        }

        return { wrapper: wrapper, label: label, input: input };
    }

    function setupDynamicList(templateId, ctx) {
        var field = setupField(templateId, ctx);
        var container = $('.dynamic-list-container', field.wrapper);
        container.dataset.name = ctx.inputName;
        return { wrapper: field.wrapper, container: container, items: $('.dynamic-list-items', field.wrapper) };
    }

    function getFieldInput(item, fieldName) {
        return $('[name*="[' + fieldName + ']"]', item);
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

    function withButtonLoading(btn, loadingText, action) {
        var originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = loadingText;
        return function(success) {
            if (!success) {
                btn.disabled = false;
                btn.textContent = originalText;
            }
            if (action) action(success);
        };
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
            addMiddleware('ProxyHeaders');
        }

        bindEvents();
    }

    function bindEvents() {
        var addMiddlewareBtn = $('#add-middleware');
        if (addMiddlewareBtn) {
            on(addMiddlewareBtn, 'click', function() { addMiddleware(); });
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
                if (confirm(i18n.confirmDelete)) {
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
            if (parsed) addMiddleware(parsed.name, parsed.args);
        });
    }

    function addMiddleware(name, args) {
        var middlewareList = $('#middleware-list');
        if (!middlewareList) return;

        var item = createMiddlewareItem(middlewareIndex, name);
        middlewareList.appendChild(item);

        if (name) {
            var select = $('.middleware-select', item);
            if (select) {
                select.value = name;
                updateMiddlewareFields(select, args);
            }
        }
        middlewareIndex++;
    }

    function createMiddlewareItem(index, selectedName) {
        var item = cloneTemplate('middleware-item-template');
        if (!item) return null;

        item.dataset.index = index;

        var select = $('.middleware-select', item);
        select.name = 'route[middlewares][' + index + '][name]';

        // Add middleware options sorted by label
        Object.keys(middlewares).sort(function(a, b) {
            return middlewares[a].label.localeCompare(middlewares[b].label);
        }).forEach(function(name) {
            var opt = cloneTemplate('select-option-template');
            opt.value = name;
            opt.textContent = middlewares[name].label;
            if (name === selectedName) opt.selected = true;
            select.appendChild(opt);
        });

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
            var desc = cloneTemplate('description-template');
            desc.textContent = middleware.description;
            body.appendChild(desc);
            body.classList.remove('empty');
        }

        if (fields.length === 0) return;

        body.classList.remove('empty');
        var grid = cloneTemplate('fields-grid-template');

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

    // Field Renderers using templates
    function renderTextarea(ctx) {
        var field = setupField('field-textarea-template', ctx, 'textarea');
        field.input.placeholder = ctx.field.placeholder || '';
        if (ctx.value !== undefined) field.input.value = ctx.value;
        return field.wrapper;
    }

    function renderCheckbox(ctx) {
        var field = setupField('field-checkbox-template', ctx, 'input');
        field.label.setAttribute('for', ctx.inputId);
        if (isTruthy(ctx.value)) field.input.checked = true;
        $('.label-text', field.wrapper).textContent = ctx.field.label;
        return field.wrapper;
    }

    function renderSelect(ctx) {
        var field = setupField('field-select-template', ctx, 'select');
        parseOptions(ctx.field.options).forEach(function(opt) {
            var option = cloneTemplate('select-option-template');
            option.value = opt.value;
            option.textContent = opt.label;
            if (ctx.value !== undefined && String(ctx.value) === String(opt.value)) option.selected = true;
            field.input.appendChild(option);
        });
        return field.wrapper;
    }

    function renderCheckboxes(ctx) {
        var field = setupField('field-checkboxes-template', ctx);
        var group = $('.checkbox-group', field.wrapper);
        var selectedValues = parseArrayValue(ctx.value);

        parseOptions(ctx.field.options).forEach(function(opt) {
            var cbId = ctx.inputId + '-' + opt.value;
            var optionEl = cloneTemplate('field-checkbox-option-template');
            var cb = $('input', optionEl);

            optionEl.setAttribute('for', cbId);
            cb.id = cbId;
            cb.name = ctx.inputName + '[]';
            cb.value = opt.value;
            if (selectedValues.indexOf(opt.value) !== -1) cb.checked = true;
            $('.option-label', optionEl).textContent = opt.label;
            group.appendChild(optionEl);
        });
        return field.wrapper;
    }

    function renderRepeater(ctx) {
        var list = setupDynamicList('field-repeater-template', ctx);
        var inputType = ctx.field.inputType || 'text';
        var placeholder = ctx.field.placeholder || '';
        var values = parseArrayValue(ctx.value);
        if (values.length === 0) values = [''];

        values.forEach(function(val) {
            list.items.appendChild(createRepeaterItem(ctx.inputName, inputType, placeholder, val));
        });
        return list.wrapper;
    }

    function renderKeyValue(ctx) {
        var list = setupDynamicList('field-keyvalue-template', ctx);
        $('.keyvalue-key-header', list.wrapper).textContent = ctx.field.keyLabel || 'Key';
        $('.keyvalue-value-header', list.wrapper).textContent = ctx.field.valueLabel || 'Value';

        var entries = ctx.value && typeof ctx.value === 'object' ? Object.keys(ctx.value) : [];
        if (entries.length === 0) {
            list.items.appendChild(createKeyValueItem(ctx.inputName, '', ''));
        } else {
            entries.forEach(function(key) {
                list.items.appendChild(createKeyValueItem(ctx.inputName, key, ctx.value[key]));
            });
        }
        return list.wrapper;
    }

    function renderJson(ctx) {
        var field = setupField('field-textarea-template', ctx, 'textarea');
        field.wrapper.classList.add('middleware-json-wrapper');
        field.input.rows = 6;
        field.input.classList.add('code', 'json-editor');

        if (ctx.value !== undefined && ctx.value !== null) {
            try {
                field.input.value = typeof ctx.value === 'string' ? ctx.value : JSON.stringify(ctx.value, null, 2);
            } catch (e) { field.input.value = ''; }
        }

        setTimeout(function() { initCodeMirror(field.input); }, 0);
        return field.wrapper;
    }

    function initCodeMirror(textarea) {
        if (!config.codeEditor || !window.wp || !window.wp.codeEditor) return;
        var settings = Object.assign({}, config.codeEditor, {
            codemirror: Object.assign({}, config.codeEditor.codemirror, {
                lineNumbers: true, lineWrapping: true, matchBrackets: true, autoCloseBrackets: true, mode: 'application/json'
            })
        });
        var editor = wp.codeEditor.initialize(textarea, settings);
        textarea._codemirror = editor.codemirror;
    }

    function renderDefaultInput(ctx) {
        var field = setupField('field-input-template', ctx, 'input');
        field.input.type = ctx.field.type || 'text';
        field.input.placeholder = ctx.field.placeholder || '';
        field.input.className = ctx.field.type === 'number' ? 'small-text' : 'regular-text';
        if (ctx.field.required) field.input.required = true;
        if (ctx.value !== undefined) field.input.value = ctx.value;
        return field.wrapper;
    }

    // Helper functions
    function createRepeaterItem(baseName, inputType, placeholder, value) {
        var row = cloneTemplate('repeater-item-template');
        var input = $('input', row);

        input.type = inputType;
        input.name = baseName + '[]';
        input.placeholder = placeholder || '';
        input.className = inputType === 'number' ? 'small-text' : 'regular-text';
        input.value = value || '';

        return row;
    }

    function createKeyValueItem(baseName, key, value) {
        var row = cloneTemplate('keyvalue-item-template');
        var keyInput = $('.keyvalue-key', row);
        var valueInput = $('.keyvalue-value', row);

        keyInput.name = baseName + '[keys][]';
        keyInput.value = key || '';
        valueInput.name = baseName + '[values][]';
        valueInput.value = value || '';

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
            errorMessage: i18n.error
        }, options);

        ajax({
            url: config.ajaxUrl,
            method: 'POST',
            data: { action: action, nonce: config.nonce, route_id: routeId },
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
            errorMessage: i18n.toggleFailed,
            onError: function() { button.disabled = false; }
        });
    }

    function deleteRoute(routeId, row) {
        makeRouteAjaxRequest('reverse_proxy_delete_route', routeId, {
            errorMessage: i18n.deleteFailed,
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
        submitBtn.value = i18n.saving;

        var formData = new FormData(form);
        formData.set('middlewares_json', JSON.stringify(collectMiddlewares()));

        ajax({
            url: config.ajaxUrl,
            method: 'POST',
            data: formDataToObject(formData),
            success: function(response) {
                if (response.success) {
                    window.location.href = response.data.redirect || (config.adminUrl + '?page=reverse-proxy');
                } else {
                    alert(response.data.message || i18n.saveFailed);
                    submitBtn.disabled = false;
                    submitBtn.value = originalText;
                }
            },
            error: function() {
                alert(i18n.saveError);
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
        var input = getFieldInput(item, field.name);
        return input ? input.checked : false;
    }

    function collectCheckboxes(item, field) {
        var checked = $$('[name*="[' + field.name + ']"]:checked', item).map(function(input) {
            return input.value;
        });
        return checked.length > 0 ? checked : null;
    }

    function collectRepeater(item, field) {
        var isNumber = field.inputType === 'number';
        var values = $$('.middleware-repeater-wrapper .dynamic-list-item input', item)
            .map(function(input) { return input.value; })
            .filter(function(v) { return v !== '' && v !== null; })
            .map(function(v) { return isNumber && !isNaN(v) ? parseFloat(v) : v; });
        return values.length > 0 ? values : null;
    }

    function collectKeyValue(item) {
        var obj = {};
        $$('.middleware-keyvalue-wrapper .dynamic-list-item', item).forEach(function(row) {
            var k = $('.keyvalue-key', row), v = $('.keyvalue-value', row);
            if (k && k.value) obj[k.value] = v ? v.value : '';
        });
        return Object.keys(obj).length > 0 ? obj : null;
    }

    function collectJson(item, field) {
        var textarea = $('.json-editor[name*="[' + field.name + ']"]', item);
        if (!textarea) return null;
        var val = textarea._codemirror ? textarea._codemirror.getValue() : textarea.value;
        if (!val || val.trim() === '') return null;
        try { return JSON.parse(val); } catch (e) { return val; }
    }

    function collectDefault(item, field) {
        var input = getFieldInput(item, field.name);
        var val = input ? input.value : '';
        if (val === '' || val === null || val === undefined) {
            return field.type === 'number' && field.default !== undefined ? field.default : null;
        }
        if (val === 'true') return true;
        if (val === 'false') return false;
        if (field.type === 'number' && !isNaN(val)) return parseFloat(val);
        return val;
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
        var done = withButtonLoading(btn, i18n.exporting);

        ajax({
            url: config.ajaxUrl,
            method: 'GET',
            data: { action: 'reverse_proxy_export_routes', nonce: config.nonce },
            success: function(response) {
                if (response.success) {
                    downloadJson(response.data, 'reverse-proxy-routes.json');
                } else {
                    alert(response.data.message || i18n.exportFailed);
                }
                done(false);
                btn.textContent = i18n.export;
            },
            error: function() {
                alert(i18n.exportFailed);
                done(false);
                btn.textContent = i18n.export;
            }
        });
    }

    function downloadJson(data, filename) {
        var blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
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
                alert(i18n.invalidJson);
            }
        };
        reader.readAsText(file);
        e.target.value = '';
    }

    function showImportDialog(data) {
        var routeCount = data.routes ? data.routes.length : 0;
        var message = i18n.importRoutes.replace('%d', routeCount) + '\n\n' +
            i18n.chooseMode + '\n' +
            '• ' + i18n.mergeMode + '\n' +
            '• ' + i18n.replaceMode;

        var mode = prompt(message + '\n\n' + i18n.enterMode, 'merge');

        if (mode !== 'merge' && mode !== 'replace') {
            if (mode !== null) alert(i18n.invalidMode);
            return;
        }

        importRoutes(data, mode);
    }

    function importRoutes(data, mode) {
        var btn = $('#reverse-proxy-import');
        var done = withButtonLoading(btn, i18n.importing);

        ajax({
            url: config.ajaxUrl,
            method: 'POST',
            data: { action: 'reverse_proxy_import_routes', nonce: config.nonce, data: JSON.stringify(data), mode: mode },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert(response.data.message || i18n.importFailed);
                    done(false);
                    btn.textContent = i18n.import;
                }
            },
            error: function() {
                alert(i18n.importFailed);
                done(false);
                btn.textContent = i18n.import;
            }
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
