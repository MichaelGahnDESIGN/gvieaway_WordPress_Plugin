(function ($) {
    'use strict';

    function fieldRow(index) {
        return '<div class="mgd-field-row">' +
            '<input type="text" name="fields[' + index + '][label]" placeholder="Label">' +
            '<input type="text" name="fields[' + index + '][name]" placeholder="feldname">' +
            '<select name="fields[' + index + '][type]">' +
            '<option value="text">Text</option><option value="email">E-Mail</option><option value="number">Zahl</option><option value="date">Datum</option><option value="checkbox">Checkbox</option><option value="textarea">Mehrzeilig</option>' +
            '</select>' +
            '<label><input type="checkbox" name="fields[' + index + '][required]" value="1"> Pflicht</label>' +
            '<button type="button" class="button mgd-remove-field">Entfernen</button>' +
            '</div>';
    }

    $(document).on('click', '.mgd-add-field', function () {
        var container = $('#mgd-fields');
        var nextIndex = parseInt(container.attr('data-next-index'), 10) || 0;
        container.append(fieldRow(nextIndex));
        container.attr('data-next-index', nextIndex + 1);
    });

    $(document).on('click', '.mgd-remove-field', function () {
        $(this).closest('.mgd-field-row').remove();
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
})(jQuery);
