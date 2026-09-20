let currentTable = window.crudConfig?.table || '';
function bindBulkCheckboxLogic() {
    $(document).off('change.bulk').on('change.bulk', '.row-check, #checkAll', function () {
        if ($(this).is('#checkAll')) {
            $('.row-check').prop('checked', this.checked);
        }

        const selected = $('.row-check:checked').length > 0;
        $('#bulkmode').toggleClass('d-none', !selected);
    });
}
function loadTable() {
    const filters = getUrlParams();
    filters.limit = $('#filter_limit').val();

    const visibleColumns = window.crudConfig?.columns || [];
    // ✅ Show loader
    $('#deptable').html('<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><div class="mt-2">Loading ' + currentTable + '...</div></div>');

    $.post('crud/crud-table.php', {
        table: currentTable,
        columns: visibleColumns,
        filters: filters
    }, function (html) {
        $('#deptable').html(html);

        // ✅ Reset all checkboxes
        $('#checkAll').prop('checked', false);
        $('.row-check').prop('checked', false);

        // ✅ Hide bulk mode bar
        $('#bulkmode').addClass('d-none');

        // ✅ Rebind checkbox logic
        bindBulkCheckboxLogic();

        // ✅ Reinitialize DataTable
        if ($.fn.DataTable.isDataTable('#dynamicTable')) {
            $('#dynamicTable').DataTable().destroy();
        }
        $('#dynamicTable').DataTable({
            responsive: true,
            pageLength: 100,
            order: [],
            columnDefs: [{ targets: -1, orderable: false }]
        });
    });
}
function renderFilterBar() {
    $.post('crud/crud-config.php', { action: 'filter', table: currentTable }, function (config) {
        const params = getUrlParams();
        const inputBlocks = [];

        config.fields.forEach(field => {
            if (!field.filterable) return;

            const value = params[field.name] || '';
            let input = '';

            if (field.type === 'select') {
                const isDynamic = !Array.isArray(field.options) && field.source;

                const optionsHtml = Array.isArray(field.options)
                    ? field.options.map(opt =>
                        `<option value="${opt.value}" ${opt.value == value ? 'selected' : ''}>${opt.label}</option>`
                    ).join('')
                    : `<option value="">Loading...</option>`;

                input = `<select class="form-select form-select-sm" id="filter_${field.name}" name="${field.name}">
              <option value="">All ${field.label}</option>
              ${optionsHtml}
            </select>
            <label for="filter_${field.name}">${field.name}</label>`;

                inputBlocks.push(`<div class="form-floating me-2">${input}</div>`);

                if (isDynamic) {
                    requestIdleCallback(() => loadDynamicOptions(field, value));
                }
            } else if (field.type === 'date') {
                input = `<input type="date" class="form-control" id="filter_${field.name}" value="${value}">
            <label for="filter_${field.name}">${field.name}</label>`;
                inputBlocks.push(`<div class="form-floating me-2">${input}</div>`);
            } else {
                input = `<input type="text" class="form-control form-control-sm" id="filter_${field.name}" placeholder="Filter ${field.label}" value="${value}"><label for="filter_${field.name}">${field.name}</label>`;
                inputBlocks.push(`<div class="form-floating">${input}</div>`);
            }
        });

        if (inputBlocks.length) {
            const limitValue = params.limit || '200';
            const from_date = `<input type="date" name="from_date" id="filter_from_date" value="" class="form-control" placeholder="From Date">
                <label for="filter_from_date">From Date</label>
                <div class="invalid-feedback">Please enter a valid from date.</div>`;
            const to_date = `<input type="date" name="to_date" id="filter_to_date" value="" class="form-control" placeholder="From Date">
                <label for="filter_to_date">To Date</label>
                <div class="invalid-feedback">Please enter a valid from date.</div>`;
            const limitSelect = `
        <select id="filter_limit" class="form-select form-select-sm">
          <option value="25" ${limitValue == 25 ? 'selected' : ''}>25 rows</option>
          <option value="50" ${limitValue == 50 ? 'selected' : ''}>50 rows</option>
          <option value="100" ${limitValue == 100 ? 'selected' : ''}>100 rows</option>
          <option value="200" ${limitValue == 200 ? 'selected' : ''}>200 rows</option>
          <option value="10000" ${limitValue == 10000 ? 'selected' : ''}>All rows</option>
        </select><label for="filter_limit">Limit</label>`;

            inputBlocks.push(`<div class="form-floating">${from_date}</div>`);
            inputBlocks.push(`<div class="form-floating">${to_date}</div>`);
            inputBlocks.push(`<div class="form-floating">${limitSelect}</div>`);
            inputBlocks.push(`<div>
        <button class="btn btn-app btn-primary m-0" onclick="applyFilters()">
          <i class="bi bi-filter"></i> Apply
        </button>
      </div>`);
        }

        $('#filterBar').html(`<div class="d-flex flex-wrap gap-2 align-items-end">${inputBlocks.join('')}</div>`);
    }, 'json');
}
function loadDynamicOptions(field, selectedValue = '') {
    const select =
        document.getElementById(`form_${field.name}`) ||
        document.getElementById(`filter_${field.name}`);
    if (!select) {
        console.warn(`Select element not found for ${field.name}`);
        return;
    }

    if ($(select).hasClass("select2-hidden-accessible")) {
        $(select).select2('destroy');
    }

    select.innerHTML = `<option value="">Loading...</option>`;

    fetch('crud/crud-options.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            table: field.source,
            id: field.valueKey || 'id',
            text: field.labelKey || 'name'
        })
    })
        .then(res => res.json())
        .then(options => {
            if (!Array.isArray(options)) {
                select.innerHTML = `<option value="">No options found</option>`;
                return;
            }

            select.innerHTML =
                `<option value="">All ${field.label}</option>` +
                options.map(opt => {
                    const selected = opt.value == selectedValue ? 'selected' : '';
                    return `<option value="${opt.value}" ${selected}>${opt.label}</option>`;
                }).join('');

            enhanceSelect2(field.name, field.label);
        })
        .catch(err => {
            console.error(`Error loading options for ${field.name}:`, err);
            select.innerHTML = `<option value="">Error loading options</option>`;
        });
}
function openModal(id = '', data = {}) {
    $('#crudForm')[0].reset();
    $('#formFields').html('');
    $('#crudForm [name=id]').remove();
    $('#crudForm').removeClass('was-validated');
    $('#crudForm input, #crudForm select').removeClass('is-valid is-invalid');

    $.post('crud/crud-config.php', { action: 'filter', table: currentTable }, function (config) {
        $('#crudCanvasLabel').text(id ? 'Edit ' + currentTable : 'Add ' + currentTable);

        config.fields.forEach(field => {
            const value = data[field.name] ?? field.value ?? '';
            const cfg = field.__config || {};
            const isAdmin = cfg.isAdmin;
            const lockedValue = cfg[field.valueKey];
            const labelKey = field.labelKey || 'name';
            let input = '';

            if (field.type === 'select') {
                const isDynamic = !field.options && field.source;

                if (!isAdmin && lockedValue !== undefined) {
                    fetch('crud/crud-options.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            table: field.source,
                            id: field.valueKey,
                            text: labelKey
                        })
                    })
                        .then(res => res.json())
                        .then(options => {
                            const match = options.find(opt => String(opt.value) === String(lockedValue));
                            const label = match?.label || 'Selected';
                            const lockedSelect = `
                <div class="mb-3">
                  <select name="${field.name}" id="form_${field.name}" class="form-select">
                    <option value="${lockedValue}" selected>${label}</option>
                  </select>
                  <div class="invalid-feedback">Please select a valid ${field.label.toLowerCase()}.</div>
                </div>`;
                            $('#formFields').append(lockedSelect);
                        })
                        .catch(err => {
                            console.error(`Error loading locked label for ${field.name}:`, err);
                            const fallbackSelect = `
                <div class="mb-3">
                  <select name="${field.name}" id="form_${field.name}" class="form-select">
                    <option value="${lockedValue}" selected>Selected</option>
                  </select>
                  <div class="invalid-feedback">Please select a valid ${field.label.toLowerCase()}.</div>
                </div>`;
                            $('#formFields').append(fallbackSelect);
                        });
                    return;
                }

                const wrapperClass = isDynamic ? 'mb-3' : 'form-floating mb-3';
                const labelTag = isDynamic ? '' : `<label for="form_${field.name}">${field.label}</label>`;
                input = `
          <div class="${wrapperClass}">
            <select name="${field.name}" id="form_${field.name}" class="form-select" placeholder="${field.label}" ${field.required ? 'required' : ''}>
              ${field.options
                        ? field.options.map(opt =>
                            `<option value="${opt.value}" ${String(opt.value) === String(value) ? 'selected' : ''}>${opt.label}</option>`
                        ).join('')
                        : `<option value="">Loading...</option>`}
            </select>
            ${labelTag}
            <div class="invalid-feedback">Please select a valid ${field.label.toLowerCase()}.</div>
          </div>`;
                $('#formFields').append(input);
                if (isDynamic) setTimeout(() => loadDynamicOptions(field, value), 0);
            } else {
                const stepAttr = field.step ? `step="${field.step}"` : '';
                const minAttr = field.min ? `min="${field.min}"` : '';
                const maxAttr = field.max ? `max="${field.max}"` : '';
                const patternAttr = field.pattern ? `pattern="${field.pattern}"` : '';

                if (!isAdmin && lockedValue !== undefined) {
                    input = `
            <div class="form-floating mb-3">
              <input type="${field.type}" name="${field.name}" id="form_${field.name}" value="${lockedValue}"
                class="form-control" placeholder="${field.placeholder || field.label}" readonly>
              <label for="form_${field.name}">${field.label}</label>
              <div class="invalid-feedback">Please enter a valid ${field.label.toLowerCase()}.</div>
            </div>
            <input type="hidden" name="${field.name}" value="${lockedValue}">`;
                } else {
                    const hideClass = field.type === 'hidden' ? 'd-none' : '';
                    input = `
        <div class="form-floating mb-3 ${hideClass}">
          <input type="${field.type}" name="${field.name}" id="form_${field.name}" value="${value}"
            class="form-control" placeholder="${field.placeholder || field.label}"
            ${field.required ? 'required' : ''} ${stepAttr} ${minAttr} ${maxAttr} ${patternAttr}>
          <label for="form_${field.name}">${field.label}</label>
          <div class="invalid-feedback">Please enter a valid ${field.label.toLowerCase()}.</div>
        </div>`;
                }

                $('#formFields').append(input);
            }
        });

        if (id) {
            $('#formFields').prepend(`<input type="hidden" name="id" value="${id}">`);
        }

        bootstrap.Offcanvas.getOrCreateInstance('#crudCanvas').show();
    }, 'json');
}
function applyFilters() {
    const params = new URLSearchParams(window.location.search);

    $('#filterBar [id^=filter_]').each(function () {
        const key = this.id.replace('filter_', '');
        const val = $(this).val();
        if (val) params.set(key, val);
        else params.delete(key);
    });
    // alert(params.toString());
    const newUrl = window.location.pathname + '?' + params.toString();
    window.history.replaceState({}, '', newUrl);
    loadTable();
}
function getUrlParams() {
    const params = {};
    const query = window.location.search.substring(1);
    query.split('&').forEach(pair => {
        const [key, value] = pair.split('=');
        if (key) params[decodeURIComponent(key)] = decodeURIComponent(value || '');
    });
    return params;
}

