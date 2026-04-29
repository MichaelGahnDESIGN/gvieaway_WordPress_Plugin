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

    function fieldRow(index, type) {
        var defaults = fieldDefaults[type] || fieldDefaults.text;
        var required = defaults[2] ? ' checked' : '';
        return '<div class="mgd-field-row" draggable="true">' +
            '<button type="button" class="mgd-drag-handle" aria-label="Element verschieben">::</button>' +
            '<div class="mgd-field-row-main">' +
            '<label><span>Label</span><input type="text" name="fields[' + index + '][label]" value="' + defaults[0] + '" placeholder="Label"></label>' +
            '<label><span>Feldname</span><input type="text" name="fields[' + index + '][name]" value="' + defaults[1] + '" placeholder="feldname"></label>' +
            '<label><span>Typ</span><select class="mgd-field-type" name="fields[' + index + '][type]">' +
            '<option value="text">Text</option><option value="email">E-Mail</option><option value="number">Zahl</option><option value="date">Datum</option><option value="checkbox">Checkbox</option><option value="textarea">Mehrzeilig</option><option value="privacy">Datenschutz</option>' +
            '</select></label>' +
            '<label class="mgd-required-toggle"><input type="checkbox" name="fields[' + index + '][required]" value="1"' + required + '> Pflichtfeld</label>' +
            '<button type="button" class="button mgd-remove-field">Entfernen</button>' +
            '<label class="mgd-field-text"><span>Hinweistext</span><textarea name="fields[' + index + '][text]" rows="3" placeholder="Optionaler Text, besonders fuer Datenschutz-Hinweise">' + defaults[3] + '</textarea></label>' +
            '</div>' +
            '</div>';
    }

    $(document).on('click', '.mgd-add-field', function () {
        var container = $('#mgd-fields');
        var nextIndex = parseInt(container.attr('data-next-index'), 10) || 0;
        var type = $(this).data('type') || 'text';
        var row = $(fieldRow(nextIndex, type));
        row.find('.mgd-field-type').val(type);
        container.append(row);
        container.attr('data-next-index', nextIndex + 1);
        reindexFields();
    });

    $(document).on('click', '.mgd-remove-field', function () {
        $(this).closest('.mgd-field-row').remove();
        reindexFields();
    });

    var dragged = null;

    $(document).on('dragstart', '.mgd-field-row', function (event) {
        dragged = this;
        this.classList.add('mgd-dragging');
        event.originalEvent.dataTransfer.effectAllowed = 'move';
    });

    $(document).on('dragend', '.mgd-field-row', function () {
        this.classList.remove('mgd-dragging');
        dragged = null;
        reindexFields();
    });

    $(document).on('dragover', '#mgd-fields', function (event) {
        event.preventDefault();
        var after = getDragAfterElement(this, event.originalEvent.clientY);
        if (!dragged) {
            return;
        }
        if (after == null) {
            this.appendChild(dragged);
        } else {
            this.insertBefore(dragged, after);
        }
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

    function reindexFields() {
        $('#mgd-fields .mgd-field-row').each(function (index) {
            $(this).find('[name^="fields["]').each(function () {
                this.name = this.name.replace(/fields\[[^\]]+\]/, 'fields[' + index + ']');
            });
        });
        $('#mgd-fields').attr('data-next-index', $('#mgd-fields .mgd-field-row').length);
    }

    $(document).on('submit', '.mgd-admin form', function () {
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

    reindexFields();
})(jQuery);
