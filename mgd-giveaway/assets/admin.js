(function ($) {
    'use strict';

    var fieldDefaults = {
        text: ['Text', 'text', false, ''],
        email: ['E-Mail', 'email', true, ''],
        number: ['Zahl', 'zahl', false, ''],
        date: ['Datum', 'datum', false, ''],
        checkbox: ['Checkbox', 'checkbox', false, ''],
        textarea: ['Nachricht', 'nachricht', false, ''],
        privacy: ['Datenschutz', 'privacy', true, 'Ich habe die Datenschutzhinweise gelesen und bin mit der Verarbeitung meiner Angaben einverstanden.']
    };

    var fieldLabels = {
        text: 'Text',
        email: 'E-Mail',
        number: 'Zahl',
        date: 'Datum',
        checkbox: 'Checkbox',
        textarea: 'Mehrzeilig',
        privacy: 'Datenschutz'
    };

    var fieldIcons = {
        text: 'dashicons-editor-textcolor',
        email: 'dashicons-email-alt',
        number: 'dashicons-editor-ol',
        date: 'dashicons-calendar-alt',
        checkbox: 'dashicons-yes-alt',
        textarea: 'dashicons-text-page',
        privacy: 'dashicons-shield'
    };

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, function (char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char];
        });
    }

    function fieldRow(index, type) {
        var defaults = fieldDefaults[type] || fieldDefaults.text;
        var required = defaults[2] ? ' checked' : '';
        var label = escapeHtml(defaults[0]);
        var name = escapeHtml(defaults[1]);
        var text = escapeHtml(defaults[3]);
        var icon = fieldIcons[type] || 'dashicons-feedback';
        var typeOptions = Object.keys(fieldLabels).map(function (key) {
            return '<option value="' + key + '"' + (key === type ? ' selected' : '') + '>' + fieldLabels[key] + '</option>';
        }).join('');

        return '<div class="mgd-field-row" draggable="true" data-type="' + type + '">' +
            '<button type="button" class="mgd-drag-handle" aria-label="Element verschieben"><span class="dashicons dashicons-move"></span></button>' +
            '<button type="button" class="mgd-field-preview" aria-label="Feld bearbeiten">' +
            '<span class="mgd-field-preview-icon dashicons ' + icon + '"></span>' +
            '<span class="mgd-field-preview-body"><strong>' + label + '</strong><small>' + fieldLabels[type] + (defaults[2] ? ' - Pflichtfeld' : '') + '</small><span class="mgd-preview-control"></span></span>' +
            '</button>' +
            '<div class="mgd-field-config-slot"><div class="mgd-field-config">' +
            '<div class="mgd-config-head"><strong>Feld bearbeiten</strong><button type="button" class="button-link-delete mgd-remove-field">Entfernen</button></div>' +
            '<label><span>Label</span><input class="mgd-field-label-input" type="text" name="fields[' + index + '][label]" value="' + label + '" placeholder="Label"></label>' +
            '<label><span>Feldname</span><input type="text" name="fields[' + index + '][name]" value="' + name + '" placeholder="feldname"></label>' +
            '<label><span>Typ</span><select class="mgd-field-type" name="fields[' + index + '][type]">' + typeOptions + '</select></label>' +
            '<label class="mgd-required-toggle"><input type="checkbox" name="fields[' + index + '][required]" value="1"' + required + '> Pflichtfeld</label>' +
            '<label class="mgd-field-text"><span>Hinweistext</span><textarea name="fields[' + index + '][text]" rows="3" placeholder="Optionaler Text, besonders fuer Datenschutz-Hinweise">' + text + '</textarea></label>' +
            '</div></div>' +
            '</div>';
    }

    function addField(type, beforeRow) {
        var container = $('#mgd-fields');
        var nextIndex = parseInt(container.attr('data-next-index'), 10) || 0;
        var row = $(fieldRow(nextIndex, type || 'text'));

        if (beforeRow && beforeRow.length) {
            row.insertBefore(beforeRow);
        } else {
            container.append(row);
        }

        container.attr('data-next-index', nextIndex + 1);
        selectField(row);
        reindexFields();
    }

    $(document).on('click', '.mgd-add-field', function () {
        addField($(this).data('type') || 'text');
    });

    $(document).on('click', '.mgd-tab', function () {
        var tab = $(this).data('tab');
        if (!tab) {
            return;
        }

        $('.mgd-tab').removeClass('is-active').attr('aria-selected', 'false');
        $(this).addClass('is-active').attr('aria-selected', 'true');
        $('.mgd-tab-panel').removeClass('is-active');
        $('.mgd-tab-panel[data-panel="' + tab + '"]').addClass('is-active');
        $('#mgd_active_tab').val(tab);
    });

    $(document).on('dragstart', '.mgd-add-field', function (event) {
        event.originalEvent.dataTransfer.setData('mgd-field-type', $(this).data('type') || 'text');
        event.originalEvent.dataTransfer.effectAllowed = 'copy';
    });

    $(document).on('click', '.mgd-field-preview', function () {
        selectField($(this).closest('.mgd-field-row'));
    });

    $(document).on('click', '.mgd-remove-field', function () {
        var row = $(this).closest('.mgd-field-row');
        if (!row.length) {
            row = $('#mgd-fields .mgd-field-row.mgd-selected');
        }
        row.remove();
        $('#mgd-inspector-content').empty();
        $('#mgd-inspector-empty').show();
        reindexFields();
    });

    $(document).on('input change', '.mgd-field-config input, .mgd-field-config select, .mgd-field-config textarea', function () {
        updateSelectedPreview();
    });

    var dragged = null;

    $(document).on('dragstart', '.mgd-field-row', function (event) {
        restoreInspector();
        dragged = this;
        this.classList.add('mgd-dragging');
        event.originalEvent.dataTransfer.effectAllowed = 'move';
    });

    $(document).on('dragend', '.mgd-field-row', function () {
        this.classList.remove('mgd-dragging');
        dragged = null;
        selectField($(this));
        reindexFields();
    });

    $(document).on('dragover', '#mgd-fields', function (event) {
        event.preventDefault();
        var dataTransfer = event.originalEvent.dataTransfer;
        if (dataTransfer) {
            dataTransfer.dropEffect = dragged ? 'move' : 'copy';
        }
        var after = getDragAfterElement(this, event.originalEvent.clientY);
        if (!dragged) {
            $(this).find('.mgd-field-row').removeClass('mgd-drop-before');
            if (after) {
                $(after).addClass('mgd-drop-before');
            }
            return;
        }
        if (after == null) {
            this.appendChild(dragged);
        } else {
            this.insertBefore(dragged, after);
        }
    });

    $(document).on('dragleave drop', '#mgd-fields', function () {
        $(this).find('.mgd-field-row').removeClass('mgd-drop-before');
    });

    $(document).on('drop', '#mgd-fields', function (event) {
        event.preventDefault();
        if (dragged) {
            reindexFields();
            return;
        }

        var type = event.originalEvent.dataTransfer.getData('mgd-field-type');
        if (!type) {
            return;
        }

        addField(type, getDragAfterElement(this, event.originalEvent.clientY) ? $(getDragAfterElement(this, event.originalEvent.clientY)) : null);
    });

    function getDragAfterElement(container, y) {
        var rows = [].slice.call(container.querySelectorAll('.mgd-field-row:not(.mgd-dragging)'));
        return rows.reduce(function (closest, child) {
            var box = child.getBoundingClientRect();
            var offset = y - box.top - box.height / 2;
            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            }
            return closest;
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }

    function selectField(row) {
        if (!row || !row.length) {
            return;
        }
        restoreInspector();
        $('#mgd-fields .mgd-field-row').removeClass('mgd-selected');
        row.addClass('mgd-selected');
        $('#mgd-inspector-empty').hide();
        $('#mgd-inspector-content').append(row.find('.mgd-field-config'));
        updateSelectedPreview();
    }

    function restoreInspector() {
        var activeConfig = $('#mgd-inspector-content .mgd-field-config');
        if (!activeConfig.length) {
            return;
        }
        $('#mgd-fields .mgd-field-row.mgd-selected .mgd-field-config-slot').append(activeConfig);
    }

    function updateSelectedPreview() {
        var row = $('#mgd-fields .mgd-field-row.mgd-selected');
        var config = $('#mgd-inspector-content .mgd-field-config');
        if (!row.length || !config.length) {
            updateBuilderState();
            return;
        }

        var label = config.find('.mgd-field-label-input').val() || 'Unbenanntes Feld';
        var type = config.find('.mgd-field-type').val() || 'text';
        var required = config.find('.mgd-required-toggle input').is(':checked');
        row.attr('data-type', type);
        row.find('.mgd-field-preview-icon').attr('class', 'mgd-field-preview-icon dashicons ' + (fieldIcons[type] || 'dashicons-feedback'));
        row.find('.mgd-field-preview-body strong').text(label);
        row.find('.mgd-field-preview-body small').text((fieldLabels[type] || 'Feld') + (required ? ' - Pflichtfeld' : ''));
        updateBuilderState();
    }

    function reindexFields() {
        restoreInspector();
        $('#mgd-fields .mgd-field-row').each(function (index) {
            $(this).find('[name^="fields["]').each(function () {
                this.name = this.name.replace(/fields\[[^\]]+\]/, 'fields[' + index + ']');
            });
        });
        $('#mgd-fields').attr('data-next-index', $('#mgd-fields .mgd-field-row').length);
        updateBuilderState();
        var selected = $('#mgd-fields .mgd-field-row.mgd-selected');
        if (selected.length) {
            selectField(selected);
        }
    }

    function updateBuilderState() {
        var count = $('#mgd-fields .mgd-field-row').length;
        $('.mgd-empty-state').toggle(count === 0);
        $('.mgd-builder-count').text(count + (count === 1 ? ' Feld' : ' Felder'));
    }

    $(document).on('submit', '#mgd-giveaway-editor-form', function () {
        reindexFields();
        $('.mgd-editor-save').text('Speichert...').prop('disabled', true);
    });

    $(document).on('submit', '.mgd-admin form:not(#mgd-giveaway-editor-form)', function () {
        reindexFields();
    });

    $(document).on('click', '.mgd-select-media', function (event) {
        event.preventDefault();
        var frame = wp.media({
            title: 'Download Datei auswaehlen',
            button: { text: 'Datei verwenden' },
            multiple: false
        });

        frame.on('select', function () {
            var attachment = frame.state().get('selection').first().toJSON();
            $('#download_attachment_id').val(attachment.id);
            $('#download_attachment_title').val(attachment.title || attachment.filename);
        });

        frame.open();
    });

    updateBuilderState();
    selectField($('#mgd-fields .mgd-field-row').first());
})(jQuery);