function enhanceSelect2(fieldName, labelText = 'Select an option') {

    const select = document.getElementById(`form_${fieldName}`);
    if (!select) return;
    // 🛑 Agar options 10 se kam hain, toh Select2 mat chalao (Static rakho)
    // const optionsCount = select.options.length;
    // if (optionsCount < 10) {
    //     if ($(select).hasClass("select2-hidden-accessible")) {
    //         $(select).select2('destroy');
    //     }
    //     $(select).removeClass('select2-hidden-accessible');
    //     return;
    // }
    // Destroy previous instance
    if ($(select).hasClass("select2-hidden-accessible")) {
        $(select).select2('destroy');
    }

    // ✅ Bind dropdown to canvas to fix z-index and focus
    const dropdownParent = document.querySelector('#crudCanvas') || document.body;

    $(select).select2({
        theme: 'bootstrap-5',
        placeholder: labelText,
        allowClear: true,
        dropdownParent: $(dropdownParent) // ✅ critical for modals/canvas
    });

    // ✅ Floating label fix
    setTimeout(() => {
        if (select.value) {
            select.classList.add('filled');
        } else {
            select.classList.remove('filled');
        }
    }, 100);
}

function deleteCrud(id) {
    if (confirm("Delete this record?")) {
        $.post('crud/crud.php', { action: 'delete', table: currentTable, id }, function (res) {
            const type = res.success === false || res.status === 'error' ? 'error' : 'success';
            const message = res.message || res.error || (type === 'success' ? 'Deleted successfully' : 'Delete failed');
            boom(message, type, true);
            if (type === 'success') loadTable();
        }, 'json');
    }
}
function toggleStatus(id, currentStatus) {
    const newStatus = currentStatus ? 0 : 1;
    const label = newStatus ? 'Unblock' : 'Block';

    if (confirm(`Are you sure you want to ${label} this record?`)) {
        const payload = {
            action: 'save',
            table: currentTable,
            data: { id, status: newStatus }
        };

        $.post('crud/crud.php', payload, function (res) {
            const type = res.success === false || res.status === 'error' ? 'error' : 'success';
            const message = res.message || res.error || (type === 'success' ? 'Status updated' : 'Status change failed');
            boom(message, type, true);
            if (type === 'success') loadTable();
        }, 'json');
    }
}

function bulkToggleStatus(newStatus) {
    const ids = $('.row-check:checked').map(function () {
        return $(this).val();
    }).get();

    if (ids.length === 0) return boom('No rows selected', 'warning', true);

    const label = newStatus ? 'Unblock' : 'Block';
    if (confirm(`Are you sure you want to ${label} ${ids.length} records?`)) {
        $.post('crud/crud.php', {
            action: 'bulk-status',
            table: currentTable,
            ids: ids,
            status: newStatus
        }, function (res) {
            const type = res.success === false || res.status === 'error' ? 'error' : 'success';
            const message = res.message || res.error || (type === 'success' ? 'Bulk status updated' : 'Bulk status failed');
            boom(message, type, true);
            if (type === 'success') {
                // $('#bulkBlockBtn, #bulkUnblockBtn').addClass('d-none');
                loadTable();
            }
        }, 'json');
    }
}

function bulkDelete() {
    const ids = $('.row-check:checked').map(function () {
        return $(this).val();
    }).get();

    if (ids.length === 0) return boom('No rows selected', 'warning', true);

    if (confirm(`Delete ${ids.length} selected records?`)) {
        $.post('crud/crud.php', {
            action: 'bulk-delete',
            table: currentTable,
            ids: ids
        }, function (res) {
            const type = res.success === false || res.status === 'error' ? 'error' : 'success';
            const message = res.message || res.error || (type === 'success' ? 'Bulk delete completed' : 'Bulk delete failed');
            boom(message, type, true);
            if (type === 'success') loadTable();
        }, 'json');
    }
}
function sendBulkSMS(smstype) {
    const ids = $('.row-check:checked').map(function () {
        return $(this).val(); // assuming this is salesorder_id
    }).get();

    if (ids.length === 0) return boom('No rows selected', 'warning', true);
    // alert(ids);
    const label = smstype === 1 ? 'Ready to Ship' : 'Ready to Collect';
    if (confirm(`Are you sure you want to send "${label}" SMS to ${ids.length} records?`)) {
        $.post('model/zohoapi.php', {
            action: 'bulk-sms',
            table: currentTable,
            ids: ids,
            smstype: smstype
        }, function (res) {
            const type = res.success === false || res.status === 'error' ? 'error' : 'success';
            const message = res.message || res.error || (type === 'success' ? 'Bulk SMS sent' : 'Bulk SMS failed');
            boom(message, type, true);
            if (type === 'success') {
                loadTable();
            }
        }, 'json');
    }
}
// ajax based
function rpPost(action, payload = {}, callback, url = 'model/ajax/api-actions.php') {
    const postData = Object.assign({}, payload, { pos_action: action });
    $.ajax({
        url: url,
        type: 'POST',
        data: postData,
        beforeSend: function () {
            $('.before_ajx').show();
        },
        success: function (response) {
            $('.before_ajx').hide();

            // Agar response pehle se hi object hai toh parse mat karo
            let data = response;
            if (typeof response === 'string') {
                try {
                    data = $.parseJSON(response);
                    boom(response || 'Done', 'success');
                } catch (e) {
                    // Agar JSON parse fail ho, toh debugapi mein daalo + fallback object do taaki app crash na ho
                    $('.debugApi').html(response);
                    data = { success: false, message: 'Server Response Error: ' + response };
                }
            }

            if (typeof callback === 'function') callback(data);
        },
        error: function (xhr) {
            $('.before_ajx').hide();
            console.error('AJAX error:', xhr.responseText);
            if (typeof callback === 'function') {
                callback({ success: false, message: 'Network or Server Error (HTTP ' + xhr.status + ')' });
            }
        }
    });
}
$('#crudForm').on('submit', function (e) {
    e.preventDefault();
    const form = this;

    if (!form.checkValidity()) {
        e.stopPropagation();
        $(form).addClass('was-validated');
        return;
    }

    const formData = $(form).serializeArray().reduce((obj, item) => {
        obj[item.name] = item.value;
        return obj;
    }, {});

    // 🚀 Loader Enable: Button ko disable karo aur spinner lagao
    const $submitBtn = $(form).find('button[type="submit"]');
    const originalBtnText = $submitBtn.html();
    $submitBtn.prop('disabled', true).html('<i class="fa fa-circle-o-notch fa-spin me-1"></i> Saving...');

    $.post('crud/crud.php', {
        action: 'save',
        table: currentTable,
        data: formData
    }, function (res) {
        // 🛑 Loader Disable: Response aate hi button ko restore karo
        $submitBtn.prop('disabled', false).html(originalBtnText);

        const type = res.success === false || res.status === 'error' ? 'error' : 'success';
        const message = res.message || res.error || (type === 'success' ? 'Saved successfully' : 'Save failed');

        boom(message, type, true);

        if (type === 'success') {
            bootstrap.Offcanvas.getInstance('#crudCanvas')?.hide();

            if (window.targetSelectName) {
                let lastId = res.insert_id || res.id;
                let recordName = formData.name || formData.title || res.name || $('#crudForm input[type="text"]:first').val() || 'New Item';

                // Name ya ID ke through page par maujood original dropdown ko target karo
                let $select = $('[name="' + window.targetSelectName + '"], #' + window.targetSelectName);

                if ($select.length && lastId) {
                    let newOption = new Option(recordName, lastId, true, true);
                    $select.append(newOption).trigger('change');

                    if ($select.hasClass('select2-hidden-accessible')) {
                        $select.trigger('change.select2');
                    }
                }
            }

            try {
                if ($('#deptable').length || $('#dynamicTable').length) {
                    loadTable();
                }
            } catch (err) {
                console.error('Table reload failed:', err);
            }
        }
    }, 'json').fail(function() {
        // 🛑 Error/Failure par bhi loader remove karna zaroori hai
        $submitBtn.prop('disabled', false).html(originalBtnText);
        boom('Server error occurred', 'error', true);
    });
});
$('#crudForm').on('input change', 'input, select', function () {
    const field = this;
    const isValid = field.checkValidity();

    field.classList.toggle('is-valid', isValid);
    field.classList.toggle('is-invalid', !isValid);
});
// Optional: check/uncheck all
$(document).on('change', '#checkAll', function () {
    $('.row-check').prop('checked', this.checked);
});

$(document).ready(function () {
    // Sirf tabhi filter bar aur table load karo jab element page par exist karta ho
    if ($('#filterBar').length) {
        renderFilterBar();
    }

    if ($('#deptable').length || $('#dynamicTable').length) {
        loadTable();
    }
});
// Global override ya direct click handler jo Neufinix framework ke sath 100% compatible ho
$(document).on('click', '.quick-add-btn', function (e) {
    e.preventDefault();

    let $btn = $(this);
    let table = $btn.attr('data-table');

    // ✅ Button ke pas wala select element dhoond kar uska name ya id save kar lo
    let $targetSelect = $btn.closest('.input-group, .mb-3, div').find('select');
    if (!$targetSelect.length) {
        $targetSelect = $btn.parent().find('select');
    }

    // Global variable mein select ka name/id ya element store karo
    window.targetSelectName = $targetSelect.attr('name') || $targetSelect.attr('id');

    if (!table) return;

    window.currentTable = table;
    currentTable = table;

    if (typeof window.crudConfig === 'undefined') window.crudConfig = {};
    window.crudConfig.table = table;

    if (typeof openModal === 'function') {
        openModal();
    }
});